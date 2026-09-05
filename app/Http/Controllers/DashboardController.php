<?php

namespace App\Http\Controllers;

use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Points\Services\PointsService;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly PointsService $points) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'dashboard' => $user->is_admin ? $this->dashboardData() : $this->customerDashboardData($user),
        ]);
    }

    /** @return array<string, mixed> */
    private function customerDashboardData(User $customer): array
    {
        $points        = $this->points->settings();
        $pointsBalance = $this->points->currentBalance($customer);
        $activeOrder   = $customer->orders()
            ->with(['riceProduct:id,name,brand,sack_size', 'delivery', 'pautangInstallments'])
            ->where('order_status', '!=', 'cancelled')
            ->whereHas('delivery', fn (Builder $query) => $query->whereNotIn('status', ['delivered', 'cancelled']))
            ->latest('order_date')
            ->latest('id')
            ->first();
        $activePautang = $customer->orders()
            ->with(['riceProduct:id,name,brand,sack_size', 'delivery', 'pautangInstallments'])
            ->where('remaining_balance', '>', 0)
            ->where('order_status', '!=', 'cancelled')
            ->latest('id')
            ->first();
        $recentOrders = $customer->orders()
            ->with(['riceProduct:id,name,brand,sack_size', 'delivery', 'pautangInstallments'])
            ->latest('order_date')
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'points' => [
                'balance'         => $pointsBalance,
                'peso_equivalent' => number_format($pointsBalance * (float) $points->peso_per_point, 2, '.', ''),
            ],
            'active_order'    => $activeOrder ? $this->customerOrderData($activeOrder) : null,
            'active_pautang'  => $activePautang ? $this->customerPautangData($activePautang) : null,
            'recent_orders'   => $recentOrders->map(fn (Order $order) => $this->customerOrderData($order))->values(),
            'recent_payments' => $customer->gcashPayments()
                ->with(['order:id,order_number', 'pautangInstallment:id,installment_number'])
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (GcashPayment $payment) => [
                    'id'                 => $payment->id,
                    'amount'             => $payment->amount,
                    'payment_date'       => $payment->payment_date->toDateString(),
                    'status'             => $payment->status,
                    'order'              => ['id' => $payment->order->id, 'order_number' => $payment->order->order_number],
                    'installment_number' => $payment->pautangInstallment?->installment_number,
                ])
                ->values(),
            'recent_points_transactions' => $customer->pointsLedgers()
                ->with('order:id,order_number')
                ->latest('transaction_date')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (PointsLedger $entry) => [
                    'id'               => $entry->id,
                    'type'             => $entry->type,
                    'points'           => $entry->points,
                    'description'      => $entry->description,
                    'transaction_date' => $entry->transaction_date->toDateString(),
                    'order'            => $entry->order ? ['id' => $entry->order->id, 'order_number' => $entry->order->order_number] : null,
                ])
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function customerOrderData(Order $order): array
    {
        return [
            'id'              => $order->id,
            'order_number'    => $order->order_number,
            'rice_product'    => $order->riceProduct ? "{$order->riceProduct->name} ({$order->riceProduct->brand})" : 'Rice product unavailable',
            'quantity'        => $order->quantity,
            'final_amount'    => $order->final_amount,
            'order_date'      => $order->order_date->toDateString(),
            'payment_status'  => $this->customerPaymentStatus($order),
            'delivery_status' => $order->delivery?->status,
        ];
    }

    /** @return array<string, mixed> */
    private function customerPautangData(Order $order): array
    {
        $unpaidInstallments = $order->pautangInstallments
            ->filter(fn (PautangInstallment $installment) => (float) $installment->remaining_balance > 0)
            ->sortBy('due_date')
            ->values();
        $nextInstallment  = $unpaidInstallments->first();
        $canSubmitPayment = $nextInstallment
            && ! in_array($order->delivery?->status, ['pending', 'cancelled'], true);

        return [
            ...$this->customerOrderData($order),
            'amount_paid'            => $order->amount_paid,
            'remaining_balance'      => $order->remaining_balance,
            'next_due_date'          => $nextInstallment?->due_date?->toDateString(),
            'payable_installment_id' => $nextInstallment?->id,
            'can_submit_payment'     => (bool) $canSubmitPayment,
            'payment_ready_message'  => $nextInstallment ? null : 'Your payment schedule will be available once this order is scheduled for delivery.',
        ];
    }

    private function customerPaymentStatus(Order $order): string
    {
        if (! $order->relationLoaded('pautangInstallments') || $order->pautangInstallments->isEmpty()) {
            return $order->payment_status;
        }

        if ($order->pautangInstallments->every(fn (PautangInstallment $installment) => (float) $installment->remaining_balance <= 0)) {
            return 'paid';
        }

        if ($order->pautangInstallments->contains(fn (PautangInstallment $installment) => $installment->currentStatus() === 'overdue')) {
            return 'overdue';
        }

        return $order->pautangInstallments->contains(fn (PautangInstallment $installment) => (float) $installment->amount_paid > 0)
            ? 'partially_paid'
            : 'unpaid';
    }

    /** @return array<string, mixed> */
    private function dashboardData(): array
    {
        $today         = today();
        $system        = SystemSetting::current();
        $activeOrders  = Order::query()->where('order_status', '!=', 'cancelled');
        $todayOrders   = (clone $activeOrders)->whereDate('order_date', $today);
        $activePautang = (clone $activeOrders)->where('remaining_balance', '>', 0);

        return [
            'metrics' => [
                $this->metric('Today\'s Orders', (clone $todayOrders)->count(), 'number', 'Orders placed today'),
                $this->metric('Today\'s Sales', (float) (clone $todayOrders)->sum('final_amount'), 'currency', 'Booked order value today'),
                $this->metric('Today\'s Collections', (float) GcashPayment::query()->where('status', 'approved')->whereDate('payment_date', $today)->sum('amount'), 'currency', 'Approved GCash payments today'),
                $this->metric('Total Customers', User::query()->where('is_admin', false)->count(), 'number', 'Customer accounts'),
                $this->metric('Active Pautang', (clone $activePautang)->count(), 'number', 'Unpaid pautang orders'),
                $this->metric('Outstanding Balance', (float) (clone $activeOrders)->where('remaining_balance', '>', 0)->sum('remaining_balance'), 'currency', 'All unpaid order balances'),
                $this->metric('Overdue Balance', (float) PautangInstallment::query()
                    ->where('remaining_balance', '>', 0)
                    ->whereDate('due_date', '<', $today->copy()->subDays($system->pautang_grace_period_days))
                    ->whereHas('order', fn (Builder $query) => $query->where('order_status', '!=', 'cancelled'))
                    ->sum('remaining_balance'), 'currency', 'Past-due pautang installments'),
                $this->metric('Pending Payment Verifications', GcashPayment::query()->where('status', 'pending_verification')->count(), 'number', 'GCash submissions awaiting review'),
                $this->metric('Available Rice Stock', RiceProduct::query()->where('is_active', true)->sum('available_stock'), 'number', 'Sacks across active products'),
                $this->metric('Low Stock Products', RiceProduct::query()->where('is_active', true)->where('available_stock', '<=', $system->low_stock_threshold)->count(), 'number', 'Active products at or below the low-stock threshold'),
                $this->metric('Points Issued', (int) PointsLedger::query()->where('points', '>', 0)->sum('points'), 'number', 'All-time positive points'),
                $this->metric('Points Redeemed', abs((int) PointsLedger::query()->where('type', 'redemption')->sum('points')), 'number', 'All-time redeemed points'),
            ],
            'charts' => [
                'daily_sales'          => $this->salesPeriod($today->copy()->subDays(6), $today, 'day'),
                'monthly_sales'        => $this->salesPeriod($today->copy()->startOfMonth()->subMonths(5), $today, 'month'),
                'collections'          => $this->collections($today->copy()->subDays(6), $today),
                'outstanding_balances' => $this->outstandingBalances(),
                'best_selling_rice'    => $this->bestSellingRice(),
                'payment_timing'       => $this->paymentTiming(),
            ],
        ];
    }

    /** @return array{label: string, value: float|int, format: string, description: string} */
    private function metric(string $label, float|int $value, string $format, string $description): array
    {
        return ['label' => $label, 'value' => $value, 'format' => $format, 'description' => $description];
    }

    /** @return list<array{label: string, value: float, percentage: float}> */
    private function salesPeriod(Carbon $start, Carbon $end, string $period): array
    {
        $sales = Order::query()
            ->where('order_status', '!=', 'cancelled')
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->get(['order_date', 'final_amount']);
        $points = [];
        if ($period === 'day') {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $points[] = ['key' => $date->toDateString(), 'label' => $date->format('M j')];
            }
        } else {
            for ($month = $start->copy()->startOfMonth(); $month->lte($end); $month->addMonth()) {
                $points[] = ['key' => $month->format('Y-m'), 'label' => $month->format('M Y')];
            }
        }

        $values = [];
        foreach ($sales as $order) {
            $key          = $period === 'day' ? $order->order_date->toDateString() : $order->order_date->format('Y-m');
            $values[$key] = ($values[$key] ?? 0.0) + (float) $order->final_amount;
        }

        return $this->withPercentages(array_map(fn (array $point) => [
            'label' => $point['label'],
            'value' => round($values[$point['key']] ?? 0, 2),
        ], $points));
    }

    /** @return list<array{label: string, value: float, percentage: float}> */
    private function collections(Carbon $start, Carbon $end): array
    {
        $payments = GcashPayment::query()->where('status', 'approved')
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->get(['payment_date', 'amount']);
        $values = [];
        foreach ($payments as $payment) {
            $key          = $payment->payment_date->toDateString();
            $values[$key] = ($values[$key] ?? 0.0) + (float) $payment->amount;
        }

        $points = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key      = $date->toDateString();
            $points[] = ['label' => $date->format('M j'), 'value' => round($values[$key] ?? 0, 2)];
        }

        return $this->withPercentages($points);
    }

    /** @return list<array{label: string, value: float, percentage: float}> */
    private function outstandingBalances(): array
    {
        $balance = (float) Order::query()->where('order_status', '!=', 'cancelled')
            ->where('remaining_balance', '>', 0)
            ->sum('remaining_balance');

        return $this->withPercentages([
            ['label' => 'Pautang', 'value' => round($balance, 2)],
        ]);
    }

    /** @return list<array{label: string, value: int, percentage: float}> */
    private function bestSellingRice(): array
    {
        $products = Order::query()->where('order_status', '!=', 'cancelled')
            ->selectRaw('rice_product_id, SUM(quantity) as total_quantity')->groupBy('rice_product_id')
            ->orderByDesc('total_quantity')->limit(5)->with('riceProduct:id,name,brand')->get();

        return $this->withPercentages($products->map(fn (Order $order) => [
            'label' => $order->riceProduct ? "{$order->riceProduct->name} ({$order->riceProduct->brand})" : 'Deleted rice product',
            'value' => (int) $order->total_quantity,
        ])->all());
    }

    /** @return list<array{label: string, value: int, percentage: float}> */
    private function paymentTiming(): array
    {
        $gracePeriodDays = SystemSetting::current()->pautang_grace_period_days;
        $installments    = PautangInstallment::query()->where('remaining_balance', '<=', 0)
            ->whereHas('order', fn (Builder $query) => $query->where('order_status', '!=', 'cancelled'))
            ->with(['gcashPayments' => fn ($query) => $query->where('status', 'approved')->whereNotNull('reviewed_at')->orderByDesc('reviewed_at')->orderByDesc('id')])
            ->get(['id', 'due_date']);
        $onTime = 0;
        $late   = 0;
        foreach ($installments as $installment) {
            $finalPayment = $installment->gcashPayments->first();
            if (! $finalPayment || ! $finalPayment->reviewed_at) {
                continue;
            }
            if ($finalPayment->reviewed_at->copy()->startOfDay()->isAfter($installment->due_date->copy()->addDays($gracePeriodDays))) {
                $late++;
            } else {
                $onTime++;
            }
        }

        return $this->withPercentages([
            ['label' => 'On time', 'value' => $onTime],
            ['label' => 'Late', 'value' => $late],
        ]);
    }

    /** @param list<array{label: string, value: float|int}> $points
     * @return list<array{label: string, value: float|int, percentage: float}>
     */
    private function withPercentages(array $points): array
    {
        $maximum = max([0, ...array_map(fn (array $point) => (float) $point['value'], $points)]);

        return array_map(fn (array $point) => [...$point, 'percentage' => $this->percentage((float) $point['value'], $maximum)], $points);
    }

    private function percentage(float $value, float $maximum): float
    {
        return $maximum > 0 ? round(($value / $maximum) * 100, 2) : 0.0;
    }
}
