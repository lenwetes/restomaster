<?php

use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\PedidoService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.menu-cliente')] class extends Component
{
    public string $numero = '';
    public ?int $mesaId = null;
    public ?string $mesaZona = '';
    public ?int $categoriaSeleccionada = null;
    public string $busqueda = '';
    public array $carrito = [];
    public string $nombreCliente = '';
    public string $notasGenerales = '';
    public bool $mostrarCarrito = false;
    public ?int $pedidoId = null;
    public bool $modoAgregarMas = false;
    public ?string $mensajeFlash = null;
    public ?string $tipoFlash = 'success';

    public function mount(string $numero): void
    {
        $this->numero = $numero;
        $mesa = Mesa::where('numero', $this->numero)->first();

        if ($mesa) {
            $this->mesaId = $mesa->id;
            $this->mesaZona = $mesa->zona;

            // Verificar si hay un pedido activo registrado para esta mesa en la sesión
            $sesionPedidoId = session()->get("pedido_qr_{$mesa->id}");
            if ($sesionPedidoId) {
                $pedido = Pedido::find($sesionPedidoId);
                if ($pedido && in_array($pedido->estado, ['solicitado_qr', 'en_cocina', 'en_proceso', 'listo', 'entregado'])) {
                    $this->pedidoId = $pedido->id;
                    $this->nombreCliente = $pedido->nombre_cliente ?? '';
                }
            }
        }
    }

    public function agregarProducto(int $productoId): void
    {
        $producto = Producto::where('activo', true)->findOrFail($productoId);

        if (isset($this->carrito[$productoId])) {
            $this->carrito[$productoId]['cantidad']++;
        } else {
            $this->carrito[$productoId] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'precio' => (float)$producto->precio,
                'cantidad' => 1,
                'notas' => '',
                'area_cocina' => $producto->area_cocina ?? 'caliente',
            ];
        }

        $this->dispatch('item-agregado', ['nombre' => $producto->nombre]);
    }

    public function incrementar(int $productoId): void
    {
        if (isset($this->carrito[$productoId])) {
            $this->carrito[$productoId]['cantidad']++;
        }
    }

    public function decrementar(int $productoId): void
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

    public function getTotalProperty(): float
    {
        $total = 0;
        foreach ($this->carrito as $item) {
            $total += $item['precio'] * $item['cantidad'];
        }
        return $total;
    }

    public function getCantidadTotalProperty(): int
    {
        $qty = 0;
        foreach ($this->carrito as $item) {
            $qty += $item['cantidad'];
        }
        return $qty;
    }

    public function enviarPedido(): void
    {
        if (empty($this->carrito)) {
            $this->mensajeFlash = 'Agrega al menos un plato a tu comanda.';
            $this->tipoFlash = 'error';
            return;
        }

        if (!$this->mesaId) {
            $this->mensajeFlash = 'Mesa no identificada.';
            $this->tipoFlash = 'error';
            return;
        }

        $mesa = Mesa::findOrFail($this->mesaId);
        $pedidoService = app(PedidoService::class);

        try {
            $pedido = $pedidoService->crearPedidoDesdeQr(
                $mesa,
                array_values($this->carrito),
                $this->nombreCliente,
                $this->notasGenerales
            );

            $this->pedidoId = $pedido->id;
            session()->put("pedido_qr_{$mesa->id}", $pedido->id);

            $this->carrito = [];
            $this->mostrarCarrito = false;
            $this->modoAgregarMas = false;
            $this->mensajeFlash = '¡Tu pedido ha sido enviado con éxito! El personal ha sido notificado.';
            $this->tipoFlash = 'success';
        } catch (\Throwable $e) {
            $this->mensajeFlash = 'Error al enviar pedido: ' . $e->getMessage();
            $this->tipoFlash = 'error';
        }
    }

    public function activarModoAgregarMas(): void
    {
        $this->modoAgregarMas = true;
    }

    public function volverASeguimiento(): void
    {
        $this->modoAgregarMas = false;
    }

    public function refrescarEstado(): void
    {
        // Polling hook para refrescar pedido en vivo
    }

    public function with(): array
    {
        $mesa = $this->mesaId ? Mesa::find($this->mesaId) : null;
        $pedidoActual = $this->pedidoId ? Pedido::with(['items', 'usuario', 'mesa'])->find($this->pedidoId) : null;

        $esModoSeguimiento = $pedidoActual && ! $this->modoAgregarMas;

        $categorias = $esModoSeguimiento
            ? collect()
            : Categoria::where('activo', true)->orderBy('orden')->get();

        $productos = collect();
        if (! $esModoSeguimiento) {
            $query = Producto::with('categoria')->where('activo', true);

            if ($this->categoriaSeleccionada) {
                $query->where('categoria_id', $this->categoriaSeleccionada);
            }

            if (! empty(trim($this->busqueda))) {
                $term = '%' . trim($this->busqueda) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('nombre', 'ilike', $term)
                        ->orWhere('descripcion', 'ilike', $term);
                });
            }

            $productos = $query->orderBy('categoria_id')->orderBy('nombre')->get();
        }

        return [
            'mesa' => $mesa,
            'categorias' => $categorias,
            'productos' => $productos,
            'pedidoActual' => $pedidoActual,
        ];
    }
}; ?>

<div class="flex-1 flex flex-col">
    @if (!$mesa)
        <!-- Pantalla de Error: Mesa Inválida -->
        <div class="flex-1 flex flex-col items-center justify-center p-8 text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-red-100 text-primary flex items-center justify-center shadow-inner">
                <span class="material-symbols-outlined text-3xl">table_restaurant</span>
            </div>
            <h1 class="text-xl font-extrabold text-stone-900">Mesa No Encontrada</h1>
            <p class="text-sm text-stone-500 max-w-xs">
                El código QR escaneado no corresponde a ninguna mesa activa del restaurante. Por favor solicita asistencia al personal.
            </p>
            <a href="/" class="px-5 py-2.5 rounded-2xl bg-stone-900 text-white text-xs font-bold shadow-md hover:bg-stone-800">
                Ir a RestoMaster
            </a>
        </div>
    @elseif ($pedidoActual && !$modoAgregarMas)
        <!-- ========================================== -->
        <!-- PANTALLA 1: SEGUIMIENTO EN VIVO DEL PEDIDO -->
        <!-- ========================================== -->
        <div class="flex-1 flex flex-col p-4 sm:p-6 space-y-5" wire:poll.15s="refrescarEstado">
            <!-- Header Mesa & Restaurante -->
            <div class="flex items-center justify-between bg-stone-900 text-white p-4 rounded-3xl shadow-lg">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-primary flex items-center justify-center font-black text-white text-base shadow-md">
                        🍽️
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-stone-400 uppercase tracking-widest block">RestoMaster Gourmet</span>
                        <h1 class="text-lg font-extrabold tracking-tight">Mesa #{{ $mesa->numero }}</h1>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full bg-stone-800 border border-stone-700 text-[11px] font-extrabold text-stone-300 capitalize">
                    Zona {{ $mesa->zona }}
                </span>
            </div>

            <!-- Banner Estado Principal -->
            <div class="p-5 rounded-3xl border shadow-sm space-y-3
                {{ $pedidoActual->estado === 'solicitado_qr' ? 'bg-amber-50/80 border-amber-200/60' : '' }}
                {{ in_array($pedidoActual->estado, ['en_cocina', 'en_proceso']) ? 'bg-orange-50/80 border-orange-200/60' : '' }}
                {{ $pedidoActual->estado === 'listo' ? 'bg-emerald-50/80 border-emerald-200/60' : '' }}
                {{ $pedidoActual->estado === 'entregado' ? 'bg-stone-50 border-stone-200' : '' }}
            ">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-wider font-mono text-stone-500">
                        Código: {{ $pedidoActual->codigo }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider
                        {{ $pedidoActual->estado === 'solicitado_qr' ? 'bg-amber-500 text-white' : '' }}
                        {{ in_array($pedidoActual->estado, ['en_cocina', 'en_proceso']) ? 'bg-primary text-white' : '' }}
                        {{ $pedidoActual->estado === 'listo' ? 'bg-emerald-600 text-white' : '' }}
                        {{ $pedidoActual->estado === 'entregado' ? 'bg-stone-800 text-white' : '' }}
                    ">
                        {{ $pedidoActual->estado === 'solicitado_qr' ? 'Solicitado' : '' }}
                        {{ in_array($pedidoActual->estado, ['en_cocina', 'en_proceso']) ? 'En Cocina' : '' }}
                        {{ $pedidoActual->estado === 'listo' ? 'Listo en Mesa' : '' }}
                        {{ $pedidoActual->estado === 'entregado' ? 'Servido' : '' }}
                    </span>
                </div>

                <!-- Stepper Visual Interactivo -->
                <div class="space-y-4 pt-2">
                    <!-- Paso 1: Solicitud recibida -->
                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-sm font-black shadow-sm shrink-0">
                            ✓
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-xs text-stone-900">1. Pedido Recibido</p>
                            <p class="text-[11px] text-stone-500">Orden registrada desde tu mesa.</p>
                        </div>
                    </div>

                    <!-- Paso 2: Asignación de Mesero -->
                    <div class="flex items-start gap-3">
                        @if ($pedidoActual->usuario_id)
                            <div class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-sm font-black shadow-sm shrink-0">
                                ✓
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-xs text-stone-900">2. Mesero Asignado</p>
                                <p class="text-[11px] text-emerald-700 font-extrabold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">person</span>
                                    Atendido por: {{ $pedidoActual->usuario?->name ?? 'Mesero en Sala' }}
                                </p>
                            </div>
                        @else
                            <div class="w-7 h-7 rounded-full bg-amber-400 text-amber-950 flex items-center justify-center text-xs font-black animate-pulse shrink-0">
                                2
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-xs text-amber-900">2. Esperando asignación de mesero</p>
                                <p class="text-[11px] text-amber-700">El personal de sala está confirmando tu mesa...</p>
                            </div>
                        @endif
                    </div>

                    <!-- Paso 3: Cocina / Preparación -->
                    <div class="flex items-start gap-3">
                        @if (in_array($pedidoActual->estado, ['en_cocina', 'en_proceso', 'listo', 'entregado']))
                            <div class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-black shadow-sm shrink-0">
                                🔥
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-xs text-stone-900">3. En Preparación en Cocina</p>
                                <p class="text-[11px] text-stone-500">Nuestros chefs preparan tus platos al momento.</p>
                            </div>
                        @else
                            <div class="w-7 h-7 rounded-full bg-stone-200 text-stone-400 flex items-center justify-center text-xs font-black shrink-0">
                                3
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-xs text-stone-400">3. Preparación en Cocina</p>
                                <p class="text-[11px] text-stone-400">Iniciará en cuanto el mesero confirme la mesa.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Paso 4: Listo / Servido -->
                    <div class="flex items-start gap-3">
                        @if (in_array($pedidoActual->estado, ['listo', 'entregado']))
                            <div class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-sm font-black shadow-sm shrink-0">
                                🍱
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-xs text-stone-900">4. ¡Listo en tu Mesa!</p>
                                <p class="text-[11px] text-emerald-700 font-bold">¡Tu pedido está listo o servido! ¡Buen provecho!</p>
                            </div>
                        @else
                            <div class="w-7 h-7 rounded-full bg-stone-200 text-stone-400 flex items-center justify-center text-xs font-black shrink-0">
                                4
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-xs text-stone-400">4. Entrega en Mesa</p>
                                <p class="text-[11px] text-stone-400">Tu mesero llevará la comanda directamente a tu mesa.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Resumen de Platos Solicitados -->
            <div class="bg-white rounded-3xl border border-stone-100 p-5 shadow-sm space-y-3">
                <h3 class="font-extrabold text-xs text-stone-900 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-primary">receipt_long</span>
                    Platos en este pedido ({{ $pedidoActual->items->count() }})
                </h3>

                <div class="divide-y divide-stone-100 text-xs">
                    @foreach ($pedidoActual->items as $item)
                        <div class="py-2.5 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-stone-900">{{ $item->cantidad }}x {{ $item->nombre_producto }}</span>
                                @if ($item->notas)
                                    <p class="text-[10px] text-stone-400 italic">Nota: {{ $item->notas }}</p>
                                @endif
                            </div>
                            <span class="font-mono font-bold text-stone-700">
                                ${{ number_format($item->subtotal, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="pt-3 border-t border-stone-100 flex items-center justify-between">
                    <span class="font-bold text-stone-500 text-xs">Total de la comanda:</span>
                    <span class="text-base font-mono font-extrabold text-primary">
                        ${{ number_format($pedidoActual->total, 0, ',', '.') }} COP
                    </span>
                </div>
            </div>

            <!-- Botón para Agregar Más Platos -->
            <button 
                wire:click="activarModoAgregarMas"
                class="w-full py-3.5 rounded-2xl bg-stone-900 text-white text-xs font-extrabold flex items-center justify-center gap-2 shadow-md hover:bg-stone-800 active:scale-95 transition cursor-pointer"
            >
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>Pedir Más Platos o Bebidas</span>
            </button>
        </div>
    @else
        <!-- ========================================== -->
        <!-- PANTALLA 2: EXPLORADOR DE CARTA & CARRITO  -->
        <!-- ========================================== -->
        <!-- Top Nav Bar Móvil -->
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-stone-100 px-4 py-3 shadow-xs">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-primary text-white flex items-center justify-center text-sm font-black shadow-xs">
                        🍽️
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-stone-400 uppercase tracking-wider block">RestoMaster</span>
                        <h2 class="text-xs font-extrabold text-stone-900 leading-none">Carta Digital</h2>
                    </div>
                </div>

                <!-- Table Pill Badge -->
                <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary">
                    <span class="material-symbols-outlined text-[14px]">table_restaurant</span>
                    <span class="text-xs font-black">Mesa #{{ $mesa->numero }}</span>
                </div>

                @if ($pedidoActual)
                    <button 
                        wire:click="volverASeguimiento"
                        class="p-1.5 rounded-full hover:bg-stone-100 text-stone-600 text-xs font-bold flex items-center gap-1"
                        title="Ver seguimiento"
                    >
                        <span class="material-symbols-outlined text-[18px]">fastfood</span>
                    </button>
                @endif
            </div>

            <!-- Search Bar -->
            <div class="mt-3 relative">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-stone-400 text-[18px]">search</span>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar rolls, entradas, bebidas..."
                    class="w-full pl-9 pr-4 py-2 rounded-2xl bg-stone-100 border-none text-xs text-stone-900 placeholder:text-stone-400 focus:ring-2 focus:ring-primary/40"
                >
            </div>

            <!-- Category Pills Bar (Horizontal Scroll) -->
            <div class="mt-2.5 flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar">
                <button 
                    wire:click="$set('categoriaSeleccionada', null)"
                    class="shrink-0 px-3 py-1 rounded-full text-[11px] font-extrabold transition cursor-pointer
                        {{ is_null($categoriaSeleccionada) ? 'bg-primary text-white shadow-xs' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}"
                >
                    🍣 Todos
                </button>
                @foreach ($categorias as $cat)
                    <button 
                        wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                        class="shrink-0 px-3 py-1 rounded-full text-[11px] font-extrabold transition cursor-pointer flex items-center gap-1
                            {{ $categoriaSeleccionada === $cat->id ? 'bg-primary text-white shadow-xs' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}"
                    >
                        @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                            <span class="material-symbols-outlined text-[14px]">{{ $cat->icono }}</span>
                        @else
                            <span>{{ $cat->icono ?? '🍽️' }}</span>
                        @endif
                        <span>{{ $cat->nombre }}</span>
                    </button>
                @endforeach
            </div>
        </header>

        <!-- Feedback Flash Banner -->
        @if ($mensajeFlash)
            <div class="mx-4 mt-3 p-3 rounded-2xl text-xs font-bold {{ $tipoFlash === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                {{ $mensajeFlash }}
            </div>
        @endif

        <!-- Product Cards Grid -->
        <main class="flex-1 p-4 space-y-3">
            @forelse ($productos as $producto)
                @php
                    $enCarrito = isset($carrito[$producto->id]);
                    $cantidad = $enCarrito ? $carrito[$producto->id]['cantidad'] : 0;
                @endphp
                <div class="p-4 rounded-3xl border border-stone-100 bg-white shadow-xs hover:shadow-md transition flex gap-3.5 items-start">
                    <!-- Image or Emoji Placeholder -->
                    <div class="w-20 h-20 rounded-2xl bg-stone-100 border border-stone-200/60 flex items-center justify-center text-3xl shrink-0 overflow-hidden shadow-inner">
                        @if ($producto->imagen)
                            <img src="{{ $producto->imagen }}" alt="{{ $producto->nombre }}" class="w-full h-full object-cover">
                        @else
                            @if(preg_match('/^[a-z0-9_]+$/', $producto->categoria?->icono ?? ''))
                                <span class="material-symbols-outlined text-3xl">{{ $producto->categoria->icono }}</span>
                            @else
                                <span>{{ $producto->categoria?->icono ?? '🍽️' }}</span>
                            @endif
                        @endif
                    </div>

                    <!-- Info & Action -->
                    <div class="flex-1 min-w-0 flex flex-col justify-between self-stretch">
                        <div>
                            <div class="flex items-start justify-between gap-1">
                                <h3 class="font-extrabold text-stone-900 text-xs leading-snug">
                                    {{ $producto->nombre }}
                                </h3>
                                <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-stone-100 text-stone-500 shrink-0">
                                    {{ in_array($producto->area_cocina, ['barra', 'bebidas']) ? 'Barra' : (in_array($producto->area_cocina, ['fria', 'sushi']) ? 'Cocina Fría' : 'Cocina / Parrilla') }}
                                </span>
                            </div>
                            @if ($producto->descripcion)
                                <p class="text-[11px] text-stone-500 line-clamp-2 mt-0.5 leading-tight">
                                    {{ $producto->descripcion }}
                                </p>
                            @endif
                        </div>

                        <!-- Price and Add Button -->
                        <div class="mt-2 flex items-center justify-between">
                            <span class="font-mono font-extrabold text-stone-900 text-xs sm:text-sm">
                                ${{ number_format($producto->precio, 0, ',', '.') }} <span class="text-[9px] text-stone-400 font-sans font-bold">COP</span>
                            </span>

                            @if ($enCarrito)
                                <!-- Stepper -->
                                <div class="flex items-center gap-1.5 bg-stone-100 rounded-xl p-1 border border-stone-200">
                                    <button 
                                        wire:click="decrementar({{ $producto->id }})"
                                        class="w-6 h-6 rounded-lg bg-white shadow-xs text-stone-700 flex items-center justify-center font-black text-xs hover:bg-stone-50 active:scale-95"
                                    >
                                        -
                                    </button>
                                    <span class="font-mono font-extrabold text-xs px-1 text-stone-900">{{ $cantidad }}</span>
                                    <button 
                                        wire:click="incrementar({{ $producto->id }})"
                                        class="w-6 h-6 rounded-lg bg-primary text-white shadow-xs flex items-center justify-center font-black text-xs hover:bg-primary/90 active:scale-95"
                                    >
                                        +
                                    </button>
                                </div>
                            @else
                                <button 
                                    wire:click="agregarProducto({{ $producto->id }})"
                                    class="px-3 py-1.5 rounded-xl bg-primary text-white text-[11px] font-extrabold shadow-sm hover:bg-primary/90 active:scale-95 transition flex items-center gap-1 cursor-pointer"
                                >
                                    <span class="material-symbols-outlined text-[14px]">add</span>
                                    <span>Agregar</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-stone-400 space-y-2">
                    <span class="material-symbols-outlined text-4xl">ramen_dining</span>
                    <p class="text-xs font-bold">No se encontraron platos con ese criterio.</p>
                </div>
            @endforelse
        </main>

        <!-- Floating Sticky Bottom Cart Bar -->
        @if (!empty($carrito))
            <div class="fixed bottom-3 left-0 right-0 max-w-lg mx-auto px-4 z-40">
                <button 
                    wire:click="$set('mostrarCarrito', true)"
                    class="w-full py-3.5 px-5 rounded-2xl bg-primary text-white shadow-xl flex items-center justify-between cursor-pointer hover:bg-primary/90 active:scale-98 transition"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-xl bg-white/20 flex items-center justify-center font-black text-xs">
                            {{ $this->cantidadTotal }}
                        </div>
                        <span class="text-xs font-extrabold tracking-tight">Ver Comanda Mesa #{{ $mesa->numero }}</span>
                    </div>
                    <span class="font-mono font-black text-sm">
                        ${{ number_format($this->total, 0, ',', '.') }} COP
                    </span>
                </button>
            </div>
        @endif

        <!-- Slide-up Cart Modal / Checkout Sheet -->
        @if ($mostrarCarrito)
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-xs p-0 sm:p-4">
                <div class="w-full max-w-lg bg-white rounded-t-[32px] sm:rounded-3xl shadow-2xl border border-stone-200 max-h-[90vh] flex flex-col overflow-hidden">
                    <!-- Sheet Header -->
                    <div class="p-4 border-b border-stone-100 flex items-center justify-between bg-stone-50">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">shopping_cart</span>
                            <h3 class="font-extrabold text-sm text-stone-900">
                                Tu Comanda · Mesa #{{ $mesa->numero }}
                            </h3>
                        </div>
                        <button 
                            wire:click="$set('mostrarCarrito', false)"
                            class="p-1 rounded-full hover:bg-stone-200 text-stone-500"
                        >
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>

                    <!-- Items List -->
                    <div class="flex-1 overflow-y-auto p-4 divide-y divide-stone-100 space-y-3">
                        @foreach ($carrito as $id => $item)
                            <div class="pt-3 first:pt-0 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-extrabold text-xs text-stone-900">{{ $item['nombre'] }}</span>
                                        <span class="text-[10px] text-stone-400 block font-mono">
                                            ${{ number_format($item['precio'], 0, ',', '.') }} c/u
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center gap-1 bg-stone-100 rounded-lg p-0.5 border border-stone-200">
                                            <button 
                                                wire:click="decrementar({{ $id }})"
                                                class="w-5 h-5 rounded bg-white text-stone-700 flex items-center justify-center text-xs font-black shadow-2xs"
                                            >
                                                -
                                            </button>
                                            <span class="font-mono font-bold text-xs px-1.5">{{ $item['cantidad'] }}</span>
                                            <button 
                                                wire:click="incrementar({{ $id }})"
                                                class="w-5 h-5 rounded bg-primary text-white flex items-center justify-center text-xs font-black shadow-2xs"
                                            >
                                                +
                                            </button>
                                        </div>
                                        <span class="font-mono font-extrabold text-xs text-stone-900 min-w-[65px] text-right">
                                            ${{ number_format($item['precio'] * $item['cantidad'], 0, ',', '.') }}
                                        </span>
                                        <button 
                                            wire:click="eliminarItem({{ $id }})"
                                            class="text-stone-400 hover:text-red-500 p-1"
                                            title="Eliminar"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Prep notes for this item -->
                                <input 
                                    type="text" 
                                    wire:model="carrito.{{ $id }}.notas"
                                    placeholder="Nota para este plato (ej: sin wasabi, salsa aparte)"
                                    class="w-full text-[11px] px-2.5 py-1 rounded-xl bg-stone-50 border border-stone-200 text-stone-700 placeholder:text-stone-400"
                                >
                            </div>
                        @endforeach

                        <!-- Comensal Name & General Notes Form -->
                        <div class="pt-4 space-y-2.5 border-t border-stone-200">
                            <div>
                                <label class="block text-[11px] font-extrabold text-stone-700 mb-1">
                                    Tu Nombre (Opcional, para llamarte por tu nombre)
                                </label>
                                <input 
                                    type="text" 
                                    wire:model="nombreCliente"
                                    placeholder="Ej: Daniel Gómez"
                                    class="w-full text-xs px-3 py-2 rounded-xl bg-stone-50 border border-stone-200 text-stone-900 placeholder:text-stone-400 focus:ring-2 focus:ring-primary/40"
                                >
                            </div>
                            <div>
                                <label class="block text-[11px] font-extrabold text-stone-700 mb-1">
                                    Instrucciones Generales para la Mesa
                                </label>
                                <textarea 
                                    wire:model="notasGenerales"
                                    rows="2"
                                    placeholder="Ej: Servir bebidas primero, palillos extra..."
                                    class="w-full text-xs px-3 py-2 rounded-xl bg-stone-50 border border-stone-200 text-stone-900 placeholder:text-stone-400 focus:ring-2 focus:ring-primary/40"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Sheet Footer & Confirm Button -->
                    <div class="p-4 border-t border-stone-100 bg-stone-50 space-y-2.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-stone-500">Total a Pagar en Mesa:</span>
                            <span class="text-base font-mono font-black text-stone-900">
                                ${{ number_format($this->total, 0, ',', '.') }} COP
                            </span>
                        </div>

                        <button 
                            wire:click="enviarPedido"
                            class="w-full py-3 rounded-2xl bg-primary text-white text-xs font-black shadow-lg hover:bg-primary/90 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[18px]">send</span>
                            <span>Confirmar y Enviar Pedido a la Mesa</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
