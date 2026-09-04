<?php

namespace App\Modules\Delivery\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'scheduled', 'preparing', 'out_for_delivery', 'delivered', 'failed', 'cancelled'];

    protected $fillable = [
        'order_id', 'customer_id', 'delivery_area_id', 'delivery_area_name', 'delivery_address',
        'delivery_fee', 'delivery_date', 'delivery_person', 'status', 'notes', 'delivered_date',
    ];

    protected function casts(): array
    {
        return ['delivery_fee' => 'decimal:2', 'delivery_date' => 'date', 'delivered_date' => 'date'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DeliveryArea, $this> */
    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class);
    }
}
