<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pedidos', 'sucursal_id')) {
            return;
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->index('sucursal_id');
        });

        DB::statement(
            'UPDATE pedidos
             SET sucursal_id = (SELECT m.sucursal_id FROM mesas m WHERE m.id = pedidos.mesa_id)
             WHERE sucursal_id IS NULL AND mesa_id IS NOT NULL'
        );

        DB::statement(
            'UPDATE pedidos
             SET sucursal_id = (SELECT c.sucursal_id FROM turnos_caja t JOIN cajas c ON c.id = t.caja_id WHERE t.id = pedidos.turno_caja_id)
             WHERE sucursal_id IS NULL AND turno_caja_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id']);
            $table->dropForeign(['sucursal_id']);
            $table->dropColumn('sucursal_id');
        });
    }
};
