<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ejecucion_id',
    'paso_id',
    'nodo_id',
    'estado',
    'input_payload',
    'output_payload',
    'error_mensaje',
    'iniciado_at',
    'finalizado_at',
    'duracion_ms',
    'intento',
])]
#[Table(name: 'automatizacion_ejecucion_pasos')]
class AutomatizacionEjecucionPaso extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'output_payload' => 'array',
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'duracion_ms' => 'integer',
            'intento' => 'integer',
        ];
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionEjecucion::class, 'ejecucion_id');
    }

    public function paso(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionPaso::class, 'paso_id');
    }

    public function iaSolicitudes(): HasMany
    {
        return $this->hasMany(AutomatizacionIaSolicitud::class, 'paso_id');
    }
}
