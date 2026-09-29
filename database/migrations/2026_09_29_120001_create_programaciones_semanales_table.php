<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programaciones_semanales', function (Blueprint $table) {
            $table->id();
            $table->integer('semana_iso');
            $table->integer('anio');
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('estado', 20)->default('borrador');
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('publicado_en')->nullable();
            $table->timestamps();

            $table->unique(['sucursal_id', 'semana_iso', 'anio'], 'uniq_prog_sem_sucursal_semana_anio');
            $table->index(['sucursal_id', 'estado'], 'idx_prog_sem_sucursal_estado');
        });

        DB::statement("ALTER TABLE programaciones_semanales ADD CONSTRAINT chk_prog_sem_estado CHECK (estado IN ('borrador', 'publicado', 'archivado'))");
        DB::statement('ALTER TABLE programaciones_semanales ADD CONSTRAINT chk_prog_sem_iso CHECK (semana_iso >= 1 AND semana_iso <= 53)');
    }

    public function down(): void
    {
        Schema::dropIfExists('programaciones_semanales');
    }
};
