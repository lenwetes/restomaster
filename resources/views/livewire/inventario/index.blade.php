<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Insumo;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\MovimientoInventario;
use App\Services\InventarioService;

new
#[Layout('layouts.app')]
#[Title('INV-01 · Gestión de Inventario y Stock Crítico')]
class extends Component {
    public string $search = '';
    public string $selectedCategoria = 'todas';
    public string $selectedFiltro = 'todos'; // todos, criticos, por_agotar, optimo
    public ?int $selectedInsumoId = null;

    // Modals
    public bool $modalMermaOpen = false;
    public bool $modalCompraOpen = false;
    public bool $modalAjusteOpen = false;
    public bool $modalNuevoInsumoOpen = false;
    public bool $modalRecetaOpen = false;

    // Formulario Merma
    public float $mermaCantidad = 0.5;
    public string $mermaMotivo = 'Merma operativa por corte y preparación';

    // Formulario Compra
    public float $compraCantidad = 5.0;
    public float $compraCostoUnitario = 50.0;
    public string $compraProveedor = '';
    public string $compraFactura = '';

    // Formulario Ajuste
    public float $ajusteNuevoStock = 0.0;
    public string $ajusteMotivo = 'Ajuste por conteo físico de inventario';

    // Formulario Nuevo Insumo
    public string $nuevoNombre = '';
    public string $nuevoCodigo = '';
    public string $nuevaCategoria = 'pescados';
    public string $nuevaUnidad = 'kg';
    public float $nuevoStockActual = 0.0;
    public float $nuevoStockMinimo = 5.0;
    public float $nuevoCostoUnitario = 10.0;
    public string $nuevoProveedor = '';

    // Mensajes flash
    public ?string $mensajeExito = null;

    public function mount(): void
    {
        $primerCritico = Insumo::where('activo', true)
            ->whereRaw('stock_actual <= stock_minimo')
            ->first();

        $this->selectedInsumoId = $primerCritico ? $primerCritico->id : Insumo::where('activo', true)->first()?->id;
    }

    public function selectInsumo(int $id): void
    {
        $this->selectedInsumoId = $id;
        $insumo = Insumo::find($id);
        if ($insumo) {
            $this->compraCostoUnitario = (float) $insumo->costo_unitario;
            $this->compraProveedor = $insumo->proveedor_nombre ?? '';
            $this->ajusteNuevoStock = (float) $insumo->stock_actual;
        }
    }

    public function abrirModalMerma(int $insumoId): void
    {
        $this->selectInsumo($insumoId);
        $this->mermaCantidad = 0.5;
        $this->mermaMotivo = 'Merma operativa en estación de preparación';
        $this->modalMermaOpen = true;
    }

    public function abrirModalCompra(int $insumoId): void
    {
        $this->selectInsumo($insumoId);
        $insumo = Insumo::find($insumoId);
        $this->compraCantidad = max(5.0, round(((float) $insumo->stock_minimo * 2) - (float) $insumo->stock_actual, 1));
        $this->compraCostoUnitario = (float) $insumo->costo_unitario;
        $this->compraProveedor = $insumo->proveedor_nombre ?? '';
        $this->compraFactura = 'FAC-' . rand(1000, 9999);
        $this->modalCompraOpen = true;
    }

    public function abrirModalAjuste(int $insumoId): void
    {
        $this->selectInsumo($insumoId);
        $insumo = Insumo::find($insumoId);
        $this->ajusteNuevoStock = (float) $insumo->stock_actual;
        $this->ajusteMotivo = 'Conteo físico verificado por Jefe de Cocina';
        $this->modalAjusteOpen = true;
    }

    public function registrarMerma(InventarioService $service): void
    {
        if (!$this->selectedInsumoId || $this->mermaCantidad <= 0) return;

        $service->registrarMerma(
            $this->selectedInsumoId,
            $this->mermaCantidad,
            $this->mermaMotivo,
            auth()->id()
        );

        $this->modalMermaOpen = false;
        $this->mensajeExito = "Merma de {$this->mermaCantidad} registrada correctamente.";
    }

    public function registrarCompra(InventarioService $service): void
    {
        if (!$this->selectedInsumoId || $this->compraCantidad <= 0) return;

        $service->registrarCompra(
            $this->selectedInsumoId,
            $this->compraCantidad,
            $this->compraCostoUnitario,
            $this->compraProveedor,
            $this->compraFactura,
            auth()->id()
        );

        $this->modalCompraOpen = false;
        $this->mensajeExito = "Ingreso de {$this->compraCantidad} registrado y costo promedio actualizado.";
    }

    public function registrarAjuste(InventarioService $service): void
    {
        if (!$this->selectedInsumoId || $this->ajusteNuevoStock < 0) return;

        $service->registrarAjuste(
            $this->selectedInsumoId,
            $this->ajusteNuevoStock,
            $this->ajusteMotivo,
            auth()->id()
        );

        $this->modalAjusteOpen = false;
        $this->mensajeExito = "Stock ajustado a {$this->ajusteNuevoStock} correctamente.";
    }

    public function guardarNuevoInsumo(): void
    {
        $this->validate([
            'nuevoNombre' => 'required|min:3',
            'nuevoCodigo' => 'required|unique:insumos,codigo',
            'nuevaCategoria' => 'required',
            'nuevaUnidad' => 'required',
            'nuevoStockMinimo' => 'required|numeric|min:0.1',
            'nuevoCostoUnitario' => 'required|numeric|min:0',
        ]);

        $insumo = Insumo::create([
            'nombre' => $this->nuevoNombre,
            'codigo' => strtoupper($this->nuevoCodigo),
            'categoria' => $this->nuevaCategoria,
            'unidad_medida' => $this->nuevaUnidad,
            'stock_actual' => $this->nuevoStockActual,
            'stock_minimo' => $this->nuevoStockMinimo,
            'capacidad_maxima' => $this->nuevoStockMinimo * 4,
            'costo_unitario' => $this->nuevoCostoUnitario,
            'proveedor_nombre' => $this->nuevoProveedor,
            'activo' => true,
        ]);

        $this->selectedInsumoId = $insumo->id;
        $this->modalNuevoInsumoOpen = false;
        $this->mensajeExito = "Insumo {$insumo->nombre} catalogado con éxito.";
    }

    public function with(InventarioService $service): array
    {
        $query = Insumo::where('activo', true);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nombre', 'ilike', "%{$this->search}%")
                  ->orWhere('codigo', 'ilike', "%{$this->search}%")
                  ->orWhere('proveedor_nombre', 'ilike', "%{$this->search}%");
            });
        }

        if ($this->selectedCategoria !== 'todas') {
            $query->where('categoria', $this->selectedCategoria);
        }

        if ($this->selectedFiltro === 'criticos') {
            $query->whereRaw('stock_actual <= stock_minimo');
        } elseif ($this->selectedFiltro === 'por_agotar') {
            $query->whereRaw('stock_actual > stock_minimo AND stock_actual <= (stock_minimo * 1.5)');
        } elseif ($this->selectedFiltro === 'optimo') {
            $query->whereRaw('stock_actual > (stock_minimo * 1.5)');
        }

        $insumos = $query->orderByRaw('CASE WHEN stock_actual <= stock_minimo THEN 0 ELSE 1 END')
            ->orderBy('nombre')
            ->get();

        $selectedInsumo = $this->selectedInsumoId 
            ? Insumo::with(['recetas.producto', 'movimientos.pedido', 'movimientos.user'])->find($this->selectedInsumoId)
            : $insumos->first();

        $kpis = $service->obtenerKpis();

        return [
            'insumos' => $insumos,
            'selectedInsumo' => $selectedInsumo,
            'kpis' => $kpis,
            'categorias' => [
                'todas' => 'Todas',
                'pescados' => 'Pescados & Mariscos',
                'arroz_granos' => 'Arroz & Granos',
                'algas_nori' => 'Algas & Nori',
                'vegetales' => 'Frutas & Vegetales',
                'lacteos_quesos' => 'Lácteos & Quesos',
                'salsas_condimentos' => 'Salsas & Condimentos',
                'packaging' => 'Packaging & Empaques',
            ],
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Feedback Flash Banner -->
    @if ($mensajeExito)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" 
             class="flex items-center justify-between p-4 rounded-2xl bg-secondary-container/40 border border-secondary/30 text-on-secondary-container shadow-sm animate-fade-in">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-secondary">check_circle</span>
                <span class="font-bold text-sm">{{ $mensajeExito }}</span>
            </div>
            <button @click="show = false" class="text-on-secondary-container/70 hover:text-on-secondary-container">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
    @endif

    <!-- Header Section (Stitch INV-01 Aura Gastro Expressive OS) -->
    <header class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high border border-outline-variant/30 text-xs font-bold text-primary tracking-wider uppercase">
                    <span class="material-symbols-outlined text-[15px] text-primary">inventory_2</span>
                    INV-01 · MÓDULO KARDEX & ESCANDALLOS
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-secondary-container/50 border border-secondary/30 text-on-secondary-container text-xs font-bold">
                    <span class="w-2 h-2 rounded-full bg-secondary animate-ping"></span>
                    KDS Live Sync Activo
                </span>
                <span class="text-xs text-on-surface-variant font-medium">
                    Res. DIAN 18764022 · Sede Poblado MDE-01
                </span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-extrabold text-on-surface tracking-tight">
                Gestión de Inventario y Materias Primas
            </h1>
            <p class="text-sm text-on-surface-variant max-w-3xl">
                Control de stock en tiempo real con deducción automática por comanda cerrada en Cocina (COC-01) y costeo ponderado continuo para alta gastronomía.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 flex-wrap self-start lg:self-center">
            <button wire:click="abrirModalAjuste({{ $selectedInsumoId ?? 1 }})"
                    class="h-11 px-4 rounded-xl bg-surface-container-high hover:bg-surface-container-highest border border-surface-container-highest text-on-surface font-semibold text-sm flex items-center gap-2 transition-all active:scale-95 shadow-sm">
                <span class="material-symbols-outlined text-[18px] text-primary">rule</span>
                <span>Ajuste Rápido</span>
            </button>
            <button wire:click="abrirModalMerma({{ $selectedInsumoId ?? 1 }})"
                    class="h-11 px-4 rounded-xl bg-surface-container-high hover:bg-surface-container-highest border border-surface-container-highest text-on-surface font-semibold text-sm flex items-center gap-2 transition-all active:scale-95 shadow-sm">
                <span class="material-symbols-outlined text-[18px] text-error">delete_sweep</span>
                <span>Registrar Merma</span>
            </button>
            <button wire:click="abrirModalCompra({{ $selectedInsumoId ?? 1 }})"
                    class="h-11 px-4 rounded-xl bg-surface-container-high hover:bg-surface-container-highest border border-surface-container-highest text-on-surface font-semibold text-sm flex items-center gap-2 transition-all active:scale-95 shadow-sm">
                <span class="material-symbols-outlined text-[18px] text-secondary">receipt</span>
                <span>Factura Proveedor</span>
            </button>
            <button wire:click="$set('modalNuevoInsumoOpen', true)"
                    class="h-11 px-5 rounded-xl bg-primary hover:bg-primary-container text-on-primary font-bold text-sm shadow-md shadow-primary/20 flex items-center gap-2 transition-all active:scale-95">
                <span class="material-symbols-outlined text-[20px]">add_circle</span>
                <span>+ Nuevo Insumo</span>
            </button>
        </div>
    </header>

    <!-- Top Bento Metrics (Aura Gastro Expressive OS - INV-01) -->
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- KPI 1: Valor Stock -->
        <div class="relative overflow-hidden p-5 rounded-2xl bg-surface-container-lowest border border-surface-container-highest shadow-sm flex flex-col justify-between group hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="text-xs uppercase tracking-wider text-on-surface-variant font-bold">VALOR TOTAL DEL STOCK</span>
                    <span class="text-2xl lg:text-3xl font-extrabold text-on-surface mt-1">
                        ${{ number_format($kpis['valor_total_stock'], 2) }}
                        <span class="text-xs text-on-surface-variant font-semibold">COP</span>
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[22px]">account_balance_wallet</span>
                </div>
            </div>
            <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center justify-between text-xs text-on-surface-variant">
                <span>{{ $kpis['total_insumos'] }} insumos catalogados</span>
                <span class="inline-flex items-center gap-1 font-bold text-secondary">
                    <span class="material-symbols-outlined text-[16px]">trending_up</span> Valuación NIIF
                </span>
            </div>
        </div>

        <!-- KPI 2: Stock Crítico -->
        <div class="relative overflow-hidden p-5 rounded-2xl bg-surface-container-lowest border {{ $kpis['criticos_count'] > 0 ? 'border-error/40 bg-error-container/10' : 'border-surface-container-highest' }} shadow-sm flex flex-col justify-between group transition-all">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <span class="text-xs uppercase tracking-wider text-on-surface-variant font-bold">STOCK CRÍTICO</span>
                        @if ($kpis['criticos_count'] > 0)
                            <span class="px-2 py-0.5 rounded-full bg-error text-on-error font-extrabold text-[10px] animate-pulse">
                                {{ $kpis['criticos_count'] }} ALERTA
                            </span>
                        @endif
                    </div>
                    <span class="text-2xl lg:text-3xl font-extrabold text-error mt-1">
                        {{ $kpis['criticos_count'] }} <span class="text-sm font-medium text-on-surface-variant">insumos bajo mínimo</span>
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-error-container/60 text-error flex items-center justify-center">
                    <span class="material-symbols-outlined text-[22px]">warning</span>
                </div>
            </div>
            <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center gap-1.5 truncate text-xs text-on-surface-variant">
                <span class="w-2 h-2 rounded-full bg-error flex-shrink-0"></span>
                <span class="truncate">
                    @forelse ($kpis['insumos_criticos']->take(3) as $crit)
                        {{ $crit->nombre }}{{ !$loop->last ? ', ' : '...' }}
                    @empty
                        Todos los insumos con stock seguro
                    @endforelse
                </span>
            </div>
        </div>

        <!-- KPI 3: Mermas del Turno -->
        <div class="relative overflow-hidden p-5 rounded-2xl bg-surface-container-lowest border border-surface-container-highest shadow-sm flex flex-col justify-between group hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="text-xs uppercase tracking-wider text-on-surface-variant font-bold">MERMAS DEL TURNO</span>
                    <span class="text-2xl lg:text-3xl font-extrabold text-on-surface mt-1">
                        -${{ number_format($kpis['total_mermas_hoy'], 2) }}
                        <span class="text-xs text-on-surface-variant font-semibold">COP</span>
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-tertiary-container/30 text-tertiary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[22px]">delete_sweep</span>
                </div>
            </div>
            <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center justify-between text-xs text-on-surface-variant">
                <span>{{ $kpis['incidentes_mermas'] }} incidentes reportados</span>
                <span class="font-bold text-tertiary">Control Operativo</span>
            </div>
        </div>

        <!-- KPI 4: Consumo Cocina KDS -->
        <div class="relative overflow-hidden p-5 rounded-2xl bg-surface-container-lowest border border-surface-container-highest shadow-sm flex flex-col justify-between group hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="text-xs uppercase tracking-wider text-on-surface-variant font-bold">CONSUMO HOY (KDS)</span>
                    <span class="text-2xl lg:text-3xl font-extrabold text-secondary mt-1">
                        ${{ number_format($kpis['total_consumo_hoy'], 2) }}
                        <span class="text-xs text-on-surface-variant font-semibold">COP</span>
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-secondary-container/40 text-secondary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[22px]">skillet</span>
                </div>
            </div>
            <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center gap-1.5 truncate text-xs text-on-surface-variant">
                <span class="material-symbols-outlined text-secondary text-[16px]">verified</span>
                <span class="truncate">Deducción por receta automatizada</span>
            </div>
        </div>
    </section>

    <!-- Filter Toolbar & Search Bar -->
    <section class="flex flex-col gap-3 p-4 rounded-2xl bg-surface-container-lowest border border-surface-container-highest shadow-sm">
        <div class="flex flex-col md:flex-row items-center justify-between gap-3">
            <!-- Search input -->
            <div class="relative w-full md:max-w-xl">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                <input wire:model.live.debounce.300ms="search" 
                       type="text"
                       placeholder="Buscar por insumo, código SKU (ej. SKU-PES-01), o proveedor..."
                       class="w-full h-11 pl-11 pr-4 rounded-xl bg-surface-container-low focus:bg-surface-container border border-surface-container-high text-on-surface placeholder-on-surface-variant/70 text-sm outline-none transition-all" />
            </div>

            <!-- View Segmented Filters -->
            <div class="flex items-center gap-1 p-1 rounded-xl bg-surface-container-low border border-surface-container-high w-full md:w-auto overflow-x-auto">
                <button wire:click="$set('selectedFiltro', 'todos')"
                        class="h-8 px-3 rounded-lg text-xs font-bold transition-all {{ $selectedFiltro === 'todos' ? 'bg-surface-container-lowest text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Todos ({{ $kpis['total_insumos'] }})
                </button>
                <button wire:click="$set('selectedFiltro', 'criticos')"
                        class="h-8 px-3 rounded-lg text-xs font-bold flex items-center gap-1.5 transition-all {{ $selectedFiltro === 'criticos' ? 'bg-error text-on-error shadow-sm' : 'text-error hover:bg-error-container/30' }}">
                    <span class="w-2 h-2 rounded-full bg-error"></span>
                    Críticos ({{ $kpis['criticos_count'] }})
                </button>
                <button wire:click="$set('selectedFiltro', 'por_agotar')"
                        class="h-8 px-3 rounded-lg text-xs font-bold transition-all {{ $selectedFiltro === 'por_agotar' ? 'bg-tertiary-fixed text-on-tertiary-fixed shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Por Agotar ({{ $kpis['por_agotar_count'] }})
                </button>
                <button wire:click="$set('selectedFiltro', 'optimo')"
                        class="h-8 px-3 rounded-lg text-xs font-bold transition-all {{ $selectedFiltro === 'optimo' ? 'bg-secondary-container text-on-secondary-container shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Óptimo ({{ $kpis['optimo_count'] }})
                </button>
            </div>
        </div>

        <!-- Categories Scrollable Pills -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            @foreach ($categorias as $key => $label)
                <button wire:click="$set('selectedCategoria', '{{ $key }}')"
                        class="h-9 px-4 rounded-full text-xs font-bold whitespace-nowrap transition-all flex items-center gap-1.5
                        {{ $selectedCategoria === $key 
                            ? 'bg-primary text-on-primary shadow-sm' 
                            : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface border border-surface-container-high' }}">
                    <span>{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <!-- Main Content Split Layout (Table / Live Traceability Card) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Left Column: Interactive Raw Material Table (col-span-12 lg:col-span-8) -->
        <section class="lg:col-span-8 flex flex-col gap-3 bg-surface-container-lowest p-5 rounded-3xl border border-surface-container-highest shadow-sm">
            <div class="flex items-center justify-between pb-2 border-b border-surface-container-high">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">layers</span>
                    <h2 class="text-base font-bold text-on-surface">Kardex de Materias Primas</h2>
                    <span class="px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant text-xs font-medium">
                        {{ $insumos->count() }} ítems listados
                    </span>
                </div>
                <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <span class="material-symbols-outlined text-[16px] text-secondary">verified_user</span>
                    <span>Costeo Ponderado Continuo</span>
                </div>
            </div>

            <!-- Material Table -->
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low text-on-surface-variant text-xs uppercase tracking-wider rounded-xl">
                            <th class="py-3 px-4 rounded-l-xl">Materia Prima & SKU</th>
                            <th class="py-3 px-4">Nivel vs Mínimo</th>
                            <th class="py-3 px-4">Costo Unitario</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4 text-right rounded-r-xl">Acciones Rápidas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high text-sm">
                        @forelse ($insumos as $insumo)
                            @php
                                $isCritico = (float) $insumo->stock_actual <= (float) $insumo->stock_minimo;
                                $isPorAgotar = !$isCritico && (float) $insumo->stock_actual <= ((float) $insumo->stock_minimo * 1.5);
                                $isSelected = $selectedInsumoId === $insumo->id;
                            @endphp
                            <tr wire:click="selectInsumo({{ $insumo->id }})"
                                class="cursor-pointer transition-colors group
                                {{ $isSelected ? 'bg-primary/5 border-l-4 border-primary' : ($isCritico ? 'bg-error-container/15 hover:bg-error-container/25' : 'hover:bg-surface-container-low/60') }}">
                                <td class="py-3.5 px-4 rounded-l-xl">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0
                                            {{ $isCritico ? 'bg-error-container text-error' : 'bg-surface-container text-primary' }}">
                                            <span class="material-symbols-outlined text-[22px]">
                                                @if ($insumo->categoria === 'pescados')
                                                    phishing
                                                @elseif ($insumo->categoria === 'arroz_granos')
                                                    rice_bowl
                                                @elseif ($insumo->categoria === 'vegetales')
                                                    nutrition
                                                @elseif ($insumo->categoria === 'lacteos_quesos')
                                                    egg
                                                @elseif ($insumo->categoria === 'packaging')
                                                    inventory_2
                                                @else
                                                    set_meal
                                                @endif
                                            </span>
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="font-bold text-on-surface flex items-center gap-1.5 group-hover:text-primary transition-colors">
                                                {{ $insumo->nombre }}
                                                @if ($isCritico)
                                                    <span class="w-2 h-2 rounded-full bg-error animate-ping"></span>
                                                @endif
                                            </span>
                                            <span class="text-xs text-on-surface-variant flex items-center gap-1">
                                                <span class="font-mono text-primary font-bold">{{ $insumo->codigo }}</span>
                                                · {{ $insumo->ubicacion_almacen ?? 'Almacén general' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex flex-col gap-1 w-40">
                                        <div class="flex justify-between items-center text-xs">
                                            <span class="font-bold {{ $isCritico ? 'text-error' : ($isPorAgotar ? 'text-tertiary' : 'text-secondary') }}">
                                                {{ number_format($insumo->stock_actual, 2) }} {{ $insumo->unidad_medida }}
                                            </span>
                                            <span class="text-on-surface-variant">Mín: {{ number_format($insumo->stock_minimo, 1) }}</span>
                                        </div>
                                        <!-- Progress Bar -->
                                        <div class="w-full h-2 rounded-full bg-surface-container overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-500
                                                {{ $isCritico ? 'bg-error' : ($isPorAgotar ? 'bg-tertiary' : 'bg-secondary') }}"
                                                style="width: {{ $insumo->porcentaje_stock }}%;"></div>
                                        </div>
                                        @if ($isCritico)
                                            <span class="text-[11px] text-error font-extrabold flex items-center gap-0.5">
                                                <span class="material-symbols-outlined text-[13px]">arrow_downward</span>
                                                DÉFICIT -{{ number_format(max(0, (float)$insumo->stock_minimo - (float)$insumo->stock_actual), 2) }} {{ $insumo->unidad_medida }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-on-surface">${{ number_format($insumo->costo_unitario, 2) }}</span>
                                        <span class="text-xs text-on-surface-variant">por {{ $insumo->unidad_medida }}</span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    @if ($isCritico)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-error-container text-error font-bold text-xs">
                                            <span class="material-symbols-outlined text-[14px]">report</span> CRÍTICO
                                        </span>
                                    @elseif ($isPorAgotar)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-tertiary-fixed text-on-tertiary-fixed font-bold text-xs">
                                            <span class="material-symbols-outlined text-[14px]">warning</span> ADVERTENCIA
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-secondary-container text-on-secondary-container font-bold text-xs">
                                            <span class="material-symbols-outlined text-[14px]">check_circle</span> ÓPTIMO
                                        </span>
                                    @endif
                                </td>

                                <td class="py-3.5 px-4 text-right rounded-r-xl">
                                    <div class="flex items-center justify-end gap-1.5" @click.stop>
                                        <button wire:click="abrirModalCompra({{ $insumo->id }})"
                                                class="p-2 rounded-xl bg-surface-container hover:bg-secondary hover:text-on-secondary text-on-surface-variant transition-colors"
                                                title="Comprar / Reabastecer">
                                            <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                                        </button>
                                        <button wire:click="abrirModalMerma({{ $insumo->id }})"
                                                class="p-2 rounded-xl bg-surface-container hover:bg-error hover:text-on-error text-on-surface-variant transition-colors"
                                                title="Registrar Merma">
                                            <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
                                        </button>
                                        <button wire:click="abrirModalAjuste({{ $insumo->id }})"
                                                class="p-2 rounded-xl bg-surface-container hover:bg-primary hover:text-on-primary text-on-surface-variant transition-colors"
                                                title="Ajuste de Conteo">
                                            <span class="material-symbols-outlined text-[18px]">tune</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-on-surface-variant">
                                    <span class="material-symbols-outlined text-4xl text-outline-variant mb-2">search_off</span>
                                    <p class="font-medium">No se encontraron insumos con los filtros actuales.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <div class="flex flex-col sm:flex-row items-center justify-between pt-3 border-t border-surface-container-high text-xs text-on-surface-variant gap-2">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[18px]">verified</span>
                    <span>Kardex valuado por Método Promedio Ponderado según directrices NIIF.</span>
                </div>
                <span>Total insumos activos: {{ $insumos->count() }}</span>
            </div>
        </section>

        <!-- Right Column: Live Insumo Detail Card & KDS Traceability (col-span-12 lg:col-span-4) -->
        <aside class="lg:col-span-4 flex flex-col gap-4">
            @if ($selectedInsumo)
                @php
                    $isSelCritico = (float) $selectedInsumo->stock_actual <= (float) $selectedInsumo->stock_minimo;
                @endphp
                <div class="bg-surface-container-lowest p-5 rounded-3xl border border-surface-container-highest shadow-sm flex flex-col gap-4 relative overflow-hidden">
                    <!-- Card Header & Badge -->
                    <div class="flex items-start justify-between">
                        <div class="flex flex-col">
                            <span class="text-xs text-primary font-bold tracking-wider uppercase">INSUMO SELECCIONADO</span>
                            <h3 class="text-xl font-extrabold text-on-surface leading-tight mt-0.5">
                                {{ $selectedInsumo->nombre }}
                            </h3>
                            <span class="text-xs text-on-surface-variant font-medium">
                                {{ ucfirst($selectedInsumo->categoria) }} · {{ $selectedInsumo->ubicacion_almacen ?? 'Almacén general' }}
                            </span>
                        </div>
                        @if ($isSelCritico)
                            <span class="px-2.5 py-1 rounded-full bg-error text-on-error text-xs font-extrabold flex items-center gap-1 shadow-sm animate-pulse">
                                <span class="material-symbols-outlined text-[14px]">alarm</span> URGENTE
                            </span>
                        @else
                            <span class="px-2.5 py-1 rounded-full bg-secondary-container text-on-secondary-container border border-secondary/30 text-xs font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span> DISPONIBLE
                            </span>
                        @endif
                    </div>

                    <!-- Visual Insumo Image Banner -->
                    <div class="relative w-full h-32 rounded-2xl overflow-hidden bg-surface-container-low border border-surface-container-high flex items-center justify-center">
                        <div class="absolute inset-0 bg-gradient-to-t from-surface-container-highest/60 via-transparent to-transparent z-10"></div>
                        <div class="flex flex-col items-center justify-center text-on-surface-variant/40 z-0">
                            <span class="material-symbols-outlined text-6xl">
                                @if ($selectedInsumo->categoria === 'pescados')
                                    phishing
                                @elseif ($selectedInsumo->categoria === 'arroz_granos')
                                    rice_bowl
                                @elseif ($selectedInsumo->categoria === 'vegetales')
                                    nutrition
                                @else
                                    kitchen
                                @endif
                            </span>
                        </div>
                        <div class="absolute bottom-2.5 left-3 right-3 flex items-center justify-between text-on-surface z-20 text-xs">
                            <span class="font-medium flex items-center gap-1 text-on-surface-variant">
                                <span class="material-symbols-outlined text-[16px] text-primary">device_thermostat</span>
                                {{ $selectedInsumo->temperatura_almacen ?? 'Temp. Óptima' }}
                            </span>
                            <span class="font-mono text-xs bg-surface-container-lowest px-2 py-0.5 rounded border border-surface-container-high text-primary font-bold">
                                {{ $selectedInsumo->codigo }}
                            </span>
                        </div>
                    </div>

                    <!-- Stock Breakdown Grid -->
                    <div class="grid grid-cols-3 gap-2 p-3 rounded-2xl bg-surface-container-low border border-surface-container-high text-center">
                        <div class="flex flex-col">
                            <span class="text-[11px] text-on-surface-variant font-medium">Stock Actual</span>
                            <span class="text-lg font-extrabold mt-0.5 {{ $isSelCritico ? 'text-error' : 'text-secondary' }}">
                                {{ number_format($selectedInsumo->stock_actual, 2) }}
                                <span class="text-xs text-on-surface-variant">{{ $selectedInsumo->unidad_medida }}</span>
                            </span>
                        </div>
                        <div class="flex flex-col border-x border-surface-container-high">
                            <span class="text-[11px] text-on-surface-variant font-medium">Pto. Reorden</span>
                            <span class="text-lg font-extrabold text-on-surface mt-0.5">
                                {{ number_format($selectedInsumo->stock_minimo, 1) }}
                                <span class="text-xs text-on-surface-variant">{{ $selectedInsumo->unidad_medida }}</span>
                            </span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[11px] text-on-surface-variant font-medium">Capac. Máx</span>
                            <span class="text-lg font-extrabold text-primary mt-0.5">
                                {{ number_format($selectedInsumo->capacidad_maxima, 0) }}
                                <span class="text-xs text-on-surface-variant">{{ $selectedInsumo->unidad_medida }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Linked Recipe Items -->
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">
                                Platos que descuentan este insumo
                            </span>
                            <span class="text-xs text-primary font-semibold">{{ $selectedInsumo->recetas->count() }} recetas</span>
                        </div>

                        <div class="flex flex-col gap-1.5 max-h-48 overflow-y-auto pr-1">
                            @forelse ($selectedInsumo->recetas as $receta)
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-surface-container-low border border-surface-container-high hover:bg-surface-container transition-colors">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-[18px]">dinner_dining</span>
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-on-surface">{{ $receta->producto->nombre ?? 'Plato' }}</span>
                                            <span class="text-[11px] text-on-surface-variant">{{ $receta->notas ?? 'Receta estándar' }}</span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-extrabold text-primary">
                                        -{{ number_format($receta->cantidad, 3) }} {{ $selectedInsumo->unidad_medida }}/plato
                                    </span>
                                </div>
                            @empty
                                <div class="p-3 text-center text-xs text-on-surface-variant bg-surface-container-low rounded-xl">
                                    Este insumo aún no está vinculado a platos del menú.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Movimientos Recientes / Kardex Traceability -->
                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">
                            Últimos Movimientos Kardex
                        </span>
                        <div class="flex flex-col gap-1.5 max-h-44 overflow-y-auto pr-1">
                            @forelse ($selectedInsumo->movimientos->take(5) as $mov)
                                <div class="flex items-center justify-between p-2 rounded-xl bg-surface-container-low border border-surface-container-high text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[16px]
                                            {{ $mov->tipo === 'compra' ? 'text-secondary' : ($mov->tipo === 'merma' ? 'text-error' : 'text-primary') }}">
                                            {{ $mov->tipo === 'compra' ? 'add_shopping_cart' : ($mov->tipo === 'merma' ? 'delete_sweep' : 'skillet') }}
                                        </span>
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-on-surface truncate max-w-[150px]">{{ $mov->motivo ?? ucfirst($mov->tipo) }}</span>
                                            <span class="text-[10px] text-on-surface-variant">{{ $mov->created_at->format('d/m H:i') }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-bold {{ $mov->tipo === 'compra' ? 'text-secondary' : 'text-error' }}">
                                            {{ $mov->tipo === 'compra' ? '+' : '-' }}{{ number_format($mov->cantidad, 2) }}
                                        </span>
                                        <div class="text-[10px] text-on-surface-variant">Saldo: {{ number_format($mov->saldo_posterior, 2) }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-3 text-center text-xs text-on-surface-variant bg-surface-container-low rounded-xl">
                                    Sin movimientos registrados.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Supplier Details & Direct Order Action -->
                    <div class="p-3.5 rounded-2xl bg-surface-container-low border border-surface-container-high flex flex-col gap-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-on-surface-variant uppercase font-bold">Proveedor Preferente</span>
                            <span class="inline-flex items-center gap-1 text-secondary font-semibold">
                                <span class="material-symbols-outlined text-[14px]">verified</span> Verificado
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-on-surface">{{ $selectedInsumo->proveedor_nombre ?? 'Proveedor no asignado' }}</span>
                                <span class="text-xs text-on-surface-variant">NIT: {{ $selectedInsumo->proveedor_nit ?? 'N/A' }}</span>
                            </div>
                            @if ($selectedInsumo->proveedor_telefono)
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $selectedInsumo->proveedor_telefono) }}" 
                                   target="_blank"
                                   class="h-8 px-3 rounded-xl bg-secondary hover:bg-secondary-fixed-dim text-on-secondary text-xs font-bold flex items-center gap-1 shadow-sm transition-all">
                                    <span class="material-symbols-outlined text-[16px]">chat</span>
                                    WhatsApp
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col gap-2 pt-1">
                        <button wire:click="abrirModalCompra({{ $selectedInsumo->id }})"
                                class="h-12 w-full rounded-xl bg-primary hover:bg-primary-container text-on-primary font-bold text-sm flex items-center justify-center gap-2 shadow-md shadow-primary/20 transition-all active:scale-95">
                            <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
                            <span>Generar Orden de Compra</span>
                        </button>
                        <div class="grid grid-cols-2 gap-2">
                            <button wire:click="abrirModalMerma({{ $selectedInsumo->id }})"
                                    class="h-10 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest text-on-surface font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
                                <span class="material-symbols-outlined text-[16px] text-error">delete_sweep</span>
                                <span>Reportar Merma</span>
                            </button>
                            <button wire:click="abrirModalAjuste({{ $selectedInsumo->id }})"
                                    class="h-10 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest text-on-surface font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors">
                                <span class="material-symbols-outlined text-[16px] text-primary">tune</span>
                                <span>Conteo Físico</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </aside>
    </div>

    <!-- MODAL: REGISTRAR MERMA -->
    @if ($modalMermaOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/40 backdrop-blur-sm animate-fade-in">
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-5">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-error-container text-error flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">delete_sweep</span>
                        </div>
                        <h3 class="text-lg font-bold text-on-surface">Registrar Merma Operativa</h3>
                    </div>
                    <button wire:click="$set('modalMermaOpen', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <span class="text-xs text-on-surface-variant uppercase font-bold">Insumo</span>
                        <p class="text-base font-bold text-on-surface mt-0.5">{{ $selectedInsumo?->nombre }}</p>
                        <span class="text-xs text-primary font-mono font-bold">{{ $selectedInsumo?->codigo }}</span>
                    </div>

                    <div>
                        <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Cantidad a Dar de Baja ({{ $selectedInsumo?->unidad_medida }})</label>
                        <input type="number" step="0.01" wire:model="mermaCantidad"
                               class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface font-bold text-lg focus:border-error outline-none" />
                    </div>

                    <div>
                        <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Motivo de la Merma</label>
                        <select wire:model="mermaMotivo"
                                class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-error outline-none">
                            <option value="Merma operativa por corte y fileteado">Merma operativa por corte y fileteado</option>
                            <option value="Vencimiento / Caducidad de fecha">Vencimiento / Caducidad de fecha</option>
                            <option value="Deterioro en cadena de frío">Deterioro en cadena de frío</option>
                            <option value="Rotura o caída accidental">Rotura o caída accidental</option>
                            <option value="Error en preparación de comanda">Error en preparación de comanda</option>
                        </select>
                    </div>

                    <div class="p-3 rounded-xl bg-error-container/40 border border-error/20 text-xs text-error flex items-center gap-2">
                        <span class="material-symbols-outlined text-error text-base">warning</span>
                        <span>Se deducirá del stock inmediatamente y quedará registrado en el Kardex.</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('modalMermaOpen', false)"
                            class="h-10 px-4 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface font-semibold text-sm">
                        Cancelar
                    </button>
                    <button wire:click="registrarMerma"
                            class="h-10 px-5 rounded-xl bg-error hover:bg-error/90 text-on-error font-bold text-sm shadow-md">
                        Confirmar Merma
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: REGISTRAR COMPRA / FACTURA PROVEEDOR -->
    @if ($modalCompraOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/40 backdrop-blur-sm animate-fade-in">
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-5">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-secondary-container text-secondary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">add_shopping_cart</span>
                        </div>
                        <h3 class="text-lg font-bold text-on-surface">Reabastecer / Factura Proveedor</h3>
                    </div>
                    <button wire:click="$set('modalCompraOpen', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <span class="text-xs text-on-surface-variant uppercase font-bold">Insumo a Ingresar</span>
                        <p class="text-base font-bold text-on-surface mt-0.5">{{ $selectedInsumo?->nombre }}</p>
                        <span class="text-xs text-secondary font-mono font-bold">{{ $selectedInsumo?->codigo }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Cantidad ({{ $selectedInsumo?->unidad_medida }})</label>
                            <input type="number" step="0.01" wire:model="compraCantidad"
                                   class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface font-bold text-lg focus:border-secondary outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Costo Unitario ($)</label>
                            <input type="number" step="0.01" wire:model="compraCostoUnitario"
                                   class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface font-bold text-lg focus:border-secondary outline-none" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Proveedor</label>
                        <input type="text" wire:model="compraProveedor"
                               class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-secondary outline-none" />
                    </div>

                    <div>
                        <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Número de Factura / Guía</label>
                        <input type="text" wire:model="compraFactura"
                               class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-secondary outline-none" />
                    </div>

                    <div class="p-3 rounded-xl bg-secondary-container/40 border border-secondary/20 text-xs text-on-secondary-container">
                        Total Factura: <strong>${{ number_format($compraCantidad * $compraCostoUnitario, 2) }} COP</strong> · El costo promedio ponderado se actualizará automáticamente.
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('modalCompraOpen', false)"
                            class="h-10 px-4 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface font-semibold text-sm">
                        Cancelar
                    </button>
                    <button wire:click="registrarCompra"
                            class="h-10 px-5 rounded-xl bg-secondary hover:bg-secondary-fixed-dim text-on-secondary font-bold text-sm shadow-md">
                        Ingresar a Inventario
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: AJUSTE POR CONTEO FÍSICO -->
    @if ($modalAjusteOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/40 backdrop-blur-sm animate-fade-in">
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-5">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-tertiary-container text-tertiary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">tune</span>
                        </div>
                        <h3 class="text-lg font-bold text-on-surface">Ajuste por Conteo Físico</h3>
                    </div>
                    <button wire:click="$set('modalAjusteOpen', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <span class="text-xs text-on-surface-variant uppercase font-bold">Insumo a Ajustar</span>
                        <p class="text-base font-bold text-on-surface mt-0.5">{{ $selectedInsumo?->nombre }}</p>
                        <span class="text-xs text-on-surface-variant">Stock Actual del Sistema: <strong class="text-on-surface">{{ number_format($selectedInsumo?->stock_actual, 2) }} {{ $selectedInsumo?->unidad_medida }}</strong></span>
                    </div>

                    <div>
                        <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Nuevo Stock Real Conteo Físico ({{ $selectedInsumo?->unidad_medida }})</label>
                        <input type="number" step="0.01" wire:model="ajusteNuevoStock"
                               class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface font-bold text-lg focus:border-primary outline-none" />
                    </div>

                    <div>
                        <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Motivo o Justificación del Ajuste</label>
                        <input type="text" wire:model="ajusteMotivo"
                               class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-primary outline-none" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('modalAjusteOpen', false)"
                            class="h-10 px-4 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface font-semibold text-sm">
                        Cancelar
                    </button>
                    <button wire:click="registrarAjuste"
                            class="h-10 px-5 rounded-xl bg-primary hover:bg-primary-container text-on-primary font-bold text-sm shadow-md">
                        Guardar Ajuste
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: NUEVO INSUMO -->
    @if ($modalNuevoInsumoOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/40 backdrop-blur-sm animate-fade-in">
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-5">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">add_circle</span>
                        </div>
                        <h3 class="text-lg font-bold text-on-surface">Catalogar Nuevo Insumo</h3>
                    </div>
                    <button wire:click="$set('modalNuevoInsumoOpen', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Nombre Insumo *</label>
                            <input type="text" wire:model="nuevoNombre" placeholder="Ej: Anguila Kabayaki"
                                   class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-primary outline-none" />
                            @error('nuevoNombre') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Código SKU *</label>
                            <input type="text" wire:model="nuevoCodigo" placeholder="SKU-PES-05"
                                   class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface font-mono text-sm uppercase focus:border-primary outline-none" />
                            @error('nuevoCodigo') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Categoría</label>
                            <select wire:model="nuevaCategoria"
                                    class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-primary outline-none">
                                <option value="pescados">Pescados & Mariscos</option>
                                <option value="arroz_granos">Arroz & Granos</option>
                                <option value="algas_nori">Algas & Nori</option>
                                <option value="vegetales">Frutas & Vegetales</option>
                                <option value="lacteos_quesos">Lácteos & Quesos</option>
                                <option value="salsas_condimentos">Salsas & Condimentos</option>
                                <option value="packaging">Packaging & Empaques</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Unidad de Medida</label>
                            <select wire:model="nuevaUnidad"
                                    class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-primary outline-none">
                                <option value="kg">Kilogramos (kg)</option>
                                <option value="g">Gramos (g)</option>
                                <option value="l">Litros (l)</option>
                                <option value="ml">Mililitros (ml)</option>
                                <option value="paquete">Paquetes / Bolsas</option>
                                <option value="unidad">Unidades</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Stock Inicial</label>
                            <input type="number" step="0.01" wire:model="nuevoStockActual"
                                   class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-primary outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Stock Mínimo</label>
                            <input type="number" step="0.01" wire:model="nuevoStockMinimo"
                                   class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-primary outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Costo Unit ($)</label>
                            <input type="number" step="0.01" wire:model="nuevoCostoUnitario"
                                   class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-primary outline-none" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-on-surface-variant font-bold uppercase mb-1">Proveedor Principal</label>
                        <input type="text" wire:model="nuevoProveedor" placeholder="Ej: Bahía Solano Seafood S.A.S."
                               class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-on-surface text-sm focus:border-primary outline-none" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('modalNuevoInsumoOpen', false)"
                            class="h-10 px-4 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface font-semibold text-sm">
                        Cancelar
                    </button>
                    <button wire:click="guardarNuevoInsumo"
                            class="h-10 px-5 rounded-xl bg-primary hover:bg-primary-container text-on-primary font-bold text-sm shadow-md">
                        Crear Insumo
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
