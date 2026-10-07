<?php

namespace App\Livewire\Concerns;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\TurnoCaja;
use App\Services\CajaService;
use App\Services\ClienteService;
use App\Services\FidelizacionService;
use App\Services\PedidoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Trait para la gestión de cobro, arqueo y liquidación en el POS.
 *
 * @property float $total
 * @property float $totalConPropina
 * @property float $subtotal
 * @property array $carrito
 * @property string $tipo
 * @property ?int $mesaId
 * @property ?int $clienteId
 * @property bool $mostrarModalCobro
 * @property bool $mostrarModalAperturaPos
 * @property float $montoPagado
 * @property float $montoEfectivoMixto
 * @property string $tipoPropina
 * @property float $montoPropina
 * @property ?float $porcentajePropina
 * @property ?string $clienteSeleccionadoNombre
 * @property ?string $clienteSeleccionadoDocumento
 *
 * @method float getTotalProperty()
 * @method float getTotalConPropinaProperty()
 */
trait ManejaCobroPos
{
    public function abrirModalCobro(): void
    {
        if ($this->tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) {
            $this->dispatch('notificacion', [
                'mensaje' => 'Bloqueo de Cobro: Debes enviar la comanda a cocina antes de cobrar la mesa.',
                'tipo' => 'warning',
            ]);

            return;
        }

        if ($this->esMesaDeOtroMesero()) {
            $mesa = Mesa::find($this->mesaId);
            $pedido = $this->obtenerPedidoActivoMesa();
            $nombre = $mesa?->mesero?->name ?? $pedido?->mesero?->name ?? 'otro mesero';
            $this->dispatch('notificacion', [
                'mensaje' => "Restricción de Cobro: Esta mesa está asignada a {$nombre}. Solo el mesero responsable o el personal de caja pueden cobrarla.",
                'tipo' => 'warning',
            ]);

            return;
        }

        if ($this->modoNuevaAdicion && $this->tipo === 'mesa' && $this->mesaId) {
            $pedidoActivo = $this->obtenerPedidoActivoMesa();
            if ($pedidoActivo) {
                if (! empty($this->carrito)) {
                    $pedidoService = app(PedidoService::class);
                    $productos = Producto::whereIn('id', array_keys($this->carrito))->get()->keyBy('id');
                    foreach ($this->carrito as $productoId => $itemCarrito) {
                        $producto = $productos->get($productoId);
                        if ($producto) {
                            $itemAgregado = $pedidoService->agregarItem($pedidoActivo, $producto, (int) $itemCarrito['cantidad'], $itemCarrito['notas'] ?? null);
                            $itemAgregado->update(['estado_cocina' => 'entregado', 'listo_en' => now()]);
                        }
                    }
                }
                $this->modoNuevaAdicion = false;
                $this->limpiarCarrito();
                foreach ($pedidoActivo->fresh()->items as $item) {
                    $this->carrito[$item->producto_id] = [
                        'producto_id' => $item->producto_id,
                        'nombre' => $item->nombre_producto,
                        'precio' => (float) $item->precio_unitario,
                        'cantidad' => (int) $item->cantidad,
                        'notas' => $item->notas ?? '',
                        'area_cocina' => $item->area_cocina,
                    ];
                }
            }
        } elseif (empty($this->carrito)) {
            $pedidoActivo = $this->obtenerPedidoActivoMesa();
            if ($pedidoActivo) {
                foreach ($pedidoActivo->items as $item) {
                    $this->carrito[$item->producto_id] = [
                        'producto_id' => $item->producto_id,
                        'nombre' => $item->nombre_producto,
                        'precio' => (float) $item->precio_unitario,
                        'cantidad' => (int) $item->cantidad,
                        'notas' => $item->notas ?? '',
                        'area_cocina' => $item->area_cocina,
                    ];
                }
            } else {
                return;
            }
        }

        if ($this->comandaActivaBloqueaCobro()) {
            $this->dispatch('notificacion', [
                'mensaje' => 'Bloqueo de Cobro: La comanda sigue en preparación en cocina. Solo se puede cobrar cuando cocina termine la preparación.',
                'tipo' => 'warning',
            ]);

            return;
        }

        $userSucursalId = Auth::user()?->sucursal_id;
        $turnoActivo = TurnoCaja::where('estado', 'abierto')
            ->when($userSucursalId, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $userSucursalId)))
            ->latest()
            ->first();

        if (! $turnoActivo) {
            if (Gate::allows('abrir', TurnoCaja::class)) {
                $caja = Caja::where('activa', true)
                    ->when($userSucursalId, fn ($q) => $q->where('sucursal_id', $userSucursalId))
                    ->first() ?? Caja::where('activa', true)->first();
                $this->cajaAperturaId = $caja?->id;
                $this->baseAperturaPos = 150000.0;
                $this->notasAperturaPos = 'Apertura de turno iniciada desde terminal POS';
                $this->mostrarModalAperturaPos = true;
            } else {
                $this->dispatch('notificacion', [
                    'mensaje' => 'Caja Cerrada: No hay un turno de caja abierto para registrar el cobro. Solicita al cajero la apertura de turno.',
                    'tipo' => 'warning',
                ]);
            }

            return;
        }

        $pedidoCobro = $this->obtenerPedidoActivoMesa();
        if (! $pedidoCobro && ! empty($this->carrito)) {
            $pedidoService = app(PedidoService::class);
            $mesaIdCobro = $this->tipo === 'mesa' ? $this->mesaId : null;
            $itemsPayload = [];
            foreach ($this->carrito as $itemCar) {
                $itemsPayload[] = [
                    'producto_id' => $itemCar['producto_id'],
                    'cantidad' => $itemCar['cantidad'],
                    'notas' => $itemCar['notas'] ?? null,
                ];
            }
            $pedidoCobro = $pedidoService->crearPedido([
                'tipo' => $this->tipo,
                'mesa_id' => $mesaIdCobro,
                'cliente_id' => $this->clienteId,
                'usuario_id' => Auth::id(),
                'sucursal_id' => Auth::user()?->sucursal_id ?? 1,
            ], $itemsPayload, Auth::user());
            $pedidoCobro->items()->update(['estado_cocina' => 'entregado', 'listo_en' => now()]);
            $this->limpiarCarrito();
        }

        if ($pedidoCobro) {
            $this->dispatch('abrir-modal-cobro-unificado', pedidoId: $pedidoCobro->id);
        } else {
            $this->dispatch('notificacion', [
                'mensaje' => 'No hay una comanda o ítems activos para cobrar.',
                'tipo' => 'warning',
            ]);
        }
    }

    public function abrirModalAperturaPosManual(): void
    {
        $userSucursalId = Auth::user()?->sucursal_id;
        $caja = Caja::where('activa', true)
            ->when($userSucursalId, fn ($q) => $q->where('sucursal_id', $userSucursalId))
            ->first() ?? Caja::where('activa', true)->first();
        $this->cajaAperturaId = $caja?->id;
        $this->baseAperturaPos = 150000.0;
        $this->notasAperturaPos = 'Apertura manual iniciada desde POS';
        $this->mostrarModalAperturaPos = true;
    }

    public function abrirTurnoDesdePos(): void
    {
        $this->authorize('abrir', TurnoCaja::class);

        $this->validate([
            'cajaAperturaId' => 'required|exists:cajas,id',
            'baseAperturaPos' => 'required|numeric|min:0',
        ]);

        $sucursalId = Auth::user()?->sucursal_id;
        $caja = Caja::when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->findOrFail($this->cajaAperturaId);

        try {
            $turno = app(CajaService::class)->abrirTurno(
                $caja,
                Auth::user(),
                $this->baseAperturaPos,
                $this->notasAperturaPos
            );

            $this->mostrarModalAperturaPos = false;
            $this->dispatch('notificacion', [
                'mensaje' => "¡Turno #{$turno->id} abierto con éxito en {$caja->nombre}! Ya puedes registrar el cobro.",
                'tipo' => 'success',
            ]);

            if (! empty($this->carrito)) {
                $this->tipoPropina = 'cero';
                $this->montoPropina = 0.0;
                $this->porcentajePropina = 0.0;
                $this->montoPagado = $this->obtenerTotal();
                $this->mostrarModalCobro = true;
            }
        } catch (\Exception $e) {
            $this->addError('baseAperturaPos', $e->getMessage());
        }
    }

    public function setMontoExacto(): void
    {
        $this->montoPagado = $this->obtenerTotalConPropina();
    }

    public function sumarMonto(float $cantidad): void
    {
        $this->montoPagado = max(0.0, (float) $this->montoPagado + max(0.0, $cantidad));
    }

    public function comandaActivaBloqueaCobro(): bool
    {
        if ($this->tipo !== 'mesa' || ! $this->mesaId) {
            return false;
        }

        $pedido = $this->obtenerPedidoActivoMesa();
        if (! $pedido) {
            return false;
        }

        return in_array($pedido->estado, ['en_cocina', 'en_preparacion', 'en_proceso'])
            && $pedido->items()->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])->exists();
    }

    public function comandaListaParaCobrar(): bool
    {
        if ($this->tipo !== 'mesa') {
            return ! empty($this->carrito);
        }

        if (! $this->mesaId || empty($this->carrito)) {
            return false;
        }

        if ($this->comandaRequiereEnvioCocina()) {
            return false;
        }

        $pedido = $this->obtenerPedidoActivoMesa();
        if (! $pedido) {
            return false;
        }

        $tieneItems = $pedido->items()->exists();
        $enCocina = in_array($pedido->estado, ['en_cocina', 'en_preparacion', 'en_proceso'])
            && $pedido->items()->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])->exists();

        return $tieneItems && ! $enCocina && $this->cantidadNuevosItemsParaCocina() === 0;
    }

    public function esMesaDeOtroMesero(): bool
    {
        if (! $this->mesaId) {
            return false;
        }

        $user = Auth::user();
        if (! $user || ! $user->isMesero() || $user->isAdmin() || $user->isGerente() || $user->isCajero()) {
            return false;
        }

        $mesa = Mesa::find($this->mesaId);
        if (! $mesa) {
            return false;
        }

        // Si la mesa no tiene mesero asignado formalmente en el salón, pertenece al mesero que la está operando
        if (! $mesa->mesero_id) {
            return false;
        }

        // Si la mesa está formalmente asignada a este mismo mesero
        if ((int) $mesa->mesero_id === (int) $user->id) {
            return false;
        }

        // Solo es de otro mesero si la mesa en el salón está formalmente asignada a un mesero diferente
        return true;
    }

    public function procesarCobro(): void
    {
        $this->authorize('cobrar', Pedido::class);

        if ($this->tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) {
            abort(422, 'Debes enviar la comanda a cocina antes de procesar el cobro de la mesa.');
        }

        if ($this->modoNuevaAdicion && $this->tipo === 'mesa' && $this->mesaId) {
            $pedidoActivo = $this->obtenerPedidoActivoMesa();
            if ($pedidoActivo && ! empty($this->carrito)) {
                $pedidoService = app(PedidoService::class);
                $productosCobro = Producto::whereIn('id', array_keys($this->carrito))->get()->keyBy('id');
                foreach ($this->carrito as $productoId => $itemCarrito) {
                    $producto = $productosCobro->get($productoId);
                    if ($producto) {
                        $itemAgregado = $pedidoService->agregarItem($pedidoActivo, $producto, (int) $itemCarrito['cantidad'], $itemCarrito['notas'] ?? null);
                        $itemAgregado->update(['estado_cocina' => 'entregado', 'listo_en' => now()]);
                    }
                }
                $this->modoNuevaAdicion = false;
            }
        }

        if (empty($this->carrito)) {
            $pedidoActivo = $this->obtenerPedidoActivoMesa();
            if ($pedidoActivo) {
                $this->cargarCarritoDesdePedido($pedidoActivo);
            }
        }

        if ($this->comandaActivaBloqueaCobro()) {
            abort(422, 'La comanda sigue en preparación en cocina: solo se puede cobrar cuando cocina termine la preparación.');
        }

        if ($this->esMesaDeOtroMesero()) {
            abort(403, 'Restricción de cobro: Solo el mesero asignado a esta mesa o un cajero/administrador puede procesar el cobro.');
        }

        if ($this->descuento > 0) {
            $this->authorize('aplicarDescuento', Pedido::class);
        }

        $pedidoService = app(PedidoService::class);
        $costoEnvio = $this->tipo === 'delivery' ? $this->costoEnvio : 0.0;

        if (! $this->clienteId && trim($this->nombreCliente) !== '') {
            $cliente = app(ClienteService::class)->buscarOcrearOcasional($this->nombreCliente);
            $this->clienteId = $cliente->id;
            $this->nombreCliente = $cliente->nombre;
        }

        if ($this->tipo === 'mesa' && $this->mesaId) {
            $mesa = Mesa::find($this->mesaId);
            abort_if($mesa && Auth::user()?->sucursal_id && $mesa->sucursal_id !== Auth::user()->sucursal_id, 403, 'Mesa no pertenece a su sucursal.');
        }

        $pedidoExistente = ($this->tipo === 'mesa' && $this->mesaId)
            ? Pedido::where('mesa_id', $this->mesaId)->activos()->latest()->first()
            : null;

        if ($pedidoExistente) {
            abort_if(Auth::user()?->sucursal_id && $pedidoExistente->sucursal_id && $pedidoExistente->sucursal_id !== Auth::user()->sucursal_id, 403, 'No autorizado para cobrar pedidos de otra sucursal.');

            if (! $pedidoExistente->cliente_id && $this->clienteId) {
                $pedidoExistente->update([
                    'cliente_id' => $this->clienteId,
                    'nombre_cliente' => $this->nombreCliente,
                ]);
            }

            $productosExistentes = Producto::whereIn('id', array_keys($this->carrito))->get()->keyBy('id');

            if ($this->modoNuevaAdicion) {
                foreach ($this->carrito as $productoId => $itemCarrito) {
                    $producto = $productosExistentes->get($productoId);
                    if ($producto) {
                        $itemAgregado = $pedidoService->agregarItem($pedidoExistente, $producto, (int) $itemCarrito['cantidad'], $itemCarrito['notas'] ?? null);
                        $itemAgregado->update(['estado_cocina' => 'entregado', 'listo_en' => now()]);
                    }
                }
                $this->modoNuevaAdicion = false;
            } else {
                $cantidadesDb = $this->obtenerCantidadesPorProducto($pedidoExistente);

                foreach ($this->carrito as $productoId => $itemCarrito) {
                    $cantidadCarrito = (int) $itemCarrito['cantidad'];
                    $cantidadExistente = (int) $cantidadesDb->get($productoId, 0);
                    $diferencia = $cantidadCarrito - $cantidadExistente;
                    if ($diferencia > 0) {
                        $producto = $productosExistentes->get($productoId);
                        if ($producto) {
                            $itemAgregado = $pedidoService->agregarItem($pedidoExistente, $producto, $diferencia, $itemCarrito['notas'] ?? null);
                            $itemAgregado->update(['estado_cocina' => 'entregado', 'listo_en' => now()]);
                        }
                    }
                }
            }
            $pedido = $pedidoExistente->fresh(['items', 'mesa']);
        } else {
            if ($this->puntosCanjeados > 0 && $this->clienteId) {
                $this->authorize('canjearPuntos', Pedido::class);
                $this->descuentoPuntos = min($this->descuentoPuntos, app(FidelizacionService::class)->calcularDescuentoPorPuntos($this->puntosCanjeados));
            } else {
                $this->puntosCanjeados = 0;
                $this->descuentoPuntos = 0.0;
            }

            if (empty($this->idempotenciaUuid)) {
                $this->idempotenciaUuid = (string) Str::uuid();
            }

            $mesaObj = ($this->tipo === 'mesa' && $this->mesaId) ? Mesa::find($this->mesaId) : null;
            $pedido = $pedidoService->crearPedido([
                'tipo' => $this->tipo,
                'estado' => 'creado',
                'sucursal_id' => Auth::user()?->sucursal_id ?? $mesaObj?->sucursal_id ?? 1,
                'estado_delivery' => $this->tipo === 'delivery' ? 'pendiente' : null,
                'mesa_id' => $this->tipo === 'mesa' ? $this->mesaId : null,
                'cliente_id' => $this->clienteId,
                'direccion_id' => $this->direccionId,
                'nombre_cliente' => $this->nombreCliente,
                'telefono_cliente' => $this->telefonoCliente,
                'direccion_delivery' => $this->direccionDelivery,
                'costo_envio' => $costoEnvio,
                'descuento' => $this->descuento,
                'descuento_puntos' => $this->descuentoPuntos,
                'puntos_canjeados' => $this->puntosCanjeados,
                'idempotencia_uuid' => $this->idempotenciaUuid,
            ], array_values($this->carrito), Auth::user());
        }

        $propina = max(0.0, (float) $this->montoPropina);
        $totalConPropina = (float) $pedido->total + $propina;

        if (in_array(strtolower((string) $this->metodoPago), ['tarjeta', 'transferencia', 'datafono', 'datáfono'], true)) {
            $this->montoPagado = $totalConPropina;
        }

        if (strtolower((string) $this->metodoPago) === 'mixto') {
            $this->montoPagado = $totalConPropina;
            $this->montoEfectivoMixto = min(max(0, (float) $this->montoEfectivoMixto), $totalConPropina);
        }

        $montoPagadoNum = round((float) $this->montoPagado);
        $totalConPropinaNum = round($totalConPropina);

        if ($montoPagadoNum < $totalConPropinaNum) {
            $msg = 'El monto entregado ($'.number_format($montoPagadoNum, 0, ',', '.').') no puede ser menor al total a cancelar ($'.number_format($totalConPropinaNum, 0, ',', '.').').';
            $this->addError('montoPagado', $msg);
            $this->dispatch('notificacion', [
                'mensaje' => $msg,
                'tipo' => 'warning',
            ]);

            return;
        }

        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $cliente = Cliente::find($this->clienteId);
            if ($cliente) {
                app(FidelizacionService::class)->canjearPuntos($cliente, $this->puntosCanjeados, $pedido);
            }
        }

        $this->pedidoCompletado = null;

        try {
            $this->pedidoCompletado = $pedidoService->cobrarPedido(
                $pedido,
                $this->metodoPago,
                $montoPagadoNum,
                strtolower((string) $this->metodoPago) === 'mixto' ? (float) $this->montoEfectivoMixto : null,
                $propina,
                $this->porcentajePropina
            );
        } catch (\Throwable $e) {
            $this->addError('montoPagado', $e->getMessage());
            $this->dispatch('notificacion', [
                'mensaje' => 'No se pudo procesar el cobro: '.$e->getMessage(),
                'tipo' => 'error',
            ]);

            return;
        }

        $this->mostrarModalCobro = false;
        $this->mostrarTicket = true;
        $this->idempotenciaUuid = null;
        $this->limpiarCarrito();
    }

    public function solicitarCobroCaja(): void
    {
        $this->authorize('solicitarCobro', Pedido::class);

        if ($this->tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) {
            $this->dispatch('notificacion', [
                'mensaje' => 'Debes enviar la comanda a cocina antes de solicitar el cobro.',
                'tipo' => 'warning',
            ]);

            return;
        }

        $pedido = $this->obtenerPedidoActivoMesa();
        if (! $pedido) {
            $this->dispatch('notificacion', [
                'mensaje' => 'No hay una comanda activa en esta mesa para solicitar cobro.',
                'tipo' => 'warning',
            ]);

            return;
        }

        $user = Auth::user();
        if ($user && (! $pedido->mesero_id || (int) $pedido->mesero_id === (int) $user->id)) {
            $pedido->update(['mesero_id' => $user->id]);
        }
        if ($this->mesaId && $user?->isMesero()) {
            $mesa = Mesa::find($this->mesaId);
            if ($mesa && ! $mesa->mesero_id) {
                $mesa->update(['mesero_id' => $user->id]);
            }
        }

        try {
            app(PedidoService::class)->solicitarCobroCaja($pedido, Auth::user());
            $this->dispatch('notificacion', [
                'mensaje' => '📲 Solicitud de cobro enviada a Caja. Espera la confirmación del cajero.',
                'tipo' => 'success',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('notificacion', [
                'mensaje' => $e->getMessage(),
                'tipo' => 'error',
            ]);
        }
    }

    public function seleccionarPropina(string $tipo): void
    {
        $this->tipoPropina = $tipo;

        if ($tipo === 'cero') {
            $this->montoPropina = 0.0;
            $this->porcentajePropina = 0.0;
        } elseif ($tipo === 'diez_porciento') {
            $this->montoPropina = round($this->obtenerTotal() * 0.10);
            $this->porcentajePropina = 10.0;
        } elseif ($tipo === 'personalizada') {
            $this->porcentajePropina = null;
        }

        $this->montoPagado = $this->obtenerTotalConPropina();
    }

    public function obtenerTotal(): float
    {
        return method_exists($this, 'getTotalProperty') ? (float) $this->getTotalProperty() : (float) ($this->total ?? 0.0);
    }

    public function obtenerTotalConPropina(): float
    {
        return method_exists($this, 'getTotalConPropinaProperty') ? (float) $this->getTotalConPropinaProperty() : (float) ($this->totalConPropina ?? 0.0);
    }

    public function cerrarModalCobro(): void
    {
        $this->mostrarModalCobro = false;
    }

    public function previsualizarUltimoTicketPos(): void
    {
        $ultimo = Pedido::with(['items.producto', 'mesa', 'mesero', 'usuario'])
            ->whereNotNull('pagado_en')
            ->orderByDesc('pagado_en')
            ->orderByDesc('id')
            ->first();

        if (! $ultimo) {
            $this->dispatch('notificacion', [
                'mensaje' => 'No se encontraron tickets cobrados recientes para previsualizar.',
                'tipo' => 'warning',
            ]);

            return;
        }

        $this->pedidoCompletado = $ultimo;
        $this->mostrarTicket = true;
    }

    public function cerrarTicket(): void
    {
        $this->mostrarTicket = false;
        $this->pedidoCompletado = null;

        if ($this->mesaId) {
            if (Auth::user()?->role?->slug === 'mesero') {
                $this->mesaId = null;
                $this->limpiarCarrito();
                $this->redirect(route('pos'), navigate: true);

                return;
            }

            $this->redirect(route('mesas'), navigate: true);
        }
    }
}
