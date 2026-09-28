<!-- Modal de Apertura Rápida de Turno de Caja desde POS -->
@if($mostrarModalAperturaPos)
    <div x-data @keydown.escape.window="$wire.set('mostrarModalAperturaPos', false)" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
        <div role="dialog" aria-modal="true" aria-labelledby="modal-apertura-pos-title" class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">lock_open</span>
                    </div>
                    <div>
                        <h3 id="modal-apertura-pos-title" class="text-base font-extrabold text-on-surface">Apertura Rápida de Caja</h3>
                        <p class="text-[11px] text-on-surface-variant">Ingresa la base inicial de efectivo para habilitar el cobro</p>
                    </div>
                </div>
                <button wire:click="$set('mostrarModalAperturaPos', false)" aria-label="Cerrar modal" class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-full text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Terminal de Caja:</label>
                    <select 
                        wire:model="cajaAperturaId" 
                        class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                    >
                        @foreach($cajasDisponibles as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                        @endforeach
                    </select>
                    @error('cajaAperturaId') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Fondo Inicial / Base de Efectivo en Gaveta:</label>
                    <div class="relative mt-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-on-surface-variant">$</span>
                        <input 
                            type="text" 
                            inputmode="decimal" 
                            data-miles data-decimales="0"
                            wire:model="baseAperturaPos" 
                            class="w-full rounded-xl border border-surface-container-high bg-surface-container-low pl-7 pr-3 py-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                            placeholder="150000"
                        />
                    </div>
                    @error('baseAperturaPos') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    <div class="flex gap-1.5 mt-2">
                        <button type="button" wire:click="$set('baseAperturaPos', 100000)" class="px-2 py-1 rounded-lg bg-surface-container text-[11px] font-bold text-on-surface-variant hover:text-on-surface border border-surface-container-high cursor-pointer">$100k</button>
                        <button type="button" wire:click="$set('baseAperturaPos', 150000)" class="px-2 py-1 rounded-lg bg-surface-container text-[11px] font-bold text-on-surface-variant hover:text-on-surface border border-surface-container-high cursor-pointer">$150k</button>
                        <button type="button" wire:click="$set('baseAperturaPos', 200000)" class="px-2 py-1 rounded-lg bg-surface-container text-[11px] font-bold text-on-surface-variant hover:text-on-surface border border-surface-container-high cursor-pointer">$200k</button>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Notas de Apertura (Opcional):</label>
                    <input 
                        type="text" 
                        wire:model="notasAperturaPos" 
                        placeholder="Ej: Base de cambio entregada para apertura de turno"
                        class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0"
                    />
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-2">
                <button 
                    wire:click="$set('mostrarModalAperturaPos', false)" 
                    class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface cursor-pointer"
                >
                    Cancelar
                </button>
                <button 
                    wire:click="abrirTurnoDesdePos" 
                    class="rounded-xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container active:scale-95 transition-all cursor-pointer"
                >
                    ✓ Abrir Turno y Cobrar
                </button>
            </div>
        </div>
    </div>
@endif
