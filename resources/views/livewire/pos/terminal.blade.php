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

    public function mount(): void
    {
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
        if ($puntos > $this->puntosDisponibles) {
            $puntos = $this->puntosDisponibles;
        }

        $descuento = app(\App\Services\FidelizacionService::class)->calcularDescuentoPorPuntos($puntos);
        if ($descuento > $this->subtotal) {
            $descuento = $this->subtotal;
            $puntos = (int) ceil($descuento / 10);
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

    public function enviarACocina(): void
    {
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
        if ($this->montoPagado < $this->total) {
            return;
        }

        $pedidoService = app(PedidoService::class);
        $costoEnvio = $this->tipo === 'delivery' ? $this->costoEnvio : 0.0;

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
        $this->redirect(route('mesas'), navigate: true);
    }

    public function with(): array
    {
        $query = Producto::query()->where('activo', true);

        if ($this->categoriaSeleccionada) {
            $query->where('categoria_id', $this->categoriaSeleccionada);
        }

        if (!empty($this->busqueda)) {
            $query->where('nombre', 'ilike', '%' . $this->busqueda . '%');
        }

        return [
            'categorias' => Categoria::where('activo', true)->orderBy('orden')->get(),
            'productos' => $query->get(),
            'mesas' => Mesa::orderBy('numero')->get(),
            'clientesDisponibles' => \App\Models\Cliente::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}; ?>

<div class="space-y-4">
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
                <button 
                    wire:click="$set('tipo', 'delivery')" 
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all {{ $tipo === 'delivery' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}"
                >
                    <span class="material-symbols-outlined text-[16px]">moped</span>
                    <span>Delivery</span>
                </button>
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
                    class="rounded-xl border border-surface-container-high bg-surface-container-low px-3 py-1.5 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                >
                    <option value="">Seleccionar mesa del salón...</option>
                    @foreach($mesas as $m)
                        <option value="{{ $m->id }}">
                            Mesa {{ $m->numero }} (Zona {{ $m->zona }} - {{ $m->estado }})
                        </option>
                    @endforeach
                </select>
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

        <!-- Search input -->
        <div class="w-full lg:w-64">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-2 text-[18px] text-on-surface-variant">search</span>
                <input 
                    type="text" 
                    wire:model.live.debounce.250ms="busqueda" 
                    placeholder="Buscar roll, nigiri o bebida..." 
                    class="w-full rounded-xl border border-surface-container-high bg-surface-container-low pl-9 pr-3 py-1.5 text-xs font-medium text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-0"
                />
            </div>
        </div>
    </div>

    <!-- Main POS Layout: 65% Catalogue (Left) + 35% Order Cart Terminal (Right) -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <!-- Catalogue Column (8 cols ~ 65%) -->
        <div class="space-y-4 lg:col-span-8">
            <!-- Category Filter Pills (Stitch Carousel Style) -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                <button 
                    wire:click="$set('categoriaSeleccionada', null)"
                    class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-xs font-extrabold transition-all border {{ is_null($categoriaSeleccionada) ? 'bg-primary text-on-primary border-primary shadow-sm' : 'bg-surface-container-lowest text-on-surface-variant border-surface-container-highest hover:bg-surface-container-low hover:text-on-surface' }}"
                >
                    <span class="material-symbols-outlined text-[16px]">restaurant_menu</span>
                    <span>Todo el Menú</span>
                </button>

                @foreach($categorias as $cat)
                    <button 
                        wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                        class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-xs font-extrabold transition-all border {{ $categoriaSeleccionada === $cat->id ? 'bg-primary text-on-primary border-primary shadow-sm' : 'bg-surface-container-lowest text-on-surface-variant border-surface-container-highest hover:bg-surface-container-low hover:text-on-surface' }}"
                    >
                        <span>{{ $cat->icono }}</span>
                        <span>{{ $cat->nombre }}</span>
                    </button>
                @endforeach
            </div>

            <!-- Product Grid (Stitch POS-01 Tactile Cards) -->
            <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 xl:grid-cols-4">
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

                            <!-- Dish Name & Description -->
                            <h4 class="mt-2.5 text-sm font-extrabold text-on-surface group-hover:text-primary transition-colors line-clamp-1">
                                {{ $prod->nombre }}
                            </h4>
                            <p class="mt-1 text-[11px] text-on-surface-variant line-clamp-2 leading-tight">
                                {{ $prod->descripcion }}
                            </p>
                        </div>

                        <!-- Price and Add Button -->
                        <div class="mt-3.5 flex items-center justify-between pt-2.5 border-t border-surface-container-high">
                            <span class="text-sm font-mono font-black text-primary">
                                ${{ number_format($prod->precio, 2) }}
                            </span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-on-primary group-hover:bg-primary-container transition-colors font-bold text-base shadow-sm">
                                +
                            </span>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-surface-container-highest p-12 text-center text-on-surface-variant">
                        No hay productos disponibles en esta categoría.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Order Cart Terminal Column (4 cols ~ 35%) -->
        <div class="flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-sm lg:col-span-4 min-h-[540px]">
            <div>
                <!-- Cart Header -->
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
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
                            class="text-[11px] font-bold text-error hover:underline"
                        >
                            Vaciar Carrito
                        </button>
                    @endif
                </div>

                <!-- Cart Items List (Stitch POS-01 Ergonomic Row Steppers) -->
                <div class="mt-3 space-y-2 max-h-[380px] overflow-y-auto pr-1 scrollbar-none">
                    @forelse($carrito as $pId => $item)
                        <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-on-surface truncate max-w-[170px]">
                                    {{ $item['nombre'] }}
                                </span>
                                <span class="text-xs font-mono font-extrabold text-primary">
                                    ${{ number_format($item['precio'] * $item['cantidad'], 2) }}
                                </span>
                            </div>

                            <!-- Row Controls: Stepper [- Qty +] and Prep Notes -->
                            <div class="mt-2 flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1">
                                    <button 
                                        wire:click="decrementarCantidad({{ $pId }})"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-surface-container font-bold text-on-surface shadow-sm hover:bg-surface-container-high active:scale-95"
                                    >
                                        -
                                    </button>
                                    <span class="w-6 text-center text-xs font-mono font-bold text-on-surface">
                                        {{ $item['cantidad'] }}
                                    </span>
                                    <button 
                                        wire:click="incrementarCantidad({{ $pId }})"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-surface-container font-bold text-on-surface shadow-sm hover:bg-surface-container-high active:scale-95"
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
                                    class="flex h-7 w-7 items-center justify-center rounded-lg text-on-surface-variant hover:text-error transition-colors"
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
            </div>

            <!-- Sticky Cart Summary & Dual Execution Triggers -->
            <div class="mt-4 pt-3 border-t border-surface-container-high space-y-3">
                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between text-on-surface-variant">
                        <span>Subtotal Comanda:</span>
                        <span class="font-mono font-bold text-on-surface">${{ number_format($this->subtotal, 2) }}</span>
                    </div>

                    @if($tipo === 'delivery')
                        <div class="flex justify-between text-on-surface-variant">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px] text-primary">two_wheeler</span>
                                Costo Envío Delivery:
                            </span>
                            <span class="font-mono font-bold text-on-surface">${{ number_format($costoEnvio, 2) }}</span>
                        </div>
                    @endif

                    @if($descuento > 0)
                        <div class="flex justify-between text-secondary">
                            <span>Descuento aplicado:</span>
                            <span class="font-mono font-bold">-${{ number_format($descuento, 2) }}</span>
                        </div>
                    @endif

                    @if($descuentoPuntos > 0)
                        <div class="flex justify-between text-tertiary">
                            <span>Descuento Fidelización ({{ $puntosCanjeados }} pts):</span>
                            <span class="font-mono font-bold">-${{ number_format($descuentoPuntos, 2) }}</span>
                        </div>
                    @elseif($clienteId && $puntosDisponibles > 0 && count($carrito) > 0)
                        @php $ptsCanje = min($puntosDisponibles, (int)floor($this->subtotal / 10)); @endphp
                        @if($ptsCanje > 0)
                            <button
                                wire:click="canjearPuntos({{ $ptsCanje }})"
                                class="w-full py-1.5 px-3 rounded-xl bg-tertiary-fixed text-on-tertiary-fixed text-[11px] font-extrabold flex items-center justify-between border border-tertiary/25 hover:bg-tertiary/20 transition-all active:scale-95"
                            >
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px] text-tertiary">loyalty</span>
                                    Canjear {{ $ptsCanje }} puntos de {{ $puntosDisponibles }}
                                </span>
                                <span>-${{ number_format($ptsCanje * 10) }}</span>
                            </button>
                        @endif
                    @endif

                    <div class="flex justify-between text-base font-extrabold text-on-surface pt-1 border-t border-dashed border-surface-container-high">
                        <span>Total Neto:</span>
                        <span class="text-primary font-mono font-black text-lg">${{ number_format($this->total, 2) }}</span>
                    </div>
                </div>

                <!-- Dual Tactical Touch Buttons -->
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button 
                        wire:click="enviarACocina"
                        @disabled(empty($carrito) || ($tipo === 'mesa' && !$mesaId))
                        class="flex h-12 items-center justify-center gap-1.5 rounded-xl bg-surface-container border border-surface-container-high text-xs font-extrabold text-on-surface shadow-sm hover:bg-surface-container-high disabled:opacity-40 transition-all active:scale-95"
                    >
                        <span class="material-symbols-outlined text-[18px] text-primary">skillet</span>
                        <span>Enviar Cocina</span>
                    </button>
                    <button 
                        wire:click="abrirModalCobro"
                        @disabled(empty($carrito))
                        class="flex h-12 items-center justify-center gap-1.5 rounded-xl bg-primary text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container disabled:opacity-40 transition-all active:scale-95"
                    >
                        <span class="material-symbols-outlined text-[18px]">payments</span>
                        <span>Cobrar Pedido</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

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
                        <p class="font-mono text-3xl font-black text-primary mt-0.5">${{ number_format($this->total, 2) }}</p>
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
                                step="0.50" 
                                wire:model.live="montoPagado" 
                                class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                            />

                            <!-- Quick denomination buttons -->
                            <div class="mt-2.5 grid grid-cols-4 gap-1.5">
                                <button wire:click="setMontoExacto" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                    Exacto
                                </button>
                                <button wire:click="sumarMonto(20.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                    $20
                                </button>
                                <button wire:click="sumarMonto(50.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                    $50
                                </button>
                                <button wire:click="sumarMonto(100.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                    $100
                                </button>
                            </div>

                            <!-- Change calculation -->
                            <div class="mt-3 flex items-center justify-between rounded-xl bg-secondary-container/40 border border-secondary/30 p-3 text-xs font-bold text-on-secondary-container">
                                <span>Cambio a Devolver:</span>
                                <span class="font-mono text-xl font-black text-secondary">${{ number_format($this->cambio, 2) }}</span>
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

    <!-- Thermal Ticket 80mm Simulation Modal -->
    @if($mostrarTicket && $pedidoCompletado)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-sm rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest font-mono text-xs">
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
                            <span class="font-bold">${{ number_format($it->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <!-- Ticket Totals -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span>SUBTOTAL:</span>
                        <span>${{ number_format($pedidoCompletado->subtotal, 2) }}</span>
                    </div>
                    @if($pedidoCompletado->descuento > 0)
                        <div class="flex justify-between text-secondary">
                            <span>DESCUENTO:</span>
                            <span>-${{ number_format($pedidoCompletado->descuento, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm font-black pt-1 text-on-surface">
                        <span>TOTAL:</span>
                        <span class="text-primary">${{ number_format($pedidoCompletado->total, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pt-1">
                        <span>PAGADO ({{ strtoupper($pedidoCompletado->metodo_pago) }}):</span>
                        <span>${{ number_format($pedidoCompletado->monto_pagado, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-secondary">
                        <span>CAMBIO:</span>
                        <span>${{ number_format($pedidoCompletado->cambio, 2) }}</span>
                    </div>
                </div>

                <!-- Ticket Footer Message -->
                <div class="pt-4 text-center text-[10px] text-on-surface-variant space-y-1">
                    <p class="font-bold text-on-surface">¡GRACIAS POR SU PREFERENCIA!</p>
                    <p>ありがとうございます (Arigatōgozaimashita)</p>
                    <p class="text-[9px]">Documento equivalente POS DIAN para control interno</p>
                </div>

                <!-- Close / Print buttons -->
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button 
                        onclick="window.print()" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high"
                    >
                        🖨️ Imprimir
                    </button>
                    <button 
                        wire:click="cerrarTicket" 
                        class="rounded-xl bg-primary py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container"
                    >
                        ✓ Finalizar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
