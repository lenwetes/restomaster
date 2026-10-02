<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nombre',
    'hora_inicio',
    'hora_fin',
    'zona_default_id',
    'sucursal_id',
    'activo',
])]
#[Table(name: 'plantillas_turnos')]
class PlantillaTurno extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'hora_inicio' => 'datetime:H:i',
            'hora_fin' => 'datetime:H:i',
            'activo' => 'boolean',
        ];
    }

    public function zonaDefault(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zona_default_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
