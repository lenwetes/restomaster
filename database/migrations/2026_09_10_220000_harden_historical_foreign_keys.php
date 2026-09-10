<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Hardens 13 foreign keys from cascadeOnDelete to restrictOnDelete or nullOnDelete
     * to protect historical data (financial, inventory, printing, audit logs).
     */
    public function up(): void
    {
        // 1. items_pedido.producto_id -> restrictOnDelete (histórico de ventas)
        if (Schema::hasTable('items_pedido')) {
            Schema::table('items_pedido', function (Blueprint $table) {
                $table->dropForeign(['producto_id']);
                $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
            });
        }

        // 2. productos.categoria_id -> nullable + nullOnDelete (catálogo: eliminar categoría no borra platos)
        if (Schema::hasTable('productos')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropForeign(['categoria_id']);
                $table->unsignedBigInteger('categoria_id')->nullable()->change();
                $table->foreign('categoria_id')->references('id')->on('categorias')->nullOnDelete();
            });
        }

        // 3. cajas.sucursal_id -> restrictOnDelete (histórico de cajas)
        if (Schema::hasTable('cajas')) {
            Schema::table('cajas', function (Blueprint $table) {
                $table->dropForeign(['sucursal_id']);
                $table->foreign('sucursal_id')->references('id')->on('sucursales')->restrictOnDelete();
            });
        }

        // 4. turnos_caja.caja_id y user_id -> restrictOnDelete (histórico financiero de turnos)
        if (Schema::hasTable('turnos_caja')) {
            Schema::table('turnos_caja', function (Blueprint $table) {
                $table->dropForeign(['caja_id']);
                $table->dropForeign(['user_id']);
                $table->foreign('caja_id')->references('id')->on('cajas')->restrictOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            });
        }

        // 5. movimientos_caja.turno_caja_id y user_id -> restrictOnDelete (arqueo e ingresos/egresos)
        if (Schema::hasTable('movimientos_caja')) {
            Schema::table('movimientos_caja', function (Blueprint $table) {
                $table->dropForeign(['turno_caja_id']);
                $table->dropForeign(['user_id']);
                $table->foreign('turno_caja_id')->references('id')->on('turnos_caja')->restrictOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            });
        }

        // 6. movimientos_inventario.insumo_id -> restrictOnDelete (kardex histórico)
        if (Schema::hasTable('movimientos_inventario')) {
            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->dropForeign(['insumo_id']);
                $table->foreign('insumo_id')->references('id')->on('insumos')->restrictOnDelete();
            });
        }

        // 7. pagos_cxps.cuenta_por_pagar_id -> restrictOnDelete (histórico de pagos a proveedores)
        if (Schema::hasTable('pagos_cxps')) {
            Schema::table('pagos_cxps', function (Blueprint $table) {
                $table->dropForeign(['cuenta_por_pagar_id']);
                $table->foreign('cuenta_por_pagar_id')->references('id')->on('cuentas_por_pagar')->restrictOnDelete();
            });
        }

        // 8. movimientos_puntos.cliente_id -> restrictOnDelete (histórico de fidelización)
        if (Schema::hasTable('movimientos_puntos')) {
            Schema::table('movimientos_puntos', function (Blueprint $table) {
                $table->dropForeign(['cliente_id']);
                $table->foreign('cliente_id')->references('id')->on('clientes')->restrictOnDelete();
            });
        }

        // 9. reserva_mesa: reserva_id y mesa_id -> restrictOnDelete (histórico de ocupación de mesas)
        if (Schema::hasTable('reserva_mesa')) {
            Schema::table('reserva_mesa', function (Blueprint $table) {
                $table->dropForeign(['reserva_id']);
                $table->dropForeign(['mesa_id']);
                $table->foreign('reserva_id')->references('id')->on('reservas')->restrictOnDelete();
                $table->foreign('mesa_id')->references('id')->on('mesas')->restrictOnDelete();
            });
        }

        // 10. trabajos_impresion.impresora_id -> restrictOnDelete (histórico fiscal de impresiones)
        if (Schema::hasTable('trabajos_impresion')) {
            Schema::table('trabajos_impresion', function (Blueprint $table) {
                $table->dropForeign(['impresora_id']);
                $table->foreign('impresora_id')->references('id')->on('impresoras')->restrictOnDelete();
            });
        }

        // 11. mesas.sucursal_id -> restrictOnDelete (no borrar sucursal si tiene mesas activas)
        if (Schema::hasTable('mesas')) {
            Schema::table('mesas', function (Blueprint $table) {
                $table->dropForeign(['sucursal_id']);
                $table->foreign('sucursal_id')->references('id')->on('sucursales')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mesas')) {
            Schema::table('mesas', function (Blueprint $table) {
                $table->dropForeign(['sucursal_id']);
                $table->foreign('sucursal_id')->references('id')->on('sucursales')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('trabajos_impresion')) {
            Schema::table('trabajos_impresion', function (Blueprint $table) {
                $table->dropForeign(['impresora_id']);
                $table->foreign('impresora_id')->references('id')->on('impresoras')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('reserva_mesa')) {
            Schema::table('reserva_mesa', function (Blueprint $table) {
                $table->dropForeign(['reserva_id']);
                $table->dropForeign(['mesa_id']);
                $table->foreign('reserva_id')->references('id')->on('reservas')->cascadeOnDelete();
                $table->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('movimientos_puntos')) {
            Schema::table('movimientos_puntos', function (Blueprint $table) {
                $table->dropForeign(['cliente_id']);
                $table->foreign('cliente_id')->references('id')->on('clientes')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('pagos_cxps')) {
            Schema::table('pagos_cxps', function (Blueprint $table) {
                $table->dropForeign(['cuenta_por_pagar_id']);
                $table->foreign('cuenta_por_pagar_id')->references('id')->on('cuentas_por_pagar')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('movimientos_inventario')) {
            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->dropForeign(['insumo_id']);
                $table->foreign('insumo_id')->references('id')->on('insumos')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('movimientos_caja')) {
            Schema::table('movimientos_caja', function (Blueprint $table) {
                $table->dropForeign(['turno_caja_id']);
                $table->dropForeign(['user_id']);
                $table->foreign('turno_caja_id')->references('id')->on('turnos_caja')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('turnos_caja')) {
            Schema::table('turnos_caja', function (Blueprint $table) {
                $table->dropForeign(['caja_id']);
                $table->dropForeign(['user_id']);
                $table->foreign('caja_id')->references('id')->on('cajas')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('cajas')) {
            Schema::table('cajas', function (Blueprint $table) {
                $table->dropForeign(['sucursal_id']);
                $table->foreign('sucursal_id')->references('id')->on('sucursales')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('productos')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropForeign(['categoria_id']);
                $table->foreign('categoria_id')->references('id')->on('categorias')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('items_pedido')) {
            Schema::table('items_pedido', function (Blueprint $table) {
                $table->dropForeign(['producto_id']);
                $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
            });
        }
    }
};
