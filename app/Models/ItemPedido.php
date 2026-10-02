<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'pedido_id',
    'producto_id',
    'nombre_producto',
    'cantidad',
    'precio_unitario',
    'subtotal',
    'area_cocina',
    'estado_cocina',
    'notas',
    'inventario_descontado',
    'cantidad_devuelta',
    'iniciado_en',
    'listo_en',
])]
#[Table(name: 'items_pedido')]
class ItemPedido extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'cantidad_devuelta' => 'integer',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'inventario_descontado' => 'boolean',
            'iniciado_en' => 'datetime',
            'listo_en' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(PedidoDevolucion::class, 'item_pedido_id');
    }

    public function cantidadDisponibleDevolucion(): int
    {
        return max(0, (int) $this->cantidad - (int) ($this->cantidad_devuelta ?? 0));
    }
}
