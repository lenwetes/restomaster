<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoDevolucion extends Model
{
    use HasFactory;

    protected $table = 'pedido_devoluciones';

    protected $fillable = [
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
    ];

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

    public function getMontoReembolsadoAttribute(): float
    {
        return (float) $this->monto_devuelto;
    }
}
