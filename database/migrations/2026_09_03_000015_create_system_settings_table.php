<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name')->default('Business Starter');
            $table->string('logo_path')->nullable();
            $table->string('contact_number')->nullable();
            $table->text('address')->nullable();
            $table->boolean('pautang_enabled')->default(true);
            $table->unsignedTinyInteger('pautang_installments')->default(2);
            $table->unsignedSmallInteger('pautang_payment_term_days')->default(30);
            $table->unsignedTinyInteger('pautang_max_active')->default(1);
            $table->unsignedTinyInteger('pautang_max_sacks')->default(1);
            $table->unsignedTinyInteger('pautang_grace_period_days')->default(3);
            $table->json('free_delivery_area_ids')->nullable();
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->timestamps();
        });

        $now = now();
        $deliveryAreaId = DB::table('delivery_areas')->insertGetId([
            'name'         => 'Daang Hari',
            'delivery_fee' => 0,
            'is_active'    => true,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        DB::table('system_settings')->insert([
            'id'                        => 1,
            'business_name'             => 'Business Starter',
            'pautang_enabled'           => true,
            'pautang_installments'      => 2,
            'pautang_payment_term_days' => 30,
            'pautang_max_active'        => 1,
            'pautang_max_sacks'         => 1,
            'pautang_grace_period_days' => 3,
            'free_delivery_area_ids'    => json_encode([$deliveryAreaId]),
            'low_stock_threshold'       => 5,
            'created_at'                => $now,
            'updated_at'                => $now,
        ]);
    }

    public function down(): void
    {
        Schema::drop('system_settings');
    }
};
