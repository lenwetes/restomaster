<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // En PostgreSQL / SQLite los partial unique indexes condicionales deben crearse con sintaxis SQL nativa
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE turnos_caja DROP CONSTRAINT IF EXISTS turnos_caja_caja_id_abierto_unique');
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
            DB::statement("CREATE UNIQUE INDEX turnos_caja_caja_id_abierto_unique ON turnos_caja (caja_id) WHERE estado = 'abierto'");
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
            DB::statement("CREATE UNIQUE INDEX turnos_caja_caja_id_abierto_unique ON turnos_caja (caja_id) WHERE estado = 'abierto'");
        } else {
            Schema::table('turnos_caja', function (Blueprint $table) {
                $table->unique('caja_id', 'turnos_caja_caja_id_abierto_unique');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE turnos_caja DROP CONSTRAINT IF EXISTS turnos_caja_caja_id_abierto_unique');
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
        } else {
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['monto_pago_efectivo', 'monto_pago_tarjeta']);
        });
    }
};
