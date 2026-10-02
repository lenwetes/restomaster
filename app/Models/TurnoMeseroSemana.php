<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'programacion_semanal_id',
    'user_id',
    'fecha',
    'zona_id',
    'plantilla_turno_id',
    'mesas_especificas',
    'es_descanso',
    'notificado_login_en',
    'confirmado_por_mesero_en',
])]
#[Table(name: 'turnos_meseros_semana')]
class TurnoMeseroSemana extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'mesas_especificas' => 'array',
            'es_descanso' => 'boolean',
            'notificado_login_en' => 'datetime',
            'confirmado_por_mesero_en' => 'datetime',
        ];
    }

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(ProgramacionSemanal::class, 'programacion_semanal_id');
    }

    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaTurno::class, 'plantilla_turno_id');
    }
}
