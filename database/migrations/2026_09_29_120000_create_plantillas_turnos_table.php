<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_turnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->foreignId('zona_default_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['sucursal_id', 'activo'], 'idx_plantillas_sucursal_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_turnos');
    }
};
