<?php

use App\Http\Controllers\DashboardController;
use App\Modules\Inventory\Http\Controllers\RiceProductController;
use App\Modules\Inventory\Http\Controllers\StockMovementController;
use App\Modules\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? to_route('dashboard')
        : to_route('login');
})->name('home');

Route::get('dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('users', UserController::class);

    Route::resource('rice-products', RiceProductController::class)
        ->except('destroy')
        ->parameters(['rice-products' => 'riceProduct']);
    Route::patch('rice-products/{riceProduct}/status', [RiceProductController::class, 'updateStatus'])->name('rice-products.status');
    Route::get('rice-products/{riceProduct}/stock', [RiceProductController::class, 'createStockEntry'])->name('rice-products.stock.create');
    Route::post('rice-products/{riceProduct}/stock', [RiceProductController::class, 'storeStockEntry'])->name('rice-products.stock.store');
    Route::get('inventory-movements', [StockMovementController::class, 'index'])->name('inventory-movements.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
