<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->string('numero_factura', 64);
            $table->date('fecha');
            $table->decimal('subtotal', 12, 2);
            $table->string('forma_pago', 16);
            $table->string('estado', 16)->default('registrada');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['proveedor_id', 'numero_factura']);
            $table->index('user_id');
            $table->index(['fecha', 'estado']);
        });

        Schema::create('compra_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->restrictOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->decimal('cantidad', 10, 3);
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestampsTz();

            $table->index('compra_id');
            $table->index('insumo_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE compras ADD CONSTRAINT chk_compras_subtotal CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE compra_lineas ADD CONSTRAINT chk_compra_lineas_valores CHECK (cantidad > 0 AND costo_unitario >= 0 AND subtotal >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_lineas');
        Schema::dropIfExists('compras');
    }
};
