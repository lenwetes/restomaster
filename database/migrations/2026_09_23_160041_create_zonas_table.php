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
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('nombre', 60);
            $table->string('slug', 40);
            $table->string('color', 30)->default('terracota');
            $table->string('icono', 40)->default('table_restaurant');
            $table->integer('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->unique(['sucursal_id', 'slug'], 'zonas_sucursal_slug_unique');
            $table->index(['sucursal_id', 'activa', 'orden'], 'zonas_sucursal_activa_orden_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
