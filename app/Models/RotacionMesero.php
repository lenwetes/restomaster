<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotacionMesero extends Model
{
    use HasFactory;

    protected $table = 'rotaciones_meseros';

    protected $fillable = [
        'sucursal_id',
        'zona_id',
        'zona_slug',
        'user_id',
        'turno',
        'orden',
        'activo',
        'ultimo_asignado_en',
    ];

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

    public function scopeDeSucursal(Builder $query, ?int $sucursalId = null): Builder
    {
        $id = $sucursalId ?? auth()->user()?->sucursal_id ?? 1;

        return $query->where('sucursal_id', $id);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeDeZona(Builder $query, string $zonaSlug): Builder
    {
        return $query->where('zona_slug', $zonaSlug);
    }
}
