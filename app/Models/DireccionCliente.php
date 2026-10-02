<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'etiqueta',
    'direccion',
    'referencia_apto',
    'barrio_ciudad',
    'telefono_contacto',
    'notas_entrega',
    'es_predeterminada',
])]
#[Table(name: 'direcciones_cliente')]
class DireccionCliente extends Model
{
    use HasFactory;

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    protected function direccionCompleta(): Attribute
    {
        return Attribute::make(get: function () {
            $partes = array_filter([
                $this->direccion,
                $this->referencia_apto,
                $this->barrio_ciudad,
            ]);

            return implode(' · ', $partes);
        });
    }

    protected function casts(): array
    {
        return [
            'es_predeterminada' => 'boolean',
        ];
    }
}
