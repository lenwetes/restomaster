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
        // 1. Tablas principales de flujos
        Schema::create('automatizacion_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('categoria', 50)->default('crm')->index(); // crm, vip, inventario, caja, reportes
            $table->jsonb('definicion');
            $table->string('icono', 50)->nullable();
            $table->boolean('activa')->default(true);
            $table->boolean('es_sistema')->default(true);
            $table->timestamps();
        });

        Schema::create('automatizacion_flujos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('borrador')->index(); // borrador, activo, pausado, archivado
            $table->string('disparador_tipo', 30)->index(); // evento, programado, manual
            $table->string('disparador_clave', 100)->index(); // ej: pedido.cobrado, cliente.cumpleanos, etc.
            $table->jsonb('disparador_config')->nullable();
            $table->jsonb('condiciones')->nullable();
            $table->integer('version_actual')->default(1);
            $table->string('origen', 30)->default('manual'); // manual, plantilla, ia
            $table->foreignId('plantilla_id')->nullable()->constrained('automatizacion_plantillas')->nullOnDelete();
            $table->integer('max_ejecuciones_dia')->nullable();
            $table->integer('cooldown_minutos')->default(0);
            $table->boolean('respetar_horario_antispam')->default(true);
            $table->unsignedBigInteger('total_ejecuciones')->default(0);
            $table->unsignedBigInteger('total_exitosas')->default(0);
            $table->unsignedBigInteger('total_fallidas')->default(0);
            $table->timestamp('ultima_ejecucion_at')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'disparador_tipo', 'disparador_clave']);
        });

        Schema::create('automatizacion_pasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flujo_id')->constrained('automatizacion_flujos')->cascadeOnDelete();
            $table->integer('orden');
            $table->string('tipo', 40)->index(); // whatsapp, email, notificar_equipo, esperar, condicion, gemini, consulta_datos, puntos, cupon, difusion_vip
            $table->string('nombre');
            $table->jsonb('config');
            $table->string('variable_salida', 60)->nullable();
            $table->unsignedBigInteger('paso_si_id')->nullable();
            $table->unsignedBigInteger('paso_no_id')->nullable();
            $table->boolean('continuar_si_falla')->default(false);
            $table->integer('reintentos_max')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['flujo_id', 'orden']);
            $table->index(['flujo_id', 'activo']);
        });

        Schema::create('automatizacion_flujo_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flujo_id')->constrained('automatizacion_flujos')->cascadeOnDelete();
            $table->integer('version');
            $table->jsonb('snapshot');
            $table->string('nota_cambio')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['flujo_id', 'version']);
        });

        Schema::create('automatizacion_programaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flujo_id')->unique()->constrained('automatizacion_flujos')->cascadeOnDelete();
            $table->string('cron_expresion', 50);
            $table->string('zona_horaria', 50)->default('America/Bogota');
            $table->timestamp('proxima_ejecucion_at')->nullable()->index();
            $table->timestamp('ultima_ejecucion_at')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['activa', 'proxima_ejecucion_at']);
        });

        // 2. Ejecuciones e Historial
        Schema::create('automatizacion_ejecuciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('flujo_id')->constrained('automatizacion_flujos')->cascadeOnDelete();
            $table->integer('flujo_version')->default(1);
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('estado', 30)->default('pendiente')->index(); // pendiente, en_curso, esperando, exitosa, fallida, cancelada, omitida
            $table->string('modo', 20)->default('real'); // real, prueba_seco
            $table->string('disparado_por', 30)->default('evento'); // evento, cron, usuario
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entidad_tipo', 80)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('clave_idempotencia', 100)->nullable()->unique();
            $table->jsonb('contexto')->nullable();
            $table->unsignedBigInteger('paso_actual_id')->nullable();
            $table->timestamp('reanudar_at')->nullable()->index();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('finalizada_at')->nullable();
            $table->integer('duracion_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['flujo_id', 'created_at']);
            $table->index(['entidad_tipo', 'entidad_id']);
        });

        Schema::create('automatizacion_ejecucion_pasos', function (Blueprint $table) {
            $table->id();
            $table->uuid('ejecucion_id');
            $table->foreign('ejecucion_id')->references('id')->on('automatizacion_ejecuciones')->cascadeOnDelete();
            $table->foreignId('paso_id')->nullable()->constrained('automatizacion_pasos')->nullOnDelete();
            $table->integer('orden');
            $table->string('estado', 30)->default('pendiente'); // pendiente, exitoso, fallido, omitido
            $table->integer('intento')->default(1);
            $table->jsonb('entrada')->nullable();
            $table->jsonb('salida')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('mensaje_log_id')->nullable()->constrained('crm_mensajes_log')->nullOnDelete();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->integer('duracion_ms')->nullable();

            $table->index(['ejecucion_id', 'orden']);
        });

        // 3. IA y Auditoría
        Schema::create('automatizacion_ia_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flujo_id')->nullable()->constrained('automatizacion_flujos')->nullOnDelete();
            $table->uuid('ejecucion_id')->nullable();
            $table->foreign('ejecucion_id')->references('id')->on('automatizacion_ejecuciones')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proposito', 40); // crear_flujo, paso_gemini, redactar
            $table->string('modelo', 50)->default('gemini-flash-lite-latest');
            $table->string('prompt_hash', 64)->nullable();
            $table->integer('tokens_entrada')->default(0);
            $table->integer('tokens_salida')->default(0);
            $table->integer('latencia_ms')->default(0);
            $table->string('estado', 30)->default('completado'); // completado, error, timeout
            $table->boolean('respuesta_valida')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['proposito', 'created_at']);
        });

        Schema::create('automatizacion_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flujo_id')->constrained('automatizacion_flujos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 40); // crear, editar, activar, pausar, archivar, restaurar_version, ejecutar_manual
            $table->jsonb('antes')->nullable();
            $table->jsonb('despues')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['flujo_id', 'created_at']);
        });

        // 4. Difusiones Masivas VIP (para Chat y Campañas)
        Schema::create('crm_difusiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('titulo');
            $table->string('segmento', 50)->default('todos_vip'); // todos_vip, cumpleaneros_mes, inactivos_30d, top20_consumo
            $table->string('canal', 20)->default('whatsapp'); // whatsapp, email, ambos
            $table->foreignId('promocion_id')->nullable()->constrained('promociones')->nullOnDelete();
            $table->text('mensaje_whatsapp')->nullable();
            $table->text('mensaje_email_html')->nullable();
            $table->string('whatsapp_template_name')->nullable();
            $table->integer('total_destinatarios')->default(0);
            $table->integer('total_enviados')->default(0);
            $table->integer('total_entregados')->default(0);
            $table->integer('total_leidos')->default(0);
            $table->integer('total_fallidos')->default(0);
            $table->string('estado', 30)->default('borrador'); // borrador, programada, procesando, completada, cancelada
            $table->timestamp('programada_para')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('completada_at')->nullable();
            $table->foreignId('enviada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sucursal_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_difusiones');
        Schema::dropIfExists('automatizacion_auditoria');
        Schema::dropIfExists('automatizacion_ia_solicitudes');
        Schema::dropIfExists('automatizacion_ejecucion_pasos');
        Schema::dropIfExists('automatizacion_ejecuciones');
        Schema::dropIfExists('automatizacion_programaciones');
        Schema::dropIfExists('automatizacion_flujo_versiones');
        Schema::dropIfExists('automatizacion_pasos');
        Schema::dropIfExists('automatizacion_flujos');
        Schema::dropIfExists('automatizacion_plantillas');
    }
};
