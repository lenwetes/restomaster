<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 1. Índices únicos condicionales para modelos con SoftDeletes (productos e insumos).
     * 2. Soporte de búsquedas difusas con trigramas (pg_trgm) en PostgreSQL para POS y Clientes.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // 1. Productos: Slug único solo para registros no eliminados
            DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_slug_unique');
            DB::statement('DROP INDEX IF EXISTS productos_slug_unique');
            DB::statement('DROP INDEX IF EXISTS productos_slug_active_unique');
            DB::statement('CREATE UNIQUE INDEX productos_slug_active_unique ON productos (slug) WHERE deleted_at IS NULL');

            // 2. Insumos: Código único solo para registros no eliminados
            DB::statement('ALTER TABLE insumos DROP CONSTRAINT IF EXISTS insumos_codigo_unique');
            DB::statement('DROP INDEX IF EXISTS insumos_codigo_unique');
            DB::statement('DROP INDEX IF EXISTS insumos_codigo_active_unique');
            DB::statement('CREATE UNIQUE INDEX insumos_codigo_active_unique ON insumos (codigo) WHERE deleted_at IS NULL');

            // 3. Extensión pg_trgm e índices GIN para acelerar búsquedas ILIKE en POS
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
                DB::statement('CREATE INDEX IF NOT EXISTS clientes_nombre_trgm_idx ON clientes USING GIN (nombre gin_trgm_ops)');
                DB::statement('CREATE INDEX IF NOT EXISTS productos_nombre_trgm_idx ON productos USING GIN (nombre gin_trgm_ops)');
            } catch (Throwable $e) {
                // En caso de que el rol de base de datos no tenga permiso de SUPERUSER para crear extensiones
                report($e);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS productos_slug_active_unique');
            DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_slug_unique UNIQUE (slug)');

            DB::statement('DROP INDEX IF EXISTS insumos_codigo_active_unique');
            DB::statement('ALTER TABLE insumos ADD CONSTRAINT insumos_codigo_unique UNIQUE (codigo)');

            DB::statement('DROP INDEX IF EXISTS clientes_nombre_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS productos_nombre_trgm_idx');
        }
    }
};
