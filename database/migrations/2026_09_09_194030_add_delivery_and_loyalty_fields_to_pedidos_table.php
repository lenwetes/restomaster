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
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('direccion_id')->nullable()->constrained('direcciones_cliente')->nullOnDelete();
            $table->foreignId('repartidor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_delivery')->nullable(); // 'pendiente', 'asignado', 'en_ruta', 'entregado', 'fallido'
            $table->string('canal_origen')->default('pos'); // 'pos', 'web', 'whatsapp', 'telefono', 'rappi'
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->integer('puntos_ganados')->default(0);
            $table->integer('puntos_canjeados')->default(0);
            $table->decimal('descuento_puntos', 10, 2)->default(0);
            $table->boolean('recaudo_liquidado')->default(false);
            $table->timestamp('hora_despacho')->nullable();
            $table->timestamp('hora_entrega')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropForeign(['direccion_id']);
            $table->dropForeign(['repartidor_id']);
            $table->dropColumn([
                'cliente_id',
                'direccion_id',
                'repartidor_id',
                'estado_delivery',
                'canal_origen',
                'costo_envio',
                'puntos_ganados',
                'puntos_canjeados',
                'descuento_puntos',
                'recaudo_liquidado',
                'hora_despacho',
                'hora_entrega',
            ]);
        });
    }
};
