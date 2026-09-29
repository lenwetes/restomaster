<?php

use App\Livewire\Concerns\RegistraDifusionSocial;
use App\Models\Promocion;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.publico')] class extends Component
{
    use RegistraDifusionSocial;

    public Promocion $promocion;

    public function mount(string $slug): void
    {
        $this->promocion = Promocion::where('slug', $slug)
            ->where('activo', true)
            ->firstOrFail();
    }
};
?>

@push('meta')
    @php
        $urlCanonica = route('promociones.detalle', $promocion->slug);
        $imagenOg = $promocion->imagen_url ? url($promocion->imagen_url) : asset('images/craft-cocktail.jpg');
        $descripcionOg = trim(strip_tags($promocion->descripcion ?? ''))
            ?: 'Promoción exclusiva en RestoMaster Provenza, Medellín.';
    @endphp
    <meta property="og:title" content="{{ $promocion->titulo }} — RestoMaster Provenza">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($descripcionOg, 200) }}">
    <meta property="og:image" content="{{ $imagenOg }}">
    <meta property="og:url" content="{{ $urlCanonica }}">
    <meta property="og:type" content="article">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $promocion->titulo }} — RestoMaster Provenza">
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit($descripcionOg, 200) }}">
    <meta name="twitter:image" content="{{ $imagenOg }}">
@endpush

<div class="py-10 sm:py-16 max-w-5xl mx-auto px-4 sm:px-6 space-y-10">
    <!-- Breadcrumb de Navegación -->
    <nav class="flex items-center gap-2 text-xs font-mono text-[#a88d82]">
        <a href="/" class="hover:text-white transition-colors">Inicio</a>
        <span>/</span>
        <a href="{{ route('promociones.publico') }}" class="hover:text-white transition-colors">Promociones</a>
        <span>/</span>
        <span class="text-amber-400 font-bold truncate">{{ $promocion->titulo }}</span>
    </nav>

    <!-- Tarjeta Principal de la Promoción -->
    <article class="bg-[#160d08] rounded-3xl border border-[#3e2920]/80 overflow-hidden shadow-2xl space-y-8">
        <!-- Banner Principal de Alta Resolución -->
        <div class="relative h-72 sm:h-96 w-full overflow-hidden bg-[#24140b]">
            @if($promocion->imagen_url)
                <img src="{{ $promocion->imagen_url }}" 
                     alt="{{ $promocion->titulo }}" 
                     onerror="this.onerror=null; this.src='/images/craft-cocktail.jpg';"
                     class="w-full h-full object-cover brightness-95">
            @else
                <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-[#26150b] to-[#120803] text-amber-500/40 p-6 text-center">
                    <span class="material-symbols-outlined text-6xl mb-2">restaurant</span>
                    <span class="text-sm font-mono uppercase tracking-wider font-bold">RestoMaster · Provenza, Medellín</span>
                </div>
            @endif

            <div class="absolute inset-0 bg-gradient-to-t from-[#160d08] via-transparent to-black/50"></div>

            <!-- Badges Flotantes -->
            <div class="absolute top-6 left-6 flex flex-wrap gap-2 z-10">
                @if($promocion->tipo_beneficio === 'dos_por_uno')
                    <span class="px-4 py-1.5 rounded-full text-xs font-black font-mono bg-gradient-to-r from-amber-500 to-amber-600 text-black shadow-xl">
                        2x1 ESPECIAL
                    </span>
                @elseif($promocion->descuento_porcentaje)
                    <span class="px-4 py-1.5 rounded-full text-xs font-black font-mono bg-gradient-to-r from-rose-600 to-[#e0442e] text-white shadow-xl">
                        {{ number_format($promocion->descuento_porcentaje, 0) }}% OFF
                    </span>
                @elseif($promocion->precio_promocional)
                    <span class="px-4 py-1.5 rounded-full text-xs font-black font-mono bg-emerald-600 text-white shadow-xl">
                        ${{ number_format($promocion->precio_promocional, 0, ',', '.') }}
                    </span>
                @endif

                @if($promocion->mostrar_en_portada)
                    <span class="px-3 py-1.5 rounded-full text-xs font-bold bg-[#140a05]/90 text-amber-300 border border-amber-600/40 backdrop-blur-xs flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px] text-amber-400">star</span>
                        <span>Destacada</span>
                    </span>
                @endif
            </div>

            <div class="absolute bottom-6 left-6 right-6">
                @if($promocion->dias_semana)
                    <div class="text-xs font-mono font-bold uppercase tracking-wider text-amber-400 mb-2 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">calendar_today</span>
                        <span>Válido los días: {{ implode(', ', array_map('ucfirst', $promocion->dias_semana)) }}</span>
                    </div>
                @endif

                <h1 class="text-2xl sm:text-4xl font-black text-white leading-tight">
                    {{ $promocion->titulo }}
                </h1>
            </div>
        </div>

        <!-- Contenido Detallado & Términos -->
        <div class="p-6 sm:p-10 space-y-8">
            @if($promocion->subtitulo)
                <p class="text-lg font-bold text-amber-300/90 leading-relaxed border-l-4 border-amber-500 pl-4">
                    {{ $promocion->subtitulo }}
                </p>
            @endif

            <div class="space-y-4 text-sm sm:text-base text-[#e5d4cb] leading-relaxed">
                {!! nl2br(e($promocion->descripcion)) !!}
            </div>

            <!-- Fila de Canales y Vigencia -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4 border-t border-[#3e2920]/80">
                <div class="p-4 rounded-2xl bg-[#100804] border border-[#3e2920]/60 space-y-1">
                    <span class="text-[11px] font-mono font-bold text-[#a88d82] uppercase tracking-wider">Canal Aplicable</span>
                    <div class="text-sm font-bold text-white flex items-center gap-2">
                        @if($promocion->aplica_salon && $promocion->aplica_delivery)
                            <span class="text-emerald-400 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">done_all</span> Salón & Delivery
                            </span>
                        @elseif($promocion->aplica_salon)
                            <span class="text-amber-400 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">table_restaurant</span> Consumo en Salón
                            </span>
                        @else
                            <span class="text-[#e0442e] flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">two_wheeler</span> Delivery Express
                            </span>
                        @endif
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-[#100804] border border-[#3e2920]/60 space-y-1">
                    <span class="text-[11px] font-mono font-bold text-[#a88d82] uppercase tracking-wider">Período de Vigencia</span>
                    <div class="text-sm font-bold text-white flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-amber-500">schedule</span>
                        @if($promocion->fecha_fin)
                            <span>Hasta {{ $promocion->fecha_fin->format('d/m/Y') }}</span>
                        @else
                            <span>Disponible por tiempo limitado</span>
                        @endif
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-[#100804] border border-[#3e2920]/60 space-y-1">
                    <span class="text-[11px] font-mono font-bold text-[#a88d82] uppercase tracking-wider">Precio / Beneficio</span>
                    <div class="text-sm font-bold text-white flex items-center gap-2">
                        @if($promocion->precio_promocional)
                            <span class="text-emerald-400 font-mono font-black">${{ number_format($promocion->precio_promocional, 0, ',', '.') }}</span>
                            @if($promocion->precio_original)
                                <span class="line-through text-stone-500 text-xs font-mono">${{ number_format($promocion->precio_original, 0, ',', '.') }}</span>
                            @endif
                        @elseif($promocion->descuento_porcentaje)
                            <span class="text-rose-400 font-mono font-black">{{ number_format($promocion->descuento_porcentaje, 0) }}% de Descuento</span>
                        @else
                            <span class="text-amber-300 font-mono font-black">Beneficio 2x1</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Términos y Condiciones -->
            @if($promocion->terminos_condiciones)
                <div class="p-5 rounded-2xl bg-[#100804] border border-[#3e2920]/60 space-y-2">
                    <h3 class="text-xs font-mono font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">info</span>
                        <span>Términos & Condiciones</span>
                    </h3>
                    <p class="text-xs text-[#a88d82] leading-relaxed">
                        {{ $promocion->terminos_condiciones }}
                    </p>
                </div>
            @endif

            <!-- Llamado a la Acción y Compartir en Redes Sociales -->
            <div class="pt-6 border-t border-[#3e2920]/80 space-y-4">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        @if($promocion->aplica_salon)
                            <a href="{{ route('reservas.publico') }}"
                               class="flex-1 sm:flex-initial py-3.5 px-6 rounded-2xl bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 hover:brightness-110 text-black font-black text-sm text-center flex items-center justify-center gap-2 shadow-xl shadow-amber-500/25 transition-all">
                                <span class="material-symbols-outlined text-[18px]">calendar_month</span>
                                <span>Reservar Mesa con esta Promo</span>
                            </a>
                        @endif

                        @if($promocion->aplica_delivery)
                            <a href="{{ route('delivery.publico') }}"
                               class="flex-1 sm:flex-initial py-3.5 px-6 rounded-2xl bg-[#e0442e] hover:bg-[#c93a26] text-white font-bold text-sm text-center flex items-center justify-center gap-2 shadow-xl shadow-rose-950/50 transition-all">
                                <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                                <span>Pedir a Domicilio Express</span>
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Botones de Compartir en Redes Sociales -->
                <div class="space-y-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#a88d82] flex items-center gap-1">
                        <span class="material-symbols-outlined text-[13px]">share</span>
                        Compartir
                    </p>
                    <button type="button"
                            x-data="{ comparteNativo: (typeof navigator !== 'undefined' && !!navigator.share) }"
                            x-show="comparteNativo"
                            style="display: none;"
                            x-on:click="navigator.share({title: {{ Js::from($promocion->titulo) }}, text: {{ Js::from('¡Mira esta promoción exclusiva en RestoMaster Provenza! '.$promocion->titulo) }}, url: {{ Js::from($urlCanonica) }}}).then(() => $wire.registrarDifusion({{ $promocion->id }}, 'social_nativo')).catch(() => {})"
                            class="w-full min-h-[44px] rounded-xl bg-[#e0442e]/15 hover:bg-[#e0442e]/25 border border-[#e0442e]/40 text-[#ff7e67] font-bold text-xs flex items-center justify-center gap-2 transition-all">
                        <span class="material-symbols-outlined text-[18px]">ios_share</span>
                        <span>Compartir</span>
                    </button>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <a href="https://wa.me/?text={{ urlencode('¡Mira esta promoción exclusiva en RestoMaster Provenza! ' . $promocion->titulo . ' 👉 ' . url()->current()) }}"
                           target="_blank"
                           wire:click="registrarDifusion({{ $promocion->id }}, 'social_whatsapp')"
                           class="min-h-[44px] rounded-xl bg-emerald-600/15 hover:bg-emerald-600/25 border border-emerald-500/30 text-emerald-400 font-bold text-xs flex items-center justify-center gap-2 transition-all">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            <span>WhatsApp</span>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlCanonica) }}&quote={{ urlencode($promocion->titulo . ' — ¡Promoción exclusiva en RestoMaster Provenza!') }}"
                           target="_blank"
                           rel="noopener"
                           wire:click="registrarDifusion({{ $promocion->id }}, 'social_facebook')"
                           class="min-h-[44px] rounded-xl bg-blue-600/15 hover:bg-blue-600/25 border border-blue-500/30 text-blue-400 font-bold text-xs flex items-center justify-center gap-2 transition-all">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            <span>Facebook</span>
                        </a>
                        <button type="button"
                                x-data="{ copiado: false }"
                                x-on:click="navigator.clipboard.writeText('{{ url()->current() }}').then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })"
                                wire:click="registrarDifusion({{ $promocion->id }}, 'social_instagram')"
                                class="min-h-[44px] rounded-xl bg-gradient-to-r from-purple-600/15 to-pink-600/15 hover:from-purple-600/25 hover:to-pink-600/25 border border-purple-500/30 text-purple-300 font-bold text-xs flex items-center justify-center gap-2 transition-all">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.948-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            <span x-text="copiado ? '¡Link copiado!' : 'Instagram'">Instagram</span>
                        </button>
                    </div>
                    <p class="text-[10px] text-[#a88d82] text-center sm:text-left">Toca Instagram para copiar el link y pegarlo en tu historia.</p>
                </div>
            </div>
        </div>
    </article>
</div>
