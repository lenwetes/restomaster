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
    'codigo',
    'tipo',
    'descripcion',
    'activa',
])]
#[Table(name: 'cajas')]
class Caja extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoCaja::class);
    }

    public function turnoActivo(): ?TurnoCaja
    {
        return $this->turnos()->where('estado', 'abierto')->latest()->first();
    }
}
