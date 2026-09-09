<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'codigo',
        'tipo',
        'estado',
        'mesa_id',
        'usuario_id',
        'turno_caja_id',
        'nombre_cliente',
        'telefono_cliente',
        'direccion_delivery',
        'cliente_id',
        'direccion_id',
        'repartidor_id',
        'estado_delivery',
        'canal_origen',
        'costo_envio',
        'puntos_ganados',
        'puntos_canjeados',
        'descuento_puntos',
        'recaudo_liquidado',
        'hora_despacho',
        'hora_entrega',
        'subtotal',
        'descuento',
        'total',
        'metodo_pago',
        'monto_pagado',
        'cambio',
        'notas',
        'pagado_en',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'costo_envio' => 'decimal:2',
            'descuento_puntos' => 'decimal:2',
            'total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'cambio' => 'decimal:2',
            'puntos_ganados' => 'integer',
            'puntos_canjeados' => 'integer',
            'recaudo_liquidado' => 'boolean',
            'hora_despacho' => 'datetime',
            'hora_entrega' => 'datetime',
            'pagado_en' => 'datetime',
        ];
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(DireccionCliente::class, 'direccion_id');
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function turnoCaja(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemPedido::class);
    }

    public function movimientosPuntos(): HasMany
    {
        return $this->hasMany(MovimientoPuntos::class, 'pedido_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereNotIn('estado', ['pagado', 'cancelado']);
    }

    public function scopeEnCocina(Builder $query): Builder
    {
        return $query->whereIn('estado', ['en_cocina', 'en_proceso', 'listo']);
    }

    public function scopeDelivery(Builder $query): Builder
    {
        return $query->where('tipo', 'delivery');
    }

    public function scopePendientesDelivery(Builder $query): Builder
    {
        return $query->where('tipo', 'delivery')
            ->whereIn('estado_delivery', ['pendiente', 'asignado', 'en_ruta']);
    }

    public function recalcularTotales(): void
    {
        $subtotal = $this->items()->sum('subtotal');
        $total = max(0, $subtotal + (float)($this->costo_envio ?? 0) - (float)$this->descuento - (float)($this->descuento_puntos ?? 0));

        $this->update([
            'subtotal' => $subtotal,
            'total' => $total,
        ]);
    }
}
