<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'semana_iso',
    'anio',
    'sucursal_id',
    'estado',
    'publicado_por',
    'publicado_en',
])]
#[Table(name: 'programaciones_semanales')]
class ProgramacionSemanal extends Model
{
    use HasFactory;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PUBLICADO = 'publicado';

    public const ESTADO_ARCHIVADO = 'archivado';

    protected function casts(): array
    {
        return [
            'semana_iso' => 'integer',
            'anio' => 'integer',
            'publicado_en' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoMeseroSemana::class, 'programacion_semanal_id');
    }

    #[Scope]
    protected function deSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    #[Scope]
    protected function borrador(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_BORRADOR);
    }

    #[Scope]
    protected function publicadas(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_PUBLICADO);
    }

    public function esPublicada(): bool
    {
        return $this->estado === self::ESTADO_PUBLICADO;
    }
}
