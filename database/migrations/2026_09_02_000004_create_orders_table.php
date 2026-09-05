<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->nullable()->unique('orders_idempotency_key_unique');
            $table->string('order_number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('rice_product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->unsignedInteger('points_used')->default(0);
            $table->decimal('points_discount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('remaining_balance', 12, 2)->default(0);
            $table->enum('payment_type', ['pautang'])->default('pautang')->index();
            $table->text('delivery_address');
            $table->string('delivery_area');
            $table->date('order_date')->index();
            $table->date('delivery_date')->nullable();
            $table->string('order_status', 30)->default('pending')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'payment_type', 'payment_status', 'order_status'], 'orders_customer_payment_state_index');
        });
    }

    public function down(): void
    {
        Schema::drop('orders');
    }
};
