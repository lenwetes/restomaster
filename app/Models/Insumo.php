<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insumo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'insumos';

    protected $fillable = [
        'categoria_id',
        'nombre',
        'codigo',
        'categoria',
        'unidad_medida',
        'stock_actual',
        'stock_minimo',
        'capacidad_maxima',
        'costo_unitario',
        'proveedor_nombre',
        'proveedor_nit',
        'proveedor_telefono',
        'ubicacion_almacen',
        'temperatura_almacen',
        'imagen',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'capacidad_maxima' => 'decimal:3',
            'costo_unitario' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Recetas en las que participa este insumo.
     */
    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'insumo_id');
    }

    /**
     * Productos del menú que contienen este insumo.
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'recetas', 'insumo_id', 'producto_id')
            ->withPivot(['cantidad', 'merma_esperada_pct', 'notas'])
            ->withTimestamps();
    }

    /**
     * Historial de movimientos de Kardex.
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'insumo_id')->latest();
    }

    /**
     * Indica si el insumo está en nivel crítico de stock.
     */
    public function getEsCriticoAttribute(): bool
    {
        return (float) $this->stock_actual <= (float) $this->stock_minimo;
    }

    /**
     * Porcentaje de llenado vs capacidad máxima (o 2x stock mínimo).
     */
    public function getPorcentajeStockAttribute(): float
    {
        $max = (float) $this->capacidad_maxima > 0 ? (float) $this->capacidad_maxima : (float) $this->stock_minimo * 2;
        if ($max <= 0) {
            return 0;
        }

        return min(100, round(((float) $this->stock_actual / $max) * 100, 1));
    }

    /**
     * Categoría a la que pertenece este insumo.
     */
    public function categoriaInsumo(): BelongsTo
    {
        return $this->belongsTo(CategoriaInsumo::class, 'categoria_id');
    }

    /**
     * Ícono visual heredado de la categoría o fallback neutro.
     */
    public function getIconoAttribute(): string
    {
        return $this->categoriaInsumo?->icono ?: 'inventory_2';
    }

    /**
     * Color cromático heredado de la categoría o fallback neutro.
     */
    public function getColorAttribute(): string
    {
        return $this->categoriaInsumo?->color ?: '#6366f1';
    }

    /**
     * Nombre legible de la categoría.
     */
    public function getNombreCategoriaAttribute(): string
    {
        return $this->categoriaInsumo?->nombre ?: ($this->categoria ?: 'Sin categoría');
    }

    /**
     * Valor total monetario del stock actual de este insumo.
     */
    public function getValorStockAttribute(): float
    {
        return round((float) $this->stock_actual * (float) $this->costo_unitario, 2);
    }
}
