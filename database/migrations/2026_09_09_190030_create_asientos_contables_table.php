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
        Schema::create('asientos_contables', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('tipo'); // ingreso, gasto
            $table->string('cuenta'); // ventas_restaurante, gastos_operativos, retiros_banco, caja_chica
            $table->string('concepto');
            $table->decimal('monto', 12, 2);
            $table->string('referencia_tipo')->nullable(); // pedido, movimiento_caja, arqueo_caja
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha', 'tipo']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asientos_contables');
    }
};
