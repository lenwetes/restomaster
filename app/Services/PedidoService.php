<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
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
            $codigo = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

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
            ]);

            $subtotal = 0;

            foreach ($items as $itemData) {
                $producto = Producto::findOrFail($itemData['producto_id']);
                $cantidad = max(1, (int)($itemData['cantidad'] ?? 1));
                $precioUnitario = (float)($itemData['precio_unitario'] ?? $producto->precio);
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

            $total = max(0, $subtotal - (float)$pedido->descuento);

            $pedido->update([
                'subtotal' => $subtotal,
                'total' => $total,
            ]);

            // Si es pedido de mesa, actualizar la mesa a 'ocupada'
            if (!empty($pedido->mesa_id)) {
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
        app(\App\Services\InventarioService::class)->descontarPorItemPedido($item);

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
            $cambio = max(0, $montoPagado - (float)$pedido->total);

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
            $turnoActivo = \App\Models\TurnoCaja::where('estado', 'abierto')->latest()->first();
            if ($turnoActivo) {
                app(\App\Services\CajaService::class)->vincularCobroPedido($turnoActivo, $pedido);
            }

            // Salvaguarda: descontar cualquier ítem del pedido que no haya pasado por KDS
            app(\App\Services\InventarioService::class)->descontarPorPedido($pedido);

            // Acumular puntos de fidelización si el pedido está asociado a un comensal
            app(\App\Services\FidelizacionService::class)->acumularPuntosPorPedido($pedido);

            return $pedido->fresh(['items', 'mesa', 'cliente']);
        });
    }
}
