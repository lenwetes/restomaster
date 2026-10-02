<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'proveedor_id',
    'numero_factura',
    'fecha',
    'subtotal',
    'forma_pago',
    'estado',
    'user_id',
])]
#[Table(name: 'compras')]
class Compra extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'subtotal' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(CompraLinea::class, 'compra_id');
    }

    public function cxp(): HasOne
    {
        return $this->hasOne(CuentaPorPagar::class, 'compra_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
