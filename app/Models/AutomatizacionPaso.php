<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'flujo_id',
    'orden',
    'tipo',
    'nombre',
    'config',
    'variable_salida',
    'paso_si_id',
    'paso_no_id',
])]
#[Table(name: 'automatizacion_pasos')]
class AutomatizacionPaso extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'config' => 'array',
            'reintentos_max' => 'integer',
            'reintentos_delay_segundos' => 'integer',
            'timeout_segundos' => 'integer',
            'continuar_en_error' => 'boolean',
        ];
    }

    public function flujo(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionFlujo::class, 'flujo_id');
    }

    public function ejecucionesPaso(): HasMany
    {
        return $this->hasMany(AutomatizacionEjecucionPaso::class, 'paso_id');
    }
}
