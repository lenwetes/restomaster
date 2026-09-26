<?php

namespace App\Services\Ai;

use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmIaPlantillaPrivilegio;
use App\Services\MenuService;
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
    public function procesarMensaje(string $mensaje, ?string $telefono = null, ?int $sucursalId = null, ?int $clienteId = null, array $historial = []): array
    {
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

        // 3. REGLA PRIVILEGIO: Consultas sobre reservas cuando están deshabilitadas
        $mensajeLower = mb_strtolower($mensaje, 'UTF-8');
        $intencionReserva = str_contains($mensajeLower, 'reserva') || str_contains($mensajeLower, 'mesa para') || str_contains($mensajeLower, 'apartar mesa');

        if ($intencionReserva && ! $plantilla->permitir_crear_reservas) {
            return [
                'respuesta' => 'Con gusto te informo sobre nuestra carta y horarios, pero actualmente las reservas deben realizarse directamente en nuestro sitio web oficial o comunicándote al restaurante.',
                'estado' => 'privilegio_denegado',
                'tools_invocadas' => [],
                'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }

        // 4. REGLA LÍMITE DE COMENSALES: Si detecta solicitud que excede el tope de la plantilla
        preg_match('/\b(\d{1,3})\s*(personas|comensales|pax|amigos|invitados)\b/i', $mensaje, $matches);
        if (! empty($matches[1])) {
            $comensales = (int) $matches[1];
            if ($this->gatekeeper->excedeLimiteComensales($comensales, $plantilla)) {
                return [
                    'respuesta' => "Para reservas de más de {$plantilla->max_personas_reserva} personas, por favor comunícate directamente con la administración de RestoMaster para coordinar la logística de grupos y eventos especiales.",
                    'estado' => 'limite_alcanzado',
                    'tools_invocadas' => [],
                    'tiempo_ms' => (int) round((microtime(true) - $inicio) * 1000),
                ];
            }
        }

        // 5. RESPUESTA ASISTIDA / SIMULADA O LLM
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
                        'tools_invocadas' => array_intersect($tools, ['consultar_menu', 'consultar_alergenos', 'consultar_puntos_cliente']),
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

        // 5. Consulta de Reserva válida
        if (str_contains($mensajeLower, 'reserva') || str_contains($mensajeLower, 'mesa')) {
            if ($plantilla->permitir_crear_reservas) {
                $toolsInvocadas[] = 'verificar_disponibilidad_mesas';

                return [
                    'texto' => "¡Excelente! Tenemos disponibilidad para grupos de hasta {$plantilla->max_personas_reserva} personas. Para agendar tu mesa, por favor indícame tu nombre, la fecha, hora deseada y la cantidad de comensales.",
                    'tools' => $toolsInvocadas,
                ];
            }
        }

        // Saludo o consulta general
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
    public function procesarConversacion(string $mensaje, ?int $clienteId = null, array $historial = [], ?int $sucursalId = null): array
    {
        $res = $this->procesarMensaje($mensaje, null, $sucursalId, $clienteId, $historial);

        return [
            'respuesta' => $res['respuesta'],
            'estado' => $res['estado'],
            'herramientas_ejecutadas' => $res['tools_invocadas'] ?? [],
            'tokens_estimados' => (int) round(strlen($res['respuesta']) / 4),
            'tiempo_ms' => $res['tiempo_ms'],
        ];
    }
}
