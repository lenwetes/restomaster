<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PedidoService
{
    /**
     * Crear un nuevo pedido con sus items asociados.
     */
    public function crearPedido(array $datos, array $items, ?User $usuario = null): Pedido
    {
        return DB::transaction(function () use ($datos, $items, $usuario) {
            $codigo = 'ORD-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $pedido = Pedido::create([
                'codigo' => $codigo,
                'tipo' => $datos['tipo'] ?? 'mesa',
                'estado' => $datos['estado'] ?? 'creado',
                'mesa_id' => $datos['mesa_id'] ?? null,
                'usuario_id' => $usuario?->id ?? auth()->id(),
                'cliente_id' => $datos['cliente_id'] ?? null,
                'nombre_cliente' => $datos['nombre_cliente'] ?? null,
                'telefono_cliente' => $datos['telefono_cliente'] ?? null,
                'direccion_delivery' => $datos['direccion_delivery'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'descuento' => $datos['descuento'] ?? 0,
                'canal_origen' => $datos['canal_origen'] ?? 'pos',
            ]);

            $subtotal = 0;
            $descuentoSolicitado = max(0, (float) ($datos['descuento'] ?? 0));
            $costoEnvio = max(0, (float) ($datos['costo_envio'] ?? 0));
            $descuentoPuntos = max(0, (float) ($datos['descuento_puntos'] ?? 0));

            foreach ($items as $itemData) {
                $producto = Producto::findOrFail($itemData['producto_id']);
                $cantidad = max(1, (int) ($itemData['cantidad'] ?? 1));
                // C1 FIX: El precio unitario SIEMPRE se toma de la base de datos, nunca del cliente
                $precioUnitario = (float) $producto->precio;
                $itemSubtotal = $precioUnitario * $cantidad;

                ItemPedido::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $producto->id,
                    'nombre_producto' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $itemSubtotal,
                    'area_cocina' => $producto->area_cocina ?? 'sushi',
                    'estado_cocina' => 'pendiente',
                    'notas' => $itemData['notas'] ?? null,
                ]);

                $subtotal += $itemSubtotal;
            }

            // C2 & M2 FIX: Descuento acotado al subtotal y fórmula unificada con envío y puntos
            $descuentoAplicado = min($subtotal, $descuentoSolicitado);
            $total = max(0, $subtotal + $costoEnvio - $descuentoAplicado - $descuentoPuntos);

            $pedido->update([
                'subtotal' => $subtotal,
                'descuento' => $descuentoAplicado,
                'costo_envio' => $costoEnvio,
                'descuento_puntos' => $descuentoPuntos,
                'total' => $total,
            ]);

            // Si es pedido de mesa, actualizar la mesa a 'ocupada'
            if (! empty($pedido->mesa_id)) {
                $mesa = Mesa::find($pedido->mesa_id);
                if ($mesa && $mesa->estado === MesaEstado::LIBRE->value) {
                    $mesa->update(['estado' => MesaEstado::OCUPADA->value]);
                }
            }

            return $pedido->fresh(['items', 'mesa']);
        });
    }

    /**
     * Enviar comanda a cocina.
     */
    public function enviarACocina(Pedido $pedido): Pedido
    {
        $pedido->update(['estado' => 'en_cocina']);

        $pedido->items()->where('estado_cocina', 'pendiente')->update([
            'estado_cocina' => 'en_preparacion',
            'iniciado_en' => now(),
        ]);

        // Despachar comanda a las impresoras térmicas de cocina por estación
        app(ImpresionService::class)->despacharComandaCocina($pedido);

        return $pedido->fresh('items');
    }

    /**
     * Marcar un item de comanda como listo para servir.
     */
    public function marcarItemListo(ItemPedido $item): ItemPedido
    {
        $item->update([
            'estado_cocina' => 'listo',
            'listo_en' => now(),
        ]);

        // Descontar materia prima e insumos de la receta en inventario
        app(InventarioService::class)->descontarPorItemPedido($item);

        $pedido = $item->pedido;
        $itemsPendientes = $pedido->items()
            ->whereNotIn('estado_cocina', ['listo', 'entregado', 'cancelado'])
            ->count();

        if ($itemsPendientes === 0 && in_array($pedido->estado, ['creado', 'en_cocina'])) {
            $pedido->update(['estado' => 'listo']);
        }

        return $item->fresh();
    }

    /**
     * Marcar un item como entregado al cliente/mesa.
     */
    public function marcarItemEntregado(ItemPedido $item): ItemPedido
    {
        $item->update(['estado_cocina' => 'entregado']);

        $pedido = $item->pedido;
        $itemsNoEntregados = $pedido->items()
            ->whereNotIn('estado_cocina', ['entregado', 'cancelado'])
            ->count();

        if ($itemsNoEntregados === 0 && $pedido->estado !== 'pagado') {
            $pedido->update(['estado' => 'entregado']);
        }

        return $item->fresh();
    }

    /**
     * Procesar cobro y cierre de un pedido.
     */
    public function cobrarPedido(Pedido $pedido, string $metodoPago, float $montoPagado): Pedido
    {
        return DB::transaction(function () use ($pedido, $metodoPago, $montoPagado) {
            // H5 FIX: Bloqueo pesimista e idempotencia para evitar cobros dobles por race condition
            $pedido = Pedido::where('id', $pedido->id)->lockForUpdate()->firstOrFail();
            abort_if($pedido->estado === 'pagado', 400, 'El pedido ya se encuentra pagado.');

            $cambio = max(0, $montoPagado - (float) $pedido->total);

            $pedido->update([
                'estado' => 'pagado',
                'metodo_pago' => $metodoPago,
                'monto_pagado' => $montoPagado,
                'cambio' => $cambio,
                'pagado_en' => now(),
            ]);

            // Si tiene mesa asignada, pasa a 'por_limpiar'
            if ($pedido->mesa_id) {
                $mesa = Mesa::find($pedido->mesa_id);
                if ($mesa) {
                    $mesa->update(['estado' => MesaEstado::POR_LIMPIAR->value]);
                }
            }

            // Vincular automáticamente con turno de caja abierto
            $turnoActivo = TurnoCaja::where('estado', 'abierto')->latest()->first();
            if ($turnoActivo) {
                app(CajaService::class)->vincularCobroPedido($turnoActivo, $pedido);
            }

            // Salvaguarda: descontar cualquier ítem del pedido que no haya pasado por KDS
            app(InventarioService::class)->descontarPorPedido($pedido);

            // Acumular puntos de fidelización si el pedido está asociado a un comensal
            app(FidelizacionService::class)->acumularPuntosPorPedido($pedido);

            // Despachar ticket térmico fiscal de venta al spooler de impresión
            app(ImpresionService::class)->despacharTicketVenta($pedido);

            return $pedido->fresh(['items', 'mesa', 'cliente']);
        });
    }

    /**
     * Crear un pedido iniciado por un comensal desde el menú QR de mesa.
     */
    public function crearPedidoDesdeQr(Mesa $mesa, array $items, string $nombreCliente = '', ?string $notas = null): Pedido
    {
        return $this->crearPedido([
            'tipo' => 'mesa',
            'estado' => 'solicitado_qr',
            'canal_origen' => 'qr_mesa',
            'mesa_id' => $mesa->id,
            'nombre_cliente' => ! empty(trim($nombreCliente)) ? trim($nombreCliente) : 'Comensal Mesa '.$mesa->numero,
            'notas' => $notas,
        ], $items, null);
    }

    /**
     * Asignar un mesero a un pedido QR con protección de concurrencia pesimista (lockForUpdate).
     * Si otro mesero ya tomó la asignación, arroja DomainException con el nombre del mesero que lo tomó.
     */
    public function asignarMeseroAPedidoQr(int $pedidoId, User $mesero): Pedido
    {
        return DB::transaction(function () use ($pedidoId, $mesero) {
            $pedido = Pedido::where('id', $pedidoId)
                ->lockForUpdate()
                ->with(['mesa', 'usuario'])
                ->firstOrFail();

            // Verificación de concurrencia: si ya fue asignado y no es el mismo mesero
            if ($pedido->usuario_id !== null && $pedido->usuario_id !== $mesero->id) {
                $nombreAsignado = $pedido->usuario?->name ?? 'otro mesero';
                throw new \DomainException("Este pedido de la Mesa #{$pedido->mesa?->numero} ya fue tomado por {$nombreAsignado}.");
            }

            $pedido->usuario_id = $mesero->id;
            if ($pedido->estado === 'solicitado_qr') {
                $pedido->estado = 'en_cocina';
            }
            $pedido->save();

            if ($pedido->mesa) {
                $pedido->mesa->update(['estado' => MesaEstado::OCUPADA->value]);
            }

            // Actualizar items de la comanda para cocina
            $pedido->items()->where('estado_cocina', 'pendiente')->update([
                'estado_cocina' => 'en_preparacion',
                'iniciado_en' => now(),
            ]);

            app(ImpresionService::class)->despacharComandaCocina($pedido);

            return $pedido->fresh(['usuario', 'mesa', 'items']);
        });
    }
}
