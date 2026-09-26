@auth
<div x-data="alertaMeseroHub({{ auth()->id() }}, {{ auth()->user()->sucursal_id ?? 1 }}, '{{ auth()->user()->role?->slug ?? 'mesero' }}')"
     x-init="init()"
     class="no-print">
    <!-- Banner Flotante de Alta Visibilidad para Mesero (No Bloqueante) -->
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
            <div class="relative overflow-hidden rounded-2xl bg-slate-950/95 backdrop-blur-xl border-2 border-amber-500/80 shadow-[0_0_35px_rgba(245,158,11,0.35)] ring-4 ring-amber-500/20 text-white">
                <!-- Barra superior de urgencia pulsante -->
                <div class="h-2 w-full bg-gradient-to-r from-amber-500 via-orange-500 to-amber-400 animate-pulse"></div>
                
                <div class="p-5">
                    <!-- Cabecera de Alerta -->
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center shrink-0 animate-bounce">
                                <span class="material-symbols-outlined text-2xl">notifications_active</span>
                            </div>
                            <div>
                                <span class="text-[11px] font-black uppercase tracking-wider text-amber-400 block leading-tight">
                                    ¡Comanda lista en cocina!
                                </span>
                                <span class="text-xs text-slate-300 font-mono" x-text="alertaActual.tiempoTranscurrido"></span>
                            </div>
                        </div>

                        <!-- Badge de Mesa -->
                        <div class="bg-amber-500 text-slate-950 font-black px-3 py-1 rounded-xl text-xs uppercase tracking-wider shadow-sm flex items-center gap-1 shrink-0">
                            <span class="material-symbols-outlined text-sm">table_restaurant</span>
                            <span x-text="alertaActual.mesa_numero ? 'Mesa #' + alertaActual.mesa_numero : (alertaActual.pedido_codigo || 'Para Llevar')"></span>
                        </div>
                    </div>

                    <!-- Contenido del plato listo -->
                    <div class="bg-slate-900/90 rounded-xl p-3.5 border border-slate-800/80 mb-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-amber-500/20 text-amber-300 font-mono font-bold text-xs" x-text="alertaActual.cantidad + 'x'"></span>
                                    <h4 class="font-bold text-base text-slate-100 tracking-tight leading-snug" x-text="alertaActual.nombre_producto"></h4>
                                </div>
                                <template x-if="alertaActual.notas">
                                    <p class="text-xs text-amber-300/90 italic mt-1.5 flex items-center gap-1 pl-8">
                                        <span class="material-symbols-outlined text-xs">edit_note</span>
                                        <span x-text="alertaActual.notas"></span>
                                    </p>
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
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                                <span>Hay <strong class="text-amber-300" x-text="alertas.length"></strong> platos pendientes por llevar</span>
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

                    <!-- Botón de Confirmación Mandatorio (Único método de cierre) -->
                    <div class="flex gap-2">
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

        init() {
            this.conectarCanales();
            this.iniciarTemporizador();
        },

        conectarCanales() {
            if (!window.Echo) {
                // Reintentar en 1s si Echo se carga asíncrono
                setTimeout(() => this.conectarCanales(), 1000);
                return;
            }

            // Canal privado del mesero
            if (this.userId) {
                window.Echo.private(`mesero.${this.userId}`)
                    .listen('.item.listo', (data) => {
                        this.recibirAlerta(data);
                    });
            }

            // Si es admin o gerente, también escuchar canal general de cocina por respaldo
            if (['admin', 'gerente'].includes(this.userRole)) {
                window.Echo.private(`cocina.${this.sucursalId}`)
                    .listen('.item.listo', (data) => {
                        // Evitar duplicados si ya vino por mesero
                        if (!this.alertas.some(a => a.item_id === data.item_id)) {
                            this.recibirAlerta(data);
                        }
                    });
            }
        },

        recibirAlerta(data) {
            const nuevaAlerta = {
                id: 'alerta_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                item_id: data.item_id,
                pedido_id: data.pedido_id,
                pedido_codigo: data.pedido_codigo,
                nombre_producto: data.nombre_producto,
                cantidad: data.cantidad || 1,
                mesa_numero: data.mesa_numero,
                mesa_zona: data.mesa_zona,
                notas: data.notas,
                recibidoEn: Date.now(),
                tiempoTranscurrido: 'Hace un momento'
            };

            this.alertas.unshift(nuevaAlerta);
            this.indiceActual = 0;

            // Reproducir sonido de atención
            if (typeof window.sonarCampanaCocina === 'function') {
                window.sonarCampanaCocina();
            }

            // Vibración en dispositivos táctiles si soportado
            if ('vibrate' in navigator) {
                try { navigator.vibrate([200, 100, 200]); } catch (e) {}
            }
        },

        confirmarActual() {
            if (this.alertas.length === 0) return;
            this.alertas.splice(this.indiceActual, 1);
            if (this.indiceActual >= this.alertas.length) {
                this.indiceActual = Math.max(0, this.alertas.length - 1);
            }
        },

        confirmarTodas() {
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
