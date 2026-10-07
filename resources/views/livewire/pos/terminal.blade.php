<?php

use App\Livewire\Concerns\ManejaClientePos;
use App\Livewire\Concerns\ManejaCobroPos;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\ConfiguracionService;
use App\Services\PedidoService;
use App\Services\TurnoSemanalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class () extends Component {
    use ManejaClientePos;
    use ManejaCobroPos;

    public ?string $idempotenciaUuid = null;

    public string $tipo = 'mesa'; // 'mesa', 'mostrador', 'delivery'

    public ?int $mesaId = null;

    public string $nombreCliente = '';

    public string $telefonoCliente = '';

    public string $direccionDelivery = '';

    public ?int $clienteId = null;

    public ?int $direccionId = null;

    public int $puntosDisponibles = 0;

    public int $puntosCanjeados = 0;

    public float $descuentoPuntos = 0.0;

    public string $busquedaCliente = '';

    public array $sugerenciasClientes = [];

    public bool $mostrarSugerencias = false;

    public bool $mostrarModalHabeasData = false;

    public string $habeasNombre = '';

    public string $habeasTelefono = '';

    public string $habeasEmail = '';

    public string $habeasDireccion = '';

    public bool $habeasAcepta = false;

    public bool $habeasWhatsapp = true;

    public bool $habeasEmailPromos = true;

    // Fase 4 — Aviso interactivo de horario semanal al login del mesero
    public bool $mostrarModalTurnoSemanal = false;

    public array $turnoSemanalDias = [];

    public string $turnoSemanalEtiqueta = '';

    public bool $turnoSemanalPuedePosponer = true;

    public float $costoEnvio = 8000.0;

    public ?int $categoriaSeleccionada = null;

    public string $busqueda = '';

    // Shopping cart
    public array $carrito = [];

    public $descuento = 0.0;

    // Checkout modal and ticket
    public bool $mostrarModalCobro = false;

    public string $metodoPago = 'efectivo';

    public $montoPagado = 0.0;

    public $montoEfectivoMixto = 0.0;

    public ?Pedido $pedidoCompletado = null;

    public bool $mostrarTicket = false;

    // Propina voluntaria
    public string $tipoPropina = 'cero'; // 'cero', 'diez_porciento', 'personalizada'

    public $montoPropina = 0.0;

    public $porcentajePropina = 0.0;

    // Tres vistas ergonómicas para rol mesero: 'pc', 'tablet', 'movil'
    public string $vistaMesero = 'pc';

    public bool $mostrarComandaMovil = false;

    // Modal de Apertura Rápida de Turno de Caja desde POS
    public bool $mostrarModalAperturaPos = false;

    public $baseAperturaPos = 150000.0;

    public string $notasAperturaPos = '';

    public ?int $cajaAperturaId = null;

    // Modo Nuevo Pedido / Adición sobre mesa con comanda previa despachada
    public bool $modoNuevaAdicion = false;

    /**
     * @param  string  $property
     * @return mixed
     */
    public function __get($property)
    {
        if ($property === 'montoPagado') {
            return $this->montoPagado = 0.0;
        }
        if ($property === 'montoEfectivoMixto') {
            return $this->montoEfectivoMixto = 0.0;
        }
        if ($property === 'montoPropina') {
            return $this->montoPropina = 0.0;
        }
        if ($property === 'baseAperturaPos') {
            return $this->baseAperturaPos = 0.0;
        }

        return parent::__get($property);
    }

    public function cambiarVista(string $vista): void
    {
        if (in_array($vista, ['pc', 'tablet', 'movil'])) {
            $this->vistaMesero = $vista;
        }
    }

    public function cargarCarritoDesdePedido(Pedido $pedido): void
    {
        $this->limpiarCarrito();
        foreach ($pedido->items as $item) {
            $prodId = (int) $item->producto_id;
            $cant = (int) $item->cantidad;
            if (isset($this->carrito[$prodId])) {
                $this->carrito[$prodId]['cantidad'] += $cant;
            } else {
                $this->carrito[$prodId] = [
                    'producto_id' => $prodId,
                    'nombre' => $item->nombre_producto,
                    'precio' => (float) $item->precio_unitario,
                    'cantidad' => $cant,
                    'notas' => $item->notas ?? '',
                    'area_cocina' => $item->area_cocina,
                ];
            }
        }
    }

    public function obtenerCantidadesPorProducto(Pedido $pedido): \Illuminate\Support\Collection
    {
        return $pedido->items()
            ->selectRaw('producto_id, SUM(cantidad) as total_cantidad')
            ->groupBy('producto_id')
            ->pluck('total_cantidad', 'producto_id');
    }

    public function mount(): void
    {
        $this->modoNuevaAdicion = false;

        if (request()->has('vista') && in_array(request()->query('vista'), ['pc', 'tablet', 'movil'])) {
            $this->vistaMesero = request()->query('vista');
        }

        $mesaIdParam = request()->query('mesa_id') ?? $this->mesaId;
        if ($mesaIdParam) {
            $this->mesaId = (int) $mesaIdParam;
            $this->tipo = 'mesa';

            // If table has an active order, load it
            $pedidoExistente = Pedido::where('mesa_id', $this->mesaId)->activos()->latest()->first();
            if ($pedidoExistente) {
                $this->cargarCarritoDesdePedido($pedidoExistente);
            }
        }

        $this->verificarAvisoTurnoSemanal();
    }

    /**
     * Fase 4.1 — Detecta horario semanal publicado sin confirmar al ingresar al POS.
     * Solo meseros; el modal se muestra y se emite evento Alpine.
     */
    public function verificarAvisoTurnoSemanal(): void
    {
        $usuario = Auth::user();

        if (! $usuario || ! $usuario->isMesero()) {
            return;
        }

        $pendiente = app(TurnoSemanalService::class)->pendienteConfirmacion($usuario);

        if ($pendiente === null) {
            $this->mostrarModalTurnoSemanal = false;

            return;
        }

        $programacion = $pendiente['programacion'];
        $this->turnoSemanalEtiqueta = 'SEMANA ' . $programacion->semana_iso . ' / ' . $programacion->anio;
        $this->turnoSemanalDias = $pendiente['turnos']->map(fn ($t) => [
            'fecha' => $t->fecha->toDateString(),
            'dia' => Str::upper($t->fecha->locale('es')->dayName),
            'zona' => $t->es_descanso ? null : ($t->zona?->nombre ?? 'Sin zona'),
            'plantilla' => $t->es_descanso ? null : $t->plantilla?->nombre,
            'es_descanso' => (bool) $t->es_descanso,
        ])->values()->all();
        $this->turnoSemanalPuedePosponer = ((int) session()->get('turno_semanal_pospuestas', 0)) < 1;
        $this->mostrarModalTurnoSemanal = true;

        app(TurnoSemanalService::class)->asegurarNotificacionRespaldo($usuario);

        $this->dispatch('mostrar-modal-turno-semanal');
    }

    /**
     * Fase 4.2 — El mesero confirma su horario semanal.
     */
    public function confirmarTurnoSemanal(): void
    {
        $usuario = Auth::user();

        if (! $usuario) {
            return;
        }

        // Seguridad: cada mesero solo confirma su propio horario (alcance propio).
        if ($usuario->isMesero()) {
            app(TurnoSemanalService::class)->confirmarHorario($usuario);
        }

        session()->forget('turno_semanal_pospuestas');
        $this->mostrarModalTurnoSemanal = false;
        $this->dispatch('notificacion', ['mensaje' => 'Horario semanal confirmado. ¡Buen turno!', 'tipo' => 'success']);
    }

    /**
     * Fase 4.2 — Posponer una sola vez por sesión ("Ver más tarde").
     */
    public function posponerTurnoSemanal(): void
    {
        session()->put('turno_semanal_pospuestas', ((int) session()->get('turno_semanal_pospuestas', 0)) + 1);
        $this->mostrarModalTurnoSemanal = false;
    }

    public function updatedMesaId(mixed $value): void
    {
        $this->modoNuevaAdicion = false;
        $this->limpiarCarrito();
        if ($value) {
            $mesa = Mesa::find((int) $value);
            $user = Auth::user();
            if ($mesa && ! $mesa->mesero_id && $user?->isMesero()) {
                $mesa->update(['mesero_id' => $user->id]);
            }
            $pedidoExistente = Pedido::where('mesa_id', (int) $value)->activos()->latest()->first();
            if ($pedidoExistente) {
                if ($user?->isMesero() && (! $pedidoExistente->mesero_id || ! $mesa?->mesero_id || (int) $mesa?->mesero_id === (int) $user->id)) {
                    $pedidoExistente->update(['mesero_id' => $user->id]);
                }
                $this->cargarCarritoDesdePedido($pedidoExistente);
            }
        }
    }

    public function agregarProducto(int $productoId): void
    {
        if ($this->tipo === 'mesa' && ! $this->mesaId) {
            $this->dispatch('notificacion-mesa-requerida');
            session()->flash('advertencia_mesa', '¡Atención! Primero debes seleccionar una mesa para tomar el pedido.');

            return;
        }

        $producto = Producto::findOrFail($productoId);

        if (isset($this->carrito[$productoId])) {
            $this->carrito[$productoId]['cantidad']++;
        } else {
            $this->carrito[$productoId] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'precio' => (float) $producto->precio,
                'cantidad' => 1,
                'notas' => '',
                'area_cocina' => $producto->area_cocina,
            ];
        }
    }

    public function incrementarCantidad(int $productoId): void
    {
        if ($this->tipo === 'mesa' && ! $this->mesaId) {
            $this->dispatch('notificacion-mesa-requerida');
            session()->flash('advertencia_mesa', '¡Atención! Primero debes seleccionar una mesa para tomar el pedido.');

            return;
        }

        if (isset($this->carrito[$productoId])) {
            $this->carrito[$productoId]['cantidad']++;
        }
    }

    public function decrementarCantidad(int $productoId): void
    {
        if (isset($this->carrito[$productoId])) {
            if ($this->carrito[$productoId]['cantidad'] > 1) {
                $this->carrito[$productoId]['cantidad']--;
            } else {
                unset($this->carrito[$productoId]);
            }
        }
    }

    public function eliminarItem(int $productoId): void
    {
        unset($this->carrito[$productoId]);
    }

    public function limpiarCarrito(): void
    {
        $this->carrito = [];
        $this->descuento = 0.0;
    }

    public function getSubtotalProperty(): float
    {
        $subtotal = 0.0;
        foreach ($this->carrito as $item) {
            $subtotal += $item['precio'] * $item['cantidad'];
        }

        return $subtotal;
    }

    public function getTotalProperty(): float
    {
        $envio = $this->tipo === 'delivery' ? $this->costoEnvio : 0.0;

        return max(0.0, $this->subtotal + $envio - $this->descuento - $this->descuentoPuntos);
    }

    public function getTotalConPropinaProperty(): float
    {
        return max(0.0, $this->total + $this->montoPropina);
    }

    public function getCambioProperty(): float
    {
        $pagado = is_numeric($this->montoPagado) ? (float) $this->montoPagado : 0.0;

        return max(0.0, $pagado - $this->totalConPropina);
    }

    public function seleccionarPropina(string $tipo): void
    {
        $this->tipoPropina = $tipo;
        if ($tipo === 'diez_porciento') {
            $this->porcentajePropina = 10.0;
            $this->montoPropina = round($this->total * 0.10);
        } elseif ($tipo === 'cero') {
            $this->porcentajePropina = 0.0;
            $this->montoPropina = 0.0;
        }
        $this->actualizarMontoPagadoConPropina();
    }

    public function updatedMontoPagado(mixed $value): void
    {
        $this->montoPagado = (is_numeric($value) && (float) $value >= 0) ? (float) $value : 0.0;
    }

    public function updatedMontoEfectivoMixto(mixed $value): void
    {
        $this->montoEfectivoMixto = (is_numeric($value) && (float) $value >= 0) ? (float) $value : 0.0;
    }

    public function updatedMontoPropina(mixed $value): void
    {
        $this->montoPropina = max(0.0, (float) $value);
        $this->porcentajePropina = $this->total > 0 ? round(($this->montoPropina / $this->total) * 100, 1) : 0.0;
        $this->actualizarMontoPagadoConPropina();
    }

    public function actualizarMontoPagadoConPropina(): void
    {
        if (in_array(strtolower((string) $this->metodoPago), ['tarjeta', 'transferencia', 'datafono', 'datáfono', 'mixto'], true)) {
            $this->montoPagado = $this->totalConPropina;
        } elseif ($this->metodoPago === 'efectivo' && (float) $this->montoPagado < $this->totalConPropina) {
            $this->montoPagado = $this->totalConPropina;
        }
    }

    public function updatedDescuento(mixed $value): void
    {
        if ((float) $value > 0) {
            $this->authorize('aplicarDescuento', Pedido::class);
        }
        if ($this->tipoPropina === 'diez_porciento') {
            $this->montoPropina = round($this->total * 0.10);
        }
        $this->actualizarMontoPagadoConPropina();
    }

    public function updatedMetodoPago(mixed $value): void
    {
        if (in_array(strtolower((string) $value), ['tarjeta', 'transferencia', 'datafono', 'datáfono', 'mixto'], true)) {
            $this->montoPagado = $this->totalConPropina;
        }

        if (strtolower((string) $value) !== 'mixto') {
            $this->montoEfectivoMixto = 0.0;
        }

        if (in_array(strtolower((string) $value), ['efectivo', 'mixto'], true)) {
            $this->dispatch('enfocar-monto');
        }
    }

    public function enviarACocina(): void
    {
        $this->authorize('enviarCocina', Pedido::class);

        if ($this->descuento > 0) {
            $this->authorize('aplicarDescuento', Pedido::class);
        }

        if (empty($this->carrito)) {
            $this->dispatch('notificacion', [
                'mensaje' => 'El carrito está vacío. Agrega productos antes de enviar a cocina.',
                'tipo' => 'warning',
            ]);

            return;
        }

        // Bloqueo estricto: evitar re-enviar la misma comanda si no hay platos nuevos
        if ($this->tipo === 'mesa' && $this->mesaId) {
            $pedidoExistente = Pedido::where('mesa_id', $this->mesaId)->activos()->latest()->first();
            if ($pedidoExistente && $this->cantidadNuevosItemsParaCocina() === 0) {
                $this->dispatch('notificacion', [
                    'mensaje' => 'Esta comanda ya fue enviada a cocina. No hay productos nuevos para enviar.',
                    'tipo' => 'warning',
                ]);

                return;
            }
        }

        $pedidoService = app(PedidoService::class);
        $costoEnvio = $this->tipo === 'delivery' ? $this->costoEnvio : 0.0;

        if (! $this->clienteId && trim($this->nombreCliente) !== '') {
            $cliente = app(\App\Services\ClienteService::class)->buscarOcrearOcasional($this->nombreCliente);
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
            abort_if(Auth::user()?->sucursal_id && $pedidoExistente->sucursal_id && $pedidoExistente->sucursal_id !== Auth::user()->sucursal_id, 403, 'No autorizado para modificar pedidos de otra sucursal.');
            if (! $pedidoExistente->cliente_id && $this->clienteId) {
                $pedidoExistente->update([
                    'cliente_id' => $this->clienteId,
                    'nombre_cliente' => $this->nombreCliente,
                ]);
            }

            $productoIds = array_keys($this->carrito);
            $productos = Producto::whereIn('id', $productoIds)->get()->keyBy('id');

            if ($this->modoNuevaAdicion) {
                foreach ($this->carrito as $productoId => $itemCarrito) {
                    $producto = $productos->get($productoId);
                    if ($producto) {
                        $pedidoService->agregarItem($pedidoExistente, $producto, (int) $itemCarrito['cantidad'], $itemCarrito['notas'] ?? null);
                    }
                }
                $this->modoNuevaAdicion = false;
            } else {
                $itemsExistentes = $pedidoExistente->items()->get()->keyBy('producto_id');

                foreach ($this->carrito as $productoId => $itemCarrito) {
                    $cantidadCarrito = (int) $itemCarrito['cantidad'];
                    if ($itemsExistentes->has($productoId)) {
                        $itemDb = $itemsExistentes->get($productoId);
                        $diferencia = $cantidadCarrito - (int) $itemDb->cantidad;
                        if ($diferencia > 0) {
                            $producto = $productos->get($productoId);
                            if ($producto) {
                                $pedidoService->agregarItem($pedidoExistente, $producto, $diferencia, $itemCarrito['notas'] ?? null);
                            }
                        }
                    } else {
                        $producto = $productos->get($productoId);
                        if ($producto) {
                            $pedidoService->agregarItem($pedidoExistente, $producto, $cantidadCarrito, $itemCarrito['notas'] ?? null);
                        }
                    }
                }
            }

            $pedido = $pedidoService->enviarACocina($pedidoExistente);
        } else {
            if ($this->puntosCanjeados > 0 && $this->clienteId) {
                $this->authorize('canjearPuntos', Pedido::class);
                $this->descuentoPuntos = min($this->descuentoPuntos, app(\App\Services\FidelizacionService::class)->calcularDescuentoPorPuntos($this->puntosCanjeados));
            } else {
                $this->puntosCanjeados = 0;
                $this->descuentoPuntos = 0.0;
            }

            $mesaObj = ($this->tipo === 'mesa' && $this->mesaId) ? Mesa::find($this->mesaId) : null;
            $pedido = $pedidoService->crearPedido([
                'tipo' => $this->tipo,
                'estado' => 'en_cocina',
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
            ], array_values($this->carrito), Auth::user());
        }

        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $cliente = \App\Models\Cliente::find($this->clienteId);
            if ($cliente) {
                app(\App\Services\FidelizacionService::class)->canjearPuntos($cliente, $this->puntosCanjeados, $pedido);
            }
        }

        $this->limpiarCarrito();

        session()->flash('notificacion', "¡Comanda {$pedido->codigo} enviada a cocina con éxito!");

        // rol intencional, no permiso: el mesero vuelve a su comandera tras enviar
        if (Auth::user()?->role?->slug === 'mesero') {
            $this->mesaId = null;
            $this->redirect(route('pos'), navigate: true);

            return;
        }

        $this->redirect($this->tipo === 'delivery' ? route('delivery') : route('mesas'), navigate: true);
    }

    public function obtenerPedidoActivoMesa(): ?Pedido
    {
        if ($this->tipo !== 'mesa' || ! $this->mesaId) {
            return null;
        }

        return Pedido::where('mesa_id', $this->mesaId)
            ->activos()
            ->latest()
            ->first();
    }

    public function comandaYaEnviadaACocina(): bool
    {
        $pedido = $this->obtenerPedidoActivoMesa();
        if (! $pedido) {
            return false;
        }

        if ($this->modoNuevaAdicion) {
            return false;
        }

        if (empty($this->carrito)) {
            return true;
        }

        if (! in_array($pedido->estado, ['en_cocina', 'en_preparacion', 'en_proceso', 'listo', 'entregado', 'servido'], true)) {
            return false;
        }

        $cantidadesDb = $this->obtenerCantidadesPorProducto($pedido);

        foreach ($this->carrito as $prodId => $itemCarrito) {
            $cantCarrito = (int) $itemCarrito['cantidad'];
            if (! $cantidadesDb->has($prodId)) {
                return false;
            }
            if ($cantCarrito > (int) $cantidadesDb->get($prodId)) {
                return false;
            }
        }

        return true;
    }

    public function comandaDespachadaPorCocina(): bool
    {
        if ($this->tipo !== 'mesa' || ! $this->mesaId) {
            return false;
        }

        $pedido = $this->obtenerPedidoActivoMesa();
        if (! $pedido) {
            return false;
        }

        if (in_array($pedido->estado, ['listo', 'entregado', 'servido'], true)) {
            return true;
        }

        $tieneItems = $pedido->items()->exists();
        $tienePendientesCocina = $pedido->items()->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])->exists();

        return $tieneItems && ! $tienePendientesCocina;
    }

    public function iniciarNuevoPedido(): void
    {
        $this->limpiarCarrito();
        $this->modoNuevaAdicion = true;

        $mesa = $this->mesaId ? Mesa::find($this->mesaId) : null;
        $destino = $mesa ? "Mesa {$mesa->numero}" : 'la comanda';

        $this->dispatch('notificacion', [
            'mensaje' => "Nuevo pedido para {$destino}: agrega los nuevos productos para enviar a cocina.",
            'tipo' => 'info',
        ]);
    }

    public function cancelarModoAdicion(): void
    {
        $this->modoNuevaAdicion = false;
        $this->limpiarCarrito();
        $pedidoExistente = $this->obtenerPedidoActivoMesa();
        if ($pedidoExistente) {
            $this->cargarCarritoDesdePedido($pedidoExistente);
        }
    }

    public function cantidadNuevosItemsParaCocina(): int
    {
        if ($this->modoNuevaAdicion) {
            return (int) array_sum(array_column($this->carrito, 'cantidad'));
        }

        $pedido = $this->obtenerPedidoActivoMesa();
        if (! $pedido) {
            return count($this->carrito);
        }

        $cantidadesDb = $this->obtenerCantidadesPorProducto($pedido);
        $nuevos = 0;

        foreach ($this->carrito as $prodId => $itemCarrito) {
            $cantCarrito = (int) $itemCarrito['cantidad'];
            if (! $cantidadesDb->has($prodId)) {
                $nuevos += $cantCarrito;
            } else {
                $diff = $cantCarrito - (int) $cantidadesDb->get($prodId);
                if ($diff > 0) {
                    $nuevos += $diff;
                }
            }
        }

        return $nuevos;
    }

    public function comandaRequiereEnvioCocina(): bool
    {
        if ($this->tipo !== 'mesa') {
            return false;
        }

        if (! $this->mesaId || empty($this->carrito)) {
            return false;
        }

        $pedido = $this->obtenerPedidoActivoMesa();

        // 1. Si no existe pedido activo en BD, los platos del carrito aún no se han enviado a cocina
        if (! $pedido) {
            return true;
        }

        // 2. Si hay productos agregados en el carrito que no han sido enviados a cocina
        if ($this->cantidadNuevosItemsParaCocina() > 0) {
            return true;
        }

        // 3. Si la comanda en BD aún no ha sido despachada hacia cocina
        return ! $this->comandaYaEnviadaACocina();
    }

    public function sincronizarPedidoQrMesa(): void
    {
        if (! $this->mesaId) {
            return;
        }

        $sucursalId = Auth::user()?->sucursal_id;
        $pedido = Pedido::where('mesa_id', $this->mesaId)
            ->where('canal_origen', 'qr_mesa')
            ->where('estado', 'solicitado_qr')
            ->whereNull('usuario_id')
            ->when($sucursalId, function ($q) use ($sucursalId) {
                $q->whereHas('mesa', fn ($m) => $m->where('sucursal_id', $sucursalId));
            })
            ->latest()
            ->first();

        if ($pedido) {
            try {
                $pedidoService = app(PedidoService::class);
                $pedido = $pedidoService->asignarMeseroAPedidoQr($pedido->id, Auth::user());
                session()->flash('notificacion', "¡Has tomado el pedido de la Mesa #{$pedido->mesa?->numero}! Comanda en preparación.");
            } catch (\DomainException $e) {
                session()->flash('error', $e->getMessage());
            } catch (\Throwable $e) {
                session()->flash('error', 'Error al asignar pedido: ' . $e->getMessage());
            }
        }
    }

    public function with(): array
    {
        $query = Producto::with('categoria')->where('activo', true);

        if ($this->categoriaSeleccionada) {
            $query->where('categoria_id', $this->categoriaSeleccionada);
        }

        if (! empty($this->busqueda)) {
            $query->where('nombre', 'ilike', '%' . $this->busqueda . '%');
        }

        $pedidoQrPendiente = ($this->tipo === 'mesa' && $this->mesaId)
            ? Pedido::where('mesa_id', $this->mesaId)
                ->where('canal_origen', 'qr_mesa')
                ->where('estado', 'solicitado_qr')
                ->whereNull('usuario_id')
                ->latest()
                ->first()
            : null;

        $clientesQuery = \App\Models\Cliente::where('activo', true);
        if (! empty(trim($this->busquedaCliente))) {
            $term = '%' . trim($this->busquedaCliente) . '%';
            $clientesQuery->where(function ($q) use ($term) {
                $q->where('nombre', 'ilike', $term)
                    ->orWhere('telefono', 'ilike', $term);
            });
            $clientesDisponibles = $clientesQuery->orderByDesc('puntos_fidelidad')->limit(30)->get();
        } else {
            // Optimización de rendimiento: evitar consultar 50 clientes en cada interacción si no se está buscando
            $clientesDisponibles = $this->mostrarSugerencias
                ? $clientesQuery->orderByDesc('puntos_fidelidad')->limit(20)->get()
                : collect();
        }

        $categorias = new \Illuminate\Database\Eloquent\Collection(
            collect(Cache::remember('pos.terminal.categorias', 60, function (): array {
                return Categoria::where('activo', true)
                    ->withCount(['productos' => fn ($q) => $q->where('activo', true)])
                    ->orderBy('orden')
                    ->get()
                    ->map(fn (Categoria $c) => [
                        'id' => $c->id,
                        'icono' => $c->icono,
                        'nombre' => $c->nombre,
                        'color' => $c->color ?? '#e11d48',
                        'productos_count' => (int) $c->productos_count,
                    ])
                    ->all();
            }))->map(fn (array $c) => (new Categoria())->forceFill($c))->all()
        );

        $userSucursalId = Auth::user()?->sucursal_id;
        $cacheKey = 'pos.terminal.mesas.sucursal.' . ($userSucursalId ?? 'all');
        $mesasCache = Cache::remember($cacheKey, 60, function () use ($userSucursalId): array {
            return Mesa::with('mesero:id,name')
                ->when($userSucursalId, fn ($q) => $q->where('sucursal_id', $userSucursalId))
                ->orderBy('numero')
                ->get()
                ->map(fn (Mesa $m) => [
                    'id' => $m->id,
                    'sucursal_id' => $m->sucursal_id,
                    'numero' => $m->numero,
                    'capacidad' => $m->capacidad,
                    'estado' => $m->estado,
                    'zona' => $m->zona,
                    'ubicacion' => $m->ubicacion,
                    'activa' => (bool) $m->activa,
                    'mesero_id' => $m->mesero_id,
                    'mesero_nombre' => $m->mesero?->name,
                ])
                ->all();
        });

        $mesasColeccion = collect($mesasCache);
        $mesas = new \Illuminate\Database\Eloquent\Collection(
            $mesasColeccion->map(fn (array $m) => (new Mesa())->forceFill($m))->all()
        );

        $turnoActivo = \App\Models\TurnoCaja::where('estado', 'abierto')
            ->when($userSucursalId, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $userSucursalId)))
            ->with('caja')
            ->latest()
            ->first();

        $cajasDisponibles = \App\Models\Caja::where('activa', true)
            ->when($userSucursalId, fn ($q) => $q->where('sucursal_id', $userSucursalId))
            ->get();

        $ticketSvc = app(ConfiguracionService::class);
        $ticketDefaults = $ticketSvc->valoresPorDefectoTicket80mm();
        $ticketConfig = array_merge($ticketDefaults, $ticketSvc->obtenerGrupo('ticket_80mm'));

        return [
            'categorias' => $categorias,
            'productos' => $query->get(),
            'mesas' => $mesas,
            'clientesDisponibles' => $clientesDisponibles,
            'pedidoQrPendiente' => $pedidoQrPendiente,
            'turnoActivo' => $turnoActivo,
            'cajasDisponibles' => $cajasDisponibles,
            'ticketConfig' => $ticketConfig,
        ];
    }

    #[\Livewire\Attributes\On('pedido-cobrado-exitosamente')]
    public function alCobrarPedidoExitosamente(array $datos): void
    {
        $this->limpiarCarrito();
        $this->mesaId = null;
        $this->modoNuevaAdicion = false;
        $this->dispatch('cerrar-modal-mesas');
    }
}; ?>

<div class="space-y-4"
     wire:poll.5s>

    <!-- Banner de Estado de Conexión Offline / PWA Store-and-Forward -->
    <div x-data="{ online: navigator.onLine }"
         @online.window="online = true"
         @offline.window="online = false"
         x-show="!online" x-cloak
         class="rounded-2xl bg-amber-500/15 border border-amber-500/40 p-3 flex items-center justify-between text-amber-500 animate-pulse shadow-sm">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">wifi_off</span>
            <span class="text-xs font-bold">Modo Offline Activo · Sin conexión de red. Los pedidos locales se conservan y sincronizan automáticamente.</span>
        </div>
        <span class="text-[10px] font-mono font-black uppercase bg-amber-500/20 px-2 py-0.5 rounded">PWA Offline</span>
    </div>

    @if($vistaMesero === 'movil')
        @include('livewire.pos.partials.vista-movil')
    @else
        @include('livewire.pos.partials.vista-escritorio')
    @endif
    <!-- Modal de Apertura Rápida de Turno de Caja desde POS -->
    @include('livewire.pos.partials.modal-apertura-caja')

    <!-- Modal Maestro Unificado de Cobro de Tickets -->
    <livewire:caja.modal-cobro-unificado />

    <!-- Thermal Ticket 80mm Simulation Modal (Optimizado para Impresoras Locales USB / Driver Navegador) -->
    @include('livewire.pos.partials.modal-ticket-preview')

    <!-- Modal Ley 1581 Habeas Data y Consentimiento -->
    @include('livewire.pos.partials.modal-habeas-data')

    <!-- Fase 4 — Aviso interactivo de horario semanal al login del mesero -->
    @if ($mostrarModalTurnoSemanal)
        <x-modal-turno-semanal :dias="$turnoSemanalDias" :etiqueta="$turnoSemanalEtiqueta" :mostrar-posponer="$turnoSemanalPuedePosponer" />
    @endif
</div>
