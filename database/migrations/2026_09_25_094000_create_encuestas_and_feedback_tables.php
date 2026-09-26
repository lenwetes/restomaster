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
        // 1. Plantillas de encuesta
        Schema::create('encuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('nombre', 200);
            $table->boolean('activa')->default(true);
            $table->string('disparador', 30)->default('post_pago'); // post_pago, post_reserva, manual
            $table->integer('delay_horas')->default(1);
            $table->json('preguntas'); // [{tipo: 'estrellas'|'texto'|'si_no', pregunta: '...'}]
            $table->timestamps();
        });

        // 2. Envíos/instancias de encuestas a clientes
        Schema::create('encuesta_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuesta_id')->constrained('encuestas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('estado', 20)->default('pendiente'); // pendiente, enviada, respondida, expirada
            $table->timestampTz('enviada_en')->nullable();
            $table->timestampTz('respondida_en')->nullable();
            $table->timestampTz('expira_en')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'estado'], 'idx_envio_cliente_estado');
            $table->index('token', 'idx_envio_token');
        });

        // 3. Respuestas a preguntas individuales
        Schema::create('encuesta_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_id')->constrained('encuesta_envios')->cascadeOnDelete();
            $table->smallInteger('pregunta_indice');
            $table->string('tipo_respuesta', 20); // estrellas, texto, si_no
            $table->smallInteger('valor_estrellas')->nullable();
            $table->text('valor_texto')->nullable();
            $table->boolean('valor_booleano')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encuesta_respuestas');
        Schema::dropIfExists('encuesta_envios');
        Schema::dropIfExists('encuestas');
    }
};
