@if ($modalReponer && $reponerDatos)
    <div 
        x-data="{ 
            copiado: false,
            pestana: @entangle('reponerPestana'),
            cantidad: @entangle('reponerCantidad'),
            costo: @entangle('reponerCostoUnitario'),
            fuentePago: @entangle('reponerFuentePago'),
            get totalCalculado() {
                const c = parseFloat(this.cantidad) || 0;
                const u = parseFloat(this.costo) || 0;
                return Math.round(c * u);
            },
            formatDinero(val) {
                return '$' + new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(val) + ' COP';
            },
            copiarTexto(txt) {
                if (!txt) return;
                navigator.clipboard.writeText(txt).then(() => {
                    this.copiado = true;
                    setTimeout(() => { this.copiado = false; }, 2500);
                });
            }
        }"
        x-on:keydown.escape.window="$wire.cerrarModalReponer()"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-xs animate-in fade-in duration-200"
    >
        <div 
            class="relative w-full max-w-2xl bg-surface-container-lowest border border-surface-container-highest rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden"
            @click.outside="$wire.cerrarModalReponer()"
        >
            <!-- CABECERA DEL MODAL -->
            <div class="p-5 sm:p-6 pb-4 border-b border-surface-container-high bg-surface-container-low/40 flex items-start justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="h-12 w-12 rounded-2xl flex items-center justify-center shrink-0 {{ $reponerDatos['stock_actual'] <= 0 ? 'bg-rose-500/15 text-rose-600 border border-rose-500/25' : 'bg-amber-500/15 text-amber-600 border border-amber-500/25' }}">
                        <span class="material-symbols-outlined text-[26px]">
                            {{ $reponerDatos['stock_actual'] <= 0 ? 'report' : 'inventory_2' }}
                        </span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base sm:text-lg font-black text-on-surface tracking-tight">
                                Reposición de Insumo
                            </h3>
                            @if ($reponerDatos['stock_actual'] <= 0)
                                <span class="px-2.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-black uppercase tracking-wider">
                                    Agotado
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-700 text-[10px] font-black uppercase border border-amber-500/30">
                                    Stock Bajo
                                </span>
                            @endif
                        </div>
                        <p class="text-sm font-bold text-primary mt-0.5">
                            {{ $reponerDatos['insumo_nombre'] ?? ($reponerDatos['insumo']->nombre ?? '') }}
                        </p>
                        <div class="flex items-center gap-3 text-xs text-on-surface-variant mt-1 font-mono">
                            <span>Actual: <strong class="{{ $reponerDatos['stock_actual'] <= 0 ? 'text-rose-600' : 'text-amber-700' }}">{{ $reponerDatos['stock_actual'] }} {{ $reponerDatos['unidad_medida'] }}</strong></span>
                            <span>•</span>
                            <span>Mínimo: <strong>{{ $reponerDatos['stock_minimo'] }} {{ $reponerDatos['unidad_medida'] }}</strong></span>
                            <span>•</span>
                            <span class="text-emerald-700 font-bold">Déficit sugerido: +{{ $reponerDatos['cantidad_sugerida'] }} {{ $reponerDatos['unidad_medida'] }}</span>
                        </div>
                    </div>
                </div>

                <button 
                    type="button" 
                    wire:click="cerrarModalReponer"
                    class="h-9 w-9 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface-variant hover:text-on-surface flex items-center justify-center transition-colors"
                >
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- CUERPO DEL MODAL (SCROLLABLE) -->
            <div class="p-5 sm:p-6 space-y-5 overflow-y-auto flex-1">
                <!-- BANNERS DE FEEDBACK -->
                @if ($reponerMensajeExito)
                    <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 text-xs font-bold flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
                        <span>{{ $reponerMensajeExito }}</span>
                    </div>
                @endif

                @if ($reponerMensajeError)
                    <div class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-800 text-xs font-bold flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[18px] text-rose-600">error</span>
                        <span>{{ $reponerMensajeError }}</span>
                    </div>
                @endif

                <!-- BLOQUE PROVEEDOR ASIGNADO / SELECCIONADO -->
                <div class="p-4 rounded-2xl bg-surface-container-low border border-surface-container-high space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-extrabold uppercase tracking-wider text-on-surface flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-primary">local_shipping</span>
                            Proveedor Asignado
                        </label>
                        <button 
                            type="button"
                            wire:click="$toggle('reponerMostrarCrearProveedor')"
                            class="text-xs font-bold text-primary hover:underline flex items-center gap-1"
                        >
                            <span class="material-symbols-outlined text-[15px]">
                                {{ $reponerMostrarCrearProveedor ? 'close' : 'add_circle' }}
                            </span>
                            <span>{{ $reponerMostrarCrearProveedor ? 'Cancelar' : '+ Nuevo Proveedor Rápido' }}</span>
                        </button>
                    </div>

                    <!-- Si está en modo crear nuevo proveedor -->
                    @if ($reponerMostrarCrearProveedor)
                        <div class="p-3.5 rounded-xl bg-surface-container-lowest border border-primary/30 space-y-3">
                            <p class="text-xs font-bold text-primary flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">domain_add</span>
                                Registrar Proveedor Rápido
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div>
                                    <label class="text-[11px] font-bold text-on-surface-variant block mb-1">Nombre o Empresa *</label>
                                    <input 
                                        type="text" 
                                        wire:model="reponerNuevoProveedor.nombre"
                                        placeholder="Ej. Distribuidora San Martín"
                                        class="w-full h-9 px-3 rounded-lg bg-surface-container-low border border-surface-container-high text-xs text-on-surface outline-none focus:border-primary"
                                    />
                                    @error('reponerNuevoProveedor.nombre') <span class="text-[10px] text-rose-600 font-bold">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-on-surface-variant block mb-1">Teléfono / WhatsApp</label>
                                    <input 
                                        type="text" 
                                        wire:model="reponerNuevoProveedor.telefono"
                                        placeholder="Ej. 3001234567"
                                        class="w-full h-9 px-3 rounded-lg bg-surface-container-low border border-surface-container-high text-xs text-on-surface outline-none focus:border-primary"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-on-surface-variant block mb-1">Correo Electrónico</label>
                                    <input 
                                        type="email" 
                                        wire:model="reponerNuevoProveedor.email"
                                        placeholder="ventas@proveedor.com"
                                        class="w-full h-9 px-3 rounded-lg bg-surface-container-low border border-surface-container-high text-xs text-on-surface outline-none focus:border-primary"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-on-surface-variant block mb-1">NIT / Cédula</label>
                                    <input 
                                        type="text" 
                                        wire:model="reponerNuevoProveedor.nit"
                                        placeholder="900.123.456-7"
                                        class="w-full h-9 px-3 rounded-lg bg-surface-container-low border border-surface-container-high text-xs text-on-surface outline-none focus:border-primary"
                                    />
                                </div>
                            </div>
                            <div class="flex justify-end pt-1">
                                <button 
                                    type="button" 
                                    wire:click="guardarNuevoProveedorReponer"
                                    class="px-3.5 py-1.5 rounded-lg bg-primary hover:bg-primary-hover text-on-primary text-xs font-bold flex items-center gap-1.5 shadow-sm transition-all"
                                >
                                    <span class="material-symbols-outlined text-[15px]">save</span>
                                    <span>Guardar y Vincular</span>
                                </button>
                            </div>
                        </div>
                    @else
                        <!-- Selector de proveedor existente -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <select 
                                wire:model.live="reponerProveedorId"
                                class="h-10 px-3 rounded-xl bg-surface-container-lowest border border-surface-container-high text-xs font-bold text-on-surface outline-none focus:border-primary flex-1"
                            >
                                <option value="">-- Sin proveedor asignado (Comprar Independiente) --</option>
                                @foreach ($reponerDatos['proveedores_disponibles'] as $prov)
                                    @php
                                        $provId = is_array($prov) ? $prov['id'] : $prov->id;
                                        $provNombre = is_array($prov) ? $prov['nombre'] : $prov->nombre;
                                        $provTel = is_array($prov) ? ($prov['telefono'] ?? null) : $prov->telefono;
                                    @endphp
                                    <option value="{{ $provId }}">
                                        {{ $provNombre }} {{ $provTel ? '· Tel: ' . $provTel : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Ficha rápida del proveedor actual -->
                        @if ($reponerEnlacesGenerados)
                            <div class="p-2.5 rounded-xl bg-surface-container-lowest/80 border border-surface-container-high flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    <span class="font-bold text-on-surface">Contacto Directo:</span>
                                    <span class="text-on-surface-variant font-mono">
                                        {{ $reponerEnlacesGenerados['telefono'] ? '+' . $reponerEnlacesGenerados['telefono'] : 'Sin teléfono' }}
                                    </span>
                                </div>
                                <span class="text-on-surface-variant text-[11px]">
                                    {{ $reponerEnlacesGenerados['email'] ?: 'Sin email' }}
                                </span>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- TABS DE OPERACIÓN: PEDIR AL PROVEEDOR vs INGRESO DIRECTO -->
                <div class="flex p-1 rounded-2xl bg-surface-container-low border border-surface-container-high">
                    <button 
                        type="button" 
                        @click="pestana = 'solicitar'"
                        class="flex-1 py-2 px-3 rounded-xl text-xs font-black flex items-center justify-center gap-2 transition-all cursor-pointer"
                        :class="pestana === 'solicitar' ? 'bg-surface-container-lowest text-primary shadow-sm border border-surface-container-high' : 'text-on-surface-variant hover:text-on-surface'"
                    >
                        <span class="material-symbols-outlined text-[17px]">send</span>
                        <span>1. Solicitar Pedido al Proveedor</span>
                    </button>
                    <button 
                        type="button" 
                        @click="pestana = 'ingresar'"
                        class="flex-1 py-2 px-3 rounded-xl text-xs font-black flex items-center justify-center gap-2 transition-all cursor-pointer"
                        :class="pestana === 'ingresar' ? 'bg-surface-container-lowest text-emerald-700 shadow-sm border border-surface-container-high' : 'text-on-surface-variant hover:text-on-surface'"
                    >
                        <span class="material-symbols-outlined text-[17px]">inventory</span>
                        <span>2. Ingreso Inmediato a Stock</span>
                    </button>
                </div>

                <!-- CONTENIDO PESTAÑA 1: SOLICITAR A PROVEEDOR -->
                <div x-show="pestana === 'solicitar'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-extrabold text-on-surface block mb-1">
                                    Cantidad a Solicitar ({{ $reponerDatos['unidad_medida'] }}) *
                                </label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    min="0.1" 
                                    x-model="cantidad"
                                    @change="$wire.actualizarEnlacesPedido()"
                                    class="w-full h-11 px-3.5 rounded-xl bg-surface-container-low border border-surface-container-high text-sm font-mono font-bold text-on-surface outline-none focus:border-primary"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-extrabold text-on-surface block mb-1">
                                    Costo Estimado COP / {{ $reponerDatos['unidad_medida'] }}
                                </label>
                                <input 
                                    type="number" 
                                    step="100" 
                                    x-model="costo"
                                    class="w-full h-11 px-3.5 rounded-xl bg-surface-container-low border border-surface-container-high text-sm font-mono text-on-surface outline-none focus:border-primary"
                                />
                            </div>
                        </div>

                        <!-- Total estimado de la orden en tiempo real -->
                        <div class="p-2.5 px-3.5 rounded-xl bg-surface-container-low/70 border border-surface-container-high flex items-center justify-between text-xs">
                            <span class="text-on-surface-variant font-medium">Inversión estimada de la orden:</span>
                            <span class="font-mono font-black text-primary text-sm" x-text="formatDinero(totalCalculado)">
                                ${{ number_format((float) $reponerCantidad * (float) $reponerCostoUnitario, 0, ',', '.') }} COP
                            </span>
                        </div>

                        <!-- Previsualización del mensaje -->
                        @if ($reponerEnlacesGenerados)
                            <div class="p-3.5 rounded-2xl bg-surface-container-low/70 border border-surface-container-high space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-black uppercase text-on-surface-variant flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">chat</span>
                                        Mensaje preparado para el proveedor:
                                    </span>
                                    <button 
                                        type="button"
                                        @click="copiarTexto(@js($reponerEnlacesGenerados['texto']))"
                                        class="text-xs font-bold text-primary flex items-center gap-1 hover:underline"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        <span x-text="copiado ? '¡Copiado!' : 'Copiar texto'"></span>
                                    </button>
                                </div>
                                <div class="p-3 rounded-xl bg-surface-container-lowest border border-surface-container-high text-xs text-on-surface whitespace-pre-wrap font-sans leading-relaxed">
                                    {{ $reponerEnlacesGenerados['texto'] }}
                                </div>
                            </div>

                            <!-- Botonera de Despacho Rápido -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                                @if ($reponerEnlacesGenerados['url_whatsapp'])
                                    <a 
                                        href="{{ $reponerEnlacesGenerados['url_whatsapp'] }}" 
                                        target="_blank"
                                        class="h-11 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-transform active:scale-95"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">phone_iphone</span>
                                        <span>Enviar WhatsApp</span>
                                    </a>
                                @else
                                    <button 
                                        type="button" 
                                        disabled
                                        class="h-11 px-4 rounded-xl bg-surface-container-high text-on-surface-variant/50 font-bold text-xs flex items-center justify-center gap-2 cursor-not-allowed"
                                        title="El proveedor no tiene teléfono registrado"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">phone_iphone</span>
                                        <span>Sin WhatsApp</span>
                                    </button>
                                @endif

                                @if ($reponerEnlacesGenerados['url_email'])
                                    <a 
                                        href="{{ $reponerEnlacesGenerados['url_email'] }}" 
                                        class="h-11 px-4 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-transform active:scale-95"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">mail</span>
                                        <span>Enviar Correo</span>
                                    </a>
                                @else
                                    <button 
                                        type="button" 
                                        disabled
                                        class="h-11 px-4 rounded-xl bg-surface-container-high text-on-surface-variant/50 font-bold text-xs flex items-center justify-center gap-2 cursor-not-allowed"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">mail</span>
                                        <span>Sin Correo</span>
                                    </button>
                                @endif

                                <button 
                                    type="button" 
                                    wire:click="solicitarPedidoProveedor"
                                    class="h-11 px-4 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-bold text-xs flex items-center justify-center gap-2 border border-surface-container-highest transition-colors"
                                >
                                    <span class="material-symbols-outlined text-[18px] text-amber-600">assignment</span>
                                    <span>Guardar Orden</span>
                                </button>
                            </div>
                        @else
                            <div class="py-6 text-center text-xs text-on-surface-variant bg-surface-container-low rounded-2xl">
                                Selecciona un proveedor para generar los canales directos de WhatsApp y Correo.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- CONTENIDO PESTAÑA 2: INGRESO INMEDIATO A STOCK -->
                <div x-show="pestana === 'ingresar'" x-cloak class="space-y-4">
                        <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-900 flex items-start gap-2.5">
                            <span class="material-symbols-outlined text-[18px] text-emerald-600 shrink-0 mt-0.5">verified</span>
                            <span>
                                Usa esta opción si el proveedor <strong>ya entregó el insumo</strong> al restaurante o si <strong>lo compraste de forma independiente</strong> en el supermercado/mayorista. El stock se sumará de inmediato al Kardex.
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-extrabold text-on-surface block mb-1">
                                    Cantidad que Ingresa ({{ $reponerDatos['unidad_medida'] }}) *
                                </label>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    min="0.01" 
                                    x-model="cantidad"
                                    class="w-full h-11 px-3.5 rounded-xl bg-surface-container-low border border-surface-container-high text-sm font-mono font-black text-emerald-700 outline-none focus:border-emerald-600"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-extrabold text-on-surface block mb-1">
                                    Costo Unitario COP (por {{ $reponerDatos['unidad_medida'] }}) *
                                </label>
                                <input 
                                    type="number" 
                                    step="100" 
                                    min="0" 
                                    x-model="costo"
                                    class="w-full h-11 px-3.5 rounded-xl bg-surface-container-low border border-surface-container-high text-sm font-mono font-bold text-on-surface outline-none focus:border-primary"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-extrabold text-on-surface block mb-1">
                                    Fuente de Pago / Origen del Dinero
                                </label>
                                <select 
                                    x-model="fuentePago"
                                    class="w-full h-11 px-3 rounded-xl bg-surface-container-low border border-surface-container-high text-xs font-bold text-on-surface outline-none focus:border-primary"
                                >
                                    <option value="externo">Pago Externo / Independiente (Sin afectar caja)</option>
                                    @if ($reponerDatos['hay_turno_caja_abierto'])
                                        <option value="caja_menor">💵 Caja Menor (Egreso automático en Turno Abierto)</option>
                                    @endif
                                    @if ($reponerProveedorId)
                                        <option value="credito">🧾 Cuenta por Pagar al Proveedor (Crédito)</option>
                                    @endif
                                </select>
                                <div x-show="fuentePago === 'caja_menor'">
                                    <p class="text-[11px] text-amber-700 mt-1 font-medium">
                                        ⚡ Se registrará un comprobante de egreso por <span class="font-bold" x-text="formatDinero(totalCalculado)">${{ number_format((float) $reponerCantidad * (float) $reponerCostoUnitario, 0, ',', '.') }} COP</span> en la caja actual.
                                    </p>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-extrabold text-on-surface block mb-1">
                                    N° Factura / Remisión / Recibo (Opcional)
                                </label>
                                <input 
                                    type="text" 
                                    wire:model="reponerNumeroDocumento"
                                    placeholder="Ej. FAC-10294 o REC-SUPER-01"
                                    class="w-full h-11 px-3.5 rounded-xl bg-surface-container-low border border-surface-container-high text-xs text-on-surface outline-none focus:border-primary"
                                />
                            </div>
                        </div>

                        <!-- Resumen del ingreso -->
                        <div class="p-3.5 rounded-2xl bg-surface-container-low border border-surface-container-high flex items-center justify-between text-xs">
                            <span class="text-on-surface-variant font-bold">Total a pagar estimado:</span>
                            <span class="text-sm font-mono font-black text-on-surface" x-text="formatDinero(totalCalculado)">
                                ${{ number_format((float) $reponerCantidad * (float) $reponerCostoUnitario, 0, ',', '.') }} COP
                            </span>
                        </div>

                        <div class="pt-2">
                            <button 
                                type="button" 
                                wire:click="confirmarIngresoDirectoStock"
                                class="w-full h-12 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-950/20 transition-transform active:scale-98"
                            >
                                <span class="material-symbols-outlined text-[20px]">add_task</span>
                                <span>Confirmar Ingreso Inmediato al Inventario</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PIE DEL MODAL -->
            <div class="p-4 px-6 border-t border-surface-container-high bg-surface-container-low/50 flex items-center justify-between text-xs text-on-surface-variant">
                <span>RestoMaster Gastro OS · Kardex en tiempo real</span>
                <button 
                    type="button" 
                    wire:click="cerrarModalReponer"
                    class="px-4 py-1.5 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-bold transition-colors"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
@endif
