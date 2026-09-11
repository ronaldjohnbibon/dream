<?php

namespace App\Providers;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Delivery\Policies\DeliveryAreaPolicy;
use App\Modules\Delivery\Policies\DeliveryPolicy;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Inventory\Policies\RiceProductPolicy;
use App\Modules\Logs\Console\PruneSystemLogs;
use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Notifications\Console\SendInstallmentReminders;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Users\Console\CreateAdmin;
use App\Modules\Users\Models\User;
use App\Modules\Users\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            app(SystemLogger::class)->record(
                type: 'system',
                action: 'push_notification_sent',
                description: 'Web Push notification delivered.',
                module: 'notifications',
                recordId: (int) $event->subscription->id,
                status: 'sent',
                metadata: $this->webPushMetadata($event),
            );
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            Log::warning('Web push provider rejected a notification.', [
                'subscription_id' => $event->subscription->id,
                'status'          => $event->report->getResponse()?->getStatusCode(),
                'expired'         => $event->report->isSubscriptionExpired(),
            ]);

            $metadata = [
                ...$this->webPushMetadata($event),
                'http_status'          => $event->report->getResponse()?->getStatusCode(),
                'error_message'        => 'Push provider delivery failed.',
                'subscription_expired' => $event->report->isSubscriptionExpired(),
            ];
            $systemLogs = app(SystemLogger::class);
            $systemLogs->record(
                type: 'system',
                action: 'push_notification_failed',
                description: 'Web Push notification delivery failed.',
                module: 'notifications',
                recordId: (int) $event->subscription->id,
                status: 'failed',
                metadata: $metadata,
            );
            if ($event->report->isSubscriptionExpired()) {
                $systemLogs->record(
                    type: 'system',
                    action: 'push_subscription_removed',
                    description: 'Expired Web Push subscription removed.',
                    module: 'notifications',
                    recordId: (int) $event->subscription->id,
                    status: 'removed',
                    metadata: [...$this->webPushMetadata($event), 'reason' => 'expired_subscription'],
                );
            }
        });

        RateLimiter::for('password-verification', fn (Request $request) => Limit::perMinute(5)
            ->by('user:'.$request->user()->id));

        RateLimiter::for('order-submission', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by('user:'.$request->user()->id));

        RateLimiter::for('payment-submission', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by('user:'.$request->user()->id));

        RateLimiter::for('payment-screenshot', fn (Request $request) => Limit::perMinute(30)
            ->by('user:'.$request->user()->id));

        RateLimiter::for('admin-financial-action', fn (Request $request) => Limit::perMinute(30)
            ->by('user:'.$request->user()->id));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)
            ->by('user:'.$request->user()->id.'|route:'.$request->route()->getName()));

        RateLimiter::for('dashboard', fn (Request $request) => Limit::perMinute(30)
            ->by('user:'.$request->user()->id));

        RateLimiter::for('reports', fn (Request $request) => Limit::perMinute(10)
            ->by('user:'.$request->user()->id));

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(RiceProduct::class, RiceProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(DeliveryArea::class, DeliveryAreaPolicy::class);

        $this->commands([
            CreateAdmin::class,
            PruneSystemLogs::class,
            SendInstallmentReminders::class,
        ]);
    }

    /** @return array<string, mixed> */
    private function webPushMetadata(NotificationSent|NotificationFailed $event): array
    {
        $message = $event->message->toArray();
        $data    = is_array($message['data'] ?? null) ? $message['data'] : [];

        return array_filter([
            'notification_type' => $data['type']      ?? null,
            'event_key'         => $data['event_key'] ?? null,
            'customer_id'       => $event->subscription->subscribable_id,
            'subscription_id'   => $event->subscription->id,
            'destination_url'   => $data['url'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
