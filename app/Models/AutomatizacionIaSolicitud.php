<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ejecucion_id',
    'paso_id',
    'prompt_generado',
    'prompt_sistema',
    'modelo_usado',
    'respuesta_cruda',
    'respuesta_parseada',
    'tokens_entrada',
    'tokens_salida',
    'duracion_ms',
    'exitoso',
    'error_mensaje',
])]
#[Table(name: 'automatizacion_ia_solicitudes')]
class AutomatizacionIaSolicitud extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'respuesta_parseada' => 'array',
            'tokens_entrada' => 'integer',
            'tokens_salida' => 'integer',
            'duracion_ms' => 'integer',
            'exitoso' => 'boolean',
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
}
