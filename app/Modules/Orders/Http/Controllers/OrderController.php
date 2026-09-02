<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Orders\Http\Requests\StoreOrderRequest;
use App\Modules\Orders\Http\Requests\UpdateOrderRequest;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Services\PointsService;
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
    public function __construct(private readonly PointsService $points)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'order_status' => ['nullable', 'in:all,'.implode(',', Order::STATUSES)],
            'payment_type' => ['nullable', 'in:all,'.implode(',', Order::PAYMENT_TYPES)],
            'payment_status' => ['nullable', 'in:all,'.implode(',', Order::PAYMENT_STATUSES)],
        ]);

        $search = $filters['search'] ?? '';
        $orderStatus = $filters['order_status'] ?? 'all';
        $paymentType = $filters['payment_type'] ?? 'all';
        $paymentStatus = $filters['payment_status'] ?? 'all';
        $user = $request->user();

        $orders = Order::query()
            ->with(['customer:id,name', 'riceProduct:id,name,brand,sack_size'])
            ->when(! $user->is_admin, fn ($query) => $query->where('customer_id', $user->id))
            ->when($search !== '', fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                ->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                ->orWhereHas('riceProduct', fn ($productQuery) => $productQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%"))))
            ->when($orderStatus !== 'all', fn ($query) => $query->where('order_status', $orderStatus))
            ->when($paymentType !== 'all', fn ($query) => $query->where('payment_type', $paymentType))
            ->when($paymentStatus !== 'all', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->latest('order_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order) => $this->orderData($order));

        return Inertia::render('modules/orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'order_status' => $orderStatus,
                'payment_type' => $paymentType,
                'payment_status' => $paymentStatus,
            ],
            'canManage' => $user->is_admin,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Order::class);

        $customer = $request->user();
        $products = RiceProduct::query()
            ->where('is_active', true)
            ->where('available_stock', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'brand', 'selling_price', 'available_stock'])
            ->map(fn (RiceProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'selling_price' => $product->selling_price,
                'available_stock' => $product->available_stock,
            ]);

        return Inertia::render('modules/orders/Create', [
            'products' => $products,
            'customer' => [
                'complete_address' => $customer->complete_address,
                'delivery_area' => $customer->delivery_area,
            ],
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $attributes = $request->validated();

        $order = DB::transaction(function () use ($attributes, $request): Order {
            $customer = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            if ($customer->account_status === 'suspended') {
                throw ValidationException::withMessages([
                    'rice_product_id' => 'Suspended customers cannot place orders.',
                ]);
            }

            $product = RiceProduct::query()->lockForUpdate()->findOrFail($attributes['rice_product_id']);
            $quantity = $attributes['quantity'];

            if (! $product->is_active) {
                throw ValidationException::withMessages([
                    'rice_product_id' => 'This rice product is no longer available.',
                ]);
            }

            if ($product->available_stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'There is not enough stock available for this order.',
                ]);
            }

            if ($attributes['payment_type'] === 'pautang') {
                if ($quantity !== 1) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Pautang orders are limited to one sack.',
                    ]);
                }

                $hasActivePautang = Order::query()
                    ->where('customer_id', $customer->id)
                    ->where('payment_type', 'pautang')
                    ->where('payment_status', '!=', 'paid')
                    ->where('order_status', '!=', 'cancelled')
                    ->exists();

                if ($hasActivePautang) {
                    throw ValidationException::withMessages([
                        'payment_type' => 'You still have an unpaid pautang order.',
                    ]);
                }
            }

            $unitPrice = (float) $product->selling_price;
            $subtotal = number_format($unitPrice * $quantity, 2, '.', '');
            $previousStock = $product->available_stock;
            $newStock = $previousStock - $quantity;

            $order = Order::create([
                'customer_id' => $customer->id,
                'rice_product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'subtotal' => $subtotal,
                'points_used' => 0,
                'points_discount' => 0,
                'final_amount' => $subtotal,
                'amount_paid' => '0.00',
                'remaining_balance' => $subtotal,
                'payment_type' => $attributes['payment_type'],
                'delivery_address' => $attributes['delivery_address'],
                'delivery_area' => $attributes['delivery_area'],
                'order_date' => today(),
                'order_status' => 'pending',
                'payment_status' => 'unpaid',
                'notes' => $attributes['notes'] ?? null,
            ]);

            $order->update(['order_number' => 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            $product->update([
                'available_stock' => $newStock,
                'reserved_stock' => $product->reserved_stock + $quantity,
            ]);
            $product->stockMovements()->create([
                'quantity' => $quantity,
                'type' => 'order',
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'order_id' => $order->id,
                'notes' => "Order {$order->order_number} reserved.",
                'user_id' => $customer->id,
            ]);

            return $order;
        });

        return to_route('orders.show', $order)->with('success', 'Order placed successfully.');
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['customer:id,name,email,mobile_number', 'riceProduct:id,name,brand,sack_size', 'pautangInstallments', 'gcashPayments.pautangInstallment', 'gcashPayments.reviewer']);

        return Inertia::render('modules/orders/Show', [
            'order' => $this->orderData($order),
            'canManage' => request()->user()->is_admin,
            'allowedStatuses' => request()->user()->is_admin ? $this->allowedStatuses($order) : [],
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $attributes = $request->validated();

        DB::transaction(function () use ($attributes, $order, $request): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $nextStatuses = $this->allowedStatuses($lockedOrder);

            if (! in_array($attributes['order_status'], $nextStatuses, true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'This order status change is not allowed.',
                ]);
            }

            $isCancelled = $attributes['order_status'] === 'cancelled' && $lockedOrder->order_status !== 'cancelled';
            $isDelivered = $attributes['order_status'] === 'delivered' && $lockedOrder->order_status !== 'delivered';
            $isPautangApproval = $lockedOrder->payment_type === 'pautang'
                && $lockedOrder->order_status === 'pending'
                && $attributes['order_status'] === 'confirmed';

            if ($isCancelled) {
                $hasPayments = $lockedOrder->gcashPayments()->exists()
                    || ($lockedOrder->payment_type === 'pautang'
                        && $lockedOrder->pautangInstallments()->where('amount_paid', '>', 0)->exists());

                if ($hasPayments) {
                    throw ValidationException::withMessages([
                        'order_status' => 'An order with GCash payment submissions cannot be cancelled.',
                    ]);
                }
            }

            if ($isCancelled || $isDelivered) {
                $product = RiceProduct::query()->lockForUpdate()->findOrFail($lockedOrder->rice_product_id);

                if ($isCancelled) {
                    $previousStock = $product->available_stock;
                    $newStock = $previousStock + $lockedOrder->quantity;

                    $product->update([
                        'available_stock' => $newStock,
                        'reserved_stock' => max(0, $product->reserved_stock - $lockedOrder->quantity),
                    ]);
                    $product->stockMovements()->create([
                        'quantity' => $lockedOrder->quantity,
                        'type' => 'cancellation',
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'order_id' => $lockedOrder->id,
                        'notes' => "Order {$lockedOrder->order_number} cancelled.",
                        'user_id' => $request->user()->id,
                    ]);
                }

                if ($isDelivered) {
                    $product->update([
                        'reserved_stock' => max(0, $product->reserved_stock - $lockedOrder->quantity),
                    ]);
                }
            }

            if ($isPautangApproval) {
                $this->createPautangInstallments($lockedOrder);
            }

            if ($isCancelled && $lockedOrder->payment_type === 'pautang') {
                $lockedOrder->pautangInstallments()->delete();
            }

            $paymentStatus = $lockedOrder->payment_status;
            if ($lockedOrder->payment_type === 'pautang') {
                $paymentStatus = $this->pautangPaymentStatus($lockedOrder->pautangInstallments()->get());
            }

            $lockedOrder->update([
                'order_status' => $attributes['order_status'],
                'payment_status' => $paymentStatus,
                'delivery_date' => $attributes['delivery_date'] ?? null,
            ]);

            if ($lockedOrder->order_status === 'completed') {
                $this->points->awardCompletedOrder($lockedOrder);
            }
        });

        return to_route('orders.show', $order)->with('success', 'Order updated successfully.');
    }

    /** @return list<string> */
    private function allowedStatuses(Order $order): array
    {
        return match ($order->order_status) {
            'pending' => ['pending', 'confirmed', 'cancelled'],
            'confirmed' => ['confirmed', 'preparing', 'cancelled'],
            'preparing' => ['preparing', 'out_for_delivery', 'cancelled'],
            'out_for_delivery' => ['out_for_delivery', 'delivered', 'cancelled'],
            'delivered' => ['delivered', 'completed'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
        };
    }

    private function createPautangInstallments(Order $order): void
    {
        $firstAmount = round((float) $order->final_amount / 2, 2);
        $secondAmount = round((float) $order->final_amount - $firstAmount, 2);

        $order->pautangInstallments()->createMany([
            [
                'installment_number' => 1,
                'amount_due' => number_format($firstAmount, 2, '.', ''),
                'due_date' => today()->addDays(15),
                'amount_paid' => '0.00',
                'remaining_balance' => number_format($firstAmount, 2, '.', ''),
                'status' => 'pending',
            ],
            [
                'installment_number' => 2,
                'amount_due' => number_format($secondAmount, 2, '.', ''),
                'due_date' => today()->addDays(30),
                'amount_paid' => '0.00',
                'remaining_balance' => number_format($secondAmount, 2, '.', ''),
                'status' => 'pending',
            ],
        ]);
    }

    /** @param Collection<int, PautangInstallment> $installments */
    private function pautangPaymentStatus($installments): string
    {
        if ($installments->isEmpty()) {
            return 'unpaid';
        }

        if ($installments->every(fn (PautangInstallment $installment) => (float) $installment->remaining_balance <= 0)) {
            return 'paid';
        }

        if ($installments->contains(fn (PautangInstallment $installment) => $installment->currentStatus() === 'overdue')) {
            return 'overdue';
        }

        return $installments->contains(fn (PautangInstallment $installment) => (float) $installment->amount_paid > 0)
            ? 'partially_paid'
            : 'unpaid';
    }

    /** @return array<string, mixed> */
    private function orderData(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer' => [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'email' => $order->customer->email,
                'mobile_number' => $order->customer->mobile_number,
            ],
            'rice_product' => [
                'id' => $order->riceProduct->id,
                'name' => $order->riceProduct->name,
                'brand' => $order->riceProduct->brand,
                'sack_size' => $order->riceProduct->sack_size,
            ],
            'quantity' => $order->quantity,
            'unit_price' => $order->unit_price,
            'subtotal' => $order->subtotal,
            'points_used' => $order->points_used,
            'points_discount' => $order->points_discount,
            'final_amount' => $order->final_amount,
            'amount_paid' => $order->amount_paid,
            'remaining_balance' => $order->remaining_balance,
            'payment_type' => $order->payment_type,
            'delivery_address' => $order->delivery_address,
            'delivery_area' => $order->delivery_area,
            'order_date' => $order->order_date->toDateString(),
            'delivery_date' => $order->delivery_date?->toDateString(),
            'order_status' => $order->order_status,
            'payment_status' => $order->relationLoaded('pautangInstallments') && $order->payment_type === 'pautang' && $order->pautangInstallments->isNotEmpty()
                ? $this->pautangPaymentStatus($order->pautangInstallments)
                : $order->payment_status,
            'notes' => $order->notes,
            'pautang_installments' => $order->relationLoaded('pautangInstallments')
                ? $order->pautangInstallments->map(fn (PautangInstallment $installment) => [
                    'id' => $installment->id,
                    'installment_number' => $installment->installment_number,
                    'amount_due' => $installment->amount_due,
                    'due_date' => $installment->due_date->toDateString(),
                    'amount_paid' => $installment->amount_paid,
                    'remaining_balance' => $installment->remaining_balance,
                    'status' => $installment->currentStatus(),
                    'paid_date' => $installment->paid_date?->toDateString(),
                ])->values()
                : [],
            'gcash_payments' => $order->relationLoaded('gcashPayments')
                ? $order->gcashPayments->map(fn (GcashPayment $payment) => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'reference_number' => $payment->reference_number,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'status' => $payment->status,
                    'remarks' => $payment->remarks,
                    'reviewed_at' => $payment->reviewed_at?->toISOString(),
                    'screenshot_url' => route('gcash-payments.screenshot', $payment),
                    'installment_number' => $payment->pautangInstallment?->installment_number,
                    'reviewer_name' => $payment->reviewer?->name,
                ])->values()
                : [],
            'created_at' => $order->created_at->toISOString(),
            'updated_at' => $order->updated_at->toISOString(),
        ];
    }
}
