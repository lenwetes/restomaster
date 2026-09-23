<?php

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\ConfiguracionService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.publico')] class extends Component
{
    public string $categoriaSeleccionada = 'todas';
    public string $busqueda = '';
    public array $carrito = []; // [producto_id => ['producto_id' => X, 'nombre' => Y, 'precio' => Z, 'cantidad' => N, 'subtotal' => S, 'area_cocina' => A]]

    // Formulario del Cliente
    public string $nombreCliente = '';
    public string $telefonoCliente = '';
    public string $direccionDelivery = '';
    public string $referenciaDireccion = '';
    public string $notas = '';
    public string $metodoPago = 'nequi_bancolombia'; // 'nequi_bancolombia', 'efectivo', 'datafono'
    public string $pagaCon = '';

    #[Locked]
    public float $costoEnvio = 8000.0;
    public bool $mostrarCheckout = false;
    public bool $pedidoExitoso = false;
    public string $paso = 'catalogo';
    public string $empresa = ''; // honeypot
    public ?Pedido $pedidoCreado = null;

    public function mount(): void
    {
        $this->costoEnvio = (float) app(ConfiguracionService::class)->obtener('general', 'costo_envio_base', 8000.0);
    }

    public function irADatosEntrega(): void
    {
        $this->abrirCheckout();
        $this->paso = 'datos_entrega';
    }

    public function seleccionarCategoria(string $slug): void
    {
        $this->categoriaSeleccionada = $slug;
    }

    public function limpiarBusqueda(): void
    {
        $this->busqueda = '';
        $this->categoriaSeleccionada = 'todas';
    }

    public function agregarAlCarrito(int $productoId): void
    {
        $producto = Producto::where('activo', true)->findOrFail($productoId);
        $precio = (float) $producto->precio;

        if (isset($this->carrito[$productoId])) {
            $this->carrito[$productoId]['cantidad']++;
            $this->carrito[$productoId]['subtotal'] = $this->carrito[$productoId]['cantidad'] * $precio;
        } else {
            $this->carrito[$productoId] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'precio' => $precio,
                'cantidad' => 1,
                'subtotal' => $precio,
                'area_cocina' => $producto->area_cocina ?? 'caliente',
            ];
        }
    }

    public function modificarCantidad(int $productoId, int $delta): void
    {
        if (! isset($this->carrito[$productoId])) {
            return;
        }

        $nuevaCant = $this->carrito[$productoId]['cantidad'] + $delta;
        if ($nuevaCant <= 0) {
            unset($this->carrito[$productoId]);
        } else {
            $this->carrito[$productoId]['cantidad'] = $nuevaCant;
            $this->carrito[$productoId]['subtotal'] = $nuevaCant * $this->carrito[$productoId]['precio'];
        }
    }

    public function removerDelCarrito(int $productoId): void
    {
        unset($this->carrito[$productoId]);
    }

    public function abrirCheckout(): void
    {
        if (empty($this->carrito)) {
            return;
        }
        $this->mostrarCheckout = true;
    }

    public function cerrarCheckout(): void
    {
        $this->mostrarCheckout = false;
    }

    public function nuevoPedido(): void
    {
        $this->pedidoExitoso = false;
        $this->paso = 'catalogo';
        $this->pedidoCreado = null;
        $this->carrito = [];
        $this->mostrarCheckout = false;
        $this->nombreCliente = '';
        $this->telefonoCliente = '';
        $this->direccionDelivery = '';
        $this->referenciaDireccion = '';
        $this->notas = '';
        $this->pagaCon = '';
    }

    public function enviarPedidoDelivery(): void
    {
        if (! empty($this->empresa)) {
            $this->mostrarCheckout = false;
            return;
        }

        $rateKey = 'pedido-delivery:' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->addError('nombreCliente', 'Demasiadas solicitudes de pedido. Por favor espera un minuto antes de reintentar.');
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 60);

        $this->validate([
            'nombreCliente' => ['required', 'string', 'min:3', 'max:100'],
            'telefonoCliente' => ['required', 'string', 'min:7', 'max:25'],
            'direccionDelivery' => ['required', 'string', 'min:6', 'max:200'],
            'metodoPago' => ['required', 'in:nequi_bancolombia,efectivo,datafono'],
        ], [
            'nombreCliente.required' => 'Ingresa tu nombre y apellido.',
            'telefonoCliente.required' => 'Ingresa tu número de celular o WhatsApp.',
            'direccionDelivery.required' => 'Ingresa la dirección completa de entrega.',
        ]);

        if (empty($this->carrito)) {
            $this->mostrarCheckout = false;
            return;
        }

        // Recalcular subtotal tomando SIEMPRE los precios frescos de la base de datos
        $subtotal = 0.0;
        $itemsProcesados = [];

        foreach ($this->carrito as $item) {
            $producto = Producto::where('activo', true)->findOrFail($item['producto_id']);
            $cantidad = max(1, (int) $item['cantidad']);
            $precioUnitario = (float) $producto->precio;
            $itemSubtotal = $precioUnitario * $cantidad;

            $subtotal += $itemSubtotal;
            $itemsProcesados[] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $itemSubtotal,
                'area_cocina' => $producto->area_cocina ?? 'caliente',
            ];
        }

        $costoEnvioOficial = (float) app(ConfiguracionService::class)->obtener('general', 'costo_envio_base', 8000.0);
        $total = $subtotal + $costoEnvioOficial;

        $sucursal = Sucursal::where('activa', true)->first() ?? Sucursal::first();
        $sucursalId = $sucursal?->id;

        $pedido = DB::transaction(function () use ($subtotal, $costoEnvioOficial, $total, $itemsProcesados, $sucursalId) {
            $ultimoNum = (int) Pedido::where('tipo', 'delivery')->max('id');
            $nuevoCodigo = sprintf('DLV-%04d', $ultimoNum + 1);

            $notasFinales = $this->notas;
            if (! empty($this->referenciaDireccion)) {
                $notasFinales = trim($notasFinales . ' | Ref: ' . $this->referenciaDireccion, ' | ');
            }
            if ($this->metodoPago === 'efectivo' && ! empty($this->pagaCon)) {
                $notasFinales = trim($notasFinales . " | Paga con: {$this->pagaCon}", ' | ');
            }

            $pedido = Pedido::create([
                'codigo' => $nuevoCodigo,
                'tipo' => 'delivery',
                'estado' => 'creado',
                'estado_cocina' => 'pendiente',
                'estado_delivery' => 'pendiente',
                'canal_origen' => 'web_delivery',
                'sucursal_id' => $sucursalId,
                'nombre_cliente' => $this->nombreCliente,
                'telefono_cliente' => $this->telefonoCliente,
                'direccion_delivery' => $this->direccionDelivery,
                'costo_envio' => $costoEnvioOficial,
                'subtotal' => $subtotal,
                'descuento' => 0,
                'total' => $total,
                'metodo_pago' => $this->metodoPago,
                'notas' => $notasFinales,
            ]);

            foreach ($itemsProcesados as $it) {
                ItemPedido::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $it['producto_id'],
                    'nombre_producto' => $it['nombre'],
                    'cantidad' => $it['cantidad'],
                    'precio_unitario' => $it['precio_unitario'],
                    'subtotal' => $it['subtotal'],
                    'area_cocina' => $it['area_cocina'],
                    'estado_cocina' => 'pendiente',
                ]);
            }

            return $pedido;
        });

        $this->pedidoCreado = $pedido->fresh(['items']);
        $this->pedidoExitoso = true;
        $this->paso = 'confirmacion_exitosa';
        $this->mostrarCheckout = false;
        $this->carrito = [];
    }

    public function with(): array
    {
        $categoriasQuery = Categoria::where('activo', true)
            ->with(['productos' => function ($q) {
                $q->where('activo', true);
                if (! empty($this->busqueda)) {
                    $q->where(function ($sub) {
                        $sub->where('nombre', 'ilike', '%' . $this->busqueda . '%')
                            ->orWhere('descripcion', 'ilike', '%' . $this->busqueda . '%');
                    });
                }
                $q->orderBy('nombre');
            }])
            ->orderBy('orden');

        if ($this->categoriaSeleccionada !== 'todas') {
            $categoriasQuery->where('slug', $this->categoriaSeleccionada);
        }

        $categorias = $categoriasQuery->get();
        $todasCategorias = Categoria::where('activo', true)
            ->withCount(['productos' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderBy('orden')
            ->get(['id', 'nombre', 'slug', 'icono', 'color']);

        $totalPlatosGeneral = $todasCategorias->sum('productos_count');

        $subtotal = 0.0;
        $totalItems = 0;
        foreach ($this->carrito as $item) {
            $subtotal += $item['subtotal'];
            $totalItems += $item['cantidad'];
        }

        return [
            'categorias' => $categorias,
            'todasCategorias' => $todasCategorias,
            'totalPlatosGeneral' => $totalPlatosGeneral,
            'subtotal' => $subtotal,
            'totalItems' => $totalItems,
            'totalGeneral' => $subtotal > 0 ? $subtotal + $this->costoEnvio : 0,
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

    <!-- ============================================================= -->
    <!-- ESTADO 1: CONFIRMACIÓN EXITOSA DEL PEDIDO                     -->
    <!-- ============================================================= -->
    @if ($pedidoExitoso && $pedidoCreado)
        <div class="max-w-2xl mx-auto my-8 p-6 sm:p-10 rounded-3xl bg-[#1e1410] border border-emerald-500/40 shadow-2xl text-center space-y-6 animate-fade-in">
            <!-- Animated Success Icon -->
            <div class="w-20 h-20 mx-auto rounded-3xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-xl shadow-emerald-500/10">
                <span class="material-symbols-outlined text-4xl">check_circle</span>
            </div>

            <div class="space-y-2">
                <span class="px-3.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 text-xs font-black uppercase tracking-wider font-mono border border-emerald-500/30">
                    ¡Pedido Recibido en Cocina!
                </span>
                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                    Tu pedido ya está en marcha
                </h1>
                <p class="text-xs sm:text-sm text-[#c4a89e] max-w-md mx-auto leading-relaxed">
                    Hemos registrado tu orden en nuestro sistema de cocina y despacho. Nuestro asesor confirmará los detalles de entrega en tu celular.
                </p>
            </div>

            <!-- Tracking Code Box -->
            <div class="p-6 rounded-2xl bg-[#261a15] border border-[#432f26] space-y-1">
                <span class="text-[11px] font-bold uppercase tracking-widest text-[#7a5a52] font-mono">Número de Orden Oficial</span>
                <div class="text-3xl sm:text-4xl font-black text-[#e0442e] tracking-tight font-mono">
                    #{{ $pedidoCreado->codigo }}
                </div>
                <p class="text-[11px] text-[#c4a89e]">Guarda este código para verificar tu entrega y método de pago.</p>
            </div>

            <!-- Order Summary Card -->
            <div class="p-5 rounded-2xl bg-[#261a15] border border-[#432f26] text-left space-y-3 text-xs">
                <div class="flex justify-between items-center pb-2.5 border-b border-[#432f26]/60">
                    <span class="text-[#c4a89e]">Cliente:</span>
                    <span class="font-bold text-white">{{ $pedidoCreado->nombre_cliente }} ({{ $pedidoCreado->telefono_cliente }})</span>
                </div>
                <div class="flex justify-between items-center pb-2.5 border-b border-[#432f26]/60">
                    <span class="text-[#c4a89e]">Dirección:</span>
                    <span class="font-bold text-white text-right max-w-xs">{{ $pedidoCreado->direccion_delivery }}</span>
                </div>
                <div class="flex justify-between items-center pb-2.5 border-b border-[#432f26]/60">
                    <span class="text-[#c4a89e]">Método de Pago:</span>
                    <span class="font-bold text-[#e8a020] uppercase font-mono">{{ str_replace('_', ' ', $pedidoCreado->metodo_pago) }}</span>
                </div>
                <div class="flex justify-between items-center pt-1 font-bold text-sm">
                    <span class="text-white">Total a Pagar:</span>
                    <span class="text-[#e0442e] font-mono text-base font-black">$ {{ number_format($pedidoCreado->total, 0, ',', '.') }} COP</span>
                </div>
            </div>

            <!-- WhatsApp Action Button -->
            @php
                $mensajeWp = urlencode("¡Hola RestoMaster! Acabo de hacer el pedido #{$pedidoCreado->codigo} a nombre de {$pedidoCreado->nombre_cliente} por $ " . number_format($pedidoCreado->total, 0, ',', '.') . " COP. Mi dirección es: {$pedidoCreado->direccion_delivery}.");
            @endphp

            <div class="space-y-3 pt-2">
                <a 
                    href="https://wa.me/573001234567?text={{ $mensajeWp }}" 
                    target="_blank" 
                    class="w-full py-4 px-6 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm shadow-xl shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[20px]">chat</span>
                    <span>Confirmar Pedido por WhatsApp</span>
                </a>

                <button 
                    type="button" 
                    wire:click="nuevoPedido" 
                    class="w-full py-3.5 rounded-2xl bg-[#261a15] hover:bg-[#38271f] text-[#c4a89e] hover:text-white font-bold text-xs border border-[#432f26] transition-all cursor-pointer"
                >
                    Hacer otro pedido
                </button>
            </div>
        </div>

    <!-- ============================================================= -->
    <!-- ESTADO 2: CATÁLOGO INTERACTIVO Y BOLSA DE COMPRAS             -->
    <!-- ============================================================= -->
    @else
        <!-- Delivery Header Banner -->
        <div class="mb-6 p-6 sm:p-7 rounded-3xl bg-gradient-to-r from-[#1e1410] via-[#241711] to-[#1e1410] border border-[#432f26] relative overflow-hidden flex flex-col sm:flex-row items-center justify-between gap-6 shadow-2xl">
            <div class="space-y-2 text-center sm:text-left">
                <div class="flex items-center justify-center sm:justify-start gap-2">
                    <span class="px-3 py-1 rounded-full bg-[#e0442e]/15 text-[#ff7e67] text-[11px] font-black uppercase tracking-wider border border-[#e0442e]/30 font-mono">
                        🛵 Pedidos en Línea · Despacho Inmediato
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                    Delivery Gastronómico RestoMaster
                </h1>
                <p class="text-xs sm:text-sm text-[#c4a89e] max-w-xl leading-relaxed">
                    Cortes a la brasa, pastas frescas artesanales, entradas de autor y coctelería empacados especialmente para mantener temperatura y textura en tu puerta.
                </p>
            </div>

            <!-- Badges -->
            <div class="flex items-center gap-4 bg-[#140e0b]/80 p-4 rounded-2xl border border-[#432f26] shrink-0 text-xs backdrop-blur-md">
                <div class="flex items-center gap-2 text-white font-bold">
                    <span class="material-symbols-outlined text-[#e8a020] text-[20px]">schedule</span>
                    <span class="font-mono">35-45 min</span>
                </div>
                <div class="w-px h-6 bg-[#432f26]"></div>
                <div class="flex items-center gap-2 text-white font-bold">
                    <span class="material-symbols-outlined text-emerald-400 text-[20px]">local_shipping</span>
                    <span class="font-mono">$ 8.000 COP</span>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- STICKY TOP CONTROLS: CATEGORIES & QUICK SEARCH            -->
        <!-- ========================================================= -->
        <div class="sticky top-20 z-30 bg-[#0e0907]/95 backdrop-blur-xl py-3.5 mb-6 border-y border-[#432f26]/60 shadow-2xl space-y-3">
            
            <!-- Tier 1: Category Status Info + Search Input + Cart Summary Trigger -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#e0442e] animate-pulse shrink-0"></span>
                    <span class="text-xs font-black uppercase tracking-wider text-white font-mono">
                        @if ($categoriaSeleccionada === 'todas')
                            Toda la Carta <span class="text-[#e8a020]">({{ $totalPlatosGeneral }} platos)</span>
                        @else
                            @php
                                $catActiva = $todasCategorias->firstWhere('slug', $categoriaSeleccionada);
                            @endphp
                            Categoría: <span class="text-[#e8a020]">{{ $catActiva?->nombre ?? 'Seleccionada' }}</span> ({{ $catActiva?->productos_count ?? 0 }} platos)
                        @endif
                    </span>
                    @if ($categoriaSeleccionada !== 'todas')
                        <button 
                            type="button" 
                            wire:click="seleccionarCategoria('todas')" 
                            class="text-[11px] text-[#ff7e67] hover:text-white font-bold ml-1.5 cursor-pointer underline font-mono"
                        >
                            Ver todo
                        </button>
                    @endif
                </div>

                <!-- Right controls: Search bar & Quick Cart Trigger -->
                <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                    <div class="relative w-full sm:w-80">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#7a5a52]">
                            <span class="material-symbols-outlined text-[18px]">search</span>
                        </span>
                        <input 
                            type="text" 
                            wire:model.live.debounce.250ms="busqueda" 
                            placeholder="Buscar en la carta..." 
                            aria-label="Buscar platos en la carta"
                            class="w-full pl-10 pr-9 py-2.5 rounded-2xl border border-[#432f26] bg-[#1e1410] text-xs text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none shadow-inner"
                        />
                        @if(!empty($busqueda))
                            <button 
                                type="button" 
                                wire:click="limpiarBusqueda" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#7a5a52] hover:text-white cursor-pointer"
                            >
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        @endif
                    </div>

                    @if ($totalItems > 0)
                        <button 
                            type="button" 
                            wire:click="abrirCheckout" 
                            class="px-4 py-2.5 rounded-2xl bg-[#e0442e] text-white text-xs font-black shadow-lg shadow-[#e0442e]/30 flex items-center gap-1.5 shrink-0 cursor-pointer hover:scale-105 active:scale-95 transition-all"
                            title="Ir a pagar"
                        >
                            <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
                            <span class="font-mono">${{ number_format($totalGeneral, 0, ',', '.') }}</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Tier 2: Category Pills - FLEX-WRAP (100% of categories visible, ZERO cut-off!) -->
            <div class="pt-1">
                
                <!-- Quick Mobile Dropdown (for instant 1-tap jump on small phone screens) -->
                <div class="sm:hidden mb-2.5">
                    <select 
                        wire:change="seleccionarCategoria($event.target.value)"
                        class="w-full py-2.5 px-3.5 rounded-2xl bg-[#1e1410] border border-[#432f26] text-xs font-black text-white outline-none focus:border-[#e0442e]"
                    >
                        <option value="todas" {{ $categoriaSeleccionada === 'todas' ? 'selected' : '' }}>
                            🔥 Todas las Categorías ({{ $totalPlatosGeneral }} platos)
                        </option>
                        @foreach ($todasCategorias as $catSelect)
                            <option value="{{ $catSelect->slug }}" {{ $categoriaSeleccionada === $catSelect->slug ? 'selected' : '' }}>
                                {{ $catSelect->nombre }} ({{ $catSelect->productos_count }} platos)
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Category Pills (Wrap cleanly into multiple rows so every category is 100% visible) -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                    <!-- 'Todas' Pill -->
                    <button 
                        type="button" 
                        wire:click="seleccionarCategoria('todas')" 
                        class="px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-2xl text-xs font-black transition-all shrink-0 cursor-pointer flex items-center gap-2 border {{ $categoriaSeleccionada === 'todas' ? 'bg-[#e0442e] text-white border-[#e0442e] shadow-lg shadow-[#e0442e]/35 scale-102 ring-2 ring-[#e0442e]/40' : 'bg-[#1e1410] text-[#c4a89e] hover:text-white border-[#432f26] hover:border-[#7a5a52]' }}"
                    >
                        <span class="text-sm">🔥</span>
                        <span>Todas las Categorías</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $categoriaSeleccionada === 'todas' ? 'bg-black/30 text-white' : 'bg-[#261a15] text-[#c4a89e]' }}">
                            {{ $totalPlatosGeneral }}
                        </span>
                    </button>

                    <!-- Each Category Pill -->
                    @foreach ($todasCategorias as $catPill)
                        @php
                            $catColor = $catPill->color ?? '#e0442e';
                            $isSelected = ($categoriaSeleccionada === $catPill->slug);
                        @endphp
                        <button 
                            type="button" 
                            wire:click="seleccionarCategoria('{{ $catPill->slug }}')" 
                            class="px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-2xl text-xs font-black transition-all shrink-0 flex items-center gap-2 cursor-pointer border {{ $isSelected ? 'text-white shadow-lg scale-102 ring-2 ring-offset-0' : 'bg-[#1e1410] text-[#c4a89e] hover:text-white border-[#432f26] hover:border-[#7a5a52]' }}"
                            @style([
                                'background-color: ' . $catColor => $isSelected,
                                'border-color: ' . $catColor => $isSelected,
                                'box-shadow: 0 4px 14px ' . $catColor . '40' => $isSelected,
                            ])
                        >
                            <span 
                                class="w-2.5 h-2.5 rounded-full shrink-0 shadow-xs" 
                                @style(['background-color: ' . $catColor])
                            ></span>
                            @if(preg_match('/^[a-z0-9_]+$/', $catPill->icono ?? ''))
                                <span class="material-symbols-outlined text-[17px]">{{ $catPill->icono }}</span>
                            @else
                                <span>{{ $catPill->icono ?? '🍽️' }}</span>
                            @endif
                            <span>{{ $catPill->nombre }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $isSelected ? 'bg-black/30 text-white' : 'bg-[#261a15] text-[#c4a89e]' }}">
                                {{ $catPill->productos_count }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- ========================================================= -->
        <!-- MAIN CATALOG GRID: PRODUCTS (8 COLS) + CART SIDEBAR (4)   -->
        <!-- ========================================================= -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start">
            
            <!-- Products Catalog Column (8 cols) -->
            <div class="lg:col-span-8 space-y-10">
                @forelse ($categorias as $cat)
                    @if ($cat->productos->isNotEmpty())
                        <div class="space-y-4">
                            @php
                                $categoriaColor = $cat->color ?? '#e0442e';
                            @endphp
                            
                            <!-- Category Section Subheader -->
                            <div class="flex items-center justify-between border-b border-[#432f26]/60 pb-3">
                                <div class="flex items-center gap-3">
                                    <div 
                                        class="w-9 h-9 rounded-xl flex items-center justify-center text-white shadow-md text-base"
                                        @style([
                                            'background-color: ' . $categoriaColor . '25',
                                            'border: 1.5px solid ' . $categoriaColor,
                                        ])
                                    >
                                        @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                            <span class="material-symbols-outlined text-base">{{ $cat->icono }}</span>
                                        @else
                                            <span>{{ $cat->icono ?? '🍽️' }}</span>
                                        @endif
                                    </div>
                                    <h2 class="text-lg sm:text-xl font-black text-white tracking-tight">{{ $cat->nombre }}</h2>
                                    <span 
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono text-white"
                                        @style(['background-color: ' . $categoriaColor])
                                    >
                                        {{ $cat->productos->count() }}
                                    </span>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-[#7a5a52] font-mono">RestoMaster Gourmet</span>
                            </div>

                            <!-- Compact, Appetizing Horizontal Food Cards Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                                @foreach ($cat->productos as $producto)
                                    @php
                                        $enCarrito = isset($carrito[$producto->id]);
                                        $cant = $enCarrito ? $carrito[$producto->id]['cantidad'] : 0;
                                        $area = $producto->area_cocina ?? 'caliente';
                                        $isBarra = in_array($area, ['barra', 'bebidas']);
                                        $isFria = in_array($area, ['fria', 'sushi']);
                                    @endphp
                                    
                                    <div 
                                        class="rounded-2xl bg-[#1e1410] border {{ $enCarrito ? 'border-[#e0442e] shadow-lg shadow-[#e0442e]/10' : 'border-[#432f26]' }} p-3 sm:p-4 flex gap-3.5 items-center justify-between transition-all duration-300 hover:border-[#7a5a52] group relative overflow-hidden"
                                    >
                                        <!-- Dish Image Thumbnail (Appetizing & Compact) -->
                                        <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-2xl overflow-hidden bg-[#140e0b] shrink-0 border border-[#432f26]/60 shadow-inner">
                                            @if (!empty($producto->imagen))
                                                <img 
                                                    src="{{ asset($producto->imagen) }}" 
                                                    alt="{{ $producto->nombre }}" 
                                                    loading="lazy"
                                                    class="w-full h-full object-cover group-hover:scale-108 transition-transform duration-500 brightness-95"
                                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                                />
                                                <div class="hidden absolute inset-0 items-center justify-center bg-[#1e1410] text-[#7a5a52]">
                                                    <span class="material-symbols-outlined text-2xl">restaurant</span>
                                                </div>
                                            @else
                                                <div class="w-full h-full flex items-center justify-center bg-[#261a15] text-[#7a5a52]">
                                                    <span class="material-symbols-outlined text-3xl">restaurant</span>
                                                </div>
                                            @endif
                                            
                                            <!-- Badge on Image -->
                                            <span class="absolute bottom-1.5 left-1.5 px-1.5 py-0.5 rounded text-[8px] font-black font-mono uppercase tracking-wider backdrop-blur-md {{ $isBarra ? 'bg-purple-950/80 text-purple-200' : ($isFria ? 'bg-[#2eb8b4]/90 text-white' : 'bg-[#e0442e]/90 text-white') }}">
                                                {{ $isBarra ? 'Bar' : ($isFria ? 'Fría' : 'Brasa') }}
                                            </span>
                                        </div>

                                        <!-- Dish Information & Price & Action -->
                                        <div class="flex-1 min-w-0 flex flex-col justify-between h-24 sm:h-28 py-0.5">
                                            <div>
                                                <h3 class="font-extrabold text-white text-xs sm:text-sm leading-snug truncate group-hover:text-[#e0442e] transition-colors" title="{{ $producto->nombre }}">
                                                    {{ $producto->nombre }}
                                                </h3>
                                                <p class="text-[11px] text-[#c4a89e] leading-snug line-clamp-2 mt-1">
                                                    {{ $producto->descripcion ?? 'Elaborado artesanalmente con ingredientes de alta calidad y cocción al detalle.' }}
                                                </p>
                                            </div>

                                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-[#432f26]/40 mt-auto">
                                                <!-- Price -->
                                                <div>
                                                    <span class="text-xs sm:text-sm font-black text-white font-mono block">
                                                        $ {{ number_format($producto->precio, 0, ',', '.') }}
                                                    </span>
                                                </div>

                                                <!-- Add / Modify Controls -->
                                                <div>
                                                    @if (! $enCarrito)
                                                        <button 
                                                            type="button" 
                                                            wire:click="agregarAlCarrito({{ $producto->id }})" 
                                                            class="px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-xl bg-[#e0442e] hover:bg-[#b8301d] text-white text-xs font-black shadow-md flex items-center gap-1 transition-all cursor-pointer hover:scale-105 active:scale-95"
                                                        >
                                                            <span class="material-symbols-outlined text-[16px]">add</span>
                                                            <span class="hidden sm:inline">Agregar</span>
                                                        </button>
                                                    @else
                                                        <div class="flex items-center gap-1 bg-[#261a15] border border-[#e0442e]/60 p-0.5 rounded-xl shadow-inner">
                                                            <button 
                                                                type="button" 
                                                                wire:click="modificarCantidad({{ $producto->id }}, -1)" 
                                                                class="w-6 h-6 rounded-lg bg-[#1e1410] hover:bg-[#38271f] text-white flex items-center justify-center font-bold text-xs cursor-pointer transition-colors"
                                                                title="Disminuir"
                                                            >
                                                                −
                                                            </button>
                                                            <span class="w-5 text-center font-black text-xs text-white font-mono">{{ $cant }}</span>
                                                            <button 
                                                                type="button" 
                                                                wire:click="modificarCantidad({{ $producto->id }}, 1)" 
                                                                class="w-6 h-6 rounded-lg bg-[#e0442e] hover:bg-[#b8301d] text-white flex items-center justify-center font-bold text-xs cursor-pointer transition-colors"
                                                                title="Aumentar"
                                                            >
                                                                +
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="text-center py-16 p-8 rounded-3xl bg-[#1e1410] border border-[#432f26] text-[#c4a89e] space-y-3 max-w-md mx-auto">
                        <span class="text-4xl block">🔍</span>
                        <p class="font-bold text-white text-base">No se encontraron platos con "{{ $busqueda }}"</p>
                        <p class="text-xs">Prueba con otra palabra o selecciona "Todas las Categorías".</p>
                        <button 
                            type="button" 
                            wire:click="limpiarBusqueda" 
                            class="px-4 py-2 rounded-xl bg-[#e0442e] text-white text-xs font-bold cursor-pointer"
                        >
                            Ver Todo el Menú
                        </button>
                    </div>
                @endforelse
            </div>

            <!-- ========================================================= -->
            <!-- CART SIDEBAR (DESKTOP: FIXED & PINNED PURCHASE CONTROLS)  -->
            <!-- ========================================================= -->
            <div class="hidden lg:block lg:col-span-4 sticky top-24">
                <div class="rounded-3xl bg-[#1e1410] border border-[#432f26] shadow-2xl p-5 sm:p-6 space-y-4 max-h-[calc(100vh-120px)] flex flex-col justify-between">
                    
                    <!-- Cart Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-[#432f26]/60 shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🛍️</span>
                            <h3 class="font-black text-white text-base">Tu Bolsa de Pedido</h3>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full bg-[#e0442e]/15 text-[#ff7e67] border border-[#e0442e]/30 text-xs font-black font-mono">
                            {{ $totalItems }} {{ $totalItems === 1 ? 'item' : 'items' }}
                        </span>
                    </div>

                    @if (empty($carrito))
                        <div class="py-12 text-center text-[#c4a89e] space-y-3 flex-1 flex flex-col justify-center items-center">
                            <div class="w-16 h-16 rounded-2xl bg-[#261a15] border border-[#432f26] flex items-center justify-center text-[#7a5a52]">
                                <span class="material-symbols-outlined text-3xl">shopping_bag</span>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-bold text-white">Tu bolsa está vacía</p>
                                <p class="text-[11px] text-[#7a5a52] max-w-xs">Haz clic en "+ Agregar" en tus platos favoritos para iniciar tu orden.</p>
                            </div>
                        </div>
                    @else
                        <!-- Cart Items Scrollable List -->
                        <div class="flex-1 overflow-y-auto space-y-2.5 pr-1 no-scrollbar max-h-72">
                            @foreach ($carrito as $item)
                                <div class="p-3 rounded-2xl bg-[#261a15] border border-[#432f26] flex items-center justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-bold text-white text-xs truncate">{{ $item['nombre'] }}</h4>
                                        <span class="text-[11px] text-[#e8a020] font-mono">$ {{ number_format($item['precio'], 0, ',', '.') }}</span>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button 
                                            type="button" 
                                            wire:click="modificarCantidad({{ $item['producto_id'] }}, -1)" 
                                            class="w-6 h-6 rounded-lg bg-[#1e1410] hover:bg-[#38271f] text-[#f5e8e2] flex items-center justify-center text-xs font-bold cursor-pointer"
                                        >
                                            −
                                        </button>
                                        <span class="w-5 text-center font-bold text-xs text-white font-mono">{{ $item['cantidad'] }}</span>
                                        <button 
                                            type="button" 
                                            wire:click="modificarCantidad({{ $item['producto_id'] }}, 1)" 
                                            class="w-6 h-6 rounded-lg bg-[#e0442e] hover:bg-[#b8301d] text-white flex items-center justify-center text-xs font-bold cursor-pointer"
                                        >
                                            +
                                        </button>
                                        <button 
                                            type="button" 
                                            wire:click="removerDelCarrito({{ $item['producto_id'] }})" 
                                            class="p-1 text-[#7a5a52] hover:text-rose-400 transition-colors ml-1 cursor-pointer"
                                            title="Quitar"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Price Breakdown & PINNED PURCHASE BUTTON -->
                        <div class="pt-3 border-t border-[#432f26]/60 space-y-2 text-xs shrink-0">
                            <div class="flex justify-between text-[#c4a89e]">
                                <span>Subtotal platos:</span>
                                <span class="font-mono text-white">$ {{ number_format($subtotal, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-[#c4a89e]">
                                <span>Costo de envío (Provenza & Poblado):</span>
                                <span class="font-mono text-white">$ {{ number_format($costoEnvio, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between font-black text-sm pt-2 border-t border-[#432f26]/60">
                                <span class="text-white">Total a Pagar:</span>
                                <span class="text-[#e0442e] font-mono text-base font-black">$ {{ number_format($totalGeneral, 0, ',', '.') }} COP</span>
                            </div>

                            <!-- UNMISSABLE PURCHASE TRIGGER BUTTON -->
                            <button 
                                type="button" 
                                wire:click="abrirCheckout" 
                                class="w-full mt-2 py-4 px-4 rounded-2xl bg-[#e0442e] hover:bg-[#b8301d] text-white font-black text-sm shadow-xl shadow-[#e0442e]/30 flex items-center justify-center gap-2 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
                            >
                                <span>Tramitar Pedido</span>
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </button>
                        </div>
                    @endif

                </div>
            </div>

        </div>

        <!-- ========================================================= -->
        <!-- FLOATING PURCHASE CONTROL BAR (ALWAYS VISIBLE WHEN CART > 0)-->
        <!-- ========================================================= -->
        @if (! empty($carrito))
            <div 
                wire:click="abrirCheckout" 
                class="fixed bottom-5 left-4 right-4 sm:left-auto sm:right-6 sm:w-[420px] z-50 bg-gradient-to-r from-[#e0442e] to-[#b8301d] text-white p-3.5 sm:p-4 rounded-2xl shadow-2xl shadow-[#e0442e]/45 border border-white/20 flex items-center justify-between gap-3 cursor-pointer hover:scale-[1.02] active:scale-[0.98] transition-all"
            >
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-black/20 flex items-center justify-center font-bold text-white shadow-inner">
                        <span class="material-symbols-outlined text-[22px]">shopping_bag</span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-white/90 block font-mono">{{ $totalItems }} {{ $totalItems === 1 ? 'plato' : 'platos' }} en bolsa</span>
                        <span class="text-base font-black text-white font-mono">$ {{ number_format($totalGeneral, 0, ',', '.') }} COP</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-1.5 bg-white text-[#1e1410] px-4 py-2 rounded-xl text-xs font-black shadow-md">
                    <span>Tramitar Pedido</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </div>
            </div>
        @endif

    @endif

    <!-- ============================================================= -->
    <!-- MODAL DE TRAMITACIÓN DE COMPRA (CHECKOUT FLUIDO)             -->
    <!-- ============================================================= -->
    @if ($mostrarCheckout)
        <div 
            x-data 
            @keydown.escape.window="$wire.cerrarCheckout()" 
            role="dialog" 
            aria-modal="true" 
            aria-labelledby="modal-checkout-title" 
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/85 backdrop-blur-md animate-fade-in overflow-y-auto"
        >
            <div class="w-full max-w-xl bg-[#1e1410] border border-[#432f26] rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6 relative my-auto">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-[#432f26]/60">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl">🛵</span>
                        <div>
                            <h3 id="modal-checkout-title" class="font-black text-white text-lg">Tramitar Pedido a Domicilio</h3>
                            <p class="text-xs text-[#c4a89e]">Completa tus datos de entrega y forma de pago</p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        wire:click="cerrarCheckout" 
                        aria-label="Cerrar ventana de tramitación" 
                        class="min-w-[40px] min-h-[40px] rounded-full bg-[#261a15] hover:bg-[#38271f] text-[#c4a89e] hover:text-white flex items-center justify-center transition-colors cursor-pointer border border-[#432f26]"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Checkout Form -->
                <form wire:submit="enviarPedidoDelivery" class="space-y-4">
                    
                    <!-- Customer Name & Phone -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-1.5 font-mono">
                                Tu Nombre Completo *
                            </label>
                            <input 
                                type="text" 
                                wire:model="nombreCliente" 
                                placeholder="Ej. Carlos Mendoza" 
                                class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-xs text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none"
                            />
                            @error('nombreCliente')
                                <p class="text-[11px] text-rose-400 mt-1 font-bold">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-1.5 font-mono">
                                Teléfono / WhatsApp *
                            </label>
                            <input 
                                type="tel" 
                                wire:model="telefonoCliente" 
                                placeholder="Ej. 300 123 4567" 
                                class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-xs text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none font-mono"
                            />
                            @error('telefonoCliente')
                                <p class="text-[11px] text-rose-400 mt-1 font-bold">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Delivery Address & References -->
                    <div>
                        <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-1.5 font-mono">
                            Dirección de Entrega Exacta *
                        </label>
                        <input 
                            type="text" 
                            wire:model="direccionDelivery" 
                            placeholder="Calle, Carrera, Edificio, Apartamento..." 
                            class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-xs text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none"
                        />
                        @error('direccionDelivery')
                            <p class="text-[11px] text-rose-400 mt-1 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-1.5 font-mono">
                                Referencias (Opcional)
                            </label>
                            <input 
                                type="text" 
                                wire:model="referenciaDireccion" 
                                placeholder="Ej. Portería 2, Apto 504" 
                                class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-xs text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-1.5 font-mono">
                                Notas para Cocina (Opcional)
                            </label>
                            <input 
                                type="text" 
                                wire:model="notas" 
                                placeholder="Ej. Término medio, sin cebolla..." 
                                class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-xs text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none"
                            />
                        </div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div class="space-y-2.5 pt-2 border-t border-[#432f26]/60">
                        <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider font-mono">
                            Método de Pago *
                        </label>
                        <div class="grid grid-cols-3 gap-2.5">
                            <label class="p-3 rounded-2xl border text-center cursor-pointer transition-all {{ $metodoPago === 'nequi_bancolombia' ? 'bg-[#e0442e]/15 border-[#e0442e] text-white font-bold shadow-md shadow-[#e0442e]/20' : 'bg-[#261a15] border-[#432f26] text-[#c4a89e] hover:border-[#7a5a52]' }}">
                                <input type="radio" wire:model.live="metodoPago" value="nequi_bancolombia" class="sr-only" />
                                <span class="material-symbols-outlined text-[22px] block mx-auto mb-1 text-[#e8a020]">qr_code_2</span>
                                <span class="text-[11px] font-bold block">Nequi / Bancolombia</span>
                            </label>

                            <label class="p-3 rounded-2xl border text-center cursor-pointer transition-all {{ $metodoPago === 'efectivo' ? 'bg-[#e0442e]/15 border-[#e0442e] text-white font-bold shadow-md shadow-[#e0442e]/20' : 'bg-[#261a15] border-[#432f26] text-[#c4a89e] hover:border-[#7a5a52]' }}">
                                <input type="radio" wire:model.live="metodoPago" value="efectivo" class="sr-only" />
                                <span class="material-symbols-outlined text-[22px] block mx-auto mb-1 text-emerald-400">payments</span>
                                <span class="text-[11px] font-bold block">Efectivo</span>
                            </label>

                            <label class="p-3 rounded-2xl border text-center cursor-pointer transition-all {{ $metodoPago === 'datafono' ? 'bg-[#e0442e]/15 border-[#e0442e] text-white font-bold shadow-md shadow-[#e0442e]/20' : 'bg-[#261a15] border-[#432f26] text-[#c4a89e] hover:border-[#7a5a52]' }}">
                                <input type="radio" wire:model.live="metodoPago" value="datafono" class="sr-only" />
                                <span class="material-symbols-outlined text-[22px] block mx-auto mb-1 text-sky-400">credit_card</span>
                                <span class="text-[11px] font-bold block">Datáfono</span>
                            </label>
                        </div>

                        <!-- Payment Details Context Box -->
                        @if ($metodoPago === 'nequi_bancolombia')
                            <div class="p-4 rounded-2xl bg-[#261a15] border border-[#432f26] text-xs text-[#c4a89e] space-y-1.5">
                                <p class="font-bold text-[#e8a020]">📱 Cuentas Oficiales RestoMaster:</p>
                                <p>• Bancolombia Ahorros: <span class="font-mono font-bold text-white">102-948572-11</span></p>
                                <p>• Nequi / Dale: <span class="font-mono font-bold text-white">300 123 4567</span></p>
                                <p class="text-[11px] text-[#7a5a52]">Al confirmar, podrás enviar el comprobante directamente por WhatsApp con tu número de orden.</p>
                            </div>
                        @elseif ($metodoPago === 'efectivo')
                            <div class="p-4 rounded-2xl bg-[#261a15] border border-[#432f26] text-xs text-[#c4a89e] space-y-2">
                                <label class="block text-[11px] font-bold text-[#c4a89e]">¿Con cuánto pagarás? (Para llevarte el cambio exacto):</label>
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="text" 
                                        inputmode="decimal" 
                                        data-miles data-decimales="0"
                                        wire:model="pagaCon" 
                                        placeholder="Ej. 100000" 
                                        class="w-full px-3.5 py-2 rounded-xl border border-[#432f26] bg-[#1e1410] text-xs text-white font-mono outline-none"
                                    />
                                    <button type="button" wire:click="$set('pagaCon', '50000')" class="px-3 py-2 bg-[#1e1410] hover:bg-[#38271f] rounded-xl text-xs font-bold text-white border border-[#432f26]">$50k</button>
                                    <button type="button" wire:click="$set('pagaCon', '100000')" class="px-3 py-2 bg-[#1e1410] hover:bg-[#38271f] rounded-xl text-xs font-bold text-white border border-[#432f26]">$100k</button>
                                </div>
                            </div>
                        @else
                            <div class="p-4 rounded-2xl bg-[#261a15] border border-[#432f26] text-xs text-[#c4a89e]">
                                <p class="font-bold text-sky-400">💳 Pago con Tarjeta en Puerta:</p>
                                <p class="text-[11px] text-[#7a5a52] mt-0.5">El domiciliario llevará un datáfono inalámbrico para tarjeta débito o crédito (Visa, Mastercard, Amex).</p>
                            </div>
                        @endif
                    </div>

                    <!-- Order Total Recap -->
                    <div class="p-4 rounded-2xl bg-[#261a15] border border-[#432f26] flex items-center justify-between text-xs">
                        <div>
                            <span class="text-[#c4a89e] block font-mono">Total con domicilio incluido:</span>
                            <span class="text-[11px] text-[#7a5a52] font-mono">({{ $totalItems }} platos + $8.000 flete)</span>
                        </div>
                        <span class="text-xl font-black text-[#e0442e] font-mono">
                            $ {{ number_format($totalGeneral, 0, ',', '.') }} COP
                        </span>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2 flex items-center gap-3">
                        <button 
                            type="button" 
                            wire:click="cerrarCheckout" 
                            class="w-1/3 py-3.5 rounded-2xl bg-[#261a15] hover:bg-[#38271f] text-[#c4a89e] hover:text-white text-xs font-bold transition-all border border-[#432f26]"
                        >
                            Volver
                        </button>
                        <button 
                            type="submit" 
                            wire:loading.attr="disabled" 
                            class="w-2/3 py-3.5 px-4 rounded-2xl bg-[#e0442e] hover:bg-[#b8301d] text-white font-black text-xs shadow-xl shadow-[#e0442e]/30 flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="enviarPedidoDelivery" class="flex items-center gap-1.5">
                                <span>Confirmar y Enviar Pedido</span>
                                <span class="material-symbols-outlined text-[16px]">check</span>
                            </span>
                            <span wire:loading wire:target="enviarPedidoDelivery" class="flex items-center gap-1.5">
                                <span class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                <span>Registrando orden...</span>
                            </span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    @endif

</div>
