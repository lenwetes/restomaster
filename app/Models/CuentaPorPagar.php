<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'proveedor_nombre',
    'proveedor_nit',
    'numero_factura',
    'compra_id',
    'insumo_id',
    'concepto',
    'monto_total',
    'saldo_pendiente',
    'fecha_emision',
    'fecha_vencimiento',
    'estado',
    'notas',
    'user_id',
])]
#[Table(name: 'cuentas_por_pagar')]
class CuentaPorPagar extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCxp::class, 'cuenta_por_pagar_id');
    }
}
