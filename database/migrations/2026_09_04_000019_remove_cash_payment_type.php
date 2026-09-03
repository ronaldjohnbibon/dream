<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('system_settings')->find(1);
        $installmentCount = max(1, (int) ($settings->pautang_installments ?? 2));
        $termDays = max(1, (int) ($settings->pautang_payment_term_days ?? 30));
        $today = today();

        DB::transaction(function () use ($installmentCount, $termDays, $today): void {
            $cashOrders = DB::table('orders')->where('payment_type', 'cash')->orderBy('id')->get();

            foreach ($cashOrders as $order) {
                DB::table('orders')->where('id', $order->id)->update(['payment_type' => 'pautang']);

                if ((float) $order->remaining_balance <= 0 || $order->order_status === 'cancelled'
                    || DB::table('pautang_installments')->where('order_id', $order->id)->exists()) {
                    continue;
                }

                $pendingPayment = DB::table('gcash_payments')
                    ->where('order_id', $order->id)
                    ->where('status', 'pending_verification')
                    ->orderBy('id')
                    ->first();
                $count = $pendingPayment ? 1 : $installmentCount;
                $remaining = round((float) $order->remaining_balance, 2);

                for ($number = 1; $number <= $count; $number++) {
                    $amount = $number === $count ? $remaining : round((float) $order->remaining_balance / $count, 2);
                    $remaining = round($remaining - $amount, 2);
                    $installmentId = DB::table('pautang_installments')->insertGetId([
                        'order_id' => $order->id,
                        'installment_number' => $number,
                        'amount_due' => number_format($amount, 2, '.', ''),
                        'due_date' => $today->copy()->addDays((int) floor(($termDays * $number) / $count))->toDateString(),
                        'amount_paid' => '0.00',
                        'remaining_balance' => number_format($amount, 2, '.', ''),
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($number === 1 && $pendingPayment) {
                        DB::table('gcash_payments')->where('id', $pendingPayment->id)->update(['pautang_installment_id' => $installmentId]);
                    }
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_type', ['pautang'])->default('pautang')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_type', 20)->change();
        });
    }
};
