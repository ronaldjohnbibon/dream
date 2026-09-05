<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pautang_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_number');
            $table->decimal('amount_due', 12, 2);
            $table->date('due_date')->index();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('remaining_balance', 12, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->date('paid_date')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::drop('pautang_installments');
    }
};
