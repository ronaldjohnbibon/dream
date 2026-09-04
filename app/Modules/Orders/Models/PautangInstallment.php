<?php

namespace App\Modules\Orders\Models;

use App\Modules\Points\Models\PointsLedger;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PautangInstallment extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'partially_paid', 'paid', 'overdue'];

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'installment_number',
        'amount_due',
        'due_date',
        'amount_paid',
        'remaining_balance',
        'status',
        'paid_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_due'        => 'decimal:2',
            'due_date'          => 'date',
            'amount_paid'       => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'paid_date'         => 'date',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function currentStatus(): string
    {
        if ((float) $this->remaining_balance <= 0) {
            return 'paid';
        }

        if ($this->due_date->copy()->addDays(SystemSetting::current()->pautang_grace_period_days)->isBefore(today())) {
            return 'overdue';
        }

        return (float) $this->amount_paid > 0 ? 'partially_paid' : 'pending';
    }
}
