<?php

namespace App\Modules\Orders\Models;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const PAYMENT_TYPE = 'pautang';

    public const STATUSES = [
        'pending',
        'confirmed',
        'preparing',
        'out_for_delivery',
        'delivered',
        'completed',
        'cancelled',
    ];

    public const PAYMENT_STATUSES = ['unpaid', 'partially_paid', 'paid', 'overdue'];

    /** @var list<string> */
    protected $fillable = [
        'idempotency_key',
        'order_number',
        'customer_id',
        'rice_product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'points_used',
        'points_discount',
        'delivery_fee',
        'final_amount',
        'amount_paid',
        'remaining_balance',
        'payment_type',
        'delivery_address',
        'delivery_area',
        'order_date',
        'delivery_date',
        'order_status',
        'payment_status',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'          => 'integer',
            'unit_price'        => 'decimal:2',
            'subtotal'          => 'decimal:2',
            'points_used'       => 'integer',
            'points_discount'   => 'decimal:2',
            'delivery_fee'      => 'decimal:2',
            'final_amount'      => 'decimal:2',
            'amount_paid'       => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'order_date'        => 'date',
            'delivery_date'     => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<RiceProduct, $this> */
    public function riceProduct(): BelongsTo
    {
        return $this->belongsTo(RiceProduct::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<PautangInstallment, $this> */
    public function pautangInstallments(): HasMany
    {
        return $this->hasMany(PautangInstallment::class)->orderBy('installment_number');
    }

    /** @return HasMany<GcashPayment, $this> */
    public function gcashPayments(): HasMany
    {
        return $this->hasMany(GcashPayment::class);
    }

    /** @return HasMany<PointsLedger, $this> */
    public function pointsLedgers(): HasMany
    {
        return $this->hasMany(PointsLedger::class);
    }

    /** @return HasOne<Delivery, $this> */
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
