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
        if (Schema::hasTable('cuentas_por_pagar') && ! Schema::hasColumn('cuentas_por_pagar', 'numero_factura')) {
            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                $table->string('numero_factura', 50)->nullable()->after('proveedor_nit');
                $table->index('numero_factura');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cuentas_por_pagar') && Schema::hasColumn('cuentas_por_pagar', 'numero_factura')) {
            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                $table->dropIndex(['numero_factura']);
                $table->dropColumn('numero_factura');
            });
        }
    }
};
