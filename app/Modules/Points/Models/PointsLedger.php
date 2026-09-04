<?php

namespace App\Modules\Points\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsLedger extends Model
{
    use HasFactory;

    public const TYPES = [
        'order_reward',
        'on_time_payment_bonus',
        'redemption',
        'redemption_refund',
        'admin_adjustment',
    ];

    /** @var list<string> */
    protected $fillable = [
        'idempotency_key',
        'customer_id',
        'order_id',
        'pautang_installment_id',
        'type',
        'points',
        'source_type',
        'source_id',
        'description',
        'transaction_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'points'           => 'integer',
            'source_id'        => 'integer',
            'transaction_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<PautangInstallment, $this> */
    public function pautangInstallment(): BelongsTo
    {
        return $this->belongsTo(PautangInstallment::class);
    }
}
