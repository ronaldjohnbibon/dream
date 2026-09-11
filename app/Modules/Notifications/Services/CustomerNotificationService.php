<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Notifications\CustomerNotification;
use App\Modules\Notifications\WebPushNotification;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Log;

class CustomerNotificationService
{
    public function __construct(private readonly SystemLogger $systemLogs) {}

    public function deliveryStatus(Order $order, string $status): void
    {
        [$type, $title, $message] = match ($status) {
            'scheduled'        => ['order_confirmed', 'Order confirmed', "Your order {$order->order_number} has been confirmed."],
            'preparing'        => ['order_preparing', 'Order is being prepared', "Your order {$order->order_number} is being prepared."],
            'out_for_delivery' => ['order_out_for_delivery', 'Order out for delivery', "Your order {$order->order_number} is out for delivery."],
            'delivered'        => ['order_delivered', 'Order delivered', "Your order {$order->order_number} has been delivered."],
            'cancelled'        => ['order_cancelled', 'Order cancelled', "Your order {$order->order_number} was cancelled."],
            default            => [null, null, null],
        };

        if (! $type) {
            return;
        }

        $this->sendCustomer($this->customer($order), [
            'event_key'  => "{$type}:{$order->id}",
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'action_url' => route('orders.show', $order),
        ]);
    }

    public function paymentSubmitted(GcashPayment $payment): void
    {
        $this->paymentStatus($payment, 'payment_submitted', 'Payment submitted', "Your GCash payment for order {$payment->order->order_number} was submitted for verification.");
        $this->administratorsPaymentSubmitted($payment);
    }

    public function paymentApproved(GcashPayment $payment): void
    {
        $this->paymentStatus($payment, 'payment_approved', 'Payment approved', "Your GCash payment for order {$payment->order->order_number} was approved.");
    }

    public function paymentRejected(GcashPayment $payment): void
    {
        $message = "Your GCash payment for order {$payment->order->order_number} was rejected.";
        if ($payment->remarks) {
            $message .= " Reason: {$payment->remarks}";
        }

        $this->paymentStatus($payment, 'payment_rejected', 'Payment rejected', $message);
    }

    public function pointsEarned(PointsLedger $ledger): void
    {
        $order     = $ledger->order;
        $orderText = $order ? " for order {$order->order_number}" : '';
        $isOnTime  = $ledger->type === 'on_time_payment_bonus';

        $this->sendCustomer(User::query()->findOrFail($ledger->customer_id), [
            'event_key' => "points_earned:{$ledger->id}",
            'type'      => $isOnTime ? 'on_time_payment_bonus' : 'points_earned',
            'title'     => $isOnTime ? 'On-time payment bonus earned' : 'Points earned',
            'message'   => $isOnTime
                ? "You earned {$ledger->points} points for an on-time payment{$orderText}."
                : "You earned {$ledger->points} points{$orderText}.",
            'action_url' => route('points.show'),
        ]);
    }

    public function pointsRedeemed(Order $order, int $points): void
    {
        $this->sendCustomer($this->customer($order), [
            'event_key'  => "points_redeemed:{$order->id}",
            'type'       => 'points_redeemed',
            'title'      => 'Points redeemed',
            'message'    => "You redeemed {$points} points on order {$order->order_number}.",
            'action_url' => route('points.show'),
        ]);
    }

    public function orderSubmitted(Order $order): void
    {
        $this->sendCustomer($this->customer($order), [
            'event_key'  => "order_submitted:{$order->id}",
            'type'       => 'order_submitted',
            'title'      => 'Order submitted',
            'message'    => "Your pautang order {$order->order_number} was submitted.",
            'action_url' => route('orders.show', $order),
        ]);
    }

    public function pautangCompleted(Order $order): void
    {
        $this->sendCustomer($this->customer($order), [
            'event_key'  => "pautang_completed:{$order->id}",
            'type'       => 'pautang_completed',
            'title'      => 'Pautang completed',
            'message'    => "Your pautang for order {$order->order_number} has been fully paid.",
            'action_url' => route('orders.show', $order),
        ]);
    }

    public function newOrder(Order $order): void
    {
        $customer = $this->customer($order);

        $this->sendToAdministrators([
            'event_key'  => "admin_new_order:{$order->id}",
            'type'       => 'admin_new_order',
            'title'      => 'New order placed',
            'message'    => "{$customer->name} placed order {$order->order_number}.",
            'action_url' => route('orders.show', $order),
        ]);
    }

    public function installmentDueTomorrow(PautangInstallment $installment): bool
    {
        return $this->sendInstallmentReminder($installment, 'due_tomorrow', 'Your :amount payment is due tomorrow.');
    }

    public function installmentDueToday(PautangInstallment $installment): bool
    {
        return $this->sendInstallmentReminder($installment, 'due_today', 'Your :amount payment is due today.');
    }

    public function administratorsUpcomingInstallment(PautangInstallment $installment): void
    {
        $order = $installment->order;

        $this->sendToAdministrators([
            'event_key'  => "admin_installment_upcoming:{$installment->id}:{$installment->due_date->toDateString()}",
            'type'       => 'admin_installment_upcoming',
            'title'      => 'Upcoming installment due date',
            'message'    => "Installment {$installment->installment_number} for order {$order->order_number} is due on {$installment->due_date->format('M j, Y')}.",
            'action_url' => route('pautang.index'),
        ]);
    }

    public function overdueInstallment(PautangInstallment $installment): bool
    {
        return $this->sendInstallmentReminder($installment, 'overdue', 'Your :amount payment is overdue. Please submit your payment.');
    }

    private function sendInstallmentReminder(PautangInstallment $installment, string $reminderType, string $message): bool
    {
        $order = $installment->order;

        return $this->sendOnce($this->customer($order), [
            'event_key'  => "installment_{$reminderType}:{$installment->id}",
            'type'       => "installment_{$reminderType}",
            'title'      => '🍚 aRICE Payment Reminder',
            'message'    => str_replace(':amount', '₱'.number_format((float) $installment->remaining_balance, 2), $message),
            'action_url' => route('orders.show', $order),
        ], true);
    }

    public function administratorsOverdueInstallment(PautangInstallment $installment): void
    {
        $order = $installment->order;

        $this->sendToAdministrators([
            'event_key'  => "admin_installment_overdue:{$installment->id}:{$installment->due_date->toDateString()}",
            'type'       => 'admin_installment_overdue',
            'title'      => 'Installment overdue',
            'message'    => "Installment {$installment->installment_number} for order {$order->order_number} is overdue.",
            'action_url' => route('pautang.index', ['view' => 'overdue']),
        ]);
    }

    private function paymentStatus(GcashPayment $payment, string $type, string $title, string $message): void
    {
        $this->sendCustomer($payment->customer, [
            'event_key'  => "{$type}:{$payment->id}",
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'action_url' => route('orders.show', $payment->order),
        ]);
    }

    private function administratorsPaymentSubmitted(GcashPayment $payment): void
    {
        $this->sendToAdministrators([
            'event_key'  => "admin_payment_submitted:{$payment->id}",
            'type'       => 'admin_payment_submitted',
            'title'      => 'New payment submitted',
            'message'    => "{$payment->customer->name} submitted a GCash payment for order {$payment->order->order_number}.",
            'action_url' => route('gcash-payments.show', $payment),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function sendCustomer(User $customer, array $data): void
    {
        $this->sendOnce($customer, $data, true);
    }

    /** @param array<string, mixed> $data */
    private function sendOnce(User $customer, array $data, bool $sendPush = false): bool
    {
        $created = $customer->getConnection()->transaction(function () use ($customer, $data): bool {
            $customer->newQuery()->lockForUpdate()->findOrFail($customer->getKey());
            if ($customer->notifications()->where('data->event_key', $data['event_key'])->exists()) {
                return false;
            }

            $customer->notify(new CustomerNotification($data));

            return true;
        });

        if ($created && $sendPush) {
            $this->sendPush($customer, $data);
        }

        return $created;
    }

    /** @param array<string, mixed> $data */
    protected function sendPush(User $customer, array $data): void
    {
        try {
            if (! $customer->pushSubscriptions()->exists()) {
                return;
            }

            $customer->notify(new WebPushNotification(
                title: $data['title'],
                body: $data['message'],
                url: $data['action_url'],
                data: $data,
            ));
        } catch (\Throwable $exception) {
            Log::warning('Unable to send customer web push notification.', [
                'customer_id'     => $customer->id,
                'event_key'       => $data['event_key'],
                'exception_class' => $exception::class,
            ]);
            $this->systemLogs->record(
                type: 'system',
                action: 'push_notification_failed',
                description: 'Web Push notification could not be queued.',
                module: 'notifications',
                status: 'failed',
                metadata: [
                    'notification_type' => $data['type'],
                    'event_key'         => $data['event_key'],
                    'customer_id'       => $customer->id,
                    'destination_url'   => $data['action_url'],
                    'error_message'     => 'Web Push notification could not be queued.',
                    'exception_class'   => $exception::class,
                ],
            );
        }
    }

    /** @param array<string, mixed> $data */
    private function sendToAdministrators(array $data): void
    {
        User::query()->where('is_admin', true)->each(function (User $admin) use ($data): void {
            $this->sendOnce($admin, $data);
        });
    }

    private function customer(Order $order): User
    {
        return $order->relationLoaded('customer')
            ? $order->customer
            : User::query()->findOrFail($order->customer_id);
    }
}
