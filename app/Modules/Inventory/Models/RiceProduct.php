<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiceProduct extends Model
{
    use HasFactory;

    public const SACK_SIZE = 25;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'brand',
        'description',
        'sack_size',
        'cost_price',
        'selling_price',
        'available_stock',
        'reserved_stock',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sack_size'       => 'decimal:2',
            'cost_price'      => 'decimal:2',
            'selling_price'   => 'decimal:2',
            'available_stock' => 'integer',
            'reserved_stock'  => 'integer',
            'is_active'       => 'boolean',
        ];
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
