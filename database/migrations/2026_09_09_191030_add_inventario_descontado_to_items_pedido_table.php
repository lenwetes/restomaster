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
        Schema::table('items_pedido', function (Blueprint $table) {
            $table->boolean('inventario_descontado')->default(false)->after('estado_cocina');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items_pedido', function (Blueprint $table) {
            $table->dropColumn('inventario_descontado');
        });
    }
};
