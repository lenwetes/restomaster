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
    'stock_actual',
    'stock_minimo',
    'capacidad_maxima',
    'costo_unitario',
    'ubicacion_almacen',
    'temperatura_almacen',
    'activo',
])]
#[Table(name: 'insumo_sucursales')]
class InsumoSucursal extends Model
{
    use HasFactory;

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'capacidad_maxima' => 'decimal:3',
            'costo_unitario' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }
}
