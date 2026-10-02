<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sucursal_id',
    'insumo_id',
    'tipo',
    'cantidad',
    'saldo_anterior',
    'saldo_posterior',
    'costo_unitario',
    'costo_total',
    'pedido_id',
    'user_id',
    'motivo',
    'referencia_documento',
])]
#[Table(name: 'movimientos_inventario')]
class MovimientoInventario extends Model
{
    use HasFactory;

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'saldo_anterior' => 'decimal:3',
            'saldo_posterior' => 'decimal:3',
            'costo_unitario' => 'decimal:2',
            'costo_total' => 'decimal:2',
        ];
    }
}
