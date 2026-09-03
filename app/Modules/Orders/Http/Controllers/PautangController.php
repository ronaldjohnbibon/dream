<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PautangController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->is_admin) {
            $pautang = Order::query()
                ->with(['riceProduct:id,name,brand,sack_size', 'pautangInstallments'])
                ->where('customer_id', $user->id)
                ->where('order_status', '!=', 'cancelled')
                ->whereHas('pautangInstallments', fn ($query) => $query->where('remaining_balance', '>', 0))
                ->latest('id')
                ->first();

            return Inertia::render('modules/pautang/Index', [
                'canManage' => false,
                'view' => 'active',
                'pautang' => $pautang ? $this->pautangData($pautang) : null,
                'pautangOrders' => null,
            ]);
        }

        $filters = $request->validate([
            'view' => ['nullable', 'in:active,paid,overdue'],
        ]);
        $view = $filters['view'] ?? 'active';
        $overdueCutoff = today()->subDays(SystemSetting::current()->pautang_grace_period_days)->toDateString();

        $orders = Order::query()
            ->with(['customer:id,name,email,mobile_number', 'pautangInstallments'])
            ->where('order_status', '!=', 'cancelled')
            ->whereHas('pautangInstallments')
            ->when($view === 'paid', fn ($query) => $query->whereDoesntHave('pautangInstallments', fn ($installments) => $installments->where('remaining_balance', '>', 0)))
            ->when($view === 'overdue', fn ($query) => $query->whereHas('pautangInstallments', fn ($installments) => $installments
                ->where('remaining_balance', '>', 0)
                ->whereDate('due_date', '<', $overdueCutoff)))
            ->when($view === 'active', fn ($query) => $query
                ->whereHas('pautangInstallments', fn ($installments) => $installments->where('remaining_balance', '>', 0))
                ->whereDoesntHave('pautangInstallments', fn ($installments) => $installments
                    ->where('remaining_balance', '>', 0)
                    ->whereDate('due_date', '<', $overdueCutoff)))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order) => $this->pautangData($order));

        return Inertia::render('modules/pautang/Index', [
            'canManage' => true,
            'view' => $view,
            'pautang' => null,
            'pautangOrders' => $orders,
        ]);
    }

    /** @return array<string, mixed> */
    private function pautangData(Order $order): array
    {
        $installments = $order->pautangInstallments;
        $unpaidInstallments = $installments->filter(fn (PautangInstallment $installment) => (float) $installment->remaining_balance > 0);
        $gracePeriod = SystemSetting::current()->pautang_grace_period_days;
        $overdueInstallments = $unpaidInstallments->filter(fn (PautangInstallment $installment) => $installment->due_date->copy()->addDays($gracePeriod)->isBefore(today()));
        $nextDueDate = $unpaidInstallments->sortBy('due_date')->first()?->due_date;
        $oldestOverdueDate = $overdueInstallments->sortBy('due_date')->first()?->due_date;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer' => $order->relationLoaded('customer') ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'email' => $order->customer->email,
                'mobile_number' => $order->customer->mobile_number,
            ] : null,
            'order_amount' => $order->final_amount,
            'amount_paid' => number_format((float) $installments->sum('amount_paid'), 2, '.', ''),
            'remaining_balance' => number_format((float) $installments->sum('remaining_balance'), 2, '.', ''),
            'next_due_date' => $nextDueDate?->toDateString(),
            'days_overdue' => $oldestOverdueDate ? $oldestOverdueDate->copy()->addDays($gracePeriod)->diffInDays(today()) : 0,
            'installments' => $installments->map(fn (PautangInstallment $installment) => [
                'id' => $installment->id,
                'installment_number' => $installment->installment_number,
                'amount_due' => $installment->amount_due,
                'due_date' => $installment->due_date->toDateString(),
                'amount_paid' => $installment->amount_paid,
                'remaining_balance' => $installment->remaining_balance,
                'status' => $installment->currentStatus(),
                'paid_date' => $installment->paid_date?->toDateString(),
            ])->values(),
        ];
    }
}
