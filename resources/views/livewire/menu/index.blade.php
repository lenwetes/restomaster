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
    public ?string $mensajeExito = null;

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
        abort_unless(auth()->user()?->role?->slug === 'admin', 403, 'Acción reservada al Administrador.');
        $this->categoriaEnEdicion = null;
        $this->categoriaForm = ['nombre' => '', 'icono' => '🍣', 'orden' => 0];
        $this->mostrarModalCategoria = true;
    }

    public function abrirEditarCategoria(int $id): void
    {
        abort_unless(auth()->user()?->role?->slug === 'admin', 403, 'Acción reservada al Administrador.');
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
        abort_unless(auth()->user()?->role?->slug === 'admin', 403, 'Acción reservada al Administrador.');
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
                $this->mensajeExito = 'Categoría actualizada con éxito.';
            } else {
                app(MenuService::class)->crearCategoria($this->categoriaForm);
                $this->mensajeExito = 'Categoría creada con éxito en la carta.';
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('categoriaForm.nombre', $e->getMessage());
            return;
        }

        $this->mostrarModalCategoria = false;
        $this->dispatch('notificacion', ['mensaje' => $this->mensajeExito, 'tipo' => 'success']);
    }

    public function toggleCategoria(int $id): void
    {
        abort_unless(auth()->user()?->role?->slug === 'admin', 403, 'Acción reservada al Administrador.');
        $categoria = Categoria::findOrFail($id);
        if ($categoria->activo) {
            app(MenuService::class)->desactivarCategoria($categoria);
            $this->mensajeExito = "Categoría '{$categoria->nombre}' desactivada.";
            $this->dispatch('notificacion', ['mensaje' => $this->mensajeExito, 'tipo' => 'info']);
        } else {
            app(MenuService::class)->activarCategoria($categoria);
            $this->mensajeExito = "Categoría '{$categoria->nombre}' activada.";
            $this->dispatch('notificacion', ['mensaje' => $this->mensajeExito, 'tipo' => 'success']);
        }
    }

    public function abrirNuevoProducto(?int $categoriaId = null): void
    {
        abort_unless(auth()->user()?->role?->slug === 'admin', 403, 'Acción reservada al Administrador.');
        $this->productoEnEdicion = null;
        $primerCategoria = $categoriaId ?: Categoria::where('activo', true)->orderBy('orden')->value('id');
        $this->productoForm = [
            'categoria_id' => $primerCategoria,
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
        abort_unless(auth()->user()?->role?->slug === 'admin', 403, 'Acción reservada al Administrador.');
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
        abort_unless(auth()->user()?->role?->slug === 'admin', 403, 'Acción reservada al Administrador.');
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
                $this->mensajeExito = '¡Producto "' . $this->productoForm['nombre'] . '" actualizado con éxito!';
            } else {
                app(MenuService::class)->crearProducto($this->productoForm);
                $this->mensajeExito = '¡Producto "' . $this->productoForm['nombre'] . '" creado con éxito en la carta!';
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('productoForm.nombre', $e->getMessage());
            return;
        }

        $this->mostrarModalProducto = false;
        $this->dispatch('notificacion', ['mensaje' => $this->mensajeExito, 'tipo' => 'success']);
    }

    public function toggleProducto(int $id): void
    {
        abort_unless(auth()->user()?->role?->slug === 'admin', 403, 'Acción reservada al Administrador.');
        $producto = Producto::findOrFail($id);
        if ($producto->activo) {
            app(MenuService::class)->desactivarProducto($producto);
            $this->mensajeExito = "Producto '{$producto->nombre}' desactivado de la carta.";
            $this->dispatch('notificacion', ['mensaje' => $this->mensajeExito, 'tipo' => 'info']);
        } else {
            app(MenuService::class)->activarProducto($producto);
            $this->mensajeExito = "Producto '{$producto->nombre}' reactivado en la carta.";
            $this->dispatch('notificacion', ['mensaje' => $this->mensajeExito, 'tipo' => 'success']);
        }
    }

    public function with(): array
    {
        return [
            'categorias' => Categoria::with(['productos' => function ($q) {
                $q->orderBy('nombre');
            }])->orderBy('orden')->orderBy('nombre')->get(),
            'productos' => Producto::with('categoria')->orderBy('nombre')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header Operativo (Aura Gastro Expressive OS) -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-surface-container-lowest p-5 rounded-3xl border border-surface-container-highest shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">restaurant_menu</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Carta & Menú de Platos y Servicios
                </h1>
                <span class="rounded-full bg-secondary-container/50 px-2.5 py-0.5 text-[11px] font-bold text-on-secondary-container border border-secondary/30">
                    MEN-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Creación y administración en vivo de platos, rolls, bebidas, precios y estaciones
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(auth()->user()?->role?->slug === 'admin')
                <button
                    wire:click="abrirNuevaCategoria"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3.5 py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all cursor-pointer shadow-sm"
                >
                    <span class="material-symbols-outlined text-[18px]">category</span>
                    <span>+ Nueva Categoría</span>
                </button>
                <button
                    wire:click="abrirNuevoProducto"
                    type="button"
                    id="btnNuevoProducto"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-black text-on-primary shadow-md shadow-primary/25 hover:bg-primary-container transition-all active:scale-95 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    <span>+ Nuevo Producto</span>
                </button>
            @endif
            <a 
                href="{{ route('pos') }}" 
                wire:navigate
                class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3.5 py-2.5 text-xs font-bold text-primary hover:bg-primary/10 transition-all active:scale-95 shadow-sm"
            >
                <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
                <span>Ir al POS</span>
            </a>
        </div>
    </header>

    @if($mensajeExito)
        <div class="flex items-center justify-between rounded-2xl bg-secondary-container/50 p-4 border border-secondary/30 text-on-secondary-container animate-fade-in">
            <div class="flex items-center gap-2 font-bold text-xs">
                <span class="material-symbols-outlined text-secondary text-[20px]">check_circle</span>
                <span>{{ $mensajeExito }}</span>
            </div>
            <button wire:click="$set('mensajeExito', null)" class="text-xs font-bold text-on-secondary-container/70 hover:text-on-secondary-container cursor-pointer">✕</button>
        </div>
    @endif

    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($categorias as $categoria)
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-outline-variant/20 shadow-sm flex flex-col justify-between {{ $categoria->activo ? '' : 'opacity-60 bg-surface-container-low' }}">
                <div>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-fixed text-xl border border-primary-fixed-dim shadow-xs">
                                {{ $categoria->icono ?? '🍣' }}
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                                    {{ $categoria->nombre }}
                                    <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-[10px] font-mono text-on-surface-variant font-bold">
                                        {{ $categoria->productos->count() }} ítems
                                    </span>
                                </h3>
                                <span class="text-[10px] font-mono text-on-surface-variant">slug: {{ $categoria->slug }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            @if(! $categoria->activo)
                                <span class="rounded-full bg-error/15 px-2 py-0.5 text-[9px] font-extrabold uppercase text-error">Inactiva</span>
                            @endif
                        @if(auth()->user()?->role?->slug === 'admin')
                            <button
                                wire:click="abrirNuevoProducto({{ $categoria->id }})"
                                class="inline-flex items-center justify-center h-8 w-8 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-on-primary transition-all text-xs font-bold cursor-pointer"
                                title="Agregar producto a {{ $categoria->nombre }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">add</span>
                            </button>
                        @endif
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 max-h-[360px] overflow-y-auto pr-1">
                        @forelse($categoria->productos as $producto)
                            <div class="flex items-center justify-between rounded-2xl bg-surface-container-low px-3.5 py-2.5 border border-outline-variant/15 transition-all hover:border-outline-variant/30 {{ $producto->activo ? '' : 'opacity-55' }}">
                                <div class="min-w-0 flex-1 pr-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-bold text-on-surface truncate">{{ $producto->nombre }}</span>
                                        @if(!$producto->activo)
                                            <span class="rounded-full bg-error/10 px-1.5 py-0.2 text-[9px] font-bold text-error">Inactivo</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="rounded bg-surface-container-high px-1.5 py-0.2 text-[9px] text-on-surface-variant font-mono uppercase font-semibold">
                                            {{ $producto->area_cocina }}
                                        </span>
                                        @if($producto->costo > 0)
                                            <span class="text-[10px] text-on-surface-variant font-mono">
                                                Costo: ${{ number_format((float) $producto->costo, 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs font-black text-primary font-mono">${{ number_format((float) $producto->precio, 0, ',', '.') }}</span>
                                    @if(auth()->user()?->role?->slug === 'admin')
                                        <button 
                                            wire:click="abrirEditarProducto({{ $producto->id }})" 
                                            class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-on-surface-variant hover:text-on-surface hover:bg-surface-container cursor-pointer transition"
                                            title="Editar {{ $producto->nombre }}"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">edit</span>
                                        </button>
                                        <button 
                                            wire:click="toggleProducto({{ $producto->id }})" 
                                            class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-on-surface-variant hover:text-primary hover:bg-surface-container cursor-pointer transition"
                                            title="{{ $producto->activo ? 'Desactivar de la carta' : 'Reactivar en la carta' }}"
                                        >
                                            <span class="material-symbols-outlined text-[16px] {{ $producto->activo ? 'text-on-surface-variant' : 'text-error' }}">
                                                {{ $producto->activo ? 'visibility_off' : 'visibility' }}
                                            </span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-outline-variant/30 p-4 text-center">
                                <p class="text-[11px] text-on-surface-variant italic">Sin productos en esta categoría.</p>
                                @if(auth()->user()?->role?->slug === 'admin')
                                    <button
                                        wire:click="abrirNuevoProducto({{ $categoria->id }})"
                                        class="mt-2 text-xs font-bold text-primary hover:underline inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">add</span>
                                        <span>Crear primer plato aquí</span>
                                    </button>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>

                @if(auth()->user()?->role?->slug === 'admin')
                    <div class="mt-5 pt-3 border-t border-outline-variant/15 flex items-center justify-between">
                        <button
                            wire:click="abrirEditarCategoria({{ $categoria->id }})"
                            class="inline-flex items-center gap-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-1.5 text-[11px] font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[14px]">edit</span>
                            <span>Editar Categoría</span>
                        </button>
                        @if($categoria->activo)
                            <button
                                wire:click="toggleCategoria({{ $categoria->id }})"
                                class="inline-flex items-center gap-1 rounded-xl px-2.5 py-1.5 text-[11px] font-bold text-error bg-error/10 hover:bg-error/20 active:scale-95 transition-all cursor-pointer"
                            >
                                <span class="material-symbols-outlined text-[14px]">toggle_off</span>
                                <span>Desactivar</span>
                            </button>
                        @else
                            <button
                                wire:click="toggleCategoria({{ $categoria->id }})"
                                class="inline-flex items-center gap-1 rounded-xl px-2.5 py-1.5 text-[11px] font-bold text-secondary bg-secondary/10 hover:bg-secondary/20 active:scale-95 transition-all cursor-pointer"
                            >
                                <span class="material-symbols-outlined text-[14px]">toggle_on</span>
                                <span>Activar</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach

        @if(auth()->user()?->role?->slug === 'admin')
            <!-- Quick Action Card: Nuevo Producto -->
            <button
                wire:click="abrirNuevoProducto"
                id="btnCardNuevoProducto"
                class="rounded-3xl border-2 border-dashed border-primary/40 bg-primary/5 p-6 flex flex-col items-center justify-center gap-2 text-primary hover:border-primary hover:bg-primary/10 transition-all min-h-[220px] cursor-pointer group shadow-xs"
            >
                <span class="material-symbols-outlined text-[44px] group-hover:scale-110 transition-transform">add_circle</span>
                <span class="text-sm font-extrabold">+ Nuevo Producto / Plato</span>
                <span class="text-[11px] text-on-surface-variant font-medium text-center">Crear sushi, roll, bebida, postre o servicio para los comensales</span>
            </button>

            <!-- Quick Action Card: Nueva Categoría -->
            <button
                wire:click="abrirNuevaCategoria"
                class="rounded-3xl border-2 border-dashed border-outline-variant/40 bg-surface-container-lowest p-6 flex flex-col items-center justify-center gap-2 text-on-surface-variant hover:border-primary hover:text-primary transition-all min-h-[220px] cursor-pointer group shadow-xs"
            >
                <span class="material-symbols-outlined text-[36px] group-hover:scale-110 transition-transform">add_box</span>
                <span class="text-xs font-bold">+ Nueva Categoría</span>
                <span class="text-[10px] text-on-surface-variant text-center">Crear una nueva sección en el menú</span>
            </button>
        @endif
    </div>

    <!-- Modal Categoría -->
    @if($mostrarModalCategoria)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4 animate-fade-in">
            <div 
                x-data="{
                    mostrarPicker: false,
                    tabActiva: 'sushi',
                    grupos: {
                        'sushi': {
                            nombre: 'Sushi & Rolls',
                            icono: '🍣',
                            emojis: ['🍣', '🍱', '🍙', '🥢', '🍘', '🍥', '🐟', '🦐', '🦀', '🐙', '🥑', '🥒']
                        },
                        'calientes': {
                            nombre: 'Wok & Ramen',
                            icono: '🍜',
                            emojis: ['🍜', '🍲', '🥟', '🍤', '🍛', '🥘', '🍢', '🥩', '🍗', '🍚', '🫕', '🥠']
                        },
                        'bebidas': {
                            nombre: 'Bebidas & Bar',
                            icono: '🍹',
                            emojis: ['🍹', '🍺', '🍵', '🍶', '🧋', '🥤', '🍷', '🍸', '☕', '🧃', '🧊', '🧉']
                        },
                        'postres': {
                            nombre: 'Postres & Dulces',
                            icono: '🍨',
                            emojis: ['🍨', '🍦', '🍰', '🍡', '🍮', '🧁', '🍩', '🍫', '🍓', '🥞', '🍧', '🍪']
                        },
                        'entradas': {
                            nombre: 'Entradas & Bowls',
                            icono: '🥗',
                            emojis: ['🥗', '🥑', '🍢', '🫛', '🌽', '🥜', '🍄', '🥦', '🥕', '🥔', '🧅', '🫒']
                        },
                        'especiales': {
                            nombre: 'Combos & Promos',
                            icono: '⭐',
                            emojis: ['⭐', '🔥', '✨', '👑', '🏷️', '🎯', '🚀', '💎', '🏆', '🎉', '⚡', '❤️']
                        }
                    },
                    seleccionar(emoji) {
                        $wire.set('categoriaForm.icono', emoji);
                    }
                }"
                class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20"
            >
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[22px] text-primary">{{ $categoriaEnEdicion ? 'edit' : 'category' }}</span>
                        <h3 class="text-base font-extrabold text-on-surface">
                            {{ $categoriaEnEdicion ? 'Editar Categoría' : 'Nueva Categoría' }}
                        </h3>
                    </div>
                    <button wire:click="$set('mostrarModalCategoria', false)" class="text-on-surface-variant hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre de la Categoría:</label>
                        <input type="text" wire:model="categoriaForm.nombre" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: Rollos Especiales, Bebidas, Postres..." />
                        @error('categoriaForm.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Ícono (Emoji):</label>
                            <div class="flex items-center gap-1.5">
                                <!-- Botón activador del selector con preview -->
                                <button
                                    type="button"
                                    @click="mostrarPicker = !mostrarPicker"
                                    class="flex-1 h-11 rounded-xl border border-outline-variant/30 bg-surface-container-low px-2 flex items-center justify-between hover:border-primary hover:bg-primary/5 transition-all shadow-xs cursor-pointer group"
                                    title="Abrir selector de íconos"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-2xl group-hover:scale-110 transition-transform" x-text="$wire.categoriaForm.icono || '🍣'"></span>
                                        <span class="text-[11px] font-bold text-on-surface-variant group-hover:text-primary">Elegir</span>
                                    </div>
                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant group-hover:text-primary transition-transform" :class="mostrarPicker ? 'rotate-180' : ''">expand_more</span>
                                </button>
                                
                                <!-- Input manual para escritura o pegado de emoji personalizado -->
                                <div class="w-12">
                                    <input
                                        type="text"
                                        wire:model.live="categoriaForm.icono"
                                        maxlength="5"
                                        class="h-11 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-1 text-center text-lg text-on-surface focus:border-primary focus:ring-0"
                                        placeholder="🍣"
                                        title="O escribe/pega tu emoji personalizado aquí"
                                    />
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Orden de Visualización:</label>
                            <input type="number" wire:model="categoriaForm.orden" class="h-11 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" min="0" />
                        </div>
                    </div>

                    <!-- Frecuentes: acceso de 1 solo toque -->
                    <div class="flex items-center justify-between gap-1 p-1.5 rounded-xl bg-surface-container-low border border-outline-variant/20">
                        <span class="text-[10px] font-bold text-on-surface-variant px-1">Frecuentes:</span>
                        <div class="flex items-center gap-1 flex-wrap">
                            <template x-for="rapido in ['🍣', '🍱', '🍜', '🍹', '🍨', '🥟', '🥗', '⭐']" :key="rapido">
                                <button
                                    type="button"
                                    @click="seleccionar(rapido)"
                                    :class="$wire.categoriaForm.icono === rapido ? 'bg-primary/20 ring-2 ring-primary scale-110' : 'hover:bg-surface-container-high hover:scale-110'"
                                    class="h-7 w-7 rounded-lg flex items-center justify-center text-base transition-all cursor-pointer"
                                    :title="'Seleccionar ' + rapido"
                                >
                                    <span x-text="rapido"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Panel Expandible de Selección Categorizada -->
                    <div
                        x-show="mostrarPicker"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2 scale-98"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 -translate-y-2 scale-98"
                        class="rounded-2xl border border-outline-variant/30 bg-surface-container-low p-3 shadow-inner space-y-2.5 animate-fade-in"
                    >
                        <!-- Pestañas temáticas -->
                        <div class="flex items-center gap-1 overflow-x-auto pb-1 scrollbar-none">
                            <template x-for="(grupo, key) in grupos" :key="key">
                                <button
                                    type="button"
                                    @click="tabActiva = key"
                                    :class="tabActiva === key ? 'bg-primary text-on-primary font-bold shadow-xs' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface'"
                                    class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] whitespace-nowrap transition-all cursor-pointer"
                                >
                                    <span x-text="grupo.icono"></span>
                                    <span x-text="grupo.nombre"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Grilla de íconos -->
                        <div class="grid grid-cols-6 gap-1.5 p-1 bg-surface-container-lowest rounded-xl border border-outline-variant/15 max-h-36 overflow-y-auto">
                            <template x-for="emoji in (grupos[tabActiva]?.emojis || [])" :key="emoji">
                                <button
                                    type="button"
                                    @click="seleccionar(emoji)"
                                    :class="$wire.categoriaForm.icono === emoji ? 'bg-primary/20 ring-2 ring-primary scale-110' : 'hover:bg-primary/10 hover:scale-115'"
                                    class="h-10 rounded-lg flex items-center justify-center text-xl transition-all cursor-pointer"
                                    :title="'Elegir ' + emoji"
                                >
                                    <span x-text="emoji"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Pie del selector -->
                        <div class="flex items-center justify-between pt-1 border-t border-outline-variant/15 text-[11px] text-on-surface-variant">
                            <span class="flex items-center gap-1">
                                Seleccionado: <strong class="text-base text-on-surface" x-text="$wire.categoriaForm.icono || 'Ninguno'"></strong>
                            </span>
                            <button
                                type="button"
                                @click="mostrarPicker = false"
                                class="text-xs font-bold text-primary hover:underline cursor-pointer"
                            >
                                Listo ✓
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalCategoria', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface cursor-pointer">
                        Cancelar
                    </button>
                    <button wire:click="guardarCategoria" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container cursor-pointer">
                        ✓ Guardar Categoría
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Producto / Servicio -->
    @if($mostrarModalProducto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4 animate-fade-in">
            <div class="w-full max-w-lg rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[22px] text-primary">ramen_dining</span>
                        <h3 class="text-base font-extrabold text-on-surface">
                            {{ $productoEnEdicion ? 'Editar Producto / Servicio' : 'Nuevo Producto / Servicio' }}
                        </h3>
                    </div>
                    <button wire:click="$set('mostrarModalProducto', false)" class="text-on-surface-variant hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Categoría:</label>
                            <select wire:model="productoForm.categoria_id" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                <option value="">Seleccionar Categoría...</option>
                                @foreach($categorias->where('activo', true) as $categoria)
                                    <option value="{{ $categoria->id }}">{{ $categoria->icono }} {{ $categoria->nombre }}</option>
                                @endforeach
                            </select>
                            @error('productoForm.categoria_id') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Área de Preparación / Cocina:</label>
                            <select wire:model="productoForm.area_cocina" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                <option value="sushi">Barra Sushi</option>
                                <option value="caliente">Cocina Caliente</option>
                                <option value="barra">Barra Bebidas / Postres</option>
                            </select>
                            @error('productoForm.area_cocina') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre del Producto / Plato:</label>
                        <input 
                            type="text" 
                            wire:model="productoForm.nombre" 
                            id="inputNombreProducto"
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" 
                            placeholder="Ej: Dragon Roll Especial, Cerveza Sapporo, Nigiri Salmón..." 
                        />
                        @error('productoForm.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Descripción / Ingredientes (Opcional):</label>
                        <textarea 
                            wire:model="productoForm.descripcion" 
                            id="inputDescripcionProducto"
                            rows="2" 
                            class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" 
                            placeholder="Ingredientes clave, preparación o notas para los comensales..."
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Precio Venta al Público (COP):</label>
                            <div class="relative mt-1">
                                <span class="absolute left-3 top-2.5 text-xs font-bold text-on-surface-variant">$</span>
                                <input 
                                    type="number" 
                                    step="100" 
                                    min="0" 
                                    wire:model="productoForm.precio" 
                                    id="inputPrecioProducto"
                                    class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low py-2.5 pl-7 pr-3 text-xs font-mono font-bold text-on-surface focus:border-primary focus:ring-0" 
                                    placeholder="42000" 
                                />
                            </div>
                            @error('productoForm.precio') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Costo Insumos / Escandallo (COP):</label>
                            <div class="relative mt-1">
                                <span class="absolute left-3 top-2.5 text-xs font-bold text-on-surface-variant">$</span>
                                <input 
                                    type="number" 
                                    step="100" 
                                    min="0" 
                                    wire:model="productoForm.costo" 
                                    id="inputCostoProducto"
                                    class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low py-2.5 pl-7 pr-3 text-xs font-mono font-bold text-on-surface focus:border-primary focus:ring-0" 
                                    placeholder="15000" 
                                />
                            </div>
                            @error('productoForm.costo') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button wire:click="$set('mostrarModalProducto', false)" type="button" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface cursor-pointer">
                        Cancelar
                    </button>
                    <button wire:click="guardarProducto" type="button" id="btnGuardarProducto" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container cursor-pointer transition-all">
                        ✓ Guardar Producto
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
