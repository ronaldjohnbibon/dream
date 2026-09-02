<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Http\Requests\RecordPautangPaymentRequest;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
                ->where('payment_type', 'pautang')
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
        $today = today()->toDateString();

        $orders = Order::query()
            ->with(['customer:id,name,email,mobile_number', 'pautangInstallments'])
            ->where('payment_type', 'pautang')
            ->where('order_status', '!=', 'cancelled')
            ->whereHas('pautangInstallments')
            ->when($view === 'paid', fn ($query) => $query->whereDoesntHave('pautangInstallments', fn ($installments) => $installments->where('remaining_balance', '>', 0)))
            ->when($view === 'overdue', fn ($query) => $query->whereHas('pautangInstallments', fn ($installments) => $installments
                ->where('remaining_balance', '>', 0)
                ->whereDate('due_date', '<', $today)))
            ->when($view === 'active', fn ($query) => $query
                ->whereHas('pautangInstallments', fn ($installments) => $installments->where('remaining_balance', '>', 0))
                ->whereDoesntHave('pautangInstallments', fn ($installments) => $installments
                    ->where('remaining_balance', '>', 0)
                    ->whereDate('due_date', '<', $today)))
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

    public function recordPayment(RecordPautangPaymentRequest $request, PautangInstallment $pautangInstallment): RedirectResponse
    {
        DB::transaction(function () use ($request, $pautangInstallment): void {
            $installment = PautangInstallment::query()->lockForUpdate()->findOrFail($pautangInstallment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($installment->order_id);

            if ($order->payment_type !== 'pautang' || $order->order_status === 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'This installment can no longer receive payments.']);
            }

            $amount = round((float) $request->validated('amount'), 2);
            $remaining = round((float) $installment->remaining_balance, 2);

            if ($amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot be greater than the remaining balance.']);
            }

            $amountPaid = round((float) $installment->amount_paid + $amount, 2);
            $newRemaining = round(max(0, (float) $installment->amount_due - $amountPaid), 2);
            $installment->fill([
                'amount_paid' => number_format($amountPaid, 2, '.', ''),
                'remaining_balance' => number_format($newRemaining, 2, '.', ''),
                'paid_date' => $newRemaining === 0.0 ? today() : null,
            ]);
            $installment->status = $installment->currentStatus();
            $installment->save();

            $installments = $order->pautangInstallments()->lockForUpdate()->get();
            foreach ($installments as $scheduledInstallment) {
                $currentStatus = $scheduledInstallment->currentStatus();
                if ($scheduledInstallment->status !== $currentStatus) {
                    $scheduledInstallment->update(['status' => $currentStatus]);
                }
            }

            $order->update(['payment_status' => $this->paymentStatus($installments)]);
        });

        return back()->with('success', 'Pautang payment recorded successfully.');
    }

    /** @param Collection<int, PautangInstallment> $installments */
    private function paymentStatus($installments): string
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
    private function pautangData(Order $order): array
    {
        $installments = $order->pautangInstallments;
        $unpaidInstallments = $installments->filter(fn (PautangInstallment $installment) => (float) $installment->remaining_balance > 0);
        $overdueInstallments = $unpaidInstallments->filter(fn (PautangInstallment $installment) => $installment->due_date->isBefore(today()));
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
            'days_overdue' => $oldestOverdueDate ? $oldestOverdueDate->diffInDays(today()) : 0,
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
