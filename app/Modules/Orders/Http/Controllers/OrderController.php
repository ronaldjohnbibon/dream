<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Delivery\Services\DeliveryPricingService;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Notifications\Services\CustomerNotificationService;
use App\Modules\Orders\Http\Requests\StoreOrderRequest;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Services\PointsService;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly PointsService $points,
        private readonly CustomerNotificationService $notifications,
        private readonly DeliveryPricingService $deliveryPricing,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'delivery_status' => ['nullable', 'in:all,'.implode(',', Delivery::STATUSES)],
            'payment_type' => ['nullable', 'in:all,'.implode(',', Order::PAYMENT_TYPES)],
            'payment_status' => ['nullable', 'in:all,'.implode(',', Order::PAYMENT_STATUSES)],
        ]);
        $search = $filters['search'] ?? '';
        $deliveryStatus = $filters['delivery_status'] ?? 'all';
        $paymentType = $filters['payment_type'] ?? 'all';
        $paymentStatus = $filters['payment_status'] ?? 'all';
        $user = $request->user();

        $orders = Order::query()
            ->with(['customer:id,name,email,mobile_number', 'riceProduct:id,name,brand,sack_size', 'delivery'])
            ->when(! $user->is_admin, fn ($query) => $query->where('customer_id', $user->id))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                ->orWhereHas('riceProduct', fn ($product) => $product->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%"))))
            ->when($deliveryStatus !== 'all', fn ($query) => $query->whereHas('delivery', fn ($delivery) => $delivery->where('status', $deliveryStatus)))
            ->when($paymentType !== 'all', fn ($query) => $query->where('payment_type', $paymentType))
            ->when($paymentStatus !== 'all', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->latest('order_date')->latest('id')->paginate(15)->withQueryString()
            ->through(fn (Order $order) => $this->orderData($order));

        return Inertia::render('modules/orders/Index', [
            'orders' => $orders,
            'filters' => ['search' => $search, 'delivery_status' => $deliveryStatus, 'payment_type' => $paymentType, 'payment_status' => $paymentStatus],
            'canManage' => $user->is_admin,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Order::class);
        $customer = $request->user();
        $system = SystemSetting::current();
        $points = $this->points->settings();

        return Inertia::render('modules/orders/Create', [
            'products' => RiceProduct::query()->where('is_active', true)->where('available_stock', '>', 0)->orderBy('name')->get(['id', 'name', 'brand', 'selling_price', 'available_stock']),
            'areas' => DeliveryArea::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'delivery_fee'])
                ->map(fn (DeliveryArea $area) => ['id' => $area->id, 'name' => $area->name, 'delivery_fee' => number_format($this->deliveryPricing->feeFor($area), 2, '.', '')]),
            'customer' => ['complete_address' => $customer->complete_address, 'delivery_area' => $customer->delivery_area],
            'points' => [
                'enabled' => $points->is_enabled,
                'balance' => $this->points->currentBalance($customer),
                'peso_per_point' => $points->peso_per_point,
                'minimum_redemption' => $points->minimum_redemption,
                'maximum_points_usable' => $points->maximum_points_usable,
            ],
            'pautang' => ['enabled' => $system->pautang_enabled, 'maximum_sacks' => $system->pautang_max_sacks],
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $redeemedPoints = null;

        $order = DB::transaction(function () use ($attributes, $request, &$redeemedPoints): Order {
            $customer = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($customer->account_status === 'suspended') throw ValidationException::withMessages(['rice_product_id' => 'Suspended customers cannot place orders.']);
            $product = RiceProduct::query()->lockForUpdate()->findOrFail($attributes['rice_product_id']);
            $area = DeliveryArea::query()->lockForUpdate()->where('is_active', true)->find($attributes['delivery_area_id']);
            if (! $area) throw ValidationException::withMessages(['delivery_area_id' => 'Choose an active delivery area.']);
            $quantity = $attributes['quantity'];
            $pointsToUse = (int) ($attributes['points_to_use'] ?? 0);
            if (! $product->is_active || $product->available_stock < $quantity) throw ValidationException::withMessages(['quantity' => 'There is not enough stock available for this order.']);
            if ($attributes['payment_type'] === 'pautang') {
                $system = SystemSetting::current();
                if (! $system->pautang_enabled) throw ValidationException::withMessages(['payment_type' => 'Pautang is not currently available.']);
                if ($pointsToUse > 0) throw ValidationException::withMessages(['points_to_use' => 'Points can only be redeemed on cash orders.']);
                if ($quantity > $system->pautang_max_sacks) throw ValidationException::withMessages(['quantity' => "Pautang orders are limited to {$system->pautang_max_sacks} sack(s)."]);
                $activePautang = Order::query()->where('customer_id', $customer->id)->where('payment_type', 'pautang')->where('remaining_balance', '>', 0)->where('order_status', '!=', 'cancelled')->count();
                if ($activePautang >= $system->pautang_max_active) throw ValidationException::withMessages(['payment_type' => "You can only have {$system->pautang_max_active} active pautang order(s)."]);
            }
            $unitPrice = (float) $product->selling_price;
            $subtotal = round($unitPrice * $quantity, 2);
            $pointsDiscount = $this->pointsDiscount($customer, $pointsToUse, $subtotal);
            $fee = $this->deliveryPricing->feeFor($area);
            $finalAmount = max(0, round($subtotal - $pointsDiscount + $fee, 2));
            $previousStock = $product->available_stock;
            $order = Order::create([
                'customer_id' => $customer->id, 'rice_product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => number_format($unitPrice, 2, '.', ''), 'subtotal' => number_format($subtotal, 2, '.', ''),
                'points_used' => $pointsToUse, 'points_discount' => number_format($pointsDiscount, 2, '.', ''), 'delivery_fee' => number_format($fee, 2, '.', ''), 'final_amount' => number_format($finalAmount, 2, '.', ''), 'amount_paid' => '0.00', 'remaining_balance' => number_format($finalAmount, 2, '.', ''),
                'payment_type' => $attributes['payment_type'], 'delivery_address' => $attributes['delivery_address'], 'delivery_area' => $area->name, 'order_date' => today(), 'order_status' => 'pending', 'payment_status' => $finalAmount <= 0 ? 'paid' : 'unpaid', 'notes' => $attributes['notes'] ?? null,
            ]);
            $order->update(['order_number' => 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            $order->delivery()->create(['customer_id' => $customer->id, 'delivery_area_id' => $area->id, 'delivery_area_name' => $area->name, 'delivery_address' => $attributes['delivery_address'], 'delivery_fee' => number_format($fee, 2, '.', ''), 'status' => 'pending', 'notes' => $attributes['notes'] ?? null]);
            if ($pointsToUse > 0) {
                $ledger = $this->points->redeemOrder($order, $pointsToUse);
                $redeemedPoints = $ledger->wasRecentlyCreated ? $pointsToUse : null;
            }
            $product->update(['available_stock' => $previousStock - $quantity, 'reserved_stock' => $product->reserved_stock + $quantity]);
            $product->stockMovements()->create(['quantity' => $quantity, 'type' => 'order', 'previous_stock' => $previousStock, 'new_stock' => $previousStock - $quantity, 'order_id' => $order->id, 'notes' => "Order {$order->order_number} reserved.", 'user_id' => $customer->id]);
            return $order;
        });
        if ($redeemedPoints) {
            $this->notifications->pointsRedeemed($order, $redeemedPoints);
        }
        $this->notifications->newOrder($order);

        return to_route('orders.show', $order)->with('success', 'Order placed successfully.');
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);
        $order->load(['customer:id,name,email,mobile_number', 'riceProduct:id,name,brand,sack_size', 'delivery', 'pautangInstallments', 'gcashPayments.pautangInstallment', 'gcashPayments.reviewer']);
        return Inertia::render('modules/orders/Show', ['order' => $this->orderData($order), 'canManage' => request()->user()->is_admin]);
    }

    private function pointsDiscount(User $customer, int $points, float $subtotal): float
    {
        if ($points === 0) return 0.0;
        if ($points > $this->points->currentBalance($customer)) throw ValidationException::withMessages(['points_to_use' => 'You cannot use more points than your available balance.']);
        $settings = $this->points->settings();
        if (! $settings->is_enabled) throw ValidationException::withMessages(['points_to_use' => 'Points redemption is not currently available.']);
        if ($points < $settings->minimum_redemption) throw ValidationException::withMessages(['points_to_use' => "Use at least {$settings->minimum_redemption} points per order."]);
        if ($points > $settings->maximum_points_usable) throw ValidationException::withMessages(['points_to_use' => "You can use at most {$settings->maximum_points_usable} points per order."]);
        $rate = (float) $settings->peso_per_point;
        if ($rate <= 0) throw ValidationException::withMessages(['points_to_use' => 'Points redemption is not currently available.']);
        if ($points > (int) floor(($subtotal + 0.000001) / $rate)) throw ValidationException::withMessages(['points_to_use' => 'Points used cannot reduce the rice subtotal below zero.']);
        return round($points * $rate, 2);
    }

    /** @param Collection<int, PautangInstallment> $installments */
    private function pautangPaymentStatus($installments): string
    {
        if ($installments->isEmpty()) return 'unpaid';
        if ($installments->every(fn (PautangInstallment $item) => (float) $item->remaining_balance <= 0)) return 'paid';
        if ($installments->contains(fn (PautangInstallment $item) => $item->currentStatus() === 'overdue')) return 'overdue';
        return $installments->contains(fn (PautangInstallment $item) => (float) $item->amount_paid > 0) ? 'partially_paid' : 'unpaid';
    }

    /** @return array<string, mixed> */
    private function orderData(Order $order): array
    {
        return [
            'id' => $order->id, 'order_number' => $order->order_number,
            'customer' => ['id' => $order->customer->id, 'name' => $order->customer->name, 'email' => $order->customer->email, 'mobile_number' => $order->customer->mobile_number],
            'rice_product' => ['id' => $order->riceProduct->id, 'name' => $order->riceProduct->name, 'brand' => $order->riceProduct->brand, 'sack_size' => $order->riceProduct->sack_size],
            'quantity' => $order->quantity, 'unit_price' => $order->unit_price, 'subtotal' => $order->subtotal, 'points_used' => $order->points_used, 'points_discount' => $order->points_discount, 'delivery_fee' => $order->delivery_fee, 'final_amount' => $order->final_amount, 'amount_paid' => $order->amount_paid, 'remaining_balance' => $order->remaining_balance, 'payment_type' => $order->payment_type,
            'delivery_address' => $order->delivery_address, 'delivery_area' => $order->delivery_area, 'order_date' => $order->order_date->toDateString(),
            'payment_status' => $order->relationLoaded('pautangInstallments') && $order->payment_type === 'pautang' && $order->pautangInstallments->isNotEmpty() ? $this->pautangPaymentStatus($order->pautangInstallments) : $order->payment_status,
            'delivery' => $order->delivery ? ['id' => $order->delivery->id, 'delivery_area_id' => $order->delivery->delivery_area_id, 'delivery_area_name' => $order->delivery->delivery_area_name, 'delivery_address' => $order->delivery->delivery_address, 'delivery_fee' => $order->delivery->delivery_fee, 'delivery_date' => $order->delivery->delivery_date?->toDateString(), 'delivery_person' => $order->delivery->delivery_person, 'status' => $order->delivery->status, 'notes' => $order->delivery->notes, 'delivered_date' => $order->delivery->delivered_date?->toDateString()] : null,
            'pautang_installments' => $order->relationLoaded('pautangInstallments') ? $order->pautangInstallments->map(fn (PautangInstallment $item) => ['id' => $item->id, 'installment_number' => $item->installment_number, 'amount_due' => $item->amount_due, 'due_date' => $item->due_date->toDateString(), 'amount_paid' => $item->amount_paid, 'remaining_balance' => $item->remaining_balance, 'status' => $item->currentStatus(), 'paid_date' => $item->paid_date?->toDateString()])->values() : [],
            'gcash_payments' => $order->relationLoaded('gcashPayments') ? $order->gcashPayments->map(fn (GcashPayment $payment) => ['id' => $payment->id, 'amount' => $payment->amount, 'reference_number' => $payment->reference_number, 'payment_date' => $payment->payment_date->toDateString(), 'status' => $payment->status, 'remarks' => $payment->remarks, 'reviewed_at' => $payment->reviewed_at?->toISOString(), 'screenshot_url' => route('gcash-payments.screenshot', $payment), 'installment_number' => $payment->pautangInstallment?->installment_number, 'reviewer_name' => $payment->reviewer?->name])->values() : [],
            'created_at' => $order->created_at->toISOString(), 'updated_at' => $order->updated_at->toISOString(),
        ];
    }
}
