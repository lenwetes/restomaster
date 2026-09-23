<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mesa extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sucursal_id',
        'numero',
        'capacidad',
        'zona',
        'estado',
        'mesero_id',
    ];

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
    public function getNombreSalaAttribute(): string
    {
        $numero = trim((string) $this->numero);

        return preg_match('/^(mesa|barra|terraza|vip|patio|sal[oó]n)\b/i', $numero) ? $numero : 'Mesa '.$numero;
    }

    /**
     * Etiqueta corta para insignias y círculos: último token del número
     * ("Barra 1" → "1", "4" → "4").
     */
    public function getNombreCortoAttribute(): string
    {
        $partes = preg_split('/\s+/', trim((string) $this->numero)) ?: [];

        return count($partes) > 1 ? (string) end($partes) : (string) $this->numero;
    }
}
