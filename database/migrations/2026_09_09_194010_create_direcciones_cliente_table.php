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
        Schema::create('direcciones_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('etiqueta')->default('Casa'); // 'Casa', 'Oficina', 'Apartamento'
            $table->string('direccion');
            $table->string('referencia_apto')->nullable(); // Ej: 'Edificio Torre Sur, Apto 802'
            $table->string('barrio_ciudad')->nullable(); // Ej: 'El Poblado, Medellín'
            $table->string('telefono_contacto')->nullable();
            $table->text('notas_entrega')->nullable(); // Ej: 'Dejar en portería 24h'
            $table->boolean('es_predeterminada')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direcciones_cliente');
    }
};
