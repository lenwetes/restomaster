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
     * Líneas de comanda / pedidos donde se ha ordenado este producto.
     */
    public function itemsPedido(): HasMany
    {
        return $this->hasMany(ItemPedido::class, 'producto_id');
    }

    /**
     * Calcula el costo teórico total del plato sumando todos sus insumos con merma.
     */
    public function getCostoRecetaAttribute(): float
    {
        $this->loadMissing('recetas.insumo');

        return round($this->recetas->sum(fn ($receta) => $receta->costo_teorico), 2);
    }

    /**
     * Resuelve la URL pública de la imagen del producto (URL absoluta, path relativo o storage).
     */
    public function getImagenUrlAttribute(): ?string
    {
        if (empty($this->imagen)) {
            return null;
        }

        if (str_starts_with($this->imagen, 'http://') || str_starts_with($this->imagen, 'https://') || str_starts_with($this->imagen, '/')) {
            return $this->imagen;
        }

        return asset('storage/'.$this->imagen);
    }
}
