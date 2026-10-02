<?php

use App\Models\Cliente;
use App\Services\ClienteService;
use Livewire\Volt\Component;

new class () extends Component {
    public ?int $clienteId = null;
    public string $nombreCliente = '';
    public string $telefonoCliente = '';
    public int $puntosDisponibles = 0;
    public array $sugerenciasClientes = [];
    public bool $mostrarSugerencias = false;

    public function mount(?int $clienteId = null, string $nombreCliente = '', string $telefonoCliente = '', int $puntosDisponibles = 0): void
    {
        $this->clienteId = $clienteId;
        $this->nombreCliente = $nombreCliente;
        $this->telefonoCliente = $telefonoCliente;
        $this->puntosDisponibles = $puntosDisponibles;
    }

    public function updatedNombreCliente(string $valor): void
    {
        $termino = trim($valor);
        if (mb_strlen($termino) >= 4) {
            $clienteService = app(ClienteService::class);
            $this->sugerenciasClientes = $clienteService->buscarPredictivo($termino, 6)
                ->map(fn (Cliente $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'telefono' => $c->telefono,
                    'email' => $c->email,
                    'tier' => $c->tier,
                    'badge_class' => $c->badgeTier()['color'] ?? '',
                    'badge_label' => $c->badgeTier()['label'] ?? strtoupper($c->tier ?? 'OCASIONAL'),
                    'puntos' => $c->puntos_fidelidad,
                ])
                ->all();
            $this->mostrarSugerencias = count($this->sugerenciasClientes) > 0;
        } else {
            $this->sugerenciasClientes = [];
            $this->mostrarSugerencias = false;
        }

        $this->dispatch('cliente-actualizado', [
            'clienteId' => $this->clienteId,
            'nombreCliente' => $this->nombreCliente,
        ]);
    }

    public function seleccionarCliente(int $id): void
    {
        $cliente = Cliente::with('direcciones')->find($id);
        if ($cliente) {
            $this->clienteId = $cliente->id;
            $this->nombreCliente = $cliente->nombre;
            $this->telefonoCliente = $cliente->telefono ?? '';
            $this->puntosDisponibles = $cliente->puntos_fidelidad ?? 0;
            $this->mostrarSugerencias = false;
            $this->sugerenciasClientes = [];

            $this->dispatch('cliente-seleccionado', [
                'clienteId' => $this->clienteId,
                'nombreCliente' => $this->nombreCliente,
                'telefonoCliente' => $this->telefonoCliente,
                'puntosDisponibles' => $this->puntosDisponibles,
            ]);
        }
    }

    public function desvincularCliente(): void
    {
        $this->clienteId = null;
        $this->nombreCliente = '';
        $this->telefonoCliente = '';
        $this->puntosDisponibles = 0;
        $this->sugerenciasClientes = [];
        $this->mostrarSugerencias = false;

        $this->dispatch('cliente-desvinculado');
    }
}; ?>

<div class="relative w-full">
    @if($clienteId)
        <div class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-xl bg-primary/10 border border-primary/20 text-on-surface">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="material-symbols-outlined text-primary text-[18px]">person</span>
                <span class="text-xs font-black truncate">{{ $nombreCliente }}</span>
                @if($puntosDisponibles > 0)
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black bg-primary text-on-primary">
                        {{ $puntosDisponibles }} pts
                    </span>
                @endif
            </div>
            <button 
                type="button" 
                wire:click="desvincularCliente" 
                class="text-on-surface-variant hover:text-error transition p-1"
                title="Desvincular comensal"
            >
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    @else
        <div class="relative">
            <div class="flex items-center gap-1.5 rounded-xl border border-surface-container-high bg-surface-container px-2.5 py-1.5">
                <span class="material-symbols-outlined text-on-surface-variant text-[18px]">person_search</span>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="nombreCliente"
                    placeholder="Comensal / Cliente (mín 4 letras)..." 
                    class="w-full bg-transparent text-xs font-medium text-on-surface placeholder:text-on-surface-variant focus:outline-hidden"
                />
                @if(!empty(trim($nombreCliente)))
                    <button type="button" wire:click="$set('nombreCliente', ''); $set('mostrarSugerencias', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[16px]">close</span>
                    </button>
                @endif
            </div>

            <!-- Menú predictivo -->
            @if($mostrarSugerencias && count($sugerenciasClientes) > 0)
                <div class="absolute left-0 right-0 top-full mt-1 z-50 rounded-2xl border border-surface-container-highest bg-surface-container-lowest shadow-2xl p-1.5 space-y-1">
                    <div class="px-2 py-1 text-[10px] font-black text-on-surface-variant uppercase tracking-wider">
                        Coincidencias ({{ count($sugerenciasClientes) }})
                    </div>
                    @foreach($sugerenciasClientes as $sug)
                        <button 
                            type="button"
                            wire:click="seleccionarCliente({{ $sug['id'] }})"
                            class="w-full flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-xl hover:bg-surface-container transition text-left cursor-pointer"
                        >
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-on-surface truncate">{{ $sug['nombre'] }}</div>
                                <div class="text-[10px] text-on-surface-variant">{{ $sug['telefono'] ?? 'Sin teléfono' }}</div>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black {{ $sug['badge_class'] }}">
                                {{ $sug['badge_label'] }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
