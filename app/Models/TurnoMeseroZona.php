<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sucursal_id',
    'zona_id',
    'mesero_id',
    'orden',
    'mesas_activas',
    'ultimo_asignado_en',
    'activo',
])]
#[Table(name: 'turno_mesero_zona')]
class TurnoMeseroZona extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'mesas_activas' => 'integer',
            'ultimo_asignado_en' => 'datetime',
            'activo' => 'boolean',
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
        return $this->belongsTo(User::class, 'mesero_id');
    }
}
