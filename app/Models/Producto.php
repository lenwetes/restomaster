<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $categoria_id
 * @property string $nombre
 * @property string $slug
 * @property string|null $descripcion
 * @property float $precio
 * @property float|null $costo
 * @property string|null $area_cocina
 * @property bool $activo
 * @property string|null $imagen
 */
#[Fillable([
    'categoria_id',
    'nombre',
    'slug',
    'descripcion',
    'precio',
    'costo',
    'area_cocina',
    'activo',
    'imagen',
])]
#[Table(name: 'productos')]
class Producto extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'costo' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function setCategoriaAttribute(mixed $value): void
    {
        if (is_numeric($value)) {
            $this->attributes['categoria_id'] = (int) $value;
        } elseif (is_string($value)) {
            $cat = Categoria::where('slug', $value)->orWhere('nombre', $value)->first();
            if ($cat) {
                $this->attributes['categoria_id'] = $cat->id;
            }
        }
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
    protected function costoReceta(): Attribute
    {
        return Attribute::make(get: function () {
            $this->loadMissing('recetas.insumo');

            return round($this->recetas->sum(fn ($receta) => $receta->costo_teorico), 2);
        });
    }

    /**
     * Resuelve el path de imagen, con fallback a imagen demo por slug si es nula.
     */
    protected function imagen(): Attribute
    {
        return Attribute::make(get: function (?string $value) {
            if (! empty($value)) {
                return $value;
            }

            return $this->resolverRutaImagenDemo();
        });
    }

    /**
     * Resuelve la URL pública de la imagen del producto (URL absoluta, path relativo o storage).
     */
    protected function imagenUrl(): Attribute
    {
        return Attribute::make(get: function () {
            $img = $this->imagen;
            if (empty($img)) {
                return null;
            }
            if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
                return $img;
            }
            if (str_starts_with($img, '/')) {
                return asset(ltrim($img, '/'));
            }

            return asset('storage/'.$img);
        });
    }

    /**
     * Busca la imagen del plato en el directorio public/demo/platos/
     */
    protected function resolverRutaImagenDemo(): ?string
    {
        if (empty($this->slug)) {
            return null;
        }

        $candidatos = [
            $this->slug.'.jpg',
            $this->slug.'-350g.jpg',
            $this->slug.'.png',
            $this->slug.'.webp',
        ];

        foreach ($candidatos as $archivo) {
            if (file_exists(public_path('demo/platos/'.$archivo))) {
                return '/demo/platos/'.$archivo;
            }
        }

        return null;
    }
}
