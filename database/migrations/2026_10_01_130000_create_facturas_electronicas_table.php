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
        Schema::create('facturas_electronicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('tipo_documento', 50)->default('pos_electronico'); // pos_electronico, factura_electronica
            $table->string('prefijo', 10)->default('POS');
            $table->unsignedBigInteger('consecutivo');
            $table->string('numero_factura', 30); // ej: POS-10025
            $table->string('cufe', 96)->unique(); // SHA-384 hexadecimal = 96 chars
            $table->text('qr_cadena');
            $table->text('qr_imagen_url')->nullable();
            $table->string('estado', 30)->default('emitida'); // emitida, contingencia, rechazada, pendiente
            $table->decimal('total', 12, 2);
            $table->decimal('impuesto', 12, 2)->default(0.00);
            $table->string('cliente_nit', 30)->default('222222222222');
            $table->string('cliente_nombre', 150)->default('Consumidor Final');
            $table->string('proveedor_tecnologico', 50)->default('factus');
            $table->json('respuesta_proveedor')->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestamp('emitida_en')->useCurrent();
            $table->timestamps();

            $table->index(['sucursal_id', 'numero_factura']);
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas_electronicas');
    }
};
