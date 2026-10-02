<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sucursal_id',
    'zona_id',
    'zona_slug',
    'user_id',
    'turno',
    'orden',
    'activo',
    'ultimo_asignado_en',
])]
#[Table(name: 'rotaciones_meseros')]
class RotacionMesero extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
            'ultimo_asignado_en' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    #[Scope]
    protected function deSucursal(Builder $query, ?int $sucursalId = null): Builder
    {
        $id = $sucursalId ?? auth()->user()?->sucursal_id ?? 1;

        return $query->where('sucursal_id', $id);
    }

    #[Scope]
    protected function activos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    #[Scope]
    protected function deZona(Builder $query, string $zonaSlug): Builder
    {
        return $query->where('zona_slug', $zonaSlug);
    }
}
