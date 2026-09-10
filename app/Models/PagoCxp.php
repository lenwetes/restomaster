<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoCxp extends Model
{
    use HasFactory;

    protected $table = 'pagos_cxps';

    protected $fillable = [
        'cuenta_por_pagar_id',
        'user_id',
        'monto',
        'metodo_pago',
        'fecha_pago',
        'concepto',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
        ];
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
