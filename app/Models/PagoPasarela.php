<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoPasarela extends Model
{
    use HasFactory;

    protected $table = 'pago_pasarelas';

    protected $fillable = [
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
    ];

    protected $casts = [
        'monto' => 'float',
        'datos_transaccion' => 'array',
        'pagado_en' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
