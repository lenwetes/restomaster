<?php

use App\Models\CrmAutomatizacion;
use App\Models\CrmConfiguracion;
use App\Models\CrmMensajeLog;
use App\Models\CrmPlantilla;
use App\Services\CrmEstadisticasService;
use App\Services\CrmWhatsAppService;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $tab = 'satisfaccion'; // satisfaccion, automatizaciones, plantillas, logs, configuracion

    // Filtros de estadísticas
    public string $desde = '';
    public string $hasta = '';

    // Gestión de configuración
    public string $whatsapp_proveedor = 'meta_cloud';
    public string $whatsapp_phone_number_id = '';
    public string $whatsapp_waba_id = '';
    public string $whatsapp_access_token = '';
    public string $whatsapp_webhook_secret = '';
    public string $whatsapp_telefono_pruebas = '';
    public bool $email_activo = true;
    public string $email_remitente_nombre = '';
    public string $email_remitente_correo = '';
    public string $horario_envio_inicio = '10:00';
    public string $horario_envio_fin = '22:00';
    public int $delay_encuesta_minutos = 15;
    public int $winback_dias_inactividad = 45;

    // Gestión de plantillas
    public ?int $plantillaSeleccionadaId = null;
    public string $plantillaNombre = '';
    public string $plantillaCanal = 'whatsapp';
    public ?string $plantillaAsunto = '';
    public string $plantillaContenido = '';
    public ?string $plantillaTemplateName = '';

    // Filtros de logs
    public string $filtroCanal = '';
    public string $filtroEstado = '';
    public ?CrmMensajeLog $logDetalle = null;
    public bool $modalLogOpen = false;

    // Mensajes de feedback
    public ?string $mensajeAlerta = null;
    public string $tipoAlerta = 'success';

    public function mount(): void
    {
        $this->desde = now()->subDays(30)->toDateString();
        $this->hasta = now()->toDateString();
        $this->cargarConfiguracion();

        $primeraPlantilla = CrmPlantilla::first();
        if ($primeraPlantilla) {
            $this->seleccionarPlantilla($primeraPlantilla->id);
        }
    }

    public function setPeriodo(string $preset): void
    {
        switch ($preset) {
            case 'hoy':
                $this->desde = now()->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case '7dias':
                $this->desde = now()->subDays(7)->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case '30dias':
                $this->desde = now()->subDays(30)->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case 'este_mes':
                $this->desde = now()->startOfMonth()->toDateString();
                $this->hasta = now()->toDateString();
                break;
        }
    }

    public function cargarConfiguracion(): void
    {
        $config = CrmConfiguracion::activa();
        $this->whatsapp_proveedor = $config->whatsapp_proveedor;
        $this->whatsapp_phone_number_id = (string) $config->whatsapp_phone_number_id;
        $this->whatsapp_waba_id = (string) $config->whatsapp_waba_id;
        $this->whatsapp_access_token = (string) $config->whatsapp_access_token;
        $this->whatsapp_webhook_secret = (string) $config->whatsapp_webhook_secret;
        $this->whatsapp_telefono_pruebas = (string) $config->whatsapp_telefono_pruebas;
        $this->email_activo = (bool) $config->email_activo;
        $this->email_remitente_nombre = (string) $config->email_remitente_nombre;
        $this->email_remitente_correo = (string) $config->email_remitente_correo;
        $this->horario_envio_inicio = (string) $config->horario_envio_inicio;
        $this->horario_envio_fin = (string) $config->horario_envio_fin;
        $this->delay_encuesta_minutos = (int) $config->delay_encuesta_minutos;
        $this->winback_dias_inactividad = (int) $config->winback_dias_inactividad;
    }

    public function guardarConfiguracion(): void
    {
        $config = CrmConfiguracion::activa();
        $config->update([
            'whatsapp_proveedor' => $this->whatsapp_proveedor,
            'whatsapp_phone_number_id' => $this->whatsapp_phone_number_id,
            'whatsapp_waba_id' => $this->whatsapp_waba_id,
            'whatsapp_access_token' => $this->whatsapp_access_token,
            'whatsapp_webhook_secret' => $this->whatsapp_webhook_secret,
            'whatsapp_telefono_pruebas' => $this->whatsapp_telefono_pruebas,
            'email_activo' => $this->email_activo,
            'email_remitente_nombre' => $this->email_remitente_nombre,
            'email_remitente_correo' => $this->email_remitente_correo,
            'horario_envio_inicio' => $this->horario_envio_inicio,
            'horario_envio_fin' => $this->horario_envio_fin,
            'delay_encuesta_minutos' => $this->delay_encuesta_minutos,
            'winback_dias_inactividad' => $this->winback_dias_inactividad,
        ]);

        $this->mensajeAlerta = '¡Configuración CRM actualizada con éxito!';
        $this->tipoAlerta = 'success';
    }

    public function enviarPruebaWhatsApp(CrmWhatsAppService $whatsAppService): void
    {
        if (empty($this->whatsapp_telefono_pruebas)) {
            $this->mensajeAlerta = 'Debes ingresar un número de teléfono de pruebas con código de país.';
            $this->tipoAlerta = 'error';
            return;
        }

        $contenido = "👋 ¡Hola! Este es un mensaje de prueba oficial de RestoMaster CRM a través de la API de WhatsApp. Conexión establecida correctamente a las " . now()->format('H:i:s') . ".";
        
        $log = $whatsAppService->enviarMensaje(
            telefono: $this->whatsapp_telefono_pruebas,
            contenido: $contenido,
            templateName: null
        );

        if ($log->estado === 'fallido') {
            $this->mensajeAlerta = 'Error al enviar prueba WhatsApp: ' . ($log->error_mensaje ?? 'Error desconocido');
            $this->tipoAlerta = 'error';
        } else {
            $this->mensajeAlerta = '¡Mensaje de prueba enviado exitosamente! (ID: ' . ($log->mensaje_id_externo ?? 'Simulado') . ')';
            $this->tipoAlerta = 'success';
        }
    }

    public function toggleAutomatizacion(int $id): void
    {
        $auto = CrmAutomatizacion::findOrFail($id);
        $auto->update(['activa' => !$auto->activa]);
        $this->mensajeAlerta = "Automatización '{$auto->nombre}' " . ($auto->activa ? 'activada' : 'pausada') . '.';
        $this->tipoAlerta = 'info';
    }

    public function actualizarCanalAutomatizacion(int $id, string $canal): void
    {
        $auto = CrmAutomatizacion::findOrFail($id);
        $auto->update(['canal' => $canal]);
        $this->mensajeAlerta = "Canal de '{$auto->nombre}' actualizado a {$canal}.";
        $this->tipoAlerta = 'success';
    }

    public function seleccionarPlantilla(int $id): void
    {
        $plantilla = CrmPlantilla::find($id);
        if ($plantilla) {
            $this->plantillaSeleccionadaId = $plantilla->id;
            $this->plantillaNombre = $plantilla->nombre;
            $this->plantillaCanal = $plantilla->canal;
            $this->plantillaAsunto = $plantilla->asunto ?? '';
            $this->plantillaContenido = $plantilla->contenido;
            $this->plantillaTemplateName = $plantilla->whatsapp_template_name ?? '';
        }
    }

    public function insertarVariable(string $variable): void
    {
        $this->plantillaContenido .= " {" . $variable . "}";
    }

    public function guardarPlantilla(): void
    {
        if (!$this->plantillaSeleccionadaId) {
            return;
        }

        $plantilla = CrmPlantilla::findOrFail($this->plantillaSeleccionadaId);
        $plantilla->update([
            'nombre' => $this->plantillaNombre,
            'asunto' => $this->plantillaAsunto,
            'contenido' => $this->plantillaContenido,
            'whatsapp_template_name' => $this->plantillaTemplateName ?: null,
        ]);

        $this->mensajeAlerta = "¡Plantilla '{$plantilla->nombre}' guardada correctamente!";
        $this->tipoAlerta = 'success';
    }

    public function verDetalleLog(int $id): void
    {
        $this->logDetalle = CrmMensajeLog::with(['cliente', 'pedido', 'reserva', 'automatizacion'])->find($id);
        $this->modalLogOpen = true;
    }

    public function reintentarLog(int $id, CrmWhatsAppService $whatsAppService): void
    {
        $log = CrmMensajeLog::findOrFail($id);
        if ($log->canal === 'whatsapp') {
            $nuevoLog = $whatsAppService->enviarMensaje(
                telefono: $log->destinatario,
                contenido: $log->contenido_enviado,
                clienteId: $log->cliente_id,
                pedidoId: $log->pedido_id,
                reservaId: $log->reserva_id,
                automatizacionId: $log->automatizacion_id
            );
            $this->mensajeAlerta = 'Reintento completado: ' . $nuevoLog->estado;
            $this->tipoAlerta = $nuevoLog->estado === 'fallido' ? 'error' : 'success';
        }
    }

    public function with(): array
    {
        $estadisticasService = app(CrmEstadisticasService::class);
        $kpis = $estadisticasService->obtenerKpis($this->desde, $this->hasta);
        $rankingMeseros = $estadisticasService->rankingCalidadMeseros($this->desde, $this->hasta);
        $opiniones = $estadisticasService->muroOpinionesRecientes(10);

        $automatizaciones = CrmAutomatizacion::with(['plantillaWhatsapp', 'plantillaEmail'])->get();
        $plantillas = CrmPlantilla::orderBy('canal')->orderBy('nombre')->get();

        $logsQuery = CrmMensajeLog::with(['cliente', 'automatizacion'])
            ->when($this->filtroCanal, fn($q) => $q->where('canal', $this->filtroCanal))
            ->when($this->filtroEstado, fn($q) => $q->where('estado', $this->filtroEstado))
            ->latest();

        $logs = $logsQuery->paginate(15);

        return [
            'kpis' => $kpis,
            'rankingMeseros' => $rankingMeseros,
            'opiniones' => $opiniones,
            'automatizaciones' => $automatizaciones,
            'plantillas' => $plantillas,
            'logs' => $logs,
        ];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
    <!-- Header Principal -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm">
        <div class="space-y-1">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">mark_chat_unread</span>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-on-surface tracking-tight">CRM & Automatizaciones</h1>
                    <p class="text-sm text-on-surface-variant font-medium">Fidelización de clientes, encuestas automáticas por WhatsApp y Correo, y analítica de satisfacción CSAT / NPS.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold {{ $whatsapp_proveedor === 'simulado' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' }}">
                <span class="w-2 h-2 rounded-full {{ $whatsapp_proveedor === 'simulado' ? 'bg-amber-500' : 'bg-emerald-500 animate-pulse' }}"></span>
                WhatsApp: {{ $whatsapp_proveedor === 'simulado' ? 'Modo Simulado Local' : 'Meta Cloud API v21.0' }}
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold {{ $email_activo ? 'bg-sky-100 text-sky-800 border border-sky-300' : 'bg-slate-100 text-slate-600 border border-slate-300' }}">
                <span class="w-2 h-2 rounded-full {{ $email_activo ? 'bg-sky-500' : 'bg-slate-400' }}"></span>
                Email: {{ $email_activo ? 'Activo' : 'Inactivo' }}
            </span>
        </div>
    </div>

    <!-- Feedback Alerta -->
    @if($mensajeAlerta)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" 
             class="flex items-center justify-between p-4 rounded-xl text-sm font-semibold transition-all {{ $tipoAlerta === 'success' ? 'bg-emerald-500/10 text-emerald-800 border border-emerald-500/30' : ($tipoAlerta === 'error' ? 'bg-red-500/10 text-red-800 border border-red-500/30' : 'bg-blue-500/10 text-blue-800 border border-blue-500/30') }}">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">{{ $tipoAlerta === 'success' ? 'check_circle' : ($tipoAlerta === 'error' ? 'error' : 'info') }}</span>
                <span>{{ $mensajeAlerta }}</span>
            </div>
            <button @click="show = false" class="text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
    @endif

    <!-- Barra de Navegación por Pestañas (Totalmente Visible y Responsive) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 bg-surface-container-lowest p-2 rounded-2xl border border-surface-container-highest shadow-sm">
        <button wire:click="$set('tab', 'satisfaccion')" 
                title="Tablero de Satisfacción & CSAT"
                class="flex items-center justify-center gap-2 px-3 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all {{ $tab === 'satisfaccion' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[20px] shrink-0">analytics</span>
            <span class="truncate">Satisfacción & CSAT</span>
        </button>

        <button wire:click="$set('tab', 'automatizaciones')" 
                title="Automatizaciones & Disparadores"
                class="flex items-center justify-center gap-2 px-3 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all {{ $tab === 'automatizaciones' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[20px] shrink-0">bolt</span>
            <span class="truncate">Automatizaciones</span>
        </button>

        <button wire:click="$set('tab', 'plantillas')" 
                title="Gestor de Plantillas (WhatsApp & Correo)"
                class="flex items-center justify-center gap-2 px-3 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all {{ $tab === 'plantillas' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[20px] shrink-0">chat_bubble_outline</span>
            <span class="truncate">Plantillas</span>
        </button>

        <button wire:click="$set('tab', 'logs')" 
                title="Bandeja de Envíos & Logs"
                class="flex items-center justify-center gap-2 px-3 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all {{ $tab === 'logs' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[20px] shrink-0">receipt_long</span>
            <span class="truncate">Envíos & Logs</span>
        </button>

        <button wire:click="$set('tab', 'configuracion')" 
                title="Conexión API Meta WhatsApp & Configuración"
                class="flex items-center justify-center gap-2 px-3 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all col-span-2 sm:col-span-1 {{ $tab === 'configuracion' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[20px] shrink-0">settings</span>
            <span class="truncate">Configuración API</span>
        </button>
    </div>

    <!-- PESTAÑA 1: TABLERO DE SATISFACCIÓN Y CALIDAD -->
    @if($tab === 'satisfaccion')
        <div class="space-y-6">
            <!-- Barra de Filtros de Período -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-surface-container-lowest p-4 rounded-xl border border-surface-container-highest">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase text-on-surface-variant tracking-wider">Período de Análisis:</span>
                    <div class="inline-flex rounded-lg bg-surface-container p-1 gap-1">
                        <button wire:click="setPeriodo('hoy')" class="px-3 py-1 rounded-md text-xs font-bold transition-colors {{ $desde === now()->toDateString() && $hasta === now()->toDateString() ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">Hoy</button>
                        <button wire:click="setPeriodo('7dias')" class="px-3 py-1 rounded-md text-xs font-bold transition-colors {{ $desde === now()->subDays(7)->toDateString() ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">Últimos 7 días</button>
                        <button wire:click="setPeriodo('30dias')" class="px-3 py-1 rounded-md text-xs font-bold transition-colors {{ $desde === now()->subDays(30)->toDateString() ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">Últimos 30 días</button>
                        <button wire:click="setPeriodo('este_mes')" class="px-3 py-1 rounded-md text-xs font-bold transition-colors {{ $desde === now()->startOfMonth()->toDateString() ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">Este Mes</button>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs font-bold text-on-surface-variant">
                    <span>Del {{ Carbon::parse($desde)->format('d/m/Y') }} al {{ Carbon::parse($hasta)->format('d/m/Y') }}</span>
                </div>
            </div>

            <!-- Grid de Tarjetas KPI -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Calificación General -->
                <div class="bg-surface-container-lowest p-5 rounded-2xl border border-surface-container-highest shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Calificación Promedio</span>
                        <span class="material-symbols-outlined text-amber-500 text-[24px]">hotel_class</span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-4xl font-black text-on-surface">{{ number_format($kpis['promedio_estrellas'], 1) }}</span>
                        <span class="text-sm font-bold text-amber-500">/ 5.0 ⭐</span>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant font-medium">Basado en {{ $kpis['total_votos'] }} valoraciones verificadas.</p>
                </div>

                <!-- CSAT % -->
                <div class="bg-surface-container-lowest p-5 rounded-2xl border border-surface-container-highest shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Índice CSAT</span>
                        <span class="material-symbols-outlined text-emerald-500 text-[24px]">sentiment_very_satisfied</span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-4xl font-black text-on-surface">{{ $kpis['csat'] }}%</span>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Excelente</span>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant font-medium">% de clientes con 4 o 5 estrellas.</p>
                </div>

                <!-- NPS -->
                <div class="bg-surface-container-lowest p-5 rounded-2xl border border-surface-container-highest shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Net Promoter Score (NPS)</span>
                        <span class="material-symbols-outlined text-primary text-[24px]">trending_up</span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-4xl font-black text-on-surface">+{{ $kpis['nps'] }}</span>
                        <span class="text-xs font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-full">Lealtad Alta</span>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant font-medium">Rango de -100 a +100 puntos.</p>
                </div>

                <!-- Tasa de Respuesta -->
                <div class="bg-surface-container-lowest p-5 rounded-2xl border border-surface-container-highest shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Tasa de Respuesta</span>
                        <span class="material-symbols-outlined text-sky-500 text-[24px]">mark_email_read</span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-4xl font-black text-on-surface">{{ $kpis['tasa_respuesta'] }}%</span>
                        <span class="text-xs font-bold text-on-surface-variant">({{ $kpis['total_respondidas'] }}/{{ $kpis['total_enviadas'] }})</span>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant font-medium">Encuestas respondidas vs enviadas.</p>
                </div>
            </div>

            <!-- Gráfica de Distribución de Estrellas + Ranking de Meseros -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Histograma de Estrellas -->
                <div class="bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-4">
                    <h3 class="font-black text-base text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-500">star</span>
                        Distribución de Calificaciones
                    </h3>

                    <div class="space-y-3 pt-2">
                        @foreach($kpis['distribucion_estrellas'] as $nivel => $dist)
                            <div class="flex items-center gap-3">
                                <div class="w-16 text-xs font-bold text-on-surface flex items-center gap-1">
                                    <span>{{ $nivel }}</span>
                                    <span class="text-amber-500">⭐</span>
                                </div>
                                <div class="flex-1 bg-surface-container h-3.5 rounded-full overflow-hidden">
                                    <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-full rounded-full transition-all duration-500" 
                                         @style(['width: ' . min(100, max(0, (float) $dist['porcentaje'])) . '%'])></div>
                                </div>
                                <div class="w-16 text-right text-xs font-black text-on-surface">
                                    {{ $dist['cantidad'] }} <span class="text-on-surface-variant font-medium text-[10px]">({{ $dist['porcentaje'] }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 p-4 rounded-xl bg-surface-container/50 border border-surface-container-high flex items-center justify-between text-xs font-medium">
                        <span class="text-on-surface-variant">Mensajes enviados por WhatsApp: <strong class="text-on-surface">{{ $kpis['canales']['whatsapp_enviados'] }}</strong></span>
                        <span class="text-on-surface-variant">Por Correo: <strong class="text-on-surface">{{ $kpis['canales']['email_enviados'] }}</strong></span>
                    </div>
                </div>

                <!-- Ranking de Calidad por Mesero -->
                <div class="bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-4">
                    <h3 class="font-black text-base text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">badge</span>
                        Calidad de Servicio por Mesero
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-surface-container-highest text-on-surface-variant font-black uppercase tracking-wider">
                                    <th class="py-2.5">Mesero</th>
                                    <th class="py-2.5 text-center">Encuestas</th>
                                    <th class="py-2.5 text-center">Promedio</th>
                                    <th class="py-2.5 text-right">Satisfacción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-container-high">
                                @forelse($rankingMeseros as $mesero)
                                    <tr class="hover:bg-surface-container/40 transition-colors">
                                        <td class="py-3 font-bold text-on-surface flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-primary/10 text-primary font-black flex items-center justify-center text-xs">
                                                {{ substr($mesero['nombre'], 0, 1) }}
                                            </div>
                                            <span>{{ $mesero['nombre'] }}</span>
                                        </td>
                                        <td class="py-3 text-center font-bold text-on-surface-variant">{{ $mesero['evaluaciones'] }}</td>
                                        <td class="py-3 text-center">
                                            <span class="inline-flex items-center gap-1 font-black text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">
                                                ⭐ {{ number_format($mesero['promedio_estrellas'], 1) }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-right">
                                            <span class="font-black text-emerald-600">{{ $mesero['porcentaje_satisfaccion'] }}%</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-on-surface-variant font-medium">Aún no hay evaluaciones registradas en el período seleccionado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Muro de Reseñas y Feedback de Clientes -->
            <div class="bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-base text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-secondary">forum</span>
                        Muro de Opiniones Recientes de Comensales
                    </h3>
                    <span class="text-xs font-bold text-on-surface-variant">Últimas 10 reseñas verificadas</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($opiniones as $op)
                        <div class="p-4 rounded-xl bg-surface-container/40 border border-surface-container-high space-y-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1 text-amber-500 font-bold text-xs">
                                        @for($s = 1; $s <= 5; $s++)
                                            <span class="material-symbols-outlined text-[16px] {{ $s <= $op['estrellas'] ? 'text-amber-500' : 'text-slate-300' }}">star</span>
                                        @endfor
                                    </div>
                                    <span class="text-[11px] font-medium text-on-surface-variant">{{ $op['hace_tiempo'] }}</span>
                                </div>
                                <p class="text-xs text-on-surface font-medium italic">"{{ $op['comentario'] }}"</p>
                            </div>

                            <div class="pt-2 border-t border-surface-container-highest/60 flex items-center justify-between text-[11px] text-on-surface-variant font-bold">
                                <span>👤 {{ $op['cliente'] }}</span>
                                @if($op['mesa'])
                                    <span class="bg-surface-container px-2 py-0.5 rounded text-[10px]">{{ $op['mesa'] }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-8 text-center text-on-surface-variant font-medium">
                            No se han recibido comentarios de texto en las encuestas recientes.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- PESTAÑA 2: AUTOMATIZACIONES & DISPARADORES -->
    @if($tab === 'automatizaciones')
        <div class="space-y-6">
            <div class="bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-surface-container-highest pb-4">
                    <div>
                        <h2 class="text-lg font-black text-on-surface">Reglas de Automatización Activas</h2>
                        <p class="text-xs text-on-surface-variant">Configura qué eventos desencadenan mensajes automáticos por WhatsApp o Correo.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    @foreach($automatizaciones as $auto)
                        <div class="p-5 rounded-2xl bg-surface-container/30 border border-surface-container-high flex flex-col lg:flex-row lg:items-center justify-between gap-4 transition-all hover:bg-surface-container/60">
                            <div class="space-y-1.5 max-w-xl">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider {{ $auto->activa ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                        {{ $auto->activa ? 'Activa' : 'Pausada' }}
                                    </span>
                                    <h3 class="font-black text-sm text-on-surface">{{ $auto->nombre }}</h3>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-on-surface-variant font-medium">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">sensors</span>
                                        Evento: <strong class="text-on-surface">{{ $auto->evento_disparador }}</strong>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">schedule</span>
                                        Delay: <strong class="text-on-surface">{{ $auto->delay_minutos }} min</strong>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">send</span>
                                        Total disparos: <strong class="text-on-surface">{{ $auto->total_disparos }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Selector de Canal -->
                                <div class="flex items-center gap-1 bg-surface-container-high p-1 rounded-xl">
                                    <button wire:click="actualizarCanalAutomatizacion({{ $auto->id }}, 'whatsapp')" 
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all {{ $auto->canal === 'whatsapp' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                                        WhatsApp
                                    </button>
                                    <button wire:click="actualizarCanalAutomatizacion({{ $auto->id }}, 'email')" 
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all {{ $auto->canal === 'email' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                                        Email
                                    </button>
                                    <button wire:click="actualizarCanalAutomatizacion({{ $auto->id }}, 'ambos')" 
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all {{ $auto->canal === 'ambos' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                                        Ambos
                                    </button>
                                </div>

                                <!-- Switch Activar/Desactivar -->
                                <button wire:click="toggleAutomatizacion({{ $auto->id }})" 
                                        class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 {{ $auto->activa ? 'bg-red-500/10 text-red-700 hover:bg-red-500/20' : 'bg-emerald-500 text-white hover:bg-emerald-600' }}">
                                    <span class="material-symbols-outlined text-[16px]">{{ $auto->activa ? 'pause' : 'play_arrow' }}</span>
                                    <span>{{ $auto->activa ? 'Pausar' : 'Activar' }}</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- PESTAÑA 3: GESTOR DE PLANTILLAS -->
    @if($tab === 'plantillas')
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Columna Izquierda: Lista de Plantillas -->
            <div class="lg:col-span-4 bg-surface-container-lowest p-5 rounded-2xl border border-surface-container-highest shadow-sm space-y-3">
                <h3 class="font-black text-sm text-on-surface uppercase tracking-wider text-on-surface-variant">Plantillas Registradas</h3>

                <div class="space-y-2">
                    @foreach($plantillas as $p)
                        <button wire:click="seleccionarPlantilla({{ $p->id }})" 
                                class="w-full text-left p-3.5 rounded-xl border transition-all flex items-center justify-between {{ $plantillaSeleccionadaId === $p->id ? 'bg-primary/10 border-primary text-primary font-bold shadow-xs' : 'bg-surface-container/30 border-surface-container-high hover:bg-surface-container text-on-surface' }}">
                            <div class="min-w-0 pr-2">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full {{ $p->canal === 'whatsapp' ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800' }}">
                                        {{ $p->canal }}
                                    </span>
                                </div>
                                <div class="text-xs font-black truncate">{{ $p->nombre }}</div>
                            </div>
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Columna Centro: Editor de Plantilla -->
            <div class="lg:col-span-5 bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-highest pb-3">
                    <h3 class="font-black text-base text-on-surface">Editor de Plantilla</h3>
                    <span class="text-xs font-bold text-on-surface-variant uppercase">Canal: {{ $plantillaCanal }}</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Nombre de la Plantilla</label>
                        <input type="text" wire:model="plantillaNombre" class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-surface-container-high bg-surface-container-lowest focus:ring-2 focus:ring-primary focus:outline-none">
                    </div>

                    @if($plantillaCanal === 'email')
                        <div>
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Asunto del Correo</label>
                            <input type="text" wire:model="plantillaAsunto" class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-surface-container-high bg-surface-container-lowest focus:ring-2 focus:ring-primary focus:outline-none">
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Template Name (Meta Cloud API)</label>
                            <input type="text" wire:model="plantillaTemplateName" placeholder="ej: encuesta_post_consumo_v1" class="w-full px-3 py-2 text-xs font-mono rounded-xl border border-surface-container-high bg-surface-container-lowest focus:ring-2 focus:ring-primary focus:outline-none">
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Cuerpo del Mensaje</label>
                            <span class="text-[11px] text-on-surface-variant">Clic en variable para insertar:</span>
                        </div>

                        <!-- Botones de inserción de variables -->
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <button type="button" wire:click="insertarVariable('nombre')" class="px-2 py-0.5 rounded text-[11px] font-mono bg-surface-container hover:bg-surface-container-high text-primary font-bold">+ {nombre}</button>
                            <button type="button" wire:click="insertarVariable('restaurante')" class="px-2 py-0.5 rounded text-[11px] font-mono bg-surface-container hover:bg-surface-container-high text-primary font-bold">+ {restaurante}</button>
                            <button type="button" wire:click="insertarVariable('url_encuesta')" class="px-2 py-0.5 rounded text-[11px] font-mono bg-surface-container hover:bg-surface-container-high text-primary font-bold">+ {url_encuesta}</button>
                            <button type="button" wire:click="insertarVariable('fecha_reserva')" class="px-2 py-0.5 rounded text-[11px] font-mono bg-surface-container hover:bg-surface-container-high text-primary font-bold">+ {fecha_reserva}</button>
                            <button type="button" wire:click="insertarVariable('hora_reserva')" class="px-2 py-0.5 rounded text-[11px] font-mono bg-surface-container hover:bg-surface-container-high text-primary font-bold">+ {hora_reserva}</button>
                            <button type="button" wire:click="insertarVariable('mesa')" class="px-2 py-0.5 rounded text-[11px] font-mono bg-surface-container hover:bg-surface-container-high text-primary font-bold">+ {mesa}</button>
                        </div>

                        <textarea wire:model.live="plantillaContenido" rows="8" class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-surface-container-high bg-surface-container-lowest focus:ring-2 focus:ring-primary focus:outline-none"></textarea>
                    </div>

                    <button wire:click="guardarPlantilla" class="w-full py-2.5 rounded-xl bg-primary text-on-primary font-bold text-xs shadow-sm hover:opacity-95 transition-opacity flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Guardar Plantilla
                    </button>
                </div>
            </div>

            <!-- Columna Derecha: Previsualizador WhatsApp / Correo -->
            <div class="lg:col-span-3 bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-4">
                <h3 class="font-black text-sm text-on-surface uppercase tracking-wider text-on-surface-variant flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-emerald-500">smartphone</span>
                    Vista Previa en Dispositivo
                </h3>

                @if($plantillaCanal === 'whatsapp')
                    <!-- Mockup Celular / Burbuja WhatsApp -->
                    <div class="bg-[#e5ddd5] rounded-2xl p-4 border border-surface-container-high shadow-inner min-h-[320px] flex flex-col justify-end">
                        <div class="bg-white rounded-xl p-3 shadow-md border-l-4 border-emerald-500 max-w-[90%] self-start space-y-2">
                            <div class="text-[11px] font-bold text-emerald-800 flex items-center gap-1">
                                <span>RestoMaster Oficial</span>
                                <span class="material-symbols-outlined text-[14px] text-emerald-600">verified</span>
                            </div>
                            <div class="text-xs text-slate-800 font-sans leading-relaxed whitespace-pre-wrap">
                                {{ str_replace(['{nombre}', '{restaurante}', '{url_encuesta}', '{fecha_reserva}', '{hora_reserva}', '{mesa}'], ['Carlos Mendoza', 'RestoMaster', 'https://restomaster.app/e/x98a', now()->format('d/m/Y'), '19:30', 'Mesa 4'], $plantillaContenido) }}
                            </div>
                            <div class="flex items-center justify-end gap-1 text-[10px] text-slate-400">
                                <span>{{ now()->format('H:i') }}</span>
                                <span class="material-symbols-outlined text-[13px] text-sky-500">done_all</span>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Mockup Email -->
                    <div class="bg-slate-100 rounded-2xl p-4 border border-surface-container-high shadow-inner space-y-3">
                        <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 space-y-2">
                            <div class="border-b pb-2">
                                <div class="text-xs font-bold text-slate-900">{{ $plantillaAsunto ?: 'Sin Asunto' }}</div>
                                <div class="text-[10px] text-slate-500">De: {{ $email_remitente_nombre }} &lt;{{ $email_remitente_correo }}&gt;</div>
                            </div>
                            <div class="text-xs text-slate-700 leading-relaxed">
                                {!! nl2br(e(str_replace(['{nombre}', '{restaurante}', '{url_encuesta}'], ['Carlos Mendoza', 'RestoMaster', 'https://restomaster.app/e/x98a'], $plantillaContenido))) !!}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- PESTAÑA 4: BANDEJA DE ENVÍOS & LOGS -->
    @if($tab === 'logs')
        <div class="bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-4">
            <!-- Filtros de Logs -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-container-highest pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <select wire:model.live="filtroCanal" class="px-3 py-1.5 text-xs font-bold rounded-xl border border-surface-container-high bg-surface-container-lowest">
                        <option value="">Todos los Canales</option>
                        <option value="whatsapp">Solo WhatsApp</option>
                        <option value="email">Solo Correo</option>
                    </select>

                    <select wire:model.live="filtroEstado" class="px-3 py-1.5 text-xs font-bold rounded-xl border border-surface-container-high bg-surface-container-lowest">
                        <option value="">Todos los Estados</option>
                        <option value="enviado">Enviado</option>
                        <option value="entregado">Entregado</option>
                        <option value="leido">Leído</option>
                        <option value="fallido">Fallido</option>
                        <option value="pendiente">Pendiente</option>
                    </select>
                </div>

                <div class="text-xs font-bold text-on-surface-variant">
                    Total registros: {{ $logs->total() }}
                </div>
            </div>

            <!-- Tabla de Mensajes Despachados -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-container-highest text-on-surface-variant font-black uppercase tracking-wider">
                            <th class="py-3">Canal</th>
                            <th class="py-3">Destinatario</th>
                            <th class="py-3">Mensaje / Asunto</th>
                            <th class="py-3">Estado</th>
                            <th class="py-3">Fecha y Hora</th>
                            <th class="py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high">
                        @forelse($logs as $log)
                            <tr class="hover:bg-surface-container/30 transition-colors">
                                <td class="py-3">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold text-[10px] uppercase {{ $log->canal === 'whatsapp' ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800' }}">
                                        {{ $log->canal }}
                                    </span>
                                </td>
                                <td class="py-3 font-mono font-bold text-on-surface">
                                    {{ $log->destinatario }}
                                    @if($log->cliente)
                                        <div class="text-[10px] font-sans font-medium text-on-surface-variant">{{ $log->cliente->nombre }}</div>
                                    @endif
                                </td>
                                <td class="py-3 max-w-xs truncate text-on-surface-variant font-medium">
                                    {{ $log->asunto ?: Str::limit($log->contenido_enviado, 45) }}
                                </td>
                                <td class="py-3">
                                    @php
                                        $badgeColor = match($log->estado) {
                                            'leido' => 'bg-sky-100 text-sky-800 border-sky-300',
                                            'entregado', 'enviado' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                            'fallido' => 'bg-red-100 text-red-800 border-red-300',
                                            default => 'bg-amber-100 text-amber-800 border-amber-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-bold text-[10px] border {{ $badgeColor }}">
                                        {{ ucfirst($log->estado) }}
                                    </span>
                                </td>
                                <td class="py-3 text-on-surface-variant font-medium">
                                    {{ $log->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="py-3 text-right space-x-1">
                                    <button wire:click="verDetalleLog({{ $log->id }})" class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-bold text-[11px]" title="Ver detalle">
                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    </button>
                                    @if($log->estado === 'fallido')
                                        <button wire:click="reintentarLog({{ $log->id }})" class="p-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-800 font-bold text-[11px]" title="Reintentar">
                                            <span class="material-symbols-outlined text-[16px]">replay</span>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-on-surface-variant font-medium">No se han encontrado registros de mensajes despachados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </div>
    @endif

    <!-- PESTAÑA 5: CONEXIÓN API META & CONFIGURACIÓN -->
    @if($tab === 'configuracion')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Columna Izquierda: Credenciales Meta WhatsApp Cloud API -->
            <div class="bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-4">
                <div class="flex items-center gap-2 border-b border-surface-container-highest pb-3">
                    <span class="material-symbols-outlined text-emerald-600 text-[24px]">chat</span>
                    <div>
                        <h3 class="font-black text-base text-on-surface">WhatsApp Business Cloud API (Meta Oficial)</h3>
                        <p class="text-xs text-on-surface-variant">Conexión directa Graph API v21.0 sin intermediarios.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Modo de Operación</label>
                        <select wire:model="whatsapp_proveedor" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-surface-container-high bg-surface-container-lowest">
                            <option value="meta_cloud">Meta Cloud API (Producción / Oficial)</option>
                            <option value="simulado">Modo Simulado (Desarrollo / Pruebas Local)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Phone Number ID (Meta)</label>
                        <input type="text" wire:model="whatsapp_phone_number_id" placeholder="ej: 105938472910482" class="w-full px-3 py-2 text-xs font-mono rounded-xl border border-surface-container-high bg-surface-container-lowest">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">WhatsApp Business Account ID (WABA ID)</label>
                        <input type="text" wire:model="whatsapp_waba_id" placeholder="ej: 392817492019482" class="w-full px-3 py-2 text-xs font-mono rounded-xl border border-surface-container-high bg-surface-container-lowest">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Permanent Access Token (Meta Bearer)</label>
                        <input type="password" wire:model="whatsapp_access_token" placeholder="EAAX..." class="w-full px-3 py-2 text-xs font-mono rounded-xl border border-surface-container-high bg-surface-container-lowest">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Webhook Verify Token</label>
                        <input type="text" wire:model="whatsapp_webhook_secret" class="w-full px-3 py-2 text-xs font-mono rounded-xl border border-surface-container-high bg-surface-container-lowest">
                    </div>

                    <!-- URL Webhook Callback -->
                    <div class="p-3 rounded-xl bg-surface-container/60 border border-surface-container-high space-y-1">
                        <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">URL de Callback para Meta Developer Console:</span>
                        <div class="text-xs font-mono text-primary font-bold break-all select-all">{{ url('api/webhooks/whatsapp') }}</div>
                    </div>

                    <!-- Test Directo -->
                    <div class="pt-3 border-t border-surface-container-highest space-y-2">
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider">Teléfono de Pruebas (con código país)</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="whatsapp_telefono_pruebas" placeholder="+573001234567" class="flex-1 px-3 py-2 text-xs font-mono rounded-xl border border-surface-container-high bg-surface-container-lowest">
                            <button type="button" wire:click="enviarPruebaWhatsApp" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">send</span>
                                Probar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Configuración de Correo & Anti-Spam -->
            <div class="bg-surface-container-lowest p-6 rounded-2xl border border-surface-container-highest shadow-sm space-y-6">
                <!-- Configuración Correo -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2 border-b border-surface-container-highest pb-3">
                        <span class="material-symbols-outlined text-sky-600 text-[24px]">mail</span>
                        <div>
                            <h3 class="font-black text-base text-on-surface">Canal de Correo Electrónico</h3>
                            <p class="text-xs text-on-surface-variant">Remitente de encuestas y promociones por email.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-container/40 border border-surface-container-high">
                        <span class="text-xs font-bold text-on-surface">Habilitar envíos por Correo Electrónico</span>
                        <input type="checkbox" wire:model="email_activo" class="w-4 h-4 text-primary rounded border-slate-300">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Nombre Remitente</label>
                            <input type="text" wire:model="email_remitente_nombre" class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-surface-container-high bg-surface-container-lowest">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Correo Remitente</label>
                            <input type="email" wire:model="email_remitente_correo" class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-surface-container-high bg-surface-container-lowest">
                        </div>
                    </div>
                </div>

                <!-- Políticas Anti-Spam y Tiempos -->
                <div class="space-y-4 pt-4 border-t border-surface-container-highest">
                    <div class="flex items-center gap-2 border-b border-surface-container-highest pb-2">
                        <span class="material-symbols-outlined text-amber-600 text-[20px]">shield</span>
                        <h4 class="font-black text-sm text-on-surface">Protección Anti-Spam y Horarios de Disparo</h4>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Hora Inicio Envíos</label>
                            <input type="time" wire:model="horario_envio_inicio" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-surface-container-high bg-surface-container-lowest">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Hora Fin Envíos</label>
                            <input type="time" wire:model="horario_envio_fin" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-surface-container-high bg-surface-container-lowest">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Delay Encuesta Post-Cobro</label>
                            <div class="flex items-center gap-1">
                                <input type="number" wire:model="delay_encuesta_minutos" min="0" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-surface-container-high bg-surface-container-lowest">
                                <span class="text-xs font-bold text-on-surface-variant">min</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1">Winback Inactividad</label>
                            <div class="flex items-center gap-1">
                                <input type="number" wire:model="winback_dias_inactividad" min="1" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-surface-container-high bg-surface-container-lowest">
                                <span class="text-xs font-bold text-on-surface-variant">días</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button wire:click="guardarConfiguracion" class="w-full py-3 rounded-xl bg-primary text-on-primary font-bold text-sm shadow-md hover:opacity-95 transition-opacity flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Guardar Toda la Configuración CRM
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL DE DETALLE DE LOG -->
    @if($modalLogOpen && $logDetalle)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-surface-container-lowest rounded-2xl max-w-lg w-full p-6 space-y-4 border border-surface-container-highest shadow-2xl">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[22px]">info</span>
                        <h3 class="font-black text-base text-on-surface">Detalle de Mensaje CRM #{{ $logDetalle->id }}</h3>
                    </div>
                    <button wire:click="$set('modalLogOpen', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-2 bg-surface-container/50 p-3 rounded-xl">
                        <div><strong class="text-on-surface-variant">Canal:</strong> <span class="uppercase font-bold">{{ $logDetalle->canal }}</span></div>
                        <div><strong class="text-on-surface-variant">Estado:</strong> <span class="font-bold">{{ ucfirst($logDetalle->estado) }}</span></div>
                        <div><strong class="text-on-surface-variant">Destinatario:</strong> <span class="font-mono font-bold">{{ $logDetalle->destinatario }}</span></div>
                        <div><strong class="text-on-surface-variant">ID Externo:</strong> <span class="font-mono">{{ $logDetalle->mensaje_id_externo ?: 'N/A' }}</span></div>
                    </div>

                    <div>
                        <strong class="text-on-surface-variant block mb-1">Contenido Enviado:</strong>
                        <div class="p-3 rounded-xl bg-surface-container-high/60 font-sans text-on-surface whitespace-pre-wrap">{{ $logDetalle->contenido_enviado }}</div>
                    </div>

                    @if($logDetalle->error_mensaje)
                        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-800 space-y-1">
                            <strong>Error del Proveedor:</strong>
                            <p class="font-mono text-[11px]">{{ $logDetalle->error_mensaje }}</p>
                        </div>
                    @endif
                </div>

                <div class="pt-3 border-t flex justify-end">
                    <button wire:click="$set('modalLogOpen', false)" class="px-4 py-2 rounded-xl bg-surface-container font-bold text-xs text-on-surface hover:bg-surface-container-high">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
