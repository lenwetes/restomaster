<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Hardening integral de PostgreSQL:
     * 1. Índices B-Tree en las 29 Foreign Keys huérfanas de índice.
     * 2. Índices compuestos para consultas de alto tráfico (POS táctil y Cocina KDS).
     * 3. Restricciones de unicidad compuestas (mesas por sucursal, cajas por sucursal, slug único).
     * 4. CHECK constraints a nivel de base de datos para prevenir estados financieros negativos o ilógicos.
     * 5. Estandarización de columnas created_at/updated_at a timestamptz.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // =========================================================================
        // 1. ÍNDICES EN TODAS LAS FOREIGN KEYS HUÉRFANAS (PREVENCIÓN DE TABLE SCANS Y DEADLOCKS)
        // =========================================================================
        if ($isPgsql) {
            // users
            DB::statement('CREATE INDEX IF NOT EXISTS users_role_id_idx ON users (role_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS users_sucursal_id_idx ON users (sucursal_id)');

            // mesas
            DB::statement('CREATE INDEX IF NOT EXISTS mesas_sucursal_id_idx ON mesas (sucursal_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS mesas_mesero_id_idx ON mesas (mesero_id)');

            // productos
            DB::statement('CREATE INDEX IF NOT EXISTS productos_categoria_id_idx ON productos (categoria_id)');

            // pedidos
            DB::statement('CREATE INDEX IF NOT EXISTS pedidos_direccion_id_idx ON pedidos (direccion_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS pedidos_repartidor_id_idx ON pedidos (repartidor_id)');

            // cajas
            DB::statement('CREATE INDEX IF NOT EXISTS cajas_sucursal_id_idx ON cajas (sucursal_id)');

            // turnos_caja
            DB::statement('CREATE INDEX IF NOT EXISTS turnos_caja_user_id_idx ON turnos_caja (user_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS turnos_caja_cerrado_por_user_id_idx ON turnos_caja (cerrado_por_user_id)');

            // movimientos_caja
            DB::statement('CREATE INDEX IF NOT EXISTS movimientos_caja_user_id_idx ON movimientos_caja (user_id)');

            // asientos_contables
            DB::statement('CREATE INDEX IF NOT EXISTS asientos_contables_user_id_idx ON asientos_contables (user_id)');

            // recetas
            DB::statement('CREATE INDEX IF NOT EXISTS recetas_insumo_id_idx ON recetas (insumo_id)');

            // movimientos_inventario
            DB::statement('CREATE INDEX IF NOT EXISTS mov_inv_pedido_id_idx ON movimientos_inventario (pedido_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS mov_inv_user_id_idx ON movimientos_inventario (user_id)');

            // auditorias
            DB::statement('CREATE INDEX IF NOT EXISTS auditorias_user_id_idx ON auditorias (user_id)');

            // cuentas_por_pagar
            DB::statement('CREATE INDEX IF NOT EXISTS cxp_user_id_idx ON cuentas_por_pagar (user_id)');

            // pagos_cxps
            DB::statement('CREATE INDEX IF NOT EXISTS pagos_cxps_user_id_idx ON pagos_cxps (user_id)');

            // direcciones_cliente
            DB::statement('CREATE INDEX IF NOT EXISTS dir_cliente_id_idx ON direcciones_cliente (cliente_id)');

            // movimientos_puntos
            DB::statement('CREATE INDEX IF NOT EXISTS mov_puntos_cliente_id_idx ON movimientos_puntos (cliente_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS mov_puntos_pedido_id_idx ON movimientos_puntos (pedido_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS mov_puntos_usuario_id_idx ON movimientos_puntos (usuario_id)');

            // reservas
            DB::statement('CREATE INDEX IF NOT EXISTS reservas_confirmado_por_idx ON reservas (confirmado_por)');
            DB::statement('CREATE INDEX IF NOT EXISTS reservas_created_by_idx ON reservas (created_by)');
            DB::statement('CREATE INDEX IF NOT EXISTS reservas_sucursal_id_idx ON reservas (sucursal_id)');

            // reserva_mesa
            DB::statement('CREATE INDEX IF NOT EXISTS reserva_mesa_mesa_id_idx ON reserva_mesa (mesa_id)');

            // trabajos_impresion
            DB::statement('CREATE INDEX IF NOT EXISTS trab_imp_impresora_id_idx ON trabajos_impresion (impresora_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS trab_imp_reimpreso_por_id_idx ON trabajos_impresion (reimpreso_por_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS trab_imp_turno_caja_id_idx ON trabajos_impresion (turno_caja_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS trab_imp_usuario_id_idx ON trabajos_impresion (usuario_id)');
        }

        // =========================================================================
        // 2. ÍNDICES COMPUESTOS PARA OPERACIÓN EN TIEMPO REAL (POS / KDS)
        // =========================================================================
        if ($isPgsql) {
            DB::statement('CREATE INDEX IF NOT EXISTS mesas_sucursal_estado_idx ON mesas (sucursal_id, estado)');
            DB::statement('CREATE INDEX IF NOT EXISTS mesas_sucursal_zona_idx ON mesas (sucursal_id, zona)');
            DB::statement('CREATE INDEX IF NOT EXISTS pedidos_sucursal_estado_idx ON pedidos (sucursal_id, estado)');
        } else {
            Schema::table('mesas', function (Blueprint $table) {
                $table->index(['sucursal_id', 'estado'], 'mesas_sucursal_estado_idx');
                $table->index(['sucursal_id', 'zona'], 'mesas_sucursal_zona_idx');
            });
            Schema::table('pedidos', function (Blueprint $table) {
                $table->index(['sucursal_id', 'estado'], 'pedidos_sucursal_estado_idx');
            });
        }

        // =========================================================================
        // 3. RESTRICCIONES DE UNICIDAD COMPUESTAS Y CORRECCIÓN DE SLUGS
        // =========================================================================
        if ($isPgsql) {
            // mesas: unicidad de número por sucursal
            DB::statement('ALTER TABLE mesas DROP CONSTRAINT IF EXISTS mesas_sucursal_numero_unique');
            DB::statement('ALTER TABLE mesas ADD CONSTRAINT mesas_sucursal_numero_unique UNIQUE (sucursal_id, numero)');

            // cajas: cambiar unicidad global por unicidad por sucursal
            DB::statement('ALTER TABLE cajas DROP CONSTRAINT IF EXISTS cajas_codigo_unique');
            DB::statement('ALTER TABLE cajas DROP CONSTRAINT IF EXISTS cajas_sucursal_codigo_unique');
            DB::statement('ALTER TABLE cajas ADD CONSTRAINT cajas_sucursal_codigo_unique UNIQUE (sucursal_id, codigo)');

            // productos: slug único
            DB::statement('DROP INDEX IF EXISTS productos_slug_index');
            DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_slug_unique');
            DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_slug_unique UNIQUE (slug)');
        }

        // =========================================================================
        // 4. CHECK CONSTRAINTS EN POSTGRESQL (BLINDAJE FINANCIERO E INVENTARIO)
        // =========================================================================
        if ($isPgsql) {
            // Pedidos
            DB::statement('ALTER TABLE pedidos DROP CONSTRAINT IF EXISTS chk_pedidos_totales_no_negativos');
            DB::statement('ALTER TABLE pedidos ADD CONSTRAINT chk_pedidos_totales_no_negativos CHECK (subtotal >= 0 AND total >= 0 AND descuento >= 0)');

            DB::statement('ALTER TABLE pedidos DROP CONSTRAINT IF EXISTS chk_pedidos_monto_pagado');
            DB::statement('ALTER TABLE pedidos ADD CONSTRAINT chk_pedidos_monto_pagado CHECK (monto_pagado IS NULL OR monto_pagado >= 0)');

            // Items pedido
            DB::statement('ALTER TABLE items_pedido DROP CONSTRAINT IF EXISTS chk_items_pedido_cantidad_positiva');
            DB::statement('ALTER TABLE items_pedido ADD CONSTRAINT chk_items_pedido_cantidad_positiva CHECK (cantidad > 0)');

            DB::statement('ALTER TABLE items_pedido DROP CONSTRAINT IF EXISTS chk_items_pedido_precio_no_negativo');
            DB::statement('ALTER TABLE items_pedido ADD CONSTRAINT chk_items_pedido_precio_no_negativo CHECK (precio_unitario >= 0 AND subtotal >= 0)');

            // Cuentas por pagar
            DB::statement('ALTER TABLE cuentas_por_pagar DROP CONSTRAINT IF EXISTS chk_cxp_saldos');
            DB::statement('ALTER TABLE cuentas_por_pagar ADD CONSTRAINT chk_cxp_saldos CHECK (monto_total > 0 AND saldo_pendiente >= 0 AND saldo_pendiente <= monto_total)');

            // Pagos CxP
            DB::statement('ALTER TABLE pagos_cxps DROP CONSTRAINT IF EXISTS chk_pagos_cxps_monto_positivo');
            DB::statement('ALTER TABLE pagos_cxps ADD CONSTRAINT chk_pagos_cxps_monto_positivo CHECK (monto > 0)');

            // Movimientos de caja
            DB::statement('ALTER TABLE movimientos_caja DROP CONSTRAINT IF EXISTS chk_movimientos_caja_monto_positivo');
            DB::statement('ALTER TABLE movimientos_caja ADD CONSTRAINT chk_movimientos_caja_monto_positivo CHECK (monto > 0)');

            // Turnos de caja
            DB::statement('ALTER TABLE turnos_caja DROP CONSTRAINT IF EXISTS chk_turnos_caja_monto_inicial');
            DB::statement('ALTER TABLE turnos_caja ADD CONSTRAINT chk_turnos_caja_monto_inicial CHECK (monto_inicial >= 0)');

            // Recetas / Escandallos
            DB::statement('ALTER TABLE recetas DROP CONSTRAINT IF EXISTS chk_recetas_cantidad_merma');
            DB::statement('ALTER TABLE recetas ADD CONSTRAINT chk_recetas_cantidad_merma CHECK (cantidad > 0 AND merma_esperada_pct >= 0 AND merma_esperada_pct <= 100)');

            // Insumos
            DB::statement('ALTER TABLE insumos DROP CONSTRAINT IF EXISTS chk_insumos_costo_stock');
            DB::statement('ALTER TABLE insumos ADD CONSTRAINT chk_insumos_costo_stock CHECK (costo_unitario >= 0 AND stock_minimo >= 0)');
        }

        // =========================================================================
        // 5. ESTANDARIZACIÓN GLOBAL DE TIMESTAMPS A TIMESTAMPTZ
        // =========================================================================
        if ($isPgsql) {
            $tablasTimestamps = [
                'clientes', 'direcciones_cliente', 'insumos', 'productos',
                'reservas', 'mesas', 'cajas', 'categoria_insumos',
            ];

            foreach ($tablasTimestamps as $tabla) {
                if (! Schema::hasTable($tabla)) {
                    continue;
                }
                foreach (['created_at', 'updated_at'] as $col) {
                    if (Schema::hasColumn($tabla, $col)) {
                        DB::statement("ALTER TABLE \"{$tabla}\" ALTER COLUMN \"{$col}\" TYPE timestamptz USING \"{$col}\" AT TIME ZONE 'America/Bogota'");
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if (! $isPgsql) {
            return;
        }

        // Drop check constraints
        DB::statement('ALTER TABLE pedidos DROP CONSTRAINT IF EXISTS chk_pedidos_totales_no_negativos');
        DB::statement('ALTER TABLE pedidos DROP CONSTRAINT IF EXISTS chk_pedidos_monto_pagado');
        DB::statement('ALTER TABLE items_pedido DROP CONSTRAINT IF EXISTS chk_items_pedido_cantidad_positiva');
        DB::statement('ALTER TABLE items_pedido DROP CONSTRAINT IF EXISTS chk_items_pedido_precio_no_negativo');
        DB::statement('ALTER TABLE cuentas_por_pagar DROP CONSTRAINT IF EXISTS chk_cxp_saldos');
        DB::statement('ALTER TABLE pagos_cxps DROP CONSTRAINT IF EXISTS chk_pagos_cxps_monto_positivo');
        DB::statement('ALTER TABLE movimientos_caja DROP CONSTRAINT IF EXISTS chk_movimientos_caja_monto_positivo');
        DB::statement('ALTER TABLE turnos_caja DROP CONSTRAINT IF EXISTS chk_turnos_caja_monto_inicial');
        DB::statement('ALTER TABLE recetas DROP CONSTRAINT IF EXISTS chk_recetas_cantidad_merma');
        DB::statement('ALTER TABLE insumos DROP CONSTRAINT IF EXISTS chk_insumos_costo_stock');

        // Drop unique constraints
        DB::statement('ALTER TABLE mesas DROP CONSTRAINT IF EXISTS mesas_sucursal_numero_unique');
        DB::statement('ALTER TABLE cajas DROP CONSTRAINT IF EXISTS cajas_sucursal_codigo_unique');
        DB::statement('ALTER TABLE cajas ADD CONSTRAINT cajas_codigo_unique UNIQUE (codigo)');
        DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_slug_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS productos_slug_index ON productos (slug)');

        // Drop composite indexes
        DB::statement('DROP INDEX IF EXISTS mesas_sucursal_estado_idx');
        DB::statement('DROP INDEX IF EXISTS mesas_sucursal_zona_idx');
        DB::statement('DROP INDEX IF EXISTS pedidos_sucursal_estado_idx');

        // Drop FK indexes
        $indexesToDrop = [
            'users_role_id_idx', 'users_sucursal_id_idx', 'mesas_sucursal_id_idx', 'mesas_mesero_id_idx',
            'productos_categoria_id_idx', 'pedidos_direccion_id_idx', 'pedidos_repartidor_id_idx',
            'cajas_sucursal_id_idx', 'turnos_caja_user_id_idx', 'turnos_caja_cerrado_por_user_id_idx',
            'movimientos_caja_user_id_idx', 'asientos_contables_user_id_idx', 'recetas_insumo_id_idx',
            'mov_inv_pedido_id_idx', 'mov_inv_user_id_idx', 'auditorias_user_id_idx', 'cxp_user_id_idx',
            'pagos_cxps_user_id_idx', 'dir_cliente_id_idx', 'mov_puntos_cliente_id_idx',
            'mov_puntos_pedido_id_idx', 'mov_puntos_usuario_id_idx', 'reservas_confirmado_por_idx',
            'reservas_created_by_idx', 'reservas_sucursal_id_idx', 'reserva_mesa_mesa_id_idx',
            'trab_imp_impresora_id_idx', 'trab_imp_reimpreso_por_id_idx', 'trab_imp_turno_caja_id_idx',
            'trab_imp_usuario_id_idx',
        ];

        foreach ($indexesToDrop as $idx) {
            DB::statement("DROP INDEX IF EXISTS {$idx}");
        }
    }
};
