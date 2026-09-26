<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmMensajeLog extends Model
{
    use HasFactory;

    protected $table = 'crm_mensajes_log';

    protected $fillable = [
        'automatizacion_id',
        'cliente_id',
        'pedido_id',
        'reserva_id',
        'canal',
        'destinatario',
        'asunto',
        'contenido_enviado',
        'estado',
        'mensaje_id_externo',
        'error_mensaje',
        'enviado_en',
        'entregado_en',
        'leido_en',
        'metadata',
    ];

    protected $casts = [
        'enviado_en' => 'datetime',
        'entregado_en' => 'datetime',
        'leido_en' => 'datetime',
        'metadata' => 'array',
    ];

    public function automatizacion(): BelongsTo
    {
        return $this->belongsTo(CrmAutomatizacion::class, 'automatizacion_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function scopeEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }

    public function scopeCanal(Builder $query, string $canal): Builder
    {
        return $query->where('canal', $canal);
    }
}
