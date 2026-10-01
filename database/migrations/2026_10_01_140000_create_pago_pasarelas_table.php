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
        Schema::create('pago_pasarelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('proveedor', 30); // wompi, bold
            $table->string('transaccion_id', 100)->nullable()->index();
            $table->string('referencia', 100)->unique();
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 10)->default('COP');
            $table->string('metodo_pasarela', 50)->default('qr_mesa'); // qr_mesa, datafono_smart, link
            $table->string('terminal_id', 100)->nullable();
            $table->string('estado', 30)->default('pendiente'); // pendiente, aprobado, rechazado, anulado, expirado
            $table->text('checkout_url')->nullable();
            $table->text('qr_cadena')->nullable();
            $table->text('qr_imagen')->nullable();
            $table->string('firma_integridad', 150)->nullable();
            $table->json('datos_transaccion')->nullable();
            $table->timestamp('pagado_en')->nullable();
            $table->timestamps();

            $table->index(['proveedor', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_pasarelas');
    }
};
