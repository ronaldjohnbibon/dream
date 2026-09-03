<?php

namespace App\Modules\Points\Services;

use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Settings\Models\PointsSetting;
use App\Modules\Settings\Models\SystemSetting;
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
                'is_enabled' => true,
                'minimum_redemption' => 1,
                'maximum_points_usable' => 1000,
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

    public function awardCompletedOrder(Order $order): ?PointsLedger
    {
        if (! $this->settings()->is_enabled || $order->order_status !== 'delivered') {
            return null;
        }

        $points = $this->settings()->completed_order_points;
        if ($points <= 0) {
            return null;
        }

        $ledger = PointsLedger::query()->firstOrCreate(
            [
                'type' => 'order_reward',
                'source_type' => 'order',
                'source_id' => $order->id,
            ],
            [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'points' => $points,
                'description' => "Delivered order {$order->order_number} reward.",
                'transaction_date' => today(),
            ],
        );

        return $ledger->wasRecentlyCreated ? $ledger : null;
    }

    public function awardOnTimeInstallmentPayment(Order $order, PautangInstallment $installment, GcashPayment $payment): ?PointsLedger
    {
        if (! $this->settings()->is_enabled || $order->order_status === 'cancelled'
            || $payment->status !== 'approved'
            || (float) $installment->remaining_balance > 0
            || $payment->payment_date->isAfter($installment->due_date->copy()->addDays(SystemSetting::current()->pautang_grace_period_days))) {
            return null;
        }

        $points = $this->settings()->on_time_payment_points;
        if ($points <= 0) {
            return null;
        }

        $ledger = PointsLedger::query()->firstOrCreate(
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

        return $ledger->wasRecentlyCreated ? $ledger : null;
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

    public function redeemOrder(Order $order, int $points): PointsLedger
    {
        return PointsLedger::query()->firstOrCreate(
            [
                'type' => 'redemption',
                'source_type' => 'order',
                'source_id' => $order->id,
            ],
            [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'points' => -$points,
                'description' => "Points redeemed for order {$order->order_number}.",
                'transaction_date' => today(),
            ],
        );
    }

    public function refundOrderRedemption(Order $order): ?PointsLedger
    {
        if ($order->points_used <= 0) {
            return null;
        }

        return PointsLedger::query()->firstOrCreate(
            [
                'type' => 'redemption_refund',
                'source_type' => 'order',
                'source_id' => $order->id,
            ],
            [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'points' => $order->points_used,
                'description' => "Points returned for cancelled order {$order->order_number}.",
                'transaction_date' => today(),
            ],
        );
    }
}
