<?php

use App\Models\Promocion;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.publico')] class extends Component
{
    public Promocion $promocion;

    public function mount(string $slug): void
    {
        $this->promocion = Promocion::where('slug', $slug)
            ->where('activo', true)
            ->firstOrFail();
    }
};
?>

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

            <!-- Llamado a la Acción y Compartir por WhatsApp -->
            <div class="pt-6 border-t border-[#3e2920]/80 flex flex-col sm:flex-row items-center justify-between gap-4">
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

                <!-- Botón de Compartir con Amigos por WhatsApp -->
                <a href="https://wa.me/?text={{ urlencode('¡Mira esta promoción exclusiva en RestoMaster Provenza! ' . $promocion->titulo . ' 👉 ' . url()->current()) }}" 
                   target="_blank" 
                   class="w-full sm:w-auto py-3.5 px-5 rounded-2xl bg-emerald-600/15 hover:bg-emerald-600/25 border border-emerald-500/30 text-emerald-400 font-bold text-xs flex items-center justify-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-[18px]">share</span>
                    <span>Compartir por WhatsApp</span>
                </a>
            </div>
        </div>
    </article>
</div>
