<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FK e índice en cuentas_por_pagar.insumo_id.
     * Nota: En 2026_09_10_200000_harden_db_integrity_audit_fixes ya se configuró
     * la clave foránea e índice; esta migración se asegura de ser 100% idempotente
     * en PostgreSQL y SQLite para evitar colisiones de índices ya existentes.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS cuentas_por_pagar_insumo_id_index ON cuentas_por_pagar (insumo_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op para preservar el índice configurado en 2026_09_10_200000
    }
};
