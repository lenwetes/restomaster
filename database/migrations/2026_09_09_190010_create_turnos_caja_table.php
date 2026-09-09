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
        Schema::create('turnos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('apertura_en');
            $table->dateTime('cierre_en')->nullable();
            $table->decimal('monto_inicial', 12, 2)->default(0.00);
            $table->decimal('total_ventas_efectivo', 12, 2)->default(0.00);
            $table->decimal('total_ventas_tarjeta', 12, 2)->default(0.00);
            $table->decimal('total_ventas_transferencia', 12, 2)->default(0.00);
            $table->decimal('total_egresos', 12, 2)->default(0.00);
            $table->decimal('total_retiros', 12, 2)->default(0.00);
            $table->decimal('monto_esperado_efectivo', 12, 2)->default(0.00);
            $table->decimal('monto_real_efectivo', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->default(0.00);
            $table->string('estado')->default('abierto'); // abierto, cerrado, cancelado
            $table->text('notas_apertura')->nullable();
            $table->text('notas_cierre')->nullable();
            $table->foreignId('cerrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Add optional turno_caja_id to pedidos table
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('turno_caja_id')->nullable()->after('user_id')->constrained('turnos_caja')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_caja_id');
        });

        Schema::dropIfExists('turnos_caja');
    }
};
