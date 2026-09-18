<?php

use App\Models\Categoria;
use App\Models\Producto;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.publico')] class extends Component
{
    public string $categoriaSeleccionada = 'todas';
    public string $busqueda = '';

    public function seleccionarCategoria(string $slug): void
    {
        $this->categoriaSeleccionada = $slug;
    }

    public function with(): array
    {
        $menuService = app(\App\Services\MenuService::class);

        if (empty($this->busqueda) && $this->categoriaSeleccionada === 'todas') {
            $menu = $menuService->obtenerMenuPublico();

            $categorias = $menu->map(fn (array $c) => (object) [
                'id' => $c['id'],
                'nombre' => $c['nombre'],
                'slug' => $c['slug'],
                'icono' => $c['icono'],
                'productos' => collect($c['productos'])->map(fn ($p) => (object) $p),
            ]);

            $todasCategorias = $menu->map(fn (array $c) => (object) [
                'id' => $c['id'],
                'nombre' => $c['nombre'],
                'slug' => $c['slug'],
                'icono' => $c['icono'],
            ]);

            return [
                'categorias' => $categorias,
                'todasCategorias' => $todasCategorias,
            ];
        }

        $query = Categoria::where('activo', true)
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
            $query->where('slug', $this->categoriaSeleccionada);
        }

        $categorias = $query->get();
        $todasCategorias = Categoria::where('activo', true)->orderBy('orden')->get(['id', 'nombre', 'slug', 'icono']);

        return [
            'categorias' => $categorias,
            'todasCategorias' => $todasCategorias,
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-8">
    
    <!-- Hero Header Title & Subtitle -->
    <div class="text-center max-w-2xl mx-auto space-y-4">
        <span class="px-3.5 py-1 rounded-full bg-[#ff5436]/10 text-[#ff5436] text-xs font-black uppercase tracking-wider border border-[#ff5436]/25">
            Gastronomía de Autor & Parrilla · Medellín
        </span>
        <h1 class="text-3xl sm:text-5xl font-black text-stone-900 tracking-tight">
            Carta de Autor & Menú
        </h1>
        <p class="text-xs sm:text-sm text-stone-500 leading-relaxed">
            Explora nuestras creaciones culinarias: cortes selectos a la parrilla, pastas artesanales, hamburguesas gourmet, entradas de autor y coctelería clásica.
        </p>

        <!-- Quick CTAs -->
        <div class="flex items-center justify-center gap-3 pt-2">
            <a 
                href="{{ route('delivery.publico') }}" 
                class="px-5 py-2.5 rounded-2xl bg-[#ff5436] hover:bg-[#e0381d] text-white text-xs font-black shadow-lg shadow-[#ff5436]/25 flex items-center gap-2 transition-all"
            >
                <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                <span>Pedir a Domicilio</span>
            </a>
            <a 
                href="{{ route('reservas.publico') }}" 
                class="px-5 py-2.5 rounded-2xl bg-white hover:bg-stone-50 border border-stone-200 hover:border-amber-400 text-stone-700 text-xs font-bold flex items-center gap-2 transition-all shadow-sm"
            >
                <span class="material-symbols-outlined text-[18px] text-amber-500">calendar_month</span>
                <span>Reservar Mesa</span>
            </a>
        </div>
    </div>

    <!-- Search & Category Filters -->
    <div class="sticky top-16 sm:top-20 z-30 bg-[#fafaf9]/95 backdrop-blur-md py-4 border-b border-stone-200 space-y-3">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            
            <!-- Category Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto w-full pb-1 no-scrollbar">
                <button 
                    type="button" 
                    wire:click="seleccionarCategoria('todas')" 
                    class="px-3.5 py-1.5 rounded-xl text-xs font-black transition-all shrink-0 {{ $categoriaSeleccionada === 'todas' ? 'bg-[#ff5436] text-white shadow-md' : 'bg-white text-stone-500 hover:text-stone-900 border border-stone-200 hover:border-stone-300' }}"
                >
                    🔥 Todos los Platos
                </button>
                @foreach ($todasCategorias as $catPill)
                    <button 
                        type="button" 
                        wire:click="seleccionarCategoria('{{ $catPill->slug }}')" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-black transition-all shrink-0 flex items-center gap-1.5 {{ $categoriaSeleccionada === $catPill->slug ? 'bg-[#ff5436] text-white shadow-md' : 'bg-white text-stone-500 hover:text-stone-900 border border-stone-200 hover:border-stone-300' }}"
                    >
                        @if(preg_match('/^[a-z0-9_]+$/', $catPill->icono ?? ''))
                            <span class="material-symbols-outlined text-[15px]">{{ $catPill->icono }}</span>
                        @else
                            <span>{{ $catPill->icono ?? '🍽️' }}</span>
                        @endif
                        <span>{{ $catPill->nombre }}</span>
                    </button>
                @endforeach
            </div>

            <!-- Search input -->
            <div class="relative w-full sm:w-72 shrink-0">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                    <span class="material-symbols-outlined text-[18px]">search</span>
                </span>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar por plato o ingrediente..."
                    class="w-full pl-10 pr-4 py-2 rounded-2xl border border-stone-200 bg-white text-xs text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none shadow-sm"
                />
            </div>
        </div>
    </div>

    <!-- Categorized Menu Display -->
    <div class="space-y-12">
        @forelse ($categorias as $cat)
            @if ($cat->productos->isNotEmpty())
                <div class="space-y-4">
                    <!-- Section Title -->
                    <div class="flex items-center justify-between border-b border-stone-200 pb-2">
                        <div class="flex items-center gap-2.5">
                            @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                <span class="material-symbols-outlined text-2xl">{{ $cat->icono }}</span>
                            @else
                                <span class="text-2xl">{{ $cat->icono ?? '🍽️' }}</span>
                            @endif
                            <h2 class="text-xl font-black text-stone-900 tracking-tight">{{ $cat->nombre }}</h2>
                            <span class="px-2 py-0.5 rounded-full bg-stone-100 border border-stone-200 text-[10px] font-bold text-stone-500">
                                {{ $cat->productos->count() }}
                            </span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Precios en Pesos Colombianos (COP)</span>
                    </div>

                    <!-- Products Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($cat->productos as $producto)
                            <div class="p-5 rounded-2xl bg-white border border-stone-200 flex flex-col justify-between hover:border-[#ff5436]/40 hover:shadow-md transition-all group shadow-sm">
                                <div class="space-y-2.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="font-black text-stone-900 text-base group-hover:text-[#ff5436] transition-colors leading-snug">
                                            {{ $producto->nombre }}
                                        </h3>
                                        <span class="px-2 py-0.5 rounded-md bg-stone-100 border border-stone-200 text-[9px] font-bold text-stone-500 uppercase tracking-wider shrink-0">
                                            {{ in_array($producto->area_cocina, ['barra', 'bebidas']) ? 'Barra' : (in_array($producto->area_cocina, ['fria', 'sushi']) ? 'Cocina Fría' : 'Cocina / Parrilla') }}
                                        </span>
                                    </div>

                                    <p class="text-xs text-stone-500 leading-relaxed">
                                        {{ $producto->descripcion ?? 'Preparado artesanalmente con pesca fresca, ingredientes seleccionados y técnica japonesa.' }}
                                    </p>
                                </div>

                                <div class="pt-4 mt-4 border-t border-stone-100 flex items-center justify-between gap-3">
                                    <div>
                                        <span class="text-[10px] text-stone-400 uppercase font-bold block">Precio COP</span>
                                        <span class="text-base font-black text-stone-900 font-mono">
                                            $ {{ number_format($producto->precio, 0, ',', '.') }}
                                        </span>
                                    </div>

                                    <a 
                                        href="{{ route('delivery.publico') }}" 
                                        class="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-[#ff5436] text-stone-600 hover:text-white text-xs font-bold border border-stone-200 hover:border-[#ff5436] transition-all flex items-center gap-1.5"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">two_wheeler</span>
                                        <span>Pedir</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @empty
            <div class="text-center py-16 p-8 rounded-3xl bg-white border border-stone-200 shadow-sm space-y-3">
                <span class="text-4xl">🔍</span>
                <p class="font-black text-stone-900 text-base">No se encontraron platos que coincidan con tu búsqueda</p>
                <p class="text-xs text-stone-400">Intenta buscando ingredientes como salmón, atún, langostino o palta.</p>
            </div>
        @endforelse
    </div>

</div>
