<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DireccionCliente extends Model
{
    use HasFactory;

    protected $table = 'direcciones_cliente';

    protected $fillable = [
        'cliente_id',
        'etiqueta',
        'direccion',
        'referencia_apto',
        'barrio_ciudad',
        'telefono_contacto',
        'notas_entrega',
        'es_predeterminada',
    ];

    protected $casts = [
        'es_predeterminada' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function getDireccionCompletaAttribute(): string
    {
        $partes = array_filter([
            $this->direccion,
            $this->referencia_apto,
            $this->barrio_ciudad,
        ]);

        return implode(' · ', $partes);
    }
}
