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
        // 1. Configuraciones generales del CRM y canales
        Schema::create('crm_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('whatsapp_proveedor')->default('meta_cloud'); // meta_cloud, evolution_api, simulado
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_waba_id')->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->string('whatsapp_webhook_secret')->nullable();
            $table->string('whatsapp_telefono_pruebas')->nullable();
            $table->boolean('email_activo')->default(true);
            $table->string('email_remitente_nombre')->nullable();
            $table->string('email_remitente_correo')->nullable();
            $table->string('horario_envio_inicio')->default('10:00');
            $table->string('horario_envio_fin')->default('22:00');
            $table->integer('delay_encuesta_minutos')->default(20);
            $table->integer('winback_dias_inactividad')->default(45);
            $table->timestamps();
        });

        // 2. Plantillas de mensajes para WhatsApp y Correo
        Schema::create('crm_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->string('canal'); // whatsapp, email
            $table->string('categoria'); // encuesta, fidelizacion, reserva, marketing, cumpleanos
            $table->string('asunto')->nullable();
            $table->text('contenido');
            $table->string('whatsapp_template_name')->nullable();
            $table->string('whatsapp_language_code')->default('es');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // 3. Reglas de automatización y disparadores de eventos
        Schema::create('crm_automatizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('evento_disparador'); // pedido_cobrado, reserva_confirmada, reserva_recordatorio_2h, cliente_inactivo, cliente_cumpleanos
            $table->string('canal')->default('whatsapp'); // whatsapp, email, ambos
            $table->foreignId('plantilla_whatsapp_id')->nullable()->constrained('crm_plantillas')->nullOnDelete();
            $table->foreignId('plantilla_email_id')->nullable()->constrained('crm_plantillas')->nullOnDelete();
            $table->integer('delay_minutos')->default(15);
            $table->boolean('activa')->default(true);
            $table->json('condiciones')->nullable();
            $table->integer('total_disparos')->default(0);
            $table->timestamps();
        });

        // 4. Log y auditoría de cada mensaje despachado
        Schema::create('crm_mensajes_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automatizacion_id')->nullable()->constrained('crm_automatizaciones')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->string('canal'); // whatsapp, email
            $table->string('destinatario');
            $table->string('asunto')->nullable();
            $table->text('contenido_enviado');
            $table->string('estado')->default('pendiente'); // pendiente, enviado, entregado, leido, fallido, cancelado
            $table->string('mensaje_id_externo')->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('entregado_en')->nullable();
            $table->timestamp('leido_en')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['canal', 'estado']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_mensajes_log');
        Schema::dropIfExists('crm_automatizaciones');
        Schema::dropIfExists('crm_plantillas');
        Schema::dropIfExists('crm_configuraciones');
    }
};
