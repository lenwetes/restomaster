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
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insumo_id')->constrained('insumos')->cascadeOnDelete();
            $table->string('tipo'); // compra, consumo_venta, merma, ajuste_positivo, ajuste_negativo
            $table->decimal('cantidad', 12, 3); // Cantidad movida (positiva)
            $table->decimal('saldo_anterior', 12, 3);
            $table->decimal('saldo_posterior', 12, 3);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->decimal('costo_total', 12, 2)->default(0);
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo')->nullable(); // Ej: Venta Pedido #15, Vencimiento, Rotura, Conteo Físico
            $table->string('referencia_documento')->nullable(); // Ej: FAC-4092, ORD-12, MER-01
            $table->timestamps();

            $table->index(['insumo_id', 'created_at']);
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
