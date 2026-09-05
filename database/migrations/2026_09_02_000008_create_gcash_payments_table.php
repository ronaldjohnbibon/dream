<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcash_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->nullable()->unique('gcash_payments_idempotency_key_unique');
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('pautang_installment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reference_number', 100);
            $table->string('approved_reference_number', 100)->nullable()->unique();
            $table->string('screenshot_path');
            $table->date('payment_date');
            $table->string('status', 30)->default('pending_verification')->index();
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['pautang_installment_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->unique('reference_number', 'gcash_payments_reference_number_unique');
        });
    }

    public function down(): void
    {
        Schema::drop('gcash_payments');
    }
};
