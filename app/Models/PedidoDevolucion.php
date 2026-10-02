<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_id',
    'item_pedido_id',
    'producto_id',
    'cantidad',
    'monto_devuelto',
    'motivo',
    'metodo_reembolso',
    'turno_caja_id',
    'movimiento_caja_id',
    'asiento_contable_id',
    'autorizado_por',
    'user_id',
])]
#[Table(name: 'pedido_devoluciones')]
class PedidoDevolucion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'monto_devuelto' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function itemPedido(): BelongsTo
    {
        return $this->belongsTo(ItemPedido::class, 'item_pedido_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function turnoCaja(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class, 'movimiento_caja_id');
    }

    public function asientoContable(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_contable_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function montoReembolsado(): Attribute
    {
        return Attribute::make(get: fn () => (float) $this->monto_devuelto);
    }
}
