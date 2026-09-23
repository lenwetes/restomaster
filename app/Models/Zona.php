<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zona extends Model
{
    use HasFactory;

    /**
     * Clave de paleta => clases Tailwind del tema Aura (única fuente de color del mapa).
     *
     * @var array<string, array{tinte: string, punto: string, pastilla: string}>
     */
    public const PALETA = [
        'terracota' => ['tinte' => 'bg-primary-container/70', 'punto' => 'bg-primary', 'pastilla' => 'bg-primary'],
        'salvia' => ['tinte' => 'bg-secondary-container/70', 'punto' => 'bg-secondary', 'pastilla' => 'bg-secondary'],
        'lavanda' => ['tinte' => 'bg-tertiary-container/70', 'punto' => 'bg-tertiary', 'pastilla' => 'bg-tertiary'],
        'ambar' => ['tinte' => 'bg-amber-200/70', 'punto' => 'bg-amber-600', 'pastilla' => 'bg-amber-600'],
        'esmeralda' => ['tinte' => 'bg-emerald-200/70', 'punto' => 'bg-emerald-600', 'pastilla' => 'bg-emerald-600'],
        'indigo' => ['tinte' => 'bg-indigo-200/70', 'punto' => 'bg-indigo-600', 'pastilla' => 'bg-indigo-600'],
        'rosa' => ['tinte' => 'bg-rose-200/70', 'punto' => 'bg-rose-600', 'pastilla' => 'bg-rose-600'],
        'pizarra' => ['tinte' => 'bg-surface-container-high/60', 'punto' => 'bg-outline-variant', 'pastilla' => 'bg-status-cleaning'],
    ];

    public const ICONOS = [
        'mesa' => 'table_restaurant',
        'barra' => 'local_bar',
        'terraza' => 'deck',
        'vip' => 'diamond',
        'patio' => 'outdoor_garden',
        'jardin' => 'park',
        'balcon' => 'balcony',
        'privado' => 'meeting_room',
    ];

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'slug',
        'color',
        'icono',
        'orden',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function scopeDeSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}
