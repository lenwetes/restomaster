        <!-- VISTA PC / TABLET: TERMINAL TÁCTIL BENTO TOUCH PRO                        -->
        <!-- ========================================================================= -->
        <div 
            wire:key="pos-terminal-vista-escritorio"
            class="space-y-2.5 -my-2 lg:-my-4 relative {{ $vistaMesero === 'tablet' ? 'max-w-5xl mx-auto' : 'w-full' }}"
            x-data="{ 
                modalMesasAbierto: false,
                filtroZonaModal: 'todas',
                toastVisible: @js(session()->has('advertencia_mesa')), 
                toastMsg: @js(session('advertencia_mesa', '¡Atención! Primero debes seleccionar una mesa para tomar el pedido.')),
                mostrarToast(msg) {
                    this.toastMsg = msg || '¡Atención! Primero debes seleccionar una mesa para tomar el pedido.';
                    this.toastVisible = true;
                    setTimeout(() => { this.toastVisible = false; }, 4500);
                }
            }"
            @abrir-selector-mesa.window="modalMesasAbierto = true"
            @notificacion-mesa-requerida.window="mostrarToast($event.detail?.mensaje)"
            @keydown.escape.window="modalMesasAbierto = false"
        >
            <!-- Toast Flotante Visualmente Agradable -->
            <div 
                x-show="toastVisible" 
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-4 scale-95"
                class="fixed top-20 right-6 z-[100] max-w-md bg-[#1c130e] border-2 border-amber-500/80 rounded-2xl p-3.5 shadow-2xl shadow-black/90 flex items-start gap-3 backdrop-blur-md"
            >
                <div class="w-9 h-9 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">table_restaurant</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-black text-amber-300 uppercase tracking-wide">Mesa Requerida</span>
                        <span class="text-[9px] px-1.5 py-0.2 rounded bg-amber-500/25 text-amber-200 font-bold">Aviso</span>
                    </div>
                    <p class="text-[11px] text-white/90 mt-1 leading-snug font-medium" x-text="toastMsg"></p>
                    <div class="mt-2.5 flex items-center gap-2">
                        <button 
                            type="button" 
                            @click="modalMesasAbierto = true; toastVisible = false;" 
                            class="px-3 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-[11px] font-black cursor-pointer transition flex items-center gap-1 shadow-md shadow-amber-600/30"
                        >
                            <span class="material-symbols-outlined text-[14px]">touch_app</span>
                            <span>Elegir Mesa Ahora</span>
                        </button>
                    </div>
                </div>
                <button 
                    type="button" 
                    @click="toastVisible = false" 
                    class="text-[#a89086] hover:text-white text-xs font-bold p-1 cursor-pointer"
                >✕</button>
            </div>

            <!-- Bento Touch Pro: BARRA DE COMANDO TÁCTIL UNIFICADA -->
            <div class="bg-[#1c1411] border border-[#32231c] rounded-2xl px-3.5 py-2 flex flex-wrap items-center justify-between gap-3 shadow-lg shadow-black/40 shrink-0">
                <!-- Izquierda: Modo de servicio táctil, Mesa & Comensal -->
                <div class="flex items-center gap-2 flex-wrap min-w-0">
                    <!-- Modo de Servicio -->
                    <div class="flex items-center bg-[#120d0b] p-0.5 rounded-xl border border-[#32231c]">
                        <button 
                            wire:click="$set('tipo', 'mesa')" 
                            type="button"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black transition cursor-pointer {{ $tipo === 'mesa' ? 'bg-[#e0442e] text-white shadow' : 'text-[#a89086] hover:text-white' }}"
                        >
                            <span class="material-symbols-outlined text-[16px]">table_restaurant</span>
                            <span>En Mesa</span>
                        </button>
                        <button 
                            wire:click="$set('tipo', 'mostrador')" 
                            type="button"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black transition cursor-pointer {{ $tipo === 'mostrador' ? 'bg-[#e0442e] text-white shadow' : 'text-[#a89086] hover:text-white' }}"
                        >
                            <span class="material-symbols-outlined text-[16px]">takeout_dining</span>
                            <span>Para Llevar</span>
                        </button>
                        @if(Auth::user()?->role?->slug !== 'mesero')
                            <button 
                                wire:click="$set('tipo', 'delivery')" 
                                type="button"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black transition cursor-pointer {{ $tipo === 'delivery' ? 'bg-[#e0442e] text-white shadow' : 'text-[#a89086] hover:text-white' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">moped</span>
                                <span>Delivery</span>
                            </button>
                        @endif
                    </div>

                    <!-- Selector de Mesa Activa Botón Táctil -->
                    @if($tipo === 'mesa')
                        @php 
                            $mesaSeleccionada = $mesaId ? $mesas->firstWhere('id', $mesaId) : null; 
                            $esCobroPendienteTop = $this->cobroEnviadoACaja();
                        @endphp
                        <button 
                            type="button"
                            @click="modalMesasAbierto = true"
                            class="flex items-center gap-2 bg-[#251b16] px-3.5 py-1.5 rounded-xl border transition cursor-pointer {{ $esCobroPendienteTop ? 'border-amber-500/80 ring-2 ring-amber-500/30' : (!$mesaId ? 'border-amber-500/60 ring-2 ring-amber-500/30' : 'border-[#3d2b22] hover:border-[#e0442e]/50') }}"
                        >
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $esCobroPendienteTop ? 'bg-amber-400 animate-ping' : ($mesaSeleccionada ? 'bg-[#10b981] shadow-xs shadow-[#10b981]' : 'bg-amber-500 animate-pulse') }}"></span>
                            <div class="flex flex-col text-left -space-y-0.5">
                                <span class="text-[9px] font-bold uppercase tracking-wider {{ $esCobroPendienteTop ? 'text-amber-400 font-black' : (!$mesaId ? 'text-amber-400' : 'text-[#a89086]') }}">
                                    {{ $esCobroPendienteTop ? '⏳ Cobro Enviado a Caja' : (!$mesaId ? '⚠️ Mesa Requerida' : 'Mesa Seleccionada') }}
                                </span>
                                <span class="text-xs font-black text-white flex items-center gap-1.5">
                                    {{ $mesaSeleccionada ? $mesaSeleccionada->nombre_sala . ($mesaSeleccionada->zona ? ' · ' . $mesaSeleccionada->zona : '') : 'Elegir mesa para comanda...' }}
                                    @if($esCobroPendienteTop)
                                        <span class="text-[10px] font-black text-amber-300 bg-amber-500/20 border border-amber-500/40 px-1.5 py-0.2 rounded">En Caja</span>
                                    @endif
                                    <span class="material-symbols-outlined text-[16px] text-[#a89086]">touch_app</span>
                                </span>
                            </div>
                        </button>
                        @if(!$mesaId && Auth::user()?->role?->slug === 'mesero')
                            <span class="text-[11px] text-amber-400 font-bold animate-pulse hidden sm:inline whitespace-nowrap">← Toca para seleccionar</span>
                        @endif
                    @endif

                    <!-- ========================================================================= -->
                    <!-- MODAL CENTRAL TÁCTIL: SELECTOR DE MESAS (BENTO TOUCH PRO)                -->
                    <!-- ========================================================================= -->
                    <div 
                        x-show="modalMesasAbierto" 
                        x-cloak
                        class="fixed inset-0 z-[150] flex items-center justify-center p-4 sm:p-6"
                    >
                        <!-- Backdrop oscuro con blur -->
                        <div 
                            class="fixed inset-0 bg-black/85 backdrop-blur-md transition-opacity"
                            @click="modalMesasAbierto = false"
                        ></div>

                        <!-- Contenedor del Modal Central -->
                        <div 
                            x-show="modalMesasAbierto"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                            class="relative max-w-4xl w-full bg-[#18100d] border border-[#38261e] rounded-3xl shadow-2xl shadow-black/95 p-5 sm:p-7 flex flex-col max-h-[90vh] z-10 text-white"
                        >
                            <!-- Header del Modal -->
                            <div class="flex items-center justify-between gap-2 pb-4 border-b border-[#2d1e18] shrink-0">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-12 h-12 rounded-2xl bg-[#e0442e]/15 border border-[#e0442e]/30 flex items-center justify-center text-[#e0442e] shrink-0">
                                        <span class="material-symbols-outlined text-[28px]">table_restaurant</span>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-black text-white flex items-center gap-2">
                                            <span class="truncate">Mapa de Mesas & Salón</span>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-[#251b16] text-[#a89086] border border-[#38261e] shrink-0 whitespace-nowrap">
                                                {{ $mesas->count() }} Mesas
                                            </span>
                                        </h3>
                                        <p class="text-xs text-[#a89086] mt-0.5">
                                            Toca una mesa para asignarla a la comanda actual y cargar pedidos.
                                        </p>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    @click="modalMesasAbierto = false" 
                                    class="w-10 h-10 rounded-2xl bg-[#251b16] border border-[#38261e] hover:bg-[#e0442e] hover:border-[#e0442e] text-[#a89086] hover:text-white flex items-center justify-center transition cursor-pointer text-lg font-bold shadow shrink-0"
                                    title="Cerrar ventana"
                                >✕</button>
                            </div>

                            @php
                                $zonasDisponibles = $mesas->pluck('zona')->unique()->filter()->values();
                            @endphp

                            <!-- Pestañas de Filtro por Zona (Táctil Pro) -->
                            <div class="flex items-center gap-2 py-3 overflow-x-auto shrink-0 custom-scrollbar select-none">
                                <button 
                                    type="button"
                                    @click="filtroZonaModal = 'todas'"
                                    class="px-4 py-2 rounded-xl text-xs font-black transition cursor-pointer flex items-center gap-1.5 shrink-0 whitespace-nowrap"
                                    :class="filtroZonaModal === 'todas' ? 'bg-[#e0442e] text-white shadow-md' : 'bg-[#251b16] text-[#a89086] hover:text-white border border-[#38261e]'"
                                >
                                    <span>Todas las Zonas</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded-md font-mono" :class="filtroZonaModal === 'todas' ? 'bg-black/30 text-white' : 'bg-[#18100d] text-[#a89086]'">
                                        {{ $mesas->count() }}
                                    </span>
                                </button>
                                @foreach($zonasDisponibles as $z)
                                    @php $countZona = $mesas->where('zona', $z)->count(); @endphp
                                    <button 
                                        type="button"
                                        @click="filtroZonaModal = '{{ $z }}'"
                                        class="px-4 py-2 rounded-xl text-xs font-black transition cursor-pointer flex items-center gap-1.5 shrink-0 whitespace-nowrap"
                                        :class="filtroZonaModal === '{{ $z }}' ? 'bg-[#e0442e] text-white shadow-md' : 'bg-[#251b16] text-[#a89086] hover:text-white border border-[#38261e]'"
                                    >
                                        <span class="capitalize">{{ $z }}</span>
                                        <span class="text-[10px] px-1.5 py-0.2 rounded-md font-mono" :class="filtroZonaModal === '{{ $z }}' ? 'bg-black/30 text-white' : 'bg-[#18100d] text-[#a89086]'">
                                            {{ $countZona }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            <!-- Grilla Táctil Grande de Mesas -->
                            <div class="flex-1 overflow-y-auto py-2 pr-1 custom-scrollbar min-h-0">
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                    @foreach($mesas as $m)
                                        @php 
                                            $mesaAjena = $m->mesero_id && (int) $m->mesero_id !== (int) Auth::id() && Auth::user()?->role?->slug === 'mesero';
                                            $esSeleccionada = (int) $mesaId === (int) $m->id;
                                            $esOcupada = $m->estado === 'ocupada';
                                        @endphp
                                        <button 
                                            type="button"
                                            x-show="filtroZonaModal === 'todas' || filtroZonaModal === '{{ $m->zona }}'"
                                            wire:click="$set('mesaId', {{ $m->id }})"
                                            @click="modalMesasAbierto = false"
                                            @disabled($mesaAjena)
                                            class="min-h-[115px] p-3.5 rounded-2xl text-left transition-all duration-150 flex flex-col justify-between border cursor-pointer group active:scale-95 {{ $esSeleccionada ? 'bg-gradient-to-br from-[#e0442e]/30 to-[#251b16] border-[#e0442e] ring-2 ring-[#e0442e]/50 shadow-xl' : ($mesaAjena ? 'bg-[#120d0b] border-[#221612] opacity-40 cursor-not-allowed text-[#786158]' : 'bg-[#251b16] border-[#38261e] hover:border-[#e0442e] hover:bg-[#2e201a] text-white shadow-md') }}"
                                        >
                                            <!-- Fila Superior: Número y Estado -->
                                            <div class="flex items-start justify-between gap-1">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center font-black text-sm shrink-0 {{ $esSeleccionada ? 'bg-[#e0442e] text-white' : 'bg-[#18100d] border border-[#38261e] text-white group-hover:border-[#e0442e]' }}">
                                                        {{ $m->nombre_corto }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <span class="text-sm font-black block leading-tight truncate">{{ $m->nombre_sala }}</span>
                                                        <span class="text-[10px] text-[#a89086] capitalize font-medium">{{ $m->zona ?: 'Salón' }}</span>
                                                    </div>
                                                </div>
                                                @if(!empty($m->cobro_pendiente))
                                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/30 text-amber-300 border border-amber-500/50 shadow-xs animate-pulse">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                                        Cobro en Caja
                                                    </span>
                                                @else
                                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider {{ $esOcupada ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' }}">
                                                        <span class="w-1.5 h-1.5 rounded-full {{ $esOcupada ? 'bg-amber-400' : 'bg-emerald-400 animate-pulse' }}"></span>
                                                        {{ $esOcupada ? 'Ocupada' : 'Libre' }}
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Fila Inferior: Capacidad y Mesero -->
                                            <div class="mt-3 pt-2 border-t border-[#32231c] flex items-center justify-between text-[11px] text-[#a89086]">
                                                <span class="flex items-center gap-1 font-medium">
                                                    <span class="material-symbols-outlined text-[14px]">group</span>
                                                    <span>{{ $m->capacidad ?? 4 }} pers.</span>
                                                </span>
                                                @if(!empty($m->cobro_pendiente))
                                                    <span class="text-[10px] font-black text-amber-300 flex items-center gap-0.5">
                                                        <span class="material-symbols-outlined text-[12px] animate-spin">hourglass_top</span>
                                                        <span>En Caja</span>
                                                    </span>
                                                @elseif($m->mesero_nombre)
                                                    <span class="text-[10px] font-semibold truncate max-w-[110px] text-amber-200/90 flex items-center gap-0.5">
                                                        <span class="material-symbols-outlined text-[12px]">person</span>
                                                        <span>{{ $m->mesero_nombre }}</span>
                                                    </span>
                                                @elseif($esSeleccionada)
                                                    <span class="text-[10px] font-black text-[#e0442e] flex items-center gap-0.5">
                                                        <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                                        <span>Activa</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Footer del Modal -->
                            <div class="pt-4 mt-2 border-t border-[#2d1e18] flex items-center justify-between shrink-0">
                                <div class="flex items-center gap-3 text-xs text-[#a89086]">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                                        <span>Verde = Disponible</span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                                        <span>Ámbar = En servicio</span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($mesaId)
                                        <button 
                                            type="button"
                                            wire:click="$set('mesaId', null)"
                                            @click="modalMesasAbierto = false"
                                            class="px-3.5 py-2 rounded-xl bg-[#251b16] border border-red-500/40 text-red-400 hover:bg-red-500/20 text-xs font-black transition cursor-pointer flex items-center gap-1.5"
                                        >
                                            <span class="material-symbols-outlined text-[15px]">cancel</span>
                                            <span>Desmarcar Mesa</span>
                                        </button>
                                    @endif
                                    <button 
                                        type="button"
                                        @click="modalMesasAbierto = false"
                                        class="px-5 py-2 rounded-xl bg-[#251b16] hover:bg-[#32231c] border border-[#38261e] text-white text-xs font-black transition cursor-pointer"
                                    >
                                        Cerrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Comensal Selector / Habeas Data -->
                    <div class="relative flex items-center" x-data="{ openDropdown: @entangle('mostrarSugerencias') }" @click.outside="openDropdown = false; $wire.cerrarSugerencias()">
                        @if($clienteId)
                            @php 
                                $cli = \App\Models\Cliente::with('direcciones')->find($clienteId); 
                                $badgeCli = $cli?->badgeTier();
                            @endphp
                            @if($cli)
                                <div class="flex items-center gap-2 bg-[#251b16] px-3 py-1.5 rounded-xl border border-[#3d2b22]">
                                    <span class="material-symbols-outlined text-[16px] text-[#2eb8b4]">person</span>
                                    <span class="text-xs text-white font-bold truncate max-w-[140px]">{{ $cli->nombre }}</span>
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded bg-[#f59e0b]/20 text-[#f59e0b] border border-[#f59e0b]/30">
                                        {{ $badgeCli['label'] ?? strtoupper($cli->tier) }}
                                    </span>
                                    <span class="text-[9px] font-mono text-[#a89086]">
                                        {{ number_format($cli->puntos_fidelidad) }} pts
                                    </span>
                                    @if($tipo === 'delivery' && $cli->direcciones->count() > 0)
                                        <select wire:model.live="direccionId" class="rounded-lg border border-[#3d2b22] bg-[#120d0b] text-[11px] py-0.5 px-2 font-semibold text-white">
                                            @foreach($cli->direcciones as $d)
                                                <option value="{{ $d->id }}">{{ $d->etiqueta }}: {{ Str::limit($d->direccion, 20) }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button 
                                        type="button"
                                        wire:click="abrirModalHabeasData" 
                                        class="text-[11px] font-bold text-[#e0442e] hover:underline px-1 py-0.5 rounded hover:bg-[#e0442e]/10 cursor-pointer"
                                        title="Actualizar datos / Habeas Data"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">edit_note</span>
                                    </button>
                                    <button wire:click="desvincularCliente" class="text-[#ef4444] hover:text-[#ff6b6b] text-[11px] font-bold ml-0.5 cursor-pointer" title="Desvincular">✕</button>
                                </div>
                            @endif
                        @else
                            <div class="flex items-center gap-2 bg-[#251b16] px-3 py-1.5 rounded-xl border border-[#3d2b22]">
                                <span class="material-symbols-outlined text-[16px] text-[#2eb8b4]">person</span>
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.300ms="nombreCliente" 
                                    placeholder="Comensal (≥4 letras)..." 
                                    autocomplete="off"
                                    class="bg-transparent text-xs text-white font-bold focus:outline-none w-36 sm:w-44 placeholder-[#786158]"
                                />
                                @if(!empty(trim($nombreCliente)))
                                    <button 
                                        type="button" 
                                        wire:click="$set('nombreCliente', ''); $wire.cerrarSugerencias()"
                                        class="text-[#786158] hover:text-white text-xs cursor-pointer"
                                        title="Limpiar"
                                    >✕</button>
                                @endif
                                <button 
                                    type="button" 
                                    wire:click="abrirModalHabeasData" 
                                    class="text-[9px] font-black px-1.5 py-0.5 rounded bg-[#f59e0b]/20 text-[#f59e0b] border border-[#f59e0b]/30 hover:bg-[#f59e0b]/30 cursor-pointer transition active:scale-95 flex items-center gap-0.5"
                                    title="Registrar nuevo cliente con datos y consentimiento"
                                >
                                    <span>+ NUEVO</span>
                                </button>
                            </div>

                            <!-- Dropdown predictivo comensales -->
                            @if($mostrarSugerencias && count($sugerenciasClientes) > 0)
                                <div class="absolute left-0 top-full mt-1.5 w-full min-w-[300px] max-h-64 overflow-y-auto rounded-2xl bg-[#1c1411] border border-[#32231c] shadow-2xl z-50 p-1.5 divide-y divide-[#32231c]">
                                    <div class="px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-[#a89086] bg-[#251b16] rounded-t-xl flex items-center justify-between">
                                        <span>Coincidencias ({{ count($sugerenciasClientes) }})</span>
                                        <span class="text-[9px] text-[#786158]">Click para vincular</span>
                                    </div>
                                    @foreach($sugerenciasClientes as $sug)
                                        <button 
                                            type="button" 
                                            wire:click="seleccionarClientePredictivo({{ $sug['id'] }})"
                                            class="w-full text-left p-2 hover:bg-[#251b16] transition rounded-xl flex items-center justify-between gap-2 cursor-pointer group"
                                        >
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-white group-hover:text-[#e0442e] truncate">{{ $sug['nombre'] }}</p>
                                                <p class="text-[10px] text-[#a89086] truncate">
                                                    {{ $sug['telefono'] ?: 'Sin teléfono' }} 
                                                    @if(!empty($sug['email'])) · {{ $sug['email'] }} @endif
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black {{ $sug['badge_class'] ?? '' }}">
                                                    {{ $sug['badge_label'] ?? strtoupper($sug['tier']) }}
                                                </span>
                                                <span class="text-[11px] font-mono font-bold text-[#e8a020]">
                                                    {{ $sug['puntos'] }} pts
                                                </span>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(mb_strlen(trim($nombreCliente)) >= 4 && empty($sugerenciasClientes) && !$clienteId)
                                <div class="absolute left-0 top-full mt-1.5 w-full min-w-[260px] rounded-xl bg-[#1c1411] border border-[#32231c] shadow-lg z-50 p-2 text-center text-xs text-[#a89086]">
                                    <span class="material-symbols-outlined text-amber-500 text-[16px] align-middle mr-1">person_add</span>
                                    Nuevo: Se registrará como <span class="font-bold text-white">Ocasional</span>.
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- Derecha: Búsqueda rápida + Estado Caja + Mesero + Selector de vista -->
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="relative w-48 sm:w-56">
                        <span class="material-symbols-outlined absolute left-2.5 top-2 text-[16px] text-[#a89086]">search</span>
                        <input 
                            type="text" 
                            wire:model.live.debounce.250ms="busqueda" 
                            placeholder="Buscar en la carta..." 
                            class="w-full bg-[#120d0b] border border-[#32231c] rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-[#786158] focus:border-[#e0442e] focus:outline-none"
                        />
                    </div>

                    <div class="shrink-0">
                        @if($turnoActivo)
                            <div class="flex items-center gap-1.5">
                                <a 
                                    href="{{ route('caja') }}" 
                                    wire:navigate 
                                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-[#2eb8b4]/10 border border-[#2eb8b4]/25 text-[#2eb8b4] text-xs font-bold hover:bg-[#2eb8b4]/20 transition"
                                    title="Turno de caja abierto - Clic para ir a control de caja"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#2eb8b4] animate-pulse"></span>
                                    <span class="font-extrabold truncate max-w-[130px]">{{ $turnoActivo->caja->nombre }}</span>
                                    <span class="text-[10px] font-mono text-[#2eb8b4]/70">#{{ $turnoActivo->id }}</span>
                                </a>

                                <button 
                                    type="button" 
                                    wire:click="previsualizarUltimoTicketPos"
                                    class="flex items-center gap-1 px-2 py-1 rounded-xl bg-[#e0442e]/10 border border-[#e0442e]/25 text-[#e0442e] text-xs font-black hover:bg-[#e0442e]/20 transition cursor-pointer shadow-xs active:scale-95"
                                    title="Previsualizar el último ticket generado para confirmar datos en BD"
                                >
                                    <span class="material-symbols-outlined text-[15px]">receipt_long</span>
                                    <span class="hidden sm:inline">Último Ticket</span>
                                </button>
                            </div>
                        @else
                            @can('abrir', App\Models\TurnoCaja::class)
                                <button 
                                    type="button" 
                                    wire:click="abrirModalAperturaPosManual"
                                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-[#ef4444]/15 border border-[#ef4444]/30 text-[#ef4444] text-xs font-black hover:bg-[#ef4444]/25 transition cursor-pointer"
                                    title="Caja cerrada. Haz clic para ingresar la base y abrir turno"
                                >
                                    <span class="material-symbols-outlined text-[15px]">lock_open</span>
                                    <span>Abrir Caja</span>
                                </button>
                            @else
                                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-[#251b16] border border-[#3d2b22] text-[#a89086] text-xs font-bold">
                                    <span class="material-symbols-outlined text-[15px] text-[#ef4444]">lock</span>
                                    <span>Caja Cerrada</span>
                                </div>
                            @endcan
                        @endif
                    </div>

                    <div class="flex items-center gap-1.5 px-2 py-1 rounded-xl bg-[#1c1411] border border-[#32231c]">
                        <div class="w-6 h-6 rounded-lg bg-[#e0442e]/20 text-[#e0442e] font-bold text-[11px] flex items-center justify-center">
                            {{ strtoupper(substr(Auth::user()?->name ?? 'A', 0, 2)) }}
                        </div>
                        <span class="text-xs font-bold text-white truncate max-w-[90px]">{{ Auth::user()?->name ?? 'Alejandro' }}</span>
                    </div>

                    @if(Auth::user()?->role?->slug === 'mesero')
                        <div class="flex items-center gap-0.5 bg-[#120d0b] p-0.5 rounded-xl border border-[#32231c] shrink-0" role="group" aria-label="Selector de vistas">
                            <button 
                                type="button" 
                                wire:click="cambiarVista('pc')"
                                id="btnVistaPc"
                                class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-black transition cursor-pointer {{ $vistaMesero === 'pc' ? 'bg-[#e0442e] text-white shadow' : 'text-[#a89086] hover:text-white' }}"
                                title="Vista PC"
                            >
                                <span class="material-symbols-outlined text-[15px]">desktop_windows</span>
                            </button>
                            <button 
                                type="button" 
                                wire:click="cambiarVista('tablet')"
                                id="btnVistaTablet"
                                class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-black transition cursor-pointer {{ $vistaMesero === 'tablet' ? 'bg-[#e0442e] text-white shadow' : 'text-[#a89086] hover:text-white' }}"
                                title="Vista Tablet"
                            >
                                <span class="material-symbols-outlined text-[15px]">tablet</span>
                            </button>
                            <button 
                                type="button" 
                                wire:click="cambiarVista('movil')"
                                id="btnVistaMovil"
                                class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-black transition cursor-pointer {{ $vistaMesero === 'movil' ? 'bg-[#e0442e] text-white shadow' : 'text-[#a89086] hover:text-white' }}"
                                title="Vista Móvil"
                            >
                                <span class="material-symbols-outlined text-[15px]">smartphone</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL: CATÁLOGO (8 Cols) + COMANDA DIGITAL (4 Cols) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 min-h-0">
                <!-- COLUMNA CATÁLOGO -->
                <div class="lg:col-span-8 flex flex-col gap-2 min-h-0 h-auto lg:h-[calc(100vh-14rem)]">
                    <!-- CATEGORÍAS (Con soporte PC: rueda ratón, scroll buttons y micro-indicadores táctiles) -->
                    <div 
                        wire:key="pos-cats-scroll-container"
                        x-data="{
                            canScrollLeft: false,
                            canScrollRight: true,
                            updateScroll() {
                                const el = this.$refs.catBar;
                                if (!el) return;
                                this.canScrollLeft = el.scrollLeft > 5;
                                this.canScrollRight = el.scrollLeft < (el.scrollWidth - el.clientWidth - 5);
                            },
                            scrollLeft() {
                                this.$refs.catBar.scrollBy({ left: -260, behavior: 'smooth' });
                            },
                            scrollRight() {
                                this.$refs.catBar.scrollBy({ left: 260, behavior: 'smooth' });
                            }
                        }"
                        x-init="updateScroll(); window.addEventListener('resize', () => updateScroll())"
                        class="relative flex items-center shrink-0 group/cats"
                    >
                        <!-- Botón scroll Izquierda (PC Friendly) -->
                        <button 
                            type="button"
                            id="btnCatNavLeft"
                            @click="scrollLeft()"
                            x-show="canScrollLeft"
                            x-cloak
                            class="absolute left-1.5 z-20 w-8 h-8 rounded-xl bg-[#251b16]/95 border border-[#3d2b22] text-white hover:bg-[#e0442e] hover:border-[#e0442e] shadow-xl flex items-center justify-center transition cursor-pointer backdrop-blur-md"
                            title="Desplazar categorías a la izquierda"
                        >
                            <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                        </button>

                        <!-- Contenedor con soporte horizontal para rueda del mouse -->
                        <div 
                            x-ref="catBar"
                            @scroll.passive="updateScroll()"
                            @wheel.prevent="$refs.catBar.scrollLeft += $event.deltaY"
                            class="flex-1 bg-[#1c1411] border border-[#32231c] rounded-2xl p-1.5 flex items-center gap-1.5 overflow-x-auto scroll-smooth no-scrollbar select-none"
                        >
                            <!-- Todos -->
                            <button 
                                wire:click="$set('categoriaSeleccionada', null)"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-extrabold transition flex items-center gap-2 shrink-0 cursor-pointer {{ is_null($categoriaSeleccionada) ? 'bg-[#e0442e] text-white shadow-sm' : 'bg-[#251b16] border border-[#3d2b22] text-[#a89086] hover:text-white hover:bg-[#2c201a]' }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">restaurant_menu</span>
                                <span>Todos</span>
                                <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-md font-bold {{ is_null($categoriaSeleccionada) ? 'bg-black/30 text-white/90' : 'bg-[#32231c] text-[#a89086]' }}">
                                    {{ $categorias->sum('productos_count') }}
                                </span>
                            </button>

                            <!-- Botones por Categoría -->
                            @foreach($categorias as $cat)
                                @php 
                                    $catColor = $cat->color ?? '#e0442e'; 
                                    $isActive = $categoriaSeleccionada === $cat->id;
                                @endphp
                                <button 
                                    wire:click="$set('categoriaSeleccionada', {{ $cat->id }})"
                                    type="button"
                                    class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition flex items-center gap-2 shrink-0 cursor-pointer {{ $isActive ? 'text-white shadow-sm' : 'bg-[#251b16] text-white hover:bg-[#2c201a]' }}"
                                    @style([
                                        'background-color: ' . $catColor => $isActive, 
                                        'border: 1px solid ' . ($isActive ? $catColor : $catColor . '55')
                                    ])
                                >
                                    <span 
                                        class="w-2.5 h-2.5 rounded-full shrink-0" 
                                        @style([
                                            'background-color: ' . $catColor, 
                                            'box-shadow: 0 0 8px ' . $catColor
                                        ])
                                    ></span>
                                    @if(preg_match('/^[a-z0-9_]+$/', $cat->icono ?? ''))
                                        <span class="material-symbols-outlined text-[16px]">{{ $cat->icono }}</span>
                                    @else
                                        <span class="text-sm leading-none">{{ $cat->icono ?: '🍱' }}</span>
                                    @endif
                                    <span>{{ $cat->nombre }}</span>
                                    <span 
                                        class="text-[10px] font-mono px-1.5 py-0.2 rounded-md font-bold {{ $isActive ? 'bg-black/30 text-white' : '' }}"
                                        @style([
                                            'background-color: ' . $catColor . '25' => !$isActive,
                                            'color: ' . $catColor => !$isActive
                                        ])
                                    >
                                        {{ $cat->productos_count ?? 0 }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <!-- Botón scroll Derecha (PC Friendly) -->
                        <button 
                            type="button"
                            id="btnCatNavRight"
                            @click="scrollRight()"
                            x-show="canScrollRight"
                            x-cloak
                            class="absolute right-1.5 z-20 w-8 h-8 rounded-xl bg-[#251b16]/95 border border-[#3d2b22] text-white hover:bg-[#e0442e] hover:border-[#e0442e] shadow-xl flex items-center justify-center transition cursor-pointer backdrop-blur-md"
                            title="Desplazar categorías a la derecha"
                        >
                            <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                        </button>

                        <!-- Botón Crear Producto: Habilitado para Administrador y Gerente (Invisible para mesero) -->
                        @if(in_array(auth()->user()?->role?->slug, ['admin', 'gerente'], true))
                            <div class="shrink-0 border-l border-white/10 pl-2">
                                <a 
                                    href="{{ route('menu') }}"
                                    wire:navigate
                                    id="btnPosCrearProductoAdmin"
                                    class="flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-black text-white bg-primary hover:bg-primary-container border border-primary shadow-sm transition-all active:scale-95"
                                    title="Solo Administrador: Crear o personalizar nuevo producto en la carta"
                                >
                                    <span class="material-symbols-outlined text-[16px]">add_circle</span>
                                    <span class="hidden sm:inline">+ Nuevo Producto</span>
                                    <span class="sm:hidden">+</span>
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- BANNER BLOQUEO PREVENTIVO: SI ES SERVICIO EN MESA Y NO HAY MESA SELECCIONADA -->
                    @if($tipo === 'mesa' && !$mesaId)
                        <div class="rounded-2xl border border-amber-500/50 bg-gradient-to-r from-amber-950/40 via-[#241a14] to-[#1c1411] p-3 shadow-lg flex flex-col items-stretch justify-between gap-2.5 sm:flex-row sm:items-center shrink-0">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 shrink-0">
                                    <span class="material-symbols-outlined text-[22px]">table_restaurant</span>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-black text-amber-300 uppercase tracking-wider flex flex-wrap items-center gap-2">
                                        <span>Mesa Requerida para Comanda</span>
                                        <span class="text-[9px] px-2 py-0.5 rounded-md bg-amber-500/25 text-amber-200 border border-amber-500/40 font-black whitespace-nowrap">
                                            BLOQUEO ACTIVO
                                        </span>
                                    </h4>
                                    <p class="text-[11px] text-amber-100/90 font-medium mt-0.5">
                                        Primero debes seleccionar una mesa para habilitar la toma y el registro de platos.
                                    </p>
                                </div>
                            </div>
                            <button 
                                type="button"
                                @click="$dispatch('abrir-selector-mesa')"
                                class="w-full sm:w-auto justify-center px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white text-xs font-black shadow-md shadow-amber-600/30 active:scale-95 transition flex items-center gap-1.5 cursor-pointer shrink-0"
                            >
                                <span class="material-symbols-outlined text-[16px]">touch_app</span>
                                <span>Seleccionar Mesa</span>
                            </button>
                        </div>
                    @endif

                    <!-- GRILLA DE PRODUCTOS BENTO (Altura garantizada, tarjetas con min-h-[225px], no colapsables) -->
                    <div class="flex-1 min-h-0 overflow-y-auto pr-1 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 content-start auto-rows-max custom-scrollbar">
                        @forelse($productos as $prod)
                            @php 
                                $prodColor = $prod->categoria?->color ?? '#e0442e'; 
                                $inCart = isset($carrito[$prod->id]);
                                $desktopAreaLabel = match(strtolower($prod->area_cocina ?? 'caliente')) {
                                    'sushi', 'fria', 'cocina_fria' => 'Cocina Fría',
                                    'caliente', 'calientes', 'cocina' => 'Caliente',
                                    'barra', 'bebidas' => 'Barra',
                                    'postres' => 'Postres',
                                    default => ucfirst($prod->area_cocina),
                                };
                            @endphp
                            <div 
                                wire:click="agregarProducto({{ $prod->id }})"
                                class="min-h-[225px] rounded-2xl p-2.5 flex flex-col justify-between relative group transition-all duration-200 cursor-pointer overflow-hidden {{ $inCart ? 'bg-[#1e1511] border-2 border-[#e0442e] shadow-lg shadow-[#e0442e]/10' : 'bg-[#1c1411] border border-[#32231c] hover:border-[#3d2b22] hover:bg-[#231915]' }}"
                            >
                                <!-- Top accent line con glow si está en carrito -->
                                <div 
                                    class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl shrink-0" 
                                    @style([
                                        'background-color: ' . $prodColor, 
                                        'box-shadow: 0 0 8px ' . $prodColor => $inCart
                                    ])
                                ></div>

                                <div class="flex flex-col flex-1 min-h-0">
                                    <!-- Header de la tarjeta -->
                                    <div class="flex items-center justify-between mb-2 mt-0.5 shrink-0">
                                        <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded-md bg-[#251b16] text-[#a89086] border border-[#3d2b22]">
                                            {{ $desktopAreaLabel }}
                                        </span>
                                        @if($inCart)
                                            <span class="px-2 py-0.5 rounded-lg bg-[#e0442e] text-white text-[10px] font-black flex items-center gap-1 shadow">
                                                <span class="material-symbols-outlined text-[12px]">check</span>
                                                {{ $carrito[$prod->id]['cantidad'] }} en orden
                                            </span>
                                        @else
                                            <span class="text-[9px] font-bold text-[#10b981] flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#10b981]"></span>
                                                Disp.
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Contenedor Visual de Fotografía / Emoji -->
                                    <div class="h-24 w-full rounded-xl bg-gradient-to-br from-[#2a1e18] to-[#17100d] border border-[#38261e] flex flex-col items-center justify-center relative overflow-hidden shrink-0 group-hover:border-[#e0442e]/40 transition">
                                        @if($prod->imagen_url)
                                            <img 
                                                src="{{ $prod->imagen_url }}" 
                                                alt="{{ $prod->nombre }}" 
                                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                loading="lazy"
                                            />
                                        @else
                                            @if(preg_match('/^[a-z0-9_]+$/', $prod->categoria?->icono ?? ''))
                                                <span class="material-symbols-outlined text-4xl filter drop-shadow-md" @style(['color: ' . $prodColor])>{{ $prod->categoria->icono }}</span>
                                            @else
                                                <span class="text-4xl filter drop-shadow-md">{{ $prod->categoria?->icono ?: '🍱' }}</span>
                                            @endif
                                        @endif
                                        <span class="absolute bottom-1 right-1.5 text-[9px] font-bold text-[#10b981] bg-[#10b981]/15 px-1.5 py-0.2 rounded border border-[#10b981]/30">Stock {{ $prod->stock ?? 18 }}</span>
                                    </div>

                                    <!-- Título & Descripción -->
                                    <div class="mt-2 shrink-0">
                                        <h3 class="text-xs font-black text-white leading-tight line-clamp-2 min-h-[1.75rem]">{{ $prod->nombre }}</h3>
                                        <p class="text-[10px] text-[#a89086] line-clamp-1 mt-0.5">{{ $prod->descripcion ?: 'Elaborado fresco al momento.' }}</p>
                                    </div>
                                </div>

                                <!-- Footer Precio + Stepper Táctil Rápido -->
                                <div class="mt-2.5 pt-2 border-t border-[#32231c] flex items-center justify-between shrink-0">
                                    <span class="text-sm font-mono font-black text-[#ff8080]">${{ number_format((float) $prod->precio, 0, ',', '.') }}</span>
                                    @if($inCart)
                                        <div class="flex items-center gap-1 bg-[#120d0b] p-0.5 rounded-lg border border-[#32231c]" @click.stop>
                                            <button 
                                                type="button"
                                                wire:click.stop="decrementarCantidad({{ $prod->id }})" 
                                                class="w-6 h-6 rounded bg-[#251b16] text-white text-xs font-black hover:bg-[#e0442e] flex items-center justify-center transition active:scale-90 cursor-pointer"
                                                title="Restar 1"
                                            >-</button>
                                            <span class="w-4 text-center font-mono font-bold text-xs text-white">
                                                {{ $carrito[$prod->id]['cantidad'] }}
                                            </span>
                                            <button 
                                                type="button"
                                                wire:click.stop="incrementarCantidad({{ $prod->id }})" 
                                                class="w-6 h-6 rounded bg-[#251b16] text-white text-xs font-black hover:bg-[#e0442e] flex items-center justify-center transition active:scale-90 cursor-pointer"
                                                title="Sumar 1"
                                            >+</button>
                                        </div>
                                    @else
                                        <button 
                                            type="button"
                                            wire:click.stop="agregarProducto({{ $prod->id }})" 
                                            class="w-6 h-6 rounded bg-[#251b16] text-white text-xs font-black hover:bg-[#e0442e] flex items-center justify-center border border-[#3d2b22] transition active:scale-90 cursor-pointer"
                                            title="Agregar al pedido"
                                        >+</button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full rounded-2xl border border-dashed border-[#32231c] p-12 text-center text-[#a89086] flex flex-col items-center justify-center gap-3">
                                <span class="material-symbols-outlined text-[36px] text-[#786158]">ramen_dining</span>
                                <p class="text-xs font-semibold">No hay productos o servicios en esta categoría.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- COLUMNA COMANDA / TICKET DIGITAL (4 Cols) -->
                <div class="lg:col-span-4 bg-[#1c1411] border border-[#32231c] rounded-2xl p-2.5 sm:p-3 flex flex-col justify-between shadow-xl shadow-black/50 min-h-0 h-auto lg:h-[calc(100vh-14rem)]">
                    <!-- HEADER TICKET -->
                    <div>
                        <div class="flex items-center justify-between pb-2.5 border-b border-[#32231c]">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg bg-[#e0442e]/15 text-[#e0442e] flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                </div>
                                <div>
                                    <h2 class="text-xs font-black text-white flex items-center gap-1.5">
                                        <span>COMANDA · {{ $tipo === 'mesa' ? ($mesaId ? 'MESA ' . $mesas->find($mesaId)?->numero : 'SIN ASIGNAR') : strtoupper($tipo) }}</span>
                                        @if($tipo === 'mesa' && $mesaId)
                                            @if($this->cobroEnviadoACaja())
                                                <span class="text-[10px] font-black text-amber-300 bg-amber-500/20 border border-amber-500/40 px-2 py-0.5 rounded-full flex items-center gap-1 shadow-xs animate-pulse">
                                                    <span class="material-symbols-outlined text-[12px] animate-spin">hourglass_top</span>
                                                    COBRO EN CAJA
                                                </span>
                                            @else
                                                <span class="text-[10px] font-bold text-[#10b981] bg-[#10b981]/15 px-1.5 py-0.2 rounded">ACTIVA</span>
                                            @endif
                                        @endif
                                    </h2>
                                    @php
                                        $pedidoActivoHeader = $this->obtenerPedidoActivoMesa();
                                        $mesaObjHeader = $mesaId ? $mesas->firstWhere('id', $mesaId) : null;
                                        $meseroNombre = $pedidoActivoHeader?->mesero?->name 
                                            ?? $mesaObjHeader?->mesero?->name 
                                            ?? (Auth::user()?->isMesero() ? Auth::user()->name : 'Sin asignar');
                                        $clienteNombre = $clienteId 
                                            ? ($mesaObjHeader?->cliente?->nombre ?? 'Registrado') 
                                            : ($pedidoActivoHeader?->nombre_cliente ?? 'Ocasional');
                                    @endphp
                                    <p class="text-[10px] text-[#a89086]">
                                        Comensal: {{ $clienteNombre }} · Mesero: {{ $meseroNombre }}
                                    </p>
                                </div>
                            </div>
                            @if(count($carrito) > 0)
                                <button 
                                    wire:click="limpiarCarrito" 
                                    class="text-[11px] font-bold text-[#ef4444] hover:underline flex items-center gap-1 cursor-pointer"
                                    title="Vaciar comanda"
                                >
                                    <span class="material-symbols-outlined text-[14px]">delete_sweep</span>
                                    <span>Vaciar</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($pedidoActivoHeader && $pedidoActivoHeader->estado === 'pendiente_cobro')
                        <div class="shrink-0 mt-2 rounded-xl border border-amber-500/50 bg-gradient-to-r from-amber-500/20 via-amber-600/15 to-[#251b16] p-2.5 flex items-center justify-between gap-2 shadow-lg shadow-amber-950/20 animate-fade-in">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-xl bg-amber-500/25 text-amber-300 flex items-center justify-center shrink-0 border border-amber-500/40">
                                    <span class="material-symbols-outlined text-[18px] animate-pulse">point_of_sale</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-black uppercase text-amber-300 tracking-wider">Cobro Enviado a Caja</span>
                                        <span class="inline-block w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                                    </div>
                                    <p class="text-[11px] font-semibold text-amber-100/90 leading-tight">
                                        Solicitud enviada a caja. Esperando cobro del cajero (${{ number_format((float) $pedidoActivoHeader->total, 0, ',', '.') }}).
                                    </p>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="text-[10px] font-mono font-black text-amber-300 bg-black/40 px-2 py-1 rounded-md border border-amber-500/30">
                                    Turno en Caja
                                </span>
                            </div>
                        </div>
                    @endif

                    @if($pedidoQrPendiente)
                        <div class="shrink-0 mt-2 rounded-xl border border-amber-500/40 bg-amber-500/10 p-2 flex items-center justify-between gap-2 shadow-xs animate-pulse">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-amber-500 text-[18px] shrink-0">notifications_active</span>
                                <div class="min-w-0">
                                    <span class="block text-[9px] font-black uppercase text-amber-400 leading-tight">Pedido QR por Asignar</span>
                                    <span class="text-[10px] font-bold text-white truncate block">{{ $pedidoQrPendiente->nombre_cliente ?? 'Comensal' }} ({{ $pedidoQrPendiente->items->count() }} platos)</span>
                                </div>
                            </div>
                            <button 
                                wire:click="atenderPedidoQrActual"
                                type="button"
                                class="px-2 py-1 rounded-lg bg-[#e0442e] text-white text-[10px] font-black shadow-sm hover:bg-[#c73420] active:scale-95 transition cursor-pointer shrink-0"
                            >
                                Tomar Mesa
                            </button>
                        </div>
                    @endif

                    <!-- LISTA DINÁMICA DE ITEMS -->
                    <div class="flex-1 min-h-0 overflow-y-auto py-2 space-y-2 pr-1 custom-scrollbar">
                        @if($modoNuevaAdicion)
                            <div class="rounded-xl border border-[#e0442e]/40 bg-[#e0442e]/10 p-2 flex items-center justify-between text-xs animate-fade-in">
                                <div class="flex items-center gap-1.5 text-[#e0442e] font-black text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]">add_circle</span>
                                    <span>Nuevo Pedido / Adición</span>
                                </div>
                                <button 
                                    wire:click="cancelarModoAdicion" 
                                    type="button"
                                    class="text-[10px] font-bold text-[#e0442e] hover:underline cursor-pointer"
                                >
                                    Ver cuenta total
                                </button>
                            </div>
                        @endif

                        @forelse($carrito as $pId => $item)
                            <div class="bg-[#251b16] border border-[#3d2b22] rounded-xl p-2.5 hover:border-[#e0442e]/40 transition">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-black text-white block truncate">{{ $item['nombre'] }}</span>
                                        <span class="text-[10px] font-mono text-[#a89086]">${{ number_format($item['precio'], 0, ',', '.') }} c/u</span>
                                    </div>
                                    <span class="text-xs font-mono font-black text-white">${{ number_format($item['precio'] * $item['cantidad'], 0, ',', '.') }}</span>
                                </div>

                                <!-- Controles táctiles amplios -->
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <div class="flex items-center bg-[#120d0b] rounded-lg border border-[#32231c] p-0.5">
                                        <button 
                                            wire:click="decrementarCantidad({{ $pId }})"
                                            class="w-7 h-7 rounded-md bg-[#1c1411] text-white font-black hover:bg-[#e0442e] flex items-center justify-center text-sm active:scale-90 transition cursor-pointer"
                                            title="Restar 1"
                                        >-</button>
                                        <span class="w-7 text-center font-mono font-bold text-xs text-white">{{ $item['cantidad'] }}</span>
                                        <button 
                                            wire:click="incrementarCantidad({{ $pId }})"
                                            class="w-7 h-7 rounded-md bg-[#1c1411] text-white font-black hover:bg-[#e0442e] flex items-center justify-center text-sm active:scale-90 transition cursor-pointer"
                                            title="Sumar 1"
                                        >+</button>
                                    </div>

                                    <div class="flex-1 relative">
                                        <input 
                                            type="text" 
                                            wire:model.lazy="carrito.{{ $pId }}.notas" 
                                            placeholder="Nota a la cocina..." 
                                            class="w-full bg-[#120d0b] border border-[#32231c] rounded-lg px-2 py-1 text-[10px] text-[#f59e0b] placeholder-[#786158] focus:outline-none focus:border-[#f59e0b]"
                                        />
                                    </div>

                                    <button 
                                        wire:click="eliminarItem({{ $pId }})" 
                                        class="w-7 h-7 rounded-lg text-[#a89086] hover:text-[#ef4444] hover:bg-[#ef4444]/10 flex items-center justify-center transition cursor-pointer"
                                        title="Eliminar ítem"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                    </button>
                                </div>
                            </div>
                        @empty
                            @if($tipo === 'mesa' && !$mesaId)
                                <div class="py-12 text-center flex flex-col items-center justify-center my-auto px-4">
                                    <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 mb-3 animate-pulse">
                                        <span class="material-symbols-outlined text-[30px]">table_restaurant</span>
                                    </div>
                                    <h4 class="text-xs font-black text-amber-300 uppercase tracking-wide">Mesa Requerida</h4>
                                    <p class="text-[11px] text-[#a89086] mt-1 max-w-[210px] leading-relaxed">
                                        Selecciona una mesa en la barra superior o presiona el botón para comenzar a comisionar platos.
                                    </p>
                                    <button 
                                        type="button"
                                        @click="$dispatch('abrir-selector-mesa')"
                                        class="mt-3.5 px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-[11px] font-black transition cursor-pointer flex items-center gap-1.5 shadow-md shadow-amber-600/25 active:scale-95"
                                    >
                                        <span class="material-symbols-outlined text-[15px]">touch_app</span>
                                        <span>Seleccionar Mesa</span>
                                    </button>
                                </div>
                            @else
                                <div class="py-16 text-center flex flex-col items-center justify-center my-auto">
                                    <span class="material-symbols-outlined text-[36px] text-[#786158] mb-2">restaurant</span>
                                    <p class="text-xs font-bold text-white/90">El carrito está vacío.</p>
                                    <p class="text-[11px] text-[#a89086] mt-0.5">Selecciona platos del menú para armar la comanda.</p>
                                </div>
                            @endif
                        @endforelse
                    </div>

                    <!-- FOOTER FINANCIERO Y BOTONES DE EJECUCIÓN TÁCTIL -->
                    <div class="pt-2.5 border-t border-[#32231c] space-y-2 shrink-0">
                        <div class="space-y-1 text-xs">
                            <div class="flex justify-between text-[#a89086]">
                                <span>Subtotal ({{ count($carrito) }} ítems)</span>
                                <span class="font-mono font-bold text-white">${{ number_format($this->subtotal, 0, ',', '.') }}</span>
                            </div>

                            @if($tipo === 'delivery')
                                <div class="flex justify-between text-[#a89086]">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-[#e0442e]">two_wheeler</span>
                                        Costo Envío Delivery:
                                    </span>
                                    <span class="font-mono font-bold text-white">${{ number_format($costoEnvio, 0, ',', '.') }}</span>
                                </div>
                            @endif

                            @if($descuento > 0)
                                <div class="flex justify-between text-[#2eb8b4]">
                                    <span>Descuento aplicado:</span>
                                    <span class="font-mono font-bold">-${{ number_format($descuento, 0, ',', '.') }}</span>
                                </div>
                            @endif

                            @if($descuentoPuntos > 0)
                                <div class="flex justify-between text-[#f59e0b]">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">loyalty</span>
                                        Fidelización ({{ $puntosCanjeados }} pts)
                                    </span>
                                    <span class="font-mono font-bold">-${{ number_format($descuentoPuntos, 0, ',', '.') }}</span>
                                </div>
                            @elseif($clienteId && $puntosDisponibles > 0 && count($carrito) > 0)
                                @php $ptsCanje = min($puntosDisponibles, (int)floor($this->subtotal / 10)); @endphp
                                @if($ptsCanje > 0)
                                    <button
                                        wire:click="canjearPuntos({{ $ptsCanje }})"
                                        class="w-full py-1.5 px-3 rounded-xl bg-[#f59e0b]/15 text-[#f59e0b] text-[11px] font-extrabold flex items-center justify-between border border-[#f59e0b]/30 hover:bg-[#f59e0b]/25 transition active:scale-95 cursor-pointer"
                                    >
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">loyalty</span>
                                            Canjear {{ $ptsCanje }} puntos de {{ $puntosDisponibles }}
                                        </span>
                                        <span>-${{ number_format($ptsCanje * 10, 0, ',', '.') }}</span>
                                    </button>
                                @endif
                            @endif

                            <div class="flex justify-between text-base font-black text-white pt-1.5 border-t border-dashed border-[#32231c]">
                                <span>Total a Pagar</span>
                                <span class="text-xl font-mono text-[#e0442e] font-black">${{ number_format($this->total, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <!-- BOTONES DE ACCIÓN GIGANTES -->
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            @if($this->comandaDespachadaPorCocina() && $this->cantidadNuevosItemsParaCocina() === 0)
                                <button 
                                    wire:click="iniciarNuevoPedido"
                                    type="button"
                                    class="h-11 rounded-xl bg-gradient-to-r from-[#251b16] to-[#1c1411] border border-[#3d2b22] text-[#e0442e] font-black text-xs flex items-center justify-center gap-1.5 hover:bg-[#2c201a] active:scale-95 transition cursor-pointer"
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
                                    class="h-11 rounded-xl bg-gradient-to-r from-[#10b981] to-[#059669] hover:from-[#059669] hover:to-[#047857] text-white font-black text-xs flex items-center justify-center gap-1.5 shadow-lg shadow-[#10b981]/20 active:scale-95 transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                    title="{{ $this->comandaYaEnviadaACocina() ? 'Comanda ya enviada a cocina.' : 'Enviar comanda a cocina' }}"
                                >
                                    <span class="material-symbols-outlined text-[18px]">{{ $this->comandaYaEnviadaACocina() ? 'check_circle' : 'send' }}</span>
                                    <span>{{ $this->comandaYaEnviadaACocina() ? '✓ En Cocina' : ($this->cantidadNuevosItemsParaCocina() > 0 && $this->obtenerPedidoActivoMesa() ? 'Enviar (+'.$this->cantidadNuevosItemsParaCocina().') Cocina' : 'Enviar a Cocina ('.count($carrito).')') }}</span>
                                </button>
                            @endif
                            @if (Auth::user()?->isMesero())
                                @if ($this->cobroEnviadoACaja())
                                    <button
                                        type="button"
                                        disabled
                                        class="h-11 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-300 font-black text-xs flex items-center justify-center gap-1.5 shadow-md cursor-not-allowed"
                                        title="La solicitud de cobro ya fue enviada a caja y está pendiente de pago por el cajero."
                                    >
                                        <span class="material-symbols-outlined text-[18px] animate-spin">hourglass_top</span>
                                        <span>⏳ Cobro Solicitado a Caja</span>
                                    </button>
                                @else
                                    <button
                                        wire:click="solicitarCobroCaja"
                                        type="button"
                                        @disabled((empty($carrito) && !$this->obtenerPedidoActivoMesa()) || ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) || $this->comandaActivaBloqueaCobro() || $this->esMesaDeOtroMesero())
                                        class="h-11 rounded-xl bg-gradient-to-r from-[#2eb8b4] to-[#1e8e8a] hover:brightness-110 text-white font-black text-xs flex items-center justify-center gap-1.5 shadow-lg active:scale-95 transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
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
                                class="h-11 rounded-xl bg-gradient-to-r from-[#e0442e] to-[#c73420] hover:from-[#c73420] hover:to-[#a82a18] text-white font-black text-xs flex items-center justify-center gap-1.5 shadow-lg shadow-[#e0442e]/25 active:scale-95 transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                title="{{ ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) ? 'Debes enviar la comanda a cocina antes de cobrar la mesa.' : ($this->comandaActivaBloqueaCobro() ? 'Comanda en preparación en cocina.' : ($this->esMesaDeOtroMesero() ? 'Mesa asignada a otro mesero.' : 'Cobrar Pedido')) }}"
                            >
                                <span class="material-symbols-outlined text-[18px]">{{ ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) ? 'skillet' : ($this->comandaActivaBloqueaCobro() ? 'hourglass_top' : ($this->esMesaDeOtroMesero() ? 'shield_person' : ($this->comandaListaParaCobrar() ? 'check_circle' : 'payments'))) }}</span>
                                <span>{{ ($tipo === 'mesa' && $this->comandaRequiereEnvioCocina()) ? 'Enviar a Cocina Primero' : ($this->comandaActivaBloqueaCobro() ? 'En Prep. Cocina' : ($this->esMesaDeOtroMesero() ? 'Mesa Otro Mesero' : ($this->comandaListaParaCobrar() ? '✓ Cobrar Listo' : 'Cobrar $' . number_format($this->total, 0, ',', '.')))) }}</span>
                            </button>
                            @endif
                        </div>

                        @if($this->cobroEnviadoACaja())
                            <div class="mt-1 flex items-center justify-center gap-1.5 rounded-xl bg-amber-500/15 border border-amber-500/40 px-2.5 py-1.5 text-[10px] font-bold text-amber-300 shadow-xs animate-pulse">
                                <span class="material-symbols-outlined text-[15px] text-amber-400 animate-spin">hourglass_top</span>
                                <span>Cobro solicitado a Caja · Esperando liquidación y cierre del ticket por el cajero</span>
                            </div>
                        @elseif($tipo === 'mesa' && $this->comandaRequiereEnvioCocina())
                            <div class="mt-1 flex items-center justify-center gap-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 px-2.5 py-1 text-[10px] font-bold text-amber-500">
                                <span class="material-symbols-outlined text-[14px]">skillet</span>
                                <span>⚠️ Comanda sin enviar: envía primero a cocina antes de cobrar</span>
                            </div>
                        @elseif($this->comandaActivaBloqueaCobro())
                            <div class="mt-1 flex items-center justify-center gap-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 px-2.5 py-1 text-[10px] font-bold text-amber-500 animate-pulse">
                                <span class="material-symbols-outlined text-[14px] text-amber-500">hourglass_top</span>
                                <span>En preparación en cocina · Bloqueado hasta despacho</span>
                            </div>
                        @elseif($this->esMesaDeOtroMesero())
                            <div class="mt-1 flex items-center justify-center gap-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 px-2.5 py-1 text-[10px] font-bold text-amber-500">
                                <span class="material-symbols-outlined text-[14px]">shield_person</span>
                                <span>Mesa asignada a otro mesero · Cobro restringido</span>
                            </div>
                        @elseif($this->comandaListaParaCobrar())
                            <div class="mt-1 flex items-center justify-center gap-1.5 rounded-xl bg-[#10b981]/15 border border-[#10b981]/30 px-2.5 py-1 text-[10px] font-bold text-[#10b981]">
                                <span class="material-symbols-outlined text-[14px]">notifications_active</span>
                                <span>¡Comanda lista en cocina! Habilitado para servir y cobrar</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
