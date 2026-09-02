<?php

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryArea extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'delivery_fee', 'is_active'];

    protected function casts(): array
    {
        return ['delivery_fee' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
