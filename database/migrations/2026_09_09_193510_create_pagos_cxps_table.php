<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_cxps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('metodo_pago')->default('efectivo'); // efectivo, tarjeta, transferencia
            $table->date('fecha_pago');
            $table->string('concepto')->nullable();
            $table->timestamps();

            $table->index('cuenta_por_pagar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_cxps');
    }
};