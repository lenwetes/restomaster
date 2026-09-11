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
        // 1. direcciones_cliente.cliente_id -> restrictOnDelete (histórico de direcciones de entrega)
        if (Schema::hasTable('direcciones_cliente')) {
            Schema::table('direcciones_cliente', function (Blueprint $table) {
                $table->dropForeign(['cliente_id']);
                $table->foreign('cliente_id')->references('id')->on('clientes')->restrictOnDelete();
            });
        }

        // 2. recetas.producto_id e insumo_id -> restrictOnDelete (escandallo y costos de platos)
        if (Schema::hasTable('recetas')) {
            Schema::table('recetas', function (Blueprint $table) {
                $table->dropForeign(['producto_id']);
                $table->dropForeign(['insumo_id']);
                $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
                $table->foreign('insumo_id')->references('id')->on('insumos')->restrictOnDelete();
            });
        }

        // 3. Índice compuesto pedidos(estado, estado_delivery) para colas y filtros de despacho
        if (Schema::hasTable('pedidos')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->index(['estado', 'estado_delivery']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pedidos')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->dropIndex(['estado', 'estado_delivery']);
            });
        }

        if (Schema::hasTable('recetas')) {
            Schema::table('recetas', function (Blueprint $table) {
                $table->dropForeign(['producto_id']);
                $table->dropForeign(['insumo_id']);
                $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
                $table->foreign('insumo_id')->references('id')->on('insumos')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('direcciones_cliente')) {
            Schema::table('direcciones_cliente', function (Blueprint $table) {
                $table->dropForeign(['cliente_id']);
                $table->foreign('cliente_id')->references('id')->on('clientes')->cascadeOnDelete();
            });
        }
    }
};
