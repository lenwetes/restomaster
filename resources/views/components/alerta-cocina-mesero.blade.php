@auth
<div x-data="alertaMeseroHub({{ auth()->id() }}, {{ auth()->user()->sucursal_id ?? 1 }}, '{{ auth()->user()->role?->slug ?? 'mesero' }}')"
     x-init="init()"
     class="no-print">
    <!-- Banner Flotante de Alta Visibilidad para Mesero y Cajero (No Bloqueante) -->
    <div x-show="alertas.length > 0"
         x-transition:enter="transform transition ease-out duration-300"
         x-transition:enter-start="translate-y-8 opacity-0 scale-95"
         x-transition:enter-end="translate-y-0 opacity-100 scale-100"
         x-transition:leave="transform transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 opacity-100 scale-100"
         x-transition:leave-end="translate-y-8 opacity-0 scale-95"
         class="fixed bottom-6 right-6 z-[9999] w-[440px] max-w-[calc(100vw-2rem)] pointer-events-auto"
         style="display: none;">
        
        <template x-if="alertaActual">
            <div class="relative overflow-hidden rounded-2xl bg-slate-950/95 backdrop-blur-xl border-2 transition-all duration-300 text-white"
                 :class="alertaActual.tipo === 'solicitud_cobro' 
                    ? 'border-emerald-500/80 shadow-[0_0_35px_rgba(16,185,129,0.35)] ring-4 ring-emerald-500/20' 
                    : 'border-amber-500/80 shadow-[0_0_35px_rgba(245,158,11,0.35)] ring-4 ring-amber-500/20'">
                <!-- Barra superior de urgencia pulsante -->
                <div class="h-2 w-full animate-pulse"
                     :class="alertaActual.tipo === 'solicitud_cobro' 
                        ? 'bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-400' 
                        : 'bg-gradient-to-r from-amber-500 via-orange-500 to-amber-400'"></div>
                
                <div class="p-5">
                    <!-- Cabecera de Alerta -->
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 animate-bounce"
                                 :class="alertaActual.tipo === 'solicitud_cobro' 
                                    ? 'bg-emerald-500/20 border border-emerald-500/40 text-emerald-400' 
                                    : 'bg-amber-500/20 border border-amber-500/40 text-amber-400'">
                                <span class="material-symbols-outlined text-2xl" 
                                      x-text="alertaActual.tipo === 'solicitud_cobro' ? 'point_of_sale' : 'notifications_active'"></span>
                            </div>
                            <div>
                                <span class="text-[11px] font-black uppercase tracking-wider block leading-tight"
                                      :class="alertaActual.tipo === 'solicitud_cobro' ? 'text-emerald-400' : 'text-amber-400'"
                                      x-text="alertaActual.tipo === 'solicitud_cobro' ? '¡Cobro enviado a caja!' : '¡Comanda lista en cocina!'">
                                </span>
                                <span class="text-xs text-slate-300 font-mono" x-text="alertaActual.tiempoTranscurrido"></span>
                            </div>
                        </div>

                        <!-- Badge de Mesa -->
                        <div class="font-black px-3 py-1 rounded-xl text-xs uppercase tracking-wider shadow-sm flex items-center gap-1 shrink-0 text-slate-950"
                             :class="alertaActual.tipo === 'solicitud_cobro' ? 'bg-emerald-400' : 'bg-amber-500'">
                            <span class="material-symbols-outlined text-sm">table_restaurant</span>
                            <span x-text="alertaActual.mesa_numero ? 'Mesa #' + alertaActual.mesa_numero : (alertaActual.pedido_codigo || 'Cobro Salón')"></span>
                        </div>
                    </div>

                    <!-- Contenido de la alerta -->
                    <div class="bg-slate-900/90 rounded-xl p-3.5 border border-slate-800/80 mb-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <!-- Vista si es cobro a caja -->
                                <template x-if="alertaActual.tipo === 'solicitud_cobro'">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-lg bg-emerald-500/20 text-emerald-300 font-mono font-bold text-xs" 
                                                  x-text="alertaActual.pedido_codigo || 'Cobro'"></span>
                                            <h4 class="font-bold text-sm text-slate-100 tracking-tight leading-snug" x-text="alertaActual.nombre_producto"></h4>
                                        </div>
                                        <template x-if="alertaActual.notas">
                                            <p class="text-xs text-emerald-300 font-mono font-semibold mt-1.5 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-xs">payments</span>
                                                <span x-text="alertaActual.notas"></span>
                                            </p>
                                        </template>
                                    </div>
                                </template>

                                <!-- Vista si es comanda de cocina -->
                                <template x-if="alertaActual.tipo !== 'solicitud_cobro'">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-amber-500/20 text-amber-300 font-mono font-bold text-xs" 
                                                  x-text="alertaActual.cantidad + 'x'"></span>
                                            <h4 class="font-bold text-base text-slate-100 tracking-tight leading-snug" x-text="alertaActual.nombre_producto"></h4>
                                        </div>
                                        <template x-if="alertaActual.notas">
                                            <p class="text-xs text-amber-300/90 italic mt-1.5 flex items-center gap-1 pl-8">
                                                <span class="material-symbols-outlined text-xs">edit_note</span>
                                                <span x-text="alertaActual.notas"></span>
                                            </p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <template x-if="alertaActual.mesa_zona">
                                <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-800 text-slate-400 border border-slate-700/60 shrink-0"
                                      x-text="alertaActual.mesa_zona"></span>
                            </template>
                        </div>
                    </div>

                    <!-- Cola si hay más de 1 alerta acumulada -->
                    <template x-if="alertas.length > 1">
                        <div class="flex items-center justify-between text-xs text-slate-400 mb-3 px-1">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full animate-ping"
                                      :class="alertaActual.tipo === 'solicitud_cobro' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                                <span>Hay <strong :class="alertaActual.tipo === 'solicitud_cobro' ? 'text-emerald-300' : 'text-amber-300'" x-text="alertas.length"></strong> avisos pendientes</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="alertaAnterior()" class="p-1 rounded hover:bg-slate-800 text-slate-300 hover:text-white transition-colors" title="Anterior">
                                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                                </button>
                                <span class="font-mono text-[11px] font-bold text-slate-300" x-text="(indiceActual + 1) + ' / ' + alertas.length"></span>
                                <button type="button" @click="alertaSiguiente()" class="p-1 rounded hover:bg-slate-800 text-slate-300 hover:text-white transition-colors" title="Siguiente">
                                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Botones de Acción -->
                    <!-- CASO 1: Solicitud de Cobro enviada a Caja -->
                    <template x-if="alertaActual.tipo === 'solicitud_cobro'">
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="procesarCobroInmediato(alertaActual)"
                                    class="flex-1 py-3.5 px-4 bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transform active:scale-[0.98] transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-base font-bold">point_of_sale</span>
                                <span>Procesar cobro de inmediato</span>
                            </button>
                            <button type="button"
                                    @click="confirmarActual()"
                                    class="py-3.5 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold text-xs rounded-xl border border-slate-700 transition-all cursor-pointer whitespace-nowrap"
                                    title="Descartar aviso">
                                <span class="material-symbols-outlined text-sm">close</span>
                            </button>
                        </div>
                    </template>

                    <!-- CASO 2: Comanda de Cocina para Mesero -->
                    <template x-if="alertaActual.tipo !== 'solicitud_cobro'">
                        <div class="flex gap-2">
                            <template x-if="alertaActual.ticket_url">
                                <a :href="alertaActual.ticket_url"
                                   class="py-3.5 px-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-lg flex items-center justify-center gap-1.5 whitespace-nowrap min-h-[44px]">
                                    <span class="material-symbols-outlined text-base font-bold">receipt_long</span>
                                    <span>Ver ticket</span>
                                </a>
                            </template>
                            <button type="button"
                                    @click="confirmarActual()"
                                    class="flex-1 py-3.5 px-4 bg-gradient-to-r from-amber-500 via-amber-400 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-amber-500/20 flex items-center justify-center gap-2 transform active:scale-[0.98] transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-base font-bold">check_circle</span>
                                <span>✓ Entendido, voy a servir</span>
                            </button>
                            <template x-if="alertas.length > 1">
                                <button type="button"
                                        @click="confirmarTodas()"
                                        class="py-3.5 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold text-xs rounded-xl border border-slate-700 transition-all cursor-pointer whitespace-nowrap"
                                        title="Confirmar y llevar todos">
                                    <span>Llevar todos</span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function alertaMeseroHub(userId, sucursalId, userRole) {
    return {
        userId: userId,
        sucursalId: sucursalId,
        userRole: userRole,
        alertas: [],
        indiceActual: 0,
        timerTicker: null,

        get alertaActual() {
            if (this.alertas.length === 0) return null;
            if (this.indiceActual >= this.alertas.length) this.indiceActual = 0;
            return this.alertas[this.indiceActual];
        },

        isChecking: false,

        init() {
            this.conectarCanales();
            this.iniciarTemporizador();
            this.iniciarSondeoHttp();
        },

        isEchoConnected() {
            try {
                return !!(window.Echo &&
                    window.Echo.connector &&
                    window.Echo.connector.pusher &&
                    window.Echo.connector.pusher.connection &&
                    window.Echo.connector.pusher.connection.state === 'connected');
            } catch (e) {
                return false;
            }
        },

        iniciarSondeoHttp() {
            // Consulta inicial al cargar la página para recuperar pendientes
            this.consultarNotificacionesPendientes();

            // Sondeo pasivo de respaldo: SOLO se ejecuta si WebSockets está caído/desconectado
            // y la pestaña está visible. Si WebSockets está activo, el sondeo HTTP se suprime al 100%.
            setInterval(() => {
                if (this.isEchoConnected()) {
                    return;
                }

                if (document.hidden) {
                    return;
                }

                this.consultarNotificacionesPendientes();
            }, 20000);
        },

        async consultarNotificacionesPendientes() {
            if (this.isChecking) return;
            this.isChecking = true;

            try {
                const res = await fetch('/notificaciones/pendientes', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                if (Array.isArray(data)) {
                    data.forEach(n => {
                        const datos = n.datos || {};
                        const esCobro = n.tipo === 'solicitud_cobro';
                        const yaExiste = this.alertas.some(a => 
                            a.notif_db_id === n.id || 
                            (!esCobro && a.item_id && a.item_id === datos.item_id) ||
                            (esCobro && a.pedido_id && a.pedido_id === datos.pedido_id)
                        );

                        if (!yaExiste) {
                            this.recibirAlerta({
                                notif_db_id: n.id,
                                tipo: n.tipo || 'comanda_cocina',
                                item_id: datos.item_id,
                                pedido_id: datos.pedido_id,
                                pedido_codigo: datos.pedido_codigo || ('Pedido #' + datos.pedido_id),
                                nombre_producto: n.cuerpo || n.titulo,
                                cantidad: datos.cantidad || 1,
                                mesa_numero: datos.mesa_numero,
                                mesa_zona: datos.mesa_zona,
                                notas: datos.notas || (esCobro && datos.total ? `Total: $${Number(datos.total).toLocaleString('es-CO')}` : null),
                                ticket_url: datos.ticket_url || (esCobro && datos.pedido_id ? `/caja?cobro_id=${datos.pedido_id}` : null)
                            });
                        }
                    });
                }
            } catch (err) {
            } finally {
                this.isChecking = false;
            }
        },

        conectarCanales() {
            if (!window.Echo) {
                return;
            }

            // Canal privado del mesero: platos listos + pagos procesados
            if (this.userId) {
                window.Echo.private(`mesero.${this.userId}`)
                    .listen('.item.listo', (data) => {
                        this.recibirAlerta(data);
                    })
                    .listen('.pago.procesado', (data) => {
                        this.recibirAlertaPago(data);
                    });
            }

            // Si es admin o gerente, también escuchar canal general de cocina por respaldo
            if (['admin', 'gerente'].includes(this.userRole)) {
                window.Echo.private(`cocina.${this.sucursalId}`)
                    .listen('.item.listo', (data) => {
                        if (!this.alertas.some(a => a.item_id === data.item_id)) {
                            this.recibirAlerta(data);
                        }
                    });
            }

            // Canal de caja: solicitudes de cobro en tiempo real para cajeros, gerentes y admin
            if (['cajero', 'admin', 'gerente'].includes(this.userRole)) {
                window.Echo.private(`caja.${this.sucursalId}`)
                    .listen('.solicitud.cobro', (data) => {
                        this.recibirAlertaSolicitudCobro(data);
                    });
            }
        },

        recibirAlerta(data) {
            const esCobro = data.tipo === 'solicitud_cobro';
            const nuevaAlerta = {
                id: 'alerta_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                notif_db_id: data.notif_db_id || null,
                tipo: data.tipo || 'comanda_cocina',
                item_id: data.item_id,
                pedido_id: data.pedido_id,
                pedido_codigo: data.pedido_codigo,
                nombre_producto: data.nombre_producto,
                cantidad: data.cantidad || 1,
                mesa_numero: data.mesa_numero,
                mesa_zona: data.mesa_zona,
                notas: data.notas,
                ticket_url: data.ticket_url || null,
                recibidoEn: Date.now(),
                tiempoTranscurrido: 'Hace un momento'
            };

            this.alertas.unshift(nuevaAlerta);
            this.indiceActual = 0;

            // Reproducir timbre correspondiente
            if (esCobro && typeof window.sonarCampanaCaja === 'function') {
                window.sonarCampanaCaja();
            } else if (typeof window.sonarCampanaCocina === 'function') {
                window.sonarCampanaCocina();
            }

            // Vibración en dispositivos táctiles si soportado
            if ('vibrate' in navigator) {
                try { navigator.vibrate([200, 100, 200]); } catch (e) {}
            }
        },

        recibirAlertaSolicitudCobro(data) {
            const pedidoId = data.pedidoId || data.pedido_id;
            if (this.alertas.some(a => a.tipo === 'solicitud_cobro' && a.pedido_id === pedidoId)) {
                return;
            }

            const mesaTexto = data.mesa || '';
            const numero = (mesaTexto.match(/\d+/) || [null])[0];
            const mesero = data.meseroNombre || data.mesero || 'Mesero';
            const codigo = data.codigo || ('Pedido #' + pedidoId);
            const total = data.total ? Number(data.total).toLocaleString('es-CO') : '0';

            this.recibirAlerta({
                tipo: 'solicitud_cobro',
                pedido_id: pedidoId,
                pedido_codigo: codigo,
                nombre_producto: `${mesero} solicita cobro de ${codigo}`,
                cantidad: 1,
                mesa_numero: numero || (mesaTexto.replace(/mesa\s*/i, '') || 'Salón'),
                mesa_zona: mesaTexto,
                notas: `Total a cobrar: $${total}`,
                ticket_url: `/caja?cobro_id=${pedidoId}`
            });
        },

        recibirAlertaPago(data) {
            const mesaTexto = data.mesa || '';
            const numero = (mesaTexto.match(/\d+/) || [null])[0];
            this.recibirAlerta({
                tipo: 'pago_procesado',
                pedido_id: data.pedidoId,
                nombre_producto: data.mensaje || 'Caja procesó el pago',
                cantidad: 1,
                mesa_numero: numero || mesaTexto,
                notas: (data.total ? '$' + Number(data.total).toLocaleString('es-CO') + ' · ' : '') + (data.metodoPago || ''),
                ticket_url: data.ticketUrl || null
            });
        },

        async procesarCobroInmediato(alerta) {
            if (!alerta) return;
            const pedidoId = alerta.pedido_id;

            // 1. Marcar como leída en backend
            if (alerta.notif_db_id) {
                try {
                    await fetch(`/notificaciones/${alerta.notif_db_id}/marcar-leida`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    });
                } catch (e) {}
            }

            // 2. Remover de alertas activas
            this.alertas.splice(this.indiceActual, 1);
            if (this.indiceActual >= this.alertas.length) {
                this.indiceActual = Math.max(0, this.alertas.length - 1);
            }

            // 3. Enviar inmediatamente al cobro notificado
            if (pedidoId) {
                if (window.location.pathname.startsWith('/caja')) {
                    if (window.Livewire) {
                        window.Livewire.dispatch('abrir-modal-cobro-unificado', { pedidoId: pedidoId });
                    } else {
                        window.location.href = `/caja?cobro_id=${pedidoId}`;
                    }
                } else {
                    window.location.href = `/caja?cobro_id=${pedidoId}`;
                }
            }
        },

        async confirmarActual() {
            if (this.alertas.length === 0) return;
            const alerta = this.alertas[this.indiceActual];
            if (alerta && alerta.notif_db_id) {
                try {
                    await fetch(`/notificaciones/${alerta.notif_db_id}/marcar-leida`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    });
                } catch (e) {}
            }
            this.alertas.splice(this.indiceActual, 1);
            if (this.indiceActual >= this.alertas.length) {
                this.indiceActual = Math.max(0, this.alertas.length - 1);
            }
        },

        async confirmarTodas() {
            for (const alerta of this.alertas) {
                if (alerta && alerta.notif_db_id) {
                    try {
                        fetch(`/notificaciones/${alerta.notif_db_id}/marcar-leida`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                    } catch (e) {}
                }
            }
            this.alertas = [];
            this.indiceActual = 0;
        },

        alertaAnterior() {
            if (this.indiceActual > 0) {
                this.indiceActual--;
            } else {
                this.indiceActual = this.alertas.length - 1;
            }
        },

        alertaSiguiente() {
            if (this.indiceActual < this.alertas.length - 1) {
                this.indiceActual++;
            } else {
                this.indiceActual = 0;
            }
        },

        iniciarTemporizador() {
            if (this.timerTicker) clearInterval(this.timerTicker);
            this.timerTicker = setInterval(() => {
                const ahora = Date.now();
                this.alertas.forEach(a => {
                    const segundos = Math.floor((ahora - a.recibidoEn) / 1000);
                    if (segundos < 60) {
                        a.tiempoTranscurrido = `Hace ${segundos}s`;
                    } else {
                        const minutos = Math.floor(segundos / 60);
                        a.tiempoTranscurrido = `Hace ${minutos}m ${segundos % 60}s`;
                    }
                });
            }, 1000);
        }
    };
}
</script>
@endauth
