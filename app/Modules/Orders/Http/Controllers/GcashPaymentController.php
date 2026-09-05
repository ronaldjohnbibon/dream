<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logs\Services\ActivityLogger;
use App\Modules\Notifications\Services\CustomerNotificationService;
use App\Modules\Orders\Http\Requests\RejectGcashPaymentRequest;
use App\Modules\Orders\Http\Requests\ReviewGcashPaymentRequest;
use App\Modules\Orders\Http\Requests\StoreGcashPaymentRequest;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Points\Services\PointsService;
use App\Modules\Settings\Models\GcashSetting;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GcashPaymentController extends Controller
{
    public function __construct(
        private readonly PointsService $points,
        private readonly CustomerNotificationService $notifications,
        private readonly ActivityLogger $activityLogs,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $filters = $request->validate([
            'status' => ['nullable', 'in:all,'.implode(',', GcashPayment::STATUSES)],
        ]);
        $status = $filters['status'] ?? 'pending_verification';

        $payments = GcashPayment::query()
            ->with(['order:id,order_number', 'customer:id,name,email,mobile_number', 'pautangInstallment:id,installment_number', 'reviewer:id,name'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (GcashPayment $payment) => $this->paymentData($payment, false));

        return Inertia::render('modules/payments/Index', [
            'payments' => $payments,
            'status'   => $status,
        ]);
    }

    public function create(Request $request, Order $order): Response
    {
        $this->ensureCustomerOwnsOrder($request, $order);
        $attributes = $request->validate([
            'installment' => ['required', 'integer', 'min:1'],
        ]);
        $order->load('pautangInstallments');
        $installment = $this->requestedInstallment($attributes['installment'], $order);
        $this->ensureSubmittable($order, $installment);
        $submissionLimit = $this->submissionLimit($installment);
        if ($submissionLimit <= 0) {
            throw ValidationException::withMessages(['payment' => 'A payment for this installment is already awaiting verification.']);
        }

        $gcash = GcashSetting::query()->find(1);
        if (! $gcash?->isConfigured()) {
            throw ValidationException::withMessages(['payment' => 'GCash payments are not available until an administrator configures the account and QR code.']);
        }

        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('r2-public');

        return Inertia::render('modules/payments/Create', [
            'order'       => $this->paymentOrderData($order),
            'installment' => $this->installmentData($installment, $submissionLimit),
            'gcash'       => ['account_name' => $gcash->account_name, 'account_number' => $gcash->account_number, 'qr_code_url' => $publicDisk->url($gcash->qr_code_path)],
        ]);
    }

    public function store(StoreGcashPaymentRequest $request, Order $order): RedirectResponse
    {
        $attributes     = $request->validated();
        $screenshotPath = null;
        $created        = false;

        try {
            $payment = DB::transaction(function () use ($request, $order, $attributes, &$screenshotPath, &$created): GcashPayment {
                $lockedOrder     = Order::query()->lockForUpdate()->findOrFail($order->id);
                $this->ensureCustomerOwnsOrder($request, $lockedOrder);
                $existingPayment = GcashPayment::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('customer_id', $request->user()->id)
                    ->where('idempotency_key', $attributes['idempotency_key'])
                    ->first();
                if ($existingPayment) {
                    return $existingPayment;
                }

                $installment = $this->installmentFromAttributes($attributes, $lockedOrder);
                $this->ensureSubmittable($lockedOrder, $installment);

                if (! GcashSetting::query()->find(1)?->isConfigured()) {
                    throw ValidationException::withMessages(['payment' => 'GCash payments are not currently configured.']);
                }

                $amount = round((float) $attributes['amount'], 2);
                if ($amount > $this->submissionLimit($installment)) {
                    throw ValidationException::withMessages(['amount' => 'Payment cannot be greater than the installment balance available for verification.']);
                }

                $referenceNumber = strtoupper(trim($attributes['reference_number']));
                if (GcashPayment::query()->where('reference_number', $referenceNumber)->exists()) {
                    throw ValidationException::withMessages(['reference_number' => 'This GCash reference number has already been submitted.']);
                }

                $screenshotPath = $request->file('screenshot')->store('gcash-payment-screenshots', 'r2-private');

                $payment = GcashPayment::create([
                    'idempotency_key'        => $attributes['idempotency_key'],
                    'order_id'               => $lockedOrder->id,
                    'pautang_installment_id' => $installment->id,
                    'customer_id'            => $request->user()->id,
                    'amount'                 => number_format($amount, 2, '.', ''),
                    'reference_number'       => $referenceNumber,
                    'screenshot_path'        => $screenshotPath,
                    'payment_date'           => $attributes['payment_date'],
                ]);
                $this->activityLogs->record(
                    $request->user(),
                    'payments',
                    'created',
                    $payment,
                    "GCash payment #{$payment->id} for order {$lockedOrder->order_number} submitted for verification.",
                );
                $created = true;

                return $payment;
            });
        } catch (QueryException $exception) {
            if ($screenshotPath) {
                Storage::disk('r2-private')->delete($screenshotPath);
            }

            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages(['reference_number' => 'This GCash reference number has already been submitted.']);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($screenshotPath) {
                Storage::disk('r2-private')->delete($screenshotPath);
            }

            throw $exception;
        }

        if ($created) {
            $this->notifications->paymentSubmitted($payment->load(['order', 'customer']));
        }

        return to_route('orders.show', $order)->with('success', 'GCash payment submitted for verification.');
    }

    public function show(Request $request, GcashPayment $gcashPayment): Response
    {
        $this->ensureAdmin($request);
        $gcashPayment->load(['order:id,order_number,final_amount,amount_paid,remaining_balance', 'customer:id,name,email,mobile_number', 'pautangInstallment:id,installment_number,amount_due,amount_paid,remaining_balance', 'reviewer:id,name']);

        return Inertia::render('modules/payments/Show', [
            'payment' => $this->paymentData($gcashPayment, true),
        ]);
    }

    public function screenshot(Request $request, GcashPayment $gcashPayment)
    {
        abort_unless($request->user()?->is_admin || $gcashPayment->customer_id === $request->user()?->id, 403);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('r2-private');
        abort_unless($disk->exists($gcashPayment->screenshot_path), 404);

        return $disk->response($gcashPayment->screenshot_path);
    }

    public function approve(ReviewGcashPaymentRequest $request, GcashPayment $gcashPayment): RedirectResponse
    {
        /** @var list<int> $earnedLedgerIds */
        $earnedLedgerIds = [];

        try {
            DB::transaction(function () use ($request, $gcashPayment, &$earnedLedgerIds): void {
                $payment = GcashPayment::query()->lockForUpdate()->findOrFail($gcashPayment->id);
                $this->ensurePending($payment);
                $order       = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                $installment = PautangInstallment::query()->lockForUpdate()->findOrFail($payment->pautang_installment_id);
                $this->ensureSubmittable($order, $installment);

                $amount = round((float) $payment->amount, 2);
                if ($payment->customer_id !== $order->customer_id || $installment->order_id !== $order->id || $amount > round((float) $installment->remaining_balance, 2)) {
                    throw ValidationException::withMessages(['amount' => 'This payment is greater than the installment\'s current remaining balance. Reject it and ask the customer to submit a new payment.']);
                }

                $amountPaid = round((float) $installment->amount_paid + $amount, 2);
                $remaining  = round(max(0, (float) $installment->amount_due - $amountPaid), 2);
                $installment->fill([
                    'amount_paid'       => number_format($amountPaid, 2, '.', ''),
                    'remaining_balance' => number_format($remaining, 2, '.', ''),
                    'paid_date'         => $remaining === 0.0 ? today() : null,
                ]);
                $installment->status = $installment->currentStatus();
                $installment->save();

                $installments = $order->pautangInstallments()->lockForUpdate()->get();
                foreach ($installments as $scheduledInstallment) {
                    $status = $scheduledInstallment->currentStatus();
                    if ($scheduledInstallment->status !== $status) {
                        $scheduledInstallment->update(['status' => $status]);
                    }
                }

                $this->updatePautangOrderTotals($order, $installments);

                $payment->update([
                    'status'                    => 'approved',
                    'approved_reference_number' => $payment->reference_number,
                    'remarks'                   => $request->validated('remarks'),
                    'reviewed_by'               => $request->user()->id,
                    'reviewed_at'               => now(),
                ]);
                $this->activityLogs->record(
                    $request->user(),
                    'payments',
                    'approved',
                    $payment,
                    "GCash payment #{$payment->id} for order {$order->order_number} approved.",
                );

                $installmentLedger = $this->points->awardOnTimeInstallmentPayment($order, $installment, $payment);
                if ($installmentLedger) {
                    $earnedLedgerIds[] = $installmentLedger->id;
                }

                $completionLedger = $this->points->awardCompletedOrder($order);
                if ($completionLedger) {
                    $earnedLedgerIds[] = $completionLedger->id;
                }
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages(['reference_number' => 'This GCash reference number has already been approved.']);
            }

            throw $exception;
        }

        $this->notifications->paymentApproved($gcashPayment->fresh(['order', 'customer']));
        foreach ($earnedLedgerIds as $earnedLedgerId) {
            $this->notifications->pointsEarned(PointsLedger::query()->with('order')->findOrFail($earnedLedgerId));
        }

        return to_route('gcash-payments.show', $gcashPayment)->with('success', 'GCash payment approved and balances updated.');
    }

    public function reject(RejectGcashPaymentRequest $request, GcashPayment $gcashPayment): RedirectResponse
    {
        DB::transaction(function () use ($request, $gcashPayment): void {
            $payment = GcashPayment::query()->lockForUpdate()->findOrFail($gcashPayment->id);
            $this->ensurePending($payment);
            $order = Order::query()->findOrFail($payment->order_id);
            $payment->update([
                'status'      => 'rejected',
                'remarks'     => $request->validated('remarks'),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            $this->activityLogs->record(
                $request->user(),
                'payments',
                'rejected',
                $payment,
                "GCash payment #{$payment->id} for order {$order->order_number} rejected.",
            );
        });

        $this->notifications->paymentRejected($gcashPayment->fresh(['order', 'customer']));

        return to_route('gcash-payments.show', $gcashPayment)->with('success', 'GCash payment rejected.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }

    private function ensureCustomerOwnsOrder(Request $request, Order $order): void
    {
        abort_unless(! $request->user()?->is_admin && $order->customer_id === $request->user()?->id, 403);
    }

    private function requestedInstallment(int $installmentId, Order $order): PautangInstallment
    {
        $installment   = $order->pautangInstallments->firstWhere('id', $installmentId);
        if (! $installment) {
            throw ValidationException::withMessages(['installment' => 'Choose an unpaid pautang installment.']);
        }

        return $installment;
    }

    /** @param array<string, mixed> $attributes */
    private function installmentFromAttributes(array $attributes, Order $order): PautangInstallment
    {
        $installment = PautangInstallment::query()->lockForUpdate()->find($attributes['pautang_installment_id']);
        if (! $installment || $installment->order_id !== $order->id) {
            throw ValidationException::withMessages(['pautang_installment_id' => 'Choose a valid installment for this order.']);
        }

        return $installment;
    }

    private function ensureSubmittable(Order $order, PautangInstallment $installment): void
    {
        if ($order->order_status === 'pending' || $order->order_status === 'cancelled' || (float) $order->remaining_balance <= 0) {
            throw ValidationException::withMessages(['payment' => 'This order cannot receive a GCash payment.']);
        }

        if ((float) $installment->remaining_balance <= 0) {
            throw ValidationException::withMessages(['pautang_installment_id' => 'This installment cannot receive a GCash payment.']);
        }
    }

    private function ensurePending(GcashPayment $payment): void
    {
        if ($payment->status !== 'pending_verification') {
            throw ValidationException::withMessages(['payment' => 'Only pending payments can be reviewed.']);
        }
    }

    private function updateOrderTotals(Order $order, float $amountPaid, float $remainingBalance): void
    {
        $order->update([
            'amount_paid'       => number_format($amountPaid, 2, '.', ''),
            'remaining_balance' => number_format($remainingBalance, 2, '.', ''),
            'payment_status'    => $remainingBalance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partially_paid' : 'unpaid'),
        ]);
    }

    /** @param Collection<int, PautangInstallment> $installments */
    private function updatePautangOrderTotals(Order $order, Collection $installments): void
    {
        $amountPaid    = round((float) $installments->sum('amount_paid'), 2);
        $amountDue     = round((float) $installments->sum('amount_due'), 2);
        $remaining     = round(max(0, $amountDue - $amountPaid), 2);
        $paymentStatus = $remaining <= 0
            ? 'paid'
            : ($installments->contains(fn (PautangInstallment $item) => $item->currentStatus() === 'overdue')
                ? 'overdue'
                : ($amountPaid > 0 ? 'partially_paid' : 'unpaid'));

        $this->updateOrderTotals($order, $amountPaid, $remaining);
        if ($order->payment_status !== $paymentStatus) {
            $order->update(['payment_status' => $paymentStatus]);
        }
    }

    /** @return array<string, mixed> */
    private function paymentOrderData(Order $order): array
    {
        return [
            'id'                => $order->id,
            'order_number'      => $order->order_number,
            'remaining_balance' => $order->remaining_balance,
        ];
    }

    /** @return array<string, mixed> */
    private function submissionLimit(PautangInstallment $installment): float
    {
        $pendingAmount = (float) GcashPayment::query()
            ->where('pautang_installment_id', $installment->id)
            ->where('status', 'pending_verification')
            ->sum('amount');

        return round(max(0, (float) $installment->remaining_balance - $pendingAmount), 2);
    }

    private function installmentData(PautangInstallment $installment, float $submissionLimit): array
    {
        return [
            'id'                 => $installment->id,
            'installment_number' => $installment->installment_number,
            'amount_due'         => $installment->amount_due,
            'remaining_balance'  => $installment->remaining_balance,
            'submission_limit'   => number_format($submissionLimit, 2, '.', ''),
        ];
    }

    /** @return array<string, mixed> */
    private function paymentData(GcashPayment $payment, bool $includeOrderDetails): array
    {
        return [
            'id'               => $payment->id,
            'amount'           => $payment->amount,
            'reference_number' => $payment->reference_number,
            'payment_date'     => $payment->payment_date->toDateString(),
            'status'           => $payment->status,
            'remarks'          => $payment->remarks,
            'reviewed_at'      => $payment->reviewed_at?->toISOString(),
            'screenshot_url'   => route('gcash-payments.screenshot', $payment),
            'order'            => [
                'id'           => $payment->order->id,
                'order_number' => $payment->order->order_number,
                ...($includeOrderDetails ? [
                    'final_amount'      => $payment->order->final_amount,
                    'amount_paid'       => $payment->order->amount_paid,
                    'remaining_balance' => $payment->order->remaining_balance,
                ] : []),
            ],
            'customer' => [
                'id'            => $payment->customer->id,
                'name'          => $payment->customer->name,
                'email'         => $payment->customer->email,
                'mobile_number' => $payment->customer->mobile_number,
            ],
            'installment' => $payment->pautangInstallment ? [
                'id'                 => $payment->pautangInstallment->id,
                'installment_number' => $payment->pautangInstallment->installment_number,
                ...($includeOrderDetails ? [
                    'amount_due'        => $payment->pautangInstallment->amount_due,
                    'amount_paid'       => $payment->pautangInstallment->amount_paid,
                    'remaining_balance' => $payment->pautangInstallment->remaining_balance,
                ] : []),
            ] : null,
            'reviewer'   => $payment->reviewer ? ['id' => $payment->reviewer->id, 'name' => $payment->reviewer->name] : null,
            'created_at' => $payment->created_at->toISOString(),
        ];
    }
}
