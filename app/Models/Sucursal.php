<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sucursales';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'nit_ruc',
        'activo',
    ];

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
