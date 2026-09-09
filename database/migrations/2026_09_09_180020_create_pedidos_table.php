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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('tipo')->default('mesa'); // 'mesa', 'mostrador', 'delivery'
            $table->string('estado')->default('creado'); // 'creado', 'en_cocina', 'listo', 'entregado', 'pagado', 'cancelado'
            $table->foreignId('mesa_id')->nullable()->constrained('mesas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre_cliente')->nullable();
            $table->string('telefono_cliente')->nullable();
            $table->text('direccion_delivery')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('metodo_pago')->nullable(); // 'efectivo', 'tarjeta', 'mixto'
            $table->decimal('monto_pagado', 10, 2)->nullable();
            $table->decimal('cambio', 10, 2)->nullable();
            $table->text('notas')->nullable();
            $table->timestamp('pagado_en')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
