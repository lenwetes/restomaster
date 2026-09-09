<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoPuntos extends Model
{
    use HasFactory;

    protected $table = 'movimientos_puntos';

    protected $fillable = [
        'cliente_id',
        'pedido_id',
        'tipo',
        'puntos',
        'saldo_anterior',
        'saldo_nuevo',
        'concepto',
        'usuario_id',
    ];

    protected $casts = [
        'puntos' => 'integer',
        'saldo_anterior' => 'integer',
        'saldo_nuevo' => 'integer',
    ];

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
}
