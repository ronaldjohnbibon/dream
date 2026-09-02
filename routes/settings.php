<?php

use App\Modules\Settings\Http\Controllers\GcashSettingController;
use App\Modules\Settings\Http\Controllers\PasswordController;
use App\Modules\Settings\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');
    Route::get('settings/gcash', [GcashSettingController::class, 'edit'])->name('gcash-settings.edit');
    Route::put('settings/gcash', [GcashSettingController::class, 'update'])->name('gcash-settings.update');
});
