<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promocion_id',
    'cliente_id',
    'pedido_id',
    'reserva_id',
    'canal',
    'codigo_cupon',
    'monto_descuento',
    'canjeado_at',
])]
#[Table(name: 'promocion_canjes')]
class PromocionCanje extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'monto_descuento' => 'decimal:2',
            'canjeado_at' => 'datetime',
        ];
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class);
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
}
