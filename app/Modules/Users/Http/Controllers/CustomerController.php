<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Requests\StoreCustomerRequest;
use App\Modules\Users\Http\Requests\UpdateCustomerRequest;
use App\Modules\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,good_standing,overdue,suspended'],
            'sort' => ['nullable', 'in:name,email,mobile_number,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? 'all';
        $sort = $filters['sort'] ?? 'created_at';
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
            'filters' => compact('search', 'status', 'sort', 'direction'),
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
            'is_admin' => false,
            'account_status' => 'good_standing',
        ]);

        return to_route('customers.show', $customer)->with('success', 'Customer created successfully.');
    }

    public function show(User $customer): Response
    {
        $this->authorize('view', $customer);
        $this->ensureCustomer($customer);

        return Inertia::render('modules/customers/Show', [
            'customer' => $this->customerData($customer),
            'summary' => $this->summaryData(),
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
        $attributes = $request->validated();

        if ($attributes['password'] === null) {
            unset($attributes['password']);
        }

        $customer->update($attributes);

        return to_route('customers.show', $customer)->with('success', 'Customer updated successfully.');
    }

    public function suspend(User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->ensureCustomer($customer);

        $customer->update(['account_status' => 'suspended']);

        return to_route('customers.show', $customer)->with('success', 'Customer suspended successfully.');
    }

    public function reactivate(User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->ensureCustomer($customer);

        $customer->update(['account_status' => 'good_standing']);

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
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'mobile_number' => $customer->mobile_number,
            'complete_address' => $customer->complete_address,
            'delivery_area' => $customer->delivery_area,
            'account_status' => $customer->account_status,
            'created_at' => $customer->created_at->toISOString(),
            'updated_at' => $customer->updated_at->toISOString(),
        ];
    }

    /**
     * @return array{total_orders: int, completed_pautang: int, active_pautang: int, on_time_payments: int, late_payments: int, outstanding_balance: int, current_points: int}
     */
    private function summaryData(): array
    {
        return [
            'total_orders' => 0,
            'completed_pautang' => 0,
            'active_pautang' => 0,
            'on_time_payments' => 0,
            'late_payments' => 0,
            'outstanding_balance' => 0,
            'current_points' => 0,
        ];
    }
}
