<?php

use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\Insumo;
use App\Models\Proveedor;
use App\Services\CompraService;
use App\Services\ProveedorService;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $busqueda = '';

    public bool $soloActivos = true;

    public array $nuevo = [
        'nombre' => '',
        'email' => '',
        'telefono' => '',
        'nit' => '',
        'direccion' => '',
        'contacto' => '',
        'dias_credito' => 0,
    ];

    public ?int $enEdicion = null;

    public array $edicion = [
        'nombre' => '',
        'email' => '',
        'telefono' => '',
        'nit' => '',
        'direccion' => '',
        'contacto' => '',
        'dias_credito' => 0,
    ];

    public ?int $fichaId = null;

    public string $pestana = 'datos';

    public string $fichaDesde = '';

    public string $fichaHasta = '';

    public ?int $comparadorInsumoId = null;

    public string $reporteDesde = '';

    public string $reporteHasta = '';

    public bool $reporteConsultado = false;

    public array $factura = [
        'proveedor_id' => null,
        'numero' => '',
        'fecha' => '',
        'forma_pago' => 'contado',
    ];

    public array $lineas = [];

    public bool $modalCrear = false;

    public bool $modalEditar = false;

    public bool $modalFicha = false;

    public bool $modalFactura = false;

    public bool $modalAnular = false;

    public ?int $anulandoId = null;

    public function mount(): void
    {
        $this->fichaDesde = now()->startOfMonth()->toDateString();
        $this->fichaHasta = now()->toDateString();
        $this->reporteDesde = now()->startOfMonth()->toDateString();
        $this->reporteHasta = now()->toDateString();
    }

    public function with(): array
    {
        $proveedores = Proveedor::withCount('compras')
            ->when($this->busqueda !== '', function ($query): void {
                $query->where(function ($sub): void {
                    $sub->where('nombre', 'like', "%{$this->busqueda}%")
                        ->orWhere('nit', 'like', "%{$this->busqueda}%");
                });
            })
            ->when($this->soloActivos, fn ($query) => $query->where('activo', true))
            ->orderBy('nombre')
            ->get();

        $ficha = null;
        if ($this->fichaId) {
            $ficha = Proveedor::with([
                'insumos' => fn ($query) => $query->orderBy('nombre'),
                'compras' => fn ($query) => $query->latest()->with(['lineas.insumo', 'cxp']),
            ])->withCount('compras')->find($this->fichaId);
        }

        $fichaResumen = $ficha && $this->fichaDesde !== '' && $this->fichaHasta !== ''
            ? app(ProveedorService::class)->fichaResumen($ficha, $this->fichaDesde, $this->fichaHasta)
            : null;

        $comparadorFilas = $this->comparadorInsumoId
            ? app(ProveedorService::class)->comparadorInsumo($this->comparadorInsumoId)
            : [];
        $comparadorMejorId = count($comparadorFilas) > 0
            ? collect($comparadorFilas)->sortBy('ultimo_costo')->first()['proveedor_id']
            : null;

        $insumosConCompras = Insumo::whereIn('id', CompraLinea::distinct()->pluck('insumo_id'))->orderBy('nombre')->get(['id', 'nombre']);

        $reporteGasto = $this->reporteConsultado && $this->reporteDesde !== '' && $this->reporteHasta !== ''
            ? app(CompraService::class)->gastoPorProveedor($this->reporteDesde, $this->reporteHasta)
            : collect();

        return [
            'proveedores' => $proveedores,
            'insumosActivos' => Insumo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'unidad_medida']),
            'ficha' => $ficha,
            'fichaResumen' => $fichaResumen,
            'comparadorFilas' => $comparadorFilas,
            'comparadorMejorId' => $comparadorMejorId,
            'insumosConCompras' => $insumosConCompras,
            'reporteGasto' => $reporteGasto,
            'reporteTotal' => $reporteGasto->sum('total'),
        ];
    }

    public function abrirCrear(): void
    {
        $this->authorize('create', Proveedor::class);
        $this->reset('nuevo');
        $this->modalCrear = true;
    }

    public function guardarProveedor(): void
    {
        $this->authorize('create', Proveedor::class);

        $validated = $this->validate([
            'nuevo.nombre' => ['required', 'string', 'max:255', 'unique:proveedores,nombre'],
            'nuevo.nit' => ['nullable', 'string', 'max:30', 'unique:proveedores,nit'],
            'nuevo.telefono' => ['nullable', 'string', 'max:30'],
            'nuevo.email' => ['nullable', 'email', 'max:255'],
            'nuevo.direccion' => ['nullable', 'string', 'max:255'],
            'nuevo.contacto' => ['nullable', 'string', 'max:255'],
            'nuevo.dias_credito' => ['nullable', 'integer', 'min:0'],
        ]);

        app(ProveedorService::class)->crear($validated['nuevo']);

        $this->reset('nuevo', 'modalCrear');
        $this->dispatch('notificacion', 'Proveedor creado correctamente.');
    }

    public function abrirEditar(int $id): void
    {
        $proveedor = Proveedor::findOrFail($id);
        $this->authorize('update', $proveedor);

        $this->enEdicion = $proveedor->id;
        $this->edicion = [
            'nombre' => $proveedor->nombre,
            'email' => $proveedor->email,
            'telefono' => $proveedor->telefono,
            'nit' => $proveedor->nit,
            'direccion' => $proveedor->direccion,
            'contacto' => $proveedor->contacto,
            'dias_credito' => (int) $proveedor->dias_credito,
        ];
        $this->modalEditar = true;
    }

    public function guardarEdicion(): void
    {
        $proveedor = Proveedor::findOrFail($this->enEdicion);
        $this->authorize('update', $proveedor);

        $validated = $this->validate([
            'edicion.nombre' => ['required', 'string', 'max:255', Rule::unique('proveedores', 'nombre')->ignore($proveedor->id)],
            'edicion.nit' => ['nullable', 'string', 'max:30', Rule::unique('proveedores', 'nit')->ignore($proveedor->id)],
            'edicion.telefono' => ['nullable', 'string', 'max:30'],
            'edicion.email' => ['nullable', 'email', 'max:255'],
            'edicion.direccion' => ['nullable', 'string', 'max:255'],
            'edicion.contacto' => ['nullable', 'string', 'max:255'],
            'edicion.dias_credito' => ['nullable', 'integer', 'min:0'],
        ]);

        app(ProveedorService::class)->actualizar($proveedor, $validated['edicion']);

        $this->reset('edicion', 'enEdicion', 'modalEditar');
        $this->dispatch('notificacion', 'Proveedor actualizado.');
    }

    public function desactivar(int $id): void
    {
        $proveedor = Proveedor::findOrFail($id);
        $this->authorize('update', $proveedor);

        app(ProveedorService::class)->desactivar($proveedor);

        $this->dispatch('notificacion', 'Proveedor desactivado.');
    }

    public function eliminar(int $id): void
    {
        $proveedor = Proveedor::findOrFail($id);
        $this->authorize('delete', $proveedor);

        try {
            app(ProveedorService::class)->eliminar($proveedor);
        } catch (\InvalidArgumentException $e) {
            $this->addError('general', $e->getMessage());

            return;
        }

        $this->dispatch('notificacion', 'Proveedor eliminado.');
    }

    public function abrirFicha(int $id): void
    {
        $proveedor = Proveedor::findOrFail($id);
        $this->authorize('view', $proveedor);

        $this->fichaId = $proveedor->id;
        $this->pestana = 'datos';
        $this->modalFicha = true;
    }

    public function setPestana(string $pestana): void
    {
        if ($this->fichaId) {
            $this->authorize('view', Proveedor::findOrFail($this->fichaId));
        }

        $this->pestana = in_array($pestana, ['datos', 'insumos', 'facturas', 'cxp', 'comparador'], true) ? $pestana : 'datos';
    }

    public function abrirFactura(int $proveedorId): void
    {
        $this->authorize('create', Compra::class);

        $proveedor = Proveedor::findOrFail($proveedorId);

        $this->factura = [
            'proveedor_id' => $proveedor->id,
            'numero' => '',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'contado',
        ];
        $this->lineas = [['insumo_id' => null, 'cantidad' => 1, 'costo_unitario' => '']];
        $this->modalFactura = true;
    }

    public function agregarLinea(?int $insumoId = null): void
    {
        $this->authorize('create', Compra::class);

        $this->lineas[] = ['insumo_id' => $insumoId, 'cantidad' => 1, 'costo_unitario' => ''];
    }

    public function quitarLinea(int $indice): void
    {
        $this->authorize('create', Compra::class);

        unset($this->lineas[$indice]);
        $this->lineas = array_values($this->lineas);
    }

    public function guardarFactura(): void
    {
        $this->authorize('create', Compra::class);

        $validated = $this->validate([
            'factura.numero' => ['required', 'string', 'max:50'],
            'factura.fecha' => ['required', 'date'],
            'factura.forma_pago' => ['required', 'in:contado,credito'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.insumo_id' => ['required', 'integer', 'exists:insumos,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'lineas.*.costo_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $cabecera = [
            'proveedor_id' => $this->factura['proveedor_id'],
            'numero_factura' => $validated['factura']['numero'],
            'fecha' => $validated['factura']['fecha'],
            'forma_pago' => $validated['factura']['forma_pago'],
        ];

        try {
            app(CompraService::class)->registrarFactura($cabecera, $validated['lineas'], auth()->user());
        } catch (\InvalidArgumentException|\DomainException $e) {
            $this->addError('factura.numero', $e->getMessage());

            return;
        }

        $this->reset('factura', 'lineas', 'modalFactura');
        $this->dispatch('notificacion', 'Factura registrada.');
    }

    public function confirmarAnulacion(int $compraId): void
    {
        $compra = Compra::findOrFail($compraId);
        $this->authorize('anular', $compra);

        $this->anulandoId = $compra->id;
        $this->modalAnular = true;
    }

    public function anularFactura(int $compraId): void
    {
        $compra = Compra::findOrFail($compraId);
        $this->authorize('anular', $compra);

        try {
            app(CompraService::class)->anularFactura($compra, auth()->user());
        } catch (\DomainException $e) {
            $this->addError('general', $e->getMessage());

            return;
        }

        $this->reset('anulandoId', 'modalAnular');
        $this->dispatch('notificacion', 'Factura anulada.');
    }

    public function consultarReporte(): void
    {
        $this->authorize('viewAny', Proveedor::class);

        $this->validate([
            'reporteDesde' => ['required', 'date'],
            'reporteHasta' => ['required', 'date', 'after_or_equal:reporteDesde'],
        ]);

        $this->reporteConsultado = true;
    }

    public function cerrarModales(): void
    {
        $this->reset('modalCrear', 'modalEditar', 'modalFicha', 'modalFactura', 'modalAnular', 'enEdicion', 'anulandoId');
    }
}; ?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">local_shipping</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Proveedores
                </h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">
                    PRV-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Fichas de proveedores, facturas de compra y cuentas por pagar
            </p>
        </div>
        @can('create', App\Models\Proveedor::class)
            <button wire:click="abrirCrear" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Nuevo proveedor
            </button>
        @endcan
    </header>

    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @error('general')
        <div class="rounded-xl border border-error/40 bg-error/10 px-4 py-2 text-xs font-bold text-error">
            {{ $message }}
        </div>
    @enderror

    <!-- Filtros -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-4 shadow-sm">
        <input type="search" wire:model.live="busqueda" placeholder="Buscar por nombre o NIT…"
            class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm text-on-surface focus:border-primary focus:ring-0" />
        <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 text-xs font-bold text-on-surface-variant">
            <input type="checkbox" wire:model.live="soloActivos" class="rounded border-outline-variant/30 text-primary focus:ring-0" />
            Solo activos
        </label>
    </div>

    <!-- Lista de proveedores -->
    <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
        <h2 class="text-sm font-extrabold uppercase tracking-wider text-on-surface">Proveedores ({{ $proveedores->count() }})</h2>

        @if ($proveedores->isEmpty())
            <p class="mt-4 text-xs text-on-surface-variant">Sin proveedores registrados.</p>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($proveedores as $proveedor)
                    <div class="rounded-2xl border border-outline-variant/10 bg-surface-container-low p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-extrabold text-on-surface truncate">{{ $proveedor->nombre }}</p>
                                    @if ($proveedor->nit)
                                        <span class="rounded-lg bg-surface-container-high px-2 py-0.5 text-[10px] font-black text-on-surface-variant border border-outline-variant/30">
                                            NIT {{ $proveedor->nit }}
                                        </span>
                                    @endif
                                    <span class="rounded-lg px-2 py-0.5 text-[10px] font-black border {{ $proveedor->activo ? 'bg-secondary/10 text-secondary border-secondary/30' : 'bg-error/10 text-error border-error/30' }}">
                                        {{ $proveedor->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    {{ $proveedor->compras_count }} factura(s)
                                    @if ($proveedor->telefono)
                                        · {{ $proveedor->telefono }}
                                    @endif
                                    @if ($proveedor->dias_credito > 0)
                                        · {{ $proveedor->dias_credito }} días crédito
                                    @endif
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @can('view', $proveedor)
                                    <button wire:click="abrirFicha({{ $proveedor->id }})" class="rounded-xl bg-surface-container-high px-3 py-2 text-xs font-bold text-on-surface inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">id_card</span>
                                        Ficha
                                    </button>
                                @endcan
                                @can('create', App\Models\Compra::class)
                                    <button wire:click="abrirFactura({{ $proveedor->id }})" class="rounded-xl bg-secondary px-3 py-2 text-xs font-bold text-white inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                                        Factura
                                    </button>
                                @endcan
                                @can('update', $proveedor)
                                    <button wire:click="abrirEditar({{ $proveedor->id }})" class="rounded-xl bg-surface-container-high px-3 py-2 text-xs font-bold text-on-surface inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                        Editar
                                    </button>
                                    <button wire:click="desactivar({{ $proveedor->id }})" class="rounded-xl bg-surface-container-high px-3 py-2 text-xs font-bold text-on-surface inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">block</span>
                                        Desactivar
                                    </button>
                                @endcan
                                @can('delete', $proveedor)
                                    <button wire:click="eliminar({{ $proveedor->id }})" class="rounded-xl bg-error/10 px-3 py-2 text-xs font-bold text-error inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                        Eliminar
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Reporte gasto por proveedor -->
    @can('viewAny', App\Models\Proveedor::class)
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-on-surface">Gasto por proveedor</h2>
            <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4 sm:items-end">
                <div>
                    <label class="font-bold text-on-surface-variant">Desde</label>
                    <input type="date" wire:model="reporteDesde"
                        class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
                    @error('reporteDesde') <p class="mt-1 text-[11px] font-bold text-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="font-bold text-on-surface-variant">Hasta</label>
                    <input type="date" wire:model="reporteHasta"
                        class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
                    @error('reporteHasta') <p class="mt-1 text-[11px] font-bold text-error">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2 sm:col-span-2">
                    <button wire:click="consultarReporte" class="w-full rounded-xl bg-primary py-2.5 text-xs font-black text-on-primary sm:w-auto sm:px-6">
                        Consultar
                    </button>
                </div>
            </div>
            @if ($reporteConsultado)
                @if ($reporteGasto->isEmpty())
                    <p class="mt-4 text-xs text-on-surface-variant">Sin gasto registrado en el rango.</p>
                @else
                    <ul class="mt-4 space-y-2 text-xs">
                        @foreach ($reporteGasto as $fila)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-surface-container-low p-3">
                                <div>
                                    <p class="font-extrabold text-on-surface">{{ $fila->proveedor?->nombre ?? '—' }}</p>
                                    <p class="text-on-surface-variant">{{ $fila->facturas }} factura(s) · {{ $reporteTotal > 0 ? number_format((float) $fila->total / (float) $reporteTotal * 100, 2, ',', '.') : '0,00' }}% del gasto</p>
                                </div>
                                <p class="font-black text-on-surface">${{ number_format((float) $fila->total, 0, ',', '.') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    @endcan

    <!-- Modal crear -->
    @if ($modalCrear)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="cerrarModales">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl" wire:key="modal-crear-prov">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-on-surface">Nuevo proveedor</h3>
                    <button wire:click="cerrarModales" class="text-on-surface-variant">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form wire:submit="guardarProveedor" class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre *</label>
                        <input type="text" wire:model="nuevo.nombre" required
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        @error('nuevo.nombre') <p class="mt-1 text-[11px] font-bold text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">NIT</label>
                            <input type="text" wire:model="nuevo.nit"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                            @error('nuevo.nit') <p class="mt-1 text-[11px] font-bold text-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Teléfono</label>
                            <input type="text" wire:model="nuevo.telefono"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Email</label>
                        <input type="email" wire:model="nuevo.email"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Dirección</label>
                        <input type="text" wire:model="nuevo.direccion"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Contacto</label>
                            <input type="text" wire:model="nuevo.contacto"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Días crédito</label>
                            <input type="number" min="0" step="1" wire:model="nuevo.dias_credito"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary">
                        Guardar proveedor
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal editar -->
    @if ($modalEditar)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="cerrarModales">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl" wire:key="modal-editar-prov">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-on-surface">Editar proveedor</h3>
                    <button wire:click="cerrarModales" class="text-on-surface-variant">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form wire:submit="guardarEdicion" class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre *</label>
                        <input type="text" wire:model="edicion.nombre" required
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        @error('edicion.nombre') <p class="mt-1 text-[11px] font-bold text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">NIT</label>
                            <input type="text" wire:model="edicion.nit"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                            @error('edicion.nit') <p class="mt-1 text-[11px] font-bold text-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Teléfono</label>
                            <input type="text" wire:model="edicion.telefono"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Email</label>
                        <input type="email" wire:model="edicion.email"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Dirección</label>
                        <input type="text" wire:model="edicion.direccion"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Contacto</label>
                            <input type="text" wire:model="edicion.contacto"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Días crédito</label>
                            <input type="number" min="0" step="1" wire:model="edicion.dias_credito"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary">
                        Guardar cambios
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal ficha con pestañas -->
    @if ($modalFicha && $ficha)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="cerrarModales">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-2xl sm:rounded-3xl" wire:key="modal-ficha-prov">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-on-surface truncate">{{ $ficha->nombre }}</h3>
                    <button wire:click="cerrarModales" class="text-on-surface-variant">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="mt-3 flex gap-2 overflow-x-auto">
                    @foreach (['datos' => 'Datos', 'insumos' => 'Insumos', 'facturas' => 'Facturas', 'cxp' => 'CxP', 'comparador' => 'Comparador'] as $clave => $etiqueta)
                        <button wire:click="setPestana('{{ $clave }}')"
                            class="shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold {{ $pestana === $clave ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant' }}">
                            {{ $etiqueta }}
                        </button>
                    @endforeach
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <label class="font-bold text-on-surface-variant">Desde</label>
                        <input type="date" wire:model.live="fichaDesde"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                    <div>
                        <label class="font-bold text-on-surface-variant">Hasta</label>
                        <input type="date" wire:model.live="fichaHasta"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
                    </div>
                </div>

                @if ($fichaResumen)
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
                        <div class="rounded-xl bg-surface-container-low p-3"><p class="font-bold text-on-surface-variant">Total rango</p><p class="font-black text-on-surface">${{ number_format((float) $fichaResumen['total'], 0, ',', '.') }}</p></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><p class="font-bold text-on-surface-variant">Facturas</p><p class="font-black text-on-surface">{{ $fichaResumen['facturas'] }}</p></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><p class="font-bold text-on-surface-variant">Ticket prom.</p><p class="font-black text-on-surface">${{ number_format((float) $fichaResumen['ticket_promedio'], 0, ',', '.') }}</p></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><p class="font-bold text-on-surface-variant">CxP saldo</p><p class="font-black text-primary">${{ number_format((float) $fichaResumen['cxp_saldo'], 0, ',', '.') }}</p></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><p class="font-bold text-on-surface-variant">Participación</p><p class="font-black text-on-surface">{{ number_format((float) $fichaResumen['participacion'], 2, ',', '.') }}%</p></div>
                    </div>
                @endif

                @if ($pestana === 'datos')
                    <dl class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl bg-surface-container-low p-3"><dt class="font-bold text-on-surface-variant">NIT</dt><dd class="font-extrabold text-on-surface">{{ $ficha->nit ?? '—' }}</dd></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><dt class="font-bold text-on-surface-variant">Teléfono</dt><dd class="font-extrabold text-on-surface">{{ $ficha->telefono ?? '—' }}</dd></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><dt class="font-bold text-on-surface-variant">Email</dt><dd class="font-extrabold text-on-surface">{{ $ficha->email ?? '—' }}</dd></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><dt class="font-bold text-on-surface-variant">Contacto</dt><dd class="font-extrabold text-on-surface">{{ $ficha->contacto ?? '—' }}</dd></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><dt class="font-bold text-on-surface-variant">Dirección</dt><dd class="font-extrabold text-on-surface">{{ $ficha->direccion ?? '—' }}</dd></div>
                        <div class="rounded-xl bg-surface-container-low p-3"><dt class="font-bold text-on-surface-variant">Días crédito</dt><dd class="font-extrabold text-on-surface">{{ $ficha->dias_credito }}</dd></div>
                    </dl>
                @elseif ($pestana === 'insumos')
                    @if ($ficha->insumos->isEmpty())
                        <p class="mt-4 text-xs text-on-surface-variant">Este proveedor aún no surte insumos vinculados.</p>
                    @else
                        <ul class="mt-4 divide-y divide-outline-variant/10 text-xs">
                            @foreach ($ficha->insumos as $insumo)
                                <li class="flex justify-between py-2">
                                    <span class="font-bold text-on-surface">{{ $insumo->nombre }}</span>
                                    <span class="text-on-surface-variant">Último costo ${{ number_format((float) $insumo->costo_unitario, 0, ',', '.') }} / {{ $insumo->unidad_medida }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @elseif ($pestana === 'facturas')
                    @if ($ficha->compras->isEmpty())
                        <p class="mt-4 text-xs text-on-surface-variant">Sin facturas registradas.</p>
                    @else
                        <ul class="mt-4 space-y-2 text-xs">
                            @foreach ($ficha->compras as $compra)
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-surface-container-low p-3">
                                    <div>
                                        <p class="font-extrabold text-on-surface">#{{ $compra->numero_factura }} · ${{ number_format((float) $compra->subtotal, 0, ',', '.') }}</p>
                                        <p class="text-on-surface-variant">{{ $compra->fecha?->format('d/m/Y') }} · {{ $compra->forma_pago }} · {{ $compra->estado }}</p>
                                    </div>
                                    @can('anular', $compra)
                                        @if ($compra->estado === 'registrada')
                                            <button wire:click="confirmarAnulacion({{ $compra->id }})" class="rounded-xl bg-error/10 px-3 py-1.5 text-[11px] font-bold text-error">
                                                Anular
                                            </button>
                                        @endif
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @elseif ($pestana === 'comparador')
                    <div class="mt-4 space-y-3 text-xs">
                        <div>
                            <label class="font-bold text-on-surface-variant">Insumo a comparar</label>
                            <select wire:model.live="comparadorInsumoId"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                <option value="">Seleccionar…</option>
                                @foreach ($insumosConCompras as $insumo)
                                    <option value="{{ $insumo->id }}">{{ $insumo->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($comparadorInsumoId && empty($comparadorFilas))
                            <p class="text-on-surface-variant">Sin compras registradas para este insumo.</p>
                        @elseif (! empty($comparadorFilas))
                            <ul class="space-y-2">
                                @foreach ($comparadorFilas as $fila)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-surface-container-low p-3">
                                        <div>
                                            <p class="font-extrabold text-on-surface">
                                                @if ($fila['proveedor_id'] === $comparadorMejorId)
                                                    ★
                                                @endif
                                                {{ $fila['proveedor'] }}
                                            </p>
                                            <p class="text-on-surface-variant">Última compra {{ $fila['ultima_fecha'] }}</p>
                                            @if ($fila['delta_vs_referencia'] !== null && $fila['delta_vs_referencia'] > 0)
                                                <p class="font-bold text-error">Sobre referencia ({{ number_format((float) $fila['delta_vs_referencia'], 2, ',', '.') }}%)</p>
                                            @elseif ($fila['delta_vs_referencia'] !== null)
                                                <p class="text-on-surface-variant">vs referencia {{ number_format((float) $fila['delta_vs_referencia'], 2, ',', '.') }}%</p>
                                            @endif
                                        </div>
                                        <p class="font-black text-on-surface">${{ number_format((float) $fila['ultimo_costo'], 0, ',', '.') }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @else
                    @php($cxps = $ficha->compras->filter(fn ($c) => $c->cxp))
                    @if ($cxps->isEmpty())
                        <p class="mt-4 text-xs text-on-surface-variant">Sin cuentas por pagar vinculadas.</p>
                    @else
                        <ul class="mt-4 space-y-2 text-xs">
                            @foreach ($cxps as $compra)
                                <li class="flex justify-between rounded-xl bg-surface-container-low p-3">
                                    <span class="font-bold text-on-surface">Factura #{{ $compra->numero_factura }} · {{ $compra->cxp->estado }}</span>
                                    <span class="font-black text-primary">${{ number_format((float) $compra->cxp->saldo_pendiente, 0, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <!-- Modal factura -->
    @if ($modalFactura)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="cerrarModales">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-xl sm:rounded-3xl" wire:key="modal-factura-prov">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-on-surface">Registrar factura</h3>
                    <button wire:click="cerrarModales" class="text-on-surface-variant">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                @error('factura.numero')
                    <p class="mt-3 rounded-xl bg-error/10 px-3 py-2 text-xs font-bold text-error">{{ $message }}</p>
                @enderror

                <form wire:submit="guardarFactura" class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Número *</label>
                            <input type="text" wire:model="factura.numero" required
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Fecha *</label>
                            <input type="date" wire:model="factura.fecha"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Forma de pago *</label>
                        <select wire:model="factura.forma_pago"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0">
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-on-surface">Líneas</h4>
                        <button type="button" wire:click="agregarLinea" class="inline-flex items-center gap-1 rounded-xl bg-surface-container-high px-3 py-1.5 text-[11px] font-bold text-on-surface">
                            <span class="material-symbols-outlined text-[16px]">add</span>
                            Agregar línea
                        </button>
                    </div>
                    @error('lineas') <p class="text-[11px] font-bold text-error">{{ $message }}</p> @enderror

                    @foreach ($lineas as $indice => $linea)
                        <div class="grid grid-cols-12 items-end gap-2 rounded-2xl border border-outline-variant/10 bg-surface-container-low p-3" wire:key="linea-{{ $indice }}">
                            <div class="col-span-5">
                                <label class="text-xs font-bold text-on-surface-variant">Insumo</label>
                                <select wire:model="lineas.{{ $indice }}.insumo_id"
                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-2 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                    <option value="">Seleccionar…</option>
                                    @foreach ($insumosActivos as $insumo)
                                        <option value="{{ $insumo->id }}">{{ $insumo->nombre }} ({{ $insumo->unidad_medida }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-3">
                                <label class="text-xs font-bold text-on-surface-variant">Cantidad</label>
                                <input type="text" inputmode="decimal" data-miles data-decimales="3" min="0.01" wire:model="lineas.{{ $indice }}.cantidad"
                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-2 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
                            </div>
                            <div class="col-span-3">
                                <label class="text-xs font-bold text-on-surface-variant">Costo unit.</label>
                                <input type="text" inputmode="decimal" data-miles data-decimales="2" min="0" wire:model="lineas.{{ $indice }}.costo_unitario"
                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-2 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
                            </div>
                            <div class="col-span-1">
                                <button type="button" wire:click="quitarLinea({{ $indice }})" class="text-error">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>
                        </div>
                    @endforeach

                    <button type="submit" wire:loading.attr="disabled" wire:target="guardarFactura" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary disabled:opacity-50">
                        <span wire:loading.remove wire:target="guardarFactura">Guardar factura</span>
                        <span wire:loading wire:target="guardarFactura">Guardando…</span>
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal anular -->
    @if ($modalAnular && $anulandoId)
        @php($anulando = App\Models\Compra::with('proveedor')->find($anulandoId))
        @if ($anulando)
            <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="cerrarModales">
                <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl" wire:key="modal-anular-prov">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-extrabold text-on-surface">Anular factura</h3>
                        <button wire:click="cerrarModales" class="text-on-surface-variant">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    <p class="mt-3 text-xs text-on-surface-variant">
                        Se anulará la factura <span class="font-black text-on-surface">#{{ $anulando->numero_factura }}</span>
                        de <span class="font-black text-on-surface">{{ $anulando->proveedor?->nombre }}</span>
                        por un total de <span class="font-black text-error">${{ number_format((float) $anulando->subtotal, 0, ',', '.') }}</span>.
                        Esta acción reversa el inventario y no se puede deshacer.
                    </p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button wire:click="cerrarModales" class="rounded-xl bg-surface-container-high py-3 text-sm font-bold text-on-surface">
                            Cancelar
                        </button>
                        <button wire:click="anularFactura({{ $anulando->id }})" class="rounded-xl bg-error py-3 text-sm font-black text-white">
                            Confirmar anulación
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
