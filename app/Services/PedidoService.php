<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Cliente;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PedidoService
{
    /**
     * Crear un nuevo pedido con sus items asociados.
     */
    public function crearPedido(array $datos, array $items, ?User $usuario = null): Pedido
    {
        return DB::transaction(function () use ($datos, $items, $usuario) {
            $idempotenciaUuid = $datos['idempotencia_uuid'] ?? null;
            if ($idempotenciaUuid) {
                $existente = Pedido::where('idempotencia_uuid', $idempotenciaUuid)->first();
                if ($existente) {
                    return $existente->fresh(['items', 'mesa']);
                }
            }

            do {
                $codigo = 'ORD-'.date('Ymd-His').'-'.strtoupper(Str::random(6));
            } while (Pedido::where('codigo', $codigo)->exists());

            $mesa = ! empty($datos['mesa_id']) ? Mesa::find($datos['mesa_id']) : null;
            $sucursalId = $datos['sucursal_id']
                ?? $mesa?->sucursal_id
                ?? $usuario?->sucursal_id
                ?? auth()->user()?->sucursal_id
                ?? Sucursal::value('id')
                ?? 1;

            $meseroId = $datos['mesero_id'] ?? null;
            if (! $meseroId && $mesa?->mesero_id) {
                $meseroId = $mesa->mesero_id;
            }
            if (! $meseroId && ($usuario?->isMesero() || auth()->user()?->isMesero())) {
                $meseroId = $usuario?->id ?? auth()->id();
            }

            $pedido = Pedido::create([
                'codigo' => $codigo,
                'tipo' => $datos['tipo'] ?? 'mesa',
                'estado' => $datos['estado'] ?? 'creado',
                'sucursal_id' => $sucursalId,
                'mesa_id' => $datos['mesa_id'] ?? null,
                'usuario_id' => $usuario?->id ?? auth()->id(),
                'mesero_id' => $meseroId,
                'cliente_id' => $datos['cliente_id'] ?? null,
                'nombre_cliente' => $datos['nombre_cliente'] ?? null,
                'telefono_cliente' => $datos['telefono_cliente'] ?? null,
                'direccion_delivery' => $datos['direccion_delivery'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'descuento' => $datos['descuento'] ?? 0,
                'canal_origen' => $datos['canal_origen'] ?? 'pos',
                'idempotencia_uuid' => $idempotenciaUuid,
            ]);

            if ($mesa && $meseroId && ! $mesa->mesero_id) {
                $mesa->update(['mesero_id' => $meseroId]);
            }

            $subtotal = 0;
            $descuentoSolicitado = max(0, (float) ($datos['descuento'] ?? 0));
            $costoEnvio = max(0, (float) ($datos['costo_envio'] ?? 0));
            $descuentoPuntos = max(0, (float) ($datos['descuento_puntos'] ?? 0));
            $clienteId = $datos['cliente_id'] ?? null;
            $puntosCanjeados = (int) ($datos['puntos_canjeados'] ?? 0);

            if ($descuentoPuntos > 0 || $puntosCanjeados > 0) {
                if ($usuario && ! in_array($usuario->role?->slug, ['mesero', 'cajero', 'gerente', 'admin'], true)) {
                    throw new AuthorizationException('No tiene permisos para canjear puntos de fidelidad.');
                }

                if ($clienteId && $puntosCanjeados > 0) {
                    $cliente = Cliente::find($clienteId);
                    if (! $cliente) {
                        throw new \InvalidArgumentException('El cliente especificado no existe.');
                    }
                    if ($cliente->puntos_fidelidad < $puntosCanjeados) {
                        throw new \InvalidArgumentException("El comensal solo dispone de {$cliente->puntos_fidelidad} puntos (se intentaron canjear {$puntosCanjeados}).");
                    }
                    $maxDescuentoPuntos = app(FidelizacionService::class)->calcularDescuentoPorPuntos($puntosCanjeados);
                    $descuentoPuntos = min($descuentoPuntos, $maxDescuentoPuntos);
                } elseif ($puntosCanjeados > 0 && ! $clienteId) {
                    throw new \InvalidArgumentException('Para canjear puntos se requiere especificar un cliente.');
                }
            }

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

            // C2 & M2 & P0-06 FIX: Descuento acotado al subtotal, puntos acotados al remanente y fórmula unificada
            $descuentoAplicado = min($subtotal, $descuentoSolicitado);
            $remanente = max(0, $subtotal - $descuentoAplicado);
            $descuentoPuntosAplicado = min($remanente, $descuentoPuntos);
            $total = max(0, $subtotal + $costoEnvio - $descuentoAplicado - $descuentoPuntosAplicado);

            $pedido->update([
                'subtotal' => $subtotal,
                'descuento' => $descuentoAplicado,
                'costo_envio' => $costoEnvio,
                'descuento_puntos' => $descuentoPuntosAplicado,
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
     *
     * El cobro exige un turno de caja abierto en la sucursal del pedido; de lo
     * contrario el ingreso quedaría invisible en el Reporte Z y el arqueo.
     */
    public function cobrarPedido(
        Pedido $pedido,
        string $metodoPago,
        float $montoPagado,
        ?float $montoPagoEfectivo = null,
        float $propina = 0.0,
        ?float $porcentajePropina = 0.0
    ): Pedido {
        return DB::transaction(function () use ($pedido, $metodoPago, $montoPagado, $montoPagoEfectivo, $propina, $porcentajePropina) {
            // H5 FIX: Bloqueo pesimista e idempotencia para evitar cobros dobles por race condition
            $pedido = Pedido::where('id', $pedido->id)->lockForUpdate()->firstOrFail();
            abort_if($pedido->estado === 'pagado', 400, 'El pedido ya se encuentra pagado.');

            $propina = max(0.0, round($propina, 2));
            $porcentajePropina = $porcentajePropina !== null ? max(0.0, (float) $porcentajePropina) : null;
            $totalConPropina = (float) $pedido->total + $propina;

            $metodo = strtolower($metodoPago);
            $montoEfectivo = null;
            $montoTarjeta = null;

            if ($metodo === 'mixto') {
                $montoEfectivo = max(0, (float) ($montoPagoEfectivo ?? 0));
                $montoTarjeta = max(0, $totalConPropina - $montoEfectivo);
            } elseif (in_array($metodo, ['tarjeta', 'tarjeta_credito', 'tarjeta_debito', 'datafono', 'datáfono', 'datfono'], true)) {
                $montoTarjeta = $totalConPropina;
            }

            if ($montoPagado < $totalConPropina) {
                throw new \InvalidArgumentException("El monto pagado ({$montoPagado}) no puede ser inferior al total a pagar ({$totalConPropina}).");
            }

            $cambio = max(0, $montoPagado - $totalConPropina);

            $pedido->update([
                'estado' => 'pagado',
                'metodo_pago' => $metodoPago,
                'propina' => $propina,
                'porcentaje_propina' => $porcentajePropina,
                'monto_pagado' => $montoPagado,
                'monto_pago_efectivo' => $montoEfectivo,
                'monto_pago_tarjeta' => $montoTarjeta,
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

            // Vincular obligatoriamente con el turno de caja abierto de la sucursal del pedido
            $turnoActivo = TurnoCaja::where('estado', 'abierto')
                ->when($pedido->sucursal_id, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $pedido->sucursal_id)))
                ->latest()
                ->first();

            if (! $turnoActivo) {
                throw new \DomainException('No hay un turno de caja abierto para cobrar este pedido. Abra un turno en el módulo de Caja.');
            }

            app(CajaService::class)->vincularCobroPedido($turnoActivo, $pedido);

            // Salvaguarda: descontar cualquier ítem del pedido que no haya pasado por KDS
            app(InventarioService::class)->descontarPorPedido($pedido);

            // Acumular puntos de fidelización si el pedido está asociado a un comensal
            app(FidelizacionService::class)->acumularPuntosPorPedido($pedido);

            // Despachar ticket térmico fiscal de venta al spooler de impresión
            app(ImpresionService::class)->despacharTicketVenta($pedido);

            return $pedido->fresh(['items', 'mesa', 'cliente', 'mesero']);
        });
    }

    /**
     * Crear un pedido desde el menú público QR de la mesa (solicitado_qr).
     */
    public function crearPedidoDesdeQr(Mesa $mesa, array $items, string $nombreCliente = '', ?string $notas = null): Pedido
    {
        return $this->crearPedido([
            'estado' => 'solicitado_qr',
            'canal_origen' => 'qr_mesa',
            'mesa_id' => $mesa->id,
            'mesero_id' => $mesa->mesero_id,
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
        if (! in_array($mesero->role?->slug, ['mesero', 'capitan', 'gerente', 'admin'], true)) {
            throw new AuthorizationException('El usuario no tiene rol para ser asignado como mesero.');
        }

        return DB::transaction(function () use ($pedidoId, $mesero) {
            $pedido = Pedido::where('id', $pedidoId)
                ->lockForUpdate()
                ->with(['mesa', 'usuario', 'mesero'])
                ->firstOrFail();

            // Verificación de concurrencia: si ya fue asignado y no es el mismo mesero
            if ($pedido->usuario_id !== null && $pedido->usuario_id !== $mesero->id) {
                $nombreAsignado = $pedido->usuario?->name ?? 'otro mesero';
                throw new \DomainException("Este pedido de la Mesa #{$pedido->mesa?->numero} ya fue tomado por {$nombreAsignado}.");
            }

            $pedido->usuario_id = $mesero->id;
            $pedido->mesero_id = $mesero->id;
            if ($pedido->estado === 'solicitado_qr') {
                $pedido->estado = 'en_cocina';
            }
            $pedido->save();

            if ($pedido->mesa) {
                $pedido->mesa->update([
                    'estado' => MesaEstado::OCUPADA->value,
                    'mesero_id' => $mesero->id,
                ]);
            }

            // Actualizar items de la comanda para cocina
            $pedido->items()->where('estado_cocina', 'pendiente')->update([
                'estado_cocina' => 'en_preparacion',
                'iniciado_en' => now(),
            ]);

            app(ImpresionService::class)->despacharComandaCocina($pedido);

            return $pedido->fresh(['usuario', 'mesero', 'mesa', 'items']);
        });
    }

    /**
     * Agregar un ítem a un pedido existente y recalcular sus totales.
     */
    public function agregarItem(Pedido $pedido, Producto $producto, int $cantidad = 1, ?string $notas = null): ItemPedido
    {
        return DB::transaction(function () use ($pedido, $producto, $cantidad, $notas) {
            $cantidad = max(1, $cantidad);
            $precioUnitario = (float) $producto->precio;
            $itemSubtotal = $precioUnitario * $cantidad;

            $item = ItemPedido::create([
                'pedido_id' => $pedido->id,
                'producto_id' => $producto->id,
                'nombre_producto' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $itemSubtotal,
                'area_cocina' => $producto->area_cocina ?? 'sushi',
                'estado_cocina' => 'pendiente',
                'notas' => $notas,
            ]);

            $pedido->subtotal = (float) $pedido->items()->sum('subtotal');
            $pedido->recalcularTotales();
            $pedido->save();

            return $item;
        });
    }
}
