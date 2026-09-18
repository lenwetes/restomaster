<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CategoriaInsumo extends Model
{
    use HasFactory;

    protected $table = 'categoria_insumos';

    protected $fillable = [
        'nombre',
        'slug',
        'icono',
        'color',
        'descripcion',
        'orden',
        'activo',
    ];

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
