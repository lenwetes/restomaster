<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'categoria_id',
        'nombre',
        'slug',
        'descripcion',
        'precio',
        'costo',
        'area_cocina',
        'activo',
        'imagen',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'costo' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Componentes de la receta (escandallo).
     */
    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'producto_id');
    }

    /**
     * Insumos utilizados en este plato.
     */
    public function insumos(): BelongsToMany
    {
        return $this->belongsToMany(Insumo::class, 'recetas', 'producto_id', 'insumo_id')
            ->withPivot(['cantidad', 'merma_esperada_pct', 'notas'])
            ->withTimestamps();
    }

    /**
     * Calcula el costo teórico total del plato sumando todos sus insumos con merma.
     */
    public function getCostoRecetaAttribute(): float
    {
        $this->loadMissing('recetas.insumo');

        return round($this->recetas->sum(fn ($receta) => $receta->costo_teorico), 2);
    }
}
