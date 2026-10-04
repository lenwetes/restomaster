<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sucursal_id',
    'nombre',
    'descripcion',
    'estado',
    'disparador_tipo',
    'disparador_clave',
    'disparador_config',
    'condiciones',
    'version_actual',
    'origen',
    'plantilla_id',
    'max_ejecuciones_dia',
    'cooldown_minutos',
    'respetar_horario_antispam',
    'creado_por',
    'actualizado_por',
])]
#[Table(name: 'automatizacion_flujos')]
class AutomatizacionFlujo extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'disparador_config' => 'array',
            'condiciones' => 'array',
            'version_actual' => 'integer',
            'max_ejecuciones_dia' => 'integer',
            'cooldown_minutos' => 'integer',
            'respetar_horario_antispam' => 'boolean',
            'total_ejecuciones' => 'integer',
            'total_exitosas' => 'integer',
            'total_fallidas' => 'integer',
            'ultima_ejecucion_at' => 'datetime',
        ];
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(AutomatizacionPaso::class, 'flujo_id')->orderBy('orden');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }
}
