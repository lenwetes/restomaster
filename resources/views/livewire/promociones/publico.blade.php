<?php

use App\Models\Promocion;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.publico')] class extends Component
{
    public string $filtroCanal = 'todos'; // todos, salon, delivery
    public string $tab = 'vigentes'; // vigentes, todas
    public string $busqueda = '';

    public function setFiltroCanal(string $canal): void
    {
        $this->filtroCanal = $canal;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function with(): array
    {
        $query = Promocion::query();

        if ($this->tab === 'vigentes') {
            $query->vigentes();
        } else {
            $query->where('activo', true);
        }

        if ($this->filtroCanal === 'salon') {
            $query->where('aplica_salon', true);
        } elseif ($this->filtroCanal === 'delivery') {
            $query->where('aplica_delivery', true);
        }

        if (! empty($this->busqueda)) {
            $term = trim($this->busqueda);
            $query->where(function ($q) use ($term) {
                $q->where('titulo', 'ilike', "%{$term}%")
                    ->orWhere('subtitulo', 'ilike', "%{$term}%")
                    ->orWhere('descripcion', 'ilike', "%{$term}%");
            });
        }

        $promociones = $query->orderBy('orden')->orderByDesc('id')->get();
        $totalVigentes = Promocion::vigentes()->count();

        return [
            'promociones' => $promociones,
            'totalVigentes' => $totalVigentes,
        ];
    }
};
?>

<div class="py-10 sm:py-16 max-w-7xl mx-auto px-4 sm:px-6 space-y-12">
    <!-- Header Hero Gastronómico de Promociones -->
    <div class="text-center max-w-3xl mx-auto space-y-4">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-gradient-to-r from-amber-500/15 to-[#e0442e]/15 border border-amber-500/30 text-amber-300 text-xs font-mono font-bold tracking-wider uppercase shadow-inner">
            <span class="material-symbols-outlined text-[16px] text-amber-400 animate-pulse">local_fire_department</span>
            <span>Experiencias Exclusivas & Beneficios de Temporada</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight">
            Promociones & Eventos en <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-[#e0442e] to-rose-400">Provenza</span>
        </h1>
        <p class="text-sm sm:text-base text-[#c4a89e] leading-relaxed">
            Descubre nuestras noches temáticas, maridajes guiados, beneficios 2x1 en mixología botánica y cortes insignia a las brasas de roble silvestre.
        </p>
    </div>

    <!-- Barra de Filtros, Canales y Búsqueda -->
    <div class="flex flex-col md:flex-row items-center justify-between gap-4 bg-[#180e08]/90 p-4 rounded-3xl border border-amber-900/30 shadow-2xl backdrop-blur-md">
        <!-- Pestañas de Estado -->
        <div class="flex items-center gap-1.5 bg-[#100804] p-1.5 rounded-2xl border border-[#3e2920]/60 w-full md:w-auto">
            <button wire:click="setTab('vigentes')" 
                    class="flex-1 md:flex-initial px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 {{ $tab === 'vigentes' ? 'bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 text-black font-black shadow-lg shadow-amber-500/20' : 'text-[#c4a89e] hover:text-white' }}">
                <span class="material-symbols-outlined text-[16px]">verified</span>
                <span>Vigentes Hoy ({{ $totalVigentes }})</span>
            </button>
            <button wire:click="setTab('todas')" 
                    class="flex-1 md:flex-initial px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 {{ $tab === 'todas' ? 'bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 text-black font-black shadow-lg shadow-amber-500/20' : 'text-[#c4a89e] hover:text-white' }}">
                <span class="material-symbols-outlined text-[16px]">auto_stories</span>
                <span>Todas / Catálogo</span>
            </button>
        </div>

        <!-- Filtros por Canal (Salón vs Delivery) -->
        <div class="flex items-center gap-1 bg-[#100804] p-1.5 rounded-2xl border border-[#3e2920]/60 w-full md:w-auto justify-center">
            <button wire:click="setFiltroCanal('todos')" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $filtroCanal === 'todos' ? 'bg-[#29150b] text-amber-300 border border-amber-600/40' : 'text-[#c4a89e] hover:text-white' }}">
                Todos los Canales
            </button>
            <button wire:click="setFiltroCanal('salon')" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ $filtroCanal === 'salon' ? 'bg-[#29150b] text-amber-300 border border-amber-600/40' : 'text-[#c4a89e] hover:text-white' }}">
                <span class="material-symbols-outlined text-[14px]">table_restaurant</span>
                <span>Salón</span>
            </button>
            <button wire:click="setFiltroCanal('delivery')" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ $filtroCanal === 'delivery' ? 'bg-[#29150b] text-amber-300 border border-amber-600/40' : 'text-[#c4a89e] hover:text-white' }}">
                <span class="material-symbols-outlined text-[14px]">two_wheeler</span>
                <span>Delivery</span>
            </button>
        </div>

        <!-- Buscador -->
        <div class="relative w-full md:w-72">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-500 text-[18px]">search</span>
            <input type="text" 
                   wire:model.live.debounce.300ms="busqueda" 
                   placeholder="Buscar promoción o plato..." 
                   class="w-full pl-10 pr-4 py-2 text-xs rounded-2xl bg-[#100804] border border-[#3e2920] text-white placeholder-stone-500 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
        </div>
    </div>

    <!-- Grid de Tarjetas de Promociones (Estilo Magazine Gastronómico) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($promociones as $promo)
            <article class="group bg-[#160d08] rounded-3xl border border-[#3e2920]/80 overflow-hidden shadow-2xl hover:border-amber-500/50 transition-all duration-300 flex flex-col hover:-translate-y-1 relative">
                <!-- Imagen / Banner de Portada -->
                <div class="relative h-56 sm:h-64 overflow-hidden bg-[#22130c]">
                    @if($promo->imagen_url)
                        <img src="{{ $promo->imagen_url }}" 
                             alt="{{ $promo->titulo }}" 
                             onerror="this.onerror=null; this.src='/images/craft-cocktail.jpg';"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 brightness-90 group-hover:brightness-100">
                    @else
                        <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-[#26150b] to-[#120803] text-amber-500/40 p-6 text-center">
                            <span class="material-symbols-outlined text-5xl mb-2">restaurant</span>
                            <span class="text-xs font-mono uppercase tracking-wider font-bold">RestoMaster Provenza</span>
                        </div>
                    @endif

                    <div class="absolute inset-0 bg-gradient-to-t from-[#160d08] via-transparent to-black/40"></div>

                    <!-- Badge de Beneficio Flotante -->
                    <div class="absolute top-4 left-4 flex flex-wrap gap-1.5 z-10">
                        @if($promo->tipo_beneficio === 'dos_por_uno')
                            <span class="px-3 py-1 rounded-full text-[11px] font-black font-mono bg-gradient-to-r from-amber-500 to-amber-600 text-black shadow-lg shadow-amber-500/30">
                                2x1 ESPECIAL
                            </span>
                        @elseif($promo->descuento_porcentaje)
                            <span class="px-3 py-1 rounded-full text-[11px] font-black font-mono bg-gradient-to-r from-rose-600 to-[#e0442e] text-white shadow-lg shadow-rose-900/40">
                                {{ number_format($promo->descuento_porcentaje, 0) }}% OFF
                            </span>
                        @elseif($promo->precio_promocional)
                            <span class="px-3 py-1 rounded-full text-[11px] font-black font-mono bg-emerald-600 text-white shadow-lg shadow-emerald-900/40">
                                ${{ number_format($promo->precio_promocional, 0, ',', '.') }}
                            </span>
                        @endif

                        @if($promo->mostrar_en_portada)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-[#140a05]/90 text-amber-300 border border-amber-600/40 backdrop-blur-xs flex items-center gap-1">
                                <span class="material-symbols-outlined text-[13px] text-amber-400">star</span>
                                <span>Destacada</span>
                            </span>
                        @endif
                    </div>

                    <!-- Badges de Canales Disponibles -->
                    <div class="absolute top-4 right-4 flex items-center gap-1.5 z-10">
                        @if($promo->aplica_salon)
                            <div class="w-8 h-8 rounded-full bg-[#100804]/90 border border-white/10 text-white flex items-center justify-center shadow-md" title="Válido en Salón">
                                <span class="material-symbols-outlined text-[16px] text-amber-400">table_restaurant</span>
                            </div>
                        @endif
                        @if($promo->aplica_delivery)
                            <div class="w-8 h-8 rounded-full bg-[#100804]/90 border border-white/10 text-white flex items-center justify-center shadow-md" title="Válido en Delivery Express">
                                <span class="material-symbols-outlined text-[16px] text-[#e0442e]">two_wheeler</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Contenido de la Tarjeta -->
                <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                    <div class="space-y-2">
                        @if($promo->dias_semana)
                            <div class="text-[10px] font-mono font-bold uppercase tracking-wider text-amber-400/90 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                                <span>{{ implode(', ', array_map('ucfirst', $promo->dias_semana)) }}</span>
                            </div>
                        @endif

                        <h2 class="text-xl font-black text-white leading-tight group-hover:text-amber-300 transition-colors">
                            <a href="{{ route('promociones.detalle', $promo->slug) }}">
                                {{ $promo->titulo }}
                            </a>
                        </h2>

                        @if($promo->subtitulo)
                            <p class="text-xs font-semibold text-stone-300 line-clamp-1">
                                {{ $promo->subtitulo }}
                            </p>
                        @endif

                        <p class="text-xs text-[#c4a89e] leading-relaxed line-clamp-2">
                            {{ $promo->descripcion }}
                        </p>
                    </div>

                    <!-- Pie de la Tarjeta con Vigencia y Acciones -->
                    <div class="pt-4 border-t border-[#3e2920]/80 space-y-3.5">
                        <div class="flex items-center justify-between text-[11px] font-mono text-[#a88d82]">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px] text-amber-500">schedule</span>
                                @if($promo->fecha_fin)
                                    <span>Hasta {{ $promo->fecha_fin->format('d/m/Y') }}</span>
                                @else
                                    <span>Vigencia permanente</span>
                                @endif
                            </span>

                            @if($promo->precio_original && $promo->precio_promocional)
                                <span class="line-through text-stone-500">
                                    ${{ number_format($promo->precio_original, 0, ',', '.') }}
                                </span>
                            @endif
                        </div>

                        <!-- Botones de Conversión Rápida -->
                        <div class="grid grid-cols-2 gap-2">
                            @if($promo->aplica_salon)
                                <a href="{{ route('reservas.publico') }}" 
                                   class="py-2.5 px-3 rounded-xl bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 hover:brightness-110 text-black font-black text-xs text-center flex items-center justify-center gap-1.5 shadow-md shadow-amber-500/20 transition-all">
                                    <span class="material-symbols-outlined text-[15px]">calendar_month</span>
                                    <span>Reservar</span>
                                </a>
                            @elseif($promo->aplica_delivery)
                                <a href="{{ route('delivery.publico') }}" 
                                   class="py-2.5 px-3 rounded-xl bg-[#e0442e] hover:bg-[#c93a26] text-white font-bold text-xs text-center flex items-center justify-center gap-1.5 shadow-md transition-all">
                                    <span class="material-symbols-outlined text-[15px]">shopping_bag</span>
                                    <span>Pedir Delivery</span>
                                </a>
                            @endif

                            <a href="{{ route('promociones.detalle', $promo->slug) }}" 
                               class="py-2.5 px-3 rounded-xl bg-[#26150b] hover:bg-[#341b0e] text-[#f5e8e2] border border-amber-600/30 text-xs font-bold text-center flex items-center justify-center gap-1 transition-all">
                                <span>Ver Detalle</span>
                                <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full p-12 rounded-3xl bg-[#180e08] border border-[#3e2920]/80 text-center space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 mx-auto flex items-center justify-center">
                    <span class="material-symbols-outlined text-3xl">sentiment_dissatisfied</span>
                </div>
                <h3 class="text-lg font-black text-white">No encontramos promociones en esta categoría</h3>
                <p class="text-xs text-[#c4a89e] max-w-md mx-auto">
                    Prueba cambiando los filtros de canal o explora todas las promociones del catálogo.
                </p>
                <button wire:click="setTab('todas')" class="px-5 py-2.5 rounded-xl bg-amber-500 text-black font-black text-xs">
                    Ver catálogo completo
                </button>
            </div>
        @endforelse
    </div>
</div>
