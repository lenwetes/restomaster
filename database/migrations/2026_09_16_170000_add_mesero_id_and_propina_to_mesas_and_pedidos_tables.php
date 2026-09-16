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
        Schema::table('mesas', function (Blueprint $table) {
            if (! Schema::hasColumn('mesas', 'mesero_id')) {
                $table->foreignId('mesero_id')
                    ->nullable()
                    ->after('estado')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('mesero_id');
            }
        });

        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'mesero_id')) {
                $table->foreignId('mesero_id')
                    ->nullable()
                    ->after('usuario_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('mesero_id');
            }

            if (! Schema::hasColumn('pedidos', 'propina')) {
                $table->decimal('propina', 10, 2)
                    ->default(0)
                    ->after('total');
            }

            if (! Schema::hasColumn('pedidos', 'porcentaje_propina')) {
                $table->decimal('porcentaje_propina', 5, 2)
                    ->nullable()
                    ->default(0)
                    ->after('propina');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'mesero_id')) {
                $table->dropForeign(['mesero_id']);
                $table->dropIndex(['mesero_id']);
                $table->dropColumn('mesero_id');
            }
            if (Schema::hasColumn('pedidos', 'porcentaje_propina')) {
                $table->dropColumn('porcentaje_propina');
            }
            if (Schema::hasColumn('pedidos', 'propina')) {
                $table->dropColumn('propina');
            }
        });

        Schema::table('mesas', function (Blueprint $table) {
            if (Schema::hasColumn('mesas', 'mesero_id')) {
                $table->dropForeign(['mesero_id']);
                $table->dropIndex(['mesero_id']);
                $table->dropColumn('mesero_id');
            }
        });
    }
};
