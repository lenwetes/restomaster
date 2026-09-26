<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoMeseroZona extends Model
{
    use HasFactory;

    protected $table = 'turno_mesero_zona';

    protected $fillable = [
        'sucursal_id',
        'zona_id',
        'mesero_id',
        'orden',
        'mesas_activas',
        'ultimo_asignado_en',
        'activo',
    ];

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
