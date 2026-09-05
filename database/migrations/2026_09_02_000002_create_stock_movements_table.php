<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->nullable()->unique('stock_movements_idempotency_key_unique');
            $table->foreignId('rice_product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('type', 30)->index();
            $table->unsignedInteger('previous_stock');
            $table->unsignedInteger('new_stock');
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['rice_product_id', 'order_id', 'type'], 'stock_movements_order_type_unique');
        });
    }

    public function down(): void
    {
        Schema::drop('stock_movements');
    }
};
