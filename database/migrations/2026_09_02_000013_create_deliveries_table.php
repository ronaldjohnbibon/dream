<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delivery_area_id')->constrained()->restrictOnDelete();
            $table->string('delivery_area_name');
            $table->text('delivery_address');
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->date('delivery_date')->nullable()->index();
            $table->string('delivery_person')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->date('delivered_date')->nullable();
            $table->timestamps();
            $table->index(['status', 'delivery_date']);
        });
    }

    public function down(): void
    {
        Schema::drop('deliveries');
    }
};
