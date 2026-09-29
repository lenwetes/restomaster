<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos_meseros_semana', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacion_semanal_id')->constrained('programaciones_semanales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->foreignId('plantilla_turno_id')->nullable()->constrained('plantillas_turnos')->nullOnDelete();
            $table->jsonb('mesas_especificas')->nullable();
            $table->boolean('es_descanso')->default(false);
            $table->timestampTz('notificado_login_en')->nullable();
            $table->timestampTz('confirmado_por_mesero_en')->nullable();
            $table->timestamps();

            $table->unique(['programacion_semanal_id', 'user_id', 'fecha'], 'uniq_turno_sem_prog_user_fecha');
            $table->index(['programacion_semanal_id', 'fecha'], 'idx_turno_sem_prog_fecha');
            $table->index(['user_id', 'fecha'], 'idx_turno_sem_user_fecha');
            $table->index(['zona_id'], 'idx_turno_sem_zona');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos_meseros_semana');
    }
};
