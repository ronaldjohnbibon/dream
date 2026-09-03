<?php

namespace App\Http\Controllers;

use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'dashboard' => $request->user()->is_admin ? $this->dashboardData() : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function dashboardData(): array
    {
        $today = today();
        $system = SystemSetting::current();
        $activeOrders = Order::query()->where('order_status', '!=', 'cancelled');
        $todayOrders = (clone $activeOrders)->whereDate('order_date', $today);
        $activePautang = (clone $activeOrders)
            ->where('payment_type', 'pautang')
            ->where('remaining_balance', '>', 0);

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
                'daily_sales' => $this->salesPeriod($today->copy()->subDays(6), $today, 'day'),
                'monthly_sales' => $this->salesPeriod($today->copy()->startOfMonth()->subMonths(5), $today, 'month'),
                'cash_vs_pautang' => $this->cashVsPautang($today->copy()->subDays(6), $today),
                'collections' => $this->collections($today->copy()->subDays(6), $today),
                'outstanding_balances' => $this->outstandingBalances(),
                'best_selling_rice' => $this->bestSellingRice(),
                'payment_timing' => $this->paymentTiming(),
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
            $key = $period === 'day' ? $order->order_date->toDateString() : $order->order_date->format('Y-m');
            $values[$key] = ($values[$key] ?? 0.0) + (float) $order->final_amount;
        }

        return $this->withPercentages(array_map(fn (array $point) => [
            'label' => $point['label'],
            'value' => round($values[$point['key']] ?? 0, 2),
        ], $points));
    }

    /** @return list<array{label: string, cash: float, pautang: float, cash_percentage: float, pautang_percentage: float}> */
    private function cashVsPautang(Carbon $start, Carbon $end): array
    {
        $orders = Order::query()->where('order_status', '!=', 'cancelled')
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->get(['order_date', 'payment_type', 'final_amount']);
        $values = [];
        foreach ($orders as $order) {
            $key = $order->order_date->toDateString();
            $values[$key][$order->payment_type] = ($values[$key][$order->payment_type] ?? 0.0) + (float) $order->final_amount;
        }

        $points = [];
        $maximum = 0.0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $cash = round($values[$key]['cash'] ?? 0, 2);
            $pautang = round($values[$key]['pautang'] ?? 0, 2);
            $maximum = max($maximum, $cash + $pautang);
            $points[] = ['label' => $date->format('M j'), 'cash' => $cash, 'pautang' => $pautang];
        }

        return array_map(fn (array $point) => [
            ...$point,
            'cash_percentage' => $this->percentage($point['cash'], $maximum),
            'pautang_percentage' => $this->percentage($point['pautang'], $maximum),
        ], $points);
    }

    /** @return list<array{label: string, value: float, percentage: float}> */
    private function collections(Carbon $start, Carbon $end): array
    {
        $payments = GcashPayment::query()->where('status', 'approved')
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->get(['payment_date', 'amount']);
        $values = [];
        foreach ($payments as $payment) {
            $key = $payment->payment_date->toDateString();
            $values[$key] = ($values[$key] ?? 0.0) + (float) $payment->amount;
        }

        $points = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $points[] = ['label' => $date->format('M j'), 'value' => round($values[$key] ?? 0, 2)];
        }

        return $this->withPercentages($points);
    }

    /** @return list<array{label: string, value: float, percentage: float}> */
    private function outstandingBalances(): array
    {
        $values = Order::query()->where('order_status', '!=', 'cancelled')->where('remaining_balance', '>', 0)
            ->get(['payment_type', 'remaining_balance'])->groupBy('payment_type')
            ->map(fn ($orders) => (float) $orders->sum('remaining_balance'));

        return $this->withPercentages([
            ['label' => 'Cash', 'value' => round($values->get('cash', 0), 2)],
            ['label' => 'Pautang', 'value' => round($values->get('pautang', 0), 2)],
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
        $installments = PautangInstallment::query()->where('remaining_balance', '<=', 0)
            ->whereHas('order', fn (Builder $query) => $query->where('order_status', '!=', 'cancelled'))
            ->with(['gcashPayments' => fn (Builder $query) => $query->where('status', 'approved')->orderByDesc('payment_date')])
            ->get(['id', 'due_date']);
        $onTime = 0;
        $late = 0;
        foreach ($installments as $installment) {
            $finalPayment = $installment->gcashPayments->first();
            if (! $finalPayment) {
                continue;
            }
            if ($finalPayment->payment_date->isAfter($installment->due_date->copy()->addDays(SystemSetting::current()->pautang_grace_period_days))) {
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
     *  @return list<array{label: string, value: float|int, percentage: float}>
     */
    private function withPercentages(array $points): array
    {
        $maximum = max(0, ...array_map(fn (array $point) => (float) $point['value'], $points));

        return array_map(fn (array $point) => [...$point, 'percentage' => $this->percentage((float) $point['value'], $maximum)], $points);
    }

    private function percentage(float $value, float $maximum): float
    {
        return $maximum > 0 ? round(($value / $maximum) * 100, 2) : 0.0;
    }
}
