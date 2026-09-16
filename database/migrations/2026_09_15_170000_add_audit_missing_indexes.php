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
            $table->index('cliente_id');
            $table->index('usuario_id');
            $table->index(['canal_origen', 'estado']);
        });

        Schema::table('items_pedido', function (Blueprint $table) {
            $table->index('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items_pedido', function (Blueprint $table) {
            $table->dropIndex(['producto_id']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['cliente_id']);
            $table->dropIndex(['usuario_id']);
            $table->dropIndex(['canal_origen', 'estado']);
        });
    }
};
