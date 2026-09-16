<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'monto_pago_efectivo')) {
                $table->decimal('monto_pago_efectivo', 12, 2)->nullable()->after('monto_pagado');
            }
            if (! Schema::hasColumn('pedidos', 'monto_pago_tarjeta')) {
                $table->decimal('monto_pago_tarjeta', 12, 2)->nullable()->after('monto_pago_efectivo');
            }
        });

        Schema::table('turnos_caja', function (Blueprint $table) {
            $table->unique('caja_id', 'turnos_caja_caja_id_abierto_unique')->where('estado', 'abierto');
        });
    }

    public function down(): void
    {
        Schema::table('turnos_caja', function (Blueprint $table) {
            $table->dropUnique('turnos_caja_caja_id_abierto_unique');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['monto_pago_efectivo', 'monto_pago_tarjeta']);
        });
    }
};
