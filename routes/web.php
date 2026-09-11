<?php

use App\Http\Controllers\DashboardController;
use App\Modules\Delivery\Http\Controllers\DeliveryAreaController;
use App\Modules\Delivery\Http\Controllers\DeliveryController;
use App\Modules\Inventory\Http\Controllers\RiceProductController;
use App\Modules\Inventory\Http\Controllers\StockMovementController;
use App\Modules\Logs\Http\Controllers\ActivityLogController;
use App\Modules\Logs\Http\Controllers\SystemLogController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Notifications\Http\Controllers\PushSubscriptionController;
use App\Modules\Notifications\Http\Controllers\TestWebPushNotificationController;
use App\Modules\Orders\Http\Controllers\GcashPaymentController;
use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Orders\Http\Controllers\PautangController;
use App\Modules\Points\Http\Controllers\PointsController;
use App\Modules\Reports\Http\Controllers\ReportsController;
use App\Modules\Users\Http\Controllers\CustomerController;
use App\Modules\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? to_route('dashboard')
        : to_route('login');
})->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'throttle:dashboard'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('users', UserController::class)->middlewareFor('index', 'throttle:search');

    Route::resource('customers', CustomerController::class)
        ->except('destroy')
        ->middlewareFor('index', 'throttle:search');
    Route::patch('customers/{customer}/suspend', [CustomerController::class, 'suspend'])->name('customers.suspend');
    Route::patch('customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])->name('customers.reactivate');
    Route::get('customers/{customer}/points', [PointsController::class, 'show'])->name('customers.points.show');
    Route::post('customers/{customer}/points/adjustments', [PointsController::class, 'adjust'])
        ->middleware('throttle:admin-financial-action')
        ->name('customers.points.adjustments.store');

    Route::resource('rice-products', RiceProductController::class)
        ->except('destroy')
        ->parameters(['rice-products' => 'riceProduct'])
        ->middlewareFor('index', 'throttle:search');
    Route::patch('rice-products/{riceProduct}/status', [RiceProductController::class, 'updateStatus'])->name('rice-products.status');
    Route::get('rice-products/{riceProduct}/stock', [RiceProductController::class, 'createStockEntry'])->name('rice-products.stock.create');
    Route::post('rice-products/{riceProduct}/stock', [RiceProductController::class, 'storeStockEntry'])
        ->middleware('throttle:admin-financial-action')
        ->name('rice-products.stock.store');
    Route::get('inventory-movements', [StockMovementController::class, 'index'])
        ->middleware('throttle:search')
        ->name('inventory-movements.index');

    Route::resource('orders', OrderController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->middlewareFor('index', 'throttle:search')
        ->middlewareFor('store', 'throttle:order-submission');

    Route::resource('deliveries', DeliveryController::class)->only(['index', 'show', 'update']);
    Route::resource('delivery-areas', DeliveryAreaController::class)->only(['index', 'store', 'update']);
    Route::patch('delivery-areas/{deliveryArea}/status', [DeliveryAreaController::class, 'updateStatus'])->name('delivery-areas.status');

    Route::get('orders/{order}/gcash-payments/create', [GcashPaymentController::class, 'create'])->name('gcash-payments.create');
    Route::post('orders/{order}/gcash-payments', [GcashPaymentController::class, 'store'])
        ->middleware('throttle:payment-submission')
        ->name('gcash-payments.store');
    Route::get('gcash-payments', [GcashPaymentController::class, 'index'])->name('gcash-payments.index');
    Route::get('gcash-payments/{gcashPayment}/screenshot', [GcashPaymentController::class, 'screenshot'])
        ->middleware('throttle:payment-screenshot')
        ->name('gcash-payments.screenshot');
    Route::get('gcash-payments/{gcashPayment}', [GcashPaymentController::class, 'show'])->name('gcash-payments.show');
    Route::patch('gcash-payments/{gcashPayment}/approve', [GcashPaymentController::class, 'approve'])
        ->middleware('throttle:admin-financial-action')
        ->name('gcash-payments.approve');
    Route::patch('gcash-payments/{gcashPayment}/reject', [GcashPaymentController::class, 'reject'])
        ->middleware('throttle:admin-financial-action')
        ->name('gcash-payments.reject');

    Route::get('pautang', [PautangController::class, 'index'])->name('pautang.index');
    Route::get('reports', [ReportsController::class, 'index'])
        ->middleware('throttle:reports')
        ->name('reports.index');
    Route::get('points', [PointsController::class, 'mine'])->name('points.show');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey'])
        ->name('push.vapid-public-key');
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push-subscriptions.destroy');
    Route::post('push/test-notification', TestWebPushNotificationController::class)
        ->middleware('throttle:3,1')
        ->name('push.test');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('throttle:search')
        ->name('activity-logs.index');
    Route::get('system-logs', [SystemLogController::class, 'index'])
        ->middleware('throttle:search')
        ->name('system-logs.index');
    Route::get('system-logs/{systemLog}', [SystemLogController::class, 'show'])
        ->middleware('throttle:search')
        ->name('system-logs.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
