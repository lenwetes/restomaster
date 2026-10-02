<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'turno_caja_id',
    'user_id',
    'tipo',
    'concepto',
    'monto',
    'metodo_pago',
    'numero_comprobante',
    'autorizado_por',
])]
#[Table(name: 'movimientos_caja')]
class MovimientoCaja extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    public function turnoCaja(): BelongsTo
    {
        return $this->turno();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->usuario();
    }
}
