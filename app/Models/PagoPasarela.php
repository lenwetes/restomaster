<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_id',
    'sucursal_id',
    'proveedor',
    'transaccion_id',
    'referencia',
    'monto',
    'moneda',
    'metodo_pasarela',
    'terminal_id',
    'estado',
    'checkout_url',
    'qr_cadena',
    'qr_imagen',
    'firma_integridad',
    'datos_transaccion',
    'pagado_en',
])]
#[Table(name: 'pago_pasarelas')]
class PagoPasarela extends Model
{
    use HasFactory;

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',  // decimal exacto — nunca float en pagos
            'datos_transaccion' => 'array',
            'pagado_en' => 'datetime',
        ];
    }
}
