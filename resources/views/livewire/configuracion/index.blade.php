<?php

use App\Services\ConfiguracionService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $tabActiva = 'dian';

    public array $dianForm = [];
    public array $empresaForm = [];
    public array $reservasForm = [];
    public string $webhookToken = '';

    public function mount(): void
    {
        $svc = app(ConfiguracionService::class);
        $this->dianForm = [
            'razon_social' => $svc->obtener('general', 'razon_social', ''),
            'nit' => $svc->obtener('general', 'nit', ''),
            'regimen' => $svc->obtener('general', 'regimen', 'Común'),
            'ambiente' => $svc->obtener('dian', 'ambiente', 'habilitacion'),
            'tipo_documento' => $svc->obtener('dian', 'tipo_documento', '01'),
            'resolucion_numero' => $svc->obtener('dian', 'resolucion_numero', ''),
            'resolucion_fecha' => $svc->obtener('dian', 'resolucion_fecha'),
            'prefijo' => $svc->obtener('dian', 'prefijo', 'MP'),
            'desde' => $svc->obtener('dian', 'desde'),
            'hasta' => $svc->obtener('dian', 'hasta'),
            'vigente' => (bool) $svc->obtener('dian', 'vigente', false),
            'envio_activo' => (bool) $svc->obtener('dian', 'envio_activo', false),
        ];
        $this->empresaForm = [
            'direccion' => $svc->obtener('general', 'direccion', ''),
            'telefono' => $svc->obtener('general', 'telefono', ''),
        ];
        $this->reservasForm = [
            'webhook_activo' => (bool) $svc->obtener('reservas', 'webhook_activo', false),
        ];
        $this->webhookToken = $svc->obtener('reservas', 'webhook_token', '');
    }

    public function guardarDian(): void
    {
        $validated = $this->validate([
            'dianForm.razon_social' => ['required', 'string', 'max:255'],
            'dianForm.nit' => ['nullable', 'string', 'max:30'],
            'dianForm.regimen' => ['required', 'string', 'max:60'],
            'dianForm.ambiente' => ['required', 'in:habilitacion,produccion'],
            'dianForm.tipo_documento' => ['required', 'string', 'max:4'],
            'dianForm.resolucion_numero' => ['nullable', 'string', 'max:30'],
            'dianForm.prefijo' => ['nullable', 'string', 'max:10'],
        ]);

        $svc = app(ConfiguracionService::class);
        foreach (['razon_social', 'nit', 'regimen'] as $k) {
            $svc->guardar('general', $k, $validated['dianForm'][$k]);
        }
        foreach (['ambiente', 'tipo_documento', 'resolucion_numero', 'resolucion_fecha', 'prefijo', 'desde', 'hasta', 'vigente', 'envio_activo'] as $k) {
            $svc->guardar('dian', $k, $validated['dianForm'][$k] ?? $this->dianForm[$k]);
        }
        session()->flash('status', 'Configuración DIAN guardada.');
    }

    public function guardarEmpresa(): void
    {
        $validated = $this->validate([
            'empresaForm.direccion' => ['nullable', 'string', 'max:255'],
            'empresaForm.telefono' => ['nullable', 'string', 'max:30'],
        ]);
        $svc = app(ConfiguracionService::class);
        $svc->guardar('general', 'direccion', $validated['empresaForm']['direccion']);
        $svc->guardar('general', 'telefono', $validated['empresaForm']['telefono']);
        session()->flash('status', 'Datos de empresa guardados.');
    }

    public function toggleWebhook(): void
    {
        $this->validate(['reservasForm.webhook_activo' => ['boolean']]);
        app(ConfiguracionService::class)->guardar('reservas', 'webhook_activo', (bool) $this->reservasForm['webhook_activo']);
        session()->flash('status', 'Estado del webhook actualizado.');
    }

    public function regenerarToken(): void
    {
        $this->webhookToken = app(ConfiguracionService::class)->regenerarWebhookToken();
        session()->flash('status', 'Token de webhook regenerado.');
    }

    public function with(): array
    {
        return ['pieTicket' => app(ConfiguracionService::class)->obtener('impresion', 'pie_ticket', '')];
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-[24px] text-primary">settings</span>
        <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Configuración del Sistema</h1>
        <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">CFG-01</span>
    </div>
</x-slot>

<div class="space-y-6">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @if (session('status'))
        <div class="rounded-xl border border-secondary/40 bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach (['dian' => 'DIAN / Facturación', 'empresa' => 'Empresa', 'reservas' => 'Reservas & Webhook'] as $tab => $label)
            <button wire:click="$set('tabActiva', '{{ $tab }}')"
                class="rounded-xl px-4 py-2 text-xs font-bold {{ $tabActiva === $tab ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant border border-outline-variant/20' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tabActiva === 'dian')
        <form wire:submit="guardarDian" class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 space-y-4">
            <p class="text-[11px] text-on-surface-variant">Parametrización del emisor y resolución. La integración con el proveedor DIAN se conectará en una fase posterior.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="text-xs font-bold text-on-surface-variant">Razón social</label>
                    <input type="text" wire:model="dianForm.razon_social" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">NIT</label>
                    <input type="text" wire:model="dianForm.nit" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Régimen</label>
                    <input type="text" wire:model="dianForm.regimen" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Ambiente</label>
                    <select wire:model="dianForm.ambiente" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0">
                        <option value="habilitacion">Habilitación</option>
                        <option value="produccion">Producción</option>
                    </select></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Tipo documento</label>
                    <input type="text" wire:model="dianForm.tipo_documento" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">N° resolución</label>
                    <input type="text" wire:model="dianForm.resolucion_numero" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Prefijo</label>
                    <input type="text" wire:model="dianForm.prefijo" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Fecha resolución</label>
                    <input type="date" wire:model="dianForm.resolucion_fecha" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Rango desde</label>
                    <input type="text" wire:model="dianForm.desde" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Rango hasta</label>
                    <input type="text" wire:model="dianForm.hasta" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="dianForm.vigente" class="rounded" />
                <label class="text-xs font-bold text-on-surface-variant">Resolución vigente</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="dianForm.envio_activo" class="rounded" />
                <label class="text-xs font-bold text-on-surface-variant">Facturación electrónica activa</label>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-5 py-3 text-sm font-black text-on-primary">Guardar DIAN</button>
        </form>
    @endif

    @if ($tabActiva === 'empresa')
        <form wire:submit="guardarEmpresa" class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="text-xs font-bold text-on-surface-variant">Dirección</label>
                    <input type="text" wire:model="empresaForm.direccion" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Teléfono</label>
                    <input type="text" wire:model="empresaForm.telefono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
            </div>
            <p class="text-[11px] text-on-surface-variant">Pie de ticket actual: <code class="font-mono">{{ $pieTicket }}</code></p>
            <button type="submit" class="rounded-xl bg-primary px-5 py-3 text-sm font-black text-on-primary">Guardar Empresa</button>
        </form>
    @endif

    @if ($tabActiva === 'reservas')
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 space-y-4">
            <div>
                <label class="text-xs font-bold text-on-surface-variant">Token del webhook de reservas (n8n / WhatsApp)</label>
                <div class="mt-1 flex items-center gap-2">
                    <code class="flex-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono break-all">{{ $webhookToken }}</code>
                    <button type="button" wire:click="regenerarToken" class="rounded-xl bg-secondary px-4 py-2 text-xs font-bold text-white">Regenerar</button>
                </div>
                <p class="mt-1 text-[11px] text-on-surface-variant">Endpoint: <code class="font-mono">POST /api/reservas</code> con header <code class="font-mono">X-Webhook-Token</code>.</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="reservasForm.webhook_activo" wire:change="toggleWebhook" class="rounded" />
                <label class="text-xs font-bold text-on-surface-variant">Webhook de reservas activo</label>
            </div>
        </div>
    @endif
</div>