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
     * Procesa un mensaje de usuario a través del flujo de seguridad y agente de IA.
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

        // 3. INTENCIÓN DE RESERVA / GESTIÓN DE MESA
        $intencionReserva = $this->esIntencionReserva($mensaje, $historial);

        if ($intencionReserva) {
            if (! $plantilla->permitir_crear_reservas) {
                return [
                    'respuesta' => 'Con gusto te informo sobre nuestra carta y horarios, pero actualmente las reservas deben realizarse directamente en nuestro sitio web oficial o comunicándote al restaurante.',
                    'estado' => 'privilegio_denegado',
                    'tools_invocadas' => [],
                    'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
                ];
            }

            $datosReserva = $this->extraerDatosReserva($mensaje, $historial, $cliente, $telefono, $nombreContacto);

            // Validar si la cantidad de comensales excede el límite permitido por la plantilla
            if ($datosReserva['personas'] && $this->gatekeeper->excedeLimiteComensales($datosReserva['personas'], $plantilla)) {
                return [
                    'respuesta' => "Para reservas de más de {$plantilla->max_personas_reserva} personas, por favor comunícate directamente con la administración de RestoMaster para coordinar la logística de grupos y eventos especiales.",
                    'estado' => 'limite_alcanzado',
                    'tools_invocadas' => [],
                    'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
                ];
            }

            $faltantes = $this->obtenerCamposFaltantes($datosReserva);

            // Si contamos con todos los datos necesarios, crear y confirmar la reserva de inmediato
            if (empty($faltantes)) {
                $resultado = $this->ejecutarCreacionReserva(
                    datos: $datosReserva,
                    plantilla: $plantilla,
                    cliente: $cliente,
                    sucursalId: $sucursalId,
                    conversacion: $conversacion,
                    telefono: $telefono
                );

                return [
                    'respuesta' => $resultado['texto'],
                    'estado' => 'autorizado',
                    'tools_invocadas' => ['verificar_disponibilidad_mesas', 'crear_reserva'],
                    'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
                ];
            }

            // Si faltan datos clave, solicitar específicamente lo que falta de manera cálida y concisa
            return [
                'respuesta' => $this->generarPreguntaDatosFaltantes($datosReserva, $faltantes, $plantilla),
                'estado' => 'autorizado',
                'tools_invocadas' => ['verificar_disponibilidad_mesas'],
                'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
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
        $respuestaGenerada = $this->generarRespuestaSemantica($mensaje, $plantilla, $cliente);

        return [
            'respuesta' => $respuestaGenerada['texto'],
            'estado' => 'autorizado',
            'tools_invocadas' => $respuestaGenerada['tools'],
            'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ];
    }

    /**
     * Determina si el mensaje del usuario expresa una intención de reservar mesa.
     */
    public function esIntencionReserva(string $mensaje, array $historial = []): bool
    {
        $mensajeLower = mb_strtolower($mensaje, 'UTF-8');

        $palabrasReserva = [
            'reserva', 'reservar', 'reservacion', 'reservación', 'apartar', 'agendar',
            'mesa para', 'quiero una mesa', 'hay mesa', 'disponibilidad de mesa',
            'separar mesa', 'mesa disponible', 'comensales', 'visitar el restaurante',
        ];

        foreach ($palabrasReserva as $palabra) {
            if (str_contains($mensajeLower, $palabra)) {
                return true;
            }
        }

        // Si el comensal menciona personas o acompañantes tras una pregunta de reserva previa
        if (str_contains($mensajeLower, 'persona') || str_contains($mensajeLower, 'seremos') || str_contains($mensajeLower, 'somos')) {
            foreach (array_reverse($historial) as $turno) {
                $txt = mb_strtolower($turno['parts'][0]['text'] ?? '', 'UTF-8');
                if (str_contains($txt, 'reserva') || str_contains($txt, 'mesa') || str_contains($txt, 'agendar')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extrae los datos esenciales de reserva desde el mensaje actual y el contexto histórico.
     *
     * @return array{
     *     nombre: ?string,
     *     fecha: ?string,
     *     hora: ?string,
     *     personas: ?int,
     *     telefono: ?string
     * }
     */
    public function extraerDatosReserva(
        string $mensaje,
        array $historial = [],
        ?Cliente $cliente = null,
        ?string $telefono = null,
        ?string $nombreContacto = null
    ): array {
        $nombre = $this->extraerNombre($mensaje, $nombreContacto, $cliente);
        $fecha = $this->extraerFecha($mensaje);
        $hora = $this->extraerHora($mensaje);
        $personas = $this->extraerPersonas($mensaje);
        $telefonoFinal = $this->extraerTelefono($mensaje, $telefono, $cliente);

        // Si falta alguno de los datos obligatorios, buscar en el historial de mensajes del usuario
        if (! $nombre || ! $fecha || ! $hora || ! $personas) {
            foreach (array_reverse($historial) as $turno) {
                if (($turno['role'] ?? '') === 'user') {
                    $txtTurno = $turno['parts'][0]['text'] ?? '';
                    if (! empty($txtTurno)) {
                        if (! $nombre) {
                            $nombre = $this->extraerNombre($txtTurno, $nombreContacto, $cliente);
                        }
                        if (! $fecha) {
                            $fecha = $this->extraerFecha($txtTurno);
                        }
                        if (! $hora) {
                            $hora = $this->extraerHora($txtTurno);
                        }
                        if (! $personas) {
                            $personas = $this->extraerPersonas($txtTurno);
                        }
                    }
                }
            }
        }

        return [
            'nombre' => $nombre,
            'fecha' => $fecha,
            'hora' => $hora,
            'personas' => $personas,
            'telefono' => $telefonoFinal,
        ];
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
            // Sin modifier explícito: en restaurante 1..6 suele ser tarde (13..18) y 7..11 cena (19..23)
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
            if (mb_strlen($raw) >= 3 && ! in_array(mb_strtolower($raw), ['un', 'una', 'el', 'la', 'los', 'las', 'cliente', 'usuario', 'amigo'])) {
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
     * @return array<string, string>
     */
    protected function obtenerCamposFaltantes(array $datos): array
    {
        $faltantes = [];
        if (empty($datos['nombre'])) {
            $faltantes['nombre'] = 'tu nombre';
        }
        if (empty($datos['fecha'])) {
            $faltantes['fecha'] = 'la fecha';
        }
        if (empty($datos['hora'])) {
            $faltantes['hora'] = 'la hora deseada';
        }
        if (empty($datos['personas'])) {
            $faltantes['personas'] = 'la cantidad de comensales';
        }

        return $faltantes;
    }

    /**
     * Formula una respuesta natural que solicita exclusivamente los datos pendientes.
     */
    protected function generarPreguntaDatosFaltantes(array $datos, array $faltantes, CrmIaPlantillaPrivilegio $plantilla): string
    {
        $saludo = ! empty($datos['nombre']) ? "¡Con gusto, {$datos['nombre']}! " : '¡Excelente! ';

        if (count($faltantes) === 4) {
            return "¡Excelente! Tenemos disponibilidad para grupos de hasta {$plantilla->max_personas_reserva} personas. Para agendar tu mesa, por favor indícame tu nombre, la fecha, hora deseada y la cantidad de comensales.";
        }

        // Acuse de recibo de los datos ya capturados
        $partesReconocidas = [];
        if (! empty($datos['personas'])) {
            $partesReconocidas[] = "para {$datos['personas']} personas";
        }
        if (! empty($datos['fecha'])) {
            $esHoy = $datos['fecha'] === today()->toDateString();
            $esManana = $datos['fecha'] === today()->addDay()->toDateString();
            $partesReconocidas[] = 'para '.($esHoy ? 'hoy' : ($esManana ? 'mañana' : $datos['fecha']));
        }
        if (! empty($datos['hora'])) {
            $horaForm = Carbon::createFromFormat('H:i', $datos['hora'])->format('g:i A');
            $partesReconocidas[] = "a las {$horaForm}";
        }

        $reconocidoTexto = ! empty($partesReconocidas)
            ? 'Tomamos nota de tu solicitud '.implode(' ', $partesReconocidas).'. '
            : '';

        $listaFaltantes = implode(', ', array_values($faltantes));

        return "{$saludo}{$reconocidoTexto}Para completar tu reserva, por favor indícanos {$listaFaltantes}.";
    }

    /**
     * Ejecuta la persistencia de la reserva y su posterior intento de autoasignación/confirmación.
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

        $reserva = $reservaService->crear([
            'sucursal_id' => $sucursalFinal,
            'cliente_id' => $cliente?->id,
            'nombre_contacto' => $datos['nombre'],
            'telefono_contacto' => $telefonoFinal,
            'fecha' => $datos['fecha'],
            'hora_llegada' => $datos['hora'],
            'personas' => (int) $datos['personas'],
            'duracion_min' => 120,
            'notas' => 'Reserva agendada automáticamente por IA Concierge desde el chat.',
        ], 'ia_concierge');

        $mesaTexto = '';
        try {
            $reservaConfirmada = $reservaService->confirmar($reserva);
            $mesa = $reservaConfirmada->mesas->first();
            if ($mesa) {
                $mesaTexto = " Te hemos preasignado la **Mesa #{$mesa->numero}**.";
            }
        } catch (\Throwable $e) {
            // Si no hay mesa disponible para confirmar de inmediato, se mantiene en estado solicitada
        }

        // Actualizar el nombre de contacto en la conversación si aún era genérico
        if ($conversacion && $datos['nombre']) {
            $nombreActual = $conversacion->nombre_contacto ?? '';
            if (empty($nombreActual) || str_starts_with($nombreActual, 'Visitante Web') || str_starts_with($nombreActual, 'WhatsApp')) {
                $conversacion->update(['nombre_contacto' => $datos['nombre']]);
            }
        }

        $esHoy = $datos['fecha'] === today()->toDateString();
        $esManana = $datos['fecha'] === today()->addDay()->toDateString();
        $fechaDesc = $esHoy ? 'hoy' : ($esManana ? 'mañana' : $datos['fecha']);

        $horaFormatted = Carbon::createFromFormat('H:i', $datos['hora'])->format('g:i A');

        $texto = "¡Listo, {$datos['nombre']}! 🎉 Tu mesa ha sido agendada con éxito para **{$fechaDesc} a las {$horaFormatted}** para **{$datos['personas']} personas** (Reserva #{$reserva->id}).{$mesaTexto}\n\n¡Te esperamos con los brazos abiertos en RestoMaster para brindarte una gran experiencia gastronómica! Si necesitas modificar algún detalle, solo avísanos por este chat.";

        return [
            'texto' => $texto,
            'reserva' => $reserva,
        ];
    }

    /**
     * Genera respuesta semántica usando los datos reales de RestoMaster y el perfil del huésped.
     */
    protected function generarRespuestaSemantica(string $mensaje, CrmIaPlantillaPrivilegio $plantilla, ?Cliente $cliente = null): array
    {
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

        // 5. Saludo o consulta general
        $nombreHuesped = $cliente ? ", {$cliente->nombre}" : '';

        return [
            'texto' => "¡Hola{$nombreHuesped}! Soy la anfitriona virtual de RestoMaster. ".
                ($plantilla->permitir_menu ? 'Puedo orientarte con los platos de nuestra carta, ' : '').
                ($plantilla->permitir_crear_reservas ? 'verificar disponibilidad y agendar tu mesa. ' : '').
                '¿En qué puedo colaborarte hoy?',
            'tools' => [],
        ];
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
