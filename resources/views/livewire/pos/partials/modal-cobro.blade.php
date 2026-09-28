<!-- Modal de Cobro Táctil (Stitch POS-02 Billing Console) -->
@if($mostrarModalCobro)
    <div x-data @keydown.escape.window="$wire.set('mostrarModalCobro', false)" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
        <div role="dialog" aria-modal="true" aria-labelledby="modal-cobro-pos-title" class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">point_of_sale</span>
                    </div>
                    <h3 id="modal-cobro-pos-title" class="text-base font-extrabold text-on-surface">Terminal de Cobro</h3>
                </div>
                <button wire:click="$set('mostrarModalCobro', false)" aria-label="Cerrar modal de cobro" class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-full text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <!-- Propina del Servicio (Ley 1935 de 2018 - Voluntaria) -->
                <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-on-surface flex items-center gap-1">
                            <span class="material-symbols-outlined text-primary text-[16px]">volunteer_activism</span>
                            Propina del Servicio (Voluntaria)
                        </span>
                        <span class="text-xs font-black text-primary font-mono">+ ${{ number_format($montoPropina, 0, ',', '.') }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button 
                            type="button"
                            wire:click="seleccionarPropina('cero')" 
                            class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition cursor-pointer {{ $tipoPropina === 'cero' ? 'border-primary bg-primary text-on-primary shadow-xs' : 'border-surface-container-high bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                        >
                            Sin Propina ($0)
                        </button>
                        <button 
                            type="button"
                            wire:click="seleccionarPropina('diez_porciento')" 
                            class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition cursor-pointer {{ $tipoPropina === 'diez_porciento' ? 'border-primary bg-primary text-on-primary shadow-xs' : 'border-surface-container-high bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                        >
                            10% (${{ number_format(round($this->total * 0.10), 0, ',', '.') }})
                        </button>
                        <button 
                            type="button"
                            wire:click="seleccionarPropina('personalizada')" 
                            class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition cursor-pointer {{ $tipoPropina === 'personalizada' ? 'border-primary bg-primary text-on-primary shadow-xs' : 'border-surface-container-high bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                        >
                            Valor Libre
                        </button>
                    </div>
                    @if($tipoPropina === 'personalizada')
                        <div class="pt-1 flex items-center gap-2">
                            <span class="text-xs text-on-surface-variant font-bold">$</span>
                            <input 
                                type="text" 
                                inputmode="decimal" 
                                data-miles data-decimales="0"
                                min="0"
                                wire:model.live.debounce.300ms="montoPropina" 
                                placeholder="Monto voluntario comensal..."
                                class="w-full rounded-xl border border-surface-container-high bg-surface-container px-3 py-1.5 text-xs font-bold font-mono text-on-surface focus:border-primary focus:ring-0"
                            />
                        </div>
                    @endif
                </div>

                <!-- Total to pay banner -->
                <div class="rounded-2xl bg-surface-container-low border border-surface-container-high p-3.5 text-center">
                    <div class="flex items-center justify-between text-[11px] text-on-surface-variant font-semibold px-1">
                        <span>Consumo: ${{ number_format($this->total, 0, ',', '.') }}</span>
                        <span>Propina: ${{ number_format($montoPropina, 0, ',', '.') }}</span>
                    </div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant block mt-1">Total a Cancelar</span>
                    <p class="font-mono text-3xl font-black text-primary mt-0.5">${{ number_format($this->totalConPropina, 0, ',', '.') }}</p>
                </div>

                <!-- Payment Method Picker -->
                <div>
                    <span class="text-xs font-bold text-on-surface-variant">Método de Pago:</span>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <button 
                            wire:click="$set('metodoPago', 'efectivo')"
                            class="flex items-center justify-center gap-1 rounded-xl p-2.5 text-xs font-extrabold transition border {{ $metodoPago === 'efectivo' ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                        >
                            <span class="material-symbols-outlined text-[16px]">payments</span>
                            <span>Efectivo</span>
                        </button>
                        <button 
                            wire:click="$set('metodoPago', 'tarjeta')"
                            class="flex items-center justify-center gap-1 rounded-xl p-2.5 text-xs font-extrabold transition border {{ $metodoPago === 'tarjeta' ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                        >
                            <span class="material-symbols-outlined text-[16px]">credit_card</span>
                            <span>Tarjeta</span>
                        </button>
                        <button 
                            wire:click="$set('metodoPago', 'mixto')"
                            class="flex items-center justify-center gap-1 rounded-xl p-2.5 text-xs font-extrabold transition border {{ $metodoPago === 'mixto' ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                        >
                            <span class="material-symbols-outlined text-[16px]">balance</span>
                            <span>Mixto</span>
                        </button>
                    </div>
                </div>

                <!-- Mixed Payment Input -->
                @if($metodoPago === 'mixto')
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Efectivo (pago mixto):</label>
                        <input 
                            type="text" 
                            inputmode="decimal" 
                            data-miles data-decimales="0"
                            min="0"
                            max="{{ (int) $this->total }}"
                            wire:model.live.debounce.500ms="montoEfectivoMixto" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                        />
                        <p class="mt-1 text-[10px] font-semibold text-on-surface-variant">
                            El resto (${{ number_format(max(0, (float) $this->total - (float) $this->montoEfectivoMixto), 0, ',', '.') }}) se registra como tarjeta.
                        </p>
                    </div>
                @endif

                <!-- Cash Input & Quick Bills -->
                @if($metodoPago === 'efectivo')
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Monto Entregado:</label>
                        <input 
                            type="text" 
                            inputmode="decimal" 
                            data-miles data-decimales="0"
                            data-monto-entregado
                            wire:model.live.debounce.500ms="montoPagado" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                        />

                        <!-- Quick denomination buttons -->
                        <div class="mt-2.5 grid grid-cols-4 gap-1.5">
                            <button wire:click="setMontoExacto" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                Exacto
                            </button>
                            <button wire:click="sumarMonto(20000.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                $20.000
                            </button>
                            <button wire:click="sumarMonto(50000.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                $50.000
                            </button>
                            <button wire:click="sumarMonto(100000.0)" class="rounded-lg bg-surface-container p-2 text-xs font-bold text-on-surface hover:bg-surface-container-high">
                                $100.000
                            </button>
                        </div>

                        <!-- Change calculation -->
                        <div class="mt-3 flex items-center justify-between rounded-xl bg-secondary-container/40 border border-secondary/30 p-3 text-xs font-bold text-on-secondary-container">
                            <span>Cambio a Devolver:</span>
                            <span class="font-mono text-xl font-black text-secondary">${{ number_format($this->cambio, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Error Feedback in Billing Modal -->
            @error('montoPagado')
                <div class="mt-4 rounded-2xl bg-error/15 border border-error/30 p-3 text-xs font-bold text-error flex items-center gap-2 animate-fade-in shadow-xs">
                    <span class="material-symbols-outlined text-[20px] text-error shrink-0">error</span>
                    <span class="flex-1">{{ $message }}</span>
                </div>
            @enderror

            @if($errors->any() && !$errors->has('montoPagado'))
                <div class="mt-4 rounded-2xl bg-error/15 border border-error/30 p-3 text-xs font-bold text-error space-y-1 animate-fade-in shadow-xs">
                    @foreach($errors->all() as $error)
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px] text-error shrink-0">warning</span>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Modal Action Buttons -->
            <div class="mt-6 grid grid-cols-2 gap-2">
                <button 
                    type="button"
                    wire:click="$set('mostrarModalCobro', false)" 
                    class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface cursor-pointer"
                >
                    Cancelar
                </button>
                <button 
                    type="button"
                    wire:click="procesarCobro" 
                    wire:loading.attr="disabled"
                    @disabled($this->comandaActivaBloqueaCobro())
                    title="{{ $this->comandaActivaBloqueaCobro() ? 'La comanda sigue activa en cocina: solo se puede cobrar cuando todo fue servido o cancelado.' : 'Confirmar cobro' }}"
                    class="rounded-xl bg-secondary py-3 text-xs font-extrabold text-on-secondary shadow-md hover:bg-secondary-fixed-dim disabled:opacity-40 flex items-center justify-center gap-1.5 cursor-pointer disabled:cursor-not-allowed transition-all"
                >
                    <span wire:loading.remove wire:target="procesarCobro">✓ Confirmar y Emitir</span>
                    <span wire:loading wire:target="procesarCobro" class="inline-flex items-center gap-1.5">
                        <svg class="animate-spin h-4 w-4 text-on-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Procesando...</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif
