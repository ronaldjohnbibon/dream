<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Modules\Settings\Models\SystemSetting;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points_settings', function (Blueprint $table) {
            $table->unsignedInteger('on_time_payment_points')->default(10)->change();
        });

        $bonusWasConfigured = DB::table('activity_logs')
            ->where('module', 'settings')
            ->where('action', 'changed')
            ->where('related_type', SystemSetting::class)
            ->where('related_id', 1)
            ->where('description', 'like', '%On-time payment points%')
            ->exists();

        if (! $bonusWasConfigured) {
            DB::table('points_settings')
                ->where('on_time_payment_points', 5)
                ->update(['on_time_payment_points' => 10]);
        }
    }

    public function down(): void
    {
        Schema::table('points_settings', function (Blueprint $table) {
            $table->unsignedInteger('on_time_payment_points')->default(5)->change();
        });
    }
};
