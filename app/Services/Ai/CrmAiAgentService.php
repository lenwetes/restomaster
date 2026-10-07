<?php

namespace App\Services\Ai;

use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmConversacion;
use App\Models\CrmIaPlantillaPrivilegio;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Services\MenuService;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrmAiAgentService
{
    public function __construct(
        protected AiToolGatekeeper $gatekeeper
    ) {}

    /**
     * Procesa un mensaje de usuario a través del flujo de seguridad, máquina de estado y agente de IA.
     *
     * @return array{
     *     respuesta: string,
     *     estado: string,
     *     tools_invocadas: array,
     *     tiempo_ms: int
     * }
     */
    public function procesarMensaje(
        string $mensaje,
        ?string $telefono = null,
        ?int $sucursalId = null,
        ?int $clienteId = null,
        array $historial = [],
        ?string $nombreContacto = null,
        ?CrmConversacion $conversacion = null
    ): array {
        $inicio = microtime(true);
        $config = CrmConfiguracion::activa($sucursalId);
        $plantilla = $this->gatekeeper->obtenerPlantillaActiva($sucursalId);

        // Cargar modelo del cliente si está disponible
        $cliente = null;
        if ($clienteId) {
            $cliente = Cliente::find($clienteId);
        } elseif ($telefono) {
            $cliente = Cliente::where('telefono', $telefono)
                ->orWhere('telefono', Str::replaceFirst('+', '', $telefono))
                ->first();
        }

        // 1. REGLA KILL-SWITCH: Si la IA está apagada manualmente
        if (! $config->iaActiva()) {
            return [
                'respuesta' => $config->ia_mensaje_apagado ?: 'En este momento nuestro asistente virtual está en pausa. Comunícate a nuestra línea de atención.',
                'estado' => 'ia_apagada',
                'tools_invocadas' => [],
                'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }

        // 2. REGLA GUARDRAILS: Fuga de datos confidenciales o financieros en canal de comensal
        if ($this->gatekeeper->esConsultaProhibida($mensaje)) {
            return [
                'respuesta' => 'Lo siento, no tengo autorización para consultar ni divulgar información administrativa o financiera. ¿Puedo ayudarte con información sobre nuestra carta, horarios o reservas?',
                'estado' => 'bloqueado_seguridad',
                'tools_invocadas' => [],
                'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }

        // 3. MÁQUINA DE ESTADO DE RESERVAS: Detección de flujo activo o nueva intención
        $contexto = $conversacion?->getContextoFlujo() ?? [];
        $enFlujoReserva = ($contexto['flujo'] ?? '') === 'reserva' && ($contexto['estado'] ?? '') === 'en_proceso';
        $intencionReserva = $this->esIntencionReserva($mensaje);

        // Cancelación explícita de la reserva en curso
        if ($enFlujoReserva && $this->esCancelacion($mensaje)) {
            $conversacion?->limpiarContextoFlujo();

            return [
                'respuesta' => 'Entendido, hemos cancelado el proceso de reserva en curso. ¿Deseas consultar nuestra carta, horarios o en qué más te podemos colaborar hoy?',
                'estado' => 'autorizado',
                'tools_invocadas' => [],
                'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }

        if ($enFlujoReserva || $intencionReserva) {
            return $this->gestionarFlujoReserva(
                mensaje: $mensaje,
                plantilla: $plantilla,
                cliente: $cliente,
                sucursalId: $sucursalId,
                telefono: $telefono,
                nombreContacto: $nombreContacto,
                conversacion: $conversacion,
                historial: $historial,
                enFlujoReserva: $enFlujoReserva,
                inicio: $inicio
            );
        }

        // 4. RESPUESTA ASISTIDA / SIMULADA O LLM (Consultas de Carta, Alérgenos, Puntos, General)
        $apiKey = $config->obtenerApiKeyIa();
        $tools = $this->gatekeeper->obtenerNombresToolsAutorizadas($plantilla);

        // Si hay API key real de Gemini y está en modo real, invocar endpoint con memoria multi-turn
        if (! empty($apiKey) && $config->ia_proveedor === 'gemini' && ! app()->environment('testing')) {
            try {
                $respuestaLlm = $this->llamarGeminiApi($config->ia_modelo, $apiKey, $mensaje, $plantilla, $cliente, $historial);
                if (! empty($respuestaLlm)) {
                    return [
                        'respuesta' => $respuestaLlm,
                        'estado' => 'autorizado',
                        'tools_invocadas' => array_intersect($tools, ['consultar_menu', 'consultar_alergenos', 'consultar_puntos_cliente', 'consultar_promociones']),
                        'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Error conectando con Gemini API: '.$e->getMessage());
            }
        }

        // Respuesta inteligente según intención con datos reales del sistema y perfil del cliente
        $conversacionIniciada = ! empty($historial) || ($conversacion && $conversacion->mensajes()->count() > 1);
        $respuestaGenerada = $this->generarRespuestaSemantica($mensaje, $plantilla, $cliente, $conversacionIniciada);

        return [
            'respuesta' => $respuestaGenerada['texto'],
            'estado' => 'autorizado',
            'tools_invocadas' => $respuestaGenerada['tools'],
            'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ];
    }

    /**
     * Gestiona el flujo conversacional y persistente de reservas.
     */
    protected function gestionarFlujoReserva(
        string $mensaje,
        CrmIaPlantillaPrivilegio $plantilla,
        ?Cliente $cliente,
        ?int $sucursalId,
        ?string $telefono,
        ?string $nombreContacto,
        ?CrmConversacion $conversacion,
        array $historial,
        bool $enFlujoReserva,
        float $inicio
    ): array {
        if (! $plantilla->permitir_crear_reservas) {
            return [
                'respuesta' => 'Con gusto te informo sobre nuestra carta y horarios, pero actualmente las reservas deben realizarse directamente en nuestro sitio web oficial o comunicándote al restaurante.',
                'estado' => 'privilegio_denegado',
                'tools_invocadas' => [],
                'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }

        $contexto = $conversacion?->getContextoFlujo() ?? [];

        // Inicializar borrador limpio o recuperar el del flujo activo
        $draft = ($enFlujoReserva && ! empty($contexto['datos'])) ? $contexto['datos'] : [
            'nombre' => $cliente?->nombre ?: ($nombreContacto && ! str_starts_with($nombreContacto, 'Visitante') && ! str_starts_with($nombreContacto, 'WhatsApp') ? $nombreContacto : null),
            'fecha' => null,
            'hora' => null,
            'personas' => null,
            'telefono' => $telefono ?: ($cliente?->telefono ?: null),
        ];

        $ultimoCampo = $contexto['ultimo_campo_solicitado'] ?? null;

        // Extraer los datos que el usuario acaba de enviar en este mensaje
        $extraidos = $this->extraerDatosDesdeMensaje($mensaje, $ultimoCampo, $cliente, $telefono, $nombreContacto);

        if (! empty($extraidos['nombre'])) {
            $draft['nombre'] = $extraidos['nombre'];
        }
        if (! empty($extraidos['fecha'])) {
            $draft['fecha'] = $extraidos['fecha'];
        }
        if (! empty($extraidos['hora'])) {
            $draft['hora'] = $extraidos['hora'];
        }
        if (! empty($extraidos['personas'])) {
            $draft['personas'] = $extraidos['personas'];
        }
        if (! empty($extraidos['telefono']) && $extraidos['telefono'] !== 'Canal Web') {
            $draft['telefono'] = $extraidos['telefono'];
        }

        // Si es el inicio de la intención y faltan datos, buscar solo en los turnos inmediatos previos
        if (! $enFlujoReserva && (! $draft['nombre'] || ! $draft['fecha'] || ! $draft['hora'] || ! $draft['personas'])) {
            $draft = $this->completarConHistorialInmediato($draft, $historial, $cliente, $nombreContacto);
        }

        // Validar límite máximo de comensales
        if ($draft['personas'] && $this->gatekeeper->excedeLimiteComensales($draft['personas'], $plantilla)) {
            $conversacion?->limpiarContextoFlujo();

            return [
                'respuesta' => "Para reservas de más de {$plantilla->max_personas_reserva} personas, por favor comunícate directamente con la administración de RestoMaster para coordinar la logística de grupos y eventos especiales.",
                'estado' => 'limite_alcanzado',
                'tools_invocadas' => [],
                'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }

        $faltantes = $this->obtenerCamposFaltantes($draft);

        // Si todos los datos están completos, crear la reserva en base de datos
        if (empty($faltantes)) {
            $resultado = $this->ejecutarCreacionReserva(
                datos: $draft,
                plantilla: $plantilla,
                cliente: $cliente,
                sucursalId: $sucursalId,
                conversacion: $conversacion,
                telefono: $telefono
            );

            $conversacion?->setContextoFlujo([
                'flujo' => 'reserva',
                'estado' => 'completada',
                'reserva_id' => $resultado['reserva']->id,
                'datos' => $draft,
            ]);

            return [
                'respuesta' => $resultado['texto'],
                'estado' => 'autorizado',
                'tools_invocadas' => ['verificar_disponibilidad_mesas', 'crear_reserva'],
                'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }

        // Guardar el estado en progreso con el campo prioritario a pedir
        $campoASolicitar = array_key_first($faltantes);

        $conversacion?->setContextoFlujo([
            'flujo' => 'reserva',
            'estado' => 'en_proceso',
            'datos' => $draft,
            'ultimo_campo_solicitado' => $campoASolicitar,
        ]);

        $respuestaTexto = $this->generarRespuestaEstadoReserva($draft, $faltantes, $plantilla);

        return [
            'respuesta' => $respuestaTexto,
            'estado' => 'autorizado',
            'tools_invocadas' => ['verificar_disponibilidad_mesas'],
            'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ];
    }

    /**
     * Formula una respuesta estructurada que detalla el estado actual del proceso y solicita lo pendiente.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, string>  $faltantes
     */
    protected function generarRespuestaEstadoReserva(array $draft, array $faltantes, CrmIaPlantillaPrivilegio $plantilla): string
    {
        $titularTxt = ! empty($draft['nombre']) ? "{$draft['nombre']} ✅" : 'Pendiente ⏳';
        $personasTxt = ! empty($draft['personas']) ? "{$draft['personas']} personas ✅" : 'Pendiente ⏳';

        if (! empty($draft['fecha']) && ! empty($draft['hora'])) {
            $esHoy = $draft['fecha'] === today()->toDateString();
            $esManana = $draft['fecha'] === today()->addDay()->toDateString();
            $fechaDesc = $esHoy ? 'Hoy' : ($esManana ? 'Mañana' : $draft['fecha']);
            $horaDesc = Carbon::createFromFormat('H:i', $draft['hora'])->format('g:i A');
            $fechaHoraTxt = "{$fechaDesc} a las {$horaDesc} ✅";
        } elseif (! empty($draft['fecha'])) {
            $esHoy = $draft['fecha'] === today()->toDateString();
            $esManana = $draft['fecha'] === today()->addDay()->toDateString();
            $fechaDesc = $esHoy ? 'Hoy' : ($esManana ? 'Mañana' : $draft['fecha']);
            $fechaHoraTxt = "{$fechaDesc} (hora pendiente) ⏳";
        } elseif (! empty($draft['hora'])) {
            $horaDesc = Carbon::createFromFormat('H:i', $draft['hora'])->format('g:i A');
            $fechaHoraTxt = "{$horaDesc} (fecha pendiente) ⏳";
        } else {
            $fechaHoraTxt = 'Pendiente ⏳';
        }

        $resumenEstado = "📋 **Estado del proceso de reserva:**\n".
            "• 👤 **Titular:** {$titularTxt}\n".
            "• 👥 **Comensales:** {$personasTxt}\n".
            "• 📅 **Fecha y hora:** {$fechaHoraTxt}\n\n";

        // Formular la pregunta concisa para el campo faltante
        if (empty($draft['nombre'])) {
            $pregunta = 'Para avanzar con tu solicitud, por favor indícame **a nombre de quién** registramos la mesa.';
        } elseif (empty($draft['personas'])) {
            $pregunta = "¡Mucho gusto, {$draft['nombre']}! ¿Para **cuántas personas** sería la reserva?";
        } elseif (empty($draft['fecha']) && empty($draft['hora'])) {
            $pregunta = "¡Perfecto, {$draft['nombre']}! ¿Para **qué fecha y hora** deseas tu mesa?";
        } elseif (empty($draft['hora'])) {
            $pregunta = "Excelente, {$draft['nombre']}. ¿A **qué hora** te gustaría acompañarnos ese día?";
        } elseif (empty($draft['fecha'])) {
            $pregunta = "Perfecto, {$draft['nombre']}. ¿Para **qué fecha** prefieres la reserva?";
        } else {
            $lista = implode(', ', array_values($faltantes));
            $pregunta = "Por favor indícanos {$lista} para agendar tu mesa.";
        }

        return $resumenEstado.$pregunta;
    }

    /**
     * Determina si el mensaje del usuario expresa una intención de reservar mesa.
     */
    public function esIntencionReserva(string $mensaje): bool
    {
        $mensajeLower = mb_strtolower($mensaje, 'UTF-8');

        $palabrasReserva = [
            'reserva', 'reservar', 'reservacion', 'reservación', 'apartar', 'agendar',
            'mesa para', 'quiero una mesa', 'hay mesa', 'disponibilidad de mesa',
            'separar mesa', 'mesa disponible', 'agendar una mesa', 'agendar mesa',
            'visitar el restaurante',
        ];

        foreach ($palabrasReserva as $palabra) {
            if (str_contains($mensajeLower, $palabra)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detecta si el usuario desea cancelar el flujo de reserva activo.
     */
    protected function esCancelacion(string $texto): bool
    {
        $t = mb_strtolower($texto, 'UTF-8');
        $palabras = ['cancelar', 'olvídalo', 'olvidalo', 'no quiero reservar', 'dejar así', 'dejar asi', 'reiniciar'];

        foreach ($palabras as $p) {
            if (str_contains($t, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrae los datos que el usuario aportó en su mensaje actual, teniendo en cuenta el campo que se le preguntó.
     *
     * @return array{
     *     nombre: ?string,
     *     fecha: ?string,
     *     hora: ?string,
     *     personas: ?int,
     *     telefono: ?string
     * }
     */
    public function extraerDatosDesdeMensaje(
        string $mensaje,
        ?string $ultimoCampoSolicitado = null,
        ?Cliente $cliente = null,
        ?string $telefono = null,
        ?string $nombreContacto = null
    ): array {
        $nombre = $this->extraerNombre($mensaje, $nombreContacto, $cliente);
        $fecha = $this->extraerFecha($mensaje);
        $hora = $this->extraerHora($mensaje);
        $personas = $this->extraerPersonas($mensaje);
        $tel = $this->extraerTelefono($mensaje, $telefono, $cliente);

        $textoLimpio = trim($mensaje);

        // Si estábamos esperando el NOMBRE y el usuario solo respondió con su nombre (ej. "luis eduardo"):
        if (! $nombre && $ultimoCampoSolicitado === 'nombre') {
            if (preg_match('/^[a-záéíóúñA-ZÁÉÍÓÚÑ\s]{2,40}$/u', $textoLimpio) && ! $this->esPalabraReservada($textoLimpio)) {
                $nombre = Str::title($textoLimpio);
            }
        }

        // Si estábamos esperando PERSONAS y el usuario solo escribió el número (ej. "3" o "dos"):
        if (! $personas && $ultimoCampoSolicitado === 'personas') {
            $num = $this->extraerNumeroAislado($textoLimpio);
            if ($num) {
                $personas = $num;
            }
        }

        // Si estábamos esperando HORA o FECHA y el usuario envió la hora o fecha:
        if (! $hora && ($ultimoCampoSolicitado === 'hora' || $ultimoCampoSolicitado === 'fecha')) {
            $h = $this->extraerHora($textoLimpio);
            if ($h) {
                $hora = $h;
            }
        }

        return [
            'nombre' => $nombre,
            'fecha' => $fecha,
            'hora' => $hora,
            'personas' => $personas,
            'telefono' => $tel,
        ];
    }

    /**
     * Extrae un número aislado expresado en dígito o palabra en español.
     */
    protected function extraerNumeroAislado(string $texto): ?int
    {
        $palabras = [
            'un' => 1, 'uno' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4,
            'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10,
        ];

        $t = mb_strtolower(trim($texto), 'UTF-8');
        if (isset($palabras[$t])) {
            return $palabras[$t];
        }
        if (preg_match('/^(\d{1,2})$/', $t, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Valida si un texto es un comando o palabra clave del sistema y no un nombre de persona.
     */
    protected function esPalabraReservada(string $texto): bool
    {
        $palabras = [
            'menu', 'menú', 'carta', 'horario', 'horarios', 'precio', 'precios',
            'hola', 'buenas', 'buenos dias', 'buenas tardes', 'gracias', 'adios',
            'cancelar', 'humano', 'asesor', 'ayuda', 'reserva', 'mesa', 'si', 'no',
            'pedido', 'pedir', 'domicilio', 'delivery', 'para llevar',
        ];

        return in_array(mb_strtolower(trim($texto), 'UTF-8'), $palabras, true);
    }

    /**
     * Revisa únicamente los últimos 2 turnos inmediatos para no jalar datos viejos de conversaciones anteriores.
     */
    protected function completarConHistorialInmediato(
        array $draft,
        array $historial,
        ?Cliente $cliente = null,
        ?string $nombreContacto = null
    ): array {
        $turnosUsuario = array_values(array_filter($historial, fn ($t) => ($t['role'] ?? '') === 'user'));
        $recientes = array_slice($turnosUsuario, -2);

        foreach (array_reverse($recientes) as $turno) {
            $txt = $turno['parts'][0]['text'] ?? '';
            if (empty($txt)) {
                continue;
            }
            if (! $draft['nombre']) {
                $draft['nombre'] = $this->extraerNombre($txt, $nombreContacto, $cliente);
            }
            if (! $draft['fecha']) {
                $draft['fecha'] = $this->extraerFecha($txt);
            }
            if (! $draft['hora']) {
                $draft['hora'] = $this->extraerHora($txt);
            }
            if (! $draft['personas']) {
                $draft['personas'] = $this->extraerPersonas($txt);
            }
        }

        return $draft;
    }

    /**
     * Extrae la fecha de reserva interpretando lenguaje natural en español.
     */
    protected function extraerFecha(string $texto): ?string
    {
        $textoLimpio = mb_strtolower($texto, 'UTF-8');

        if (preg_match('/\bhoy\b/u', $textoLimpio)) {
            return today()->toDateString();
        }

        if (preg_match('/\bpasado\s+mañana\b/u', $textoLimpio)) {
            return today()->addDays(2)->toDateString();
        }

        if (preg_match('/\bmañana\b/u', $textoLimpio)) {
            return today()->addDay()->toDateString();
        }

        // Días de la semana en español
        if (preg_match('/\b(?:el\s+|este\s+)?(lunes|martes|mi[eé]rcoles|miercoles|jueves|viernes|s[aá]bado|sabado|domingo)\b/u', $textoLimpio, $m)) {
            $diasMap = [
                'domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3,
                'miércoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'sábado' => 6,
            ];
            $target = $diasMap[$m[1]] ?? null;
            if ($target !== null) {
                $actual = today()->dayOfWeek;
                $diff = ($target - $actual + 7) % 7;
                if ($diff === 0 && ! str_contains($textoLimpio, 'hoy')) {
                    $diff = 7;
                }

                return today()->addDays($diff)->toDateString();
            }
        }

        // Formato ISO: YYYY-MM-DD
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $textoLimpio, $m)) {
            return $m[1];
        }

        // Formato DD/MM o DD/MM/YYYY
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/', $textoLimpio, $m)) {
            $dia = (int) $m[1];
            $mes = (int) $m[2];
            $anio = ! empty($m[3]) ? (int) $m[3] : (int) today()->year;
            if ($anio < 100) {
                $anio += 2000;
            }
            try {
                return Carbon::createFromDate($anio, $mes, $dia)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Extrae y normaliza la hora de llegada en formato 24h (H:i).
     */
    protected function extraerHora(string $texto): ?string
    {
        $textoLimpio = mb_strtolower($texto, 'UTF-8');

        // Buscar patrón tipo "a las 6 de la tarde", "para las 6:30 pm", "6 pm"
        if (preg_match('/(?:a\s+las?|para\s+las?)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm|de\s+la\s+tarde|de\s+la\s+noche|de\s+la\s+mañana|del\s+mediod[ií]a)?/u', $textoLimpio, $m)) {
            return $this->normalizarHora((int) $m[1], (int) ($m[2] ?? 0), $m[3] ?? '');
        }

        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm|de\s+la\s+tarde|de\s+la\s+noche|de\s+la\s+mañana|del\s+mediod[ií]a)\b/u', $textoLimpio, $m)) {
            return $this->normalizarHora((int) $m[1], (int) ($m[2] ?? 0), $m[3] ?? '');
        }

        if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $textoLimpio, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    /**
     * Normaliza horas 12h a formato 24h.
     */
    protected function normalizarHora(int $h, int $min, string $mod): string
    {
        $mod = trim($mod);

        if (str_contains($mod, 'tarde') || str_contains($mod, 'noche') || str_contains($mod, 'pm')) {
            if ($h < 12) {
                $h += 12;
            }
        } elseif (str_contains($mod, 'mañana') || str_contains($mod, 'am')) {
            if ($h === 12) {
                $h = 0;
            }
        } else {
            if ($h >= 1 && $h <= 6) {
                $h += 12;
            } elseif ($h >= 7 && $h <= 11) {
                $h += 12;
            }
        }

        return sprintf('%02d:%02d', $h, $min);
    }

    /**
     * Extrae la cantidad de comensales / personas.
     */
    protected function extraerPersonas(string $texto): ?int
    {
        $textoLimpio = mb_strtolower($texto, 'UTF-8');

        $palabrasNumero = [
            'un' => 1, 'uno' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4,
            'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10,
            'once' => 11, 'doce' => 12,
        ];

        // 1. "3 personas", "3 comensales", "3 pax"
        if (preg_match('/\b(\d{1,2})\s*(?:personas?|comensales|pax|amigos|invitados)\b/u', $textoLimpio, $m)) {
            return (int) $m[1];
        }

        // 2. "tres personas", "dos personas"
        if (preg_match('/\b(un[ao]?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\s*(?:personas?|comensales|pax|invitados)\b/u', $textoLimpio, $m)) {
            return $palabrasNumero[$m[1]] ?? null;
        }

        // 3. "seremos 3", "somos 3"
        if (preg_match('/\b(?:seremos|somos)\s+(\d{1,2})\b/u', $textoLimpio, $m)) {
            return (int) $m[1];
        }

        // 4. "seremos tres", "somos dos"
        if (preg_match('/\b(?:seremos|somos)\s+(un[ao]?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\b/u', $textoLimpio, $m)) {
            return $palabrasNumero[$m[1]] ?? null;
        }

        // 5. "mesa para 4", "mesa de 4"
        if (preg_match('/\bmesa\s+(?:para|de)\s+(\d{1,2})\b/u', $textoLimpio, $m)) {
            return (int) $m[1];
        }

        // 6. "mesa para cuatro", "mesa de dos"
        if (preg_match('/\bmesa\s+(?:para|de)\s+(un[ao]?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\b/u', $textoLimpio, $m)) {
            return $palabrasNumero[$m[1]] ?? null;
        }

        return null;
    }

    /**
     * Extrae el nombre de contacto para la reserva.
     */
    protected function extraerNombre(string $texto, ?string $nombreContacto = null, ?Cliente $cliente = null): ?string
    {
        // 1. Buscar en el texto actual si se presentó explícitamente
        if (preg_match('/(?:mi\s+nombre\s+es|me\s+llamo|a\s+nombre\s+de|soy)\s+([a-záéíóúñA-ZÁÉÍÓÚÑ\s]{2,40})/iu', $texto, $m)) {
            $partes = preg_split('/[,;\.\n]|(?:\b(?:seremos|somos|para|con|telefono|cel|whatsapp)\b)|\s+y\s+/iu', $m[1]);
            $raw = trim($partes[0] ?? '');
            $raw = preg_replace('/\s+y\s*$/iu', '', $raw);
            $raw = trim($raw);
            if (mb_strlen($raw) >= 3 && ! in_array(mb_strtolower($raw), ['un', 'una', 'el', 'la', 'los', 'las', 'cliente', 'usuario', 'amigo'], true)) {
                return Str::title($raw);
            }
        }

        // 2. Si ya viene de perfil del cliente
        if ($cliente && ! empty($cliente->nombre)) {
            return $cliente->nombre;
        }

        // 3. Si viene del nombre de contacto de la conversación (y no es genérico)
        if ($nombreContacto && ! str_starts_with($nombreContacto, 'Visitante Web') && ! str_starts_with($nombreContacto, 'WhatsApp')) {
            return $nombreContacto;
        }

        return null;
    }

    /**
     * Extrae el teléfono de contacto.
     */
    protected function extraerTelefono(string $texto, ?string $telefono = null, ?Cliente $cliente = null): ?string
    {
        if (preg_match('/(?:tel[eé]fono|celular|m[oó]vil|whatsapp|contacto)?\s*(\+?\d[\d\s\-]{7,15}\d)/iu', $texto, $m)) {
            $num = preg_replace('/[^\d+]/', '', $m[1]);
            if (strlen($num) >= 7) {
                return $num;
            }
        }

        if ($telefono) {
            return $telefono;
        }

        if ($cliente && ! empty($cliente->telefono)) {
            return $cliente->telefono;
        }

        return 'Canal Web';
    }

    /**
     * Determina los campos que aún faltan para poder procesar la reserva.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, string>
     */
    protected function obtenerCamposFaltantes(array $datos): array
    {
        $faltantes = [];
        if (empty($datos['nombre'])) {
            $faltantes['nombre'] = 'tu nombre';
        }
        if (empty($datos['personas'])) {
            $faltantes['personas'] = 'la cantidad de comensales';
        }
        if (empty($datos['fecha'])) {
            $faltantes['fecha'] = 'la fecha deseada';
        }
        if (empty($datos['hora'])) {
            $faltantes['hora'] = 'la hora deseada';
        }

        return $faltantes;
    }

    /**
     * Ejecuta la persistencia de la reserva en estado solicitada/agendada (sin auto-confirmar).
     */
    protected function ejecutarCreacionReserva(
        array $datos,
        CrmIaPlantillaPrivilegio $plantilla,
        ?Cliente $cliente = null,
        ?int $sucursalId = null,
        ?CrmConversacion $conversacion = null,
        ?string $telefono = null
    ): array {
        $reservaService = app(ReservaService::class);
        $sucursalFinal = $sucursalId ?: (Sucursal::value('id') ?? null);

        $telefonoFinal = $datos['telefono'] ?: ($telefono ?: ($cliente?->telefono ?: 'Canal Web'));

        // La reserva se agenda en estado 'solicitada' sin confirmarse automáticamente
        $reserva = $reservaService->crear([
            'sucursal_id' => $sucursalFinal,
            'cliente_id' => $cliente?->id,
            'nombre_contacto' => $datos['nombre'],
            'telefono_contacto' => $telefonoFinal,
            'fecha' => $datos['fecha'],
            'hora_llegada' => $datos['hora'],
            'personas' => (int) $datos['personas'],
            'duracion_min' => 120,
            'notas' => 'Reserva agendada por IA Concierge desde el chat (pendiente de confirmación por el restaurante).',
        ], 'ia_concierge');

        $mesaTexto = 'Asignación al ser confirmada en el restaurante';

        // Actualizar el nombre de contacto en la conversación si aún era genérico
        if ($conversacion && $datos['nombre']) {
            $nombreActual = $conversacion->nombre_contacto ?? '';
            if (empty($nombreActual) || str_starts_with($nombreActual, 'Visitante Web') || str_starts_with($nombreActual, 'WhatsApp')) {
                $conversacion->update(['nombre_contacto' => $datos['nombre']]);
            }
        }

        $esHoy = $datos['fecha'] === today()->toDateString();
        $esManana = $datos['fecha'] === today()->addDay()->toDateString();
        $fechaDesc = $esHoy ? "Hoy ({$datos['fecha']})" : ($esManana ? "Mañana ({$datos['fecha']})" : $datos['fecha']);

        $horaFormatted = Carbon::createFromFormat('H:i', $datos['hora'])->format('g:i A');

        $texto = "📅 **¡Tu reserva ha sido agendada con éxito!**\n".
            "━━━━━━━━━━━━━━━━━━━━━━\n".
            "📋 **Detalles de la Reserva (#{$reserva->id}):**\n".
            "• 👤 **Titular:** {$datos['nombre']}\n".
            "• 👥 **Comensales:** {$datos['personas']} personas\n".
            "• 📅 **Fecha:** {$fechaDesc}\n".
            "• ⏰ **Hora:** {$horaFormatted}\n".
            "• 📌 **Estado:** Agendada (Pendiente de confirmación)\n".
            "• 📍 **Mesa:** {$mesaTexto}\n".
            "━━━━━━━━━━━━━━━━━━━━━━\n".
            'Tu solicitud ha sido registrada en nuestro sistema de reservas. El equipo de RestoMaster revisará la disponibilidad y confirmará tu mesa a la brevedad. Si necesitas modificar algún detalle, solo avísanos por este chat.';

        return [
            'texto' => $texto,
            'reserva' => $reserva,
        ];
    }

    /**
     * Genera respuesta semántica usando los datos reales de RestoMaster y el perfil del huésped.
     */
    protected function generarRespuestaSemantica(
        string $mensaje,
        CrmIaPlantillaPrivilegio $plantilla,
        ?Cliente $cliente = null,
        bool $conversacionIniciada = false
    ): array {
        $mensajeLower = mb_strtolower($mensaje, 'UTF-8');
        $toolsInvocadas = [];

        // 1. Consulta de Alérgenos / Restricciones Alimentarias
        if (str_contains($mensajeLower, 'alerg') || str_contains($mensajeLower, 'intoleran') || str_contains($mensajeLower, 'celiac') || str_contains($mensajeLower, 'gluten') || str_contains($mensajeLower, 'marisco') || str_contains($mensajeLower, 'lactosa') || str_contains($mensajeLower, 'maní')) {
            if ($plantilla->permitir_alergenos) {
                $toolsInvocadas[] = 'consultar_alergenos';
                $alergiaNota = ($cliente && ! empty($cliente->alergias))
                    ? " Hemos tomado nota especial de tu registro de alérgenos ({$cliente->alergias}) para alertar a cocina."
                    : '';

                $texto = "En RestoMaster la seguridad alimentaria es nuestra máxima prioridad. Todos nuestros platos cuentan con ficha técnica de alérgenos y trazabilidad estricta.{$alergiaNota} ¿Deseas consultar los ingredientes de algún plato específico de la carta?";

                return ['texto' => $texto, 'tools' => $toolsInvocadas];
            }
        }

        // 2. Consulta de Puntos de Fidelidad / Club VIP
        if (str_contains($mensajeLower, 'punto') || str_contains($mensajeLower, 'fidelidad') || str_contains($mensajeLower, 'club') || str_contains($mensajeLower, 'mi saldo') || str_contains($mensajeLower, 'mis puntos')) {
            if ($plantilla->permitir_puntos_vip) {
                $toolsInvocadas[] = 'consultar_puntos_cliente';
                if ($cliente) {
                    $puntos = (int) ($cliente->puntos_fidelidad ?? 0);
                    $tier = strtoupper($cliente->tier ?: 'Ocasional');
                    $texto = "¡Hola {$cliente->nombre}! Tienes {$puntos} puntos acumulados en el Club de Fidelidad RestoMaster (Nivel: {$tier}). Puedes redimirlos directamente en tus visitas y reservas.";
                } else {
                    $texto = 'En nuestro Club de Fidelidad acumulas puntos por cada visita para redimir en experiencias gastronómicas exclusivas. Al registrar tu cuenta o número en sala, tus consumos se acreditan automáticamente.';
                }

                return ['texto' => $texto, 'tools' => $toolsInvocadas];
            }
        }

        // 3. Consulta de Promociones / Especiales
        if (str_contains($mensajeLower, 'promocion') || str_contains($mensajeLower, 'promoción') || str_contains($mensajeLower, 'descuento') || str_contains($mensajeLower, 'especial') || str_contains($mensajeLower, 'beneficio')) {
            if ($plantilla->permitir_promociones) {
                $toolsInvocadas[] = 'consultar_promociones';
                $texto = '¡Por supuesto! Contamos con experiencias gastronómicas especiales de autor, maridaje selecto y beneficios de temporada para miembros de nuestro Club. Te invitamos a consultar nuestra carta digital para más detalles.';

                return ['texto' => $texto, 'tools' => $toolsInvocadas];
            }
        }

        // 4. Consulta de Menú
        if (str_contains($mensajeLower, 'menú') || str_contains($mensajeLower, 'menu') || str_contains($mensajeLower, 'plato') || str_contains($mensajeLower, 'carta') || str_contains($mensajeLower, 'recomiend')) {
            if ($plantilla->permitir_menu) {
                $toolsInvocadas[] = 'consultar_menu';
                $menuService = app(MenuService::class);
                $categorias = $menuService->obtenerMenuPublico();
                $platosMuestra = [];

                foreach ($categorias->take(2) as $cat) {
                    $prods = collect($cat['productos'] ?? [])->take(2);
                    foreach ($prods as $prod) {
                        $precioTxt = $plantilla->permitir_precios ? ' ($'.number_format($prod['precio'] ?? 0, 0, ',', '.').' COP)' : '';
                        $platosMuestra[] = "• {$prod['nombre']}{$precioTxt}";
                    }
                }

                $saludoPersonal = $cliente ? "¡Un gusto saludarte, {$cliente->nombre}! " : '¡Con gusto! ';
                $texto = "{$saludoPersonal}En RestoMaster ofrecemos una selecta variedad gastronómica:\n\n".
                    implode("\n", $platosMuestra).
                    "\n\n¿Te gustaría consultar ingredientes o verificar mesa para hoy?";

                return ['texto' => $texto, 'tools' => $toolsInvocadas];
            }
        }

        // 5. Consulta sobre Pedidos / Domicilios / Delivery / Comida para llevar (Enlace directo al asistente de delivery)
        if ($this->esConsultaPedidos($mensajeLower)) {
            $nombrePersonal = $cliente ? " {$cliente->nombre}" : '';
            $linkDelivery = route('delivery.publico');

            $texto = "¡Hola{$nombrePersonal}! ¡Con gusto! Para realizar tu pedido a domicilio puedes ingresar a nuestro **asistente de pedidos y delivery en línea** en el siguiente enlace:\n\n".
                "🛵 **Pedir a Domicilio:**\n{$linkDelivery}\n\n".
                "Desde allí podrás explorar el catálogo completo, seleccionar tus platos favoritos con fotos y precios, armar tu pedido y hacer seguimiento en tiempo real.\n\n".
                ($plantilla->permitir_crear_reservas
                    ? 'Y si en lugar de delivery prefieres visitarnos en el restaurante para vivir la experiencia en sala, ¡también puedo **agendar tu mesa en segundos**! ¿Deseas hacer tu pedido por el enlace o te gustaría reservar una mesa?'
                    : '¿Te gustaría que te recomiende alguna especialidad de la carta?');

            return ['texto' => $texto, 'tools' => []];
        }

        // 6. Consulta sobre Horarios de Atención
        if (str_contains($mensajeLower, 'horario') || str_contains($mensajeLower, 'abren') || str_contains($mensajeLower, 'cierran') || str_contains($mensajeLower, 'a que hora') || str_contains($mensajeLower, 'a qué hora') || str_contains($mensajeLower, 'abierto')) {
            $cierreHorario = $plantilla->permitir_crear_reservas
                ? '¿Te gustaría que verifiquemos disponibilidad y agendemos tu mesa para hoy o prefieres consultar los platos recomendados?'
                : '¿Deseas consultar nuestras opciones y recomendaciones de la carta?';

            $texto = "En RestoMaster abrimos nuestras puertas de martes a domingo de 12:00 PM a 11:00 PM (lunes cerrado por descanso del equipo). ✨\n\n{$cierreHorario}";

            return ['texto' => $texto, 'tools' => []];
        }

        // 7. Saludo puro (solo si el mensaje es exclusivamente un saludo)
        if ($this->esSaludoPuro($mensaje)) {
            $nombreHuesped = $cliente ? ", {$cliente->nombre}" : '';

            return [
                'texto' => "¡Hola{$nombreHuesped}! Soy la anfitriona virtual de RestoMaster. ".
                    ($plantilla->permitir_menu ? 'Puedo orientarte con los platos de nuestra carta, ' : '').
                    ($plantilla->permitir_crear_reservas ? 'verificar disponibilidad y agendar tu mesa. ' : '').
                    '¿En qué puedo colaborarte hoy?',
                'tools' => [],
            ];
        }

        // 8. Consulta no reconocida o fuera de alcance: Persuadir al usuario hacia las opciones disponibles (Anti-bucle)
        $nombreHuesped = $cliente ? " {$cliente->nombre}" : '';
        $opciones = [];
        if ($plantilla->permitir_crear_reservas) {
            $opciones[] = '• 📅 **Agendar tu reserva:** Apartamos tu mesa en segundos para que disfrutes una experiencia memorable.';
        }
        if ($plantilla->permitir_menu) {
            $opciones[] = '• 🍽️ **Consultar nuestra carta:** Te recomiendo nuestros platos más destacados y cortes de autor.';
        }
        if ($plantilla->permitir_alergenos) {
            $opciones[] = '• 🌾 **Alérgenos e ingredientes:** Revisamos detalles nutricionales o requerimientos alimentarios.';
        }
        $opciones[] = '• 👤 **Hablar con un asesor:** Te comunico con el equipo del restaurante.';

        $listaOpciones = implode("\n", $opciones);
        $cierreOpciones = $plantilla->permitir_crear_reservas
            ? '¿Te gustaría que te reserve una mesa para hoy o prefieres consultar las recomendaciones de la carta?'
            : '¿Te gustaría consultar las opciones de nuestra carta gastronómica?';

        $texto = "Por el momento no dispongo de esa opción directamente por este canal{$nombreHuesped}, pero estoy aquí para ayudarte a planear la mejor velada en RestoMaster ✨.\n\n".
            "Puedo ayudarte con cualquiera de estas alternativas:\n".
            $listaOpciones.
            "\n\n{$cierreOpciones}";

        return [
            'texto' => $texto,
            'tools' => [],
        ];
    }

    /**
     * Evalúa si un mensaje corresponde a una consulta sobre pedidos, domicilios, delivery o comida para llevar.
     */
    public function esConsultaPedidos(string $mensaje): bool
    {
        $m = mb_strtolower($mensaje, 'UTF-8');
        $terminos = [
            'pedido', 'pedidos', 'pedir', 'ordenar', 'orden', 'órden', 'ordenes', 'órdenes',
            'domicilio', 'domicilios', 'delivery', 'para llevar', 'takeout', 'take out',
            'recoger', 'a domicilio', 'hacer un pedido', 'comprar comida', 'pedir comida',
            'enviar a casa', 'mandar a casa', 'traer a casa', 'puedo pedir',
        ];

        foreach ($terminos as $termino) {
            if (str_contains($m, $termino)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina si el mensaje del comensal es exclusivamente una fórmula de saludo.
     */
    public function esSaludoPuro(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje), 'UTF-8');
        $m = preg_replace('/[^\p{L}\s]/u', ' ', $m);
        $m = preg_replace('/\s+/', ' ', trim($m));

        if (empty($m)) {
            return false;
        }

        $saludosDirectos = [
            'hola', 'holaa', 'holaaa', 'buenas', 'buen dia', 'buen día', 'buenos dias', 'buenos días',
            'buenas tardes', 'buenas noches', 'saludos', 'hey', 'hello', 'hi', 'que tal', 'qué tal',
            'hola buenas', 'hola buenas tardes', 'hola buenas noches', 'hola buen dia', 'hola buenos dias',
            'como estas', 'cómo estás', 'como va', 'cómo va', 'hola como estas', 'hola cómo estás',
        ];

        if (in_array($m, $saludosDirectos, true)) {
            return true;
        }

        // Si la frase es corta (<= 4 palabras) y está compuesta exclusivamente de términos de saludo
        $palabrasSaludo = [
            'hola', 'holaa', 'buenas', 'buen', 'bueno', 'buenos', 'dia', 'dias', 'tarde', 'tardes',
            'noche', 'noches', 'saludo', 'saludos', 'hey', 'hello', 'hi', 'que', 'tal', 'como', 'estas',
            'va', 'bienvenido', 'bienvenidos',
        ];

        $tokens = explode(' ', $m);
        if (count($tokens) <= 4) {
            $todasSonSaludos = true;
            foreach ($tokens as $token) {
                if (! in_array($token, $palabrasSaludo, true)) {
                    $todasSonSaludos = false;
                    break;
                }
            }
            if ($todasSonSaludos) {
                return true;
            }
        }

        return false;
    }

    /**
     * Llamada a Google Gemini API (REST) con timeout controlado y soporte multi-turn.
     */
    protected function llamarGeminiApi(
        string $modelo,
        string $apiKey,
        string $mensaje,
        CrmIaPlantillaPrivilegio $plantilla,
        ?Cliente $cliente = null,
        array $historial = []
    ): ?string {
        $systemPrompt = $this->gatekeeper->construirSystemPrompt($plantilla, $cliente);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        // Construir contenido multi-turn para contexto conversacional
        $contents = [];
        foreach ($historial as $turn) {
            $role = ($turn['role'] ?? '') === 'user' ? 'user' : 'model';
            $text = $turn['parts'][0]['text'] ?? '';
            if (! empty($text)) {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => (string) $text]],
                ];
            }
        }

        // Añadir el mensaje actual como último turno del usuario
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $mensaje]],
        ];

        $response = Http::timeout(8)->post($url, [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => 300,
                'temperature' => 0.4,
            ],
        ]);

        if ($response->successful()) {
            return $response->json('candidates.0.content.parts.0.text');
        }

        return null;
    }

    /**
     * Procesa una conversación omnicanal con contexto y retorno enriquecido.
     */
    public function procesarConversacion(
        string $mensaje,
        ?int $clienteId = null,
        array $historial = [],
        ?int $sucursalId = null,
        ?string $telefono = null,
        ?string $nombreContacto = null,
        ?CrmConversacion $conversacion = null
    ): array {
        $res = $this->procesarMensaje(
            mensaje: $mensaje,
            telefono: $telefono,
            sucursalId: $sucursalId,
            clienteId: $clienteId,
            historial: $historial,
            nombreContacto: $nombreContacto,
            conversacion: $conversacion
        );

        return [
            'respuesta' => $res['respuesta'],
            'estado' => $res['estado'],
            'herramientas_ejecutadas' => $res['tools_invocadas'] ?? [],
            'tokens_estimados' => (int) round(strlen($res['respuesta']) / 4),
            'tiempo_ms' => $res['tiempo_ms'],
        ];
    }
}
