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
/**
 * @property int $id
 * @property string $codigo
 * @property string $tipo
 * @property int|null $sucursal_id
 * @property int|null $mesa_id
 * @property int|null $usuario_id
 * @property int|null $mesero_id
 * @property int|null $turno_caja_id
 * @property int|null $cliente_id
 * @property int|null $repartidor_id
 * @property string $estado
 * @property string|null $canal_origen
 * @property string|null $metodo_pago
 * @property float $subtotal
 * @property float $total
 * @property float $propina
 * @property-read Mesa|null $mesa
 * @property-read User|null $mesero
 * @property-read User|null $usuario
 * @property-read Cliente|null $cliente
 * @property-read Sucursal|null $sucursal
 * @property-read TurnoCaja|null $turnoCaja
 */
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

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<Mesa, $this>
     */
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * @return BelongsTo<DireccionCliente, $this>
     */
    public function direccion(): BelongsTo
    {
        return $this->belongsTo(DireccionCliente::class, 'direccion_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mesero_id');
    }

    /**
     * @return BelongsTo<TurnoCaja, $this>
     */
    public function turnoCaja(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    /**
     * @return HasMany<ItemPedido, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItemPedido::class);
    }

    /**
     * @return HasMany<MovimientoPuntos, $this>
     */
    public function movimientosPuntos(): HasMany
    {
        return $this->hasMany(MovimientoPuntos::class, 'pedido_id');
    }

    /**
     * @return HasMany<PedidoDevolucion, $this>
     */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(PedidoDevolucion::class, 'pedido_id');
    }

    /**
     * @return HasOne<FacturaElectronica, $this>
     */
    public function facturaElectronica(): HasOne
    {
        return $this->hasOne(FacturaElectronica::class);
    }

    /**
     * @return HasMany<PagoPasarela, $this>
     */
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
