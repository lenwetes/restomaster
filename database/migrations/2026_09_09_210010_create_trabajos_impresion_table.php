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
        Schema::create('trabajos_impresion', function (Blueprint $table) {
            $table->id();
            $table->string('tipo'); // 'comanda_cocina', 'ticket_venta', 'cuenta_mesa', 'reporte_z', 'prueba'
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('turno_caja_id')->nullable()->constrained('turnos_caja')->nullOnDelete();
            $table->foreignId('impresora_id')->constrained('impresoras')->cascadeOnDelete();
            $table->string('area')->default('general');
            $table->text('contenido_texto');
            $table->text('contenido_raw')->nullable(); // Binario o comandos ESC/POS codificados
            $table->string('estado')->default('pendiente'); // 'pendiente', 'enviado', 'error', 'reimpreso', 'cancelado'
            $table->integer('intentos')->default(0);
            $table->text('error_mensaje')->nullable();
            $table->timestamp('impreso_en')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reimpreso_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('veces_reimpreso')->default(0);
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index(['pedido_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trabajos_impresion');
    }
};
