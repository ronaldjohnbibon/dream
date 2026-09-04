<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('final_amount');
            $table->decimal('remaining_balance', 12, 2)->default(0)->after('amount_paid');
        });

        DB::table('orders')->update(['remaining_balance' => DB::raw('final_amount')]);

        $pautangOrders = DB::table('pautang_installments')
            ->selectRaw('order_id, SUM(amount_paid) as amount_paid, SUM(remaining_balance) as remaining_balance')
            ->groupBy('order_id')
            ->get();

        foreach ($pautangOrders as $pautangOrder) {
            DB::table('orders')->where('id', $pautangOrder->order_id)->update([
                'amount_paid'       => $pautangOrder->amount_paid,
                'remaining_balance' => $pautangOrder->remaining_balance,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'remaining_balance']);
        });
    }
};
