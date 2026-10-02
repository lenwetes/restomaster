<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sucursal_id',
    'numero',
    'capacidad',
    'zona',
    'estado',
    'mesero_id',
])]
class Mesa extends Model
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
            'capacidad' => 'integer',
        ];
    }

    protected function activa(): Attribute
    {
        return Attribute::make(get: fn () => true, set: function (mixed $value) {
            // La tabla mesas no almacena columna activa por separado
            return [];
        });
    }

    protected function activo(): Attribute
    {
        return Attribute::make(get: fn () => true, set: function (mixed $value) {
            // La tabla mesas no almacena columna activo por separado
            return [];
        });
    }

    protected function nombre(): Attribute
    {
        return Attribute::make(get: fn () => 'Mesa '.($this->numero ?? ''), set: function (mixed $value) {
            // La tabla mesas no almacena columna nombre (usa numero)
            return [];
        });
    }

    protected function zonaId(): Attribute
    {
        return Attribute::make(get: fn () => null, set: function (mixed $value) {
            // La tabla mesas usa columna string zona, no FK zona_id
            return [];
        });
    }

    /**
     * Get the waiter assigned to this table.
     */
    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mesero_id');
    }

    /**
     * Get the branch the table belongs to.
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Get the orders associated with the table.
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function reservas(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'reserva_mesa');
    }

    /**
     * Nombre para sala sin duplicar prefijo: el número ya suele traer
     * la zona ("Barra 1", "Mesa 1"), así que solo se antepone "Mesa"
     * cuando el número es pelado ("4").
     */
    protected function nombreSala(): Attribute
    {
        return Attribute::make(get: function () {
            $numero = trim((string) $this->numero);

            return preg_match('/^(mesa|barra|terraza|vip|patio|sal[oó]n)\b/i', $numero) ? $numero : 'Mesa '.$numero;
        });
    }

    /**
     * Etiqueta corta para insignias y círculos: último token del número
     * ("Barra 1" → "1", "4" → "4").
     */
    protected function nombreCorto(): Attribute
    {
        return Attribute::make(get: function () {
            $partes = preg_split('/\s+/', trim((string) $this->numero)) ?: [];

            return count($partes) > 1 ? (string) end($partes) : (string) $this->numero;
        });
    }
}
