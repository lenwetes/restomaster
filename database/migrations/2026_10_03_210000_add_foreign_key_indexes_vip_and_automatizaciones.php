<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->index('vip_aprobado_por');
        });

        Schema::table('vip_invitaciones', function (Blueprint $table) {
            $table->index('enviada_por');
            $table->index('revisada_por');
        });

        Schema::table('automatizacion_flujos', function (Blueprint $table) {
            $table->index('sucursal_id');
            $table->index('plantilla_id');
            $table->index('creado_por');
            $table->index('actualizado_por');
        });

        Schema::table('automatizacion_flujo_versiones', function (Blueprint $table) {
            $table->index('creado_por');
        });

        Schema::table('automatizacion_ejecuciones', function (Blueprint $table) {
            $table->index('sucursal_id');
            $table->index('usuario_id');
        });

        Schema::table('automatizacion_ejecucion_pasos', function (Blueprint $table) {
            $table->index('paso_id');
            $table->index('mensaje_log_id');
        });

        Schema::table('automatizacion_ia_solicitudes', function (Blueprint $table) {
            $table->index('flujo_id');
            $table->index('ejecucion_id');
            $table->index('usuario_id');
        });

        Schema::table('automatizacion_auditoria', function (Blueprint $table) {
            $table->index('usuario_id');
        });

        Schema::table('crm_difusiones', function (Blueprint $table) {
            $table->index('promocion_id');
            $table->index('enviada_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_difusiones', function (Blueprint $table) {
            $table->dropIndex(['promocion_id']);
            $table->dropIndex(['enviada_por']);
        });

        Schema::table('automatizacion_auditoria', function (Blueprint $table) {
            $table->dropIndex(['usuario_id']);
        });

        Schema::table('automatizacion_ia_solicitudes', function (Blueprint $table) {
            $table->dropIndex(['flujo_id']);
            $table->dropIndex(['ejecucion_id']);
            $table->dropIndex(['usuario_id']);
        });

        Schema::table('automatizacion_ejecucion_pasos', function (Blueprint $table) {
            $table->dropIndex(['paso_id']);
            $table->dropIndex(['mensaje_log_id']);
        });

        Schema::table('automatizacion_ejecuciones', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id']);
            $table->dropIndex(['usuario_id']);
        });

        Schema::table('automatizacion_flujo_versiones', function (Blueprint $table) {
            $table->dropIndex(['creado_por']);
        });

        Schema::table('automatizacion_flujos', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id']);
            $table->dropIndex(['plantilla_id']);
            $table->dropIndex(['creado_por']);
            $table->dropIndex(['actualizado_por']);
        });

        Schema::table('vip_invitaciones', function (Blueprint $table) {
            $table->dropIndex(['enviada_por']);
            $table->dropIndex(['revisada_por']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['vip_aprobado_por']);
        });
    }
};
