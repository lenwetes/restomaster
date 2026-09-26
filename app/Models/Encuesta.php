<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encuesta extends Model
{
    use HasFactory;

    protected $table = 'encuestas';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'activa',
        'disparador',
        'delay_horas',
        'preguntas',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'delay_horas' => 'integer',
            'preguntas' => 'array',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function envios(): HasMany
    {
        return $this->hasMany(EncuestaEnvio::class);
    }
}
