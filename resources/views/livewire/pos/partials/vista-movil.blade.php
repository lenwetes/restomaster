        <!-- ========================================================================= -->
        <!-- EXPERIENCIA MÓVIL DEDICADA: AURA GASTRO POCKET POS (UX/UI MÓVIL)           -->
        <!-- ========================================================================= -->
        <div 
            x-data="{
                mostrarSelectorCategorias: false,
                scrollCatLeft() {
                    $refs.catMobileTrack.scrollBy({ left: -220, behavior: 'smooth' });
                },
                scrollCatRight() {
                    $refs.catMobileTrack.scrollBy({ left: 220, behavior: 'smooth' });
                },
                scrollMenuTop() {
                    const el = document.getElementById('posFoodFeed');
                    if (el) el.scrollTo({ top: 0, behavior: 'smooth' });
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                scrollMenuBottom() {
                    const el = document.getElementById('posFoodFeed');
                    if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                }
            }"
            class="max-w-md mx-auto w-full sm:my-2 relative"
        >
            <style>
                .pos-scroll-vertical {
                    scrollbar-width: thin !important;
                    scrollbar-color: #b91c1c rgba(0, 0, 0, 0.08) !important;
                }
                .pos-scroll-vertical::-webkit-scrollbar {
                    display: block !important;
                    width: 7px !important;
                }
                .pos-scroll-vertical::-webkit-scrollbar-track {
                    background: rgba(0, 0, 0, 0.05) !important;
                    border-radius: 9999px !important;
                }
                .pos-scroll-vertical::-webkit-scrollbar-thumb {
                    background: #b91c1c !important;
                    border-radius: 9999px !important;
                }
                .pos-scroll-vertical::-webkit-scrollbar-thumb:hover {
                    background: #991b1b !important;
                }
            </style>

            <!-- Marco Táctil Nativo Móvil (Edge-to-edge en teléfonos, carcasa premium en PC) -->
            <div class="relative bg-surface-container-lowest sm:rounded-[36px] sm:border sm:border-surface-container-high/80 sm:shadow-2xl overflow-hidden transition-all flex flex-col">
                
                <!-- Barra Superior de Estado / Dispositivo Móvil -->
                <div class="bg-surface-container-low px-4 py-2 border-b border-surface-container-high/60 flex items-center justify-between text-[11px] font-bold text-on-surface-variant">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary/10 text-primary text-[10px]">🍽️</span>
                        <span class="font-black text-on-surface">RestoMaster Pocket</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @if($turnoActivo)
                            <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                            <span class="text-[10px] text-secondary font-mono font-bold">Turno #{{ $turnoActivo->id }}</span>
                        @else
                            <span class="w-2 h-2 rounded-full bg-error"></span>
                            <span class="text-[10px] text-error font-mono font-bold">Caja Cerrada</span>
                        @endif
                    </div>
                </div>

                <!-- Cabecera de la Comandera: Perfil + Selector de Vistas + Segmented Mode + Mesa -->
                <div class="p-3.5 space-y-3 bg-surface-container-lowest border-b border-surface-container-high/50">
                    <!-- Fila 1: Perfil Mesero + Selector de Vistas (PC, Tablet, Móvil) -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 rounded-xl bg-primary text-on-primary flex items-center justify-center font-bold text-xs shadow-xs shrink-0">
                                <span class="material-symbols-outlined text-[18px]">badge</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-on-surface truncate leading-tight">{{ Auth::user()->name }}</p>
                                <p class="text-[9px] text-primary font-bold uppercase tracking-wider">Comandera de Bolsillo</p>
                            </div>
                        </div>

                        {{-- rol intencional, no permiso: selector de vistas exclusivo de la comandera del mesero --}}
                        @if(Auth::user()?->role?->slug === 'mesero')
                            <!-- Selector de Vistas Táctiles en Móvil -->
                            <div class="flex items-center gap-1 bg-surface-container-low p-1 rounded-xl border border-surface-container-high shrink-0" role="group" aria-label="Selector de vistas">
                                <button type="button" wire:click="cambiarVista('pc')" id="btnVistaPc" class="px-2 py-1 rounded-lg text-[10px] font-black text-on-surface-variant hover:text-on-surface cursor-pointer" title="Vista PC">
                                    💻 PC
                                </button>
                                <button type="button" wire:click="cambiarVista('tablet')" id="btnVistaTablet" class="px-2 py-1 rounded-lg text-[10px] font-black text-on-surface-variant hover:text-on-surface cursor-pointer" title="Vista Tablet">
                                    📟 Tab
                                </button>
                                <button type="button" wire:click="cambiarVista('movil')" id="btnVistaMovil" class="px-2.5 py-1 rounded-lg text-[10px] font-black bg-primary text-on-primary shadow-xs cursor-pointer" title="Vista Móvil">
                                    📱 Móvil
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Fila 2: Segmented Control Modo (En Mesa vs Para Llevar) -->
                    <div class="grid grid-cols-12 gap-2">
                        <div class="col-span-12 flex rounded-xl bg-surface-container-low p-1 border border-surface-container-high">
                            <button 
                                type="button" 
                                wire:click="$set('tipo', 'mesa')" 
                                class="flex-1 py-1.5 rounded-lg text-center text-xs font-black transition-all cursor-pointer flex items-center justify-center gap-1.5 {{ $tipo === 'mesa' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">table_restaurant</span>
                                <span>En Mesa</span>
                            </button>
                            <button 
                                type="button" 
                                wire:click="$set('tipo', 'mostrador')" 
                                class="flex-1 py-1.5 rounded-lg text-center text-xs font-black transition-all cursor-pointer flex items-center justify-center gap-1.5 {{ $tipo === 'mostrador' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">takeout_dining</span>
                                <span>Para Llevar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Fila 3: Selector Táctil Ergonómico de Mesa o Cliente -->
                    @if($tipo === 'mesa')
                        <div class="rounded-2xl border p-2.5 flex items-center gap-2.5 transition-all {{ !$mesaId ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-surface-container-high bg-surface-container-low' }}">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $mesaId ? 'bg-secondary text-on-secondary shadow-xs' : 'bg-primary text-on-primary shadow-xs' }}">
                                <span class="material-symbols-outlined text-[20px]">table_restaurant</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-[10px] font-black uppercase tracking-wider {{ $this->cobroEnviadoACaja() ? 'text-amber-400 font-black' : (!$mesaId ? 'text-primary' : 'text-on-surface-variant') }}">
                                    {{ $this->cobroEnviadoACaja() ? '⏳ Cobro Enviado a Caja' : ($mesaId ? 'Mesa Activa' : 'Paso 1: Asignar Mesa') }}
                                </span>
                                <select 
                                    wire:model.live="mesaId" 
                                    id="mesaSelectMovil"
                                    class="w-full bg-transparent border-0 p-0 text-xs font-black text-on-surface focus:ring-0 cursor-pointer"
                                >
                                    <option value="">Seleccionar mesa del salón...</option>
                                    @foreach($mesas as $m)
                                        {{-- rol intencional, no permiso: guard de mesa ajena (identidad de dominio) --}}
                                        @php $mesaAjenaMovil = $m->mesero_id && (int) $m->mesero_id !== (int) Auth::id() && Auth::user()?->role?->slug === 'mesero'; @endphp
                                        <option value="{{ $m->id }}" @disabled($mesaAjenaMovil)>
                                            {{ $m->nombre_sala }} (Zona {{ $m->zona }} - {{ !empty($m->cobro_pendiente) ? '⏳ Cobro en Caja' : ucfirst($m->estado) }}){{ $m->mesero_nombre ? ' · '.$m->mesero_nombre : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if(!$mesaId)
                                <span class="text-[10px] font-bold text-primary animate-pulse shrink-0">← Elegir</span>
                            @else
                                <span class="material-symbols-outlined text-[18px] text-secondary shrink-0">check_circle</span>
                            @endif
                        </div>
                    @else
                        <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-2.5 flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-primary text-on-primary flex items-center justify-center shrink-0 shadow-xs">
                                <span class="material-symbols-outlined text-[20px]">person</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant">Cliente en Mostrador</span>
                                <input 
                                    type="text" 
                                    wire:model.live="nombreCliente" 
                                    placeholder="Nombre del comensal..." 
                                    class="w-full bg-transparent border-0 p-0 text-xs font-black text-on-surface placeholder:text-on-surface-variant/60 focus:ring-0"
                                />
                            </div>
                        </div>
                    @endif

                    @if($pedidoQrPendiente)
                        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-2.5 flex items-center justify-between gap-2 shadow-xs animate-pulse">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-amber-700 text-[20px] shrink-0">notifications_active</span>
                                <div class="min-w-0">
                                    <span class="block text-[10px] font-black uppercase text-amber-900 leading-tight">Pedido QR Recibido</span>
                                    <span class="text-[11px] font-bold text-amber-800 truncate block">{{ $pedidoQrPendiente->nombre_cliente ?? 'Comensal' }} ({{ $pedidoQrPendiente->items->count() }} platos)</span>
                                </div>
                            </div>
                            <button 
                                wire:click="atenderPedidoQrActual"
                                type="button"
                                class="px-2.5 py-1.5 rounded-xl bg-primary text-on-primary text-[11px] font-black shadow-sm hover:bg-primary/90 active:scale-95 transition cursor-pointer shrink-0"
                            >
                                Tomar Mesa
                            </button>
                        </div>
                    @endif

                    <!-- Fila 4 (Al Inicio del Bloque): Acceso y Estado de Comanda para el Mesero -->
                    <div class="rounded-2xl border border-primary/30 bg-primary/5 p-2.5 flex items-center justify-between gap-2 shadow-xs">
                        <button 
                            type="button"
                            wire:click="$toggle('mostrarComandaMovil')"
                            id="btnVerComandaHeader"
                            class="flex items-center gap-2.5 text-left flex-1 cursor-pointer min-w-0"
                            title="Toca para ver u ocultar la comanda actual"
                        >
                            <div class="w-9 h-9 rounded-xl bg-primary text-on-primary flex items-center justify-center font-bold shadow-sm shrink-0">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-black text-on-surface">Comanda Activa</span>
                                    <span class="px-1.5 py-0.2 rounded-full bg-primary text-on-primary text-[10px] font-mono font-bold">{{ count($carrito) }}</span>
                                </div>
                                <span class="text-xs font-mono font-black text-primary truncate block">${{ number_format($this->total, 0, ',', '.') }}</span>
                            </div>
                        </button>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button 
                                type="button" 
                                wire:click="$toggle('mostrarComandaMovil')" 
                                id="btnToggleComandaHeader"
                                class="px-2.5 py-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-[11px] font-bold text-on-surface active:scale-95 cursor-pointer border border-surface-container-high shadow-2xs"
                            >
                                {{ $mostrarComandaMovil ? 'Ocultar' : 'Ver Comanda' }}
                            </button>
                            <button 
                                type="button" 
                                wire:click="enviarACocina" 
                                id="btnCocinaHeader"
                                @disabled(empty($carrito) || ($tipo === 'mesa' && !$mesaId)) 
                                class="px-3 py-1.5 rounded-xl bg-primary hover:bg-primary-container text-[11px] font-black text-on-primary shadow-sm disabled:opacity-40 active:scale-95 cursor-pointer"
                            >
                                Cocina
                            </button>
                        </div>
                    </div>
                </div>

                <!-- STICKY HEADER MÓVIL: Buscador + Barra de Navegación de Categorías -->
                <div class="sticky top-0 z-20 bg-surface-container-lowest/95 backdrop-blur-md border-b border-surface-container-high/60 shadow-2xs">
                    <!-- Buscador Móvil Rápido -->
                    <div class="px-3 pt-2.5 pb-1.5">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-2 text-[18px] text-on-surface-variant">search</span>
                            <input 
                                type="text" 
                                wire:model.live.debounce.200ms="busqueda" 
                                placeholder="Buscar roll, nigiri, bebida..." 
                                class="w-full rounded-xl border border-surface-container-high bg-surface-container-low pl-9 pr-8 py-1.5 text-xs font-bold text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-0"
                            />
                            @if($busqueda)
                                <button type="button" wire:click="$set('busqueda', '')" class="absolute right-2.5 top-2 text-on-surface-variant hover:text-on-surface text-xs font-bold cursor-pointer">✕</button>
                            @endif
                        </div>
                    </div>

                    <!-- Barra de Navegación de Categorías con Botones < y > + Botón Ver Todo Grid -->
                    <div class="flex items-center gap-1 px-2 pb-2">
                        <!-- Flecha Izquierda para Desplazamiento Horizontal -->
                        <button 
                            type="button" 
                            @click="scrollCatLeft()"
                            class="h-8 w-8 shrink-0 flex items-center justify-center rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all active:scale-90 shadow-2xs cursor-pointer"
                            title="Categorías anteriores"
                            aria-label="Categorías anteriores"
                        >
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </button>

                        <!-- Carrusel Horizontal Táctil con Scroll Suave -->
                        <div 
                            x-ref="catMobileTrack" 
                            class="flex-1 flex items-center gap-1.5 overflow-x-auto scroll-smooth py-0.5 scrollbar-none"
                        >
                            <button 
                                type="button" 
                                wire:click="$set('categoriaSeleccionada', null)"
                                class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-black transition-all border cursor-pointer active:scale-95 {{ is_null($categoriaSeleccionada) ? 'bg-primary text-on-primary border-primary shadow-xs' : 'bg-surface-container-low text-on-surface-variant border-surface-container-high hover:bg-surface-container' }}"
                            >
                                <span>🍣</span>
                                <span>Todo</span>
                            </button>
                            @foreach($categorias as $cat)
                                @php $catColor = $cat->color ?? '#e11d48'; @endphp
                                <button 
                                    type="button" 
                                    wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                                    class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-black transition-all border cursor-pointer active:scale-95 {{ $categoriaSeleccionada === $cat->id ? 'text-white shadow-xs' : 'bg-surface-container-low text-on-surface-variant border-surface-container-high hover:bg-surface-container' }}"
                                    @style(['background-color: ' . $catColor => $categoriaSeleccionada === $cat->id, 'border-color: ' . $catColor => $categoriaSeleccionada === $cat->id])
                                >
                                    @if($categoriaSeleccionada !== $cat->id)
                                        <span class="w-2 h-2 rounded-full shrink-0" @style(['background-color: ' . $catColor])></span>
                                    @endif
                                    @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                        <span class="material-symbols-outlined text-[15px]">{{ $cat->icono }}</span>
                                    @else
                                        <span>{{ $cat->icono ?: '🍽️' }}</span>
                                    @endif
                                    <span>{{ $cat->nombre }}</span>
                                    <span class="rounded-full px-1 text-[9px] font-mono {{ $categoriaSeleccionada === $cat->id ? 'bg-white/20 text-white' : 'bg-surface-container text-on-surface-variant' }}">
                                        {{ $cat->productos_count ?? 0 }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <!-- Flecha Derecha para Desplazamiento Horizontal -->
                        <button 
                            type="button" 
                            @click="scrollCatRight()"
                            class="h-8 w-8 shrink-0 flex items-center justify-center rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all active:scale-90 shadow-2xs cursor-pointer"
                            title="Siguientes categorías"
                            aria-label="Siguientes categorías"
                        >
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </button>

                        <!-- Botón Menú / Grid de Todas las Categorías -->
                        <button 
                            type="button" 
                            @click="mostrarSelectorCategorias = true"
                            class="h-8 px-2.5 shrink-0 flex items-center gap-1 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-on-primary text-[10px] font-black transition-all active:scale-95 shadow-2xs cursor-pointer"
                            title="Ver rejilla completa de categorías"
                        >
                            <span class="material-symbols-outlined text-[15px]">grid_view</span>
                            <span>Menú</span>
                        </button>
                    </div>

                    <!-- Indicador Activo de Categoría con Opción de Quitar Filtro -->
                    @if($categoriaSeleccionada)
                        @php $catActiva = $categorias->find($categoriaSeleccionada); @endphp
                        @if($catActiva)
                            <div class="px-3 py-1 bg-primary/5 border-t border-primary/20 flex items-center justify-between text-[11px]">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    @if(preg_match('/^[a-z0-9_]+$/', $catActiva->icono ?? ''))
                                        <span class="material-symbols-outlined text-[14px]">{{ $catActiva->icono }}</span>
                                    @else
                                        <span class="text-xs">{{ $catActiva->icono ?: '🍽️' }}</span>
                                    @endif
                                    <span class="font-bold text-on-surface truncate">
                                        Filtrado: <span class="text-primary font-black">{{ $catActiva->nombre }}</span>
                                    </span>
                                    <span class="px-1.5 py-0.2 rounded-full bg-primary/10 text-primary font-mono text-[9px] font-bold shrink-0">
                                        {{ $productos->count() }} platos
                                    </span>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="$set('categoriaSeleccionada', null)" 
                                    class="text-[10px] font-black text-primary hover:underline flex items-center gap-0.5 cursor-pointer shrink-0 ml-2"
                                >
                                    <span>Ver Todo</span>
                                    <span>✕</span>
                                </button>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Feed de Platos Móvil con Barra de Desplazamiento Vertical Visible -->
                <div 
                    id="posFoodFeed" 
                    class="p-3 space-y-2 bg-surface-container-low/30 overflow-y-auto max-h-[54vh] pr-2 scroll-smooth pos-scroll-vertical"
                >
                    @forelse($productos as $prod)
                        @php $prodColor = $prod->categoria?->color ?? '#e11d48'; @endphp
                        <div class="rounded-2xl border bg-surface-container-lowest p-3 flex items-center justify-between gap-3 shadow-2xs hover:shadow-xs transition-all {{ isset($carrito[$prod->id]) ? 'border-primary/50 bg-primary/5 ring-1 ring-primary/20' : 'border-surface-container-highest' }}"
                             @style(['border-left: 4.5px solid ' . $prodColor])>
                            <!-- Visual & Detalles del Plato -->
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-14 h-14 rounded-xl overflow-hidden shrink-0 flex items-center justify-center shadow-2xs border border-surface-container-high/60 bg-surface-container-high/30">
                                    @if($prod->imagen_url)
                                        <img src="{{ $prod->imagen_url }}" alt="{{ $prod->nombre }}" class="w-full h-full object-cover" loading="lazy">
                                    @elseif(preg_match('/^[a-z0-9_]+$/', $prod->categoria?->icono ?? ''))
                                        <span class="material-symbols-outlined text-[24px]" @style(['color: ' . $prodColor])>{{ $prod->categoria->icono }}</span>
                                    @else
                                        <span class="text-2xl leading-none">{{ $prod->categoria?->icono ?: '🍽️' }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <h4 class="text-xs font-black text-on-surface truncate">{{ $prod->nombre }}</h4>
                                        @php
                                            $mobileAreaLabel = match(strtolower($prod->area_cocina ?? 'caliente')) {
                                                'sushi', 'fria', 'cocina_fria' => 'Cocina Fría',
                                                'caliente', 'calientes', 'cocina' => 'Caliente',
                                                'barra', 'bebidas' => 'Barra',
                                                'postres' => 'Postres',
                                                default => ucfirst($prod->area_cocina),
                                            };
                                        @endphp
                                        <span class="rounded px-1 py-0.2 text-[8px] font-bold uppercase tracking-wider bg-surface-container text-on-surface-variant shrink-0">
                                            {{ $mobileAreaLabel }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-on-surface-variant line-clamp-1 mt-0.5">
                                        {{ $prod->descripcion ?: 'Elaborado fresco en barra.' }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-mono font-black text-primary">
                                            ${{ number_format((float)$prod->precio, 0, ',', '.') }}
                                        </span>
                                        @if(isset($carrito[$prod->id]))
                                            <span class="text-[9px] font-bold text-primary bg-primary/10 px-1.5 py-0.2 rounded">
                                                {{ $carrito[$prod->id]['cantidad'] }} en orden
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Stepper Inline Táctil o Botón Agregar Directo -->
                            <div class="shrink-0">
                                @if(isset($carrito[$prod->id]))
                                    <div class="flex items-center gap-1 bg-surface-container-low rounded-xl p-1 border border-primary/30 shadow-xs">
                                        <button 
                                            type="button" 
                                            wire:click="decrementarCantidad({{ $prod->id }})" 
                                            class="w-7 h-7 rounded-lg bg-surface-container-lowest text-on-surface font-black text-xs flex items-center justify-center shadow-2xs active:scale-90 cursor-pointer"
                                            title="Disminuir"
                                        >
                                            -
                                        </button>
                                        <span class="w-5 text-center font-mono font-black text-xs text-primary">
                                            {{ $carrito[$prod->id]['cantidad'] }}
                                        </span>
                                        <button 
                                            type="button" 
                                            wire:click="incrementarCantidad({{ $prod->id }})" 
                                            class="w-7 h-7 rounded-lg bg-primary text-on-primary font-black text-xs flex items-center justify-center shadow-2xs active:scale-90 cursor-pointer"
                                            title="Aumentar"
                                        >
                                            +
                                        </button>
                                    </div>
                                @else
                                    <button 
                                        type="button" 
                                        wire:click="agregarProducto({{ $prod->id }})" 
                                        class="w-10 h-10 rounded-xl bg-primary text-on-primary flex items-center justify-center font-black text-lg shadow-sm hover:bg-primary-container active:scale-90 transition-all cursor-pointer"
                                        title="Agregar a la comanda"
                                    >
                                        +
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-surface-container-highest p-8 text-center text-on-surface-variant bg-surface-container-lowest">
                            <span class="material-symbols-outlined text-[32px] opacity-40">ramen_dining</span>
                            <p class="text-xs font-semibold mt-1">No hay productos en esta categoría.</p>
                        </div>
                    @endforelse
                </div>
                
                <!-- BARRA DE NAVEGACIÓN VERTICAL MÓVIL (Subir / Bajar / Categorías Rápido) -->
                <div 
                    class="absolute right-2 top-1/2 -translate-y-1/2 z-20 flex flex-col items-center gap-1.5 p-1 rounded-2xl bg-surface-container-lowest/90 backdrop-blur-md border border-surface-container-high shadow-lg"
                    role="navigation"
                    aria-label="Controles verticales del menú"
                >
                    <!-- Botón Subir al Inicio -->
                    <button 
                        type="button" 
                        @click="scrollMenuTop()"
                        class="w-7 h-7 rounded-xl bg-surface-container hover:bg-primary hover:text-on-primary text-on-surface-variant flex items-center justify-center shadow-2xs transition-all active:scale-90 cursor-pointer"
                        title="Subir al inicio del menú"
                        aria-label="Subir al inicio"
                    >
                        <span class="material-symbols-outlined text-[16px]">arrow_upward</span>
                    </button>

                    <!-- Botón Desplegar Rejilla de Categorías -->
                    <button 
                        type="button" 
                        @click="mostrarSelectorCategorias = true"
                        class="w-7 h-7 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-on-primary flex items-center justify-center shadow-2xs transition-all active:scale-90 cursor-pointer"
                        title="Menú de categorías completo"
                        aria-label="Ver todas las categorías"
                    >
                        <span class="material-symbols-outlined text-[16px]">category</span>
                    </button>

                    <!-- Botón Bajar al Final -->
                    <button 
                        type="button" 
                        @click="scrollMenuBottom()"
                        class="w-7 h-7 rounded-xl bg-surface-container hover:bg-primary hover:text-on-primary text-on-surface-variant flex items-center justify-center shadow-2xs transition-all active:scale-90 cursor-pointer"
                        title="Bajar al final del menú"
                        aria-label="Bajar al final"
                    >
                        <span class="material-symbols-outlined text-[16px]">arrow_downward</span>
                    </button>
                </div>

                <!-- MODAL SELECTOR DE CATEGORÍAS (Centrado en Pantalla con Backdrop Viewport) -->
                <div 
                    x-show="mostrarSelectorCategorias" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 backdrop-blur-xs p-4 lg:pl-64"
                    style="display: none;"
                >
                    <div 
                        @click.outside="mostrarSelectorCategorias = false"
                        class="w-full max-w-sm rounded-3xl bg-surface-container-lowest p-5 shadow-2xl border border-surface-container-highest max-h-[80vh] flex flex-col justify-between overflow-hidden animate-in zoom-in-95 duration-150"
                    >
                        <div>
                            <div class="flex items-center justify-between border-b border-surface-container-high pb-2.5 mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[20px]">restaurant_menu</span>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-black text-on-surface">Seleccionar Categoría</h3>
                                        <p class="text-[10px] text-on-surface-variant">Salta directamente a la sección que buscas</p>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    @click="mostrarSelectorCategorias = false"
                                    class="w-8 h-8 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition cursor-pointer"
                                    title="Cerrar modal"
                                >
                                    <span class="material-symbols-outlined text-[20px]">close</span>
                                </button>
                            </div>

                            <!-- Botón Ver Todo el Menú -->
                            <button 
                                type="button"
                                wire:click="$set('categoriaSeleccionada', null)"
                                @click="mostrarSelectorCategorias = false; scrollMenuTop();"
                                class="w-full mb-3 p-2.5 rounded-2xl border transition-all flex items-center justify-between cursor-pointer active:scale-98 {{ is_null($categoriaSeleccionada) ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface hover:bg-surface-container' }}"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xl">🍱</span>
                                    <div class="text-left">
                                        <p class="text-xs font-black">Todo el Menú</p>
                                        <p class="text-[10px] {{ is_null($categoriaSeleccionada) ? 'text-on-primary/80' : 'text-on-surface-variant' }}">Ver la carta completa del restaurante</p>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </button>

                            <!-- Rejilla de Categorías -->
                            <div class="grid grid-cols-2 gap-2 max-h-[46vh] overflow-y-auto pr-1 scrollbar-none">
                                @foreach($categorias as $cat)
                                    <button 
                                        type="button"
                                        wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                                        @click="mostrarSelectorCategorias = false; scrollMenuTop();"
                                        class="p-2.5 rounded-2xl border text-left transition-all relative overflow-hidden group cursor-pointer active:scale-95 {{ $categoriaSeleccionada === $cat->id ? 'border-primary bg-primary text-on-primary shadow-sm' : 'border-surface-container-high bg-surface-container-low text-on-surface hover:bg-surface-container hover:border-primary/40' }}"
                                    >
                                        <div class="flex items-start justify-between">
                                            @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                                <span class="material-symbols-outlined text-2xl mb-1 block">{{ $cat->icono }}</span>
                                            @else
                                                <span class="text-2xl mb-1 block leading-none">{{ $cat->icono ?: '🍽️' }}</span>
                                            @endif
                                            <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full font-bold {{ $categoriaSeleccionada === $cat->id ? 'bg-on-primary/20 text-on-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                                                {{ $cat->productos_count ?? 0 }}
                                            </span>
                                        </div>
                                        <p class="text-xs font-black truncate leading-tight">{{ $cat->nombre }}</p>
                                        <p class="text-[9px] truncate mt-0.5 {{ $categoriaSeleccionada === $cat->id ? 'text-on-primary/80' : 'text-on-surface-variant' }}">
                                            {{ $cat->descripcion ?: 'Especialidades frescas' }}
                                        </p>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-3 pt-2.5 border-t border-surface-container-high text-center">
                            <button 
                                type="button" 
                                @click="mostrarSelectorCategorias = false" 
                                class="w-full py-2.5 rounded-xl bg-surface-container text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer active:scale-98"
                            >
                                Cerrar Selector
                            </button>
                        </div>
                    </div>
                </div>

                <!-- MODAL DE COMANDA EN MANO MÓVIL (Centrado en Pantalla con Backdrop Viewport) -->
                @if($mostrarComandaMovil)
                    <div 
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 backdrop-blur-xs p-4 lg:pl-64"
                    >
                        <div 
                            @click.outside="$wire.set('mostrarComandaMovil', false)"
                            class="w-full max-w-sm rounded-3xl bg-surface-container-lowest p-5 shadow-2xl border border-surface-container-highest max-h-[82vh] flex flex-col justify-between overflow-hidden animate-in zoom-in-95 duration-150"
                        >
                            <div class="flex-1 overflow-hidden flex flex-col min-h-0">
                                <div class="flex items-center justify-between border-b border-surface-container-high pb-3 shrink-0">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                            <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-1.5">
                                                <span>Comanda en Mano (Móvil)</span>
                                                @if($this->cobroEnviadoACaja())
                                                    <span class="text-[10px] font-black text-amber-300 bg-amber-500/20 border border-amber-500/40 px-1.5 py-0.2 rounded-full flex items-center gap-0.5 animate-pulse">
                                                        <span class="material-symbols-outlined text-[12px] animate-spin">hourglass_top</span>
                                                        En Caja
                                                    </span>
                                                @endif
                                            </h3>
                                            @php
                                                $pedidoActivoMovil = $this->obtenerPedidoActivoMesa();
                                                $meseroNombreMovil = $pedidoActivoMovil?->mesero?->name 
                                                    ?? ($mesaId ? $mesas->find($mesaId)?->mesero?->name : null)
                                                    ?? (Auth::user()?->isMesero() ? Auth::user()->name : null);
                                                $descMesa = $tipo === 'mesa' ? ($mesaId ? $mesas->find($mesaId)?->nombre_sala : 'Mesa sin asignar') : 'Para Llevar';
                                            @endphp
                                            <p class="text-[11px] text-on-surface-variant">
                                                {{ $descMesa }}{{ $meseroNombreMovil ? ' · Mesero: ' . $meseroNombreMovil : '' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if(count($carrito) > 0)
                                            <button wire:click="limpiarCarrito" class="text-[10px] font-bold text-error hover:underline cursor-pointer">
                                                Vaciar
                                            </button>
                                        @endif
                                        <button wire:click="$set('mostrarComandaMovil', false)" class="w-8 h-8 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition cursor-pointer">
                                            <span class="material-symbols-outlined text-[20px]">close</span>
                                        </button>
                                    </div>
                                </div>

                                @if($pedidoActivoMovil && $pedidoActivoMovil->estado === 'pendiente_cobro')
                                    <div class="mt-2 rounded-2xl border border-amber-500/40 bg-amber-500/15 p-2.5 flex items-center justify-between text-xs animate-fade-in shrink-0">
                                        <div class="flex items-center gap-2 text-amber-300 font-bold text-[11px] min-w-0">
                                            <span class="material-symbols-outlined text-[16px] text-amber-400 animate-spin shrink-0">hourglass_top</span>
                                            <span class="truncate">Cobro solicitado a Caja · En espera de pago</span>
                                        </div>
                                        <span class="text-[10px] font-black text-amber-300 font-mono bg-black/40 px-2 py-0.5 rounded border border-amber-500/30 shrink-0">
                                            ${{ number_format((float) $pedidoActivoMovil->total, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @endif

                                <!-- Lista de items móvil -->
                                <div class="mt-3 space-y-2.5 flex-1 min-h-0 overflow-y-auto pr-1 scrollbar-none max-h-[42vh]">
                                    @if($modoNuevaAdicion)
                                        <div class="rounded-2xl border border-primary/30 bg-primary/10 p-2 flex items-center justify-between text-xs animate-fade-in">
                                            <div class="flex items-center gap-1 text-primary font-black text-[11px]">
                                                <span class="material-symbols-outlined text-[15px]">add_circle</span>
                                                <span>Nuevo Pedido / Adición</span>
                                            </div>
                                            <button 
                                                wire:click="cancelarModoAdicion" 
                                                type="button"
                                                class="text-[10px] font-bold text-primary hover:underline cursor-pointer"
                                            >
                                                Ver cuenta total
                                            </button>
                                        </div>
                                    @endif
                                    @forelse($carrito as $pId => $item)
                                        <div class="rounded-2xl border border-surface-container-high bg-surface-container-low p-2.5">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-on-surface truncate max-w-[200px]">{{ $item['nombre'] }}</span>
                                                <span class="text-xs font-mono font-black text-primary">${{ number_format($item['precio'] * $item['cantidad'], 0, ',', '.') }}</span>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between gap-1.5">
                                                <div class="flex items-center gap-1">
                                                    <button wire:click="decrementarCantidad({{ $pId }})" class="h-8 w-8 rounded-lg bg-surface-container font-bold text-on-surface shadow-sm active:scale-95 cursor-pointer">-</button>
                                                    <span class="w-6 text-center text-xs font-mono font-bold">{{ $item['cantidad'] }}</span>
                                                    <button wire:click="incrementarCantidad({{ $pId }})" class="h-8 w-8 rounded-lg bg-surface-container font-bold text-on-surface shadow-sm active:scale-95 cursor-pointer">+</button>
                                                </div>
                                                <input type="text" wire:model.lazy="carrito.{{ $pId }}.notas" placeholder="Nota al chef..." class="h-8 flex-1 rounded-lg border border-surface-container-high bg-surface-container-lowest px-2 text-[10px] text-on-surface" />
                                                <button wire:click="eliminarItem({{ $pId }})" class="h-8 w-8 rounded-lg text-on-surface-variant hover:text-error cursor-pointer">✕</button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="py-10 text-center text-xs text-on-surface-variant">
                                            <span class="material-symbols-outlined text-[32px] text-outline-variant block mb-1">local_dining</span>
                                            La comanda está vacía.<br/>Selecciona platos para agregarlos.
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Resumen y acciones móvil -->
                            <div class="mt-3 pt-3 border-t border-surface-container-high space-y-2.5 shrink-0">
                                <div class="flex justify-between text-sm font-black text-on-surface">
                                    <span>Total Neto:</span>
                                    <span class="text-primary font-mono text-lg">${{ number_format($this->total, 0, ',', '.') }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    @if($this->comandaDespachadaPorCocina() && $this->cantidadNuevosItemsParaCocina() === 0)
                                        <button 
                                            wire:click="iniciarNuevoPedido"
                                            type="button"
                                            class="flex h-11 items-center justify-center gap-1.5 rounded-xl border border-primary/40 bg-surface-container text-xs font-extrabold text-primary shadow-sm hover:bg-surface-container-high transition-all active:scale-95 cursor-pointer"
                                            title="Comanda anterior despachada. Iniciar nuevo pedido para esta mesa."
                                        >
                                            <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                                            <span>+ Nuevo Pedido</span>
                                        </button>
                                    @else
                                        <button 
                                            wire:click="enviarACocina"
                                            type="button"
                                            @disabled(empty($carrito) || ($tipo === 'mesa' && !$mesaId) || ($tipo === 'mesa' && $this->comandaYaEnviadaACocina()))
                                            class="flex h-11 items-center justify-center gap-1.5 rounded-xl border text-xs font-black shadow-sm disabled:opacity-40 cursor-pointer active:scale-95 {{ $this->comandaYaEnviadaACocina() ? 'bg-surface-container/50 border-surface-container-high text-on-surface-variant cursor-not-allowed' : 'bg-surface-container border-primary/40 text-primary hover:bg-surface-container-high' }}"
                                            title="{{ $this->comandaYaEnviadaACocina() ? 'Comanda ya enviada a cocina.' : 'Enviar comanda a cocina' }}"
                                        >
                                            <span class="material-symbols-outlined text-[18px]">{{ $this->comandaYaEnviadaACocina() ? 'check_circle' : 'skillet' }}</span>
                                            <span>{{ $this->comandaYaEnviadaACocina() ? '✓ En Cocina' : ($this->cantidadNuevosItemsParaCocina() > 0 && $this->obtenerPedidoActivoMesa() ? 'Enviar +'.$this->cantidadNuevosItemsParaCocina().' Cocina' : 'Enviar Cocina') }}</span>
                                        </button>
                                    @endif
                                    @if (Auth::user()?->isMesero())
                                        @if ($this->cobroEnviadoACaja())
                                            <button
                                                type="button"
                                                disabled
                                                class="flex h-11 items-center justify-center gap-1.5 rounded-xl text-xs font-black shadow-md cursor-not-allowed bg-amber-500/20 border border-amber-500/40 text-amber-300"
                                                title="La solicitud de cobro ya fue enviada a caja y está pendiente de pago."
                                            >
                                                <span class="material-symbols-outlined text-[18px] animate-spin">hourglass_top</span>
                                                <span>⏳ Cobro Solicitado a Caja</span>
                                            </button>
                                        @else
                                            <button
                                                wire:click="solicitarCobroCaja"
                                                type="button"
                                                @disabled((empty($carrito) && !$this->obtenerPedidoActivoMesa()) || ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) || $this->comandaActivaBloqueaCobro() || $this->esMesaDeOtroMesero())
                                                class="flex h-11 items-center justify-center gap-1.5 rounded-xl text-xs font-black shadow-md disabled:opacity-40 cursor-pointer active:scale-95 bg-gradient-to-r from-[#2eb8b4] to-[#1e8e8a] text-white hover:brightness-110"
                                                title="Solicitar el cobro de la mesa a Caja"
                                            >
                                                <span class="material-symbols-outlined text-[18px]">forward_to_inbox</span>
                                                <span>📲 Solicitar cobro a Caja</span>
                                            </button>
                                        @endif
                                    @else
                                    <button
                                        wire:click="abrirModalCobro"
                                        type="button"
                                        @disabled((empty($carrito) && !$this->obtenerPedidoActivoMesa()) || ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) || $this->comandaActivaBloqueaCobro() || $this->esMesaDeOtroMesero())
                                        class="flex h-11 items-center justify-center gap-1.5 rounded-xl text-xs font-black shadow-md disabled:opacity-40 cursor-pointer active:scale-95 {{ ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) ? 'bg-amber-500/20 text-amber-500 border border-amber-500/40 cursor-not-allowed' : ($this->comandaActivaBloqueaCobro() ? 'bg-amber-500/20 text-amber-900 border border-amber-500/40 cursor-not-allowed' : ($this->esMesaDeOtroMesero() ? 'bg-slate-700/50 text-slate-300 border border-slate-600 cursor-not-allowed' : ($this->comandaListaParaCobrar() ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-primary text-on-primary hover:bg-primary-container'))) }}"
                                        title="{{ ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) ? 'Debes enviar la comanda a cocina antes de cobrar.' : ($this->comandaActivaBloqueaCobro() ? 'Comanda en preparación en cocina. Solo se puede cobrar cuando cocina termine.' : ($this->esMesaDeOtroMesero() ? 'Mesa asignada a otro mesero.' : 'Cobrar Pedido')) }}"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">{{ ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) ? 'skillet' : ($this->comandaActivaBloqueaCobro() ? 'hourglass_top' : ($this->esMesaDeOtroMesero() ? 'shield_person' : ($this->comandaListaParaCobrar() ? 'check_circle' : 'payments'))) }}</span>
                                        <span>{{ ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) ? 'Enviar a Cocina Primero' : ($this->comandaActivaBloqueaCobro() ? 'En Prep. Cocina' : ($this->esMesaDeOtroMesero() ? 'Mesa de Otro Mesero' : ($this->comandaListaParaCobrar() ? '✓ Cobrar Listo' : 'Cobrar Pedido'))) }}</span>
                                    </button>
                                    @endif
                                </div>
                                @if($this->cobroEnviadoACaja())
                                    <p class="text-[10px] text-center font-bold text-amber-300 bg-amber-500/15 py-1 px-2 rounded-lg border border-amber-500/30 flex items-center justify-center gap-1 animate-pulse">
                                        <span class="material-symbols-outlined text-xs animate-spin">hourglass_top</span>
                                        Cobro solicitado a Caja · Esperando pago del cajero
                                    </p>
                                @elseif($tipo === 'mesa' && $this->comandaRequiereEnvioCocina())
                                    <p class="text-[10px] text-center font-bold text-amber-500 bg-amber-500/15 py-1 px-2 rounded-lg border border-amber-500/30 flex items-center justify-center gap-1">
                                        <span class="material-symbols-outlined text-xs">skillet</span>
                                        ⚠️ Comanda sin enviar: envía primero a cocina antes de cobrar
                                    </p>
                                @elseif($this->comandaActivaBloqueaCobro())
                                    <p class="text-[10px] text-center font-bold text-amber-800 bg-amber-500/15 py-1 px-2 rounded-lg border border-amber-500/30">
                                        ⏳ En preparación en cocina · Cobro bloqueado
                                    </p>
                                @elseif($this->esMesaDeOtroMesero())
                                    <p class="text-[10px] text-center font-bold text-amber-500 bg-amber-500/15 py-1 px-2 rounded-lg border border-amber-500/30 flex items-center justify-center gap-1">
                                        <span class="material-symbols-outlined text-xs">shield_person</span>
                                        Mesa asignada a otro mesero · Cobro restringido
                                    </p>
                                @elseif($this->comandaListaParaCobrar())
                                    <p class="text-[10px] text-center font-bold text-emerald-800 bg-emerald-500/15 py-1 px-2 rounded-lg border border-emerald-500/30">
                                        🛎️ ¡Comanda despachada / lista en cocina! Habilitado para cobrar
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
