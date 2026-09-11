<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Logs\Services\ActivityLogger;
use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Notifications\Services\CustomerNotificationService;
use App\Modules\Orders\Models\Order;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Points\Services\PointsService;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        private readonly PointsService $points,
        private readonly CustomerNotificationService $notifications,
        private readonly DeliveryPricingService $pricing,
        private readonly ActivityLogger $activityLogs,
        private readonly SystemLogger $systemLogs,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function update(Delivery $delivery, array $attributes, User $admin): Delivery
    {
        $notificationData = [];
        /** @var array{action: string, description: string, order_id: int, status: string, metadata: array<string, mixed>}|null $systemLog */
        $systemLog = null;
        /** @var array{action: string, description: string, ledger_id: int, metadata: array<string, mixed>}|null $pointsLog */
        $pointsLog = null;
        /** @var PointsLedger|null $earnedLedger */
        $earnedLedger = null;

        $updatedDelivery = DB::transaction(function () use ($delivery, $attributes, $admin, &$notificationData, &$systemLog, &$pointsLog, &$earnedLedger): Delivery {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $order          = Order::query()->lockForUpdate()->findOrFail($lockedDelivery->order_id);
            $nextStatus     = $attributes['status'];

            if (! in_array($nextStatus, $this->allowedStatuses($lockedDelivery), true)) {
                throw ValidationException::withMessages(['status' => 'This delivery status change is not allowed.']);
            }

            $area = DeliveryArea::query()->find($attributes['delivery_area_id']);
            if (! $area || (! $area->is_active && $area->id !== $lockedDelivery->delivery_area_id)) {
                throw ValidationException::withMessages(['delivery_area_id' => 'Choose an active delivery area.']);
            }

            $isScheduled    = $nextStatus === 'scheduled' && $lockedDelivery->status !== 'scheduled';
            $isDelivered    = $nextStatus === 'delivered' && $lockedDelivery->status !== 'delivered';
            $isCancelled    = $nextStatus === 'cancelled' && $lockedDelivery->status !== 'cancelled';
            $isStatusChange = $nextStatus                                            !== $lockedDelivery->status;

            if ($isCancelled) {
                $hasPayments = $order->gcashPayments()->exists()
                    || $order->pautangInstallments()->where('amount_paid', '>', 0)->exists();
                if ($hasPayments) {
                    throw ValidationException::withMessages(['status' => 'A delivery with payment submissions cannot be cancelled.']);
                }
            }

            $newFee              = $this->pricing->feeFor($area);
            $oldFee              = (float) $lockedDelivery->delivery_fee;
            $hasCommittedBalance = $order->gcashPayments()->exists() || $order->pautangInstallments()->exists();
            if ($newFee !== $oldFee && $hasCommittedBalance) {
                throw ValidationException::withMessages(['delivery_area_id' => 'The delivery area cannot change after payment activity or pautang scheduling.']);
            }
            if ($newFee !== $oldFee) {
                $finalAmount = max(0, round((float) $order->final_amount - $oldFee + $newFee, 2));
                $order->update([
                    'delivery_fee'      => number_format($newFee, 2, '.', ''),
                    'final_amount'      => number_format($finalAmount, 2, '.', ''),
                    'remaining_balance' => number_format($finalAmount, 2, '.', ''),
                    'payment_status'    => $finalAmount <= 0 ? 'paid' : 'unpaid',
                ]);
            }

            if ($isScheduled && $order->pautangInstallments()->doesntExist()) {
                $this->createPautangInstallments($order);
            }

            if ($isCancelled) {
                $product       = RiceProduct::query()->lockForUpdate()->findOrFail($order->rice_product_id);
                $previousStock = $product->available_stock;
                $newStock      = $previousStock + $order->quantity;
                $product->update([
                    'available_stock' => $newStock,
                    'reserved_stock'  => max(0, $product->reserved_stock - $order->quantity),
                ]);
                $product->stockMovements()->create([
                    'quantity'  => $order->quantity, 'type' => 'cancellation', 'previous_stock' => $previousStock,
                    'new_stock' => $newStock, 'order_id' => $order->id,
                    'notes'     => "Order {$order->order_number} cancelled.", 'user_id' => $admin->id,
                ]);
                $customer       = User::query()->lockForUpdate()->findOrFail($order->customer_id);
                $previousPoints = $this->points->currentBalance($customer);
                $refundLedger   = $this->points->refundOrderRedemption($order);
                if ($refundLedger?->wasRecentlyCreated) {
                    $pointsLog = [
                        'action'      => 'points_refunded',
                        'description' => "{$refundLedger->points} redeemed points refunded for cancelled order {$order->order_number}.",
                        'ledger_id'   => $refundLedger->id,
                        'metadata'    => [
                            'ledger_id'       => $refundLedger->id,
                            'customer_id'     => $refundLedger->customer_id,
                            'order_id'        => $refundLedger->order_id,
                            'previous_points' => $previousPoints,
                            'points_added'    => $refundLedger->points,
                            'new_points'      => $this->points->currentBalance($customer),
                            'reason'          => 'order_cancelled',
                        ],
                    ];
                }
                $order->pautangInstallments()->delete();
                $this->activityLogs->record($admin, 'orders', 'cancelled', $order, "Order {$order->order_number} cancelled.");
            }

            if ($isDelivered) {
                $product = RiceProduct::query()->lockForUpdate()->findOrFail($order->rice_product_id);
                $product->update(['reserved_stock' => max(0, $product->reserved_stock - $order->quantity)]);
            }

            $deliveryData = [
                'delivery_area_id'   => $area->id,
                'delivery_area_name' => $area->name,
                'delivery_address'   => $attributes['delivery_address'],
                'delivery_fee'       => number_format($newFee, 2, '.', ''),
                'delivery_date'      => $attributes['delivery_date'] ?: null,
                'delivery_person'    => $attributes['delivery_person'] ?: null,
                'status'             => $nextStatus,
                'notes'              => $attributes['notes'] ?: null,
                'delivered_date'     => $isDelivered ? today() : $lockedDelivery->delivered_date,
            ];
            $lockedDelivery->update($deliveryData);

            $previousOrderStatus = $order->order_status;
            $nextOrderStatus     = $this->orderStatus($nextStatus);
            $order->update(['order_status' => $nextOrderStatus, 'delivery_date' => $deliveryData['delivery_date']]);
            if ($previousOrderStatus !== $nextOrderStatus) {
                $action = match (true) {
                    $nextOrderStatus     === 'cancelled'                                   => 'order_cancelled',
                    $previousOrderStatus === 'pending' && $nextOrderStatus === 'confirmed' => 'order_approved',
                    default                                                                => 'order_status_changed',
                };
                $metadata = [
                    'order_id'        => $order->id,
                    'customer_id'     => $order->customer_id,
                    'previous_status' => $previousOrderStatus,
                    'new_status'      => $nextOrderStatus,
                ];
                if ($action === 'order_approved') {
                    $metadata['final_amount'] = $order->final_amount;
                }

                $systemLog = [
                    'action'      => $action,
                    'description' => match ($action) {
                        'order_cancelled' => "Order {$order->order_number} cancelled.",
                        'order_approved'  => "Order {$order->order_number} approved.",
                        default           => "Order {$order->order_number} status changed from {$previousOrderStatus} to {$nextOrderStatus}.",
                    },
                    'order_id' => $order->id,
                    'status'   => $nextOrderStatus,
                    'metadata' => $metadata,
                ];
            }
            if ($isDelivered) {
                $previousPoints = $this->points->currentBalance($order->customer_id);
                $earnedLedger   = $this->points->awardCompletedOrder($order);
                if ($earnedLedger) {
                    $pointsLog = [
                        'action'      => 'points_earned',
                        'description' => "{$earnedLedger->points} completed order points added.",
                        'ledger_id'   => $earnedLedger->id,
                        'metadata'    => [
                            'ledger_id'       => $earnedLedger->id,
                            'customer_id'     => $earnedLedger->customer_id,
                            'order_id'        => $earnedLedger->order_id,
                            'previous_points' => $previousPoints,
                            'points_added'    => $earnedLedger->points,
                            'new_points'      => $this->points->currentBalance($order->customer_id),
                        ],
                    ];
                }
            }
            if ($isStatusChange) {
                $notificationData[] = ['status' => $nextStatus, 'order_id' => $order->id];
            }

            return $lockedDelivery->fresh(['order.riceProduct', 'customer', 'deliveryArea']);
        });

        if ($systemLog) {
            $this->systemLogs->record(
                type: 'activity',
                action: $systemLog['action'],
                description: $systemLog['description'],
                module: 'orders',
                recordId: $systemLog['order_id'],
                status: $systemLog['status'],
                metadata: $systemLog['metadata'],
            );
        }
        if ($pointsLog) {
            $this->systemLogs->record(
                type: 'activity',
                action: $pointsLog['action'],
                description: $pointsLog['description'],
                module: 'points',
                recordId: $pointsLog['ledger_id'],
                status: 'completed',
                metadata: $pointsLog['metadata'],
            );
        }

        foreach ($notificationData as $notification) {
            if (isset($notification['status'])) {
                $this->notifications->deliveryStatus(
                    Order::query()->with('customer')->findOrFail($notification['order_id']),
                    $notification['status'],
                );
            }

        }

        if ($earnedLedger) {
            $this->notifications->pointsEarned($earnedLedger->load('order'));
        }

        return $updatedDelivery;
    }

    /** @return list<string> */
    public function allowedStatuses(Delivery $delivery): array
    {
        return match ($delivery->status) {
            'pending'          => ['pending', 'scheduled', 'cancelled'],
            'scheduled'        => ['scheduled', 'preparing', 'cancelled'],
            'preparing'        => ['preparing', 'out_for_delivery', 'cancelled'],
            'out_for_delivery' => ['out_for_delivery', 'delivered', 'failed', 'cancelled'],
            'failed'           => ['failed', 'scheduled', 'cancelled'],
            'delivered'        => ['delivered'],
            'cancelled'        => ['cancelled'],
        };
    }

    private function orderStatus(string $deliveryStatus): string
    {
        return match ($deliveryStatus) {
            'scheduled' => 'confirmed', 'preparing' => 'preparing', 'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered', 'cancelled' => 'cancelled', 'pending' => 'pending', default => 'out_for_delivery',
        };
    }

    private function createPautangInstallments(Order $order): void
    {
        $settings     = SystemSetting::current();
        $installments = $settings->pautang_installments;
        $amounts      = [];
        $remaining    = round((float) $order->final_amount, 2);
        for ($number = 1; $number <= $installments; $number++) {
            $amount    = $number === $installments ? $remaining : round((float) $order->final_amount / $installments, 2);
            $remaining = round($remaining - $amount, 2);
            $amounts[] = [
                'installment_number' => $number,
                'amount_due'         => number_format($amount, 2, '.', ''),
                'due_date'           => today()->addDays((int) floor(($settings->pautang_payment_term_days * $number) / $installments)),
                'amount_paid'        => '0.00',
                'remaining_balance'  => number_format($amount, 2, '.', ''),
                'status'             => 'pending',
            ];
        }
        $order->pautangInstallments()->createMany($amounts);
    }
}
