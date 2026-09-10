<?php

use App\Models\CuentaPorPagar;
use App\Services\CuentasPorPagarService;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $cuentaPagoId = null;

    public array $pagoForm = [
        'monto' => '',
        'metodo_pago' => 'efectivo',
    ];

    public array $crearForm = [
        'proveedor_nombre' => '',
        'proveedor_nit' => '',
        'concepto' => '',
        'monto_total' => '',
        'fecha_emision' => '',
        'fecha_vencimiento' => '',
    ];

    public bool $modalCrear = false;

    public function mount(): void
    {
        $this->crearForm['fecha_emision'] = now()->toDateString();
    }

    #[Computed]
    public function cuentas(): array
    {
        $service = app(CuentasPorPagarService::class);

        return [
            'pendientes' => CuentaPorPagar::with('usuario')
                ->where('estado', 'pendiente')
                ->orderByDesc('fecha_emision')
                ->get(),
            'pagadas' => CuentaPorPagar::with('usuario')
                ->where('estado', 'pagada')
                ->latest()
                ->take(20)
                ->get(),
            'saldos' => $service->saldosPorProveedor(),
            'total_pendiente' => (float) CuentaPorPagar::where('estado', 'pendiente')->sum('saldo_pendiente'),
        ];
    }

    public function abrirPago(int $cuentaId): void
    {
        $this->cuentaPagoId = $cuentaId;
        $this->reset('pagoForm');
        $this->pagoForm['metodo_pago'] = 'efectivo';
    }

    public function registrarPago(): void
    {
        $this->authorize('registrarPago', CuentaPorPagar::class);

        $validated = $this->validate([
            'cuentaPagoId' => ['required', 'integer'],
            'pagoForm.monto' => ['required', 'numeric', 'min:0.01'],
            'pagoForm.metodo_pago' => ['required', 'string'],
        ]);

        $cuenta = CuentaPorPagar::findOrFail($this->cuentaPagoId);
        $saldo = (float) $cuenta->saldo_pendiente;

        if ($validated['pagoForm']['monto'] > $saldo) {
            $this->addError('pagoForm.monto', 'El pago no puede superar el saldo pendiente.');
            $this->dispatch('open-modal', 'modal-pago');

            return;
        }

        app(CuentasPorPagarService::class)->registrarPago(
            $cuenta,
            (float) $validated['pagoForm']['monto'],
            metodoPago: $validated['pagoForm']['metodo_pago'],
        );

        $this->reset('cuentaPagoId', 'pagoForm');
        $this->dispatch('close-modal', 'modal-pago');
        session()->flash('status', 'Abono registrado.');
    }

    public function abrirCrear(): void
    {
        $this->modalCrear = true;
    }

    public function crearCuenta(): void
    {
        $this->authorize('create', CuentaPorPagar::class);

        $validated = $this->validate([
            'crearForm.proveedor_nombre' => ['required', 'string', 'max:255'],
            'crearForm.concepto' => ['required', 'string', 'max:255'],
            'crearForm.monto_total' => ['required', 'numeric', 'min:0.01'],
            'crearForm.fecha_emision' => ['required', 'date'],
        ]);

        app(CuentasPorPagarService::class)->crear($validated['crearForm']);

        $this->reset('crearForm', 'modalCrear');
        $this->crearForm['fecha_emision'] = now()->toDateString();
        session()->flash('status', 'Cuenta por pagar creada.');
    }

    public function cerrarModales(): void
    {
        $this->reset('cuentaPagoId', 'pagoForm', 'modalCrear');
    }
}; ?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">account_balance_wallet</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Cuentas por Pagar
                </h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">
                    CXP-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Obligaciones con proveedores y saldos pendientes
            </p>
        </div>
        <button wire:click="abrirCrear" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Nueva cuenta
        </button>
    </header>

    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @if (session('status'))
        <div class="rounded-xl border border-secondary/40 bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary">
            {{ session('status') }}
        </div>
    @endif

    <!-- Saldos por proveedor -->
    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-on-surface">Saldos por proveedor</h2>
            <span class="text-2xl font-black text-primary">${{ number_format($this->cuentas['total_pendiente'], 0, ',', '.') }}</span>
        </div>

        @if ($this->cuentas['saldos']->isEmpty())
            <p class="mt-4 text-xs text-on-surface-variant">Sin obligaciones pendientes por proveedor.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wider text-on-surface-variant border-b border-outline-variant/20">
                            <th class="py-2 pr-3">Proveedor</th>
                            <th class="py-2 pr-3">NIT</th>
                            <th class="py-2 pr-3 text-right">Cuentas</th>
                            <th class="py-2 text-right">Monto total</th>
                            <th class="py-2 text-right">Saldo pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->cuentas['saldos'] as $saldo)
                            <tr class="border-b border-outline-variant/10 last:border-0">
                                <td class="py-2 pr-3 font-bold text-on-surface">{{ $saldo->proveedor_nombre }}</td>
                                <td class="py-2 pr-3 text-on-surface-variant">{{ $saldo->proveedor_nit ?? '—' }}</td>
                                <td class="py-2 pr-3 text-right text-on-surface-variant">{{ (int) $saldo->cuentas }}</td>
                                <td class="py-2 pr-3 text-right text-on-surface">${{ number_format((float) $saldo->monto_total, 0, ',', '.') }}</td>
                                <td class="py-2 text-right font-black text-primary">${{ number_format((float) $saldo->saldo_pendiente, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Cuentas pendientes -->
    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
        <h2 class="text-sm font-extrabold uppercase tracking-wider text-on-surface">Cuentas pendientes</h2>

        @if ($this->cuentas['pendientes']->isEmpty())
            <p class="mt-4 text-xs text-on-surface-variant">No hay cuentas pendientes.</p>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($this->cuentas['pendientes'] as $cuenta)
                    <div class="rounded-2xl border border-outline-variant/10 bg-surface-container-low p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-extrabold text-on-surface truncate">{{ $cuenta->concepto }}</p>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    {{ $cuenta->proveedor_nombre }}
                                    @if ($cuenta->fecha_vencimiento)
                                        · Vence {{ $cuenta->fecha_vencimiento->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-[10px] text-on-surface-variant uppercase tracking-wider">Saldo</p>
                                    <p class="text-base font-black text-primary">${{ number_format((float) $cuenta->saldo_pendiente, 0, ',', '.') }}</p>
                                </div>
                                <button wire:click="abrirPago({{ $cuenta->id }})" class="rounded-xl bg-secondary px-3 py-2 text-xs font-bold text-white inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]">payments</span>
                                    Abonar
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Recientes pagadas -->
    @if ($this->cuentas['pagadas']->isNotEmpty())
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-on-surface">Pagadas recientemente</h2>
            <ul class="mt-3 divide-y divide-outline-variant/10 text-xs">
                @foreach ($this->cuentas['pagadas'] as $cuenta)
                    <li class="flex justify-between py-2">
                        <span class="truncate pr-3 text-on-surface-variant">{{ $cuenta->concepto }} — {{ $cuenta->proveedor_nombre }}</span>
                        <span class="text-secondary font-bold">${{ number_format((float) $cuenta->monto_total, 0, ',', '.') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Modal registrar pago -->
    @if ($cuentaPagoId)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" id="modal-pago" wire:click.self="cerrarModales">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl" wire:key="modal-pago">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-on-surface">Registrar abono</h3>
                    <button wire:click="cerrarModales" class="text-on-surface-variant">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                @error('pagoForm.monto')
                    <p class="mt-3 rounded-xl bg-error/10 px-3 py-2 text-xs font-bold text-error">{{ $message }}</p>
                @enderror

                <form wire:submit="registrarAbono" class="mt-4 space-y-4">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Monto del abono</label>
                        <input type="number" step="0.01" min="0.01" wire:model="pagoForm.monto"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Método de pago</label>
                        <select wire:model="pagoForm.metodo_pago"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0">
                            <option value="transferencia">Transferencia</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Comprobante / Referencia</label>
                        <input type="text" wire:model="pagoForm.comprobante" placeholder="Ej. TRX-12345"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Notas</label>
                        <textarea wire:model="pagoForm.notas" rows="2"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary">
                        Registrar abono
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal nueva cuenta -->
    @if ($modalCrear)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" id="modal-crear" wire:click.self="cerrarModales">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl" wire:key="modal-crear">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-on-surface">Nueva cuenta por pagar</h3>
                    <button wire:click="cerrarModales" class="text-on-surface-variant">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form wire:submit="guardarCuenta" class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Proveedor</label>
                        <input type="text" wire:model="crearForm.proveedor_nombre" required
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">NIT / Documento</label>
                            <input type="text" wire:model="crearForm.proveedor_nit"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Factura #</label>
                            <input type="text" wire:model="crearForm.numero_factura"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Concepto</label>
                        <input type="text" wire:model="crearForm.concepto" required
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Monto total</label>
                            <input type="number" step="0.01" min="0.01" wire:model="crearForm.monto_total" required
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Emisión</label>
                            <input type="date" wire:model="crearForm.fecha_emision"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Fecha de vencimiento</label>
                        <input type="date" wire:model="crearForm.fecha_vencimiento"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary">
                        Guardar cuenta
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>