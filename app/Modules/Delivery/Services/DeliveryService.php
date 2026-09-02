<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Notifications\Services\CustomerNotificationService;
use App\Modules\Orders\Models\Order;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Points\Services\PointsService;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        private readonly PointsService $points,
        private readonly CustomerNotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function update(Delivery $delivery, array $attributes, User $admin): Delivery
    {
        $notificationData = [];

        $updatedDelivery = DB::transaction(function () use ($delivery, $attributes, $admin, &$notificationData): Delivery {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedDelivery->order_id);
            $nextStatus = $attributes['status'];

            if (! in_array($nextStatus, $this->allowedStatuses($lockedDelivery), true)) {
                throw ValidationException::withMessages(['status' => 'This delivery status change is not allowed.']);
            }

            $area = DeliveryArea::query()->find($attributes['delivery_area_id']);
            if (! $area || (! $area->is_active && $area->id !== $lockedDelivery->delivery_area_id)) {
                throw ValidationException::withMessages(['delivery_area_id' => 'Choose an active delivery area.']);
            }

            $isScheduled = $nextStatus === 'scheduled' && $lockedDelivery->status !== 'scheduled';
            $isDelivered = $nextStatus === 'delivered' && $lockedDelivery->status !== 'delivered';
            $isCancelled = $nextStatus === 'cancelled' && $lockedDelivery->status !== 'cancelled';
            $isStatusChange = $nextStatus !== $lockedDelivery->status;

            if ($isCancelled) {
                $hasPayments = $order->gcashPayments()->exists()
                    || ($order->payment_type === 'pautang' && $order->pautangInstallments()->where('amount_paid', '>', 0)->exists());
                if ($hasPayments) {
                    throw ValidationException::withMessages(['status' => 'A delivery with payment submissions cannot be cancelled.']);
                }
            }

            $newFee = (float) $area->delivery_fee;
            $oldFee = (float) $lockedDelivery->delivery_fee;
            $hasCommittedBalance = $order->gcashPayments()->exists() || $order->pautangInstallments()->exists();
            if ($newFee !== $oldFee && $hasCommittedBalance) {
                throw ValidationException::withMessages(['delivery_area_id' => 'The delivery area cannot change after payment activity or pautang scheduling.']);
            }
            if ($newFee !== $oldFee) {
                $finalAmount = max(0, round((float) $order->final_amount - $oldFee + $newFee, 2));
                $order->update([
                    'delivery_fee' => number_format($newFee, 2, '.', ''),
                    'final_amount' => number_format($finalAmount, 2, '.', ''),
                    'remaining_balance' => number_format($finalAmount, 2, '.', ''),
                    'payment_status' => $finalAmount <= 0 ? 'paid' : 'unpaid',
                ]);
            }

            if ($isScheduled && $order->payment_type === 'pautang' && $order->pautangInstallments()->doesntExist()) {
                $this->createPautangInstallments($order);
            }

            if ($isCancelled) {
                $product = RiceProduct::query()->lockForUpdate()->findOrFail($order->rice_product_id);
                $previousStock = $product->available_stock;
                $newStock = $previousStock + $order->quantity;
                $product->update([
                    'available_stock' => $newStock,
                    'reserved_stock' => max(0, $product->reserved_stock - $order->quantity),
                ]);
                $product->stockMovements()->create([
                    'quantity' => $order->quantity, 'type' => 'cancellation', 'previous_stock' => $previousStock,
                    'new_stock' => $newStock, 'order_id' => $order->id,
                    'notes' => "Order {$order->order_number} cancelled.", 'user_id' => $admin->id,
                ]);
                User::query()->lockForUpdate()->findOrFail($order->customer_id);
                $this->points->refundOrderRedemption($order);
                $order->pautangInstallments()->delete();
            }

            if ($isDelivered) {
                $product = RiceProduct::query()->lockForUpdate()->findOrFail($order->rice_product_id);
                $product->update(['reserved_stock' => max(0, $product->reserved_stock - $order->quantity)]);
            }

            $deliveryData = [
                'delivery_area_id' => $area->id,
                'delivery_area_name' => $area->name,
                'delivery_address' => $attributes['delivery_address'],
                'delivery_fee' => number_format($newFee, 2, '.', ''),
                'delivery_date' => $attributes['delivery_date'] ?: null,
                'delivery_person' => $attributes['delivery_person'] ?: null,
                'status' => $nextStatus,
                'notes' => $attributes['notes'] ?: null,
                'delivered_date' => $isDelivered ? today() : $lockedDelivery->delivered_date,
            ];
            $lockedDelivery->update($deliveryData);

            $order->update(['order_status' => $this->orderStatus($nextStatus), 'delivery_date' => $deliveryData['delivery_date']]);
            if ($isStatusChange) {
                $notificationData[] = ['status' => $nextStatus, 'order_id' => $order->id];
            }
            if ($isDelivered) {
                $earnedLedger = $this->points->awardCompletedOrder($order->fresh());
                if ($earnedLedger instanceof PointsLedger) {
                    $notificationData[] = ['points_ledger_id' => $earnedLedger->id];
                }
            }

            return $lockedDelivery->fresh(['order.riceProduct', 'customer', 'deliveryArea']);
        });

        foreach ($notificationData as $notification) {
            if (isset($notification['status'])) {
                $this->notifications->deliveryStatus(
                    Order::query()->with('customer')->findOrFail($notification['order_id']),
                    $notification['status'],
                );
            }

            if (isset($notification['points_ledger_id'])) {
                $this->notifications->pointsEarned(
                    PointsLedger::query()->with('order')->findOrFail($notification['points_ledger_id']),
                );
            }
        }

        return $updatedDelivery;
    }

    /** @return list<string> */
    public function allowedStatuses(Delivery $delivery): array
    {
        return match ($delivery->status) {
            'pending' => ['pending', 'scheduled', 'cancelled'],
            'scheduled' => ['scheduled', 'preparing', 'cancelled'],
            'preparing' => ['preparing', 'out_for_delivery', 'cancelled'],
            'out_for_delivery' => ['out_for_delivery', 'delivered', 'failed', 'cancelled'],
            'failed' => ['failed', 'scheduled', 'cancelled'],
            'delivered' => ['delivered'],
            'cancelled' => ['cancelled'],
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
        $first = round((float) $order->final_amount / 2, 2);
        $second = round((float) $order->final_amount - $first, 2);
        $order->pautangInstallments()->createMany([
            ['installment_number' => 1, 'amount_due' => number_format($first, 2, '.', ''), 'due_date' => today()->addDays(15), 'amount_paid' => '0.00', 'remaining_balance' => number_format($first, 2, '.', ''), 'status' => 'pending'],
            ['installment_number' => 2, 'amount_due' => number_format($second, 2, '.', ''), 'due_date' => today()->addDays(30), 'amount_paid' => '0.00', 'remaining_balance' => number_format($second, 2, '.', ''), 'status' => 'pending'],
        ]);
    }
}
