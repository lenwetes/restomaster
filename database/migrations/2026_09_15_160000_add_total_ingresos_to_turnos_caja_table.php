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
        Schema::table('turnos_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('turnos_caja', 'total_ingresos')) {
                $table->decimal('total_ingresos', 12, 2)->default(0)->after('total_ventas_transferencia');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos_caja', function (Blueprint $table) {
            if (Schema::hasColumn('turnos_caja', 'total_ingresos')) {
                $table->dropColumn('total_ingresos');
            }
        });
    }
};
