<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Inventory\Models\StockMovement;
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

class ReportsController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $today     = today();
        $system    = SystemSetting::current();
        $validated = $request->validate([
            'date_from'      => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to'        => ['nullable', 'date', 'after_or_equal:date_from'],
            'customer_id'    => ['nullable', 'integer', 'exists:users,id'],
            'payment_status' => ['nullable', 'in:all,'.implode(',', Order::PAYMENT_STATUSES)],
            'order_status'   => ['nullable', 'in:all,'.implode(',', Order::STATUSES)],
        ]);

        $filters = [
            'date_from'      => $validated['date_from']      ?? $today->copy()->startOfMonth()->toDateString(),
            'date_to'        => $validated['date_to']        ?? $today->toDateString(),
            'customer_id'    => $validated['customer_id']    ?? null,
            'payment_status' => $validated['payment_status'] ?? 'all',
            'order_status'   => $validated['order_status']   ?? 'all',
        ];
        $asOf = Carbon::parse($filters['date_to'])->startOfDay();

        $matchingOrders = $this->filteredOrders($filters, true);
        $salesOrders    = (clone $matchingOrders)->where('order_status', '!=', 'cancelled');
        $openOrders     = $this->filteredOrders($filters, false)
            ->where('order_status', '!=', 'cancelled')
            ->where('remaining_balance', '>', 0);
        $openPautang         = clone $openOrders;
        $overdueInstallments = $this->openInstallments($filters)
            ->whereDate('due_date', '<', $asOf->copy()->subDays($system->pautang_grace_period_days)->toDateString());
        $approvedPayments = $this->approvedPayments($filters);

        $salesSummary = [
            'order_count'         => (clone $salesOrders)->count(),
            'amount'              => $this->money((clone $salesOrders)->sum('final_amount')),
            'average_order_value' => $this->money((clone $salesOrders)->avg('final_amount') ?? 0),
        ];
        $orderStatusBreakdown = (clone $matchingOrders)
            ->selectRaw('order_status, COUNT(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->map(fn ($count) => (int) $count)
            ->all();
        $pautangSummary = [
            'order_count'       => (clone $openPautang)->count(),
            'amount_paid'       => $this->money((clone $openPautang)->sum('amount_paid')),
            'remaining_balance' => $this->money((clone $openPautang)->sum('remaining_balance')),
        ];
        $collectionsSummary = [
            'payment_count' => (clone $approvedPayments)->count(),
            'amount'        => $this->money((clone $approvedPayments)->sum('amount')),
        ];
        $outstandingSummary = [
            'order_count' => (clone $openOrders)->count(),
            'amount'      => $this->money((clone $openOrders)->sum('remaining_balance')),
        ];
        $overdueSummary = [
            'customer_count' => (clone $overdueInstallments)
                ->join('orders', 'orders.id', '=', 'pautang_installments.order_id')
                ->distinct('orders.customer_id')
                ->count('orders.customer_id'),
            'amount' => $this->money((clone $overdueInstallments)->sum('pautang_installments.remaining_balance')),
        ];

        $profitTotals = (clone $salesOrders)
            ->join('rice_products', 'rice_products.id', '=', 'orders.rice_product_id')
            ->selectRaw('COALESCE(SUM(orders.final_amount), 0) as revenue, COALESCE(SUM(orders.quantity * rice_products.cost_price), 0) as cost')
            ->first();
        $profitSummary = [
            'revenue' => $this->money($profitTotals?->revenue ?? 0),
            'cost'    => $this->money($profitTotals?->cost ?? 0),
            'profit'  => $this->money((float) ($profitTotals?->revenue ?? 0) - (float) ($profitTotals?->cost ?? 0)),
        ];

        $pointsEarned       = $this->filteredPoints($filters)->where('points', '>', 0);
        $pointsRedeemed     = $this->filteredPoints($filters)->where('type', 'redemption');
        $inventoryMovements = StockMovement::query()
            ->with(['riceProduct:id,name,brand', 'user:id,name'])
            ->whereBetween('created_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59']);

        return Inertia::render('modules/reports/Index', [
            'filters'   => $filters,
            'customers' => User::query()->where('is_admin', false)->orderBy('name')->get(['id', 'name']),
            'summaries' => [
                'sales'       => $salesSummary,
                'orders'      => ['count' => (clone $matchingOrders)->count(), 'statuses' => $orderStatusBreakdown],
                'pautang'     => $pautangSummary,
                'collections' => $collectionsSummary,
                'outstanding' => $outstandingSummary,
                'overdue'     => $overdueSummary,
                'inventory'   => [
                    'available_stock' => (int) RiceProduct::query()->sum('available_stock'),
                    'reserved_stock'  => (int) RiceProduct::query()->sum('reserved_stock'),
                    'low_stock_count' => RiceProduct::query()->where('is_active', true)->where('available_stock', '<=', $system->low_stock_threshold)->count(),
                ],
                'movements' => [
                    'count'     => (clone $inventoryMovements)->count(),
                    'stock_in'  => (int) (clone $inventoryMovements)->whereColumn('new_stock', '>', 'previous_stock')->sum('quantity'),
                    'stock_out' => (int) (clone $inventoryMovements)->whereColumn('new_stock', '<', 'previous_stock')->sum('quantity'),
                ],
                'points' => [
                    'earned'   => (int) (clone $pointsEarned)->sum('points'),
                    'redeemed' => abs((int) (clone $pointsRedeemed)->sum('points')),
                ],
                'profit' => $profitSummary,
            ],
            'aging'  => $this->aging($filters, $asOf, $system->pautang_grace_period_days),
            'orders' => (clone $matchingOrders)
                ->with(['customer:id,name', 'riceProduct:id,name,brand'])
                ->latest('order_date')->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'orders_page')
                ->withQueryString()
                ->through(fn (Order $order) => $this->orderRow($order)),
            'pautang' => (clone $openPautang)
                ->with(['customer:id,name', 'pautangInstallments'])
                ->latest('order_date')->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'pautang_page')
                ->withQueryString()
                ->through(fn (Order $order) => $this->pautangRow($order, $asOf, $system->pautang_grace_period_days)),
            'outstanding' => (clone $openOrders)
                ->with('customer:id,name')
                ->latest('order_date')->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'outstanding_page')
                ->withQueryString()
                ->through(fn (Order $order) => $this->balanceRow($order)),
            'overdueCustomers' => $this->overdueCustomers($filters, $asOf, $system->pautang_grace_period_days),
            'payments'         => (clone $approvedPayments)
                ->with(['customer:id,name', 'order:id,order_number', 'pautangInstallment:id,installment_number,due_date'])
                ->latest('payment_date')->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'payments_page')
                ->withQueryString()
                ->through(fn (GcashPayment $payment) => $this->paymentRow($payment)),
            'inventory' => RiceProduct::query()
                ->orderBy('name')->orderBy('brand')
                ->paginate(self::PER_PAGE, ['*'], 'inventory_page')
                ->withQueryString()
                ->through(fn (RiceProduct $product) => [
                    'id'              => $product->id,
                    'name'            => $product->name,
                    'brand'           => $product->brand,
                    'available_stock' => $product->available_stock,
                    'reserved_stock'  => $product->reserved_stock,
                    'is_active'       => $product->is_active,
                ]),
            'movements' => $inventoryMovements
                ->latest('created_at')->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'movements_page')
                ->withQueryString()
                ->through(fn (StockMovement $movement) => [
                    'id'             => $movement->id,
                    'rice_product'   => $movement->riceProduct ? ['id' => $movement->riceProduct->id, 'name' => $movement->riceProduct->name, 'brand' => $movement->riceProduct->brand] : null,
                    'type'           => $movement->type,
                    'quantity'       => $movement->quantity,
                    'previous_stock' => $movement->previous_stock,
                    'new_stock'      => $movement->new_stock,
                    'created_at'     => $movement->created_at->toISOString(),
                ]),
            'pointsEarned' => $pointsEarned
                ->with(['customer:id,name', 'order:id,order_number'])
                ->latest('transaction_date')->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'earned_page')
                ->withQueryString()
                ->through(fn (PointsLedger $entry) => $this->pointRow($entry)),
            'pointsRedeemed' => $pointsRedeemed
                ->with(['customer:id,name', 'order:id,order_number'])
                ->latest('transaction_date')->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'redeemed_page')
                ->withQueryString()
                ->through(fn (PointsLedger $entry) => $this->pointRow($entry, true)),
            'profitByProduct' => (clone $salesOrders)
                ->join('rice_products', 'rice_products.id', '=', 'orders.rice_product_id')
                ->selectRaw('rice_products.id, rice_products.name, rice_products.brand, SUM(orders.quantity) as quantity, SUM(orders.final_amount) as revenue, SUM(orders.quantity * rice_products.cost_price) as cost')
                ->groupBy('rice_products.id', 'rice_products.name', 'rice_products.brand')
                ->orderByRaw('SUM(orders.final_amount) - SUM(orders.quantity * rice_products.cost_price) DESC')
                ->paginate(self::PER_PAGE, ['*'], 'profit_page')
                ->withQueryString()
                ->through(fn ($row) => [
                    'id'       => (int) $row->id,
                    'name'     => $row->name,
                    'brand'    => $row->brand,
                    'quantity' => (int) $row->quantity,
                    'revenue'  => $this->money($row->revenue),
                    'cost'     => $this->money($row->cost),
                    'profit'   => $this->money((float) $row->revenue - (float) $row->cost),
                ]),
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function filteredOrders(array $filters, bool $includeDateRange): Builder
    {
        return $this->applyOrderFilters(Order::query(), $filters, $includeDateRange);
    }

    /** @param array<string, mixed> $filters */
    private function applyOrderFilters(Builder $query, array $filters, bool $includeDateRange): Builder
    {
        return $query
            ->when($includeDateRange, fn (Builder $query) => $query->whereBetween('order_date', [$filters['date_from'], $filters['date_to']]))
            ->when($filters['customer_id'], fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['payment_status'] !== 'all', fn (Builder $query) => $query->where('payment_status', $filters['payment_status']))
            ->when($filters['order_status'] !== 'all', fn (Builder $query) => $query->where('order_status', $filters['order_status']));
    }

    /** @param array<string, mixed> $filters */
    private function openInstallments(array $filters): Builder
    {
        return PautangInstallment::query()
            ->where('pautang_installments.remaining_balance', '>', 0)
            ->whereHas('order', fn (Builder $query) => $this->applyOrderFilters($query, $filters, false)->where('order_status', '!=', 'cancelled'));
    }

    /** @param array<string, mixed> $filters */
    private function approvedPayments(array $filters): Builder
    {
        return GcashPayment::query()
            ->where('status', 'approved')
            ->whereBetween('payment_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['customer_id'], fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->whereHas('order', function (Builder $query) use ($filters): void {
                $this->applyOrderFilters($query, [...$filters, 'customer_id' => null], false);
            });
    }

    /** @param array<string, mixed> $filters */
    private function filteredPoints(array $filters): Builder
    {
        return PointsLedger::query()
            ->whereBetween('transaction_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['customer_id'], fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId));
    }

    /** @param array<string, mixed> $filters
     * @return array<string, array{label: string, amount: string}>
     */
    private function aging(array $filters, Carbon $asOf, int $gracePeriodDays): array
    {
        $buckets = [
            'current'           => ['label' => 'Current', 'amount' => 0.0],
            'one_to_seven'      => ['label' => '1-7 days overdue', 'amount' => 0.0],
            'eight_to_fifteen'  => ['label' => '8-15 days overdue', 'amount' => 0.0],
            'sixteen_to_thirty' => ['label' => '16-30 days overdue', 'amount' => 0.0],
            'thirty_one_plus'   => ['label' => '31+ days overdue', 'amount' => 0.0],
        ];

        $installments = $this->openInstallments($filters)->get(['due_date', 'remaining_balance']);
        foreach ($installments as $installment) {
            $daysOverdue = Carbon::parse($installment->due_date)->startOfDay()
                ->addDays($gracePeriodDays)
                ->diffInDays($asOf, false);
            $key = match (true) {
                $daysOverdue <= 0  => 'current',
                $daysOverdue <= 7  => 'one_to_seven',
                $daysOverdue <= 15 => 'eight_to_fifteen',
                $daysOverdue <= 30 => 'sixteen_to_thirty',
                default            => 'thirty_one_plus',
            };
            $buckets[$key]['amount'] += (float) $installment->remaining_balance;
        }

        $unscheduledPautang = $this->filteredOrders($filters, false)
            ->where('order_status', '!=', 'cancelled')
            ->where('remaining_balance', '>', 0)
            ->whereDoesntHave('pautangInstallments')
            ->sum('remaining_balance');
        $buckets['current']['amount'] += (float) $unscheduledPautang;

        return collect($buckets)->map(fn (array $bucket) => [
            'label'  => $bucket['label'],
            'amount' => $this->money($bucket['amount']),
        ])->all();
    }

    /** @param array<string, mixed> $filters */
    private function overdueCustomers(array $filters, Carbon $asOf, int $gracePeriodDays)
    {
        $query = PautangInstallment::query()
            ->join('orders', 'orders.id', '=', 'pautang_installments.order_id')
            ->join('users', 'users.id', '=', 'orders.customer_id')
            ->where('pautang_installments.remaining_balance', '>', 0)
            ->whereDate('pautang_installments.due_date', '<', $asOf->copy()->subDays($gracePeriodDays)->toDateString())
            ->where('orders.order_status', '!=', 'cancelled')
            ->when($filters['customer_id'], fn ($query, int $customerId) => $query->where('orders.customer_id', $customerId))
            ->when($filters['payment_status'] !== 'all', fn ($query) => $query->where('orders.payment_status', $filters['payment_status']))
            ->when($filters['order_status'] !== 'all', fn ($query) => $query->where('orders.order_status', $filters['order_status']))
            ->selectRaw('users.id, users.name, SUM(pautang_installments.remaining_balance) as remaining_balance, MIN(pautang_installments.due_date) as oldest_due_date')
            ->groupBy('users.id', 'users.name')
            ->orderBy('oldest_due_date');

        return $query->paginate(self::PER_PAGE, ['*'], 'overdue_page')
            ->withQueryString()
            ->through(function ($row) use ($asOf, $gracePeriodDays): array {
                $oldestDue = Carbon::parse($row->oldest_due_date)->startOfDay();

                return [
                    'id'                => (int) $row->id,
                    'name'              => $row->name,
                    'remaining_balance' => $this->money($row->remaining_balance),
                    'oldest_due_date'   => $oldestDue->toDateString(),
                    'days_overdue'      => $oldestDue->addDays($gracePeriodDays)->diffInDays($asOf),
                ];
            });
    }

    /** @return array<string, mixed> */
    private function orderRow(Order $order): array
    {
        return [
            'id'             => $order->id,
            'order_number'   => $order->order_number,
            'customer_name'  => $order->customer?->name ?? 'Deleted customer',
            'product_name'   => $order->riceProduct ? trim($order->riceProduct->name.' '.$order->riceProduct->brand) : 'Deleted product',
            'order_date'     => $order->order_date->toDateString(),
            'final_amount'   => $this->money($order->final_amount),
            'payment_status' => $order->payment_status,
            'order_status'   => $order->order_status,
        ];
    }

    /** @return array<string, mixed> */
    private function pautangRow(Order $order, Carbon $asOf, int $gracePeriodDays): array
    {
        $unpaid  = $order->pautangInstallments->filter(fn (PautangInstallment $installment) => (float) $installment->remaining_balance > 0);
        $nextDue = $unpaid->sortBy('due_date')->first()?->due_date;

        return [
            'id'                => $order->id,
            'order_number'      => $order->order_number,
            'customer_name'     => $order->customer?->name ?? 'Deleted customer',
            'amount_paid'       => $this->money($order->amount_paid),
            'remaining_balance' => $this->money($order->remaining_balance),
            'next_due_date'     => $nextDue?->toDateString(),
            'days_overdue'      => $nextDue && $nextDue->copy()->addDays($gracePeriodDays)->isBefore($asOf) ? $nextDue->copy()->addDays($gracePeriodDays)->diffInDays($asOf) : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function balanceRow(Order $order): array
    {
        return [
            'id'                => $order->id,
            'order_number'      => $order->order_number,
            'customer_name'     => $order->customer?->name ?? 'Deleted customer',
            'order_date'        => $order->order_date->toDateString(),
            'remaining_balance' => $this->money($order->remaining_balance),
        ];
    }

    /** @return array<string, mixed> */
    private function paymentRow(GcashPayment $payment): array
    {
        return [
            'id'                 => $payment->id,
            'customer_name'      => $payment->customer?->name      ?? 'Deleted customer',
            'order_number'       => $payment->order?->order_number ?? 'Deleted order',
            'installment_number' => $payment->pautangInstallment?->installment_number,
            'amount'             => $this->money($payment->amount),
            'payment_date'       => $payment->payment_date->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function pointRow(PointsLedger $entry, bool $absolute = false): array
    {
        return [
            'id'               => $entry->id,
            'customer_name'    => $entry->customer?->name ?? 'Deleted customer',
            'order_number'     => $entry->order?->order_number,
            'type'             => $entry->type,
            'points'           => $absolute ? abs($entry->points) : $entry->points,
            'description'      => $entry->description,
            'transaction_date' => $entry->transaction_date->toDateString(),
        ];
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
