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
        Schema::create('notificaciones_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 50); // plato_listo, qr_nuevo, reserva_hoy, sistema
            $table->string('titulo', 200);
            $table->text('cuerpo')->nullable();
            $table->jsonb('datos')->nullable();
            $table->boolean('leida')->default(false);
            $table->timestampTz('leida_en')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'leida'], 'idx_notif_user_leida');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones_usuario');
    }
};
