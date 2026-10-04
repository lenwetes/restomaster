<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'flujo_id',
    'version_id',
    'programacion_id',
    'disparador',
    'disparador_payload',
    'estado',
    'contexto_variables',
    'error_mensaje',
    'iniciado_at',
    'finalizado_at',
    'duracion_ms',
    'intentos',
])]
#[Table(name: 'automatizacion_ejecuciones')]
class AutomatizacionEjecucion extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EN_EJECUCION = 'en_ejecucion';

    public const ESTADO_COMPLETADO = 'completado';

    public const ESTADO_FALLIDO = 'fallido';

    public const ESTADO_CANCELADO = 'cancelado';

    protected static function booted(): void
    {
        static::creating(function ($ejecucion) {
            if (empty($ejecucion->uuid)) {
                $ejecucion->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'disparador_payload' => 'array',
            'contexto_variables' => 'array',
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'duracion_ms' => 'integer',
            'intentos' => 'integer',
        ];
    }

    public function flujo(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionFlujo::class, 'flujo_id');
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(AutomatizacionEjecucionPaso::class, 'ejecucion_id')->orderBy('id');
    }

    public function iaSolicitudes(): HasMany
    {
        return $this->hasMany(AutomatizacionIaSolicitud::class, 'ejecucion_id');
    }
}
