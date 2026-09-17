<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\DireccionCliente;
use App\Models\Pedido;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DeliveryService
{
    /**
     * Crea un pedido de tipo delivery con cliente, dirección y comanda.
     */
    public function crearPedidoDelivery(array $datos): Pedido
    {
        return DB::transaction(function () use ($datos) {
            $cliente = null;
            if (! empty($datos['cliente_id'])) {
                $cliente = Cliente::find($datos['cliente_id']);
            } elseif (! empty($datos['telefono_cliente'])) {
                $cliente = Cliente::where('telefono', trim($datos['telefono_cliente']))->first();
            }

            $direccionId = $datos['direccion_id'] ?? null;
            $direccionTexto = $datos['direccion_delivery'] ?? '';

            if ($cliente && $direccionId) {
                $dirModel = DireccionCliente::find($direccionId);
                if ($dirModel) {
                    $direccionTexto = $dirModel->direccion_completa;
                }
            } elseif ($cliente && ! $direccionTexto) {
                $dirPredeterminada = $cliente->direccionPredeterminada ?? $cliente->direcciones()->first();
                if ($dirPredeterminada) {
                    $direccionId = $dirPredeterminada->id;
                    $direccionTexto = $dirPredeterminada->direccion_completa;
                }
            }

            do {
                $codigo = 'DLV-'.date('Ymd-His').'-'.strtoupper(Str::random(6));
            } while (Pedido::where('codigo', $codigo)->exists());
            $canalOrigen = $datos['canal_origen'] ?? 'pos';
            $costoEnvio = $canalOrigen === 'web_delivery'
                ? (float) app(ConfiguracionService::class)->obtener('general', 'costo_envio_base', 8000.0)
                : max(0, (float) ($datos['costo_envio'] ?? 0));

            $sucursalId = $datos['sucursal_id']
                ?? auth()->user()?->sucursal_id
                ?? Sucursal::value('id')
                ?? 1;

            $pedido = Pedido::create([
                'codigo' => $codigo,
                'tipo' => 'delivery',
                'estado' => 'creado',
                'sucursal_id' => $sucursalId,
                'estado_delivery' => 'pendiente',
                'canal_origen' => $canalOrigen,
                'cliente_id' => $cliente?->id,
                'direccion_id' => $direccionId,
                'nombre_cliente' => $cliente?->nombre ?? ($datos['nombre_cliente'] ?? 'Cliente Delivery'),
                'telefono_cliente' => $cliente?->telefono ?? ($datos['telefono_cliente'] ?? null),
                'direccion_delivery' => $direccionTexto,
                'costo_envio' => $costoEnvio,
                'subtotal' => 0,
                'descuento' => 0,
                'total' => $costoEnvio,
                'notas' => $datos['notas'] ?? null,
                'usuario_id' => auth()->id() ?? ($datos['usuario_id'] ?? null),
                'turno_caja_id' => $datos['turno_caja_id'] ?? null,
            ]);

            return $pedido;
        });
    }

    /**
     * Asigna un repartidor/motorizado a la comanda de delivery.
     */
    public function asignarRepartidor(Pedido $pedido, User $repartidor): Pedido
    {
        if ($pedido->tipo !== 'delivery') {
            throw new InvalidArgumentException('El pedido no es de tipo delivery.');
        }

        $pedido->update([
            'repartidor_id' => $repartidor->id,
            'estado_delivery' => 'asignado',
        ]);

        return $pedido->fresh(['repartidor', 'cliente']);
    }

    /**
     * Marca la salida del motorizado con el pedido hacia el destino.
     */
    public function marcarSalida(Pedido $pedido): Pedido
    {
        if (! $pedido->repartidor_id) {
            throw new InvalidArgumentException('Debe asignar un repartidor antes de marcar la salida.');
        }

        $pedido->update([
            'estado_delivery' => 'en_ruta',
            'hora_despacho' => now(),
        ]);

        return $pedido->fresh();
    }

    /**
     * Marca el pedido como entregado en destino y procesa cobro si aplica.
     */
    public function marcarEntregado(Pedido $pedido, ?string $metodoPago = null, ?float $montoRecibido = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $metodoPago, $montoRecibido) {
            $yaPagado = $pedido->estado === 'pagado';

            $actualizacion = [
                'estado_delivery' => 'entregado',
                'hora_entrega' => now(),
            ];
            if (! $yaPagado && ! $metodoPago) {
                $actualizacion['estado'] = 'entregado';
            }
            $pedido->update($actualizacion);

            // Si se cobró contra entrega y el pedido aún no estaba pagado
            if ($metodoPago && ! $yaPagado) {
                if ($montoRecibido !== null && (float) $montoRecibido < (float) $pedido->total) {
                    throw new InvalidArgumentException("El monto recibido ({$montoRecibido}) no puede ser inferior al total del pedido ({$pedido->total}).");
                }

                $montoFinal = $montoRecibido ?? (float) $pedido->total;

                $pedido->update([
                    'estado' => 'pagado',
                    'metodo_pago' => $metodoPago,
                    'monto_pagado' => $montoFinal,
                    'cambio' => max(0, $montoFinal - (float) $pedido->total),
                    'pagado_en' => now(),
                ]);

                // Vincular el cobro al turno abierto de la sucursal
                $turnoActivo = TurnoCaja::where('estado', 'abierto')
                    ->when($pedido->sucursal_id, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $pedido->sucursal_id)))
                    ->latest()
                    ->first();
                if ($turnoActivo) {
                    app(CajaService::class)->vincularCobroPedido($turnoActivo, $pedido);
                }

                // Acumular puntos automáticamente
                app(FidelizacionService::class)->acumularPuntosPorPedido($pedido);

                // Descontar inventario si no se había descontado en KDS
                app(InventarioService::class)->descontarPorPedido($pedido);
            }

            return $pedido->fresh(['repartidor', 'cliente']);
        });
    }

    /**
     * Liquida el recaudo en efectivo traído por el repartidor en la caja del turno.
     */
    public function liquidarRecaudoRepartidor(User $repartidor, TurnoCaja $turno): float
    {
        return DB::transaction(function () use ($repartidor, $turno) {
            $pedidosALiquidar = Pedido::where('repartidor_id', $repartidor->id)
                ->where('tipo', 'delivery')
                ->where('metodo_pago', 'efectivo')
                ->where('estado_delivery', 'entregado')
                ->where('recaudo_liquidado', false)
                ->lockForUpdate()
                ->get();

            if ($pedidosALiquidar->isEmpty()) {
                return 0.0;
            }

            $pedidoIds = $pedidosALiquidar->pluck('id')->toArray();
            $updatedCount = Pedido::whereIn('id', $pedidoIds)
                ->where('recaudo_liquidado', false)
                ->update(['recaudo_liquidado' => true]);

            if ($updatedCount === 0) {
                return 0.0;
            }

            $totalRecaudado = (float) $pedidosALiquidar->sum('total');

            if ($totalRecaudado > 0) {
                app(CajaService::class)->registrarMovimiento(
                    $turno,
                    'ingreso',
                    $totalRecaudado,
                    "Liquidación recaudo delivery motorizado: {$repartidor->name} ({$updatedCount} pedidos)",
                    'efectivo',
                    null,
                    auth()->user()?->name ?? 'Sistema',
                    auth()->user()
                );
            }

            return $totalRecaudado;
        });
    }

    /**
     * Devuelve las métricas operativas de delivery en tiempo real.
     */
    public function obtenerMetricasDelivery(): array
    {
        $hoy = Carbon::today();

        $despachadosHoy = Pedido::where('tipo', 'delivery')
            ->whereDate('created_at', $hoy)
            ->count();

        $enRuta = Pedido::where('tipo', 'delivery')
            ->where('estado_delivery', 'en_ruta')
            ->count();

        $listosEnPase = Pedido::where('tipo', 'delivery')
            ->where('estado', 'listo')
            ->whereIn('estado_delivery', ['pendiente', 'asignado'])
            ->count();

        $tiempos = Pedido::where('tipo', 'delivery')
            ->whereDate('created_at', $hoy)
            ->whereNotNull('hora_entrega')
            ->get()
            ->map(function ($p) {
                return $p->created_at->diffInMinutes($p->hora_entrega);
            });

        $tiempoPromedioMin = $tiempos->count() > 0 ? (int) round($tiempos->avg()) : 28;

        $recaudoPendiente = (float) Pedido::where('tipo', 'delivery')
            ->where('metodo_pago', 'efectivo')
            ->whereIn('estado_delivery', ['en_ruta', 'entregado'])
            ->where('recaudo_liquidado', false)
            ->sum('total');

        return [
            'despachados_hoy' => $despachadosHoy,
            'en_ruta' => $enRuta,
            'listos_en_pase' => $listosEnPase,
            'tiempo_promedio_min' => $tiempoPromedioMin,
            'recaudo_pendiente' => $recaudoPendiente,
        ];
    }

    /**
     * Obtiene la flota de motorizados y sus pedidos activos.
     */
    public function obtenerFlotaMotorizados(): Collection
    {
        return User::whereHas('role', function ($q) {
            $q->whereIn('slug', ['delivery', 'repartidor', 'mesero', 'cajero', 'admin']);
        })
            ->where('activo', true)
            ->withCount([
                'pedidosRepartidor as pedidos_en_ruta_count' => fn ($q) => $q->where('estado_delivery', 'en_ruta'),
            ])
            ->withSum([
                'pedidosRepartidor as efectivo_pendiente_sum' => fn ($q) => $q->where('tipo', 'delivery')
                    ->where('metodo_pago', 'efectivo')
                    ->where('estado_delivery', 'entregado')
                    ->where('recaudo_liquidado', false),
            ], 'total')
            ->get();
    }
}
