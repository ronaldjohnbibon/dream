<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('webpush.database_connection'));
        if (in_array($schema->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $schema->table(config('webpush.table_name'), function (Blueprint $table): void {
                $table->string('endpoint', 1024)->charset('ascii')->collation('ascii_bin')->change();
            });
        }
    }

    public function down(): void
    {
        // Keep case-sensitive comparison: reverting could merge distinct endpoints.
    }
};
