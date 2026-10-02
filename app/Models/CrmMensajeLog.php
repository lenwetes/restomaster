<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
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
])]
#[Table(name: 'crm_mensajes_log')]
class CrmMensajeLog extends Model
{
    use HasFactory;

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

    #[Scope]
    protected function estado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }

    #[Scope]
    protected function canal(Builder $query, string $canal): Builder
    {
        return $query->where('canal', $canal);
    }

    protected function casts(): array
    {
        return [
            'enviado_en' => 'datetime',
            'entregado_en' => 'datetime',
            'leido_en' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
