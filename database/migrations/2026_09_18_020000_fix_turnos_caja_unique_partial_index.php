<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Eliminar cualquier restricción de tabla rígida previa (UNIQUE (caja_id))
            DB::statement('ALTER TABLE turnos_caja DROP CONSTRAINT IF EXISTS turnos_caja_caja_id_abierto_unique');
            // Eliminar cualquier índice previo
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
            // Crear el índice único parcial que solo aplica a turnos con estado 'abierto'
            DB::statement("CREATE UNIQUE INDEX turnos_caja_caja_id_abierto_unique ON turnos_caja (caja_id) WHERE estado = 'abierto'");
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
            DB::statement("CREATE UNIQUE INDEX turnos_caja_caja_id_abierto_unique ON turnos_caja (caja_id) WHERE estado = 'abierto'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE turnos_caja DROP CONSTRAINT IF EXISTS turnos_caja_caja_id_abierto_unique');
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
        } else {
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
        }
    }
};
