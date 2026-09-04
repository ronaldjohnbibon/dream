<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->unique('orders_idempotency_key_unique');
        });

        Schema::table('gcash_payments', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->unique('gcash_payments_idempotency_key_unique');
            $table->unique('reference_number', 'gcash_payments_reference_number_unique');
        });

        Schema::table('points_ledgers', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->unique('points_ledgers_idempotency_key_unique');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->unique('stock_movements_idempotency_key_unique');
            $table->unique(['rice_product_id', 'order_id', 'type'], 'stock_movements_order_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique('stock_movements_idempotency_key_unique');
            $table->dropUnique('stock_movements_order_type_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('points_ledgers', function (Blueprint $table) {
            $table->dropUnique('points_ledgers_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('gcash_payments', function (Blueprint $table) {
            $table->dropUnique('gcash_payments_idempotency_key_unique');
            $table->dropUnique('gcash_payments_reference_number_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
