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
        Schema::table('pedidos', function (Blueprint $table) {
            $table->index(['estado', 'pagado_en']);
        });

        Schema::table('turnos_caja', function (Blueprint $table) {
            $table->index(['caja_id', 'estado']);
        });

        Schema::table('movimientos_caja', function (Blueprint $table) {
            $table->index(['turno_caja_id', 'tipo']);
        });

        Schema::table('auditorias', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('movimientos_caja', function (Blueprint $table) {
            $table->dropIndex(['turno_caja_id', 'tipo']);
        });

        Schema::table('turnos_caja', function (Blueprint $table) {
            $table->dropIndex(['caja_id', 'estado']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['estado', 'pagado_en']);
        });
    }
};
