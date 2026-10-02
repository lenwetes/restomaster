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
    'zona_id',
    'activa',
    'modo',
])]
#[Table(name: 'rotaciones_zona')]
class RotacionZona extends Model
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

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoMeseroZona::class, 'zona_id', 'zona_id')
            ->orderBy('orden', 'asc');
    }
}
