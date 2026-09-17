<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns grouped by table to be converted to timestamptz in PostgreSQL.
     */
    protected array $targets = [
        'pedidos' => ['pagado_en', 'hora_despacho', 'hora_entrega', 'created_at', 'updated_at'],
        'turnos_caja' => ['apertura_en', 'cierre_en', 'created_at', 'updated_at'],
        'movimientos_caja' => ['created_at', 'updated_at'],
        'asientos_contables' => ['created_at', 'updated_at'],
        'items_pedido' => ['iniciado_en', 'listo_en', 'created_at', 'updated_at'],
        'auditorias' => ['created_at', 'updated_at'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" TYPE timestamptz USING \"{$column}\" AT TIME ZONE 'UTC'");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" TYPE timestamp without time zone USING \"{$column}\" AT TIME ZONE 'UTC'");
                }
            }
        }
    }
};
