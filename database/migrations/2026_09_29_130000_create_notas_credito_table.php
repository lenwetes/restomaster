<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_credito', function (Blueprint $table) {
            $table->id();
            $table->string('numero_nc', 40)->unique();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('pedido_devolucion_id')->nullable()->constrained('pedido_devoluciones')->nullOnDelete();
            $table->string('motivo', 30);
            $table->text('descripcion')->nullable();
            $table->foreignId('autorizado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->timestamps();

            $table->index(['pedido_id'], 'idx_nc_pedido');
            $table->index(['sucursal_id'], 'idx_nc_sucursal');
        });

        DB::statement("ALTER TABLE notas_credito ADD CONSTRAINT chk_nc_motivo CHECK (motivo IN ('error_cargo', 'producto_defectuoso', 'cambio_pedido', 'otro'))");
        DB::statement('ALTER TABLE notas_credito ADD CONSTRAINT chk_nc_monto CHECK (monto > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_credito');
    }
};
