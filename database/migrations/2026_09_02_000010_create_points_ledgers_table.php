<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pautang_installment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 50)->index();
            $table->integer('points');
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('description');
            $table->date('transaction_date')->index();
            $table->timestamps();

            $table->index(['customer_id', 'transaction_date'], 'points_ledgers_customer_date_index');
            $table->unique(['type', 'source_type', 'source_id'], 'points_ledgers_unique_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_ledgers');
    }
};
