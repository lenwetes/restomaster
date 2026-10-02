<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nombre',
    'slug',
    'icono',
    'color',
    'orden',
    'activo',
])]
#[Table(name: 'categorias')]
class Categoria extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class)->where('activo', true);
    }
}
