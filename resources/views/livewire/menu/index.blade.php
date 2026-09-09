<?php

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\MenuService;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $mostrarModalCategoria = false;
    public bool $mostrarModalProducto = false;
    public ?int $categoriaEnEdicion = null;
    public ?int $productoEnEdicion = null;

    public array $categoriaForm = [
        'nombre' => '',
        'icono' => '🍣',
        'orden' => 0,
    ];

    public array $productoForm = [
        'categoria_id' => null,
        'nombre' => '',
        'descripcion' => '',
        'precio' => null,
        'costo' => 0,
        'area_cocina' => 'sushi',
    ];

    public function abrirNuevaCategoria(): void
    {
        $this->categoriaEnEdicion = null;
        $this->categoriaForm = ['nombre' => '', 'icono' => '🍣', 'orden' => 0];
        $this->mostrarModalCategoria = true;
    }

    public function abrirEditarCategoria(int $id): void
    {
        $categoria = Categoria::findOrFail($id);
        $this->categoriaEnEdicion = $id;
        $this->categoriaForm = [
            'nombre' => $categoria->nombre,
            'icono' => $categoria->icono ?? '🍣',
            'orden' => (int) $categoria->orden,
        ];
        $this->mostrarModalCategoria = true;
    }

    public function guardarCategoria(): void
    {
        $this->validate([
            'categoriaForm.nombre' => 'required|string|min:2|max:100',
            'categoriaForm.icono' => 'nullable|string|max:5',
            'categoriaForm.orden' => 'nullable|integer|min:0',
        ]);

        try {
            if ($this->categoriaEnEdicion) {
                app(MenuService::class)->actualizarCategoria(
                    Categoria::findOrFail($this->categoriaEnEdicion),
                    $this->categoriaForm
                );
            } else {
                app(MenuService::class)->crearCategoria($this->categoriaForm);
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('categoriaForm.nombre', $e->getMessage());
            return;
        }

        $this->mostrarModalCategoria = false;
        $this->dispatch('notificacion', ['mensaje' => 'Categoría guardada correctamente.', 'tipo' => 'success']);
    }

    public function toggleCategoria(int $id): void
    {
        $categoria = Categoria::findOrFail($id);
        app(MenuService::class)->desactivarCategoria($categoria);
        $this->dispatch('notificacion', ['mensaje' => 'Categoría desactivada.', 'tipo' => 'info']);
    }

    public function abrirNuevoProducto(): void
    {
        $this->productoEnEdicion = null;
        $this->productoForm = [
            'categoria_id' => Categoria::where('activo', true)->value('id'),
            'nombre' => '',
            'descripcion' => '',
            'precio' => null,
            'costo' => 0,
            'area_cocina' => 'sushi',
        ];
        $this->mostrarModalProducto = true;
    }

    public function abrirEditarProducto(int $id): void
    {
        $producto = Producto::findOrFail($id);
        $this->productoEnEdicion = $id;
        $this->productoForm = [
            'categoria_id' => $producto->categoria_id,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion ?? '',
            'precio' => (float) $producto->precio,
            'costo' => (float) $producto->costo,
            'area_cocina' => $producto->area_cocina,
        ];
        $this->mostrarModalProducto = true;
    }

    public function guardarProducto(): void
    {
        $this->validate([
            'productoForm.categoria_id' => 'required|exists:categorias,id',
            'productoForm.nombre' => 'required|string|min:2|max:150',
            'productoForm.precio' => 'required|numeric|gt:0',
            'productoForm.costo' => 'nullable|numeric|min:0',
            'productoForm.area_cocina' => 'required|in:sushi,caliente,barra',
        ]);

        try {
            if ($this->productoEnEdicion) {
                app(MenuService::class)->actualizarProducto(
                    Producto::findOrFail($this->productoEnEdicion),
                    $this->productoForm
                );
            } else {
                app(MenuService::class)->crearProducto($this->productoForm);
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('productoForm.nombre', $e->getMessage());
            return;
        }

        $this->mostrarModalProducto = false;
        $this->dispatch('notificacion', ['mensaje' => 'Producto guardado correctamente.', 'tipo' => 'success']);
    }

    public function toggleProducto(int $id): void
    {
        $producto = Producto::findOrFail($id);
        app(MenuService::class)->desactivarProducto($producto);
        $this->dispatch('notificacion', ['mensaje' => 'Producto desactivado del catálogo.', 'tipo' => 'info']);
    }

    public function with(): array
    {
        return [
            'categorias' => Categoria::with('productos')->orderBy('orden')->orderBy('nombre')->get(),
            'productos' => Producto::with('categoria')->orderBy('nombre')->get(),
        ];
    }
}; ?>

<x-slot name="header">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">restaurant_menu</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Gestión de Menú
                </h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">
                    MEN-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Categorías y productos de la carta · solo Gerencia
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button
                wire:click="abrirNuevaCategoria"
                class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3.5 py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all"
            >
                <span class="material-symbols-outlined text-[18px]">category</span>
                <span>Categoría</span>
            </button>
            <button
                wire:click="abrirNuevoProducto"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-black text-on-primary shadow-md shadow-primary/25 hover:bg-primary/90 active:scale-95 transition-all"
            >
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>Nuevo Producto</span>
            </button>
        </div>
    </div>
</x-slot>

<div class="space-y-6">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($categorias as $categoria)
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-outline-variant/20 shadow-sm {{ $categoria->activo ? '' : 'opacity-55' }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-fixed text-xl border border-primary-fixed-dim">
                            {{ $categoria->icono ?? '🍣' }}
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                                {{ $categoria->nombre }}
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-[10px] font-mono text-on-surface-variant">
                                    {{ $categoria->productos->count() }} pltos
                                </span>
                            </h3>
                            <span class="text-[10px] font-mono text-on-surface-variant">{{ $categoria->slug }}</span>
                        </div>
                    </div>
                    @if(! $categoria->activo)
                        <span class="rounded-full bg-error/15 px-2 py-0.5 text-[9px] font-extrabold uppercase text-error">Inactiva</span>
                    @endif
                </div>

                <div class="mt-4 space-y-1.5">
                    @forelse($categoria->productos as $producto)
                        <div class="flex items-center justify-between rounded-xl bg-surface-container-low px-3 py-2">
                            <div>
                                <span class="text-xs font-bold text-on-surface block">{{ $producto->nombre }}</span>
                                <span class="text-[10px] text-on-surface-variant font-mono">{{ $producto->area_cocina }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-primary">${{ number_format((float) $producto->precio, 0, ',', '.') }}</span>
                                <button wire:click="abrirEditarProducto({{ $producto->id }})" class="text-on-surface-variant hover:text-on-surface">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                </button>
                                <button wire:click="toggleProducto({{ $producto->id }})" class="text-on-surface-variant hover:text-error" title="Desactivar">
                                    <span class="material-symbols-outlined text-[16px]">visibility_off</span>
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-[11px] text-on-surface-variant italic px-1">Sin productos activos en esta categoría.</p>
                    @endforelse
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <button
                        wire:click="abrirEditarCategoria({{ $categoria->id }})"
                        class="inline-flex items-center gap-1 rounded-lg border border-outline-variant/30 bg-surface-container-low px-2.5 py-1.5 text-[11px] font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all"
                    >
                        <span class="material-symbols-outlined text-[14px]">edit</span>
                        Editar
                    </button>
                    @if($categoria->activo)
                        <button
                            wire:click="toggleCategoria({{ $categoria->id }})"
                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-error bg-error/10 hover:bg-error/20 active:scale-95 transition-all"
                        >
                            <span class="material-symbols-outlined text-[14px]">toggle_off</span>
                            Desactivar
                        </button>
                    @endif
                </div>
            </div>
        @endforeach

        <button
            wire:click="abrirNuevaCategoria"
            class="rounded-3xl border-2 border-dashed border-outline-variant/50 p-5 flex flex-col items-center justify-center gap-2 text-on-surface-variant hover:border-primary hover:text-primary transition-all min-h-[200px]"
        >
            <span class="material-symbols-outlined text-[36px]">add_box</span>
            <span class="text-xs font-bold">Nueva Categoría</span>
        </button>
    </div>

    @if($mostrarModalCategoria)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[22px] text-primary">{{ $categoriaEnEdicion ? 'edit' : 'category' }}</span>
                        <h3 class="text-base font-extrabold text-on-surface">
                            {{ $categoriaEnEdicion ? 'Editar Categoría' : 'Nueva Categoría' }}
                        </h3>
                    </div>
                    <button wire:click="$set('mostrarModalCategoria', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre:</label>
                        <input type="text" wire:model="categoriaForm.nombre" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: Rollos Especiales" />
                        @error('categoriaForm.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Ícono:</label>
                            <input type="text" wire:model="categoriaForm.icono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-center text-lg text-on-surface focus:border-primary focus:ring-0" placeholder="🍣" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Orden:</label>
                            <input type="number" wire:model="categoriaForm.orden" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" min="0" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalCategoria', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface">
                        Cancelar
                    </button>
                    <button wire:click="guardarCategoria" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary/90">
                        ✓ Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($mostrarModalProducto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[22px] text-primary">ramen_dining</span>
                        <h3 class="text-base font-extrabold text-on-surface">
                            {{ $productoEnEdicion ? 'Editar Producto' : 'Nuevo Producto' }}
                        </h3>
                    </div>
                    <button wire:click="$set('mostrarModalProducto', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Categoría:</label>
                            <select wire:model="productoForm.categoria_id" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                <option value="">Seleccionar...</option>
                                @foreach($categorias->where('activo', true) as $categoria)
                                    <option value="{{ $categoria->id }}">{{ $categoria->icono }} {{ $categoria->nombre }}</option>
                                @endforeach
                            </select>
                            @error('productoForm.categoria_id') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Área de cocina:</label>
                            <select wire:model="productoForm.area_cocina" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                <option value="sushi">Sushi</option>
                                <option value="caliente">Caliente</option>
                                <option value="barra">Barra</option>
                            </select>
                            @error('productoForm.area_cocina') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre:</label>
                        <input type="text" wire:model="productoForm.nombre" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: Nigiri Salmón" />
                        @error('productoForm.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Descripción:</label>
                        <textarea wire:model="productoForm.descripcion" rows="2" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Ingredientes o notas de la carta..."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Precio venta:</label>
                            <input type="number" step="0.01" min="0" wire:model="productoForm.precio" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="12000" />
                            @error('productoForm.precio') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Costo (receta):</label>
                            <input type="number" step="0.01" min="0" wire:model="productoForm.costo" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="4500" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalProducto', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface">
                        Cancelar
                    </button>
                    <button wire:click="guardarProducto" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary/90">
                        ✓ Guardar Producto
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
