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
            $table->foreignId('rice_product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('type', 30)->index();
            $table->unsignedInteger('previous_stock');
            $table->unsignedInteger('new_stock');
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
