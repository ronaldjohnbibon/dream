<?php

namespace App\Modules\Orders\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GcashPayment extends Model
{
    use HasFactory;

    public const STATUSES = ['pending_verification', 'approved', 'rejected'];

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'pautang_installment_id',
        'customer_id',
        'amount',
        'reference_number',
        'approved_reference_number',
        'screenshot_path',
        'payment_date',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'payment_date' => 'date',
            'reviewed_at'  => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
