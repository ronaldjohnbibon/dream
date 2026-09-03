<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gcash_settings', function (Blueprint $table) {
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
        });

        Schema::table('points_settings', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('minimum_redemption')->default(1);
            $table->unsignedInteger('maximum_points_usable')->default(1000);
        });
    }

    public function down(): void
    {
        Schema::table('points_settings', function (Blueprint $table) {
            $table->dropColumn(['is_enabled', 'minimum_redemption', 'maximum_points_usable']);
        });

        Schema::table('gcash_settings', function (Blueprint $table) {
            $table->dropColumn(['account_name', 'account_number']);
        });
    }
};
