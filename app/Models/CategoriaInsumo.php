<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'nombre',
    'slug',
    'icono',
    'color',
    'descripcion',
    'orden',
    'activo',
])]
#[Table(name: 'categoria_insumos')]
class CategoriaInsumo extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($categoria) {
            if (empty($categoria->slug)) {
                $categoria->slug = Str::slug($categoria->nombre);
            }
            if (empty($categoria->color)) {
                $categoria->color = '#6366f1';
            }
            if (empty($categoria->icono)) {
                $categoria->icono = 'inventory_2';
            }
        });
    }

    public function insumos(): HasMany
    {
        return $this->hasMany(Insumo::class, 'categoria_id')->where('activo', true);
    }
}
