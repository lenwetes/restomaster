<?php

use Livewire\Volt\Component;

new class extends Component
{
    public bool $mostrar = false;
    public float $total = 0.0;
    public float $subtotal = 0.0;
    public string $metodoPago = 'efectivo';
    public $montoPagado = 0.0;
    public $montoEfectivoMixto = 0.0;
    public string $tipoPropina = 'cero';
    public $montoPropina = 0.0;
    public $porcentajePropina = 0.0;

    public function mount(
        bool $mostrar = false,
        float $total = 0.0,
        float $subtotal = 0.0,
        string $metodoPago = 'efectivo',
        float $montoPagado = 0.0,
        string $tipoPropina = 'cero',
        float $montoPropina = 0.0
    ): void {
        $this->mostrar = $mostrar;
        $this->total = $total;
        $this->subtotal = $subtotal;
        $this->metodoPago = $metodoPago;
        $this->montoPagado = $montoPagado;
        $this->tipoPropina = $tipoPropina;
        $this->montoPropina = $montoPropina;
    }

    public function seleccionarPropina(string $tipo): void
    {
        $this->tipoPropina = $tipo;

        if ($tipo === 'cero') {
            $this->montoPropina = 0.0;
            $this->porcentajePropina = 0.0;
        } elseif ($tipo === 'diez_porciento') {
            $this->montoPropina = round($this->total * 0.10);
            $this->porcentajePropina = 10.0;
        } elseif ($tipo === 'personalizada') {
            $this->porcentajePropina = null;
        }

        $this->montoPagado = $this->total + (float) $this->montoPropina;
        $this->dispatch('propina-cambiada', [
            'tipoPropina' => $this->tipoPropina,
            'montoPropina' => $this->montoPropina,
            'porcentajePropina' => $this->porcentajePropina,
        ]);
    }

    public function sumarMonto(float $cantidad): void
    {
        $this->montoPagado = max(0.0, (float) $this->montoPagado + max(0.0, $cantidad));
        $this->dispatch('monto-pagado-cambiado', ['monto' => $this->montoPagado]);
    }

    public function setMontoExacto(): void
    {
        $this->montoPagado = $this->total + (float) $this->montoPropina;
        $this->dispatch('monto-pagado-cambiado', ['monto' => $this->montoPagado]);
    }

    public function cerrar(): void
    {
        $this->mostrar = false;
        $this->dispatch('cerrar-modal-cobro');
    }

    public function confirmarCobro(): void
    {
        $this->dispatch('ejecutar-cobro-pos');
    }
}; ?>

<div>
    @if($mostrar)
        <div x-data @keydown.escape.window="$wire.cerrar()" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-cobro-pos-title" class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">point_of_sale</span>
                        </div>
                        <h3 id="modal-cobro-pos-title" class="text-base font-extrabold text-on-surface">Terminal de Cobro</h3>
                    </div>
                    <button wire:click="cerrar" aria-label="Cerrar modal de cobro" class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-full text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <!-- Propina del Servicio (Ley 1935 de 2018 - Voluntaria) -->
                    <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-on-surface flex items-center gap-1">
                                <span class="material-symbols-outlined text-primary text-[16px]">volunteer_activism</span>
                                Propina Voluntaria
                            </span>
                            <span class="text-xs font-black text-primary font-mono">+ ${{ number_format((float) $montoPropina, 0, ',', '.') }}</span>
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
                                10% (${{ number_format(round($total * 0.10), 0, ',', '.') }})
                            </button>
                            <button 
                                type="button"
                                wire:click="seleccionarPropina('personalizada')" 
                                class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition cursor-pointer {{ $tipoPropina === 'personalizada' ? 'border-primary bg-primary text-on-primary shadow-xs' : 'border-surface-container-high bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                            >
                                Libre
                            </button>
                        </div>
                    </div>

                    <!-- Métodos de Pago -->
                    <div class="grid grid-cols-4 gap-2">
                        @foreach(['efectivo' => ['payments', 'Efectivo'], 'tarjeta' => ['credit_card', 'Tarjeta'], 'transferencia' => ['account_balance', 'Transf.'], 'mixto' => ['call_split', 'Mixto']] as $key => [$icono, $label])
                            <button 
                                type="button"
                                wire:click="$set('metodoPago', '{{ $key }}')" 
                                class="flex flex-col items-center justify-center p-2.5 rounded-2xl border text-xs font-black transition cursor-pointer {{ $metodoPago === $key ? 'border-primary bg-primary/10 text-primary shadow-sm' : 'border-surface-container-high bg-surface-container text-on-surface-variant hover:text-on-surface' }}"
                            >
                                <span class="material-symbols-outlined text-[22px] mb-1">{{ $icono }}</span>
                                <span>{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>

                    <!-- Botones de Dinero Rápido en Efectivo -->
                    @if($metodoPago === 'efectivo' || $metodoPago === 'mixto')
                        <div class="grid grid-cols-4 gap-1.5">
                            <button type="button" wire:click="setMontoExacto" class="py-1.5 px-2 rounded-xl text-[11px] font-bold bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition">Exacto</button>
                            <button type="button" wire:click="sumarMonto(20000)" class="py-1.5 px-2 rounded-xl text-[11px] font-bold bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition">+20k</button>
                            <button type="button" wire:click="sumarMonto(50000)" class="py-1.5 px-2 rounded-xl text-[11px] font-bold bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition">+50k</button>
                            <button type="button" wire:click="sumarMonto(100000)" class="py-1.5 px-2 rounded-xl text-[11px] font-bold bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition">+100k</button>
                        </div>
                    @endif

                    <!-- Botón de Confirmación -->
                    <button 
                        type="button"
                        wire:click="confirmarCobro"
                        class="w-full min-h-[50px] rounded-2xl bg-primary text-on-primary font-black text-sm shadow-lg shadow-primary/20 hover:brightness-110 active:scale-[0.98] transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[20px]">check_circle</span>
                        <span>Confirmar y Procesar Cobro</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
