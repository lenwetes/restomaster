<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Consolidación de 44 índices B-Tree en Foreign Keys de tablas satélite
     * para eliminar Seq Scans y bloqueos en cascada en PostgreSQL 18.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            // 1. Rotaciones y Asignación de Zonas
            DB::statement('CREATE INDEX IF NOT EXISTS idx_rotaciones_meseros_user_id ON rotaciones_meseros (user_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_rotaciones_meseros_zona_id ON rotaciones_meseros (zona_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_rotaciones_zona_zona_id ON rotaciones_zona (zona_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_turno_mesero_zona_mesero_id ON turno_mesero_zona (mesero_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_turno_mesero_zona_sucursal_id ON turno_mesero_zona (sucursal_id)');

            // 2. Encuestas y Feedback de Clientes
            DB::statement('CREATE INDEX IF NOT EXISTS idx_encuestas_sucursal_id ON encuestas (sucursal_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_encuesta_envios_encuesta_id ON encuesta_envios (encuesta_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_encuesta_envios_pedido_id ON encuesta_envios (pedido_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_encuesta_envios_reserva_id ON encuesta_envios (reserva_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_encuesta_respuestas_envio_id ON encuesta_respuestas (envio_id)');

            // 3. CRM & Automatizaciones
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_config_ia_plantilla_priv_id ON crm_configuraciones (ia_plantilla_privilegio_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_config_sucursal_id ON crm_configuraciones (sucursal_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_automatizaciones_email_id ON crm_automatizaciones (plantilla_email_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_automatizaciones_whatsapp_id ON crm_automatizaciones (plantilla_whatsapp_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_mensajes_log_automatizacion_id ON crm_mensajes_log (automatizacion_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_mensajes_log_cliente_id ON crm_mensajes_log (cliente_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_mensajes_log_pedido_id ON crm_mensajes_log (pedido_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_mensajes_log_reserva_id ON crm_mensajes_log (reserva_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_ia_plantillas_priv_sucursal_id ON crm_ia_plantillas_privilegios (sucursal_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_conversaciones_cliente_id ON crm_conversaciones (cliente_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_conversaciones_sucursal_id ON crm_conversaciones (sucursal_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_conversaciones_user_id_asignado ON crm_conversaciones (user_id_asignado)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_mensajes_user_id ON crm_mensajes (user_id)');

            // 4. Promociones, Difusión y Canjes
            DB::statement('CREATE INDEX IF NOT EXISTS idx_promociones_created_by ON promociones (created_by)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_promociones_sucursal_id ON promociones (sucursal_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_promocion_canjes_cliente_id ON promocion_canjes (cliente_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_promocion_canjes_pedido_id ON promocion_canjes (pedido_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_promocion_canjes_reserva_id ON promocion_canjes (reserva_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_promocion_difusiones_user_id ON promocion_difusiones (user_id)');

            // 5. Devoluciones de Pedidos
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pedido_devoluciones_asiento_contable_id ON pedido_devoluciones (asiento_contable_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pedido_devoluciones_item_pedido_id ON pedido_devoluciones (item_pedido_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pedido_devoluciones_movimiento_caja_id ON pedido_devoluciones (movimiento_caja_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pedido_devoluciones_producto_id ON pedido_devoluciones (producto_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pedido_devoluciones_turno_caja_id ON pedido_devoluciones (turno_caja_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pedido_devoluciones_user_id ON pedido_devoluciones (user_id)');

            // 6. Turnos Semanales y Plantillas
            DB::statement('CREATE INDEX IF NOT EXISTS idx_plantillas_turnos_zona_default_id ON plantillas_turnos (zona_default_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_programaciones_semanales_publicado_por ON programaciones_semanales (publicado_por)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_turnos_meseros_semana_plantilla_turno_id ON turnos_meseros_semana (plantilla_turno_id)');

            // 7. Notas Crédito
            DB::statement('CREATE INDEX IF NOT EXISTS idx_notas_credito_autorizado_por ON notas_credito (autorizado_por)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_notas_credito_pedido_devolucion_id ON notas_credito (pedido_devolucion_id)');

            // 8. Insumos por Sucursal
            DB::statement('CREATE INDEX IF NOT EXISTS idx_insumo_sucursales_insumo_id ON insumo_sucursales (insumo_id)');

            // 9. Facturación Electrónica DIAN y Pasarelas de Pago
            DB::statement('CREATE INDEX IF NOT EXISTS idx_facturas_electronicas_pedido_id ON facturas_electronicas (pedido_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pago_pasarelas_pedido_id ON pago_pasarelas (pedido_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_pago_pasarelas_sucursal_id ON pago_pasarelas (sucursal_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            $indices = [
                'idx_rotaciones_meseros_user_id',
                'idx_rotaciones_meseros_zona_id',
                'idx_rotaciones_zona_zona_id',
                'idx_turno_mesero_zona_mesero_id',
                'idx_turno_mesero_zona_sucursal_id',
                'idx_encuestas_sucursal_id',
                'idx_encuesta_envios_encuesta_id',
                'idx_encuesta_envios_pedido_id',
                'idx_encuesta_envios_reserva_id',
                'idx_encuesta_respuestas_envio_id',
                'idx_crm_config_ia_plantilla_priv_id',
                'idx_crm_config_sucursal_id',
                'idx_crm_automatizaciones_email_id',
                'idx_crm_automatizaciones_whatsapp_id',
                'idx_crm_mensajes_log_automatizacion_id',
                'idx_crm_mensajes_log_cliente_id',
                'idx_crm_mensajes_log_pedido_id',
                'idx_crm_mensajes_log_reserva_id',
                'idx_crm_ia_plantillas_priv_sucursal_id',
                'idx_crm_conversaciones_cliente_id',
                'idx_crm_conversaciones_sucursal_id',
                'idx_crm_conversaciones_user_id_asignado',
                'idx_crm_mensajes_user_id',
                'idx_promociones_created_by',
                'idx_promociones_sucursal_id',
                'idx_promocion_canjes_cliente_id',
                'idx_promocion_canjes_pedido_id',
                'idx_promocion_canjes_reserva_id',
                'idx_promocion_difusiones_user_id',
                'idx_pedido_devoluciones_asiento_contable_id',
                'idx_pedido_devoluciones_item_pedido_id',
                'idx_pedido_devoluciones_movimiento_caja_id',
                'idx_pedido_devoluciones_producto_id',
                'idx_pedido_devoluciones_turno_caja_id',
                'idx_pedido_devoluciones_user_id',
                'idx_plantillas_turnos_zona_default_id',
                'idx_programaciones_semanales_publicado_por',
                'idx_turnos_meseros_semana_plantilla_turno_id',
                'idx_notas_credito_autorizado_por',
                'idx_notas_credito_pedido_devolucion_id',
                'idx_insumo_sucursales_insumo_id',
                'idx_facturas_electronicas_pedido_id',
                'idx_pago_pasarelas_pedido_id',
                'idx_pago_pasarelas_sucursal_id',
            ];

            foreach ($indices as $idx) {
                DB::statement("DROP INDEX IF EXISTS {$idx}");
            }
        }
    }
};
