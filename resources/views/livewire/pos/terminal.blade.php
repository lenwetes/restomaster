<?php

use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\PedidoService;
use Livewire\Volt\Component;

new class extends Component
{
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
    public float $costoEnvio = 8000.0;
    public ?int $categoriaSeleccionada = null;
    public string $busqueda = '';

    // Shopping cart
    public array $carrito = [];
    public float $descuento = 0.0;

    // Checkout modal and ticket
    public bool $mostrarModalCobro = false;
    public string $metodoPago = 'efectivo';
    public float $montoPagado = 0.0;
    public ?Pedido $pedidoCompletado = null;
    public bool $mostrarTicket = false;

    // Tres vistas ergonómicas para rol mesero: 'pc', 'tablet', 'movil'
    public string $vistaMesero = 'pc';
    public bool $mostrarComandaMovil = false;

    public function cambiarVista(string $vista): void
    {
        if (in_array($vista, ['pc', 'tablet', 'movil'])) {
            $this->vistaMesero = $vista;
        }
    }

    public function mount(): void
    {
        if (request()->has('vista') && in_array(request()->query('vista'), ['pc', 'tablet', 'movil'])) {
            $this->vistaMesero = request()->query('vista');
        }

        $mesaIdParam = request()->query('mesa_id');
        if ($mesaIdParam) {
            $this->mesaId = (int)$mesaIdParam;
            $this->tipo = 'mesa';

            // If table has an active order, load it
            $pedidoExistente = Pedido::where('mesa_id', $this->mesaId)->activos()->latest()->first();
            if ($pedidoExistente) {
                foreach ($pedidoExistente->items as $item) {
                    $this->carrito[$item->producto_id] = [
                        'producto_id' => $item->producto_id,
                        'nombre' => $item->nombre_producto,
                        'precio' => (float)$item->precio_unitario,
                        'cantidad' => (int)$item->cantidad,
                        'notas' => $item->notas ?? '',
                        'area_cocina' => $item->area_cocina,
                    ];
                }
            }
        }
    }

    public function updatedMesaId($value): void
    {
        $this->limpiarCarrito();
        if ($value) {
            $pedidoExistente = Pedido::where('mesa_id', (int)$value)->activos()->latest()->first();
            if ($pedidoExistente) {
                foreach ($pedidoExistente->items as $item) {
                    $this->carrito[$item->producto_id] = [
                        'producto_id' => $item->producto_id,
                        'nombre' => $item->nombre_producto,
                        'precio' => (float)$item->precio_unitario,
                        'cantidad' => (int)$item->cantidad,
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
                'precio' => (float)$producto->precio,
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

    public function getTotalProperty(): float
    {
        $envio = $this->tipo === 'delivery' ? $this->costoEnvio : 0.0;
        return max(0.0, $this->subtotal + $envio - $this->descuento - $this->descuentoPuntos);
    }

    public function getCambioProperty(): float
    {
        return max(0.0, $this->montoPagado - $this->total);
    }

    public function updatedDescuento($value): void
    {
        if ((float) $value > 0) {
            $this->authorize('aplicarDescuento', Pedido::class);
        }
    }

    public function enviarACocina(): void
    {
        $this->authorize('enviarCocina', Pedido::class);

        if ($this->descuento > 0) {
            $this->authorize('aplicarDescuento', Pedido::class);
        }

        if (empty($this->carrito)) {
            return;
        }

        $pedidoService = app(PedidoService::class);
        $costoEnvio = $this->tipo === 'delivery' ? $this->costoEnvio : 0.0;

        $pedido = $pedidoService->crearPedido([
            'tipo' => $this->tipo,
            'estado' => 'en_cocina',
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
        ], array_values($this->carrito), auth()->user());

        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $cliente = \App\Models\Cliente::find($this->clienteId);
            if ($cliente) {
                app(\App\Services\FidelizacionService::class)->canjearPuntos($cliente, $this->puntosCanjeados, $pedido);
            }
        }

        $this->limpiarCarrito();

        session()->flash('notificacion', "¡Comanda {$pedido->codigo} enviada a cocina con éxito!");

        if (auth()->user()?->role?->slug === 'mesero') {
            $this->mesaId = null;
            $this->redirect(route('pos'), navigate: true);
            return;
        }

        $this->redirect($this->tipo === 'delivery' ? route('delivery') : route('mesas'), navigate: true);
    }

    public function abrirModalCobro(): void
    {
        if (empty($this->carrito)) {
            return;
        }
        $this->montoPagado = $this->total;
        $this->mostrarModalCobro = true;
    }

    public function setMontoExacto(): void
    {
        $this->montoPagado = $this->total;
    }

    public function sumarMonto(float $cantidad): void
    {
        $this->montoPagado = $cantidad;
    }

    public function procesarCobro(): void
    {
        $this->authorize('cobrar', Pedido::class);

        if ($this->descuento > 0) {
            $this->authorize('aplicarDescuento', Pedido::class);
        }

        if ($this->montoPagado < $this->total) {
            return;
        }

        $pedidoService = app(PedidoService::class);
        $costoEnvio = $this->tipo === 'delivery' ? $this->costoEnvio : 0.0;

        $pedidoExistente = ($this->tipo === 'mesa' && $this->mesaId)
            ? Pedido::where('mesa_id', $this->mesaId)->activos()->latest()->first()
            : null;

        if ($pedidoExistente) {
            $pedido = $pedidoExistente;
        } else {
            $pedido = $pedidoService->crearPedido([
                'tipo' => $this->tipo,
                'estado' => 'creado',
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
            ], array_values($this->carrito), auth()->user());
        }

        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $cliente = \App\Models\Cliente::find($this->clienteId);
            if ($cliente) {
                app(\App\Services\FidelizacionService::class)->canjearPuntos($cliente, $this->puntosCanjeados, $pedido);
            }
        }

        $this->pedidoCompletado = $pedidoService->cobrarPedido($pedido, $this->metodoPago, $this->montoPagado);

        $this->mostrarModalCobro = false;
        $this->mostrarTicket = true;
        $this->limpiarCarrito();
    }

    public function cerrarTicket(): void
    {
        $this->mostrarTicket = false;
        $this->pedidoCompletado = null;

        if (auth()->user()?->role?->slug === 'mesero') {
            $this->mesaId = null;
            $this->limpiarCarrito();
            $this->redirect(route('pos'), navigate: true);
            return;
        }

        $this->redirect(route('mesas'), navigate: true);
    }

    public function atenderPedidoQrActual(): void
    {
        if (!$this->mesaId) {
            return;
        }

        $pedido = Pedido::where('mesa_id', $this->mesaId)
            ->where('canal_origen', 'qr_mesa')
            ->where('estado', 'solicitado_qr')
            ->whereNull('usuario_id')
            ->latest()
            ->first();

        if ($pedido) {
            try {
                $pedidoService = app(PedidoService::class);
                $pedido = $pedidoService->asignarMeseroAPedidoQr($pedido->id, auth()->user());
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

        if (!empty($this->busqueda)) {
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
        }
        $clientesDisponibles = $clientesQuery->orderByDesc('puntos_fidelidad')->limit(50)->get();

        return [
            'categorias' => Categoria::where('activo', true)
                ->withCount(['productos' => fn ($q) => $q->where('activo', true)])
                ->orderBy('orden')
                ->get(),
            'productos' => $query->get(),
            'mesas' => Mesa::orderBy('numero')->get(),
            'clientesDisponibles' => $clientesDisponibles,
            'pedidoQrPendiente' => $pedidoQrPendiente,
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
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary/10 text-primary text-[10px]">🍣</span>
                        <span class="font-black text-on-surface">SushiXpress Pocket</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                        <span class="text-[10px] text-on-surface-variant font-mono">En Línea</span>
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
                                <p class="text-xs font-black text-on-surface truncate leading-tight">{{ auth()->user()->name }}</p>
                                <p class="text-[9px] text-primary font-bold uppercase tracking-wider">Comandera de Bolsillo</p>
                            </div>
                        </div>

                        @if(auth()->user()?->role?->slug === 'mesero')
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
                                        <option value="{{ $m->id }}">
                                            Mesa {{ $m->numero }} (Zona {{ $m->zona }} - {{ ucfirst($m->estado) }})
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
                                <button 
                                    type="button" 
                                    wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                                    class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-black transition-all border cursor-pointer active:scale-95 {{ $categoriaSeleccionada === $cat->id ? 'bg-primary text-on-primary border-primary shadow-xs' : 'bg-surface-container-low text-on-surface-variant border-surface-container-high hover:bg-surface-container' }}"
                                >
                                    <span>{{ $cat->icono }}</span>
                                    <span>{{ $cat->nombre }}</span>
                                    <span class="rounded-full px-1 text-[9px] font-mono {{ $categoriaSeleccionada === $cat->id ? 'bg-white/20 text-white' : 'bg-surface-container text-on-surface-variant' }}">
                                        {{ $cat->productos->where('activo', true)->count() }}
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
                                    <span class="text-xs">{{ $catActiva->icono }}</span>
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
                        <div class="rounded-2xl border bg-surface-container-lowest p-3 flex items-center justify-between gap-3 shadow-2xs hover:shadow-xs transition-all {{ isset($carrito[$prod->id]) ? 'border-primary/50 bg-primary/5 ring-1 ring-primary/20' : 'border-surface-container-highest' }}">
                            <!-- Visual & Detalles del Plato -->
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-12 h-12 rounded-xl bg-surface-container flex items-center justify-center text-2xl shrink-0 shadow-2xs">
                                    {{ $prod->categoria?->icono ?? '🍣' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <h4 class="text-xs font-black text-on-surface truncate">{{ $prod->nombre }}</h4>
                                        <span class="rounded px-1 py-0.2 text-[8px] font-bold uppercase tracking-wider bg-surface-container text-on-surface-variant shrink-0">
                                            {{ $prod->area_cocina }}
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
                                            <span class="text-2xl mb-1 block">{{ $cat->icono }}</span>
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
                                    <button 
                                        wire:click="enviarACocina"
                                        @disabled(empty($carrito) || ($tipo === 'mesa' && !$mesaId))
                                        class="flex h-11 items-center justify-center gap-1.5 rounded-xl bg-surface-container border border-surface-container-high text-xs font-black text-on-surface shadow-sm hover:bg-surface-container-high disabled:opacity-40 cursor-pointer active:scale-95"
                                    >
                                        <span class="material-symbols-outlined text-[18px] text-primary">skillet</span>
                                        <span>Enviar Cocina</span>
                                    </button>
                                    <button 
                                        wire:click="abrirModalCobro"
                                        @disabled(empty($carrito))
                                        class="flex h-11 items-center justify-center gap-1.5 rounded-xl bg-primary text-xs font-black text-on-primary shadow-md hover:bg-primary-container disabled:opacity-40 cursor-pointer active:scale-95"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">payments</span>
                                        <span>Cobrar Pedido</span>
                                    </button>
                                </div>
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
            <div class="flex flex-col gap-3 rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-3.5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
                <!-- Order Mode Toggle Pills -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-on-surface-variant flex items-center gap-1">
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
                @if(auth()->user()?->role?->slug !== 'mesero')
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
                <label for="mesaId" class="text-xs font-bold text-on-surface-variant flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-secondary">pin</span>
                    Mesa:
                </label>
                <select 
                    wire:model.live="mesaId" 
                    id="mesaId" 
                    class="rounded-xl border bg-surface-container-low px-3 py-1.5 text-xs font-bold text-on-surface focus:border-primary focus:ring-0 {{ !$mesaId && auth()->user()?->role?->slug === 'mesero' ? 'border-primary/60 ring-2 ring-primary/20' : 'border-surface-container-high' }}"
                >
                    <option value="">Seleccionar mesa del salón...</option>
                    @foreach($mesas as $m)
                        <option value="{{ $m->id }}">
                            Mesa {{ $m->numero }} (Zona {{ $m->zona }} - {{ $m->estado }})
                        </option>
                    @endforeach
                </select>
                @if(!$mesaId && auth()->user()?->role?->slug === 'mesero')
                    <span class="text-[11px] text-primary font-bold animate-pulse hidden sm:inline">← Elige una mesa</span>
                @endif
            </div>
        @else
            <div class="flex items-center gap-2 flex-wrap">
                @if($clienteId)
                    @php $cli = \App\Models\Cliente::with('direcciones')->find($clienteId); @endphp
                    <div class="inline-flex items-center gap-2 rounded-xl bg-surface-container-low border border-primary/40 px-3 py-1.5 text-xs">
                        <span class="material-symbols-outlined text-[16px] text-primary">stars</span>
                        <span class="font-extrabold text-on-surface">{{ $cli->nombre }}</span>
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
                        <button wire:click="desvincularCliente" class="text-error hover:text-error/80 text-[11px] font-bold ml-1" title="Desvincular">✕</button>
                    </div>
                @else
                    <select wire:change="seleccionarCliente($event.target.value)" class="rounded-xl border border-surface-container-high bg-surface-container-low px-3 py-1.5 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                        <option value="">Vincular Comensal VIP...</option>
                        @foreach($clientesDisponibles as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->telefono }} · {{ $c->puntos_fidelidad }} pts)</option>
                        @endforeach
                    </select>
                    <input 
                        type="text" 
                        wire:model.live="nombreCliente" 
                        placeholder="O nombre comensal manual..." 
                        class="rounded-xl border border-surface-container-high bg-surface-container-low px-3.5 py-1.5 text-xs font-medium text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-0 w-48"
                    />
                @endif
            </div>
        @endif

        <!-- Search input & View Switcher -->
        <div class="flex items-center gap-2 w-full lg:w-auto">
            <div class="relative flex-1 lg:w-60">
                <span class="material-symbols-outlined absolute left-3 top-2 text-[18px] text-on-surface-variant">search</span>
                <input 
                    type="text" 
                    wire:model.live.debounce.250ms="busqueda" 
                    placeholder="Buscar producto..." 
                    class="w-full rounded-xl border border-surface-container-high bg-surface-container-low pl-9 pr-3 py-1.5 text-xs font-medium text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-0"
                />
            </div>

            @if(auth()->user()?->role?->slug === 'mesero')
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
                        <button 
                            wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                            type="button"
                            class="flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all border cursor-pointer active:scale-95 {{ $categoriaSeleccionada === $cat->id ? 'bg-primary text-on-primary border-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant border-surface-container-high hover:bg-surface-container hover:text-on-surface' }}"
                        >
                            <span class="text-sm">{{ $cat->icono }}</span>
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

                <!-- Botón Crear Producto: EXCLUSIVO para Administrador (Invisible para el resto de usuarios) -->
                @if(auth()->user()?->role?->slug === 'admin')
                    <div class="shrink-0 border-l border-surface-container-highest pl-2">
                        <a 
                            href="{{ route('menu') }}"
                            wire:navigate
                            id="btnPosCrearProductoAdmin"
                            class="flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-black text-on-primary bg-primary hover:bg-primary-container border border-primary shadow-sm transition-all active:scale-95"
                            title="Solo Administrador: Crear o personalizar nuevo producto en la carta"
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
                    <button 
                        wire:click="agregarProducto({{ $prod->id }})"
                        class="group relative flex flex-col justify-between rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-3.5 text-left shadow-sm transition-all duration-150 hover:border-primary hover:shadow-md active:scale-95"
                    >
                        <div>
                            <!-- Header: Icon & Kitchen Area Chip -->
                            <div class="flex items-start justify-between gap-1">
                                <span class="text-2xl">{{ $prod->categoria?->icono ?? '🍣' }}</span>
                                <span class="rounded-md border border-surface-container-high bg-surface-container-low px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">
                                    {{ $prod->area_cocina }}
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
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-on-primary group-hover:bg-primary-container transition-colors font-bold text-base shadow-sm">
                                +
                            </span>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-surface-container-highest p-12 text-center text-on-surface-variant flex flex-col items-center justify-center gap-3">
                        <span class="material-symbols-outlined text-[36px] text-on-surface-variant/40">ramen_dining</span>
                        <p class="text-xs font-semibold">No hay productos o servicios en esta categoría.</p>
                        @if(auth()->user()?->role?->slug === 'admin')
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
                    <button 
                        wire:click="enviarACocina"
                        @disabled(empty($carrito) || ($tipo === 'mesa' && !$mesaId))
                        class="flex h-12 items-center justify-center gap-1.5 rounded-xl bg-surface-container border border-surface-container-high text-xs font-extrabold text-on-surface shadow-sm hover:bg-surface-container-high disabled:opacity-40 transition-all active:scale-95 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px] text-primary">skillet</span>
                        <span>Enviar Cocina</span>
                    </button>
                    <button 
                        wire:click="abrirModalCobro"
                        @disabled(empty($carrito))
                        class="flex h-12 items-center justify-center gap-1.5 rounded-xl bg-primary text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container disabled:opacity-40 transition-all active:scale-95 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">payments</span>
                        <span>Cobrar Pedido</span>
                    </button>
                </div>
                @if(auth()->user()?->role?->slug === 'mesero')
                    <p class="text-[10px] text-center text-on-surface-variant font-medium pt-1">
                        <span class="font-bold text-primary">Modo Mesero:</span> Envía comandas o cobra directo en mesa
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

    <!-- Modal de Cobro Táctil (Stitch POS-02 Billing Console) -->
    @if($mostrarModalCobro)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">point_of_sale</span>
                        </div>
                        <h3 class="text-base font-extrabold text-on-surface">Terminal de Cobro</h3>
                    </div>
                    <button wire:click="$set('mostrarModalCobro', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <!-- Total to pay banner -->
                    <div class="rounded-2xl bg-surface-container-low border border-surface-container-high p-4 text-center">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Monto Total a Cancelar</span>
                        <p class="font-mono text-3xl font-black text-primary mt-0.5">${{ number_format($this->total, 0, ',', '.') }}</p>
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
                        class="rounded-xl bg-secondary py-3 text-xs font-extrabold text-on-secondary shadow-md hover:bg-secondary-fixed-dim"
                    >
                        ✓ Confirmar y Emitir
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Thermal Ticket 80mm Simulation Modal (Optimizado para Impresoras Locales USB / Driver Navegador) -->
    @if($mostrarTicket && $pedidoCompletado)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="print-ticket-termico w-full max-w-sm rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest font-mono text-xs">
                <!-- Thermal Receipt Header -->
                <div class="text-center border-b border-dashed border-surface-container-high pb-4">
                    <p class="text-base font-black tracking-tight text-primary">🍣 SUSHIXPRESS 🍣</p>
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
                        <span>ATENDIÓ:</span>
                        <span>{{ auth()->user()->name }}</span>
                    </div>
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
                    <div class="flex justify-between text-sm font-black pt-1 text-on-surface">
                        <span>TOTAL:</span>
                        <span class="text-primary">${{ number_format($pedidoCompletado->total, 0, ',', '.') }}</span>
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
</div>
