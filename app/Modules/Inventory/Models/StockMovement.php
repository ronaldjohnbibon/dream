<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPES = [
        'stock_in',
        'order',
        'cancellation',
        'adjustment',
        'damaged',
        'returned',
    ];

    /** @var list<string> */
    protected $fillable = [
        'idempotency_key',
        'quantity',
        'type',
        'previous_stock',
        'new_stock',
        'order_id',
        'notes',
        'user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'       => 'integer',
            'previous_stock' => 'integer',
            'new_stock'      => 'integer',
            'order_id'       => 'integer',
        ];
    }

    /** @return BelongsTo<RiceProduct, $this> */
    public function riceProduct(): BelongsTo
    {
        return $this->belongsTo(RiceProduct::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
