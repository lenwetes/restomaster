<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Events\ComandaEnviada;
use App\Events\ItemListoParaServir;
use App\Events\PedidoQrSolicitado;
use App\Jobs\EnviarEncuestaClienteJob;
use App\Models\AsientoContable;
use App\Models\Cliente;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\MovimientoCaja;
use App\Models\NotificacionUsuario;
use App\Models\Pedido;
use App\Models\PedidoDevolucion;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            if ($mesa) {
                app(MesaService::class)->validarDisponiblePara($mesa, $usuario);
            }
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
            if (! $meseroId && $mesa) {
                $rotService = app(\App\Services\RotacionMeseroService::class);
                $meseroRotacion = $rotService->autoasignarMesa($mesa);
                if ($meseroRotacion) {
                    $meseroId = $meseroRotacion->id;
                }
            }

            $pedido = (new Pedido)->forceFill([
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
            $pedido->save();

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
                if ($descuentoPuntos > 0 && $puntosCanjeados < 1) {
                    throw new \InvalidArgumentException('Para aplicar descuento por puntos debe indicar cuántos puntos canjear.');
                }

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
            // Tope absoluto configurable (config/pos.php) aunque el usuario tenga permiso de descuento.
            $descuentoAplicado = min($subtotal, $descuentoSolicitado, (float) config('pos.max_descuento', 50000));
            $remanente = max(0, $subtotal - $descuentoAplicado);
            $descuentoPuntosAplicado = min($remanente, $descuentoPuntos);
            $total = max(0, $subtotal + $costoEnvio - $descuentoAplicado - $descuentoPuntosAplicado);

            $pedido->forceFill([
                'subtotal' => $subtotal,
                'descuento' => $descuentoAplicado,
                'costo_envio' => $costoEnvio,
                'descuento_puntos' => $descuentoPuntosAplicado,
                'total' => $total,
            ])->save();

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
        return DB::transaction(function () use ($pedido) {
            $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            abort_if(
                in_array($pedido->estado, ['pagado', 'cancelado'], true),
                422,
                'El pedido ya fue cerrado y no admite envío a cocina.'
            );

            $pedido->forceFill(['estado' => 'en_cocina'])->save();

            $pedido->items()->where('estado_cocina', 'pendiente')->update([
                'estado_cocina' => 'en_preparacion',
                'iniciado_en' => now(),
            ]);

            // Despachar comanda a las impresoras térmicas de cocina por estación
            app(ImpresionService::class)->despacharComandaCocina($pedido);

            // Broadcast en tiempo real al KDS de cocina
            broadcast(new ComandaEnviada($pedido))->toOthers();

            return $pedido->fresh('items');
        });
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

        $pedido = $item->relationLoaded('pedido') ? $item->pedido : Pedido::find($item->pedido_id);
        if ($pedido) {
            $itemsPendientes = $pedido->items()
                ->whereNotIn('estado_cocina', ['listo', 'entregado', 'servido', 'cancelado'])
                ->count();

            if ($itemsPendientes === 0 && in_array($pedido->estado, ['creado', 'en_cocina', 'en_preparacion'])) {
                $pedido->forceFill(['estado' => 'listo'])->save();
            }
        }

        Cache::forget('pos.terminal.mesas');
        Cache::forget('pos.terminal.categorias');

        // Broadcast y persistencia de alerta al mesero responsable
        if ($pedido && $pedido->mesero_id) {
            try {
                NotificacionUsuario::create([
                    'user_id' => $pedido->mesero_id,
                    'tipo' => 'plato_listo',
                    'titulo' => '¡Plato listo para servir!',
                    'cuerpo' => "{$item->cantidad}x {$item->nombre_producto} (Mesa #{$pedido->mesa?->numero})",
                    'datos' => [
                        'item_id' => $item->id,
                        'pedido_id' => $pedido->id,
                        'pedido_codigo' => $pedido->codigo,
                        'mesa_numero' => $pedido->mesa?->numero,
                        'mesa_zona' => $pedido->mesa?->zona ?? $pedido->mesa?->nombre_sala,
                    ],
                ]);
                Cache::forget('notif.resumen.'.$pedido->mesero_id);
            } catch (\Throwable) {
                // Si la tabla no está disponible en tests legacy, continuar
            }

            broadcast(new ItemListoParaServir($item))->toOthers();
        }

        return $item;
    }

    /**
     * Marcar un item como entregado al cliente/mesa.
     */
    public function marcarItemEntregado(ItemPedido $item): ItemPedido
    {
        $item->update(['estado_cocina' => 'entregado']);

        $pedido = $item->relationLoaded('pedido') ? $item->pedido : Pedido::find($item->pedido_id);
        if ($pedido) {
            $itemsNoEntregados = $pedido->items()
                ->whereNotIn('estado_cocina', ['entregado', 'servido', 'cancelado'])
                ->count();

            if ($itemsNoEntregados === 0 && $pedido->estado !== 'pagado') {
                $pedido->forceFill(['estado' => 'entregado'])->save();
            }
        }

        return $item;
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

            // La comanda sigue en preparación en cocina: solo se cobra cuando cocina termine la preparación
            $comandaEnCocina = in_array($pedido->estado, ['en_cocina', 'en_preparacion'])
                && $pedido->items()->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])->exists();
            abort_if($comandaEnCocina, 422, 'La comanda sigue en preparación en cocina: solo se puede cobrar cuando cocina termine la preparación.');

            // Restricción de cobro: Un mesero no puede cobrar pedidos asignados a otro mesero
            $user = auth()->user();
            if ($user && $user->isMesero() && ! $user->isAdmin() && ! $user->isGerente() && ! $user->isCajero()) {
                $meseroAsignadoId = $pedido->mesero_id ?? $pedido->mesa?->mesero_id;
                if ($meseroAsignadoId && (int) $meseroAsignadoId !== (int) $user->id) {
                    abort(403, 'Restricción de cobro: Solo el mesero asignado a esta mesa o un cajero/administrador puede procesar el cobro.');
                }
            }

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

            $pedido->forceFill([
                'estado' => 'pagado',
                'metodo_pago' => $metodoPago,
                'propina' => $propina,
                'porcentaje_propina' => $porcentajePropina,
                'monto_pagado' => $montoPagado,
                'monto_pago_efectivo' => $montoEfectivo,
                'monto_pago_tarjeta' => $montoTarjeta,
                'cambio' => $cambio,
                'pagado_en' => now(),
            ])->save();

            $pedido->items()->whereIn('estado_cocina', ['pendiente', 'listo'])->update([
                'estado_cocina' => 'entregado',
                'listo_en' => now(),
            ]);

            // Si tiene mesa asignada, pasa a 'por_limpiar' y libera al mesero
            if ($pedido->mesa_id) {
                $mesa = Mesa::find($pedido->mesa_id);
                if ($mesa) {
                    $meseroAnteriorId = $mesa->mesero_id;
                    $mesa->update(['estado' => MesaEstado::POR_LIMPIAR->value, 'mesero_id' => null]);

                    if ($meseroAnteriorId) {
                        app(RotacionMeseroService::class)->liberarMesa($mesa, $meseroAnteriorId);
                        app(AuditoriaService::class)->registrar(
                            accion: 'mesas.liberada_cobro',
                            entidad: 'mesa',
                            entidadId: $mesa->id,
                            descripcion: "Mesa #{$mesa->numero} liberada al cobrar el pedido {$pedido->codigo}.",
                            datos: ['mesa_id' => $mesa->id, 'pedido_id' => $pedido->id, 'mesero_anterior_id' => $meseroAnteriorId]
                        );
                    }
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

            if ($propina > 0) {
                AsientoContable::create([
                    'fecha' => now()->toDateString(),
                    'tipo' => 'ingreso',
                    'cuenta' => 'propinas',
                    'concepto' => "Propina pedido {$pedido->codigo} ({$porcentajePropina}%)",
                    'monto' => $propina,
                    'referencia_tipo' => 'pedido',
                    'referencia_id' => $pedido->id,
                    'user_id' => $pedido->usuario_id ?? auth()->id(),
                ]);
            }

            // Salvaguarda: descontar cualquier ítem del pedido que no haya pasado por KDS
            app(InventarioService::class)->descontarPorPedido($pedido);

            // Acumular puntos de fidelización si el pedido está asociado a un comensal
            app(FidelizacionService::class)->acumularPuntosPorPedido($pedido);

            // Programar envío de encuesta de satisfacción (F7-07)
            if ($pedido->cliente_id) {
                EnviarEncuestaClienteJob::dispatch($pedido->id);
                try {
                    app(CrmAutomatizacionService::class)->procesarCobroPedido($pedido);
                } catch (\Throwable $e) {
                    Log::warning("CRM Error post-cobro pedido: {$e->getMessage()}");
                }
            }

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
        $pedido = $this->crearPedido([
            'estado' => 'solicitado_qr',
            'canal_origen' => 'qr_mesa',
            'mesa_id' => $mesa->id,
            'mesero_id' => $mesa->mesero_id,
            'nombre_cliente' => ! empty(trim($nombreCliente)) ? trim($nombreCliente) : 'Comensal Mesa '.$mesa->numero,
            'notas' => $notas,
        ], $items, null);

        broadcast(new PedidoQrSolicitado($pedido))->toOthers();

        return $pedido;
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

            if ($mesero->sucursal_id && $pedido->sucursal_id && $pedido->sucursal_id !== $mesero->sucursal_id) {
                throw new AuthorizationException('No puede atender pedidos de otra sucursal.');
            }

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
            $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            abort_if($pedido->estado === 'pagado', 400, 'No se pueden agregar ítems a un pedido ya cobrado.');
            abort_if($pedido->estado === 'cancelado', 400, 'No se pueden agregar ítems a un pedido cancelado.');

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

    /**
     * Procesa la anulación o devolución rápida de un ítem de ticket cobrado.
     * Ejecuta en una transacción atómica:
     * 1. Egreso de caja por devolución (actualiza efectivo esperado y cuadre).
     * 2. Reingreso de insumos y recetas al inventario (Kardex).
     * 3. Asiento contable de compensación (devoluciones_ventas).
     * 4. Actualización del ítem y pedido.
     * 5. Registro inmutable de auditoría.
     */
    public function devolverItemPedido(
        ItemPedido $item,
        int $cantidad,
        string $motivo,
        string $autorizadoPor,
        User $usuario,
        string $metodoReembolso = 'efectivo'
    ): PedidoDevolucion {
        $pedido = $item->relationLoaded('pedido') ? $item->pedido : $item->pedido()->firstOrFail();

        if ($pedido->estado !== 'pagado') {
            throw new \DomainException('Solo se pueden procesar devoluciones sobre tickets o pedidos ya cobrados.');
        }

        $disponible = $item->cantidadDisponibleDevolucion();
        if ($cantidad <= 0 || $cantidad > $disponible) {
            throw new \InvalidArgumentException("La cantidad a devolver ({$cantidad}) no es válida. Disponible para devolución: {$disponible}.");
        }

        if (empty(trim($motivo))) {
            throw new \InvalidArgumentException('Debes especificar el motivo de la devolución.');
        }

        if (empty(trim($autorizadoPor))) {
            throw new \InvalidArgumentException('La devolución requiere el nombre o PIN del supervisor autorizador.');
        }

        return DB::transaction(function () use ($item, $pedido, $cantidad, $motivo, $autorizadoPor, $usuario, $metodoReembolso) {
            $montoDevuelto = round((float) $item->precio_unitario * $cantidad, 2);

            // 1. Obtener turno de caja activo o el turno del pedido
            $turno = null;
            if ($pedido->turno_caja_id) {
                $turno = TurnoCaja::where('id', $pedido->turno_caja_id)->where('estado', 'abierto')->first();
            }
            if (! $turno) {
                $turno = TurnoCaja::where('estado', 'abierto')
                    ->when($pedido->sucursal_id, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $pedido->sucursal_id)))
                    ->latest()
                    ->first();
            }

            $movimientoCaja = null;
            if ($turno) {
                // Registrar movimiento de egreso por devolución en caja
                $conceptoCaja = "Devolución {$cantidad}x {$item->nombre_producto} (Ticket #{$pedido->codigo}) - Motivo: {$motivo}";
                $movimientoCaja = MovimientoCaja::create([
                    'turno_caja_id' => $turno->id,
                    'user_id' => $usuario->id,
                    'tipo' => 'egreso',
                    'concepto' => $conceptoCaja,
                    'monto' => $montoDevuelto,
                    'metodo_pago' => $metodoReembolso,
                    'numero_comprobante' => 'DEV-'.strtoupper(uniqid()),
                    'autorizado_por' => $autorizadoPor,
                ]);

                // Actualizar acumuladores del turno de caja
                $turno->total_egresos = (float) $turno->total_egresos + $montoDevuelto;
                app(CajaService::class)->recalcularEsperado($turno);
                $turno->save();
            }

            // 2. Reingreso de insumos al inventario (Kardex) si ya fueron descontados
            if ($item->inventario_descontado) {
                app(InventarioService::class)->revertirPorItemDevuelto($item, $cantidad, $usuario);
            }

            // 3. Asiento contable de compensación / contrapartida
            $asientoContable = AsientoContable::create([
                'fecha' => now()->toDateString(),
                'tipo' => 'gasto',
                'cuenta' => 'devoluciones_ventas',
                'concepto' => "Devolución {$cantidad}x {$item->nombre_producto} - Pedido {$pedido->codigo} (Autorizó: {$autorizadoPor})",
                'monto' => $montoDevuelto,
                'referencia_tipo' => 'pedido_devolucion',
                'referencia_id' => $pedido->id,
                'user_id' => $usuario->id,
            ]);

            // 4. Actualizar cantidad devuelta en el ítem del pedido
            $item->cantidad_devuelta = (int) ($item->cantidad_devuelta ?? 0) + $cantidad;
            $item->save();

            // 5. Registrar la devolución en la tabla específica
            $devolucion = PedidoDevolucion::create([
                'pedido_id' => $pedido->id,
                'item_pedido_id' => $item->id,
                'producto_id' => $item->producto_id,
                'cantidad' => $cantidad,
                'monto_devuelto' => $montoDevuelto,
                'motivo' => $motivo,
                'metodo_reembolso' => $metodoReembolso,
                'turno_caja_id' => $turno?->id,
                'movimiento_caja_id' => $movimientoCaja?->id,
                'asiento_contable_id' => $asientoContable->id,
                'autorizado_por' => $autorizadoPor,
                'user_id' => $usuario->id,
            ]);

            // 6. Auditoría inmutable
            app(AuditoriaService::class)->registrar(
                usuario: $usuario,
                accion: 'pedidos.item_devuelto',
                entidad: 'pedido',
                entidadId: $pedido->id,
                descripcion: "Devolución de {$cantidad}x {$item->nombre_producto} ($" . number_format($montoDevuelto, 0) . ") por motivo: {$motivo}. Autorizado por: {$autorizadoPor}",
                datos: [
                    'pedido_id' => $pedido->id,
                    'item_id' => $item->id,
                    'cantidad' => $cantidad,
                    'monto_devuelto' => $montoDevuelto,
                    'motivo' => $motivo,
                    'autorizado_por' => $autorizadoPor,
                ]
            );

            // 7. Spooler de impresión para comprobante térmico de devolución
            try {
                app(ImpresionService::class)->despacharComprobanteDevolucion($devolucion);
            } catch (\Throwable $e) {
                Log::warning("Impresión comprobante devolución omitida: {$e->getMessage()}");
            }

            return $devolucion;
        });
    }
}
