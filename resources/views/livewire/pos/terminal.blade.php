<?php

use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\PedidoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
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

    public function mount(): void
    {
        $this->modoNuevaAdicion = false;

        if (request()->has('vista') && in_array(request()->query('vista'), ['pc', 'tablet', 'movil'])) {
            $this->vistaMesero = request()->query('vista');
        }

        $mesaIdParam = request()->query('mesa_id');
        if ($mesaIdParam) {
            $this->mesaId = (int) $mesaIdParam;
            $this->tipo = 'mesa';

            // If table has an active order, load it
            $pedidoExistente = Pedido::where('mesa_id', $this->mesaId)->activos()->latest()->first();
            if ($pedidoExistente) {
                foreach ($pedidoExistente->items as $item) {
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
        }
    }

    public function updatedMesaId(mixed $value): void
    {
        $this->modoNuevaAdicion = false;
        $this->limpiarCarrito();
        if ($value) {
            $pedidoExistente = Pedido::where('mesa_id', (int) $value)->activos()->latest()->first();
            if ($pedidoExistente) {
                foreach ($pedidoExistente->items as $item) {
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
        }
    }

    public function agregarProducto(int $productoId): void
    {
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

    public function seleccionarCliente(int $id): void
    {
        $cliente = \App\Models\Cliente::with('direcciones')->find($id);
        if ($cliente) {
            $this->clienteId = $cliente->id;
            $this->nombreCliente = $cliente->nombre;
            $this->telefonoCliente = $cliente->telefono;
            $this->puntosDisponibles = $cliente->puntos_fidelidad;

            $dir = $cliente->direccionPredeterminada ?? $cliente->direcciones->first();
            if ($dir) {
                $this->direccionId = $dir->id;
                $this->direccionDelivery = $dir->direccion_completa;
            }
        }
    }

    public function desvincularCliente(): void
    {
        $this->clienteId = null;
        $this->direccionId = null;
        $this->nombreCliente = '';
        $this->telefonoCliente = '';
        $this->direccionDelivery = '';
        $this->puntosDisponibles = 0;
        $this->puntosCanjeados = 0;
        $this->descuentoPuntos = 0.0;
        $this->sugerenciasClientes = [];
        $this->mostrarSugerencias = false;
    }

    public function updatedNombreCliente(string $valor): void
    {
        $termino = trim($valor);
        if (mb_strlen($termino) >= 4) {
            $clienteService = app(\App\Services\ClienteService::class);
            $this->sugerenciasClientes = $clienteService->buscarPredictivo($termino, 6)
                ->map(fn (\App\Models\Cliente $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'telefono' => $c->telefono,
                    'email' => $c->email,
                    'tier' => $c->tier,
                    'badge_class' => $c->badgeTier()['color'] ?? '',
                    'badge_label' => $c->badgeTier()['label'] ?? strtoupper($c->tier ?? 'OCASIONAL'),
                    'puntos' => $c->puntos_fidelidad,
                ])
                ->all();
            $this->mostrarSugerencias = count($this->sugerenciasClientes) > 0;
        } else {
            $this->sugerenciasClientes = [];
            $this->mostrarSugerencias = false;
        }
    }

    public function seleccionarClientePredictivo(int $id): void
    {
        $this->seleccionarCliente($id);
        $this->mostrarSugerencias = false;
        $this->sugerenciasClientes = [];
    }

    public function cerrarSugerencias(): void
    {
        $this->mostrarSugerencias = false;
        $this->sugerenciasClientes = [];
    }

    public function abrirModalHabeasData(): void
    {
        if ($this->clienteId) {
            $cliente = \App\Models\Cliente::find($this->clienteId);
            if ($cliente) {
                $this->habeasNombre = $cliente->nombre;
                $this->habeasTelefono = $cliente->telefono ?? '';
                $this->habeasEmail = $cliente->email ?? '';
                $this->habeasAcepta = (bool) $cliente->acepta_tratamiento_datos;
                $this->habeasWhatsapp = $cliente->autoriza_whatsapp ?? true;
                $this->habeasEmailPromos = $cliente->autoriza_email ?? true;
                $this->habeasDireccion = $this->direccionDelivery;
            }
        } else {
            $this->habeasNombre = $this->nombreCliente;
            $this->habeasTelefono = $this->telefonoCliente;
            $this->habeasEmail = '';
            $this->habeasAcepta = false;
            $this->habeasWhatsapp = true;
            $this->habeasEmailPromos = true;
            $this->habeasDireccion = $this->direccionDelivery;
        }

        $this->mostrarModalHabeasData = true;
    }

    public function guardarHabeasData(): void
    {
        $this->validate([
            'habeasNombre' => 'required|string|min:3|max:100',
            'habeasAcepta' => 'accepted',
            'habeasTelefono' => 'nullable|string|max:20',
            'habeasEmail' => 'nullable|email|max:100',
            'habeasDireccion' => 'nullable|string|max:255',
        ], [
            'habeasNombre.required' => 'El nombre del cliente es obligatorio.',
            'habeasNombre.min' => 'El nombre debe tener al menos 3 letras.',
            'habeasAcepta.accepted' => 'Debe aceptar la política de tratamiento de datos personales (Habeas Data).',
            'habeasEmail.email' => 'Ingrese un correo electrónico válido.',
        ]);

        $clienteService = app(\App\Services\ClienteService::class);

        if (! $this->clienteId) {
            $cliente = $clienteService->buscarOcrearOcasional($this->habeasNombre);
            $this->clienteId = $cliente->id;
            $this->nombreCliente = $cliente->nombre;
        } else {
            $cliente = \App\Models\Cliente::findOrFail($this->clienteId);
            $cliente->update(['nombre' => trim($this->habeasNombre)]);
            $this->nombreCliente = $cliente->nombre;
        }

        $clienteService->registrarConsentimientoHabeasData($cliente, [
            'telefono' => $this->habeasTelefono ?: null,
            'email' => $this->habeasEmail ?: null,
            'direccion' => $this->habeasDireccion ?: null,
            'acepta_tratamiento_datos' => $this->habeasAcepta,
            'canal_autorizacion_datos' => 'pos_terminal',
            'autoriza_whatsapp' => $this->habeasWhatsapp,
            'autoriza_email' => $this->habeasEmailPromos,
        ]);

        $this->telefonoCliente = $cliente->fresh()->telefono ?? '';
        if (! empty($this->habeasDireccion)) {
            $this->direccionDelivery = $this->habeasDireccion;
        }

        $this->mostrarModalHabeasData = false;
        session()->flash('notificacion', "¡Cliente {$cliente->nombre} registrado y autorizado con éxito!");
    }

    public function canjearPuntos(int $puntos): void
    {
        $this->authorize('canjearPuntos', Pedido::class);

        if ($puntos > $this->puntosDisponibles) {
            $puntos = $this->puntosDisponibles;
        }

        $remanente = max(0.0, (float) $this->subtotal - (float) $this->descuento);
        $descuentoCalculado = app(\App\Services\FidelizacionService::class)->calcularDescuentoPorPuntos($puntos);
        if ($descuentoCalculado > $remanente) {
            $descuento = $remanente;
            $puntos = (int) ceil($descuento / 10);
        } else {
            $descuento = $descuentoCalculado;
        }

        $this->puntosCanjeados = $puntos;
        $this->descuentoPuntos = $descuento;
    }

    public function limpiarCanje(): void
    {
        $this->puntosCanjeados = 0;
        $this->descuentoPuntos = 0.0;
    }

    public function updatedDescuentoPuntos(): void
    {
        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $this->descuentoPuntos = min($this->descuentoPuntos, app(\App\Services\FidelizacionService::class)->calcularDescuentoPorPuntos($this->puntosCanjeados));
        } else {
            $this->descuentoPuntos = 0.0;
        }
    }

    public function updatedPuntosCanjeados(): void
    {
        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $this->canjearPuntos($this->puntosCanjeados);
        } else {
            $this->limpiarCanje();
        }
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

            if ($this->modoNuevaAdicion) {
                foreach ($this->carrito as $productoId => $itemCarrito) {
                    $producto = Producto::find($productoId);
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
                            $producto = Producto::find($productoId);
                            if ($producto) {
                                $pedidoService->agregarItem($pedidoExistente, $producto, $diferencia, $itemCarrito['notas'] ?? null);
                            }
                        }
                    } else {
                        $producto = Producto::find($productoId);
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

    public function abrirModalCobro(): void
    {
        if ($this->modoNuevaAdicion && $this->tipo === 'mesa' && $this->mesaId) {
            $pedidoActivo = $this->obtenerPedidoActivoMesa();
            if ($pedidoActivo) {
                if (! empty($this->carrito)) {
                    $pedidoService = app(PedidoService::class);
                    foreach ($this->carrito as $productoId => $itemCarrito) {
                        $producto = Producto::find($productoId);
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
        $turnoActivo = \App\Models\TurnoCaja::where('estado', 'abierto')
            ->when($userSucursalId, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $userSucursalId)))
            ->latest()
            ->first();

        if (! $turnoActivo) {
            if (Gate::allows('abrir', App\Models\TurnoCaja::class)) {
                $caja = \App\Models\Caja::where('activa', true)
                    ->when($userSucursalId, fn ($q) => $q->where('sucursal_id', $userSucursalId))
                    ->first() ?? \App\Models\Caja::where('activa', true)->first();
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

        $this->tipoPropina = 'cero';
        $this->montoPropina = 0.0;
        $this->porcentajePropina = 0.0;
        $this->montoPagado = $this->total;
        $this->mostrarModalCobro = true;
    }

    public function abrirModalAperturaPosManual(): void
    {
        $userSucursalId = Auth::user()?->sucursal_id;
        $caja = \App\Models\Caja::where('activa', true)
            ->when($userSucursalId, fn ($q) => $q->where('sucursal_id', $userSucursalId))
            ->first() ?? \App\Models\Caja::where('activa', true)->first();
        $this->cajaAperturaId = $caja?->id;
        $this->baseAperturaPos = 150000.0;
        $this->notasAperturaPos = 'Apertura manual iniciada desde POS';
        $this->mostrarModalAperturaPos = true;
    }

    public function abrirTurnoDesdePos(): void
    {
        $this->authorize('abrir', \App\Models\TurnoCaja::class);

        $this->validate([
            'cajaAperturaId' => 'required|exists:cajas,id',
            'baseAperturaPos' => 'required|numeric|min:0',
        ]);

        $sucursalId = Auth::user()?->sucursal_id;
        $caja = \App\Models\Caja::when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->findOrFail($this->cajaAperturaId);

        try {
            $turno = app(\App\Services\CajaService::class)->abrirTurno(
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
                $this->montoPagado = $this->total;
                $this->mostrarModalCobro = true;
            }
        } catch (\Exception $e) {
            $this->addError('baseAperturaPos', $e->getMessage());
        }
    }

    public function setMontoExacto(): void
    {
        $this->montoPagado = $this->totalConPropina;
    }

    public function sumarMonto(float $cantidad): void
    {
        $this->montoPagado = $cantidad;
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

        $itemsDb = $pedido->items()->get()->keyBy('producto_id');

        foreach ($this->carrito as $prodId => $itemCarrito) {
            $cantCarrito = (int) $itemCarrito['cantidad'];
            if (! $itemsDb->has($prodId)) {
                return false;
            }
            $itemDb = $itemsDb->get($prodId);
            if ($cantCarrito > (int) $itemDb->cantidad) {
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
            foreach ($pedidoExistente->items as $item) {
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

        $itemsDb = $pedido->items()->get()->keyBy('producto_id');
        $nuevos = 0;

        foreach ($this->carrito as $prodId => $itemCarrito) {
            $cantCarrito = (int) $itemCarrito['cantidad'];
            if (! $itemsDb->has($prodId)) {
                $nuevos += $cantCarrito;
            } else {
                $itemDb = $itemsDb->get($prodId);
                $diff = $cantCarrito - (int) $itemDb->cantidad;
                if ($diff > 0) {
                    $nuevos += $diff;
                }
            }
        }

        return $nuevos;
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
        if ($this->tipo !== 'mesa' || ! $this->mesaId) {
            return ! empty($this->carrito);
        }

        $pedido = $this->obtenerPedidoActivoMesa();
        if (! $pedido) {
            return ! empty($this->carrito);
        }

        $tieneItems = $pedido->items()->exists();
        $enCocina = in_array($pedido->estado, ['en_cocina', 'en_preparacion', 'en_proceso'])
            && $pedido->items()->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])->exists();

        return $tieneItems && ! $enCocina;
    }

    public function procesarCobro(): void
    {
        $this->authorize('cobrar', Pedido::class);

        if ($this->modoNuevaAdicion && $this->tipo === 'mesa' && $this->mesaId) {
            $pedidoActivo = $this->obtenerPedidoActivoMesa();
            if ($pedidoActivo && ! empty($this->carrito)) {
                $pedidoService = app(PedidoService::class);
                foreach ($this->carrito as $productoId => $itemCarrito) {
                    $producto = Producto::find($productoId);
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
            }
        }

        if ($this->comandaActivaBloqueaCobro()) {
            abort(422, 'La comanda sigue en preparación en cocina: solo se puede cobrar cuando cocina termine la preparación.');
        }

        if ($this->descuento > 0) {
            $this->authorize('aplicarDescuento', Pedido::class);
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
            abort_if(Auth::user()?->sucursal_id && $pedidoExistente->sucursal_id && $pedidoExistente->sucursal_id !== Auth::user()->sucursal_id, 403, 'No autorizado para cobrar pedidos de otra sucursal.');
            if (! $pedidoExistente->cliente_id && $this->clienteId) {
                $pedidoExistente->update([
                    'cliente_id' => $this->clienteId,
                    'nombre_cliente' => $this->nombreCliente,
                ]);
            }

            if ($this->modoNuevaAdicion) {
                foreach ($this->carrito as $productoId => $itemCarrito) {
                    $producto = Producto::find($productoId);
                    if ($producto) {
                        $itemAgregado = $pedidoService->agregarItem($pedidoExistente, $producto, (int) $itemCarrito['cantidad'], $itemCarrito['notas'] ?? null);
                        $itemAgregado->update(['estado_cocina' => 'entregado', 'listo_en' => now()]);
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
                            $producto = Producto::find($productoId);
                            if ($producto) {
                                $itemAgregado = $pedidoService->agregarItem($pedidoExistente, $producto, $diferencia, $itemCarrito['notas'] ?? null);
                                $itemAgregado->update(['estado_cocina' => 'entregado', 'listo_en' => now()]);
                            }
                        }
                    } else {
                        $producto = Producto::find($productoId);
                        if ($producto) {
                            $itemAgregado = $pedidoService->agregarItem($pedidoExistente, $producto, $cantidadCarrito, $itemCarrito['notas'] ?? null);
                            $itemAgregado->update(['estado_cocina' => 'entregado', 'listo_en' => now()]);
                        }
                    }
                }
            }
            $pedido = $pedidoExistente->fresh(['items', 'mesa']);
        } else {
            if ($this->puntosCanjeados > 0 && $this->clienteId) {
                $this->authorize('canjearPuntos', Pedido::class);
                $this->descuentoPuntos = min($this->descuentoPuntos, app(\App\Services\FidelizacionService::class)->calcularDescuentoPorPuntos($this->puntosCanjeados));
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

        if ($this->montoPagado < $totalConPropina) {
            $this->addError('montoPagado', 'El monto pagado no puede ser menor al total.');

            return;
        }

        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $cliente = \App\Models\Cliente::find($this->clienteId);
            if ($cliente) {
                app(\App\Services\FidelizacionService::class)->canjearPuntos($cliente, $this->puntosCanjeados, $pedido);
            }
        }

        $this->pedidoCompletado = null;

        try {
            $this->pedidoCompletado = $pedidoService->cobrarPedido(
                $pedido,
                $this->metodoPago,
                $this->montoPagado,
                strtolower((string) $this->metodoPago) === 'mixto' ? (float) $this->montoEfectivoMixto : null,
                $propina,
                $this->porcentajePropina
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->addError('montoPagado', $e->getMessage());

            return;
        }

        $this->mostrarModalCobro = false;
        $this->mostrarTicket = true;
        $this->idempotenciaUuid = null;
        $this->limpiarCarrito();
    }

    public function cerrarTicket(): void
    {
        $this->mostrarTicket = false;
        $this->pedidoCompletado = null;

        // rol intencional, no permiso: el mesero vuelve a su comandera al cerrar el ticket
        if (Auth::user()?->role?->slug === 'mesero') {
            $this->mesaId = null;
            $this->limpiarCarrito();
            $this->redirect(route('pos'), navigate: true);

            return;
        }

        $this->redirect(route('mesas'), navigate: true);
    }

    public function atenderPedidoQrActual(): void
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
                session()->flash('error', 'Error al asignar pedido: '.$e->getMessage());
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
            $query->where('nombre', 'ilike', '%'.$this->busqueda.'%');
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
            $term = '%'.trim($this->busquedaCliente).'%';
            $clientesQuery->where(function ($q) use ($term) {
                $q->where('nombre', 'ilike', $term)
                    ->orWhere('telefono', 'ilike', $term);
            });
        }
        $clientesDisponibles = $clientesQuery->orderByDesc('puntos_fidelidad')->limit(50)->get();

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
            }))->map(fn (array $c) => (new Categoria)->forceFill($c))->all()
        );

        $mesasCache = Cache::remember('pos.terminal.mesas', 60, function (): array {
            return Mesa::with('mesero:id,name')->orderBy('numero')
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

        $userSucursalId = Auth::user()?->sucursal_id;
        $mesasColeccion = collect($mesasCache);
        if ($userSucursalId) {
            $mesasColeccion = $mesasColeccion->filter(fn ($m) => ($m['sucursal_id'] ?? null) == $userSucursalId);
        }

        $mesas = new \Illuminate\Database\Eloquent\Collection(
            $mesasColeccion->map(fn (array $m) => (new Mesa)->forceFill($m))->all()
        );

        $turnoActivo = \App\Models\TurnoCaja::where('estado', 'abierto')
            ->when($userSucursalId, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $userSucursalId)))
            ->with('caja')
            ->latest()
            ->first();

        $cajasDisponibles = \App\Models\Caja::where('activa', true)
            ->when($userSucursalId, fn ($q) => $q->where('sucursal_id', $userSucursalId))
            ->get();

        return [
            'categorias' => $categorias,
            'productos' => $query->get(),
            'mesas' => $mesas,
            'clientesDisponibles' => $clientesDisponibles,
            'pedidoQrPendiente' => $pedidoQrPendiente,
            'turnoActivo' => $turnoActivo,
            'cajasDisponibles' => $cajasDisponibles,
        ];
    }
}; ?>

<div class="space-y-4">
    @if($vistaMesero === 'movil')
        <!-- ========================================================================= -->
        <!-- EXPERIENCIA MÓVIL DEDICADA: AURA GASTRO POCKET POS (UX/UI MÓVIL)           -->
        <!-- ========================================================================= -->
        <div 
            x-data="{
                mostrarSelectorCategorias: false,
                scrollCatLeft() {
                    $refs.catMobileTrack.scrollBy({ left: -220, behavior: 'smooth' });
                },
                scrollCatRight() {
                    $refs.catMobileTrack.scrollBy({ left: 220, behavior: 'smooth' });
                },
                scrollMenuTop() {
                    const el = document.getElementById('posFoodFeed');
                    if (el) el.scrollTo({ top: 0, behavior: 'smooth' });
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                scrollMenuBottom() {
                    const el = document.getElementById('posFoodFeed');
                    if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                }
            }"
            class="max-w-md mx-auto w-full sm:my-2 relative"
        >
            <style>
                .pos-scroll-vertical {
                    scrollbar-width: thin !important;
                    scrollbar-color: #b91c1c rgba(0, 0, 0, 0.08) !important;
                }
                .pos-scroll-vertical::-webkit-scrollbar {
                    display: block !important;
                    width: 7px !important;
                }
                .pos-scroll-vertical::-webkit-scrollbar-track {
                    background: rgba(0, 0, 0, 0.05) !important;
                    border-radius: 9999px !important;
                }
                .pos-scroll-vertical::-webkit-scrollbar-thumb {
                    background: #b91c1c !important;
                    border-radius: 9999px !important;
                }
                .pos-scroll-vertical::-webkit-scrollbar-thumb:hover {
                    background: #991b1b !important;
                }
            </style>

            <!-- Marco Táctil Nativo Móvil (Edge-to-edge en teléfonos, carcasa premium en PC) -->
            <div class="relative bg-surface-container-lowest sm:rounded-[36px] sm:border sm:border-surface-container-high/80 sm:shadow-2xl overflow-hidden transition-all flex flex-col">
                
                <!-- Barra Superior de Estado / Dispositivo Móvil -->
                <div class="bg-surface-container-low px-4 py-2 border-b border-surface-container-high/60 flex items-center justify-between text-[11px] font-bold text-on-surface-variant">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary/10 text-primary text-[10px]">🍽️</span>
                        <span class="font-black text-on-surface">RestoMaster Pocket</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @if($turnoActivo)
                            <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                            <span class="text-[10px] text-secondary font-mono font-bold">Turno #{{ $turnoActivo->id }}</span>
                        @else
                            <span class="w-2 h-2 rounded-full bg-error"></span>
                            <span class="text-[10px] text-error font-mono font-bold">Caja Cerrada</span>
                        @endif
                    </div>
                </div>

                <!-- Cabecera de la Comandera: Perfil + Selector de Vistas + Segmented Mode + Mesa -->
                <div class="p-3.5 space-y-3 bg-surface-container-lowest border-b border-surface-container-high/50">
                    <!-- Fila 1: Perfil Mesero + Selector de Vistas (PC, Tablet, Móvil) -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 rounded-xl bg-primary text-on-primary flex items-center justify-center font-bold text-xs shadow-xs shrink-0">
                                <span class="material-symbols-outlined text-[18px]">badge</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-on-surface truncate leading-tight">{{ Auth::user()->name }}</p>
                                <p class="text-[9px] text-primary font-bold uppercase tracking-wider">Comandera de Bolsillo</p>
                            </div>
                        </div>

                        {{-- rol intencional, no permiso: selector de vistas exclusivo de la comandera del mesero --}}
                        @if(Auth::user()?->role?->slug === 'mesero')
                            <!-- Selector de Vistas Táctiles en Móvil -->
                            <div class="flex items-center gap-1 bg-surface-container-low p-1 rounded-xl border border-surface-container-high shrink-0" role="group" aria-label="Selector de vistas">
                                <button type="button" wire:click="cambiarVista('pc')" id="btnVistaPc" class="px-2 py-1 rounded-lg text-[10px] font-black text-on-surface-variant hover:text-on-surface cursor-pointer" title="Vista PC">
                                    💻 PC
                                </button>
                                <button type="button" wire:click="cambiarVista('tablet')" id="btnVistaTablet" class="px-2 py-1 rounded-lg text-[10px] font-black text-on-surface-variant hover:text-on-surface cursor-pointer" title="Vista Tablet">
                                    📟 Tab
                                </button>
                                <button type="button" wire:click="cambiarVista('movil')" id="btnVistaMovil" class="px-2.5 py-1 rounded-lg text-[10px] font-black bg-primary text-on-primary shadow-xs cursor-pointer" title="Vista Móvil">
                                    📱 Móvil
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Fila 2: Segmented Control Modo (En Mesa vs Para Llevar) -->
                    <div class="grid grid-cols-12 gap-2">
                        <div class="col-span-12 flex rounded-xl bg-surface-container-low p-1 border border-surface-container-high">
                            <button 
                                type="button" 
                                wire:click="$set('tipo', 'mesa')" 
                                class="flex-1 py-1.5 rounded-lg text-center text-xs font-black transition-all cursor-pointer flex items-center justify-center gap-1.5 {{ $tipo === 'mesa' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">table_restaurant</span>
                                <span>En Mesa</span>
                            </button>
                            <button 
                                type="button" 
                                wire:click="$set('tipo', 'mostrador')" 
                                class="flex-1 py-1.5 rounded-lg text-center text-xs font-black transition-all cursor-pointer flex items-center justify-center gap-1.5 {{ $tipo === 'mostrador' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">takeout_dining</span>
                                <span>Para Llevar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Fila 3: Selector Táctil Ergonómico de Mesa o Cliente -->
                    @if($tipo === 'mesa')
                        <div class="rounded-2xl border p-2.5 flex items-center gap-2.5 transition-all {{ !$mesaId ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-surface-container-high bg-surface-container-low' }}">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $mesaId ? 'bg-secondary text-on-secondary shadow-xs' : 'bg-primary text-on-primary shadow-xs' }}">
                                <span class="material-symbols-outlined text-[20px]">table_restaurant</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-[10px] font-black uppercase tracking-wider {{ !$mesaId ? 'text-primary' : 'text-on-surface-variant' }}">
                                    {{ $mesaId ? 'Mesa Activa' : 'Paso 1: Asignar Mesa' }}
                                </span>
                                <select 
                                    wire:model.live="mesaId" 
                                    id="mesaSelectMovil"
                                    class="w-full bg-transparent border-0 p-0 text-xs font-black text-on-surface focus:ring-0 cursor-pointer"
                                >
                                    <option value="">Seleccionar mesa del salón...</option>
                                    @foreach($mesas as $m)
                                        {{-- rol intencional, no permiso: guard de mesa ajena (identidad de dominio) --}}
                                        @php $mesaAjenaMovil = $m->mesero_id && (int) $m->mesero_id !== (int) Auth::id() && Auth::user()?->role?->slug === 'mesero'; @endphp
                                        <option value="{{ $m->id }}" @disabled($mesaAjenaMovil)>
                                            Mesa {{ $m->numero }} (Zona {{ $m->zona }} - {{ ucfirst($m->estado) }}){{ $m->mesero_nombre ? ' · '.$m->mesero_nombre : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if(!$mesaId)
                                <span class="text-[10px] font-bold text-primary animate-pulse shrink-0">← Elegir</span>
                            @else
                                <span class="material-symbols-outlined text-[18px] text-secondary shrink-0">check_circle</span>
                            @endif
                        </div>
                    @else
                        <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-2.5 flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-primary text-on-primary flex items-center justify-center shrink-0 shadow-xs">
                                <span class="material-symbols-outlined text-[20px]">person</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant">Cliente en Mostrador</span>
                                <input 
                                    type="text" 
                                    wire:model.live="nombreCliente" 
                                    placeholder="Nombre del comensal..." 
                                    class="w-full bg-transparent border-0 p-0 text-xs font-black text-on-surface placeholder:text-on-surface-variant/60 focus:ring-0"
                                />
                            </div>
                        </div>
                    @endif

                    @if($pedidoQrPendiente)
                        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-2.5 flex items-center justify-between gap-2 shadow-xs animate-pulse">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-amber-700 text-[20px] shrink-0">notifications_active</span>
                                <div class="min-w-0">
                                    <span class="block text-[10px] font-black uppercase text-amber-900 leading-tight">Pedido QR Recibido</span>
                                    <span class="text-[11px] font-bold text-amber-800 truncate block">{{ $pedidoQrPendiente->nombre_cliente ?? 'Comensal' }} ({{ $pedidoQrPendiente->items->count() }} platos)</span>
                                </div>
                            </div>
                            <button 
                                wire:click="atenderPedidoQrActual"
                                type="button"
                                class="px-2.5 py-1.5 rounded-xl bg-primary text-on-primary text-[11px] font-black shadow-sm hover:bg-primary/90 active:scale-95 transition cursor-pointer shrink-0"
                            >
                                Tomar Mesa
                            </button>
                        </div>
                    @endif

                    <!-- Fila 4 (Al Inicio del Bloque): Acceso y Estado de Comanda para el Mesero -->
                    <div class="rounded-2xl border border-primary/30 bg-primary/5 p-2.5 flex items-center justify-between gap-2 shadow-xs">
                        <button 
                            type="button"
                            wire:click="$toggle('mostrarComandaMovil')"
                            id="btnVerComandaHeader"
                            class="flex items-center gap-2.5 text-left flex-1 cursor-pointer min-w-0"
                            title="Toca para ver u ocultar la comanda actual"
                        >
                            <div class="w-9 h-9 rounded-xl bg-primary text-on-primary flex items-center justify-center font-bold shadow-sm shrink-0">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-black text-on-surface">Comanda Activa</span>
                                    <span class="px-1.5 py-0.2 rounded-full bg-primary text-on-primary text-[10px] font-mono font-bold">{{ count($carrito) }}</span>
                                </div>
                                <span class="text-xs font-mono font-black text-primary truncate block">${{ number_format($this->total, 0, ',', '.') }}</span>
                            </div>
                        </button>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button 
                                type="button" 
                                wire:click="$toggle('mostrarComandaMovil')" 
                                id="btnToggleComandaHeader"
                                class="px-2.5 py-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-[11px] font-bold text-on-surface active:scale-95 cursor-pointer border border-surface-container-high shadow-2xs"
                            >
                                {{ $mostrarComandaMovil ? 'Ocultar' : 'Ver Comanda' }}
                            </button>
                            <button 
                                type="button" 
                                wire:click="enviarACocina" 
                                id="btnCocinaHeader"
                                @disabled(empty($carrito) || ($tipo === 'mesa' && !$mesaId)) 
                                class="px-3 py-1.5 rounded-xl bg-primary hover:bg-primary-container text-[11px] font-black text-on-primary shadow-sm disabled:opacity-40 active:scale-95 cursor-pointer"
                            >
                                Cocina
                            </button>
                        </div>
                    </div>
                </div>

                <!-- STICKY HEADER MÓVIL: Buscador + Barra de Navegación de Categorías -->
                <div class="sticky top-0 z-20 bg-surface-container-lowest/95 backdrop-blur-md border-b border-surface-container-high/60 shadow-2xs">
                    <!-- Buscador Móvil Rápido -->
                    <div class="px-3 pt-2.5 pb-1.5">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-2 text-[18px] text-on-surface-variant">search</span>
                            <input 
                                type="text" 
                                wire:model.live.debounce.200ms="busqueda" 
                                placeholder="Buscar roll, nigiri, bebida..." 
                                class="w-full rounded-xl border border-surface-container-high bg-surface-container-low pl-9 pr-8 py-1.5 text-xs font-bold text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-0"
                            />
                            @if($busqueda)
                                <button type="button" wire:click="$set('busqueda', '')" class="absolute right-2.5 top-2 text-on-surface-variant hover:text-on-surface text-xs font-bold cursor-pointer">✕</button>
                            @endif
                        </div>
                    </div>

                    <!-- Barra de Navegación de Categorías con Botones < y > + Botón Ver Todo Grid -->
                    <div class="flex items-center gap-1 px-2 pb-2">
                        <!-- Flecha Izquierda para Desplazamiento Horizontal -->
                        <button 
                            type="button" 
                            @click="scrollCatLeft()"
                            class="h-8 w-8 shrink-0 flex items-center justify-center rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all active:scale-90 shadow-2xs cursor-pointer"
                            title="Categorías anteriores"
                            aria-label="Categorías anteriores"
                        >
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </button>

                        <!-- Carrusel Horizontal Táctil con Scroll Suave -->
                        <div 
                            x-ref="catMobileTrack" 
                            class="flex-1 flex items-center gap-1.5 overflow-x-auto scroll-smooth py-0.5 scrollbar-none"
                        >
                            <button 
                                type="button" 
                                wire:click="$set('categoriaSeleccionada', null)"
                                class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-black transition-all border cursor-pointer active:scale-95 {{ is_null($categoriaSeleccionada) ? 'bg-primary text-on-primary border-primary shadow-xs' : 'bg-surface-container-low text-on-surface-variant border-surface-container-high hover:bg-surface-container' }}"
                            >
                                <span>🍣</span>
                                <span>Todo</span>
                            </button>
                            @foreach($categorias as $cat)
                                @php $catColor = $cat->color ?? '#e11d48'; @endphp
                                <button 
                                    type="button" 
                                    wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                                    class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-black transition-all border cursor-pointer active:scale-95 {{ $categoriaSeleccionada === $cat->id ? 'text-white shadow-xs' : 'bg-surface-container-low text-on-surface-variant border-surface-container-high hover:bg-surface-container' }}"
                                    @style(['background-color: ' . $catColor => $categoriaSeleccionada === $cat->id, 'border-color: ' . $catColor => $categoriaSeleccionada === $cat->id])
                                >
                                    @if($categoriaSeleccionada !== $cat->id)
                                        <span class="w-2 h-2 rounded-full shrink-0" @style(['background-color: ' . $catColor])></span>
                                    @endif
                                    @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                        <span class="material-symbols-outlined text-[15px]">{{ $cat->icono }}</span>
                                    @else
                                        <span>{{ $cat->icono ?: '🍽️' }}</span>
                                    @endif
                                    <span>{{ $cat->nombre }}</span>
                                    <span class="rounded-full px-1 text-[9px] font-mono {{ $categoriaSeleccionada === $cat->id ? 'bg-white/20 text-white' : 'bg-surface-container text-on-surface-variant' }}">
                                        {{ $cat->productos_count ?? 0 }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <!-- Flecha Derecha para Desplazamiento Horizontal -->
                        <button 
                            type="button" 
                            @click="scrollCatRight()"
                            class="h-8 w-8 shrink-0 flex items-center justify-center rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all active:scale-90 shadow-2xs cursor-pointer"
                            title="Siguientes categorías"
                            aria-label="Siguientes categorías"
                        >
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </button>

                        <!-- Botón Menú / Grid de Todas las Categorías -->
                        <button 
                            type="button" 
                            @click="mostrarSelectorCategorias = true"
                            class="h-8 px-2.5 shrink-0 flex items-center gap-1 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-on-primary text-[10px] font-black transition-all active:scale-95 shadow-2xs cursor-pointer"
                            title="Ver rejilla completa de categorías"
                        >
                            <span class="material-symbols-outlined text-[15px]">grid_view</span>
                            <span>Menú</span>
                        </button>
                    </div>

                    <!-- Indicador Activo de Categoría con Opción de Quitar Filtro -->
                    @if($categoriaSeleccionada)
                        @php $catActiva = $categorias->find($categoriaSeleccionada); @endphp
                        @if($catActiva)
                            <div class="px-3 py-1 bg-primary/5 border-t border-primary/20 flex items-center justify-between text-[11px]">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    @if(preg_match('/^[a-z0-9_]+$/', $catActiva->icono ?? ''))
                                        <span class="material-symbols-outlined text-[14px]">{{ $catActiva->icono }}</span>
                                    @else
                                        <span class="text-xs">{{ $catActiva->icono ?: '🍽️' }}</span>
                                    @endif
                                    <span class="font-bold text-on-surface truncate">
                                        Filtrado: <span class="text-primary font-black">{{ $catActiva->nombre }}</span>
                                    </span>
                                    <span class="px-1.5 py-0.2 rounded-full bg-primary/10 text-primary font-mono text-[9px] font-bold shrink-0">
                                        {{ $productos->count() }} platos
                                    </span>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="$set('categoriaSeleccionada', null)" 
                                    class="text-[10px] font-black text-primary hover:underline flex items-center gap-0.5 cursor-pointer shrink-0 ml-2"
                                >
                                    <span>Ver Todo</span>
                                    <span>✕</span>
                                </button>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Feed de Platos Móvil con Barra de Desplazamiento Vertical Visible -->
                <div 
                    id="posFoodFeed" 
                    class="p-3 space-y-2 bg-surface-container-low/30 overflow-y-auto max-h-[54vh] pr-2 scroll-smooth pos-scroll-vertical"
                >
                    @forelse($productos as $prod)
                        @php $prodColor = $prod->categoria?->color ?? '#e11d48'; @endphp
                        <div class="rounded-2xl border bg-surface-container-lowest p-3 flex items-center justify-between gap-3 shadow-2xs hover:shadow-xs transition-all {{ isset($carrito[$prod->id]) ? 'border-primary/50 bg-primary/5 ring-1 ring-primary/20' : 'border-surface-container-highest' }}"
                             @style(['border-left: 4.5px solid ' . $prodColor])>
                            <!-- Visual & Detalles del Plato -->
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 shadow-2xs"
                                     @style(['background-color: ' . $prodColor . '1a'])>
                                    @if(preg_match('/^[a-z0-9_]+$/', $prod->categoria?->icono ?? ''))
                                        <span class="material-symbols-outlined text-[24px]" @style(['color: ' . $prodColor])>{{ $prod->categoria->icono }}</span>
                                    @else
                                        <span class="text-2xl leading-none">{{ $prod->categoria?->icono ?: '🍽️' }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <h4 class="text-xs font-black text-on-surface truncate">{{ $prod->nombre }}</h4>
                                        @php
                                            $mobileAreaLabel = match(strtolower($prod->area_cocina ?? 'caliente')) {
                                                'sushi', 'fria', 'cocina_fria' => 'Cocina Fría',
                                                'caliente', 'calientes', 'cocina' => 'Caliente',
                                                'barra', 'bebidas' => 'Barra',
                                                'postres' => 'Postres',
                                                default => ucfirst($prod->area_cocina),
                                            };
                                        @endphp
                                        <span class="rounded px-1 py-0.2 text-[8px] font-bold uppercase tracking-wider bg-surface-container text-on-surface-variant shrink-0">
                                            {{ $mobileAreaLabel }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-on-surface-variant line-clamp-1 mt-0.5">
                                        {{ $prod->descripcion ?: 'Elaborado fresco en barra.' }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-mono font-black text-primary">
                                            ${{ number_format((float)$prod->precio, 0, ',', '.') }}
                                        </span>
                                        @if(isset($carrito[$prod->id]))
                                            <span class="text-[9px] font-bold text-primary bg-primary/10 px-1.5 py-0.2 rounded">
                                                {{ $carrito[$prod->id]['cantidad'] }} en orden
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Stepper Inline Táctil o Botón Agregar Directo -->
                            <div class="shrink-0">
                                @if(isset($carrito[$prod->id]))
                                    <div class="flex items-center gap-1 bg-surface-container-low rounded-xl p-1 border border-primary/30 shadow-xs">
                                        <button 
                                            type="button" 
                                            wire:click="decrementarCantidad({{ $prod->id }})" 
                                            class="w-7 h-7 rounded-lg bg-surface-container-lowest text-on-surface font-black text-xs flex items-center justify-center shadow-2xs active:scale-90 cursor-pointer"
                                            title="Disminuir"
                                        >
                                            -
                                        </button>
                                        <span class="w-5 text-center font-mono font-black text-xs text-primary">
                                            {{ $carrito[$prod->id]['cantidad'] }}
                                        </span>
                                        <button 
                                            type="button" 
                                            wire:click="incrementarCantidad({{ $prod->id }})" 
                                            class="w-7 h-7 rounded-lg bg-primary text-on-primary font-black text-xs flex items-center justify-center shadow-2xs active:scale-90 cursor-pointer"
                                            title="Aumentar"
                                        >
                                            +
                                        </button>
                                    </div>
                                @else
                                    <button 
                                        type="button" 
                                        wire:click="agregarProducto({{ $prod->id }})" 
                                        class="w-10 h-10 rounded-xl bg-primary text-on-primary flex items-center justify-center font-black text-lg shadow-sm hover:bg-primary-container active:scale-90 transition-all cursor-pointer"
                                        title="Agregar a la comanda"
                                    >
                                        +
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-surface-container-highest p-8 text-center text-on-surface-variant bg-surface-container-lowest">
                            <span class="material-symbols-outlined text-[32px] opacity-40">ramen_dining</span>
                            <p class="text-xs font-semibold mt-1">No hay productos en esta categoría.</p>
                        </div>
                    @endforelse
                </div>
                
                <!-- BARRA DE NAVEGACIÓN VERTICAL MÓVIL (Subir / Bajar / Categorías Rápido) -->
                <div 
                    class="absolute right-2 top-1/2 -translate-y-1/2 z-20 flex flex-col items-center gap-1.5 p-1 rounded-2xl bg-surface-container-lowest/90 backdrop-blur-md border border-surface-container-high shadow-lg"
                    role="navigation"
                    aria-label="Controles verticales del menú"
                >
                    <!-- Botón Subir al Inicio -->
                    <button 
                        type="button" 
                        @click="scrollMenuTop()"
                        class="w-7 h-7 rounded-xl bg-surface-container hover:bg-primary hover:text-on-primary text-on-surface-variant flex items-center justify-center shadow-2xs transition-all active:scale-90 cursor-pointer"
                        title="Subir al inicio del menú"
                        aria-label="Subir al inicio"
                    >
                        <span class="material-symbols-outlined text-[16px]">arrow_upward</span>
                    </button>

                    <!-- Botón Desplegar Rejilla de Categorías -->
                    <button 
                        type="button" 
                        @click="mostrarSelectorCategorias = true"
                        class="w-7 h-7 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-on-primary flex items-center justify-center shadow-2xs transition-all active:scale-90 cursor-pointer"
                        title="Menú de categorías completo"
                        aria-label="Ver todas las categorías"
                    >
                        <span class="material-symbols-outlined text-[16px]">category</span>
                    </button>

                    <!-- Botón Bajar al Final -->
                    <button 
                        type="button" 
                        @click="scrollMenuBottom()"
                        class="w-7 h-7 rounded-xl bg-surface-container hover:bg-primary hover:text-on-primary text-on-surface-variant flex items-center justify-center shadow-2xs transition-all active:scale-90 cursor-pointer"
                        title="Bajar al final del menú"
                        aria-label="Bajar al final"
                    >
                        <span class="material-symbols-outlined text-[16px]">arrow_downward</span>
                    </button>
                </div>

                <!-- MODAL SELECTOR DE CATEGORÍAS (Centrado en Pantalla con Backdrop Viewport) -->
                <div 
                    x-show="mostrarSelectorCategorias" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 backdrop-blur-xs p-4 lg:pl-64"
                    style="display: none;"
                >
                    <div 
                        @click.outside="mostrarSelectorCategorias = false"
                        class="w-full max-w-sm rounded-3xl bg-surface-container-lowest p-5 shadow-2xl border border-surface-container-highest max-h-[80vh] flex flex-col justify-between overflow-hidden animate-in zoom-in-95 duration-150"
                    >
                        <div>
                            <div class="flex items-center justify-between border-b border-surface-container-high pb-2.5 mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[20px]">restaurant_menu</span>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-black text-on-surface">Seleccionar Categoría</h3>
                                        <p class="text-[10px] text-on-surface-variant">Salta directamente a la sección que buscas</p>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    @click="mostrarSelectorCategorias = false"
                                    class="w-8 h-8 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition cursor-pointer"
                                    title="Cerrar modal"
                                >
                                    <span class="material-symbols-outlined text-[20px]">close</span>
                                </button>
                            </div>

                            <!-- Botón Ver Todo el Menú -->
                            <button 
                                type="button"
                                wire:click="$set('categoriaSeleccionada', null)"
                                @click="mostrarSelectorCategorias = false; scrollMenuTop();"
                                class="w-full mb-3 p-2.5 rounded-2xl border transition-all flex items-center justify-between cursor-pointer active:scale-98 {{ is_null($categoriaSeleccionada) ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface hover:bg-surface-container' }}"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xl">🍱</span>
                                    <div class="text-left">
                                        <p class="text-xs font-black">Todo el Menú</p>
                                        <p class="text-[10px] {{ is_null($categoriaSeleccionada) ? 'text-on-primary/80' : 'text-on-surface-variant' }}">Ver la carta completa del restaurante</p>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </button>

                            <!-- Rejilla de Categorías -->
                            <div class="grid grid-cols-2 gap-2 max-h-[46vh] overflow-y-auto pr-1 scrollbar-none">
                                @foreach($categorias as $cat)
                                    <button 
                                        type="button"
                                        wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                                        @click="mostrarSelectorCategorias = false; scrollMenuTop();"
                                        class="p-2.5 rounded-2xl border text-left transition-all relative overflow-hidden group cursor-pointer active:scale-95 {{ $categoriaSeleccionada === $cat->id ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface hover:bg-surface-container hover:border-primary/40' }}"
                                    >
                                        <div class="flex items-start justify-between">
                                            @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                                <span class="material-symbols-outlined text-2xl mb-1 block">{{ $cat->icono }}</span>
                                            @else
                                                <span class="text-2xl mb-1 block leading-none">{{ $cat->icono ?: '🍽️' }}</span>
                                            @endif
                                            <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full font-bold {{ $categoriaSeleccionada === $cat->id ? 'bg-on-primary/20 text-on-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                                                {{ $cat->productos_count ?? 0 }}
                                            </span>
                                        </div>
                                        <p class="text-xs font-black truncate leading-tight">{{ $cat->nombre }}</p>
                                        <p class="text-[9px] truncate mt-0.5 {{ $categoriaSeleccionada === $cat->id ? 'text-on-primary/80' : 'text-on-surface-variant' }}">
                                            {{ $cat->descripcion ?: 'Especialidades frescas' }}
                                        </p>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-3 pt-2.5 border-t border-surface-container-high text-center">
                            <button 
                                type="button" 
                                @click="mostrarSelectorCategorias = false" 
                                class="w-full py-2.5 rounded-xl bg-surface-container text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer active:scale-98"
                            >
                                Cerrar Selector
                            </button>
                        </div>
                    </div>
                </div>

                <!-- MODAL DE COMANDA EN MANO MÓVIL (Centrado en Pantalla con Backdrop Viewport) -->
                @if($mostrarComandaMovil)
                    <div 
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 backdrop-blur-xs p-4 lg:pl-64"
                    >
                        <div 
                            @click.outside="$wire.set('mostrarComandaMovil', false)"
                            class="w-full max-w-sm rounded-3xl bg-surface-container-lowest p-5 shadow-2xl border border-surface-container-highest max-h-[82vh] flex flex-col justify-between overflow-hidden animate-in zoom-in-95 duration-150"
                        >
                            <div class="flex-1 overflow-hidden flex flex-col min-h-0">
                                <div class="flex items-center justify-between border-b border-surface-container-high pb-3 shrink-0">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                            <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-extrabold text-on-surface">Comanda en Mano (Móvil)</h3>
                                            <p class="text-[11px] text-on-surface-variant">
                                                {{ $tipo === 'mesa' ? 'Mesa ' . ($mesaId ? $mesas->find($mesaId)?->numero : 'Sin asignar') : 'Para Llevar' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if(count($carrito) > 0)
                                            <button wire:click="limpiarCarrito" class="text-[10px] font-bold text-error hover:underline cursor-pointer">
                                                Vaciar
                                            </button>
                                        @endif
                                        <button wire:click="$set('mostrarComandaMovil', false)" class="w-8 h-8 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition cursor-pointer">
                                            <span class="material-symbols-outlined text-[20px]">close</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Lista de items móvil -->
                                <div class="mt-3 space-y-2.5 flex-1 min-h-0 overflow-y-auto pr-1 scrollbar-none max-h-[42vh]">
                                    @if($modoNuevaAdicion)
                                        <div class="rounded-2xl border border-primary/30 bg-primary/10 p-2 flex items-center justify-between text-xs animate-fade-in">
                                            <div class="flex items-center gap-1 text-primary font-black text-[11px]">
                                                <span class="material-symbols-outlined text-[15px]">add_circle</span>
                                                <span>Nuevo Pedido / Adición</span>
                                            </div>
                                            <button 
                                                wire:click="cancelarModoAdicion" 
                                                type="button"
                                                class="text-[10px] font-bold text-primary hover:underline cursor-pointer"
                                            >
                                                Ver cuenta total
                                            </button>
                                        </div>
                                    @endif
                                    @forelse($carrito as $pId => $item)
                                        <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-2.5">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-on-surface truncate max-w-[200px]">{{ $item['nombre'] }}</span>
                                                <span class="text-xs font-mono font-black text-primary">${{ number_format($item['precio'] * $item['cantidad'], 0, ',', '.') }}</span>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between gap-1.5">
                                                <div class="flex items-center gap-1">
                                                    <button wire:click="decrementarCantidad({{ $pId }})" class="h-8 w-8 rounded-lg bg-surface-container font-bold text-on-surface shadow-sm active:scale-95 cursor-pointer">-</button>
                                                    <span class="w-6 text-center text-xs font-mono font-bold">{{ $item['cantidad'] }}</span>
                                                    <button wire:click="incrementarCantidad({{ $pId }})" class="h-8 w-8 rounded-lg bg-surface-container font-bold text-on-surface shadow-sm active:scale-95 cursor-pointer">+</button>
                                                </div>
                                                <input type="text" wire:model.lazy="carrito.{{ $pId }}.notas" placeholder="Nota al chef..." class="h-8 flex-1 rounded-lg border border-surface-container-high bg-surface-container-lowest px-2 text-[10px] text-on-surface" />
                                                <button wire:click="eliminarItem({{ $pId }})" class="h-8 w-8 rounded-lg text-on-surface-variant hover:text-error cursor-pointer">✕</button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="py-10 text-center text-xs text-on-surface-variant">
                                            <span class="material-symbols-outlined text-[32px] text-outline-variant block mb-1">local_dining</span>
                                            La comanda está vacía.<br/>Selecciona platos para agregarlos.
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Resumen y acciones móvil -->
                            <div class="mt-3 pt-3 border-t border-surface-container-high space-y-2.5 shrink-0">
                                <div class="flex justify-between text-sm font-black text-on-surface">
                                    <span>Total Neto:</span>
                                    <span class="text-primary font-mono text-lg">${{ number_format($this->total, 0, ',', '.') }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    @if($this->comandaDespachadaPorCocina() && $this->cantidadNuevosItemsParaCocina() === 0)
                                        <button 
                                            wire:click="iniciarNuevoPedido"
                                            type="button"
                                            class="flex h-11 items-center justify-center gap-1.5 rounded-xl border border-primary/40 bg-surface-container text-xs font-extrabold text-primary shadow-sm hover:bg-surface-container-high transition-all active:scale-95 cursor-pointer"
                                            title="Comanda anterior despachada. Iniciar nuevo pedido para esta mesa."
                                        >
                                            <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                                            <span>+ Nuevo Pedido</span>
                                        </button>
                                    @else
                                        <button 
                                            wire:click="enviarACocina"
                                            type="button"
                                            @disabled(empty($carrito) || ($tipo === 'mesa' && !$mesaId) || ($tipo === 'mesa' && $this->comandaYaEnviadaACocina()))
                                            class="flex h-11 items-center justify-center gap-1.5 rounded-xl border text-xs font-black shadow-sm disabled:opacity-40 cursor-pointer active:scale-95 {{ $this->comandaYaEnviadaACocina() ? 'bg-surface-container/50 border-surface-container-high text-on-surface-variant cursor-not-allowed' : 'bg-surface-container border-primary/40 text-primary hover:bg-surface-container-high' }}"
                                            title="{{ $this->comandaYaEnviadaACocina() ? 'Comanda ya enviada a cocina.' : 'Enviar comanda a cocina' }}"
                                        >
                                            <span class="material-symbols-outlined text-[18px]">{{ $this->comandaYaEnviadaACocina() ? 'check_circle' : 'skillet' }}</span>
                                            <span>{{ $this->comandaYaEnviadaACocina() ? '✓ En Cocina' : ($this->cantidadNuevosItemsParaCocina() > 0 && $this->obtenerPedidoActivoMesa() ? 'Enviar +'.$this->cantidadNuevosItemsParaCocina().' Cocina' : 'Enviar Cocina') }}</span>
                                        </button>
                                    @endif
                                    <button 
                                        wire:click="abrirModalCobro"
                                        type="button"
                                        @disabled((empty($carrito) && !$this->obtenerPedidoActivoMesa()) || $this->comandaActivaBloqueaCobro())
                                        class="flex h-11 items-center justify-center gap-1.5 rounded-xl text-xs font-black shadow-md disabled:opacity-40 cursor-pointer active:scale-95 {{ $this->comandaActivaBloqueaCobro() ? 'bg-amber-500/20 text-amber-900 border border-amber-500/40 cursor-not-allowed' : ($this->comandaListaParaCobrar() ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-primary text-on-primary hover:bg-primary-container') }}"
                                        title="{{ $this->comandaActivaBloqueaCobro() ? 'Comanda en preparación en cocina. Solo se puede cobrar cuando cocina termine.' : 'Cobrar Pedido' }}"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">{{ $this->comandaActivaBloqueaCobro() ? 'hourglass_top' : ($this->comandaListaParaCobrar() ? 'check_circle' : 'payments') }}</span>
                                        <span>{{ $this->comandaActivaBloqueaCobro() ? 'En Prep. Cocina' : ($this->comandaListaParaCobrar() ? '✓ Cobrar Listo' : 'Cobrar Pedido') }}</span>
                                    </button>
                                </div>
                                @if($this->comandaActivaBloqueaCobro())
                                    <p class="text-[10px] text-center font-bold text-amber-800 bg-amber-500/15 py-1 px-2 rounded-lg border border-amber-500/30">
                                        ⏳ En preparación en cocina · Cobro bloqueado
                                    </p>
                                @elseif($this->comandaListaParaCobrar())
                                    <p class="text-[10px] text-center font-bold text-emerald-800 bg-emerald-500/15 py-1 px-2 rounded-lg border border-emerald-500/30">
                                        🛎️ ¡Comanda despachada / lista en cocina! Habilitado para cobrar
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        <!-- ========================================================================= -->
        <!-- VISTA PC / TABLET: TERMINAL TÁCTIL DE SALÓN Y MOSTRADOR                    -->
        <!-- ========================================================================= -->
        <div class="space-y-4 {{ $vistaMesero === 'tablet' ? 'max-w-5xl mx-auto' : 'w-full' }}">
            <!-- Top Control Bar (Stitch POS-01 Aura Gastro Expressive OS) -->
            <div class="flex flex-col flex-wrap gap-3 rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-3.5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
                <!-- Order Mode Toggle Pills -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-on-surface-variant flex items-center gap-1 whitespace-nowrap shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-primary">room_service</span>
                        Modo:
                    </span>
            <div class="inline-flex rounded-xl bg-surface-container-low p-1 border border-surface-container-high">
                <button 
                    wire:click="$set('tipo', 'mesa')" 
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all {{ $tipo === 'mesa' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                >
                    <span class="material-symbols-outlined text-[16px]">table_restaurant</span>
                    <span>En Mesa</span>
                </button>
                <button 
                    wire:click="$set('tipo', 'mostrador')" 
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all {{ $tipo === 'mostrador' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                >
                    <span class="material-symbols-outlined text-[16px]">takeout_dining</span>
                    <span>Para Llevar</span>
                </button>
                {{-- rol intencional, no permiso: el botón Delivery se oculta solo al mesero (identidad de flujo, sin ability 1:1) --}}
                @if(Auth::user()?->role?->slug !== 'mesero')
                    <button 
                        wire:click="$set('tipo', 'delivery')" 
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all {{ $tipo === 'delivery' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                    >
                        <span class="material-symbols-outlined text-[16px]">moped</span>
                        <span>Delivery</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Table or Customer Selector -->
        @if($tipo === 'mesa')
            <div class="flex items-center gap-2">
                <label for="mesaId" class="text-xs font-bold text-on-surface-variant flex items-center gap-1 whitespace-nowrap shrink-0">
                    <span class="material-symbols-outlined text-[16px] text-secondary">pin</span>
                    Mesa:
                </label>
                {{-- rol intencional, no permiso: resaltado de ayuda exclusivo del mesero sin mesa --}}
                <select 
                    wire:model.live="mesaId" 
                    id="mesaId" 
                    class="h-9 w-auto max-w-[240px] sm:max-w-xs truncate rounded-xl border bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0 {{ !$mesaId && Auth::user()?->role?->slug === 'mesero' ? 'border-primary/60 ring-2 ring-primary/20' : 'border-surface-container-high' }}"
                >
                    <option value="">Seleccionar mesa del salón...</option>
                    @foreach($mesas as $m)
                        {{-- rol intencional, no permiso: guard de mesa ajena (identidad de dominio) --}}
                        @php $mesaAjena = $m->mesero_id && (int) $m->mesero_id !== (int) Auth::id() && Auth::user()?->role?->slug === 'mesero'; @endphp
                        <option value="{{ $m->id }}" @disabled($mesaAjena)>
                            Mesa {{ $m->numero }} (Zona {{ $m->zona }} - {{ $m->estado }}){{ $m->mesero_nombre ? ' · '.$m->mesero_nombre : '' }}
                        </option>
                    @endforeach
                </select>
                {{-- rol intencional, no permiso: aviso contextual exclusivo del mesero sin mesa --}}
                @if(!$mesaId && Auth::user()?->role?->slug === 'mesero')
                    <span class="text-[11px] text-primary font-bold animate-pulse hidden sm:inline whitespace-nowrap shrink-0">← Elige una mesa</span>
                @endif
            </div>
        @endif

        <!-- Customer Selector (Disponible en todas las modalidades: Mesa, Mostrador, Delivery) -->
        <div class="relative flex items-center gap-2 flex-wrap" x-data="{ openDropdown: @entangle('mostrarSugerencias') }" @click.outside="openDropdown = false; $wire.cerrarSugerencias()">
            @if($clienteId)
                @php 
                    $cli = \App\Models\Cliente::with('direcciones')->find($clienteId); 
                    $badgeCli = $cli?->badgeTier();
                @endphp
                @if($cli)
                    <div class="inline-flex items-center gap-2 rounded-xl bg-surface-container-low border border-primary/40 px-3 py-1.5 text-xs shadow-xs">
                        <span class="material-symbols-outlined text-[16px] text-primary">person</span>
                        <span class="font-extrabold text-on-surface">{{ $cli->nombre }}</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black {{ $badgeCli['color'] ?? '' }}">
                            {{ $badgeCli['label'] ?? strtoupper($cli->tier) }}
                        </span>
                        <span class="px-1.5 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed text-[10px] font-black">
                            {{ number_format($cli->puntos_fidelidad) }} pts
                        </span>
                        @if($tipo === 'delivery' && $cli->direcciones->count() > 0)
                            <select wire:model.live="direccionId" class="rounded-lg border border-outline-variant/30 bg-surface-container-lowest text-[11px] py-1 px-2 font-semibold text-on-surface">
                                @foreach($cli->direcciones as $d)
                                    <option value="{{ $d->id }}">{{ $d->etiqueta }}: {{ Str::limit($d->direccion, 22) }}</option>
                                @endforeach
                            </select>
                        @endif
                        <button 
                            type="button"
                            wire:click="abrirModalHabeasData" 
                            class="inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline px-1 py-0.5 rounded hover:bg-primary/10 cursor-pointer"
                            title="Actualizar datos / Habeas Data"
                        >
                            <span class="material-symbols-outlined text-[14px]">edit_note</span>
                            <span class="hidden sm:inline">Habeas Data</span>
                        </button>
                        <button wire:click="desvincularCliente" class="text-error hover:text-error/80 text-[11px] font-bold ml-1 cursor-pointer" title="Desvincular">✕</button>
                    </div>
                @endif
            @else
                <div class="flex items-center gap-1.5">
                    <div class="relative w-64 sm:w-80 lg:w-96">
                        <div class="relative flex items-center">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant/70 pointer-events-none">person_search</span>
                            <input 
                                type="text" 
                                wire:model.live.debounce.300ms="nombreCliente" 
                                placeholder="Comensal (≥4 letras)..." 
                                autocomplete="off"
                                class="w-full h-9 rounded-xl border border-surface-container-high bg-surface-container-low pl-10 pr-8 text-xs font-medium text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                            />
                            @if(!empty(trim($nombreCliente)))
                                <button 
                                    type="button" 
                                    wire:click="$set('nombreCliente', ''); $wire.cerrarSugerencias()"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 hover:text-on-surface text-xs p-1 cursor-pointer"
                                    title="Limpiar"
                                >✕</button>
                            @endif
                        </div>

                        <!-- Dropdown flotante predictivo -->
                        @if($mostrarSugerencias && count($sugerenciasClientes) > 0)
                            <div class="absolute left-0 top-full mt-1.5 w-full min-w-[320px] max-h-64 overflow-y-auto rounded-2xl bg-surface-container-lowest border border-surface-container-high shadow-2xl z-50 p-1.5 divide-y divide-surface-container-high/40">
                                <div class="px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant bg-surface-container-low rounded-t-xl flex items-center justify-between">
                                    <span>Coincidencias ({{ count($sugerenciasClientes) }})</span>
                                    <span class="text-[9px] text-on-surface-variant/70">Click para vincular</span>
                                </div>
                                @foreach($sugerenciasClientes as $sug)
                                    <button 
                                        type="button"
                                        wire:click="seleccionarClientePredictivo({{ $sug['id'] }})"
                                        class="w-full text-left p-2.5 hover:bg-surface-container-high transition rounded-xl flex items-center justify-between gap-2.5 cursor-pointer group"
                                    >
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-on-surface group-hover:text-primary truncate">{{ $sug['nombre'] }}</p>
                                            <p class="text-[11px] text-on-surface-variant truncate">
                                                {{ $sug['telefono'] ?: 'Sin teléfono' }} 
                                                @if(!empty($sug['email'])) · {{ $sug['email'] }} @endif
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $sug['badge_class'] ?? '' }}">
                                                {{ $sug['badge_label'] ?? strtoupper($sug['tier']) }}
                                            </span>
                                            <span class="text-xs font-mono font-bold text-tertiary">
                                                {{ $sug['puntos'] }} pts
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @elseif(mb_strlen(trim($nombreCliente)) >= 4 && empty($sugerenciasClientes) && !$clienteId)
                            <div class="absolute left-0 top-full mt-1.5 w-full min-w-[280px] rounded-xl bg-surface-container-lowest border border-surface-container-high shadow-lg z-50 p-2.5 text-center text-xs text-on-surface-variant">
                                <span class="material-symbols-outlined text-amber-500 text-[18px] align-middle mr-1">person_add</span>
                                Nuevo: Se registrará como <span class="font-bold text-on-surface">Ocasional</span>.
                            </div>
                        @endif
                    </div>

                    <!-- Botón para registrar comensal -->
                    <button 
                        type="button"
                        wire:click="abrirModalHabeasData"
                        class="h-9 px-2.5 sm:px-3 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-bold flex items-center gap-1 shadow-sm transition-all cursor-pointer shrink-0 active:scale-95"
                        title="Registrar nuevo cliente con datos y consentimiento"
                    >
                        <span class="material-symbols-outlined text-[18px]">person_add</span>
                        <span class="hidden sm:inline">Nuevo</span>
                    </button>
                </div>
            @endif
        </div>

        <!-- Search input & View Switcher & Caja Indicator -->
        <div class="flex items-center gap-2 w-full lg:w-auto flex-wrap">
            <!-- Indicador de Caja / Turno -->
            <div class="shrink-0">
                @if($turnoActivo)
                    <a 
                        href="{{ route('caja') }}" 
                        wire:navigate 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-secondary-container/40 border border-secondary/30 text-on-secondary-container text-xs font-bold hover:bg-secondary-container/60 transition-all shadow-xs"
                        title="Turno de caja abierto - Clic para ir a control de caja"
                    >
                        <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                        <span class="font-extrabold truncate max-w-[130px]">{{ $turnoActivo->caja->nombre }}</span>
                        <span class="text-[10px] font-mono text-on-surface-variant font-bold">#{{ $turnoActivo->id }}</span>
                    </a>
                @else
                    @can('abrir', App\Models\TurnoCaja::class)
                        <button 
                            type="button"
                            wire:click="abrirModalAperturaPosManual"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-error-container/60 border border-error/40 text-error text-xs font-black hover:bg-error-container active:scale-95 transition-all cursor-pointer shadow-xs"
                            title="Caja cerrada. Haz clic para ingresar la base y abrir turno"
                        >
                            <span class="material-symbols-outlined text-[16px]">lock_open</span>
                            <span>Caja Cerrada · Abrir</span>
                        </button>
                    @else
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-container-high border border-surface-container-highest text-on-surface-variant text-xs font-bold">
                            <span class="material-symbols-outlined text-[16px] text-error">lock</span>
                            <span>Caja Cerrada</span>
                        </div>
                    @endcan
                @endif
            </div>

            <div class="relative flex-1 min-w-[180px] sm:w-56 lg:w-60">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                <input 
                    type="text" 
                    wire:model.live.debounce.250ms="busqueda" 
                    placeholder="Buscar producto..." 
                    class="w-full h-9 rounded-xl border border-surface-container-high bg-surface-container-low pl-9.5 pr-3 text-xs font-medium text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-0"
                />
            </div>

            {{-- rol intencional, no permiso: selector de vistas exclusivo de la comandera del mesero --}}
            @if(Auth::user()?->role?->slug === 'mesero')
                <!-- Selector de Tres Vistas Táctiles Exclusivo Mesero (Tablet, PC, Móvil) -->
                <div class="flex items-center gap-1 bg-surface-container-low p-1 rounded-xl border border-surface-container-high shrink-0" role="group" aria-label="Selector de vistas">
                    <!-- Botón PC -->
                    <button 
                        type="button" 
                        wire:click="cambiarVista('pc')"
                        id="btnVistaPc"
                        class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-black transition-all cursor-pointer {{ $vistaMesero === 'pc' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}"
                        title="Vista de Terminal PC / Escritorio (Mostrador)"
                    >
                        <span class="material-symbols-outlined text-[16px]">desktop_windows</span>
                        <span class="hidden sm:inline">PC</span>
                    </button>
                    <!-- Botón Tablet -->
                    <button 
                        type="button" 
                        wire:click="cambiarVista('tablet')"
                        id="btnVistaTablet"
                        class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-black transition-all cursor-pointer {{ $vistaMesero === 'tablet' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}"
                        title="Vista de Tablet táctil (iPad / Salón 50/50)"
                    >
                        <span class="material-symbols-outlined text-[16px]">tablet</span>
                        <span class="hidden sm:inline">Tablet</span>
                    </button>
                    <!-- Botón Móvil -->
                    <button 
                        type="button" 
                        wire:click="cambiarVista('movil')"
                        id="btnVistaMovil"
                        class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-black transition-all cursor-pointer {{ $vistaMesero === 'movil' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}"
                        title="Vista Móvil de Bolsillo (Comandera)"
                    >
                        <span class="material-symbols-outlined text-[16px]">smartphone</span>
                        <span class="hidden sm:inline">Móvil</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Main POS Layout Adaptable: PC (8/4), Tablet (7/5) o Móvil (12 cols) -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <!-- Catalogue Column -->
        <div class="space-y-4 {{ $vistaMesero === 'movil' ? 'col-span-12' : ($vistaMesero === 'tablet' ? 'lg:col-span-7 col-span-12' : 'lg:col-span-8 col-span-12') }}">
            <!-- Barra de Navegación de Categorías (Aura Gastro Expressive OS) -->
            <div 
                x-data="{
                    scrollLeft() {
                        $refs.catNavTrack.scrollBy({ left: -260, behavior: 'smooth' });
                    },
                    scrollRight() {
                        $refs.catNavTrack.scrollBy({ left: 260, behavior: 'smooth' });
                    }
                }"
                class="bg-surface-container-lowest p-2 rounded-2xl border border-surface-container-highest shadow-sm flex items-center gap-2"
            >
                <!-- Botón Desplazamiento Izquierda -->
                <button 
                    type="button" 
                    @click="scrollLeft()"
                    id="btnCatNavLeft"
                    class="h-9 w-9 shrink-0 flex items-center justify-center rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all active:scale-95 shadow-xs cursor-pointer"
                    title="Desplazar categorías hacia la izquierda"
                    aria-label="Categorías anteriores"
                >
                    <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                </button>

                <!-- Pistas de Categorías con Scroll Suave Táctil -->
                <div 
                    x-ref="catNavTrack"
                    class="flex-1 flex items-center gap-2 overflow-x-auto scroll-smooth py-1 px-1 scrollbar-none"
                >
                    <!-- Opción Todo el Menú -->
                    <button 
                        wire:click="$set('categoriaSeleccionada', null)"
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all border cursor-pointer active:scale-95 {{ is_null($categoriaSeleccionada) ? 'bg-primary text-on-primary border-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant border-surface-container-high hover:bg-surface-container hover:text-on-surface' }}"
                    >
                        <span class="material-symbols-outlined text-[16px]">restaurant_menu</span>
                        <span>Todo el Menú</span>
                    </button>

                    <!-- Botones por Categoría -->
                    @foreach($categorias as $cat)
                        @php $catColor = $cat->color ?? '#e11d48'; @endphp
                        <button 
                            wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                            type="button"
                            class="flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all border cursor-pointer active:scale-95 {{ $categoriaSeleccionada === $cat->id ? 'text-white shadow-sm' : 'bg-surface-container-low text-on-surface-variant border-surface-container-high hover:bg-surface-container hover:text-on-surface' }}"
                            @style(['background-color: ' . $catColor => $categoriaSeleccionada === $cat->id, 'border-color: ' . $catColor => $categoriaSeleccionada === $cat->id])
                        >
                            @if($categoriaSeleccionada !== $cat->id)
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-xs" @style(['background-color: ' . $catColor])></span>
                            @endif
                            @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                <span class="material-symbols-outlined text-[18px]">{{ $cat->icono }}</span>
                            @else
                                <span class="text-sm leading-none">{{ $cat->icono ?: '🍽️' }}</span>
                            @endif
                            <span>{{ $cat->nombre }}</span>
                            <span class="ml-0.5 rounded-full px-1.5 py-0.2 text-[10px] font-mono {{ $categoriaSeleccionada === $cat->id ? 'bg-white/20 text-white' : 'bg-surface-container-high text-on-surface-variant' }}">
                                {{ $cat->productos_count ?? 0 }}
                            </span>
                        </button>
                    @endforeach
                </div>

                <!-- Botón Desplazamiento Derecha -->
                <button 
                    type="button" 
                    @click="scrollRight()"
                    id="btnCatNavRight"
                    class="h-9 w-9 shrink-0 flex items-center justify-center rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all active:scale-95 shadow-xs cursor-pointer"
                    title="Desplazar categorías hacia la derecha"
                    aria-label="Siguientes categorías"
                >
                    <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                </button>

                <!-- Botón Crear Producto: Para Administrador y Gerente (Invisible para el resto de usuarios) -->
                {{-- rol intencional, no permiso: crear producto es gestión de carta, sin ability en el catálogo --}}
                @if(in_array(Auth::user()?->role?->slug, ['admin', 'gerente'], true))
                    <div class="shrink-0 border-l border-surface-container-highest pl-2">
                        <a 
                            href="{{ route('menu') }}"
                            wire:navigate
                            id="btnPosCrearProductoAdmin"
                            class="flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-black text-on-primary bg-primary hover:bg-primary-container border border-primary shadow-sm transition-all active:scale-95"
                            title="Administrador y Gerente: Crear o personalizar nuevo producto en la carta"
                        >
                            <span class="material-symbols-outlined text-[16px]">add_circle</span>
                            <span class="hidden sm:inline">+ Nuevo Producto</span>
                            <span class="sm:hidden">+</span>
                        </a>
                    </div>
                @endif
            </div>

            <!-- Product Grid Adaptable por Tipo de Vista -->
            <div class="grid gap-3 {{ $vistaMesero === 'movil' ? 'grid-cols-1 sm:grid-cols-2' : ($vistaMesero === 'tablet' ? 'grid-cols-2 lg:grid-cols-3' : 'grid-cols-2 sm:grid-cols-3 xl:grid-cols-4') }}">
                @forelse($productos as $prod)
                    @php $prodColor = $prod->categoria?->color ?? '#e11d48'; @endphp
                    <button 
                        wire:click="agregarProducto({{ $prod->id }})"
                        class="group relative flex flex-col justify-between rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-3.5 text-left shadow-sm transition-all duration-150 hover:border-primary hover:shadow-md active:scale-95 overflow-hidden"
                        @style(['border-top: 4px solid ' . $prodColor])
                    >
                        <div>
                            <!-- Header: Icon & Kitchen Area Chip -->
                            <div class="flex items-start justify-between gap-1">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs"
                                     @style(['background-color: ' . $prodColor . '1a'])>
                                    @if(preg_match('/^[a-z0-9_]+$/', $prod->categoria?->icono ?? ''))
                                        <span class="material-symbols-outlined text-[22px]" @style(['color: ' . $prodColor])>{{ $prod->categoria->icono }}</span>
                                    @else
                                        <span class="text-xl leading-none">{{ $prod->categoria?->icono ?: '🍽️' }}</span>
                                    @endif
                                </div>
                                @php
                                    $desktopAreaLabel = match(strtolower($prod->area_cocina ?? 'caliente')) {
                                        'sushi', 'fria', 'cocina_fria' => 'Cocina Fría',
                                        'caliente', 'calientes', 'cocina' => 'Caliente',
                                        'barra', 'bebidas' => 'Barra',
                                        'postres' => 'Postres',
                                        default => ucfirst($prod->area_cocina),
                                    };
                                @endphp
                                <span class="rounded-md border border-surface-container-high bg-surface-container-low px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">
                                    {{ $desktopAreaLabel }}
                                </span>
                            </div>

                            <!-- Product Name & Description -->
                            <h4 class="mt-2 text-xs font-extrabold text-on-surface group-hover:text-primary transition-colors line-clamp-1">
                                {{ $prod->nombre }}
                            </h4>
                            <p class="mt-0.5 text-[10px] text-on-surface-variant line-clamp-2 leading-tight">
                                {{ $prod->descripcion ?: 'Especialidad de la casa elaborada al momento.' }}
                            </p>
                        </div>

                        <!-- Price & Add Button Footer -->
                        <div class="mt-3.5 flex items-center justify-between border-t border-surface-container pt-2">
                            <span class="text-xs font-black text-on-surface tracking-tight">
                                ${{ number_format((float) $prod->precio, 0, ',', '.') }}
                            </span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl text-white group-hover:opacity-90 transition-colors font-bold text-base shadow-sm"
                                  @style(['background-color: ' . $prodColor])>
                                +
                            </span>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-surface-container-highest p-12 text-center text-on-surface-variant flex flex-col items-center justify-center gap-3">
                        <span class="material-symbols-outlined text-[36px] text-on-surface-variant/40">ramen_dining</span>
                        <p class="text-xs font-semibold">No hay productos o servicios en esta categoría.</p>
                        {{-- rol intencional, no permiso: crear producto es gestión de carta, sin ability en el catálogo --}}
                        @if(in_array(Auth::user()?->role?->slug, ['admin', 'gerente'], true))
                            <a href="{{ route('menu') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-bold shadow hover:bg-primary/90 transition">
                                <span class="material-symbols-outlined text-[16px]">add_circle</span>
                                <span>Crear Producto para esta Categoría</span>
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Order Cart Terminal Column (Sticky Viewport en PC/Tablet, slide-up en Móvil) -->
        <div class="rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-sm flex flex-col h-[calc(100vh-6.5rem)] sticky top-20 {{ $vistaMesero === 'movil' ? 'hidden' : ($vistaMesero === 'tablet' ? 'lg:col-span-5 col-span-12' : 'lg:col-span-4 col-span-12') }}">
            <!-- Cart Header (Fijo al tope) -->
            <div class="shrink-0 flex items-center justify-between border-b border-surface-container-high pb-3">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[20px] text-primary">receipt_long</span>
                        <h3 class="text-sm font-extrabold text-on-surface">Comanda en Curso</h3>
                    </div>
                    <p class="text-[11px] text-on-surface-variant mt-0.5">
                        @if($tipo === 'mesa')
                            Mesa {{ $mesaId ? $mesas->find($mesaId)?->numero : 'Sin asignar' }} · Salón
                        @else
                            {{ ucfirst($tipo) }} {{ $nombreCliente ? "• $nombreCliente" : '' }}
                        @endif
                    </p>
                </div>
                @if(count($carrito) > 0)
                    <button 
                        wire:click="limpiarCarrito" 
                        class="text-[11px] font-bold text-error hover:underline cursor-pointer"
                    >
                        Vaciar Carrito
                    </button>
                @endif
            </div>

            @if($pedidoQrPendiente)
                <div class="shrink-0 mt-2.5 rounded-2xl border border-amber-300 bg-amber-50 p-2.5 flex items-center justify-between gap-2 shadow-xs animate-pulse">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="material-symbols-outlined text-amber-700 text-[20px] shrink-0">notifications_active</span>
                        <div class="min-w-0">
                            <span class="block text-[10px] font-black uppercase text-amber-900 leading-tight">Pedido QR por Asignar</span>
                            <span class="text-[11px] font-bold text-amber-800 truncate block">{{ $pedidoQrPendiente->nombre_cliente ?? 'Comensal' }} ({{ $pedidoQrPendiente->items->count() }} platos)</span>
                        </div>
                    </div>
                    <button 
                        wire:click="atenderPedidoQrActual"
                        type="button"
                        class="px-2.5 py-1.5 rounded-xl bg-primary text-on-primary text-[11px] font-black shadow-sm hover:bg-primary/90 active:scale-95 transition cursor-pointer shrink-0"
                    >
                        Tomar Mesa
                    </button>
                </div>
            @endif

            <!-- Cart Items List: Ocupa todo el espacio dinámico (flex-1 min-h-0) sin huecos en blanco -->
            <div class="flex-1 min-h-0 overflow-y-auto mt-3 pr-1 space-y-2">
                @if($modoNuevaAdicion)
                    <div class="rounded-2xl border border-primary/30 bg-primary/10 p-2.5 flex items-center justify-between text-xs animate-fade-in">
                        <div class="flex items-center gap-1.5 text-primary font-black">
                            <span class="material-symbols-outlined text-[16px]">add_circle</span>
                            <span>Nuevo Pedido / Adición</span>
                        </div>
                        <button 
                            wire:click="cancelarModoAdicion" 
                            type="button"
                            class="text-[10px] font-bold text-primary hover:underline cursor-pointer"
                        >
                            Ver cuenta total
                        </button>
                    </div>
                @endif
                @forelse($carrito as $pId => $item)
                    <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-on-surface truncate max-w-[170px]">
                                {{ $item['nombre'] }}
                            </span>
                            <span class="text-xs font-mono font-extrabold text-primary">
                                ${{ number_format($item['precio'] * $item['cantidad'], 0, ',', '.') }}
                            </span>
                        </div>

                        <!-- Row Controls: Stepper [- Qty +] and Prep Notes -->
                        <div class="mt-2 flex items-center justify-between gap-1.5">
                            <div class="flex items-center gap-1">
                                <button 
                                    wire:click="decrementarCantidad({{ $pId }})"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-surface-container font-bold text-on-surface shadow-sm hover:bg-surface-container-high active:scale-95 cursor-pointer"
                                >
                                    -
                                </button>
                                <span class="w-6 text-center text-xs font-mono font-bold text-on-surface">
                                    {{ $item['cantidad'] }}
                                </span>
                                <button 
                                    wire:click="incrementarCantidad({{ $pId }})"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-surface-container font-bold text-on-surface shadow-sm hover:bg-surface-container-high active:scale-95 cursor-pointer"
                                >
                                    +
                                </button>
                            </div>

                            <input 
                                type="text" 
                                wire:model.lazy="carrito.{{ $pId }}.notas" 
                                placeholder="Nota al chef..." 
                                class="h-7 w-32 rounded-lg border border-surface-container-high bg-surface-container-lowest px-2 text-[10px] text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-0"
                            />

                            <button 
                                wire:click="eliminarItem({{ $pId }})" 
                                class="flex h-7 w-7 items-center justify-center rounded-lg text-on-surface-variant hover:text-error transition-colors cursor-pointer"
                            >
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="py-14 text-center text-xs text-on-surface-variant">
                        <span class="material-symbols-outlined text-[36px] text-outline-variant block mb-1">local_dining</span>
                        El carrito está vacío.<br />Selecciona platos del menú para armar la comanda.
                    </div>
                @endforelse
            </div>

            <!-- Sticky Cart Summary & Dual Execution Triggers (Fijo al pie del panel) -->
            <div class="shrink-0 mt-3 pt-3 border-t border-surface-container-high space-y-2.5">
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-on-surface-variant">
                        <span>Subtotal Comanda:</span>
                        <span class="font-mono font-bold text-on-surface">${{ number_format($this->subtotal, 0, ',', '.') }}</span>
                    </div>

                    @if($tipo === 'delivery')
                        <div class="flex justify-between text-on-surface-variant">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px] text-primary">two_wheeler</span>
                                Costo Envío Delivery:
                            </span>
                            <span class="font-mono font-bold text-on-surface">${{ number_format($costoEnvio, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    @if($descuento > 0)
                        <div class="flex justify-between text-secondary">
                            <span>Descuento aplicado:</span>
                            <span class="font-mono font-bold">-${{ number_format($descuento, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    @if($descuentoPuntos > 0)
                        <div class="flex justify-between text-tertiary">
                            <span>Descuento Fidelización ({{ $puntosCanjeados }} pts):</span>
                            <span class="font-mono font-bold">-${{ number_format($descuentoPuntos, 0, ',', '.') }}</span>
                        </div>
                    @elseif($clienteId && $puntosDisponibles > 0 && count($carrito) > 0)
                        @php $ptsCanje = min($puntosDisponibles, (int)floor($this->subtotal / 10)); @endphp
                        @if($ptsCanje > 0)
                            <button
                                wire:click="canjearPuntos({{ $ptsCanje }})"
                                class="w-full py-1.5 px-3 rounded-xl bg-tertiary-fixed text-on-tertiary-fixed text-[11px] font-extrabold flex items-center justify-between border border-tertiary/25 hover:bg-tertiary/20 transition-all active:scale-95 cursor-pointer"
                            >
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px] text-tertiary">loyalty</span>
                                    Canjear {{ $ptsCanje }} puntos de {{ $puntosDisponibles }}
                                </span>
                                <span>-${{ number_format($ptsCanje * 10, 0, ',', '.') }}</span>
                            </button>
                        @endif
                    @endif

                    <div class="flex justify-between text-base font-extrabold text-on-surface pt-1 border-t border-dashed border-surface-container-high">
                        <span>Total Neto:</span>
                        <span class="text-primary font-mono font-black text-lg">${{ number_format($this->total, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Dual Tactical Touch Buttons -->
                <div class="grid grid-cols-2 gap-2 pt-1">
                    @if($this->comandaDespachadaPorCocina() && $this->cantidadNuevosItemsParaCocina() === 0)
                        <button 
                            wire:click="iniciarNuevoPedido"
                            type="button"
                            class="flex h-12 items-center justify-center gap-1.5 rounded-xl border border-primary/40 bg-surface-container text-xs font-extrabold text-primary shadow-sm hover:bg-surface-container-high transition-all active:scale-95 cursor-pointer"
                            title="Comanda anterior despachada. Haz clic para iniciar un nuevo pedido o adición para esta mesa."
                        >
                            <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                            <span>+ Nuevo Pedido</span>
                        </button>
                    @else
                        <button 
                            wire:click="enviarACocina"
                            type="button"
                            @disabled(empty($carrito) || ($tipo === 'mesa' && !$mesaId) || ($tipo === 'mesa' && $this->comandaYaEnviadaACocina()))
                            class="flex h-12 items-center justify-center gap-1.5 rounded-xl border text-xs font-extrabold shadow-sm disabled:opacity-40 transition-all active:scale-95 cursor-pointer {{ $this->comandaYaEnviadaACocina() ? 'bg-surface-container/50 border-surface-container-high text-on-surface-variant cursor-not-allowed' : 'bg-surface-container border-primary/40 text-primary hover:bg-surface-container-high' }}"
                            title="{{ $this->comandaYaEnviadaACocina() ? 'Comanda ya enviada a cocina. Agrega nuevos productos para reactivar el envío.' : 'Enviar comanda a cocina' }}"
                        >
                            <span class="material-symbols-outlined text-[18px]">{{ $this->comandaYaEnviadaACocina() ? 'check_circle' : 'skillet' }}</span>
                            <span>{{ $this->comandaYaEnviadaACocina() ? '✓ En Cocina' : ($this->cantidadNuevosItemsParaCocina() > 0 && $this->obtenerPedidoActivoMesa() ? 'Enviar +'.$this->cantidadNuevosItemsParaCocina().' a Cocina' : 'Enviar Cocina') }}</span>
                        </button>
                    @endif
                    <button 
                        wire:click="abrirModalCobro"
                        type="button"
                        @disabled((empty($carrito) && !$this->obtenerPedidoActivoMesa()) || $this->comandaActivaBloqueaCobro())
                        class="flex h-12 items-center justify-center gap-1.5 rounded-xl text-xs font-extrabold shadow-md disabled:opacity-40 transition-all active:scale-95 cursor-pointer {{ $this->comandaActivaBloqueaCobro() ? 'bg-amber-500/20 text-amber-900 border border-amber-500/40 cursor-not-allowed' : ($this->comandaListaParaCobrar() ? 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-emerald-500/30' : 'bg-primary text-on-primary hover:bg-primary-container') }}"
                        title="{{ $this->comandaActivaBloqueaCobro() ? 'Comanda en preparación en cocina. Solo se puede cobrar cuando cocina termine.' : 'Cobrar Pedido' }}"
                    >
                        <span class="material-symbols-outlined text-[18px]">{{ $this->comandaActivaBloqueaCobro() ? 'hourglass_top' : ($this->comandaListaParaCobrar() ? 'check_circle' : 'payments') }}</span>
                        <span>{{ $this->comandaActivaBloqueaCobro() ? 'En Prep. Cocina' : ($this->comandaListaParaCobrar() ? '✓ Comanda Lista: Cobrar' : 'Cobrar Pedido') }}</span>
                    </button>
                </div>

                @if($this->comandaActivaBloqueaCobro())
                    <div class="mt-1.5 flex items-center justify-center gap-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 px-3 py-1.5 text-[11px] font-bold text-amber-800 animate-pulse">
                        <span class="material-symbols-outlined text-[15px] text-amber-600">hourglass_top</span>
                        <span>En preparación en cocina · Bloqueado hasta que cocina termine</span>
                    </div>
                @elseif($this->comandaListaParaCobrar())
                    <div class="mt-1.5 flex items-center justify-center gap-1.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 px-3 py-1.5 text-[11px] font-bold text-emerald-800">
                        <span class="material-symbols-outlined text-[15px] text-emerald-600">notifications_active</span>
                        <span>¡Comanda despachada / lista en cocina! Habilitado para servir y cobrar</span>
                    </div>
                @endif
                {{-- rol intencional, no permiso: hint contextual del flujo mesero --}}
                @if(Auth::user()?->role?->slug === 'mesero')
                    <p class="text-[10px] text-center text-on-surface-variant font-medium pt-1">
                        <span class="font-bold text-primary">Modo Mesero:</span> Envía comandas a cocina y cobra al ser servidas
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

    <!-- Modal de Apertura Rápida de Turno de Caja desde POS -->
    @if($mostrarModalAperturaPos)
        <div x-data @keydown.escape.window="$wire.set('mostrarModalAperturaPos', false)" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-apertura-pos-title" class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">lock_open</span>
                        </div>
                        <div>
                            <h3 id="modal-apertura-pos-title" class="text-base font-extrabold text-on-surface">Apertura Rápida de Caja</h3>
                            <p class="text-[11px] text-on-surface-variant">Ingresa la base inicial de efectivo para habilitar el cobro</p>
                        </div>
                    </div>
                    <button wire:click="$set('mostrarModalAperturaPos', false)" aria-label="Cerrar modal" class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-full text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Terminal de Caja:</label>
                        <select 
                            wire:model="cajaAperturaId" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                        >
                            @foreach($cajasDisponibles as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                            @endforeach
                        </select>
                        @error('cajaAperturaId') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Fondo Inicial / Base de Efectivo en Gaveta:</label>
                        <div class="relative mt-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-on-surface-variant">$</span>
                            <input 
                                type="number" 
                                step="1000" 
                                wire:model="baseAperturaPos" 
                                class="w-full rounded-xl border border-surface-container-high bg-surface-container-low pl-7 pr-3 py-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                                placeholder="150000"
                            />
                        </div>
                        @error('baseAperturaPos') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        <div class="flex gap-1.5 mt-2">
                            <button type="button" wire:click="$set('baseAperturaPos', 100000)" class="px-2 py-1 rounded-lg bg-surface-container text-[11px] font-bold text-on-surface-variant hover:text-on-surface border border-surface-container-high cursor-pointer">$100k</button>
                            <button type="button" wire:click="$set('baseAperturaPos', 150000)" class="px-2 py-1 rounded-lg bg-surface-container text-[11px] font-bold text-on-surface-variant hover:text-on-surface border border-surface-container-high cursor-pointer">$150k</button>
                            <button type="button" wire:click="$set('baseAperturaPos', 200000)" class="px-2 py-1 rounded-lg bg-surface-container text-[11px] font-bold text-on-surface-variant hover:text-on-surface border border-surface-container-high cursor-pointer">$200k</button>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Notas de Apertura (Opcional):</label>
                        <input 
                            type="text" 
                            wire:model="notasAperturaPos" 
                            placeholder="Ej: Base de cambio entregada para apertura de turno"
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0"
                        />
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button 
                        wire:click="$set('mostrarModalAperturaPos', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button 
                        wire:click="abrirTurnoDesdePos" 
                        class="rounded-xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container active:scale-95 transition-all cursor-pointer"
                    >
                        ✓ Abrir Turno y Cobrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de Cobro Táctil (Stitch POS-02 Billing Console) -->
    @if($mostrarModalCobro)
        <div x-data @keydown.escape.window="$wire.set('mostrarModalCobro', false)" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-cobro-pos-title" class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">point_of_sale</span>
                        </div>
                        <h3 id="modal-cobro-pos-title" class="text-base font-extrabold text-on-surface">Terminal de Cobro</h3>
                    </div>
                    <button wire:click="$set('mostrarModalCobro', false)" aria-label="Cerrar modal de cobro" class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-full text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <!-- Propina del Servicio (Ley 1935 de 2018 - Voluntaria) -->
                    <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-on-surface flex items-center gap-1">
                                <span class="material-symbols-outlined text-primary text-[16px]">volunteer_activism</span>
                                Propina del Servicio (Voluntaria)
                            </span>
                            <span class="text-xs font-black text-primary font-mono">+ ${{ number_format($montoPropina, 0, ',', '.') }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button 
                                type="button"
                                wire:click="seleccionarPropina('cero')" 
                                class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition cursor-pointer {{ $tipoPropina === 'cero' ? 'border-primary bg-primary text-on-primary shadow-xs' : 'border-surface-container-high bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                            >
                                Sin Propina ($0)
                            </button>
                            <button 
                                type="button"
                                wire:click="seleccionarPropina('diez_porciento')" 
                                class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition cursor-pointer {{ $tipoPropina === 'diez_porciento' ? 'border-primary bg-primary text-on-primary shadow-xs' : 'border-surface-container-high bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                            >
                                10% (${{ number_format(round($this->total * 0.10), 0, ',', '.') }})
                            </button>
                            <button 
                                type="button"
                                wire:click="seleccionarPropina('personalizada')" 
                                class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition cursor-pointer {{ $tipoPropina === 'personalizada' ? 'border-primary bg-primary text-on-primary shadow-xs' : 'border-surface-container-high bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                            >
                                Valor Libre
                            </button>
                        </div>
                        @if($tipoPropina === 'personalizada')
                            <div class="pt-1 flex items-center gap-2">
                                <span class="text-xs text-on-surface-variant font-bold">$</span>
                                <input 
                                    type="number" 
                                    step="500" 
                                    min="0"
                                    wire:model.live.debounce.300ms="montoPropina" 
                                    placeholder="Monto voluntario comensal..."
                                    class="w-full rounded-xl border border-surface-container-high bg-surface-container px-3 py-1.5 text-xs font-bold font-mono text-on-surface focus:border-primary focus:ring-0"
                                />
                            </div>
                        @endif
                    </div>

                    <!-- Total to pay banner -->
                    <div class="rounded-2xl bg-surface-container-low border border-surface-container-high p-3.5 text-center">
                        <div class="flex items-center justify-between text-[11px] text-on-surface-variant font-semibold px-1">
                            <span>Consumo: ${{ number_format($this->total, 0, ',', '.') }}</span>
                            <span>Propina: ${{ number_format($montoPropina, 0, ',', '.') }}</span>
                        </div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant block mt-1">Total a Cancelar</span>
                        <p class="font-mono text-3xl font-black text-primary mt-0.5">${{ number_format($this->totalConPropina, 0, ',', '.') }}</p>
                    </div>

                    <!-- Payment Method Picker -->
                    <div>
                        <span class="text-xs font-bold text-on-surface-variant">Método de Pago:</span>
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            <button 
                                wire:click="$set('metodoPago', 'efectivo')"
                                class="flex items-center justify-center gap-1 rounded-xl p-2.5 text-xs font-extrabold transition border {{ $metodoPago === 'efectivo' ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">payments</span>
                                <span>Efectivo</span>
                            </button>
                            <button 
                                wire:click="$set('metodoPago', 'tarjeta')"
                                class="flex items-center justify-center gap-1 rounded-xl p-2.5 text-xs font-extrabold transition border {{ $metodoPago === 'tarjeta' ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">credit_card</span>
                                <span>Tarjeta</span>
                            </button>
                            <button 
                                wire:click="$set('metodoPago', 'mixto')"
                                class="flex items-center justify-center gap-1 rounded-xl p-2.5 text-xs font-extrabold transition border {{ $metodoPago === 'mixto' ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">balance</span>
                                <span>Mixto</span>
                            </button>
                        </div>
                    </div>

                    <!-- Mixed Payment Input -->
                    @if($metodoPago === 'mixto')
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Efectivo (pago mixto):</label>
                            <input
                                type="number"
                                step="1000"
                                min="0"
                                max="{{ (int) $this->total }}"
                                wire:model.live="montoEfectivoMixto"
                                class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                            />
                            <p class="mt-1 text-[10px] font-semibold text-on-surface-variant">
                                El resto (${{ number_format(max(0, (float) $this->total - (float) $this->montoEfectivoMixto), 0, ',', '.') }}) se registra como tarjeta.
                            </p>
                        </div>
                    @endif

                    <!-- Cash Input & Quick Bills -->
                    @if($metodoPago === 'efectivo')
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Monto Entregado:</label>
                            <input 
                                type="number" 
                                step="1000" 
                                wire:model.live="montoPagado" 
                                class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                            />

                            <!-- Quick denomination buttons -->
                            <div class="mt-2.5 grid grid-cols-4 gap-1.5">
                                <button wire:click="setMontoExacto" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                    Exacto
                                </button>
                                <button wire:click="sumarMonto(20000.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                    $20.000
                                </button>
                                <button wire:click="sumarMonto(50000.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                    $50.000
                                </button>
                                <button wire:click="sumarMonto(100000.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                    $100.000
                                </button>
                            </div>

                            <!-- Change calculation -->
                            <div class="mt-3 flex items-center justify-between rounded-xl bg-secondary-container/40 border border-secondary/30 p-3 text-xs font-bold text-on-secondary-container">
                                <span>Cambio a Devolver:</span>
                                <span class="font-mono text-xl font-black text-secondary">${{ number_format($this->cambio, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Modal Action Buttons -->
                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button 
                        wire:click="$set('mostrarModalCobro', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cancelar
                    </button>
                    <button 
                        wire:click="procesarCobro" 
                        @disabled($this->comandaActivaBloqueaCobro())
                        title="{{ $this->comandaActivaBloqueaCobro() ? 'La comanda sigue activa en cocina: solo se puede cobrar cuando todo fue servido o cancelado.' : 'Confirmar cobro' }}"
                        class="rounded-xl bg-secondary py-3 text-xs font-extrabold text-on-secondary shadow-md hover:bg-secondary-fixed-dim disabled:opacity-40"
                    >
                        ✓ Confirmar y Emitir
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Thermal Ticket 80mm Simulation Modal (Optimizado para Impresoras Locales USB / Driver Navegador) -->
    @if($mostrarTicket && $pedidoCompletado)
        <div x-data @keydown.escape.window="$wire.cerrarTicket()" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-ticket-title" class="print-ticket-termico w-full max-w-sm rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest font-mono text-xs max-h-[90vh] overflow-y-auto">
                <!-- Thermal Receipt Header -->
                <div class="text-center border-b border-dashed border-surface-container-high pb-4">
                    <p id="modal-ticket-title" class="text-base font-black tracking-tight text-primary">🍽️ RESTOMASTER 🍽️</p>
                    <p class="text-[11px] text-on-surface-variant">AURA GASTRO Enterprise POS</p>
                    <p class="text-[10px] text-on-surface-variant/70">El Poblado MDE-01 • Medellín</p>
                    <p class="text-[10px] text-on-surface-variant/70">NIT: 901.884.200-1 · Res. DIAN 18764022</p>
                </div>

                <!-- Ticket Details -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span>ORDEN:</span>
                        <span class="font-bold text-primary">{{ $pedidoCompletado->codigo }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>FECHA:</span>
                        <span>{{ now()->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>TIPO:</span>
                        <span class="font-bold uppercase text-secondary">{{ $pedidoCompletado->tipo }} {{ $pedidoCompletado->mesa ? "- Mesa {$pedidoCompletado->mesa->numero}" : '' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>CAJERO:</span>
                        <span>{{ Auth::user()->name }}</span>
                    </div>
                    @if($pedidoCompletado->mesero)
                        <div class="flex justify-between font-bold text-primary">
                            <span>MESERO:</span>
                            <span>{{ $pedidoCompletado->mesero->name }}</span>
                        </div>
                    @endif
                </div>

                <!-- Ticket Line Items -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1.5">
                    @foreach($pedidoCompletado->items as $it)
                        <div class="flex justify-between text-[11px]">
                            <span>{{ $it->cantidad }}x {{ $it->nombre_producto }}</span>
                            <span class="font-bold">${{ number_format($it->subtotal, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>

                <!-- Ticket Totals -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span>SUBTOTAL:</span>
                        <span>${{ number_format($pedidoCompletado->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($pedidoCompletado->descuento > 0)
                        <div class="flex justify-between text-secondary">
                            <span>DESCUENTO:</span>
                            <span>-${{ number_format($pedidoCompletado->descuento, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if((float) ($pedidoCompletado->propina ?? 0) > 0)
                        <div class="flex justify-between font-bold text-primary">
                            <span>PROPINA VOLUNTARIA:</span>
                            <span>+${{ number_format($pedidoCompletado->propina, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm font-black pt-1 text-on-surface">
                        <span>TOTAL A PAGAR:</span>
                        <span class="text-primary">${{ number_format((float) $pedidoCompletado->total + (float) ($pedidoCompletado->propina ?? 0), 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pt-1">
                        <span>PAGADO ({{ strtoupper($pedidoCompletado->metodo_pago) }}):</span>
                        <span>${{ number_format($pedidoCompletado->monto_pagado, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-secondary">
                        <span>CAMBIO:</span>
                        <span>${{ number_format($pedidoCompletado->cambio, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Ticket Footer Message -->
                <div class="pt-4 text-center text-[10px] text-on-surface-variant space-y-1">
                    <p class="font-bold text-on-surface">¡GRACIAS POR SU PREFERENCIA!</p>
                    <p>ありがとうございます (Arigatōgozaimashita)</p>
                    <p class="text-[9px]">Documento equivalente POS DIAN para control interno</p>
                </div>

                <!-- Close / Print buttons (Ocultos al imprimir en papel) -->
                <div class="no-print mt-5 grid grid-cols-2 gap-2">
                    <button 
                        onclick="window.print()" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[16px]">print</span>
                        <span>Imprimir</span>
                    </button>
                    <button 
                        wire:click="cerrarTicket" 
                        class="rounded-xl bg-primary py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container cursor-pointer"
                    >
                        ✓ Finalizar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Ley 1581 Habeas Data y Consentimiento -->
    @if($mostrarModalHabeasData)
        <div x-data @keydown.escape.window="$wire.set('mostrarModalHabeasData', false)" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-habeas-title" class="w-full max-w-lg rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">verified_user</span>
                        </div>
                        <div>
                            <h3 id="modal-habeas-title" class="text-base font-extrabold text-on-surface">Habeas Data & Datos de Contacto</h3>
                            <p class="text-[11px] text-on-surface-variant">Ley 1581 de 2012 · Fidelización y Facturación</p>
                        </div>
                    </div>
                    <button wire:click="$set('mostrarModalHabeasData', false)" aria-label="Cerrar modal" class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-full text-on-surface-variant hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <!-- Resumen Legal Informativo -->
                    <div class="rounded-2xl bg-surface-container-low border border-surface-container-high p-3.5 text-[11px] text-on-surface-variant space-y-1.5">
                        <p class="font-bold text-on-surface flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-primary">policy</span>
                            Autorización para Tratamiento de Datos Personales
                        </p>
                        <p class="leading-relaxed">
                            En cumplimiento de la Ley Estatutaria 1581 de 2012, el comensal autoriza el tratamiento de sus datos de contacto para la prestación del servicio gastronómico, emisión de facturas electrónicas, acumulación de puntos de fidelidad y notificaciones vía WhatsApp o correo electrónico.
                        </p>
                    </div>

                    <!-- Campos de Contacto -->
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Nombre Completo del Comensal *:</label>
                            <input 
                                type="text" 
                                wire:model="habeasNombre" 
                                placeholder="Ej: Valentina Gómez" 
                                class="w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-2 text-xs font-medium text-on-surface focus:border-primary focus:ring-0"
                            />
                            @error('habeasNombre') <span class="text-error text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Teléfono Móvil (WhatsApp / Pedidos):</label>
                            <input 
                                type="tel" 
                                wire:model="habeasTelefono" 
                                placeholder="Ej: 3001234567" 
                                class="w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-2 text-xs font-medium text-on-surface focus:border-primary focus:ring-0"
                            />
                            @error('habeasTelefono') <span class="text-error text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Correo Electrónico (Facturación & Promos):</label>
                            <input 
                                type="email" 
                                wire:model="habeasEmail" 
                                placeholder="comensal@ejemplo.com" 
                                class="w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-2 text-xs font-medium text-on-surface focus:border-primary focus:ring-0"
                            />
                            @error('habeasEmail') <span class="text-error text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Dirección para Domicilios (Opcional):</label>
                            <input 
                                type="text" 
                                wire:model="habeasDireccion" 
                                placeholder="Calle 123 #45-67, Apto 101" 
                                class="w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-2 text-xs font-medium text-on-surface focus:border-primary focus:ring-0"
                            />
                            @error('habeasDireccion') <span class="text-error text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Checkboxes de Consentimiento -->
                    <div class="space-y-2.5 pt-2 border-t border-surface-container-high">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input 
                                type="checkbox" 
                                wire:model="habeasAcepta" 
                                class="mt-0.5 rounded border-outline-variant text-primary focus:ring-primary h-4 w-4"
                            />
                            <span class="text-xs font-bold text-on-surface leading-tight">
                                Acepto expresamente los términos y autorizo el tratamiento de mis datos personales (Habeas Data).
                            </span>
                        </label>
                        @error('habeasAcepta') <span class="text-error text-[11px] block">{{ $message }}</span> @enderror

                        <label class="flex items-center gap-2.5 cursor-pointer pl-6">
                            <input 
                                type="checkbox" 
                                wire:model="habeasWhatsapp" 
                                class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4"
                            />
                            <span class="text-xs text-on-surface-variant">
                                Autorizo envío de promociones, estado de pedidos y cupones por WhatsApp.
                            </span>
                        </label>

                        <label class="flex items-center gap-2.5 cursor-pointer pl-6">
                            <input 
                                type="checkbox" 
                                wire:model="habeasEmailPromos" 
                                class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4"
                            />
                            <span class="text-xs text-on-surface-variant">
                                Autorizo envío de boletines de ofertas y facturación por correo electrónico.
                            </span>
                        </label>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="pt-3 grid grid-cols-2 gap-2 border-t border-surface-container-high">
                        <button 
                            type="button" 
                            wire:click="$set('mostrarModalHabeasData', false)" 
                            class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="button" 
                            wire:click="guardarHabeasData" 
                            class="rounded-xl bg-primary py-2.5 text-xs font-black text-on-primary shadow-md hover:bg-primary-container cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <span class="material-symbols-outlined text-[16px]">save</span>
                            <span>Guardar Consentimiento</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
