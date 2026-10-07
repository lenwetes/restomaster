<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('compras') && ! Schema::hasColumn('compras', 'soporte_factura')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->string('soporte_factura', 500)->nullable()->after('estado');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('compras') && Schema::hasColumn('compras', 'soporte_factura')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropColumn('soporte_factura');
            });
        }
    }
};
