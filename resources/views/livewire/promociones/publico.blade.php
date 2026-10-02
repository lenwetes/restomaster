<?php

use App\Livewire\Concerns\RegistraDifusionSocial;
use App\Models\Promocion;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.publico')] class extends Component {
    use RegistraDifusionSocial;

    public string $filtroCanal = 'todos';

    public string $tab = 'vigentes';

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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
        @forelse($promociones as $promo)
            <article class="group bg-[#160d08] rounded-3xl border border-[#3e2920]/80 overflow-hidden shadow-2xl hover:border-amber-500/50 transition-all duration-300 flex flex-col hover:-translate-y-1 relative">
                <!-- Imagen / Banner de Portada -->
                <div class="relative aspect-video overflow-hidden bg-[#22130c]">
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
                            <p class="text-sm font-semibold text-stone-300 line-clamp-1 break-words">
                                {{ $promo->subtitulo }}
                            </p>
                        @endif

                        <p class="text-sm sm:text-[15px] text-[#c4a89e] leading-relaxed line-clamp-2 break-words">
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

                        <!-- Botones de Compartir en Redes Sociales -->
                        <div class="pt-3 border-t border-[#3e2920]/80">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#a88d82] mb-2 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[13px]">share</span>
                                Compartir
                            </p>
                            <button type="button"
                                    x-data="{ comparteNativo: (typeof navigator !== 'undefined' && !!navigator.share) }"
                                    x-show="comparteNativo"
                                    style="display: none;"
                                    x-on:click="navigator.share({title: {{ Js::from($promo->titulo) }}, text: {{ Js::from('¡Mira esta promoción en RestoMaster! '.$promo->titulo) }}, url: {{ Js::from(route('promociones.detalle', $promo->slug)) }}}).then(() => $wire.registrarDifusion({{ $promo->id }}, 'social_nativo')).catch(() => {})"
                                    class="w-full mb-2 min-h-[44px] rounded-xl bg-[#e0442e]/15 hover:bg-[#e0442e]/25 border border-[#e0442e]/40 text-[#ff7e67] font-bold text-xs flex items-center justify-center gap-1.5 transition-all">
                                <span class="material-symbols-outlined text-[18px]">ios_share</span>
                                <span>Compartir</span>
                            </button>
                            <div class="flex items-center gap-2">
                                <a href="https://wa.me/?text={{ urlencode('¡Mira esta promoción! ' . $promo->titulo . ' 👉 ' . route('promociones.detalle', $promo->slug)) }}"
                                   target="_blank"
                                   wire:click="registrarDifusion({{ $promo->id }}, 'social_whatsapp')"
                                   class="flex-1 min-h-[44px] rounded-xl bg-emerald-600/15 hover:bg-emerald-600/25 border border-emerald-500/30 text-emerald-400 font-bold text-xs flex items-center justify-center gap-1.5 transition-all"
                                   title="Compartir por WhatsApp">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    <span>WhatsApp</span>
                                </a>
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('promociones.detalle', $promo->slug)) }}&quote={{ urlencode($promo->titulo . ' — ¡Promoción exclusiva en RestoMaster Provenza!') }}"
                                   target="_blank"
                                   rel="noopener"
                                   wire:click="registrarDifusion({{ $promo->id }}, 'social_facebook')"
                                   class="flex-1 min-h-[44px] rounded-xl bg-blue-600/15 hover:bg-blue-600/25 border border-blue-500/30 text-blue-400 font-bold text-xs flex items-center justify-center gap-1.5 transition-all"
                                   title="Compartir en Facebook">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                    <span>Facebook</span>
                                </a>
                                <button type="button"
                                        x-data="{ copiado: false }"
                                        x-on:click="navigator.clipboard.writeText('{{ route('promociones.detalle', $promo->slug) }}').then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })"
                                        wire:click="registrarDifusion({{ $promo->id }}, 'social_instagram')"
                                        class="flex-1 min-h-[44px] rounded-xl bg-gradient-to-r from-purple-600/15 to-pink-600/15 hover:from-purple-600/25 hover:to-pink-600/25 border border-purple-500/30 text-purple-300 font-bold text-xs flex items-center justify-center gap-1.5 transition-all"
                                        title="Copiar link para Instagram">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.948-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                    <span x-text="copiado ? '¡Link copiado!' : 'Instagram'">Instagram</span>
                                </button>
                            </div>
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
