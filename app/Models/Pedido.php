<?php

namespace App\Models;

use App\Enums\PedidoEstado;
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
        'sucursal_id',
        'mesa_id',
        'usuario_id',
        'mesero_id',
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
        'propina',
        'porcentaje_propina',
        'metodo_pago',
        'monto_pagado',
        'monto_pago_efectivo',
        'monto_pago_tarjeta',
        'cambio',
        'notas',
        'pagado_en',
        'idempotencia_uuid',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'costo_envio' => 'decimal:2',
            'descuento_puntos' => 'decimal:2',
            'total' => 'decimal:2',
            'propina' => 'decimal:2',
            'porcentaje_propina' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'monto_pago_efectivo' => 'decimal:2',
            'monto_pago_tarjeta' => 'decimal:2',
            'cambio' => 'decimal:2',
            'puntos_ganados' => 'integer',
            'puntos_canjeados' => 'integer',
            'recaudo_liquidado' => 'boolean',
            'hora_despacho' => 'datetime',
            'hora_entrega' => 'datetime',
            'pagado_en' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
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

    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mesero_id');
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
        return $query->whereNotIn('estado', [PedidoEstado::PAGADO->value, PedidoEstado::CANCELADO->value]);
    }

    public function scopeEnCocina(Builder $query): Builder
    {
        return $query->whereIn('estado', [
            PedidoEstado::EN_COCINA->value,
            PedidoEstado::EN_PROCESO->value,
            PedidoEstado::LISTO->value,
        ]);
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
        $subtotal = (float) $this->items()->sum('subtotal');
        $descuento = min($subtotal, (float) ($this->descuento ?? 0));
        $remanente = max(0, $subtotal - $descuento);
        $descuentoPuntos = min($remanente, (float) ($this->descuento_puntos ?? 0));
        $envio = (float) ($this->costo_envio ?? 0);
        $total = max(0, $subtotal + $envio - $descuento - $descuentoPuntos);

        $this->update([
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'descuento_puntos' => $descuentoPuntos,
            'total' => $total,
        ]);
    }
}
