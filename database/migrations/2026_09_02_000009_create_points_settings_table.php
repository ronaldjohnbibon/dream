<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('completed_order_points')->default(10);
            $table->unsignedInteger('on_time_payment_points')->default(10);
            $table->decimal('peso_per_point', 12, 2)->default(0.10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_settings');
    }
};
