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

        // C1 Compliance: Recalcular subtotal tomando SIEMPRE los precios frescos de la base de datos
        $subtotal = 0.0;
        $itemsProcesados = [];

        foreach ($this->carrito as $item) {
            $producto = Producto::where('activo', true)->findOrFail($item['producto_id']);
            $cantidad = max(1, (int) $item['cantidad']);
            $precioUnitario = (float) $producto->precio;
            $itemSubtotal = $precioUnitario * $cantidad;

            $itemsProcesados[] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $itemSubtotal,
                'area_cocina' => $producto->area_cocina ?? 'sushi',
            ];

            $subtotal += $itemSubtotal;
        }

        $costoEnvioOficial = (float) app(ConfiguracionService::class)->obtener('general', 'costo_envio_base', 8000.0);
        $total = $subtotal + $costoEnvioOficial;

        $detallePago = match ($this->metodoPago) {
            'nequi_bancolombia' => 'Pago por Transferencia Nequi/Bancolombia.',
            'efectivo' => ! empty($this->pagaCon) ? "Efectivo contra entrega. Paga con: $ " . number_format((float) $this->pagaCon, 0, ',', '.') . " COP." : 'Efectivo contra entrega (Monto exacto).',
            'datafono' => 'Llevar datáfono contra entrega (Tarjeta Débito/Crédito).',
        };

        $direccionCompleta = trim($this->direccionDelivery . ($this->referenciaDireccion ? ' (Ref: ' . $this->referenciaDireccion . ')' : ''));
        $notasFinales = trim($this->notas . ' | ' . $detallePago);

        $codigo = 'DLV-' . strtoupper(substr(uniqid(), -5));

        $pedido = DB::transaction(function () use ($codigo, $subtotal, $total, $costoEnvioOficial, $itemsProcesados, $direccionCompleta, $notasFinales) {
            $sucursalId = Sucursal::where('activa', true)->value('id') ?? Sucursal::value('id') ?? 1;

            $pedido = Pedido::create([
                'codigo' => $codigo,
                'tipo' => 'delivery',
                'estado' => 'creado',
                'sucursal_id' => $sucursalId,
                'estado_delivery' => 'pendiente',
                'canal_origen' => 'web_delivery',
                'nombre_cliente' => $this->nombreCliente,
                'telefono_cliente' => $this->telefonoCliente,
                'direccion_delivery' => $direccionCompleta,
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
        $todasCategorias = Categoria::where('activo', true)->orderBy('orden')->get(['id', 'nombre', 'slug', 'icono']);

        $subtotal = 0.0;
        $totalItems = 0;
        foreach ($this->carrito as $item) {
            $subtotal += $item['subtotal'];
            $totalItems += $item['cantidad'];
        }

        return [
            'categorias' => $categorias,
            'todasCategorias' => $todasCategorias,
            'subtotal' => $subtotal,
            'totalItems' => $totalItems,
            'totalGeneral' => $subtotal > 0 ? $subtotal + $this->costoEnvio : 0,
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10">

    <!-- ============================================================= -->
    <!-- ESTADO 1: CONFIRMACIÓN EXITOSA DEL PEDIDO                     -->
    <!-- ============================================================= -->
    @if ($pedidoExitoso && $pedidoCreado)
        <div class="max-w-2xl mx-auto my-8 p-6 sm:p-10 rounded-3xl bg-white border border-stone-200 shadow-2xl text-center space-y-6 animate-fade-in">
            <!-- Animated Success Icon -->
            <div class="w-20 h-20 mx-auto rounded-3xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-xl shadow-emerald-500/10">
                <span class="material-symbols-outlined text-4xl">check_circle</span>
            </div>

            <div class="space-y-2">
                <span class="px-3 py-1 rounded-full bg-emerald-500/15 text-emerald-400 text-xs font-black uppercase tracking-wider border border-emerald-500/30">
                    ¡Pedido Recibido en Cocina!
                </span>
                <h1 class="text-2xl sm:text-3xl font-black text-stone-900 tracking-tight">
                    Tu pedido ya está en marcha
                </h1>
                <p class="text-xs sm:text-sm text-stone-500 max-w-md mx-auto">
                    Hemos registrado tu orden en nuestro sistema de cocina y despacho. Nuestro asesor confirmará los detalles de entrega.
                </p>
            </div>

            <!-- Tracking Code Box -->
            <div class="p-5 rounded-2xl bg-stone-50 border border-stone-200 space-y-1">
                <span class="text-[11px] font-bold uppercase tracking-widest text-stone-500">Número de Orden Oficial</span>
                <div class="text-3xl sm:text-4xl font-black text-[#ff5436] tracking-tight font-mono">
                    #{{ $pedidoCreado->codigo }}
                </div>
                <p class="text-[11px] text-stone-500">Guarda este código para verificar tu entrega y pago.</p>
            </div>

            <!-- Order Summary Card -->
            <div class="p-5 rounded-2xl bg-stone-50 border border-stone-200 text-left space-y-3 text-xs">
                <div class="flex justify-between items-center pb-2 border-b border-stone-200">
                    <span class="text-stone-500">Cliente:</span>
                    <span class="font-bold text-stone-900">{{ $pedidoCreado->nombre_cliente }} ({{ $pedidoCreado->telefono_cliente }})</span>
                </div>
                <div class="flex justify-between items-center pb-2 border-b border-stone-200">
                    <span class="text-stone-500">Dirección:</span>
                    <span class="font-bold text-stone-900 text-right max-w-xs">{{ $pedidoCreado->direccion_delivery }}</span>
                </div>
                <div class="flex justify-between items-center pb-2 border-b border-stone-200">
                    <span class="text-stone-500">Método de Pago:</span>
                    <span class="font-bold text-amber-400 uppercase">{{ str_replace('_', ' ', $pedidoCreado->metodo_pago) }}</span>
                </div>
                <div class="flex justify-between items-center pt-1 font-bold text-sm">
                    <span class="text-stone-900">Total a Pagar:</span>
                    <span class="text-[#ff5436] font-mono text-base">$ {{ number_format($pedidoCreado->total, 0, ',', '.') }} COP</span>
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
                    class="w-full py-3.5 px-6 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm shadow-xl shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all"
                >
                    <span class="material-symbols-outlined text-[20px]">chat</span>
                    <span>Enviar Soporte o Chatear por WhatsApp</span>
                </a>

                <button 
                    type="button" 
                    wire:click="nuevoPedido" 
                    class="w-full py-3 rounded-2xl bg-stone-100 hover:bg-stone-200 text-stone-600 hover:text-stone-900 font-bold text-xs border border-stone-200 transition-all"
                >
                    Hacer otro pedido
                </button>
            </div>
        </div>

    <!-- ============================================================= -->
    <!-- ESTADO 2: CATÁLOGO INTERACTIVO Y BOLSA DE COMPRAS             -->
    <!-- ============================================================= -->
    @else
        <!-- Hero Header -->
        <div class="mb-8 p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-stone-50 via-white to-stone-50 border border-stone-200 relative overflow-hidden flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="space-y-2 text-center sm:text-left">
                <span class="px-3 py-1 rounded-full bg-[#ff5436]/15 text-[#ff5436] text-[11px] font-black uppercase tracking-wider border border-[#ff5436]/30">
                    🛵 Pedidos Online · Despacho Inmediato
                </span>
                <h1 class="text-2xl sm:text-3xl font-black text-stone-900 tracking-tight">
                    Delivery Gastronómico RestoMaster
                </h1>
                <p class="text-xs sm:text-sm text-stone-600 max-w-xl leading-relaxed">
                    Nuestra carta completa a tu puerta: cortes a la parrilla, pastas artesanales, hamburguesas gourmet y entradas de autor empacadas para conservar temperatura y sabor.
                </p>
            </div>

            <!-- Quick Delivery Badges -->
            <div class="flex items-center gap-4 bg-white p-3.5 rounded-2xl border border-stone-200 shrink-0 text-xs">
                <div class="flex items-center gap-2 text-stone-600 font-bold">
                    <span class="material-symbols-outlined text-amber-400 text-[20px]">schedule</span>
                    <span>35-45 min</span>
                </div>
                <div class="w-px h-6 bg-stone-200"></div>
                <div class="flex items-center gap-2 text-stone-600 font-bold">
                    <span class="material-symbols-outlined text-emerald-400 text-[20px]">local_shipping</span>
                    <span>$ 8.000 COP</span>
                </div>
            </div>
        </div>

        <!-- Sticky Controls Bar: Categories & Search -->
        <div class="sticky top-16 sm:top-20 z-30 bg-[#fafaf9]/95 backdrop-blur-md py-3 mb-6 border-b border-stone-200 space-y-3">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                
                <!-- Category Pills Carousel -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full pb-1 no-scrollbar">
                    <button 
                        type="button" 
                        wire:click="seleccionarCategoria('todas')" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-black transition-all shrink-0 {{ $categoriaSeleccionada === 'todas' ? 'bg-[#ff5436] text-stone-900 shadow-md' : 'bg-stone-50 text-stone-500 hover:text-stone-900 border border-stone-200' }}"
                    >
                        🔥 Todos los Platos
                    </button>
                    @foreach ($todasCategorias as $catPill)
                        <button 
                            type="button" 
                            wire:click="seleccionarCategoria('{{ $catPill->slug }}')" 
                            class="px-3.5 py-1.5 rounded-xl text-xs font-black transition-all shrink-0 flex items-center gap-1.5 {{ $categoriaSeleccionada === $catPill->slug ? 'bg-[#ff5436] text-stone-900 shadow-md' : 'bg-stone-50 text-stone-500 hover:text-stone-900 border border-stone-200' }}"
                        >
                            <span>{{ $catPill->icono ?? '🍣' }}</span>
                            <span>{{ $catPill->nombre }}</span>
                        </button>
                    @endforeach
                </div>

                <!-- Live Search Bar -->
                <div class="relative w-full sm:w-72 shrink-0">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-stone-500">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                    </span>
                    <input 
                        type="text" 
                        wire:model.live.debounce.250ms="busqueda" 
                        placeholder="Buscar roll, salmón, atún..." 
                        class="w-full pl-9 pr-3 py-1.5 rounded-xl border border-stone-200 bg-stone-50 text-xs text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Main Layout Grid: Products (Left) + Cart Sidebar (Right) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Products Catalog Column (8 cols) -->
            <div class="lg:col-span-8 space-y-10">
                @forelse ($categorias as $cat)
                    @if ($cat->productos->isNotEmpty())
                        <div class="space-y-4">
                            <!-- Category Section Header -->
                            <div class="flex items-center justify-between border-b border-stone-200 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">{{ $cat->icono ?? '🍱' }}</span>
                                    <h2 class="text-lg font-black text-stone-900 tracking-tight">{{ $cat->nombre }}</h2>
                                    <span class="px-2 py-0.5 rounded-full bg-stone-200 text-[10px] font-bold text-stone-500">
                                        {{ $cat->productos->count() }}
                                    </span>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-stone-500">RestoMaster Gourmet</span>
                            </div>

                            <!-- Product Cards Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach ($cat->productos as $producto)
                                    @php
                                        $enCarrito = isset($carrito[$producto->id]);
                                        $cant = $enCarrito ? $carrito[$producto->id]['cantidad'] : 0;
                                    @endphp
                                    <div class="p-4 rounded-2xl bg-white border {{ $enCarrito ? 'border-[#ff5436]/60 shadow-lg shadow-[#ff5436]/5' : 'border-stone-200' }} flex flex-col justify-between transition-all hover:border-stone-300">
                                        <div class="space-y-2">
                                            <div class="flex items-start justify-between gap-2">
                                                <h3 class="font-extrabold text-stone-900 text-sm leading-snug">{{ $producto->nombre }}</h3>
                                                <span class="px-2 py-0.5 rounded-md bg-stone-100 border border-stone-200 text-[9px] font-bold text-stone-500 uppercase tracking-wider shrink-0">
                                                    {{ in_array($producto->area_cocina, ['barra', 'bebidas']) ? 'Barra' : (in_array($producto->area_cocina, ['fria', 'sushi']) ? 'Cocina Fría' : 'Cocina / Parrilla') }}
                                                </span>
                                            </div>

                                            <p class="text-xs text-stone-500 leading-relaxed">
                                                {{ $producto->descripcion ?? 'Elaborado artesanalmente con ingredientes frescos y altos estándares de calidad.' }}
                                            </p>
                                        </div>

                                        <div class="pt-4 mt-3 border-t border-stone-100 flex items-center justify-between gap-3">
                                            <!-- Price -->
                                            <div>
                                                <span class="text-[10px] text-stone-500 uppercase font-bold block">Precio</span>
                                                <span class="text-sm sm:text-base font-black text-[#ff5436] font-mono">
                                                    $ {{ number_format($producto->precio, 0, ',', '.') }}
                                                </span>
                                            </div>

                                            <!-- Add / Quantity Controls -->
                                            <div>
                                                @if (! $enCarrito)
                                                    <button 
                                                        type="button" 
                                                        wire:click="agregarAlCarrito({{ $producto->id }})" 
                                                        class="px-3.5 py-1.5 rounded-xl bg-[#ff5436] hover:bg-[#e0381d] text-stone-900 text-xs font-black shadow-md flex items-center gap-1.5 transition-all cursor-pointer"
                                                    >
                                                        <span class="material-symbols-outlined text-[16px]">add</span>
                                                        <span>Agregar</span>
                                                    </button>
                                                @else
                                                    <div class="flex items-center gap-1.5 bg-stone-100 border border-stone-300 p-1 rounded-xl">
                                                        <button 
                                                            type="button" 
                                                            wire:click="modificarCantidad({{ $producto->id }}, -1)" 
                                                            class="w-7 h-7 rounded-lg bg-stone-200 hover:bg-stone-700 text-stone-900 flex items-center justify-center font-bold text-sm cursor-pointer transition-colors"
                                                        >
                                                            −
                                                        </button>
                                                        <span class="w-6 text-center font-black text-xs text-stone-900 font-mono">{{ $cant }}</span>
                                                        <button 
                                                            type="button" 
                                                            wire:click="modificarCantidad({{ $producto->id }}, 1)" 
                                                            class="w-7 h-7 rounded-lg bg-[#ff5436] hover:bg-[#e0381d] text-stone-900 flex items-center justify-center font-bold text-sm cursor-pointer transition-colors"
                                                        >
                                                            +
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="text-center py-12 p-8 rounded-3xl bg-white border border-stone-200 text-stone-500 space-y-2">
                        <span class="text-3xl">🔍</span>
                        <p class="font-bold text-stone-900">No se encontraron platos con "{{ $busqueda }}"</p>
                        <p class="text-xs">Prueba con otra palabra o selecciona "Todos los Platos".</p>
                    </div>
                @endforelse
            </div>

            <!-- Cart Summary Column (4 cols, Sticky Desktop) -->
            <div class="hidden lg:block lg:col-span-4 sticky top-24">
                <div class="p-6 rounded-3xl bg-white border border-stone-200 shadow-2xl space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🛍️</span>
                            <h3 class="font-black text-stone-900 text-base">Bolsa de Pedido</h3>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full bg-[#ff5436]/20 text-[#ff5436] text-xs font-black font-mono">
                            {{ $totalItems }} {{ $totalItems === 1 ? 'item' : 'items' }}
                        </span>
                    </div>

                    @if (empty($carrito))
                        <div class="py-10 text-center text-stone-500 space-y-2">
                            <span class="material-symbols-outlined text-4xl text-stone-600">shopping_bag</span>
                            <p class="text-xs font-bold text-stone-500">Tu bolsa está vacía</p>
                            <p class="text-[11px] text-stone-500">Agrega rolls, nigiris o entradas para pedir a domicilio.</p>
                        </div>
                    @else
                        <!-- Cart Items List -->
                        <div class="max-h-72 overflow-y-auto space-y-2.5 pr-1">
                            @foreach ($carrito as $item)
                                <div class="p-2.5 rounded-xl bg-stone-50 border border-stone-200 flex items-center justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-bold text-stone-900 text-xs truncate">{{ $item['nombre'] }}</h4>
                                        <span class="text-[11px] text-[#ff5436] font-mono">$ {{ number_format($item['precio'], 0, ',', '.') }}</span>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button 
                                            type="button" 
                                            wire:click="modificarCantidad({{ $item['producto_id'] }}, -1)" 
                                            class="w-6 h-6 rounded-md bg-stone-200 hover:bg-stone-700 text-stone-600 flex items-center justify-center text-xs font-bold"
                                        >
                                            −
                                        </button>
                                        <span class="w-5 text-center font-bold text-xs text-stone-900 font-mono">{{ $item['cantidad'] }}</span>
                                        <button 
                                            type="button" 
                                            wire:click="modificarCantidad({{ $item['producto_id'] }}, 1)" 
                                            class="w-6 h-6 rounded-md bg-[#ff5436] hover:bg-[#e0381d] text-white flex items-center justify-center text-xs font-bold"
                                        >
                                            +
                                        </button>
                                        <button 
                                            type="button" 
                                            wire:click="removerDelCarrito({{ $item['producto_id'] }})" 
                                            class="p-1 text-stone-500 hover:text-rose-400 transition-colors ml-1"
                                            title="Quitar"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Price Breakdown -->
                        <div class="pt-4 border-t border-stone-200 space-y-2 text-xs">
                            <div class="flex justify-between text-stone-500">
                                <span>Subtotal platos:</span>
                                <span class="font-mono text-stone-900">$ {{ number_format($subtotal, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-stone-500">
                                <span>Costo de envío (Provenza):</span>
                                <span class="font-mono text-stone-900">$ {{ number_format($costoEnvio, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between font-black text-sm pt-2 border-t border-stone-200">
                                <span class="text-stone-900">Total a Pagar:</span>
                                <span class="text-[#ff5436] font-mono text-base">$ {{ number_format($totalGeneral, 0, ',', '.') }} COP</span>
                            </div>
                        </div>

                        <!-- Checkout Trigger Button -->
                        <button 
                            type="button" 
                            wire:click="abrirCheckout" 
                            class="w-full py-3.5 px-4 rounded-2xl bg-[#ff5436] hover:bg-[#e0381d] text-white font-black text-sm shadow-xl shadow-[#ff5436]/25 flex items-center justify-center gap-2 transition-all cursor-pointer"
                        >
                            <span>Tramitar Pedido</span>
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    @endif
                </div>
            </div>

        </div>

        <!-- Floating Bottom Bar for Mobile Devices -->
        @if (! empty($carrito))
            <div class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-stone-200 p-3 flex items-center justify-between shadow-2xl">
                <div>
                    <span class="text-[10px] text-stone-500 uppercase font-bold block">{{ $totalItems }} platos en bolsa</span>
                    <span class="text-base font-black text-[#ff5436] font-mono">$ {{ number_format($totalGeneral, 0, ',', '.') }} COP</span>
                </div>
                <button 
                    type="button" 
                    wire:click="abrirCheckout" 
                    class="py-2.5 px-5 rounded-xl bg-[#ff5436] text-stone-900 font-black text-xs shadow-lg flex items-center gap-1.5"
                >
                    <span>Ver Pedido</span>
                    <span class="material-symbols-outlined text-[18px]">shopping_cart_checkout</span>
                </button>
            </div>
        @endif
    @endif

    <!-- ============================================================= -->
    <!-- MODAL / DRAWER DE TRAMITACIÓN (CHECKOUT)                       -->
    <!-- ============================================================= -->
    @if ($mostrarCheckout)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/80 backdrop-blur-sm animate-fade-in overflow-y-auto">
            <div class="w-full max-w-xl bg-white border border-stone-200 rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6 relative my-auto">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🛵</span>
                        <h3 class="font-black text-stone-900 text-lg">Tramitar Pedido a Domicilio</h3>
                    </div>
                    <button 
                        type="button" 
                        wire:click="cerrarCheckout" 
                        class="w-8 h-8 rounded-full bg-stone-100 hover:bg-stone-200 text-stone-500 hover:text-stone-900 flex items-center justify-center"
                    >
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <!-- Checkout Form -->
                <form wire:submit="enviarPedidoDelivery" class="space-y-4">
                    
                    <!-- Customer Name & Phone -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase tracking-wider mb-1">
                                Tu Nombre Completo *
                            </label>
                            <input 
                                type="text" 
                                wire:model="nombreCliente" 
                                placeholder="Ej. Carlos Mendoza" 
                                class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 bg-stone-50 text-xs text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none"
                            />
                            @error('nombreCliente')
                                <p class="text-[11px] text-rose-400 mt-1 font-bold">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase tracking-wider mb-1">
                                Teléfono / WhatsApp *
                            </label>
                            <input 
                                type="tel" 
                                wire:model="telefonoCliente" 
                                placeholder="Ej. 300 123 4567" 
                                class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 bg-stone-50 text-xs text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none font-mono"
                            />
                            @error('telefonoCliente')
                                <p class="text-[11px] text-rose-400 mt-1 font-bold">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Delivery Address & References -->
                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase tracking-wider mb-1">
                            Dirección de Entrega Exacta *
                        </label>
                        <input 
                            type="text" 
                            wire:model="direccionDelivery" 
                            placeholder="Calle, Carrera, Edificio, Apto..." 
                            class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 bg-stone-50 text-xs text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none"
                        />
                        @error('direccionDelivery')
                            <p class="text-[11px] text-rose-400 mt-1 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase tracking-wider mb-1">
                                Referencias (Opcional)
                            </label>
                            <input 
                                type="text" 
                                wire:model="referenciaDireccion" 
                                placeholder="Ej. Torre 2, Apto 504" 
                                class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 bg-stone-50 text-xs text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase tracking-wider mb-1">
                                Notas para Cocina (Opcional)
                            </label>
                            <input 
                                type="text" 
                                wire:model="notas" 
                                placeholder="Ej. Sin wasabi, salsa de soya extra" 
                                class="w-full px-3.5 py-2.5 rounded-xl border border-stone-200 bg-stone-50 text-xs text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none"
                            />
                        </div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div class="space-y-2 pt-2 border-t border-stone-200">
                        <label class="block text-xs font-bold text-stone-600 uppercase tracking-wider">
                            Método de Pago *
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="p-3 rounded-xl border text-center cursor-pointer transition-all {{ $metodoPago === 'nequi_bancolombia' ? 'bg-[#ff5436]/15 border-[#ff5436] text-stone-900' : 'bg-stone-100 border-stone-200 text-stone-500 hover:border-stone-300' }}">
                                <input type="radio" wire:model.live="metodoPago" value="nequi_bancolombia" class="hidden" />
                                <span class="material-symbols-outlined text-[20px] block mx-auto mb-1">qr_code_2</span>
                                <span class="text-[11px] font-bold block">Nequi / Bancolombia</span>
                            </label>

                            <label class="p-3 rounded-xl border text-center cursor-pointer transition-all {{ $metodoPago === 'efectivo' ? 'bg-[#ff5436]/15 border-[#ff5436] text-stone-900' : 'bg-stone-100 border-stone-200 text-stone-500 hover:border-stone-300' }}">
                                <input type="radio" wire:model.live="metodoPago" value="efectivo" class="hidden" />
                                <span class="material-symbols-outlined text-[20px] block mx-auto mb-1">payments</span>
                                <span class="text-[11px] font-bold block">Efectivo</span>
                            </label>

                            <label class="p-3 rounded-xl border text-center cursor-pointer transition-all {{ $metodoPago === 'datafono' ? 'bg-[#ff5436]/15 border-[#ff5436] text-stone-900' : 'bg-stone-100 border-stone-200 text-stone-500 hover:border-stone-300' }}">
                                <input type="radio" wire:model.live="metodoPago" value="datafono" class="hidden" />
                                <span class="material-symbols-outlined text-[20px] block mx-auto mb-1">credit_card</span>
                                <span class="text-[11px] font-bold block">Datáfono</span>
                            </label>
                        </div>

                        <!-- Payment Details Context Box -->
                        @if ($metodoPago === 'nequi_bancolombia')
                            <div class="p-3 rounded-xl bg-stone-50 border border-stone-200 text-xs text-stone-600 space-y-1">
                                <p class="font-bold text-amber-400">📱 Cuentas Oficiales:</p>
                                <p>• Bancolombia Ahorros: <span class="font-mono font-bold text-stone-900">102-948572-11</span></p>
                                <p>• Nequi / Dale: <span class="font-mono font-bold text-stone-900">300 123 4567</span></p>
                                <p class="text-[10px] text-stone-500">Al confirmar, podrás enviar el comprobante por WhatsApp con tu número de orden.</p>
                            </div>
                        @elseif ($metodoPago === 'efectivo')
                            <div class="p-3 rounded-xl bg-stone-50 border border-stone-200 text-xs text-stone-600 space-y-2">
                                <label class="block text-[11px] font-bold text-stone-500">¿Con cuánto dinero pagarás? (Para llevarte el cambio exacto):</label>
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="number" 
                                        wire:model="pagaCon" 
                                        placeholder="Ej. 100000" 
                                        class="w-full px-3 py-1.5 rounded-lg border border-stone-200 bg-stone-50 text-xs text-stone-900 font-mono"
                                    />
                                    <button type="button" wire:click="$set('pagaCon', '50000')" class="px-2 py-1 bg-stone-200 hover:bg-stone-700 rounded text-[10px] font-bold">$50k</button>
                                    <button type="button" wire:click="$set('pagaCon', '100000')" class="px-2 py-1 bg-stone-200 hover:bg-stone-700 rounded text-[10px] font-bold">$100k</button>
                                </div>
                            </div>
                        @else
                            <div class="p-3 rounded-xl bg-stone-50 border border-stone-200 text-xs text-stone-300">
                                <p class="font-bold text-sky-400">💳 Pago con Tarjeta:</p>
                                <p class="text-[11px] text-stone-500">El domiciliario llevará un datáfono inalámbrico para tarjeta débito o crédito.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Order Total Recap -->
                    <div class="p-3 rounded-xl bg-stone-50 border border-stone-200 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-stone-500 block">Total con domicilio incluido:</span>
                            <span class="text-xs text-stone-500 font-mono">({{ $totalItems }} platos + $8.000 flete)</span>
                        </div>
                        <span class="text-lg font-black text-[#ff5436] font-mono">
                            $ {{ number_format($totalGeneral, 0, ',', '.') }} COP
                        </span>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2 flex items-center gap-3">
                        <button 
                            type="button" 
                            wire:click="cerrarCheckout" 
                            class="w-1/3 py-3 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-500 hover:text-stone-900 text-xs font-bold transition-all"
                        >
                            Volver
                        </button>
                        <button 
                            type="submit" 
                            wire:loading.attr="disabled" 
                            class="w-2/3 py-3 px-4 rounded-xl bg-[#ff5436] hover:bg-[#e0381d] text-white font-black text-xs shadow-lg shadow-[#ff5436]/25 flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50"
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
