<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nombre',
    'direccion',
    'telefono',
    'nit_ruc',
    'activo',
])]
#[Table(name: 'sucursales')]
class Sucursal extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected function activa(): Attribute
    {
        return Attribute::make(get: fn () => (bool) ($this->attributes['activo'] ?? true), set: fn (mixed $value) => ['activo' => (bool) $value]);
    }

    protected function codigo(): Attribute
    {
        return Attribute::make(get: fn () => null, set: function (mixed $value) {
            // sucursales no tiene columna codigo en BD
            return [];
        });
    }

    protected function ciudad(): Attribute
    {
        return Attribute::make(get: fn () => null, set: function (mixed $value) {
            // sucursales no tiene columna ciudad en BD
            return [];
        });
    }

    /**
     * Get the tables for this branch.
     */
    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }

    /**
     * Get the users assigned to this branch.
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
