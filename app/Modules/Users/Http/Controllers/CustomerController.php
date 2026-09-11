<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logs\Services\ActivityLogger;
use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Points\Services\PointsService;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Users\Http\Requests\StoreCustomerRequest;
use App\Modules\Users\Http\Requests\UpdateCustomerRequest;
use App\Modules\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly PointsService $points,
        private readonly ActivityLogger $activityLogs,
        private readonly SystemLogger $systemLogs,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'search'    => ['nullable', 'string', 'max:100'],
            'status'    => ['nullable', 'in:all,good_standing,overdue,suspended'],
            'sort'      => ['nullable', 'in:name,email,mobile_number,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search    = $filters['search']    ?? '';
        $status    = $filters['status']    ?? 'all';
        $sort      = $filters['sort']      ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $customers = User::query()
            ->where('is_admin', false)
            ->when($search !== '', fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('mobile_number', 'like', "%{$search}%")))
            ->when($status !== 'all', fn ($query) => $query->where('account_status', $status))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $customer) => $this->customerData($customer));

        return Inertia::render('modules/customers/Index', [
            'customers' => $customers,
            'filters'   => compact('search', 'status', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('modules/customers/Create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = User::create([
            ...$request->validated(),
            'is_admin'       => false,
            'account_status' => 'good_standing',
        ]);

        return to_route('customers.show', $customer)->with('success', 'Customer created successfully.');
    }

    public function show(User $customer): Response
    {
        $this->authorize('view', $customer);
        $this->ensureCustomer($customer);
        $gracePeriodDays = SystemSetting::current()->pautang_grace_period_days;

        return Inertia::render('modules/customers/Show', [
            'customer' => $this->customerData($customer),
            'summary'  => $this->summaryData($customer, $gracePeriodDays),
            'orders'   => $customer->orders()
                ->with(['riceProduct:id,name,brand,sack_size', 'pautangInstallments'])
                ->latest('order_date')
                ->latest('id')
                ->paginate(10, ['*'], 'orders_page')
                ->withQueryString()
                ->through(fn (Order $order) => $this->orderHistoryData($order, $gracePeriodDays)),
            'payments' => $customer->gcashPayments()
                ->with(['order:id,order_number', 'pautangInstallment:id,installment_number'])
                ->latest('payment_date')
                ->latest('id')
                ->paginate(10, ['*'], 'payments_page')
                ->withQueryString()
                ->through(fn (GcashPayment $payment) => $this->paymentHistoryData($payment)),
            'pointsHistory' => $customer->pointsLedgers()
                ->with(['order:id,order_number', 'pautangInstallment:id,installment_number'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(10, ['*'], 'points_page')
                ->withQueryString()
                ->through(fn (PointsLedger $entry) => $this->pointsHistoryData($entry)),
        ]);
    }

    public function edit(User $customer): Response
    {
        $this->authorize('update', $customer);
        $this->ensureCustomer($customer);

        return Inertia::render('modules/customers/Edit', [
            'customer' => $this->customerData($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, User $customer): RedirectResponse
    {
        $this->ensureCustomer($customer);
        $attributes      = $request->validated();
        $passwordChanged = $attributes['password'] !== null;

        if (! $passwordChanged) {
            unset($attributes['password']);
        }

        $customer->update($attributes);

        if ($passwordChanged) {
            $this->systemLogs->record(
                type: 'security',
                action: 'password_changed_by_admin',
                description: 'Customer password changed by an administrator.',
                module: 'users',
                recordId: (int) $customer->id,
                status: 'completed',
                metadata: ['affected_user_id' => $customer->id],
            );
        }

        return to_route('customers.show', $customer)->with('success', 'Customer updated successfully.');
    }

    public function suspend(Request $request, User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->ensureCustomer($customer);

        $previousStatus  = null;
        $updatedCustomer = DB::transaction(function () use ($request, $customer, &$previousStatus): ?User {
            $lockedCustomer = User::query()->lockForUpdate()->findOrFail($customer->id);
            if ($lockedCustomer->account_status === 'suspended') {
                return null;
            }

            $previousStatus = $lockedCustomer->account_status;
            $lockedCustomer->update(['account_status' => 'suspended']);
            $this->activityLogs->record($request->user(), 'customers', 'suspended', $lockedCustomer, "Customer {$lockedCustomer->name} suspended.");

            return $lockedCustomer;
        });

        if ($updatedCustomer !== null) {
            $this->systemLogs->record(
                type: 'security',
                action: 'account_disabled',
                description: 'Customer account suspended.',
                module: 'users',
                recordId: (int) $updatedCustomer->id,
                status: 'suspended',
                metadata: [
                    'affected_user_id' => $updatedCustomer->id,
                    'previous_status'  => $previousStatus,
                    'new_status'       => 'suspended',
                ],
            );
        }

        return to_route('customers.show', $customer)->with('success', 'Customer suspended successfully.');
    }

    public function reactivate(Request $request, User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->ensureCustomer($customer);

        $previousStatus  = null;
        $updatedCustomer = DB::transaction(function () use ($request, $customer, &$previousStatus): ?User {
            $lockedCustomer = User::query()->lockForUpdate()->findOrFail($customer->id);
            if ($lockedCustomer->account_status !== 'suspended') {
                return null;
            }

            $previousStatus = $lockedCustomer->account_status;
            $lockedCustomer->update(['account_status' => 'good_standing']);
            $this->activityLogs->record($request->user(), 'customers', 'reactivated', $lockedCustomer, "Customer {$lockedCustomer->name} reactivated.");

            return $lockedCustomer;
        });

        if ($updatedCustomer !== null) {
            $this->systemLogs->record(
                type: 'security',
                action: 'account_enabled',
                description: 'Customer account reactivated.',
                module: 'users',
                recordId: (int) $updatedCustomer->id,
                status: 'good_standing',
                metadata: [
                    'affected_user_id' => $updatedCustomer->id,
                    'previous_status'  => $previousStatus,
                    'new_status'       => 'good_standing',
                ],
            );
        }

        return to_route('customers.show', $customer)->with('success', 'Customer reactivated successfully.');
    }

    private function ensureCustomer(User $customer): void
    {
        abort_if($customer->is_admin, 404);
    }

    /**
     * @return array{id: int, name: string, email: string|null, mobile_number: string|null, complete_address: string|null, delivery_area: string|null, account_status: string, created_at: string, updated_at: string}
     */
    private function customerData(User $customer): array
    {
        return [
            'id'               => $customer->id,
            'name'             => $customer->name,
            'email'            => $customer->email,
            'mobile_number'    => $customer->mobile_number,
            'complete_address' => $customer->complete_address,
            'delivery_area'    => $customer->delivery_area,
            'account_status'   => $customer->account_status,
            'created_at'       => $customer->created_at->toISOString(),
            'updated_at'       => $customer->updated_at->toISOString(),
        ];
    }

    /**
     * @return array{total_orders: int, completed_pautang: int, active_pautang: int, on_time_payments: int, late_payments: int, outstanding_balance: float, current_points: int, peso_equivalent: string}
     */
    private function summaryData(User $customer, int $gracePeriodDays): array
    {
        $pautangOrders = $customer->orders()
            ->where('order_status', '!=', 'cancelled');
        $activePautang = (clone $pautangOrders)
            ->where('remaining_balance', '>', 0);
        $completedInstallments = PautangInstallment::query()
            ->where('remaining_balance', '<=', 0)
            ->whereHas('order', fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->where('order_status', '!=', 'cancelled'))
            ->with(['gcashPayments' => fn ($query) => $query
                ->where('status', 'approved')
                ->select(['id', 'pautang_installment_id', 'payment_date'])])
            ->get();

        $onTimePayments = 0;
        $latePayments   = 0;
        foreach ($completedInstallments as $installment) {
            $finalPaymentDate = $installment->gcashPayments->max('payment_date');
            if ($finalPaymentDate === null) {
                continue;
            }

            if ($finalPaymentDate->isAfter($installment->due_date->copy()->addDays($gracePeriodDays))) {
                $latePayments++;
            } else {
                $onTimePayments++;
            }
        }

        $currentPoints  = $this->points->currentBalance($customer);
        $pointsSettings = $this->points->settings();

        return [
            'total_orders'        => $customer->orders()->count(),
            'completed_pautang'   => (clone $pautangOrders)->where('remaining_balance', '<=', 0)->count(),
            'active_pautang'      => (clone $activePautang)->count(),
            'on_time_payments'    => $onTimePayments,
            'late_payments'       => $latePayments,
            'outstanding_balance' => (float) (clone $activePautang)->sum('remaining_balance'),
            'current_points'      => $currentPoints,
            'peso_equivalent'     => number_format($currentPoints * (float) $pointsSettings->peso_per_point, 2, '.', ''),
        ];
    }

    /** @return array<string, mixed> */
    private function orderHistoryData(Order $order, int $gracePeriodDays): array
    {
        return [
            'id'                => $order->id,
            'order_number'      => $order->order_number,
            'order_date'        => $order->order_date->toDateString(),
            'product_name'      => $order->riceProduct ? "{$order->riceProduct->name} {$order->riceProduct->brand}" : 'Deleted product',
            'sack_size'         => $order->riceProduct?->sack_size,
            'quantity'          => $order->quantity,
            'payment_status'    => $this->orderPaymentStatus($order, $gracePeriodDays),
            'order_status'      => $order->order_status,
            'final_amount'      => $order->final_amount,
            'remaining_balance' => $order->remaining_balance,
        ];
    }

    /** @return array<string, mixed> */
    private function paymentHistoryData(GcashPayment $payment): array
    {
        return [
            'id'                 => $payment->id,
            'payment_date'       => $payment->payment_date->toDateString(),
            'amount'             => $payment->amount,
            'status'             => $payment->status,
            'order'              => ['id' => $payment->order->id, 'order_number' => $payment->order->order_number],
            'installment_number' => $payment->pautangInstallment?->installment_number,
        ];
    }

    /** @return array<string, mixed> */
    private function pointsHistoryData(PointsLedger $entry): array
    {
        return [
            'id'                 => $entry->id,
            'type'               => $entry->type,
            'points'             => $entry->points,
            'description'        => $entry->description,
            'transaction_date'   => $entry->transaction_date->toDateString(),
            'order'              => $entry->order ? ['id' => $entry->order->id, 'order_number' => $entry->order->order_number] : null,
            'installment_number' => $entry->pautangInstallment?->installment_number,
        ];
    }

    private function orderPaymentStatus(Order $order, int $gracePeriodDays): string
    {
        if ($order->pautangInstallments->isEmpty()) {
            return $order->payment_status;
        }

        if ($order->pautangInstallments->every(fn (PautangInstallment $installment) => (float) $installment->remaining_balance <= 0)) {
            return 'paid';
        }

        if ($order->pautangInstallments->contains(fn (PautangInstallment $installment) => (float) $installment->remaining_balance > 0
            && $installment->due_date->copy()->addDays($gracePeriodDays)->isBefore(today()))) {
            return 'overdue';
        }

        return $order->pautangInstallments->contains(fn (PautangInstallment $installment) => (float) $installment->amount_paid > 0)
            ? 'partially_paid'
            : 'unpaid';
    }
}
