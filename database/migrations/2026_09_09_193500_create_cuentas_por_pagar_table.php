<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_por_pagar', function (Blueprint $table) {
            $table->id();
            $table->string('proveedor_nombre');
            $table->string('proveedor_nit', 30)->nullable();
            $table->unsignedBigInteger('insumo_id')->nullable();
            $table->string('concepto');
            $table->decimal('monto_total', 12, 2);
            $table->decimal('saldo_pendiente', 12, 2);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente, pagada, cancelada
            $table->string('notas')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['proveedor_nombre', 'estado']);
            $table->index('fecha_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_por_pagar');
    }
};
