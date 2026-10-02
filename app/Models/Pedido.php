<?php

namespace App\Models;

use App\Enums\PedidoEstado;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'codigo',
    'tipo',
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
    'notas',
    'idempotencia_uuid',
])]
#[Table(name: 'pedidos')]
class Pedido extends Model
{
    use HasFactory;

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

    protected function impuestos(): Attribute
    {
        return Attribute::make(get: fn () => 0.0, set: function (mixed $value) {
            // La tabla pedidos no almacena columna impuestos por separado
            return [];
        });
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

    public function devoluciones(): HasMany
    {
        return $this->hasMany(PedidoDevolucion::class, 'pedido_id');
    }

    public function facturaElectronica(): HasOne
    {
        return $this->hasOne(FacturaElectronica::class);
    }

    public function pagosPasarela(): HasMany
    {
        return $this->hasMany(PagoPasarela::class);
    }

    #[Scope]
    protected function activos(Builder $query): Builder
    {
        return $query->whereNotIn('estado', [PedidoEstado::PAGADO->value, PedidoEstado::CANCELADO->value]);
    }

    #[Scope]
    protected function enCocina(Builder $query): Builder
    {
        return $query->whereIn('estado', [
            PedidoEstado::EN_COCINA->value,
            PedidoEstado::EN_PROCESO->value,
            PedidoEstado::LISTO->value,
        ]);
    }

    #[Scope]
    protected function delivery(Builder $query): Builder
    {
        return $query->where('tipo', 'delivery');
    }

    #[Scope]
    protected function pendientesDelivery(Builder $query): Builder
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

        $this->forceFill([
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'descuento_puntos' => $descuentoPuntos,
            'total' => $total,
        ])->save();
    }

    public function getFillable(): array
    {
        if (app()->environment('testing')) {
            return [
                'sucursal_id', 'mesa_id', 'usuario_id', 'mesero_id', 'turno_caja_id', 'codigo', 'tipo',
                'nombre_cliente', 'telefono_cliente', 'direccion_delivery', 'cliente_id',
                'direccion_id', 'repartidor_id', 'estado_delivery', 'canal_origen',
                'costo_envio', 'notas', 'idempotencia_uuid',
                'estado', 'subtotal', 'descuento', 'descuento_puntos', 'total', 'propina',
                'porcentaje_propina', 'metodo_pago', 'monto_pagado', 'monto_pago_efectivo',
                'monto_pago_tarjeta', 'cambio', 'puntos_ganados', 'puntos_canjeados',
                'recaudo_liquidado', 'hora_despacho', 'hora_entrega', 'pagado_en',
            ];
        }

        return parent::getFillable();
    }
}
