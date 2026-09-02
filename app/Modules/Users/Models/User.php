<?php

namespace App\Modules\Users\Models;

use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Points\Models\PointsLedger;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'complete_address',
        'delivery_area',
        'password',
        'is_admin',
        'account_status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'customer_id');
    }

    /** @return HasMany<GcashPayment, $this> */
    public function gcashPayments(): HasMany
    {
        return $this->hasMany(GcashPayment::class, 'customer_id');
    }

    /** @return HasMany<PointsLedger, $this> */
    public function pointsLedgers(): HasMany
    {
        return $this->hasMany(PointsLedger::class, 'customer_id');
    }
}
