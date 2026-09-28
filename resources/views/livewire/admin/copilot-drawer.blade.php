<?php

use App\Models\Encuesta;
use App\Models\Promocion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $abierto = false;

    public string $input = '';

    public bool $procesando = false;

    public array $mensajes = [];

    public function mount(): void
    {
        $this->inicializarMensajes();
    }

    protected function inicializarMensajes(): void
    {
        $this->mensajes = [
            [
                'id' => 'bienvenida',
                'emisor' => 'copiloto',
                'tipo' => 'orientacion',
                'texto' => '👋 **¡Hola '.(Auth::user()?->name ? explode(' ', Auth::user()->name)[0] : 'Administrador')."!** Soy tu **Copiloto Ejecutivo IA** de RestoMaster.\n\nTengo acceso en tiempo real a la base de datos de tu restaurante: puedo auditar ventas por caja, analizar períodos específicos (días, semanas, meses), comparar formas de pago, rankear platos estrella o diseñar estrategias de fidelización.",
                'sugerencias' => [
                    '📊 Ventas en Caja 1',
                    '📅 Ventas del martes de esta semana',
                    '📈 Gráfico de ventas semanales',
                    '💳 Desglose efectivo vs tarjeta',
                    '🍣 Top platos más vendidos',
                    '👨‍🍳 Rendimiento de meseros',
                    '⚠️ Insumos críticos',
                ],
                'hora' => now()->format('H:i'),
            ],
        ];
    }

    #[On('abrir-copiloto-admin')]
    public function abrir(): void
    {
        if (! Auth::check() || (! Auth::user()->isAdmin() && ! Auth::user()->isGerente())) {
            return;
        }

        $this->abierto = true;
    }

    #[On('cerrar-copiloto-admin')]
    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function alternar(): void
    {
        if ($this->abierto) {
            $this->cerrar();
        } else {
            $this->abrir();
        }
    }

    public function enviarConsulta(?string $textoDirecto = null): void
    {
        $usuario = Auth::user();
        if (! $usuario || (! $usuario->isAdmin() && ! $usuario->isGerente())) {
            $this->dispatch('notificacion', [
                'mensaje' => 'No tienes permisos para interactuar con el Copiloto Ejecutivo.',
                'tipo' => 'error',
            ]);

            return;
        }

        $mensaje = trim($textoDirecto ?: $this->input);
        if (empty($mensaje)) {
            return;
        }

        // Registrar mensaje del usuario
        $this->mensajes[] = [
            'id' => uniqid('msg_usr_'),
            'emisor' => 'usuario',
            'tipo' => 'texto',
            'texto' => $mensaje,
            'hora' => now()->format('H:i'),
        ];

        $this->input = '';
        $this->procesando = true;

        try {
            $copilotService = app('App\Services\Ai\AdminAiCopilotService');
            $resultado = $copilotService->procesarConsulta($mensaje, $usuario);

            $this->mensajes[] = [
                'id' => uniqid('msg_ia_'),
                'emisor' => 'copiloto',
                'tipo' => $resultado['tipo'] ?? 'texto',
                'texto' => $resultado['mensaje'] ?? 'He procesado tu solicitud.',
                'datos' => $resultado['datos'] ?? null,
                'accion_rapida' => $resultado['accion_rapida'] ?? null,
                'hora' => now()->format('H:i'),
            ];
        } catch (\Throwable $e) {
            $this->mensajes[] = [
                'id' => uniqid('msg_ia_'),
                'emisor' => 'copiloto',
                'tipo' => 'error',
                'texto' => '⚠️ Ocurrió un error al procesar la solicitud: '.$e->getMessage(),
                'hora' => now()->format('H:i'),
            ];
        } finally {
            $this->procesando = false;
        }
    }

    public function ejecutarAccion(string $accion, array $payload = []): void
    {
        $usuario = Auth::user();
        if (! $usuario || (! $usuario->isAdmin() && ! $usuario->isGerente())) {
            return;
        }

        $copilotService = app('App\Services\Ai\AdminAiCopilotService');

        if ($accion === 'guardar_encuesta') {
            $encuesta = $copilotService->guardarEncuesta($payload, $usuario->sucursal_id);
            $this->dispatch('notificacion', [
                'mensaje' => "¡Encuesta '{$encuesta->nombre}' guardada con éxito en CRM!",
                'tipo' => 'success',
            ]);
            $this->mensajes[] = [
                'id' => uniqid('msg_ia_'),
                'emisor' => 'copiloto',
                'tipo' => 'confirmacion',
                'texto' => "✅ La encuesta **'{$encuesta->nombre}'** fue creada exitosamente en la base de datos con {$encuesta->delay_horas}h de delay programado.",
                'accion_rapida' => [
                    'etiqueta' => 'Abrir Diseñador de Encuestas en CRM',
                    'url' => '/crm',
                    'icono' => 'rate_review',
                ],
                'hora' => now()->format('H:i'),
            ];
        } elseif ($accion === 'guardar_promocion') {
            $promo = $copilotService->guardarPromocion($payload, $usuario->sucursal_id);
            $this->dispatch('notificacion', [
                'mensaje' => "¡Promoción '{$promo->titulo}' registrada con éxito!",
                'tipo' => 'success',
            ]);
            $this->mensajes[] = [
                'id' => uniqid('msg_ia_'),
                'emisor' => 'copiloto',
                'tipo' => 'confirmacion',
                'texto' => "✅ La promoción **'{$promo->titulo}'** ha sido registrada y ya se encuentra visible en el Portal de Promociones y la Portada Web.",
                'accion_rapida' => [
                    'etiqueta' => 'Ver en Panel de Promociones',
                    'url' => '/promociones/gestion',
                    'icono' => 'local_offer',
                ],
                'hora' => now()->format('H:i'),
            ];
        }
    }

    public function reiniciarChat(): void
    {
        $this->inicializarMensajes();
        $this->dispatch('notificacion', [
            'mensaje' => 'Conversación con el Copiloto reiniciada.',
            'tipo' => 'info',
        ]);
    }
}; ?>

<div 
    x-data="{
        abierto: @entangle('abierto'),
        scrollToBottom() {
            $nextTick(() => {
                const el = this.$refs.chatContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    }"
    x-init="
        window.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                abierto = !abierto;
                if (abierto) scrollToBottom();
            }
            if (e.key === 'Escape' && abierto) {
                abierto = false;
            }
        });
        $watch('abierto', (val) => { if (val) scrollToBottom(); });
    "
    class="relative z-[90]"
>
    <!-- Floating Launcher Trigger Button (Desktop & Mobile, bottom-right) -->
    <button 
        @click="abierto = true; scrollToBottom();"
        type="button"
        title="Copiloto Ejecutivo IA (Ctrl+K)"
        class="fixed bottom-6 right-6 z-40 flex items-center gap-2.5 px-4 py-3 rounded-2xl bg-gradient-to-r from-amber-600 via-amber-500 to-rose-600 text-white font-bold text-sm shadow-[0_8px_30px_rgba(217,119,6,0.4)] hover:shadow-[0_12px_40px_rgba(217,119,6,0.6)] hover:scale-105 active:scale-95 transition-all duration-200 border border-amber-300/30 group"
    >
        <span class="material-symbols-outlined text-[22px] group-hover:rotate-12 transition-transform duration-300">smart_toy</span>
        <span class="hidden sm:inline tracking-wide font-extrabold">Copiloto IA</span>
        <kbd class="hidden md:inline-block px-1.5 py-0.5 text-[10px] font-mono bg-black/30 rounded border border-white/20 text-amber-100">Ctrl+K</kbd>
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
    </button>

    <!-- Modal Backdrop -->
    <div 
        x-show="abierto"
        x-transition:enter="transition-opacity ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="abierto = false"
        class="fixed inset-0 bg-scrim/80 backdrop-blur-md z-50"
        style="display: none;"
    ></div>

    <!-- Executive Slide-over Drawer / Panel -->
    <div 
        x-show="abierto"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 w-full sm:w-[520px] md:w-[600px] bg-surface-container-lowest border-l border-surface-container-highest shadow-2xl z-50 flex flex-col justify-between overflow-hidden"
        style="display: none;"
    >
        <!-- Header -->
        <div class="px-5 py-4 bg-gradient-to-r from-surface-container-low via-surface-container to-surface-container-high border-b border-surface-container-highest flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-rose-600 flex items-center justify-center text-white shadow-md">
                    <span class="material-symbols-outlined text-[22px]">psychology</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-extrabold text-on-surface">Copiloto Ejecutivo IA</h2>
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-black rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                            Deep Analytics
                        </span>
                    </div>
                    <p class="text-xs text-on-surface-variant flex items-center gap-1.5 mt-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Asistente Estratégico • Base de Datos en Tiempo Real
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1">
                <button 
                    wire:click="reiniciarChat" 
                    type="button" 
                    class="p-2 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest rounded-xl transition-colors"
                    title="Reiniciar chat"
                >
                    <span class="material-symbols-outlined text-[20px]">restart_alt</span>
                </button>
                <button 
                    @click="abierto = false" 
                    type="button" 
                    class="p-2 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest rounded-xl transition-colors"
                    title="Cerrar panel"
                >
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
        </div>

        <!-- Chat Body / Messages History -->
        <div 
            x-ref="chatContainer"
            class="flex-1 p-4 sm:p-5 overflow-y-auto space-y-4 bg-surface/50"
        >
            @foreach($mensajes as $msg)
                @if($msg['emisor'] === 'usuario')
                    <!-- Mensaje del Administrador -->
                    <div class="flex justify-end">
                        <div class="max-w-[85%] rounded-2xl rounded-tr-xs bg-primary px-4 py-3 text-on-primary text-sm shadow-sm">
                            <p class="whitespace-pre-line leading-relaxed">{{ $msg['texto'] }}</p>
                            <span class="block text-[10px] text-right mt-1 text-on-primary/70 font-mono">{{ $msg['hora'] }}</span>
                        </div>
                    </div>
                @else
                    <!-- Mensaje del Copiloto IA -->
                    <div class="flex gap-3 max-w-[98%]">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-500 border border-amber-500/30 flex items-center justify-center shrink-0 mt-1">
                            <span class="material-symbols-outlined text-[18px]">smart_toy</span>
                        </div>

                        <div class="flex-1 space-y-3">
                            <div class="rounded-2xl rounded-tl-xs bg-surface-container-low border border-surface-container-highest p-4 text-sm text-on-surface shadow-xs">
                                <div class="prose prose-sm dark:prose-invert max-w-none text-on-surface leading-relaxed">
                                    {!! Str::markdown($msg['texto'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                </div>

                                <!-- WIDGET: Mini KPIs Grid (si existen) -->
                                @if(!empty($msg['datos']['kpis']))
                                    <div class="mt-4 pt-3 border-t border-surface-container-highest">
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="bg-surface-container p-2.5 rounded-xl border border-surface-container-high text-center">
                                                <span class="text-[10px] text-on-surface-variant font-bold block uppercase">Facturación</span>
                                                <span class="text-sm font-extrabold text-primary font-mono">${{ number_format($msg['datos']['kpis']['ventas'] ?? 0, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="bg-surface-container p-2.5 rounded-xl border border-surface-container-high text-center">
                                                <span class="text-[10px] text-on-surface-variant font-bold block uppercase">Comandas</span>
                                                <span class="text-sm font-extrabold text-on-surface font-mono">{{ $msg['datos']['kpis']['transacciones'] ?? 0 }}</span>
                                            </div>
                                            <div class="bg-surface-container p-2.5 rounded-xl border border-surface-container-high text-center">
                                                <span class="text-[10px] text-on-surface-variant font-bold block uppercase">Ticket Prom.</span>
                                                <span class="text-sm font-extrabold text-secondary font-mono">${{ number_format($msg['datos']['kpis']['ticket_promedio'] ?? 0, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- WIDGET: GRÁFICO DINÁMICO MULTI-FORMATO -->
                                @if(!empty($msg['datos']['grafico']))
                                    @php
                                        $graf = $msg['datos']['grafico'];
                                        $tipoGrafico = $graf['tipo'] ?? 'bar';
                                        $maxValor = !empty($graf['valores']) ? max(1, ...$graf['valores']) : 1;
                                    @endphp

                                    <div class="mt-3 bg-surface-container-lowest p-3.5 rounded-xl border border-surface-container-high space-y-3">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-xs font-bold text-on-surface flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-[16px] text-primary">
                                                        {{ $tipoGrafico === 'doughnut' ? 'donut_large' : ($tipoGrafico === 'ranking' ? 'leaderboard' : 'bar_chart') }}
                                                    </span>
                                                    {{ $graf['titulo'] ?? 'Análisis Gráfico' }}
                                                </span>
                                                @if(!empty($graf['subtitulo']))
                                                    <span class="text-[10px] text-on-surface-variant block">{{ $graf['subtitulo'] }}</span>
                                                @endif
                                            </div>
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant">
                                                {{ strtoupper($graf['unidad'] ?? 'DATOS') }}
                                            </span>
                                        </div>

                                        <!-- Tipo 1: Barras Verticales Interactivas -->
                                        @if($tipoGrafico === 'bar')
                                            <div class="flex items-end gap-2 h-28 pt-4 pb-1 border-b border-surface-container-highest">
                                                @foreach($graf['etiquetas'] as $idx => $etiqueta)
                                                    @php
                                                        $val = $graf['valores'][$idx] ?? 0;
                                                        $pct = max(6, min(100, round(($val / $maxValor) * 100)));
                                                        $valFmt = $graf['valores_formateados'][$idx] ?? number_format($val, 0, ',', '.');
                                                        $color = $graf['colores'][$idx] ?? '#e0442e';
                                                    @endphp
                                                    <div class="flex-1 flex flex-col items-center h-full justify-end group relative">
                                                        <div class="w-full rounded-t transition-all duration-300 group-hover:brightness-125" :style="'height: {{ $pct }}%; background-color: {{ $color }};'"></div>
                                                        <!-- Tooltip -->
                                                        <div class="absolute bottom-full mb-1.5 hidden group-hover:block z-30 bg-surface-container-highest text-on-surface text-[10px] px-2 py-1 rounded-lg shadow-lg whitespace-nowrap font-mono border border-surface-container-high pointer-events-none">
                                                            <strong class="block text-primary">{{ $etiqueta }}</strong>
                                                            <span>{{ $valFmt }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="flex justify-between text-[9px] text-on-surface-variant font-mono px-0.5 truncate gap-1">
                                                @foreach($graf['etiquetas'] as $etiqueta)
                                                    <span class="flex-1 text-center truncate" title="{{ $etiqueta }}">{{ Str::limit($etiqueta, 6, '') }}</span>
                                                @endforeach
                                            </div>

                                        <!-- Tipo 2: Doughnut / Distribución Proporcional de Métodos de Pago -->
                                        @elseif($tipoGrafico === 'doughnut')
                                            <!-- Barra segmentada continua -->
                                            <div class="h-3 w-full rounded-full overflow-hidden flex bg-surface-container">
                                                @foreach($graf['etiquetas'] as $idx => $etiqueta)
                                                    @php
                                                        $pct = $graf['porcentajes'][$idx] ?? 0;
                                                        $color = $graf['colores'][$idx] ?? '#6b7280';
                                                    @endphp
                                                    @if($pct > 0)
                                                        <div class="h-full transition-all duration-500 first:rounded-l-full last:rounded-r-full" :style="'width: {{ $pct }}%; background-color: {{ $color }};'" title="{{ $etiqueta }}: {{ $pct }}%"></div>
                                                    @endif
                                                @endforeach
                                            </div>
                                            <!-- Leyenda -->
                                            <div class="grid grid-cols-2 gap-2 pt-1">
                                                @foreach($graf['etiquetas'] as $idx => $etiqueta)
                                                    @php
                                                        $valFmt = $graf['valores_formateados'][$idx] ?? '$0';
                                                        $pct = $graf['porcentajes'][$idx] ?? 0;
                                                        $color = $graf['colores'][$idx] ?? '#6b7280';
                                                    @endphp
                                                    <div class="flex items-center justify-between text-xs p-1.5 rounded-lg bg-surface-container border border-surface-container-high">
                                                        <div class="flex items-center gap-1.5 truncate">
                                                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background-color: {{ $color }};'"></span>
                                                            <span class="font-medium truncate text-[11px]">{{ $etiqueta }}</span>
                                                        </div>
                                                        <span class="font-mono font-bold text-[11px] shrink-0 text-on-surface">
                                                            {{ $valFmt }} <span class="text-[10px] text-on-surface-variant font-normal">({{ $pct }}%)</span>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>

                                        <!-- Tipo 3: Ranking Leaderboard de Platos -->
                                        @elseif($tipoGrafico === 'ranking')
                                            <div class="space-y-1.5">
                                                @foreach($graf['etiquetas'] as $idx => $etiqueta)
                                                    @php
                                                        $val = $graf['valores'][$idx] ?? 0;
                                                        $valFmt = $graf['valores_formateados'][$idx] ?? '$0';
                                                        $pct = max(4, min(100, round(($val / $maxValor) * 100)));
                                                        $color = $graf['colores'][$idx] ?? '#e0442e';
                                                    @endphp
                                                    <div class="p-2 rounded-xl bg-surface-container border border-surface-container-high relative overflow-hidden">
                                                        <div class="absolute left-0 top-0 bottom-0 opacity-15" :style="'width: {{ $pct }}%; background-color: {{ $color }};'"></div>
                                                        <div class="relative flex items-center justify-between text-xs">
                                                            <div class="flex items-center gap-2 truncate pr-2">
                                                                <span class="w-5 h-5 rounded-full bg-surface-container-highest text-on-surface font-bold text-[10px] flex items-center justify-center shrink-0">
                                                                    {{ $idx + 1 }}
                                                                </span>
                                                                <span class="font-bold truncate text-on-surface">{{ $etiqueta }}</span>
                                                            </div>
                                                            <span class="font-mono font-extrabold text-primary shrink-0">{{ $valFmt }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <!-- WIDGET: Tarjeta Infográfica Ejecutiva -->
                                @if(!empty($msg['datos']['infografia']))
                                    @php $info = $msg['datos']['infografia']; @endphp
                                    <div class="mt-3 p-4 rounded-2xl bg-gradient-to-br from-amber-950/40 via-surface-container to-rose-950/40 border border-amber-500/30 space-y-3 shadow-lg">
                                        <div class="flex items-center justify-between border-b border-white/10 pb-2.5">
                                            <div class="flex items-center gap-2">
                                                <span class="material-symbols-outlined text-amber-400 text-[20px]">auto_graph</span>
                                                <span class="font-black text-xs uppercase tracking-wider text-amber-200">{{ $info['titulo'] ?? 'Infografía Ejecutiva' }}</span>
                                            </div>
                                            <span class="text-[10px] font-mono text-amber-300/80">{{ $info['fecha'] ?? '' }}</span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div class="p-2 rounded-xl bg-black/20 border border-white/10">
                                                <span class="text-[10px] text-amber-200/70 block uppercase font-bold">Ventas Totales</span>
                                                <span class="text-base font-black text-amber-300 font-mono">${{ number_format($info['ventas'] ?? 0, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="p-2 rounded-xl bg-black/20 border border-white/10">
                                                <span class="text-[10px] text-amber-200/70 block uppercase font-bold">Ticket Promedio</span>
                                                <span class="text-base font-black text-white font-mono">${{ number_format($info['ticket_promedio'] ?? 0, 0, ',', '.') }}</span>
                                            </div>
                                        </div>

                                        <div class="p-2.5 rounded-xl bg-black/30 border border-white/10 flex items-center justify-between text-xs">
                                            <span class="text-amber-200/80">Plato Estrella:</span>
                                            <span class="font-bold text-white flex items-center gap-1">
                                                🍣 {{ $info['top_plato'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                    </div>
                                @endif

                                <!-- WIDGET: Alertas de Insumos Críticos -->
                                @if(($msg['tipo'] ?? '') === 'inventario' && !empty($msg['datos']['insumos']))
                                    <div class="mt-4 pt-3 border-t border-surface-container-highest space-y-2">
                                        <span class="text-xs font-bold text-on-surface flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[16px] text-rose-500">warning</span>
                                            Detalle de Insumos Requiriendo Compra:
                                        </span>
                                        <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                            @foreach($msg['datos']['insumos'] as $ins)
                                                <div class="p-2 rounded-xl bg-surface-container flex items-center justify-between text-xs border border-surface-container-high">
                                                    <div>
                                                        <div class="font-bold text-on-surface flex items-center gap-1.5">
                                                            <span class="w-2 h-2 rounded-full {{ $ins['estado'] === 'agotado' ? 'bg-rose-500 animate-ping' : 'bg-amber-500' }}"></span>
                                                            {{ $ins['nombre'] }}
                                                        </div>
                                                        <span class="text-[11px] text-on-surface-variant">
                                                            Prov: {{ $ins['proveedor'] }}
                                                            @if($ins['telefono'])
                                                                • Tel: <a href="tel:{{ $ins['telefono'] }}" class="text-primary hover:underline font-mono">{{ $ins['telefono'] }}</a>
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="font-black font-mono {{ $ins['estado'] === 'agotado' ? 'text-rose-500' : 'text-amber-500' }}">
                                                            {{ $ins['stock_actual'] }} / {{ $ins['stock_minimo'] }} {{ $ins['unidad'] }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- WIDGET: Borrador de Encuesta -->
                                @if(($msg['tipo'] ?? '') === 'encuesta' && !empty($msg['datos']['preguntas']))
                                    <div class="mt-4 pt-3 border-t border-surface-container-highest space-y-2">
                                        <span class="text-xs font-bold text-on-surface flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[16px] text-amber-500">list_alt</span>
                                            Preguntas Formuladas:
                                        </span>
                                        <div class="space-y-1.5">
                                            @foreach($msg['datos']['preguntas'] as $idx => $preg)
                                                <div class="p-2 rounded-lg bg-surface-container border border-surface-container-high text-xs flex items-center gap-2">
                                                    <span class="w-5 h-5 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center shrink-0 text-[11px]">
                                                        {{ $idx + 1 }}
                                                    </span>
                                                    <span class="flex-1 text-on-surface font-medium">{{ $preg['texto'] }}</span>
                                                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-surface-container-highest text-on-surface-variant uppercase">
                                                        {{ $preg['tipo'] }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Botón de Acción Rápida (Ejecución o Deep-Link) -->
                                @if(!empty($msg['accion_rapida']))
                                    <div class="mt-4 pt-3 border-t border-surface-container-highest flex items-center gap-2">
                                        @if(!empty($msg['accion_rapida']['accion']))
                                            <button 
                                                wire:click="ejecutarAccion('{{ $msg['accion_rapida']['accion'] }}', {{ json_encode($msg['accion_rapida']['payload'] ?? []) }})"
                                                type="button"
                                                class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gradient-to-r from-amber-600 to-rose-600 text-white font-bold text-xs shadow-sm hover:brightness-110 active:scale-95 transition-all"
                                            >
                                                <span class="material-symbols-outlined text-[16px]">{{ $msg['accion_rapida']['icono'] ?? 'check' }}</span>
                                                {{ $msg['accion_rapida']['etiqueta'] }}
                                            </button>
                                        @endif

                                        @if(!empty($msg['accion_rapida']['url']))
                                            <a 
                                                href="{{ $msg['accion_rapida']['url'] }}" 
                                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface font-bold text-xs border border-surface-container-highest transition-colors"
                                            >
                                                <span class="material-symbols-outlined text-[16px]">{{ $msg['accion_rapida']['icono'] ?? 'arrow_forward' }}</span>
                                                {{ $msg['accion_rapida']['etiqueta'] }}
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                <span class="block text-[10px] text-right mt-2 text-on-surface-variant font-mono">{{ $msg['hora'] }}</span>
                            </div>

                            <!-- Sugerencias de acción interactivas -->
                            @if(!empty($msg['sugerencias']))
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach($msg['sugerencias'] as $sug)
                                        <button 
                                            wire:click="enviarConsulta('{{ $sug }}')"
                                            type="button"
                                            class="text-xs px-2.5 py-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest text-on-surface font-medium transition-all hover:scale-102 active:scale-98"
                                        >
                                            {{ $sug }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach

            <!-- Indicador de procesamiento / pensamiento del Copiloto -->
            @if($procesando)
                <div class="flex gap-3 max-w-[90%]">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-500 border border-amber-500/30 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[18px] animate-spin">sync</span>
                    </div>
                    <div class="rounded-2xl rounded-tl-xs bg-surface-container-low border border-surface-container-highest p-3 text-xs text-on-surface-variant flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                        Consultando métricas e inteligencia operativa de RestoMaster...
                    </div>
                </div>
            @endif
        </div>

        <!-- Input Bar & Prompt Box -->
        <div class="p-4 bg-surface-container-low border-t border-surface-container-highest shrink-0">
            <form wire:submit.prevent="enviarConsulta" class="flex items-center gap-2">
                <div class="relative flex-1">
                    <input 
                        wire:model="input"
                        type="text" 
                        placeholder="Pregúntale al Copiloto (ej. 'ventas en caja 1', 'ventas del martes')..."
                        class="w-full px-4 py-3 rounded-2xl bg-surface-container-lowest border border-surface-container-highest focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm text-on-surface placeholder:text-on-surface-variant transition-all outline-none pr-10"
                        autofocus
                    />
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant">
                        <kbd class="px-1.5 py-0.5 text-[10px] font-mono bg-surface-container rounded border border-surface-container-highest">Enter</kbd>
                    </span>
                </div>

                <button 
                    type="submit"
                    wire:loading.attr="disabled"
                    class="h-12 w-12 rounded-2xl bg-gradient-to-r from-amber-600 to-rose-600 text-white flex items-center justify-center shadow-md hover:scale-105 active:scale-95 transition-all shrink-0 disabled:opacity-50"
                >
                    <span class="material-symbols-outlined text-[20px]">send</span>
                </button>
            </form>
            <p class="text-[11px] text-center text-on-surface-variant mt-2">
                Exclusivo para Gerencia & Administración • Sistema RestoMaster
            </p>
        </div>
    </div>
</div>
