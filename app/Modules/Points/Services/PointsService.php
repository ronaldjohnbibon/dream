<?php

namespace App\Modules\Points\Services;

use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Settings\Models\PointsSetting;
use App\Modules\Users\Models\User;

class PointsService
{
    public function settings(): PointsSetting
    {
        return PointsSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'completed_order_points' => 10,
                'on_time_payment_points' => 5,
                'peso_per_point' => '0.10',
            ],
        );
    }

    public function currentBalance(User|int $customer): int
    {
        $customerId = $customer instanceof User ? $customer->id : $customer;

        return (int) PointsLedger::query()
            ->where('customer_id', $customerId)
            ->sum('points');
    }

    public function awardCompletedOrder(Order $order): void
    {
        if ($order->order_status !== 'completed') {
            return;
        }

        $points = $this->settings()->completed_order_points;
        if ($points <= 0) {
            return;
        }

        PointsLedger::query()->firstOrCreate(
            [
                'type' => 'order_reward',
                'source_type' => 'order',
                'source_id' => $order->id,
            ],
            [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'points' => $points,
                'description' => "Completed order {$order->order_number} reward.",
                'transaction_date' => today(),
            ],
        );
    }

    public function awardOnTimeInstallmentPayment(Order $order, PautangInstallment $installment, GcashPayment $payment): void
    {
        if ($order->order_status === 'cancelled'
            || $payment->status !== 'approved'
            || (float) $installment->remaining_balance > 0
            || $payment->payment_date->isAfter($installment->due_date)) {
            return;
        }

        $points = $this->settings()->on_time_payment_points;
        if ($points <= 0) {
            return;
        }

        PointsLedger::query()->firstOrCreate(
            [
                'type' => 'on_time_payment_bonus',
                'source_type' => 'pautang_installment',
                'source_id' => $installment->id,
            ],
            [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'pautang_installment_id' => $installment->id,
                'points' => $points,
                'description' => "On-time payment bonus for installment {$installment->installment_number} of order {$order->order_number}.",
                'transaction_date' => $payment->payment_date,
            ],
        );
    }

    public function createAdminAdjustment(User $customer, int $points, string $reason, User $admin): PointsLedger
    {
        return $customer->pointsLedgers()->create([
            'type' => 'admin_adjustment',
            'points' => $points,
            'description' => "Admin adjustment by {$admin->name}: {$reason}",
            'transaction_date' => today(),
        ]);
    }
}
