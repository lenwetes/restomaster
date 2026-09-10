<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'sucursal_id', 'cliente_id', 'nombre_contacto', 'telefono_contacto', 'email_contacto',
        'fecha', 'hora_llegada', 'duracion_min', 'personas', 'estado', 'origen',
        'notas', 'anticipo', 'confirmado_por', 'token_publico', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'anticipo' => 'decimal:2',
            'duracion_min' => 'integer',
            'personas' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($reserva) {
            if (empty($reserva->token_publico)) {
                $reserva->token_publico = Str::random(32);
            }
        });
    }

    public function mesas(): BelongsToMany
    {
        return $this->belongsToMany(Mesa::class, 'reserva_mesa');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
