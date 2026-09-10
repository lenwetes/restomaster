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
        Schema::table('pedidos', function (Blueprint $table) {
            $table->index(['mesa_id', 'estado']);
            $table->index(['estado', 'created_at']);
            $table->index(['tipo', 'estado_delivery']);
            $table->index('turno_caja_id');
        });

        Schema::table('items_pedido', function (Blueprint $table) {
            $table->index(['estado_cocina', 'area_cocina']);
            $table->index(['pedido_id', 'inventario_descontado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items_pedido', function (Blueprint $table) {
            $table->dropIndex(['estado_cocina', 'area_cocina']);
            $table->dropIndex(['pedido_id', 'inventario_descontado']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['mesa_id', 'estado']);
            $table->dropIndex(['estado', 'created_at']);
            $table->dropIndex(['tipo', 'estado_delivery']);
            $table->dropIndex(['turno_caja_id']);
        });
    }
};
