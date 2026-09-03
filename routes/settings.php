<?php

use App\Modules\Settings\Http\Controllers\PasswordController;
use App\Modules\Settings\Http\Controllers\ProfileController;
use App\Modules\Settings\Http\Controllers\SystemSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('settings', fn () => request()->user()?->is_admin
        ? to_route('system-settings.edit')
        : to_route('profile.edit'));

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');
    Route::get('settings/system', [SystemSettingController::class, 'edit'])->name('system-settings.edit');
    Route::put('settings/system', [SystemSettingController::class, 'update'])->name('system-settings.update');
});
