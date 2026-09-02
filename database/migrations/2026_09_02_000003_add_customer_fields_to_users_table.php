<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_number')->nullable()->after('email');
            $table->text('complete_address')->nullable()->after('mobile_number');
            $table->string('delivery_area')->nullable()->after('complete_address');
            $table->string('account_status')->default('good_standing')->index()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status']);
            $table->dropColumn(['mobile_number', 'complete_address', 'delivery_area', 'account_status']);
        });
    }
};
