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
        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('mesero_id')->nullable()->constrained('users')->nullOnDelete()->after('cliente_id');
            $table->foreignId('zona_preferida_id')->nullable()->constrained('zonas')->nullOnDelete()->after('mesero_id');
            $table->boolean('asignacion_automatica')->default(false)->after('zona_preferida_id');

            $table->index('mesero_id', 'idx_reservas_mesero');
            $table->index('zona_preferida_id', 'idx_reservas_zona');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropIndex('idx_reservas_mesero');
            $table->dropIndex('idx_reservas_zona');
            $table->dropForeign(['mesero_id']);
            $table->dropForeign(['zona_preferida_id']);
            $table->dropColumn(['mesero_id', 'zona_preferida_id', 'asignacion_automatica']);
        });
    }
};
