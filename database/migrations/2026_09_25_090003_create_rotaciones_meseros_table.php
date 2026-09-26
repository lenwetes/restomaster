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
        Schema::create('rotaciones_meseros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->string('zona_slug', 50);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('turno', 30)->default('general');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_asignado_en')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'zona_slug', 'activo']);
            $table->unique(['sucursal_id', 'zona_slug', 'user_id', 'turno'], 'rotacion_zona_user_turno_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotaciones_meseros');
    }
};
