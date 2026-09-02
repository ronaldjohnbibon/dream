<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\CustomerNotification;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Users\Models\User;

class CustomerNotificationService
{
    public function deliveryStatus(Order $order, string $status): void
    {
        [$type, $title, $message] = match ($status) {
            'scheduled' => ['order_confirmed', 'Order confirmed', "Your order {$order->order_number} has been confirmed."],
            'preparing' => ['order_preparing', 'Order is being prepared', "Your order {$order->order_number} is being prepared."],
            'out_for_delivery' => ['order_out_for_delivery', 'Order out for delivery', "Your order {$order->order_number} is out for delivery."],
            'delivered' => ['order_delivered', 'Order delivered', "Your order {$order->order_number} has been delivered."],
            default => [null, null, null],
        };

        if (! $type) {
            return;
        }

        $this->send($this->customer($order), [
            'event_key' => "{$type}:{$order->id}",
            'type' => $type,
            'title' => $title,
            'message' => $message,
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
        $order = $ledger->order;
        $orderText = $order ? " for order {$order->order_number}" : '';

        $this->send(User::query()->findOrFail($ledger->customer_id), [
            'event_key' => "points_earned:{$ledger->id}",
            'type' => 'points_earned',
            'title' => 'Points earned',
            'message' => "You earned {$ledger->points} points{$orderText}.",
            'action_url' => route('points.show'),
        ]);
    }

    public function pointsRedeemed(Order $order, int $points): void
    {
        $this->send($this->customer($order), [
            'event_key' => "points_redeemed:{$order->id}",
            'type' => 'points_redeemed',
            'title' => 'Points redeemed',
            'message' => "You redeemed {$points} points on order {$order->order_number}.",
            'action_url' => route('points.show'),
        ]);
    }

    public function newOrder(Order $order): void
    {
        $customer = $this->customer($order);

        $this->sendToAdministrators([
            'event_key' => "admin_new_order:{$order->id}",
            'type' => 'admin_new_order',
            'title' => 'New order placed',
            'message' => "{$customer->name} placed order {$order->order_number}.",
            'action_url' => route('orders.show', $order),
        ]);
    }

    public function upcomingInstallment(PautangInstallment $installment): bool
    {
        $order = $installment->order;

        return $this->sendOnce($this->customer($order), [
            'event_key' => "installment_upcoming:{$installment->id}:{$installment->due_date->toDateString()}",
            'type' => 'installment_upcoming',
            'title' => 'Upcoming installment due date',
            'message' => "Installment {$installment->installment_number} for order {$order->order_number} is due on {$installment->due_date->format('M j, Y')}.",
            'action_url' => route('orders.show', $order),
        ]);
    }

    public function administratorsUpcomingInstallment(PautangInstallment $installment): void
    {
        $order = $installment->order;

        $this->sendToAdministrators([
            'event_key' => "admin_installment_upcoming:{$installment->id}:{$installment->due_date->toDateString()}",
            'type' => 'admin_installment_upcoming',
            'title' => 'Upcoming installment due date',
            'message' => "Installment {$installment->installment_number} for order {$order->order_number} is due on {$installment->due_date->format('M j, Y')}.",
            'action_url' => route('pautang.index'),
        ]);
    }

    public function overdueInstallment(PautangInstallment $installment): bool
    {
        $order = $installment->order;

        return $this->sendOnce($this->customer($order), [
            'event_key' => "installment_overdue:{$installment->id}:{$installment->due_date->toDateString()}",
            'type' => 'installment_overdue',
            'title' => 'Installment overdue',
            'message' => "Installment {$installment->installment_number} for order {$order->order_number} is overdue. Please submit your payment.",
            'action_url' => route('orders.show', $order),
        ]);
    }

    public function administratorsOverdueInstallment(PautangInstallment $installment): void
    {
        $order = $installment->order;

        $this->sendToAdministrators([
            'event_key' => "admin_installment_overdue:{$installment->id}:{$installment->due_date->toDateString()}",
            'type' => 'admin_installment_overdue',
            'title' => 'Installment overdue',
            'message' => "Installment {$installment->installment_number} for order {$order->order_number} is overdue.",
            'action_url' => route('pautang.index', ['view' => 'overdue']),
        ]);
    }

    private function paymentStatus(GcashPayment $payment, string $type, string $title, string $message): void
    {
        $this->send($payment->customer, [
            'event_key' => "{$type}:{$payment->id}",
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => route('orders.show', $payment->order),
        ]);
    }

    private function administratorsPaymentSubmitted(GcashPayment $payment): void
    {
        $this->sendToAdministrators([
            'event_key' => "admin_payment_submitted:{$payment->id}",
            'type' => 'admin_payment_submitted',
            'title' => 'New payment submitted',
            'message' => "{$payment->customer->name} submitted a GCash payment for order {$payment->order->order_number}.",
            'action_url' => route('gcash-payments.show', $payment),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function send(User $customer, array $data): void
    {
        $customer->notify(new CustomerNotification($data));
    }

    /** @param array<string, mixed> $data */
    private function sendOnce(User $customer, array $data): bool
    {
        if ($customer->notifications()->where('data->event_key', $data['event_key'])->exists()) {
            return false;
        }

        $this->send($customer, $data);

        return true;
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
