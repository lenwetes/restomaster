<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar cantidad_devuelta a items_pedido si no existe
        if (! Schema::hasColumn('items_pedido', 'cantidad_devuelta')) {
            Schema::table('items_pedido', function (Blueprint $table) {
                $table->integer('cantidad_devuelta')->default(0)->after('cantidad');
            });
        }

        // 2. Crear tabla pedido_devoluciones para auditoría y trazabilidad
        Schema::create('pedido_devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('item_pedido_id')->nullable()->constrained('items_pedido')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->integer('cantidad')->default(1);
            $table->decimal('monto_devuelto', 12, 2);
            $table->string('motivo', 255);
            $table->string('metodo_reembolso', 50)->default('efectivo');
            $table->foreignId('turno_caja_id')->nullable()->constrained('turnos_caja')->nullOnDelete();
            $table->foreignId('movimiento_caja_id')->nullable()->constrained('movimientos_caja')->nullOnDelete();
            $table->foreignId('asiento_contable_id')->nullable()->constrained('asientos_contables')->nullOnDelete();
            $table->string('autorizado_por', 150);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['pedido_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_devoluciones');

        if (Schema::hasColumn('items_pedido', 'cantidad_devuelta')) {
            Schema::table('items_pedido', function (Blueprint $table) {
                $table->dropColumn('cantidad_devuelta');
            });
        }
    }
};
