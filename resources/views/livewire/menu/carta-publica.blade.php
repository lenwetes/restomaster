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

    public function limpiarBusqueda(): void
    {
        $this->busqueda = '';
        $this->categoriaSeleccionada = 'todas';
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
                'color' => $c['color'] ?? '#e0442e',
                'productos' => collect($c['productos'])->map(fn ($p) => (object) $p),
                'productos_count' => count($c['productos']),
            ]);

            $todasCategorias = $menu->map(fn (array $c) => (object) [
                'id' => $c['id'],
                'nombre' => $c['nombre'],
                'slug' => $c['slug'],
                'icono' => $c['icono'],
                'color' => $c['color'] ?? '#e0442e',
                'productos_count' => count($c['productos']),
            ]);

            return [
                'categorias' => $categorias,
                'todasCategorias' => $todasCategorias,
                'totalProductos' => $todasCategorias->sum('productos_count'),
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
        $todasCategorias = Categoria::where('activo', true)
            ->withCount(['productos' => fn ($q) => $q->where('activo', true)])
            ->orderBy('orden')
            ->get(['id', 'nombre', 'slug', 'icono', 'color']);

        return [
            'categorias' => $categorias,
            'todasCategorias' => $todasCategorias,
            'totalProductos' => $todasCategorias->sum('productos_count'),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-10">
    
    <!-- ============================================================= -->
    <!-- HEADER HERO DE LA CARTA                                       -->
    <!-- ============================================================= -->
    <div class="text-center max-w-3xl mx-auto space-y-4">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#1e1410] border border-[#e0442e]/30 shadow-md">
            <span class="w-2 h-2 rounded-full bg-[#e0442e] animate-pulse"></span>
            <span class="text-xs font-bold text-[#e8a020] uppercase font-mono tracking-wider">Menú Gastronómico Oficial</span>
            <span class="text-[#432f26]">•</span>
            <span class="text-xs text-[#c4a89e]">Provenza, Medellín</span>
        </div>

        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight">
            Nuestra Carta Culinaria
        </h1>
        <p class="text-xs sm:text-sm text-[#c4a89e] leading-relaxed max-w-2xl mx-auto">
            Descubre nuestra cuidada selección de cortes Angus madurados a la brasa de roble, pastas frescas al huevo elaboradas cada mañana, smash burgers artesanales y coctelería botánica de autor.
        </p>

        <!-- Quick CTAs -->
        <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
            <a 
                href="{{ route('delivery.publico') }}" 
                class="px-5 py-2.5 rounded-2xl bg-[#e0442e] hover:bg-[#b8301d] text-white text-xs font-black shadow-lg shadow-[#e0442e]/25 flex items-center gap-2 transition-all hover:scale-105"
            >
                <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                <span>Pedir a Domicilio Express</span>
            </a>
            <a 
                href="{{ route('reservas.publico') }}" 
                class="px-5 py-2.5 rounded-2xl bg-[#1e1410] hover:bg-[#261a15] border border-[#432f26] hover:border-[#e8a020]/60 text-white text-xs font-bold flex items-center gap-2 transition-all"
            >
                <span class="material-symbols-outlined text-[18px] text-[#e8a020]">calendar_month</span>
                <span>Reservar Mesa</span>
            </a>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- BARRA STICKY: NAVEGACIÓN DE CATEGORÍAS 2-TIER SIN CORTES      -->
    <!-- ============================================================= -->
    <div class="sticky top-20 z-30 bg-[#0e0907]/95 backdrop-blur-xl p-4 sm:p-5 rounded-3xl border border-[#432f26]/80 shadow-2xl space-y-3.5">
        
        <!-- TIER 1: Título de Estado + Buscador -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pb-3 border-b border-[#432f26]/50">
            
            <!-- Estado / Filtro Actual -->
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-[#e0442e] animate-ping shrink-0"></span>
                @if($categoriaSeleccionada !== 'todas')
                    @php
                        $catActiva = $todasCategorias->firstWhere('slug', $categoriaSeleccionada);
                    @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-white uppercase tracking-wider font-mono">
                            Filtrado por: <span class="text-[#e8a020]">{{ $catActiva->nombre ?? $categoriaSeleccionada }}</span>
                        </span>
                        <button 
                            type="button" 
                            wire:click="seleccionarCategoria('todas')" 
                            class="text-[11px] font-mono text-[#ff7e67] hover:underline cursor-pointer flex items-center gap-0.5"
                        >
                            <span>(Ver Toda la Carta)</span>
                        </button>
                    </div>
                @elseif(!empty($busqueda))
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-white uppercase tracking-wider font-mono">
                            Búsqueda: <span class="text-[#e8a020]">"{{ $busqueda }}"</span>
                        </span>
                        <button 
                            type="button" 
                            wire:click="limpiarBusqueda" 
                            class="text-[11px] font-mono text-[#ff7e67] hover:underline cursor-pointer"
                        >
                            (Limpiar)
                        </button>
                    </div>
                @else
                    <span class="text-xs font-bold text-[#f5e8e2] uppercase tracking-wider font-mono">
                        Toda la Carta ({{ $totalProductos ?? 22 }} platos disponibles)
                    </span>
                @endif
            </div>

            <!-- Buscador en tiempo real -->
            <div class="relative w-full sm:w-80 shrink-0">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#7a5a52]">
                    <span class="material-symbols-outlined text-[18px]">search</span>
                </span>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar por plato o ingrediente..."
                    aria-label="Buscar en la carta"
                    class="w-full pl-10 pr-9 py-2 rounded-2xl border border-[#432f26] bg-[#1e1410] text-xs text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none shadow-inner"
                />
                @if(!empty($busqueda))
                    <button 
                        type="button" 
                        wire:click="limpiarBusqueda" 
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#7a5a52] hover:text-white"
                        title="Limpiar búsqueda"
                    >
                        <span class="material-symbols-outlined text-[16px]">close</span>
                    </button>
                @endif
            </div>

        </div>

        <!-- TIER 2: Selector nativo móvil (1 toque en celulares) -->
        <div class="sm:hidden">
            <label for="categoria-select-carta" class="sr-only">Seleccionar Categoría</label>
            <div class="relative">
                <select 
                    id="categoria-select-carta"
                    wire:change="seleccionarCategoria($event.target.value)"
                    class="w-full px-4 py-2.5 rounded-2xl bg-[#1e1410] border border-[#432f26] text-xs font-bold text-white focus:border-[#e0442e] outline-none appearance-none"
                >
                    <option value="todas" {{ $categoriaSeleccionada === 'todas' ? 'selected' : '' }}>
                        🔥 Todas las Categorías ({{ $totalProductos ?? 22 }})
                    </option>
                    @foreach ($todasCategorias as $catPill)
                        <option value="{{ $catPill->slug }}" {{ $categoriaSeleccionada === $catPill->slug ? 'selected' : '' }}>
                            {{ $catPill->nombre }} ({{ $catPill->productos_count ?? '' }})
                        </option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-[#c4a89e]">
                    <span class="material-symbols-outlined text-[18px]">expand_more</span>
                </div>
            </div>
        </div>

        <!-- TIER 2: Botones de Categorías con FLEX-WRAP (100% visibles, NUNCA se cortan en pantallas) -->
        <div class="hidden sm:flex flex-wrap items-center gap-2 sm:gap-2.5 pt-0.5">
            
            <!-- Botón Todas -->
            <button 
                type="button" 
                wire:click="seleccionarCategoria('todas')" 
                class="px-4 py-2 rounded-2xl text-xs font-black transition-all cursor-pointer flex items-center gap-2 {{ $categoriaSeleccionada === 'todas' ? 'bg-[#e0442e] text-white shadow-lg shadow-[#e0442e]/30 scale-105 ring-2 ring-white/20' : 'bg-[#1e1410] text-[#c4a89e] hover:text-white border border-[#432f26] hover:border-[#7a5a52]' }}"
            >
                <span>🔥</span>
                <span>Todas</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono {{ $categoriaSeleccionada === 'todas' ? 'bg-black/30 text-white' : 'bg-white/10 text-[#c4a89e]' }}">
                    {{ $totalProductos ?? count($todasCategorias) }}
                </span>
            </button>

            <!-- Categorías con colores POS y conteo exacto -->
            @foreach ($todasCategorias as $catPill)
                @php
                    $catColor = $catPill->color ?? '#e0442e';
                    $isSelected = ($categoriaSeleccionada === $catPill->slug);
                @endphp
                <button 
                    type="button" 
                    wire:click="seleccionarCategoria('{{ $catPill->slug }}')" 
                    class="px-3.5 py-2 rounded-2xl text-xs font-black transition-all flex items-center gap-2 cursor-pointer border {{ $isSelected ? 'text-white shadow-lg scale-105 ring-2 ring-white/20' : 'bg-[#1e1410] text-[#c4a89e] hover:text-white border-[#432f26] hover:border-[#7a5a52]' }}"
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
                        <span class="material-symbols-outlined text-[16px]">{{ $catPill->icono }}</span>
                    @else
                        <span>{{ $catPill->icono ?? '🍽️' }}</span>
                    @endif
                    <span>{{ $catPill->nombre }}</span>

                    @if(isset($catPill->productos_count))
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono {{ $isSelected ? 'bg-black/30 text-white' : 'bg-white/10 text-[#c4a89e]' }}">
                            {{ $catPill->productos_count }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

    </div>

    <!-- ============================================================= -->
    <!-- DESPLIEGUE CATEGORIZADO DE PLATOS CON FOTOGRAFÍA REAL         -->
    <!-- ============================================================= -->
    <div class="space-y-16">
        @forelse ($categorias as $cat)
            @if ($cat->productos->isNotEmpty())
                @php
                    $categoriaColor = $cat->color ?? '#e0442e';
                @endphp
                <div class="space-y-6">
                    
                    <!-- Category Section Header with Official POS Accent Line -->
                    <div class="flex items-center justify-between border-b border-[#432f26]/60 pb-3 relative">
                        <div class="flex items-center gap-3">
                            <div 
                                class="w-11 h-11 rounded-2xl flex items-center justify-center text-white shadow-md text-xl"
                                @style([
                                    'background-color: ' . $categoriaColor . '25',
                                    'border: 1.5px solid ' . $categoriaColor,
                                ])
                            >
                                @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                    <span class="material-symbols-outlined text-[22px]">{{ $cat->icono }}</span>
                                @else
                                    <span>{{ $cat->icono ?? '🍽️' }}</span>
                                @endif
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">{{ $cat->nombre }}</h2>
                                    <span 
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono text-white"
                                        @style(['background-color: ' . $categoriaColor])
                                    >
                                        {{ $cat->productos->count() }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-[#c4a89e] mt-0.5">Especialidad de la casa elaborada al instante</p>
                            </div>
                        </div>

                        <span class="text-[10px] font-bold uppercase tracking-widest text-[#7a5a52] font-mono hidden sm:inline">
                            Precios Oficiales (COP)
                        </span>
                    </div>

                    <!-- Products Grid with Dish Photography -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($cat->productos as $producto)
                            @php
                                $area = $producto->area_cocina ?? 'caliente';
                                $isBarra = in_array($area, ['barra', 'bebidas']);
                                $isFria = in_array($area, ['fria', 'sushi']);
                            @endphp
                            <div 
                                class="rounded-3xl bg-[#1e1410] border border-[#432f26] overflow-hidden flex flex-col justify-between hover:border-[#e0442e]/70 hover:shadow-2xl hover:shadow-[#e0442e]/10 transition-all duration-300 group shadow-lg"
                                @style(['border-top: 4px solid ' . $categoriaColor])
                            >
                                
                                <!-- Dish Photography Thumbnail -->
                                @if (!empty($producto->imagen))
                                    <div class="relative h-48 sm:h-52 w-full overflow-hidden bg-[#140e0b]">
                                        <img 
                                            src="{{ asset($producto->imagen) }}" 
                                            alt="{{ $producto->nombre }}" 
                                            loading="lazy"
                                            class="w-full h-full object-cover group-hover:scale-108 transition-transform duration-700 brightness-95 group-hover:brightness-105"
                                            onerror="this.parentElement.style.display='none'"
                                        />
                                        <div class="absolute inset-0 bg-gradient-to-t from-[#1e1410] via-transparent to-transparent"></div>
                                        
                                        <!-- Culinary Station Badge Floating on Image -->
                                        <span class="absolute top-3 right-3 px-2.5 py-1 rounded-xl text-[9px] font-black font-mono uppercase tracking-wider backdrop-blur-md shadow-md {{ $isBarra ? 'bg-purple-900/80 text-purple-200 border border-purple-400/40' : ($isFria ? 'bg-[#2eb8b4]/80 text-white border border-[#2eb8b4]/40' : 'bg-[#e0442e]/80 text-white border border-[#e0442e]/40') }}">
                                            {{ $isBarra ? 'Barra' : ($isFria ? 'Cocina Fría' : 'Parrilla') }}
                                        </span>
                                    </div>
                                @endif

                                <!-- Dish Content Body -->
                                <div class="p-6 space-y-3 flex-1 flex flex-col justify-between">
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <h3 class="font-black text-white text-base group-hover:text-[#e0442e] transition-colors leading-snug">
                                                {{ $producto->nombre }}
                                            </h3>
                                        </div>

                                        <p class="text-xs text-[#c4a89e] leading-relaxed line-clamp-3">
                                            {{ $producto->descripcion ?? 'Preparado artesanalmente en nuestra cocina con ingredientes seleccionados de origen y técnica gastronómica de autor.' }}
                                        </p>
                                    </div>

                                    <!-- Price and Order Action -->
                                    <div class="pt-4 mt-2 border-t border-[#432f26]/50 flex items-center justify-between gap-3">
                                        <div>
                                            <span class="text-[10px] text-[#7a5a52] uppercase font-bold block font-mono">Precio COP</span>
                                            <span class="text-lg font-black text-white font-mono tracking-tight group-hover:text-[#e8a020] transition-colors">
                                                $ {{ number_format($producto->precio, 0, ',', '.') }}
                                            </span>
                                        </div>

                                        <a 
                                            href="{{ route('delivery.publico') }}" 
                                            class="px-4 py-2 rounded-2xl bg-[#261a15] hover:bg-[#e0442e] text-[#f5e8e2] hover:text-white text-xs font-black border border-[#432f26] hover:border-[#e0442e] transition-all flex items-center gap-1.5 shadow-sm group-hover:bg-[#e0442e]"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">two_wheeler</span>
                                            <span>Pedir</span>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>

                </div>
            @endif
        @empty
            <div class="text-center py-16 p-8 rounded-3xl bg-[#1e1410] border border-[#432f26] text-[#c4a89e] space-y-4 max-w-md mx-auto">
                <span class="text-4xl block">🔍</span>
                <div class="space-y-1">
                    <p class="font-black text-white text-base">No encontramos platos con "{{ $busqueda }}"</p>
                    <p class="text-xs text-[#c4a89e]">Intenta buscar por ingrediente (ej. 'angus', 'salmón', 'trufa', 'ceviche')</p>
                </div>
                <button 
                    type="button" 
                    wire:click="limpiarBusqueda" 
                    class="px-5 py-2.5 rounded-2xl bg-[#e0442e] text-white text-xs font-bold shadow-md hover:scale-105 transition-all"
                >
                    Ver Todo el Menú
                </button>
            </div>
        @endforelse
    </div>

</div>
