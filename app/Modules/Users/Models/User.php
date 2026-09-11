<?php

namespace App\Modules\Users\Models;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Notifications\PushSubscriptionValidator;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Points\Models\PointsLedger;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

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

    public function routeNotificationForWebPush(): Collection
    {
        // Always query again: a queued notification may run after device logout.
        return $this->pushSubscriptions()->get()->filter(function ($subscription): bool {
            $valid = validator([
                'endpoint' => $subscription->endpoint,
                'keys'     => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token],
            ], PushSubscriptionValidator::rules())->passes();

            if (! $valid) {
                Log::warning('Ignoring invalid web push subscription.', ['subscription_id' => $subscription->id]);
                $systemLogs = app(SystemLogger::class);
                $metadata   = [
                    'customer_id'     => $this->id,
                    'subscription_id' => $subscription->id,
                    'reason'          => 'invalid_subscription',
                ];
                $systemLogs->record(
                    type: 'system',
                    action: 'push_subscription_invalid',
                    description: 'Invalid Web Push subscription detected.',
                    module: 'notifications',
                    recordId: (int) $subscription->id,
                    status: 'invalid',
                    metadata: $metadata,
                );

                try {
                    $subscription->delete();
                    $systemLogs->record(
                        type: 'system',
                        action: 'push_subscription_removed',
                        description: 'Invalid Web Push subscription removed.',
                        module: 'notifications',
                        recordId: (int) $subscription->id,
                        status: 'removed',
                        metadata: $metadata,
                    );
                } catch (\Throwable $exception) {
                    Log::warning('Unable to remove invalid web push subscription.', [
                        'subscription_id' => $subscription->id,
                        'exception_class' => $exception::class,
                    ]);
                }
            }

            return $valid;
        });
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
