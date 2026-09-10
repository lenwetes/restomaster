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
        // 1. SoftDeletes en catálogo para proteger histórico contable (Fix H10)
        if (Schema::hasTable('productos') && ! Schema::hasColumn('productos', 'deleted_at')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('insumos') && ! Schema::hasColumn('insumos', 'deleted_at')) {
            Schema::table('insumos', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('clientes') && ! Schema::hasColumn('clientes', 'deleted_at')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 2. FK real en cuentas_por_pagar.insumo_id (Fix H9)
        if (Schema::hasTable('cuentas_por_pagar') && Schema::hasColumn('cuentas_por_pagar', 'insumo_id')) {
            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                $table->foreign('insumo_id')->references('id')->on('insumos')->nullOnDelete();
                $table->index('insumo_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cuentas_por_pagar') && Schema::hasColumn('cuentas_por_pagar', 'insumo_id')) {
            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                $table->dropForeign(['insumo_id']);
                $table->dropIndex(['insumo_id']);
            });
        }

        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'deleted_at')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('insumos') && Schema::hasColumn('insumos', 'deleted_at')) {
            Schema::table('insumos', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'deleted_at')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
