<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramacionSemanal extends Model
{
    use HasFactory;

    protected $table = 'programaciones_semanales';

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PUBLICADO = 'publicado';

    public const ESTADO_ARCHIVADO = 'archivado';

    protected $fillable = [
        'semana_iso',
        'anio',
        'sucursal_id',
        'estado',
        'publicado_por',
        'publicado_en',
    ];

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

    public function scopeDeSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeBorrador(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_BORRADOR);
    }

    public function scopePublicadas(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_PUBLICADO);
    }

    public function esPublicada(): bool
    {
        return $this->estado === self::ESTADO_PUBLICADO;
    }
}
