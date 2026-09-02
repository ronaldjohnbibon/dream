<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use App\Modules\Notifications\Console\SendInstallmentReminders;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

app(Schedule::class)
    ->command(SendInstallmentReminders::class)
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
