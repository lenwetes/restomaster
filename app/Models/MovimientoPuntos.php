<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'pedido_id',
    'tipo',
    'puntos',
    'saldo_anterior',
    'saldo_nuevo',
    'concepto',
    'usuario_id',
])]
#[Table(name: 'movimientos_puntos')]
class MovimientoPuntos extends Model
{
    use HasFactory;

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected function casts(): array
    {
        return [
            'puntos' => 'integer',
            'saldo_anterior' => 'integer',
            'saldo_nuevo' => 'integer',
        ];
    }
}
