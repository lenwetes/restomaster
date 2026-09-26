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
        // 1. Configuración de rotación por zona
        Schema::create('rotaciones_zona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            $table->boolean('activa')->default(true);
            $table->string('modo', 20)->default('automatico'); // automatico, manual
            $table->timestamps();

            $table->unique(['sucursal_id', 'zona_id'], 'uniq_rotacion_sucursal_zona');
        });

        // 2. Cola de turno de meseros por zona (round-robin)
        Schema::create('turno_mesero_zona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            $table->foreignId('mesero_id')->constrained('users')->cascadeOnDelete();
            $table->integer('orden')->default(0);
            $table->integer('mesas_activas')->default(0);
            $table->timestampTz('ultimo_asignado_en')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['zona_id', 'mesero_id'], 'uniq_turno_zona_mesero');
            $table->index(['zona_id', 'orden'], 'idx_turno_mz_zona_orden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turno_mesero_zona');
        Schema::dropIfExists('rotaciones_zona');
    }
};
