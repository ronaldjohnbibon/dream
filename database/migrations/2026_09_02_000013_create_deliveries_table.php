<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        if (DB::pretending()) {
            return;
        }

        $now         = now();
        $daangHariId = DB::table('delivery_areas')->insertGetId([
            'name'         => 'Daang Hari',
            'delivery_fee' => 0,
            'is_active'    => true,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $areaIds = ['Daang Hari' => $daangHariId];
        foreach (DB::table('orders')->orderBy('id')->cursor() as $order) {
            $areaName = trim((string) $order->delivery_area) ?: 'Daang Hari';
            if (! isset($areaIds[$areaName])) {
                $areaIds[$areaName] = DB::table('delivery_areas')->insertGetId([
                    'name'         => $areaName,
                    'delivery_fee' => 0,
                    'is_active'    => false,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }

            $status = match ($order->order_status) {
                'confirmed'              => 'scheduled',
                'preparing'              => 'preparing',
                'out_for_delivery'       => 'out_for_delivery',
                'delivered', 'completed' => 'delivered',
                'cancelled'              => 'cancelled',
                default                  => 'pending',
            };

            DB::table('deliveries')->insert([
                'order_id'           => $order->id,
                'customer_id'        => $order->customer_id,
                'delivery_area_id'   => $areaIds[$areaName],
                'delivery_area_name' => $areaName,
                'delivery_address'   => $order->delivery_address,
                'delivery_fee'       => $order->delivery_fee,
                'delivery_date'      => $order->delivery_date,
                'delivery_person'    => null,
                'status'             => $status,
                'notes'              => $order->notes,
                'delivered_date'     => in_array($status, ['delivered'], true) ? substr((string) $order->updated_at, 0, 10) : null,
                'created_at'         => $order->created_at,
                'updated_at'         => $order->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
