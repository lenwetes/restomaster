<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las 6 tablas que la migración 2026_09_17_210000 convirtió con AT TIME ZONE 'UTC',
     * cuando Laravel escribió los valores en America/Bogota (UTC-5).
     */
    protected array $tablas = [
        'pedidos' => ['pagado_en', 'hora_despacho', 'hora_entrega', 'created_at', 'updated_at'],
        'turnos_caja' => ['apertura_en', 'cierre_en', 'created_at', 'updated_at'],
        'movimientos_caja' => ['created_at', 'updated_at'],
        'asientos_contables' => ['created_at', 'updated_at'],
        'items_pedido' => ['iniciado_en', 'listo_en', 'created_at', 'updated_at'],
        'auditorias' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tablas as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($columnas as $columna) {
                if (Schema::hasColumn($tabla, $columna)) {
                    $col = '"'.$tabla.'"."'.$columna.'"';
                    DB::statement("UPDATE $tabla SET {$columna} = ({$col}) + INTERVAL '5 hours' WHERE {$columna} IS NOT NULL");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tablas as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($columnas as $columna) {
                if (Schema::hasColumn($tabla, $columna)) {
                    $col = '"'.$tabla.'"."'.$columna.'"';
                    DB::statement("UPDATE $tabla SET {$columna} = ({$col}) - INTERVAL '5 hours' WHERE {$columna} IS NOT NULL");
                }
            }
        }
    }
};
