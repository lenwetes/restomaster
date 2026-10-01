<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Soporte integral para inventario y Kardex independiente en 5 sucursales.
     */
    public function up(): void
    {
        Schema::create('insumo_sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->cascadeOnDelete();
            $table->decimal('stock_actual', 12, 3)->default(0);
            $table->decimal('stock_minimo', 12, 3)->default(1);
            $table->decimal('capacidad_maxima', 12, 3)->default(100);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->string('ubicacion_almacen')->nullable();
            $table->string('temperatura_almacen')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['sucursal_id', 'insumo_id'], 'insumo_sucursal_unique');
            $table->index(['sucursal_id', 'stock_actual'], 'idx_insumo_sucursal_stock');
        });

        // Agregar sucursal_id a movimientos_inventario para trazabilidad de Kardex multi-bodega
        if (! Schema::hasColumn('movimientos_inventario', 'sucursal_id')) {
            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->foreignId('sucursal_id')->nullable()->after('insumo_id')->constrained('sucursales')->nullOnDelete();
                $table->index(['sucursal_id', 'created_at'], 'idx_mov_inv_sucursal_fecha');
            });
        }

        // Sembrar stock inicial en insumo_sucursales para sucursales e insumos existentes
        try {
            $sucursales = DB::table('sucursales')->pluck('id');
            $insumos = DB::table('insumos')->get(['id', 'stock_actual', 'stock_minimo', 'capacidad_maxima', 'costo_unitario', 'ubicacion_almacen', 'temperatura_almacen']);

            $now = now();
            foreach ($sucursales as $sucursalId) {
                foreach ($insumos as $insumo) {
                    DB::table('insumo_sucursales')->insertOrIgnore([
                        'sucursal_id' => $sucursalId,
                        'insumo_id' => $insumo->id,
                        'stock_actual' => $insumo->stock_actual,
                        'stock_minimo' => $insumo->stock_minimo,
                        'capacidad_maxima' => $insumo->capacidad_maxima,
                        'costo_unitario' => $insumo->costo_unitario,
                        'ubicacion_almacen' => $insumo->ubicacion_almacen,
                        'temperatura_almacen' => $insumo->temperatura_almacen,
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        } catch (Throwable $e) {
            // Silencioso si la tabla está vacía en este punto
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('movimientos_inventario', 'sucursal_id')) {
            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sucursal_id');
            });
        }

        Schema::dropIfExists('insumo_sucursales');
    }
};
