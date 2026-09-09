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
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->string('categoria'); // pescados, arroz_granos, algas_nori, vegetales, lacteos_quesos, salsas_condimentos, packaging
            $table->string('unidad_medida'); // kg, g, l, ml, unidad, paquete
            $table->decimal('stock_actual', 12, 3)->default(0);
            $table->decimal('stock_minimo', 12, 3)->default(1);
            $table->decimal('capacidad_maxima', 12, 3)->default(100);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->string('proveedor_nombre')->nullable();
            $table->string('proveedor_nit')->nullable();
            $table->string('proveedor_telefono')->nullable();
            $table->string('ubicacion_almacen')->nullable(); // Ej: Cámara Fría #01 · Estante 4
            $table->string('temperatura_almacen')->nullable(); // Ej: 2°C, -18°C, Ambiente
            $table->string('imagen')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['categoria', 'activo']);
            $table->index('stock_actual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
