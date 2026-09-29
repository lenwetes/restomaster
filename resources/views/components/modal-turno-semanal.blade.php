@props(['dias' => [], 'etiqueta' => '', 'mostrarPosponer' => true])

<div
    x-data="{ abierto: true }"
    x-show="abierto"
    x-on:mostrar-modal-turno-semanal.window="abierto = true"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4"
    role="dialog"
    aria-modal="true"
    aria-label="Tu horario semanal"
>
    <div class="w-full max-w-2xl rounded-3xl bg-surface-container-lowest p-5 sm:p-6 shadow-2xl border border-outline-variant/20 space-y-4 max-h-[calc(100vh-2rem)] overflow-y-auto">
        <div class="text-center border-b border-outline-variant/15 pb-3">
            <p class="text-sm font-black text-on-surface flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">calendar_month</span>
                📅 TU HORARIO — {{ $etiqueta }}
            </p>
            <p class="text-sm text-on-surface mt-1">Revísalo antes de iniciar tu turno de hoy.</p>
        </div>

        <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
            @foreach ($dias as $dia)
                <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-low px-1 py-2.5 text-center min-h-[44px]">
                    <span class="block text-[10px] sm:text-xs font-black uppercase text-on-surface">{{ mb_substr($dia['dia'] ?? '', 0, 3) }}</span>
                    @if ($dia['es_descanso'])
                        <span class="block text-lg sm:text-xl mt-1">😴</span>
                    @else
                        <span class="block text-xs sm:text-sm font-bold text-on-surface mt-1 break-words">{{ $dia['zona'] }}</span>
                        @if (! empty($dia['plantilla']))
                            <span class="block text-[10px] text-on-surface">{{ $dia['plantilla'] }}</span>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>

        <div class="flex flex-col sm:flex-row items-stretch justify-center gap-2 pt-1">
            <button
                type="button"
                wire:click="confirmarTurnoSemanal"
                class="rounded-2xl bg-green-600 px-6 py-3 text-sm font-black text-white shadow-md hover:bg-green-700 active:scale-95 transition cursor-pointer min-h-[44px]"
            >
                ✅ He leído y acepto mi turno
            </button>
            @if ($mostrarPosponer)
                <button
                    type="button"
                    wire:click="posponerTurnoSemanal"
                    class="rounded-2xl bg-surface-container-high border border-outline-variant/30 px-6 py-3 text-sm font-bold text-on-surface hover:bg-surface-container-highest active:scale-95 transition cursor-pointer min-h-[44px]"
                >
                    Ver más tarde
                </button>
            @endif
        </div>
    </div>
</div>
