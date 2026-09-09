<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\DireccionCliente;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\TurnoCaja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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

            $codigo = 'DLV-' . strtoupper(substr(uniqid(), -5));
            $costoEnvio = (float) ($datos['costo_envio'] ?? 0);

            $pedido = Pedido::create([
                'codigo' => $codigo,
                'tipo' => 'delivery',
                'estado' => 'creado',
                'estado_delivery' => 'pendiente',
                'canal_origen' => $datos['canal_origen'] ?? 'pos',
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
            $pedido->update([
                'estado_delivery' => 'entregado',
                'estado' => 'entregado',
                'hora_entrega' => now(),
            ]);

            // Si se cobró contra entrega y el pedido aún no estaba pagado
            if ($metodoPago && $pedido->estado !== 'pagado') {
                $pedido->update([
                    'metodo_pago' => $metodoPago,
                    'monto_pagado' => $montoRecibido ?? $pedido->total,
                    'cambio' => $montoRecibido ? max(0, $montoRecibido - (float) $pedido->total) : 0,
                    'estado' => 'pagado',
                    'pagado_en' => now(),
                ]);

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
                ->get();

            $totalRecaudado = (float) $pedidosALiquidar->sum('total');

            if ($totalRecaudado > 0) {
                // Registrar ingreso en caja
                MovimientoCaja::create([
                    'turno_caja_id' => $turno->id,
                    'tipo' => 'ingreso',
                    'monto' => $totalRecaudado,
                    'concepto' => "Liquidación recaudo delivery motorizado: {$repartidor->name} ({$pedidosALiquidar->count()} pedidos)",
                    'user_id' => auth()->id() ?? $turno->user_id,
                ]);

                // Marcar pedidos como liquidados
                Pedido::whereIn('id', $pedidosALiquidar->pluck('id'))
                    ->update(['recaudo_liquidado' => true]);
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
            $q->whereIn('slug', ['repartidor', 'mesero', 'cajero', 'admin']);
        })->where('activo', true)->get();
    }
}
