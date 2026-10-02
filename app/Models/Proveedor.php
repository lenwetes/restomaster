<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nombre',
    'nit',
    'telefono',
    'email',
    'direccion',
    'contacto',
    'dias_credito',
    'activo',
])]
#[Table(name: 'proveedores')]
class Proveedor extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function insumos(): HasMany
    {
        return $this->hasMany(Insumo::class, 'proveedor_id');
    }
}
