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
        Schema::create('crm_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('canal', 20)->default('web')->index(); // 'web', 'whatsapp'
            $table->string('identificador_remoto', 100)->index(); // Teléfono o Session Token
            $table->uuid('session_token')->nullable()->unique(); // Para visitantes web
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('user_id_asignado')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modo_atencion', 20)->default('ia'); // 'ia', 'humano'
            $table->string('estado', 30)->default('activa'); // 'activa', 'esperando_humano', 'cerrada'
            $table->string('nombre_contacto', 150)->nullable();
            $table->text('ultimo_mensaje_texto')->nullable();
            $table->timestampTz('ultimo_mensaje_at')->nullable()->index();
            $table->text('resumen_contexto')->nullable(); // Memoria compacta de la conversación
            $table->unsignedInteger('no_leidos_staff')->default(0);
            $table->unsignedInteger('no_leidos_cliente')->default(0);
            $table->timestampsTz();

            $table->index(['canal', 'estado']);
            $table->index(['modo_atencion', 'estado']);
        });

        Schema::create('crm_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_conversacion_id')->constrained('crm_conversaciones')->cascadeOnDelete();
            $table->string('emisor', 20); // 'cliente', 'bot', 'staff'
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Staff que respondió
            $table->text('contenido');
            $table->string('canal_origen', 20)->default('web'); // 'web', 'whatsapp'
            $table->string('estado_entrega', 20)->default('enviado'); // 'enviado', 'entregado', 'leido', 'fallido'
            $table->string('wamid', 150)->nullable()->index(); // ID único mensaje WhatsApp
            $table->jsonb('metadata')->nullable(); // Tokens, intención, herramientas ejecutadas
            $table->timestampsTz();

            $table->index(['crm_conversacion_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_mensajes');
        Schema::dropIfExists('crm_conversaciones');
    }
};
