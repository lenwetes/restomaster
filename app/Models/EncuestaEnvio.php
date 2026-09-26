<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EncuestaEnvio extends Model
{
    use HasFactory;

    protected $table = 'encuesta_envios';

    protected $fillable = [
        'encuesta_id',
        'cliente_id',
        'pedido_id',
        'reserva_id',
        'token',
        'estado',
        'enviada_en',
        'respondida_en',
        'expira_en',
    ];

    protected function casts(): array
    {
        return [
            'enviada_en' => 'datetime',
            'respondida_en' => 'datetime',
            'expira_en' => 'datetime',
        ];
    }

    public function encuesta(): BelongsTo
    {
        return $this->belongsTo(Encuesta::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EncuestaRespuesta::class, 'envio_id');
    }
}
