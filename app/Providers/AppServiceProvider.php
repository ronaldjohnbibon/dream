<?php

namespace App\Providers;

use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Inventory\Policies\RiceProductPolicy;
use App\Modules\Notifications\Console\SendInstallmentReminders;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Delivery\Policies\DeliveryAreaPolicy;
use App\Modules\Delivery\Policies\DeliveryPolicy;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Users\Console\CreateAdmin;
use App\Modules\Users\Models\User;
use App\Modules\Users\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(RiceProduct::class, RiceProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(DeliveryArea::class, DeliveryAreaPolicy::class);

        $this->commands([
            CreateAdmin::class,
            SendInstallmentReminders::class,
        ]);
    }
}
