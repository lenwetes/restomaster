<?php

use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\CuentaPorPagar;
use App\Models\Insumo;
use App\Models\Proveedor;
use App\Services\CompraService;
use App\Services\CuentasPorPagarService;
use App\Services\ProveedorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

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

    // Facturación de Proveedor (Historial, Detalle, Pagos y Nueva Factura)
    public ?int $facturaProveedorId = null;

    public string $pestanaFactura = 'historial';

    public ?int $verDetalleCompraId = null;

    public ?int $pagandoCxpId = null;

    public array $pagoForm = [
        'monto' => '',
        'metodo_pago' => 'efectivo',
        'comprobante' => '',
        'notas' => '',
    ];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|\Illuminate\Http\UploadedFile|null */
    public mixed $soporteArchivo = null;

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
            ->with(['insumos' => fn ($query) => $query->select('id', 'proveedor_id', 'categoria')])
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
        $fichaInsumos = collect();
        $fichaCategorias = [];
        if ($this->fichaId) {
            $ficha = Proveedor::with([
                'insumos' => fn ($query) => $query->orderBy('nombre'),
                'compras' => fn ($query) => $query->latest()->with(['lineas.insumo', 'cxp']),
            ])->withCount('compras')->find($this->fichaId);

            if ($ficha) {
                $insumosData = app(ProveedorService::class)->insumosSuministrados($ficha);
                $fichaInsumos = $insumosData['insumos'];
                $fichaCategorias = $insumosData['categorias'];
            }
        }

        $proveedorFactura = null;
        if ($this->facturaProveedorId) {
            $proveedorFactura = Proveedor::with([
                'compras' => fn ($q) => $q->latest()->with(['lineas.insumo', 'cxp.pagos', 'usuario']),
            ])->find($this->facturaProveedorId);
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

        $insumosConCompras = ($this->modalFicha && $this->pestana === 'comparador')
            ? Insumo::whereIn('id', CompraLinea::distinct()->pluck('insumo_id'))->orderBy('nombre')->get(['id', 'nombre'])
            : collect();

        $insumosActivos = ($this->modalFactura && $this->pestanaFactura === 'nueva')
            ? Insumo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'unidad_medida', 'categoria'])
            : collect();

        $reporteGasto = $this->reporteConsultado && $this->reporteDesde !== '' && $this->reporteHasta !== ''
            ? app(CompraService::class)->gastoPorProveedor($this->reporteDesde, $this->reporteHasta)
            : collect();

        return [
            'proveedores' => $proveedores,
            'insumosActivos' => $insumosActivos,
            'ficha' => $ficha,
            'fichaInsumos' => $fichaInsumos,
            'fichaCategorias' => $fichaCategorias,
            'proveedorFactura' => $proveedorFactura,
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
        $this->authorize('viewAny', Compra::class);

        $proveedor = Proveedor::findOrFail($proveedorId);
        $this->facturaProveedorId = $proveedor->id;
        $this->pestanaFactura = $proveedor->compras()->exists() ? 'historial' : 'nueva';
        $this->verDetalleCompraId = null;
        $this->pagandoCxpId = null;
        $this->soporteArchivo = null;

        $this->factura = [
            'proveedor_id' => $proveedor->id,
            'numero' => '',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'contado',
        ];
        $this->lineas = [['insumo_id' => null, 'cantidad' => 1, 'costo_unitario' => '']];
        $this->modalFactura = true;
    }

    public function setPestanaFactura(string $pestana): void
    {
        $this->pestanaFactura = in_array($pestana, ['historial', 'nueva'], true) ? $pestana : 'historial';
        $this->pagandoCxpId = null;
        $this->verDetalleCompraId = null;
    }

    public function toggleDetalleCompra(int $compraId): void
    {
        $this->verDetalleCompraId = ($this->verDetalleCompraId === $compraId) ? null : $compraId;
    }

    public function abrirPagarFactura(int $cxpId): void
    {
        $cxp = CuentaPorPagar::findOrFail($cxpId);
        $this->authorize('update', $cxp);

        $this->pagandoCxpId = $cxp->id;
        $this->pagoForm = [
            'monto' => (string) (float) $cxp->saldo_pendiente,
            'metodo_pago' => 'efectivo',
            'comprobante' => '',
            'notas' => "Pago Factura #{$cxp->numero_factura}",
        ];
    }

    public function cancelarPagoFactura(): void
    {
        $this->pagandoCxpId = null;
        $this->pagoForm = [
            'monto' => '',
            'metodo_pago' => 'efectivo',
            'comprobante' => '',
            'notas' => '',
        ];
    }

    public function guardarPagoFactura(): void
    {
        if (! $this->pagandoCxpId) {
            return;
        }

        $cxp = CuentaPorPagar::findOrFail($this->pagandoCxpId);
        $this->authorize('update', $cxp);

        $this->validate([
            'pagoForm.monto' => ['required', 'numeric', 'min:1', "max:{$cxp->saldo_pendiente}"],
            'pagoForm.metodo_pago' => ['required', 'string', 'in:efectivo,transferencia,caja_menor,tarjeta'],
            'pagoForm.comprobante' => ['nullable', 'string', 'max:100'],
            'pagoForm.notas' => ['nullable', 'string', 'max:255'],
        ], [
            'pagoForm.monto.max' => 'El monto no puede superar el saldo pendiente ($'.number_format($cxp->saldo_pendiente, 0, ',', '.').').',
        ]);

        try {
            app(CuentasPorPagarService::class)->registrarPago(
                cuenta: $cxp,
                monto: (float) $this->pagoForm['monto'],
                usuario: auth()->user(),
                metodoPago: $this->pagoForm['metodo_pago'],
                concepto: "Pago factura #{$cxp->numero_factura}",
                comprobante: $this->pagoForm['comprobante'] ?: null,
                notas: $this->pagoForm['notas'] ?: null,
            );

            $this->dispatch('notificacion', 'Pago registrado exitosamente.');
            $this->cancelarPagoFactura();
        } catch (\Throwable $e) {
            $this->addError('pagoForm.general', $e->getMessage());
        }
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
            'soporteArchivo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $soporteRuta = null;
        if ($this->soporteArchivo) {
            $soporteRuta = $this->soporteArchivo->store('facturas_proveedores', 'public');
        }

        $cabecera = [
            'proveedor_id' => $this->factura['proveedor_id'],
            'numero_factura' => $validated['factura']['numero'],
            'fecha' => $validated['factura']['fecha'],
            'forma_pago' => $validated['factura']['forma_pago'],
            'soporte_factura' => $soporteRuta,
        ];

        try {
            app(CompraService::class)->registrarFactura($cabecera, $validated['lineas'], auth()->user());
        } catch (\InvalidArgumentException|\DomainException $e) {
            $this->addError('factura.numero', $e->getMessage());

            return;
        }

        $this->reset('lineas', 'soporteArchivo');
        $this->factura = [
            'proveedor_id' => $this->facturaProveedorId,
            'numero' => '',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'contado',
        ];
        $this->pestanaFactura = 'historial';
        $this->dispatch('notificacion', 'Factura registrada con éxito.');
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
        $this->reset('modalCrear', 'modalEditar', 'modalFicha', 'modalFactura', 'modalAnular', 'enEdicion', 'anulandoId', 'pagandoCxpId', 'soporteArchivo', 'verDetalleCompraId');
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
                                @php
                                    $categoriasProv = $proveedor->insumos->pluck('categoria')->filter()->unique();
                                @endphp
                                @if ($categoriasProv->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($categoriasProv as $cat)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-surface-container-highest px-2 py-0.5 text-[10px] font-bold text-on-surface-variant border border-outline-variant/20">
                                                <span class="material-symbols-outlined text-[12px] text-primary">category</span>
                                                {{ $cat }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
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
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-extrabold text-on-surface truncate">{{ $ficha->nombre }}</h3>
                            @if ($ficha->nit)
                                <span class="rounded-lg bg-surface-container-high px-2 py-0.5 text-[10px] font-black text-on-surface-variant border border-outline-variant/30">
                                    NIT {{ $ficha->nit }}
                                </span>
                            @endif
                        </div>
                        @if (! empty($fichaCategorias))
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($fichaCategorias as $catNom => $catCount)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-secondary/10 px-2 py-0.5 text-[10px] font-bold text-secondary border border-secondary/20">
                                        <span class="material-symbols-outlined text-[12px]">category</span>
                                        {{ $catNom }} ({{ $catCount }})
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <button wire:click="cerrarModales" class="text-on-surface-variant">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="mt-3 flex gap-2 overflow-x-auto">
                    @foreach (['datos' => 'Datos', 'insumos' => 'Insumos / Catálogo', 'facturas' => 'Facturas', 'cxp' => 'CxP', 'comparador' => 'Comparador'] as $clave => $etiqueta)
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
                    @if (! empty($fichaCategorias))
                        <div class="mt-3 rounded-2xl border border-outline-variant/15 bg-surface-container-low p-3">
                            <p class="text-xs font-bold text-on-surface-variant mb-2">Rubro y categorías suministradas:</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($fichaCategorias as $catNom => $catCount)
                                    <span class="rounded-xl bg-surface-container-high px-2.5 py-1 text-xs font-bold text-on-surface border border-outline-variant/30 flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[14px] text-primary">category</span>
                                        {{ $catNom }}
                                        <span class="rounded-full bg-primary/20 px-1.5 py-0.5 text-[10px] font-black text-primary">{{ $catCount }} insumo(s)</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @elseif ($pestana === 'insumos')
                    @if (! empty($fichaCategorias))
                        <div class="mt-3 flex flex-wrap items-center gap-1.5 rounded-2xl border border-outline-variant/15 bg-surface-container-low p-3">
                            <span class="text-[11px] font-bold text-on-surface-variant flex items-center gap-1 mr-1">
                                <span class="material-symbols-outlined text-[14px] text-primary">category</span>
                                Categorías que nos provee:
                            </span>
                            @foreach ($fichaCategorias as $catNom => $catCount)
                                <span class="rounded-lg bg-surface-container-high px-2 py-0.5 text-[11px] font-extrabold text-on-surface border border-outline-variant/30 flex items-center gap-1">
                                    {{ $catNom }}
                                    <span class="rounded-full bg-secondary/20 px-1 py-0.2 text-[9px] font-black text-secondary">{{ $catCount }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if ($fichaInsumos->isEmpty())
                        <div class="mt-4 rounded-2xl border border-dashed border-outline-variant/30 p-6 text-center">
                            <span class="material-symbols-outlined text-[32px] text-on-surface-variant/40">inventory_2</span>
                            <p class="mt-1 text-xs font-bold text-on-surface-variant">Este proveedor aún no cuenta con insumos registrados o comprados.</p>
                        </div>
                    @else
                        <div class="mt-3 overflow-x-auto rounded-2xl border border-outline-variant/15">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-surface-container-low text-[10px] font-black uppercase tracking-wider text-on-surface-variant">
                                    <tr>
                                        <th class="px-3 py-2.5">Insumo / Producto</th>
                                        <th class="px-3 py-2.5">Categoría</th>
                                        <th class="px-3 py-2.5">Unidad</th>
                                        <th class="px-3 py-2.5 text-right">Último Costo</th>
                                        <th class="px-3 py-2.5 text-right">Stock Actual</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/10 bg-surface-container-lowest">
                                    @foreach ($fichaInsumos as $insumo)
                                        <tr class="hover:bg-surface-container-low/40">
                                            <td class="px-3 py-2.5">
                                                <div class="font-extrabold text-on-surface">{{ $insumo->nombre }}</div>
                                                @if ($insumo->codigo)
                                                    <div class="text-[10px] text-on-surface-variant font-mono">{{ $insumo->codigo }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <span class="inline-flex items-center gap-1 rounded-md bg-surface-container-high px-2 py-0.5 text-[10px] font-bold text-on-surface">
                                                    {{ $insumo->categoria ?: 'General' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 font-medium text-on-surface-variant">
                                                {{ $insumo->unidad_medida }}
                                            </td>
                                            <td class="px-3 py-2.5 text-right font-black text-on-surface">
                                                ${{ number_format((float) ($insumo->costo_unitario ?? 0), 0, ',', '.') }}
                                            </td>
                                            <td class="px-3 py-2.5 text-right">
                                                <span class="font-bold {{ ($insumo->stock_actual ?? 0) > 0 ? 'text-secondary' : 'text-error' }}">
                                                    {{ number_format((float) ($insumo->stock_actual ?? 0), 2, ',', '.') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
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
                    @php
                        $cxps = $ficha->compras->filter(fn ($c) => $c->cxp);
                    @endphp
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

    <!-- Modal Factura Integral (Historial, Detalle, Pagos y Nueva Factura con Soporte) -->
    @if ($modalFactura && $proveedorFactura)
        @php
            $saldoTotalProveedor = $proveedorFactura->compras->sum(fn ($c) => $c->cxp?->saldo_pendiente ?? 0);
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="cerrarModales">
            <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-3xl sm:rounded-3xl" wire:key="modal-factura-prov-{{ $proveedorFactura->id }}">
                <!-- Cabecera del modal -->
                <div class="flex items-start justify-between gap-3 border-b border-outline-variant/15 pb-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="material-symbols-outlined text-[24px] text-primary">receipt_long</span>
                            <h3 class="text-base font-extrabold text-on-surface">Facturación — {{ $proveedorFactura->nombre }}</h3>
                            @if ($proveedorFactura->nit)
                                <span class="rounded-lg bg-surface-container-high px-2 py-0.5 text-[10px] font-black text-on-surface-variant border border-outline-variant/30">
                                    NIT {{ $proveedorFactura->nit }}
                                </span>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-on-surface-variant">
                            @if ($proveedorFactura->telefono)
                                <span>Tel: {{ $proveedorFactura->telefono }}</span>
                            @endif
                            @if ($proveedorFactura->dias_credito > 0)
                                <span>Crédito a {{ $proveedorFactura->dias_credito }} días</span>
                            @endif
                            @if ($saldoTotalProveedor > 0)
                                <span class="font-extrabold text-error">
                                    Saldo CxP pendiente: ${{ number_format((float) $saldoTotalProveedor, 0, ',', '.') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <button wire:click="cerrarModales" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <!-- Selector de pestañas -->
                <div class="mt-4 flex gap-2 border-b border-outline-variant/15 pb-3">
                    <button type="button" wire:click="setPestanaFactura('historial')"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all {{ $pestanaFactura === 'historial' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface' }}">
                        <span class="material-symbols-outlined text-[16px]">history</span>
                        Historial y Pagos
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black {{ $pestanaFactura === 'historial' ? 'bg-on-primary/20 text-on-primary' : 'bg-surface-container-highest text-on-surface' }}">
                            {{ $proveedorFactura->compras->count() }}
                        </span>
                    </button>
                    @can('create', App\Models\Compra::class)
                        <button type="button" wire:click="setPestanaFactura('nueva')"
                            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all {{ $pestanaFactura === 'nueva' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface' }}">
                            <span class="material-symbols-outlined text-[16px]">add_circle</span>
                            Registrar Nueva Factura
                        </button>
                    @endcan
                </div>

                <!-- Mensaje de errores globales -->
                @error('factura.numero')
                    <p class="mt-3 rounded-xl bg-error/10 px-3 py-2 text-xs font-bold text-error border border-error/30">{{ $message }}</p>
                @enderror
                @error('pagoForm.general')
                    <p class="mt-3 rounded-xl bg-error/10 px-3 py-2 text-xs font-bold text-error border border-error/30">{{ $message }}</p>
                @enderror

                <!-- PESTAÑA A: HISTORIAL, DETALLE Y PAGOS -->
                @if ($pestanaFactura === 'historial')
                    <div class="mt-4 space-y-3">
                        @if ($proveedorFactura->compras->isEmpty())
                            <div class="rounded-2xl border border-dashed border-outline-variant/30 p-8 text-center">
                                <span class="material-symbols-outlined text-[36px] text-on-surface-variant/40">receipt</span>
                                <p class="mt-2 text-xs font-bold text-on-surface-variant">No hay facturas registradas para este proveedor.</p>
                                @can('create', App\Models\Compra::class)
                                    <button type="button" wire:click="setPestanaFactura('nueva')" class="mt-3 rounded-xl bg-primary px-4 py-2 text-xs font-black text-on-primary">
                                        Registrar primera factura
                                    </button>
                                @endcan
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($proveedorFactura->compras as $compra)
                                    <div class="rounded-2xl border border-outline-variant/15 bg-surface-container-low p-4 transition-all" wire:key="compra-card-{{ $compra->id }}">
                                        <!-- Cabecera de la factura -->
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm font-black text-on-surface">
                                                        Factura #{{ $compra->numero_factura }}
                                                    </span>
                                                    <span class="text-xs text-on-surface-variant">
                                                        · {{ $compra->fecha?->format('d/m/Y') }}
                                                    </span>
                                                    <!-- Estado de la compra -->
                                                    @if ($compra->estado === 'anulada')
                                                        <span class="rounded-lg bg-error/10 px-2 py-0.5 text-[10px] font-black text-error border border-error/20">
                                                            Anulada
                                                        </span>
                                                    @elseif ($compra->forma_pago === 'contado')
                                                        <span class="rounded-lg bg-secondary/10 px-2 py-0.5 text-[10px] font-black text-secondary border border-secondary/20">
                                                            Contado · Pagada
                                                        </span>
                                                    @elseif ($compra->cxp && $compra->cxp->saldo_pendiente > 0)
                                                        <span class="rounded-lg bg-error/10 px-2 py-0.5 text-[10px] font-black text-error border border-error/20">
                                                            Crédito · Pendiente: ${{ number_format((float) $compra->cxp->saldo_pendiente, 0, ',', '.') }}
                                                        </span>
                                                    @elseif ($compra->cxp && $compra->cxp->saldo_pendiente <= 0)
                                                        <span class="rounded-lg bg-secondary/10 px-2 py-0.5 text-[10px] font-black text-secondary border border-secondary/20">
                                                            Crédito · Pagado
                                                        </span>
                                                    @else
                                                        <span class="rounded-lg bg-surface-container-high px-2 py-0.5 text-[10px] font-black text-on-surface-variant">
                                                            {{ ucfirst($compra->forma_pago) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-on-surface-variant">
                                                    <span>{{ $compra->lineas->count() }} insumo(s)</span>
                                                    @if ($compra->usuario)
                                                        <span>· Registrada por {{ $compra->usuario->name }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <div class="text-right">
                                                    <div class="text-xs font-bold text-on-surface-variant">Total</div>
                                                    <div class="text-sm font-black text-on-surface">
                                                        ${{ number_format((float) $compra->subtotal, 0, ',', '.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Botones de Acción de la Factura -->
                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-outline-variant/10 pt-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <!-- Botón Ver Detalle Toggle -->
                                                <button type="button" wire:click="toggleDetalleCompra({{ $compra->id }})"
                                                    class="inline-flex items-center gap-1 rounded-xl bg-surface-container-high px-3 py-1.5 text-xs font-bold text-on-surface hover:bg-surface-container-highest">
                                                    <span class="material-symbols-outlined text-[16px]">
                                                        {{ $verDetalleCompraId === $compra->id ? 'expand_less' : 'visibility' }}
                                                    </span>
                                                    {{ $verDetalleCompraId === $compra->id ? 'Ocultar Detalle' : 'Ver Detalle' }}
                                                </button>

                                                <!-- Botón Ver Soporte Adjunto (si existe) -->
                                                @if ($compra->soporte_factura)
                                                    <a href="{{ $compra->soporte_url }}" target="_blank"
                                                        class="inline-flex items-center gap-1 rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/20 border border-primary/20">
                                                        <span class="material-symbols-outlined text-[16px]">attachment</span>
                                                        Ver Soporte
                                                    </a>
                                                @endif
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                <!-- Botón Pagar / Abonar Factura si tiene CxP pendiente -->
                                                @if ($compra->cxp && $compra->cxp->saldo_pendiente > 0 && $compra->estado !== 'anulada')
                                                    @can('update', $compra->cxp)
                                                        <button type="button" wire:click="abrirPagarFactura({{ $compra->cxp->id }})"
                                                            class="inline-flex items-center gap-1 rounded-xl bg-secondary px-3 py-1.5 text-xs font-bold text-white hover:bg-secondary/90 shadow-sm">
                                                            <span class="material-symbols-outlined text-[16px]">payments</span>
                                                            Pagar / Abonar
                                                        </button>
                                                    @endcan
                                                @endif

                                                <!-- Botón Anular Factura -->
                                                @can('anular', $compra)
                                                    @if ($compra->estado === 'registrada')
                                                        <button type="button" wire:click="confirmarAnulacion({{ $compra->id }})"
                                                            class="rounded-xl bg-error/10 px-3 py-1.5 text-xs font-bold text-error hover:bg-error/20">
                                                            Anular
                                                        </button>
                                                    @endif
                                                @endcan
                                            </div>
                                        </div>

                                        <!-- Panel Desplegable: Detalle de Insumos de la Compra -->
                                        @if ($verDetalleCompraId === $compra->id)
                                            <div class="mt-3 overflow-x-auto rounded-xl border border-outline-variant/15 bg-surface-container-lowest p-3">
                                                <p class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant mb-2">Desglose de la factura:</p>
                                                <table class="w-full text-left text-xs">
                                                    <thead class="text-[10px] font-black uppercase text-on-surface-variant border-b border-outline-variant/10">
                                                        <tr>
                                                            <th class="py-1.5 pr-2">Insumo</th>
                                                            <th class="py-1.5 px-2">Categoría</th>
                                                            <th class="py-1.5 px-2 text-right">Cantidad</th>
                                                            <th class="py-1.5 px-2 text-right">Costo Unit.</th>
                                                            <th class="py-1.5 pl-2 text-right">Subtotal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-outline-variant/10">
                                                        @foreach ($compra->lineas as $linea)
                                                            <tr>
                                                                <td class="py-2 pr-2 font-bold text-on-surface">
                                                                    {{ $linea->insumo?->nombre ?? 'Insumo #'.$linea->insumo_id }}
                                                                </td>
                                                                <td class="py-2 px-2 text-on-surface-variant">
                                                                    <span class="rounded bg-surface-container-high px-1.5 py-0.5 text-[10px]">
                                                                        {{ $linea->insumo?->categoria ?? 'General' }}
                                                                    </span>
                                                                </td>
                                                                <td class="py-2 px-2 text-right font-medium text-on-surface-variant">
                                                                    {{ number_format((float) $linea->cantidad, 2, ',', '.') }} {{ $linea->insumo?->unidad_medida }}
                                                                </td>
                                                                <td class="py-2 px-2 text-right font-medium text-on-surface-variant">
                                                                    ${{ number_format((float) $linea->costo_unitario, 0, ',', '.') }}
                                                                </td>
                                                                <td class="py-2 pl-2 text-right font-black text-on-surface">
                                                                    ${{ number_format((float) $linea->subtotal, 0, ',', '.') }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif

                                        <!-- Panel Desplegable Inline: Formulario de Pago de Factura -->
                                        @if ($pagandoCxpId && $compra->cxp && $pagandoCxpId === $compra->cxp->id)
                                            <div class="mt-3 rounded-2xl border border-secondary/30 bg-secondary/5 p-4" wire:key="panel-pago-{{ $compra->cxp->id }}">
                                                <div class="flex items-center justify-between border-b border-secondary/20 pb-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="material-symbols-outlined text-[18px] text-secondary">point_of_sale</span>
                                                        <h5 class="text-xs font-black text-on-surface">
                                                            Registrar pago a Factura #{{ $compra->numero_factura }}
                                                        </h5>
                                                    </div>
                                                    <span class="text-xs font-extrabold text-secondary">
                                                        Saldo: ${{ number_format((float) $compra->cxp->saldo_pendiente, 0, ',', '.') }}
                                                    </span>
                                                </div>

                                                <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2 text-xs">
                                                    <div>
                                                        <label class="font-bold text-on-surface-variant">Monto a pagar *</label>
                                                        <div class="mt-1 flex gap-1.5">
                                                            <input type="number" step="0.01" min="1" max="{{ $compra->cxp->saldo_pendiente }}"
                                                                wire:model="pagoForm.monto" required placeholder="0.00"
                                                                class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:border-secondary focus:ring-0" />
                                                            <button type="button" wire:click="$set('pagoForm.monto', '{{ $compra->cxp->saldo_pendiente }}')"
                                                                class="shrink-0 rounded-xl bg-secondary/15 px-2.5 py-1 text-[11px] font-black text-secondary border border-secondary/30">
                                                                Total
                                                            </button>
                                                        </div>
                                                        @error('pagoForm.monto') <p class="mt-1 text-[10px] font-bold text-error">{{ $message }}</p> @enderror
                                                    </div>

                                                    <div>
                                                        <label class="font-bold text-on-surface-variant">Método de pago *</label>
                                                        <select wire:model="pagoForm.metodo_pago"
                                                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:border-secondary focus:ring-0">
                                                            <option value="efectivo">Efectivo</option>
                                                            <option value="transferencia">Transferencia bancaria</option>
                                                            <option value="caja_menor">Caja Menor</option>
                                                            <option value="tarjeta">Tarjeta débito/crédito</option>
                                                        </select>
                                                        @error('pagoForm.metodo_pago') <p class="mt-1 text-[10px] font-bold text-error">{{ $message }}</p> @enderror
                                                    </div>

                                                    <div>
                                                        <label class="font-bold text-on-surface-variant">Comprobante / Referencia</label>
                                                        <input type="text" wire:model="pagoForm.comprobante" placeholder="Ej. TRANSF-90214"
                                                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:border-secondary focus:ring-0" />
                                                    </div>

                                                    <div>
                                                        <label class="font-bold text-on-surface-variant">Notas u observaciones</label>
                                                        <input type="text" wire:model="pagoForm.notas" placeholder="Opcional..."
                                                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:border-secondary focus:ring-0" />
                                                    </div>
                                                </div>

                                                <div class="mt-3 flex justify-end gap-2">
                                                    <button type="button" wire:click="cancelarPagoFactura"
                                                        class="rounded-xl bg-surface-container-high px-3 py-2 text-xs font-bold text-on-surface">
                                                        Cancelar
                                                    </button>
                                                    <button type="button" wire:click="guardarPagoFactura" wire:loading.attr="disabled"
                                                        class="rounded-xl bg-secondary px-4 py-2 text-xs font-black text-white hover:bg-secondary/90 shadow-sm disabled:opacity-50">
                                                        <span wire:loading.remove wire:target="guardarPagoFactura">Confirmar Pago</span>
                                                        <span wire:loading wire:target="guardarPagoFactura">Procesando…</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                <!-- PESTAÑA B: REGISTRAR NUEVA FACTURA -->
                @if ($pestanaFactura === 'nueva')
                    @can('create', App\Models\Compra::class)
                        <form wire:submit="guardarFactura" class="mt-4 space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Número de Factura *</label>
                                    <input type="text" wire:model="factura.numero" required placeholder="Ej: FAC-00129"
                                        class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Fecha de Emisión *</label>
                                    <input type="date" wire:model="factura.fecha" required
                                        class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Forma de pago *</label>
                                    <select wire:model="factura.forma_pago"
                                        class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-on-surface focus:border-primary focus:ring-0">
                                        <option value="contado">Contado</option>
                                        <option value="credito">Crédito</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Soporte de Factura (Carga de Foto o Documento PDF Opcional) -->
                            <div class="rounded-2xl border border-dashed border-outline-variant/30 bg-surface-container-low/60 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-primary">add_a_photo</span>
                                        <label class="text-xs font-extrabold text-on-surface">
                                            Foto o Documento de la Factura (Opcional)
                                        </label>
                                    </div>
                                    <span class="text-[10px] text-on-surface-variant">JPG, PNG, WEBP o PDF (Máx. 10MB)</span>
                                </div>

                                <div class="mt-2.5 flex flex-wrap items-center gap-3">
                                    <input type="file" wire:model="soporteArchivo" id="soporte_file_input" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                        class="block w-full text-xs text-on-surface-variant file:mr-3 file:rounded-xl file:border-0 file:bg-surface-container-high file:px-3 file:py-2 file:text-xs file:font-bold file:text-on-surface hover:file:bg-surface-container-highest cursor-pointer" />
                                </div>

                                <div wire:loading wire:target="soporteArchivo" class="mt-2 text-xs font-bold text-primary flex items-center gap-1.5">
                                    <span class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                                    Cargando archivo adjunto…
                                </div>

                                @if ($soporteArchivo)
                                    <div class="mt-2.5 flex items-center justify-between rounded-xl bg-surface-container-high p-2.5 text-xs">
                                        <div class="flex items-center gap-2 truncate">
                                            @if (str_starts_with($soporteArchivo->getMimeType() ?? '', 'image/'))
                                                <img src="{{ $soporteArchivo->temporaryUrl() }}" class="h-10 w-10 rounded-lg object-cover border border-outline-variant/30" />
                                            @else
                                                <span class="material-symbols-outlined text-[24px] text-primary">picture_as_pdf</span>
                                            @endif
                                            <span class="font-bold text-on-surface truncate">{{ $soporteArchivo->getClientOriginalName() }}</span>
                                        </div>
                                        <button type="button" wire:click="$set('soporteArchivo', null)" class="text-error hover:opacity-80 p-1">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </div>
                                @endif
                                @error('soporteArchivo') <p class="mt-1 text-xs font-bold text-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Líneas de Insumos -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-on-surface">Líneas de insumos</h4>
                                    <button type="button" wire:click="agregarLinea" class="inline-flex items-center gap-1 rounded-xl bg-surface-container-high px-3 py-1.5 text-xs font-bold text-on-surface hover:bg-surface-container-highest">
                                        <span class="material-symbols-outlined text-[16px]">add</span>
                                        Agregar insumo
                                    </button>
                                </div>
                                @error('lineas') <p class="mt-1 text-xs font-bold text-error">{{ $message }}</p> @enderror

                                <div class="mt-2 space-y-2">
                                    @foreach ($lineas as $indice => $linea)
                                        <div class="grid grid-cols-12 items-end gap-2 rounded-2xl border border-outline-variant/10 bg-surface-container-low p-3" wire:key="linea-{{ $indice }}">
                                            <div class="col-span-12 sm:col-span-5">
                                                <label class="text-[11px] font-bold text-on-surface-variant">Insumo *</label>
                                                <select wire:model="lineas.{{ $indice }}.insumo_id" required
                                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-2 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                                    <option value="">Seleccionar insumo…</option>
                                                    @foreach ($insumosActivos as $insumo)
                                                        <option value="{{ $insumo->id }}">{{ $insumo->nombre }} ({{ $insumo->categoria ?: 'General' }} - {{ $insumo->unidad_medida }})</option>
                                                    @endforeach
                                                </select>
                                                @error("lineas.{$indice}.insumo_id") <p class="text-[10px] text-error font-bold">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="col-span-5 sm:col-span-3">
                                                <label class="text-[11px] font-bold text-on-surface-variant">Cantidad *</label>
                                                <input type="text" inputmode="decimal" data-miles data-decimales="3" min="0.01" wire:model="lineas.{{ $indice }}.cantidad" required
                                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-2 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
                                                @error("lineas.{$indice}.cantidad") <p class="text-[10px] text-error font-bold">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="col-span-5 sm:col-span-3">
                                                <label class="text-[11px] font-bold text-on-surface-variant">Costo Unit. *</label>
                                                <input type="text" inputmode="decimal" data-miles data-decimales="2" min="0" wire:model="lineas.{{ $indice }}.costo_unitario" required
                                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-2 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
                                                @error("lineas.{$indice}.costo_unitario") <p class="text-[10px] text-error font-bold">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="col-span-2 sm:col-span-1 flex justify-center pb-1">
                                                <button type="button" wire:click="quitarLinea({{ $indice }})" class="text-error hover:opacity-75">
                                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Botón Guardar Factura -->
                            <button type="submit" wire:loading.attr="disabled" wire:target="guardarFactura,soporteArchivo"
                                class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary shadow-sm disabled:opacity-50 transition-all hover:bg-primary/95">
                                <span wire:loading.remove wire:target="guardarFactura">Guardar factura y registrar inventario</span>
                                <span wire:loading wire:target="guardarFactura">Guardando factura…</span>
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    @endif

    <!-- Modal anular -->
    @if ($modalAnular && $anulandoId)
        @php
            $anulando = App\Models\Compra::with('proveedor')->find($anulandoId);
        @endphp
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
