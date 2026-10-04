<?php

namespace App\Services\Ai;

use App\Models\AutomatizacionAuditoria;
use App\Models\AutomatizacionFlujo;
use App\Models\AutomatizacionPaso;
use App\Models\CrmAutomatizacion;
use App\Models\CrmConfiguracion;
use App\Models\CrmPlantilla;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutomatizacionIaGeneratorService
{
    /**
     * Interpreta la instrucción en lenguaje natural del usuario y genera la estructura de la automatización estilo n8n.
     *
     * @return array{
     *     nombre: string,
     *     descripcion: string,
     *     evento_disparador: string,
     *     canal: string,
     *     delay_minutos: int,
     *     condiciones: array,
     *     mensaje_sugerido: string,
     *     nodos: array
     * }
     */
    public function interpretarYGenerar(string $prompt, ?int $sucursalId = null): array
    {
        $config = CrmConfiguracion::activa($sucursalId);
        $apiKey = $config->obtenerApiKeyIa() ?: env('GEMINI_API_KEY');

        if (! empty($apiKey) && ! app()->environment('testing')) {
            try {
                $flujoGemini = $this->consultarGeminiParaAutomatizacion($prompt, $apiKey, $config->ia_modelo ?: 'gemini-1.5-flash');
                if (! empty($flujoGemini)) {
                    return $flujoGemini;
                }
            } catch (\Throwable $e) {
                Log::warning('Error en generador de automatizaciones Gemini: '.$e->getMessage());
            }
        }

        // Fallback heurístico semántico de alta precisión
        return $this->generarHeuristicoSemantico($prompt);
    }

    /**
     * Llama a la API de Gemini con instrucciones de salida JSON estrictas.
     */
    protected function consultarGeminiParaAutomatizacion(string $prompt, string $apiKey, string $modelo): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        $systemPrompt = <<<'PROMPT'
Eres el Arquitecto de Automatizaciones Gastronómicas de RestoMaster (estilo n8n integrado con Laravel y PostgreSQL).
Tu misión es recibir una instrucción en lenguaje natural del gerente o administrador y traducirla a un flujo de automatización estructurado.

Los eventos disparadores válidos del sistema son:
- "pedido_cobrado": Cuando un pedido se paga exitosamente en caja o delivery.
- "cliente_elegible_vip": Cuando el consumo del cliente en 60 días supera el tope ($500.000 COP).
- "reserva_confirmada": Cuando una reserva es aceptada y confirmada.
- "encuesta_negativa": Cuando una encuesta se califica con 1 o 2 estrellas.
- "cliente_inactivo": Cuando un cliente no registra compras en más de 30 días.
- "cumpleanos_cliente": Cuando es el mes o día de cumpleaños del cliente.

Los canales son: "whatsapp", "email" o "ambos".

DEBES RETORNAR ÚNICAMENTE UN OBJETO JSON VÁLIDO SIN BLOQUES MARKDOWN, con esta estructura exacta:
{
  "nombre": "Título conciso y elegante",
  "descripcion": "Explicación clara de qué hace la regla",
  "evento_disparador": "uno de los eventos válidos",
  "canal": "whatsapp" | "email" | "ambos",
  "delay_minutos": entero (0 si es inmediato),
  "condiciones": ["condición 1", "condición 2"],
  "mensaje_sugerido": "Texto de la plantilla con variables como {{nombre}}, {{restaurante}}, {{url}}, {{total}}",
  "nodos": [
    {"tipo": "disparador", "titulo": "Trigger: Evento", "subtitulo": "Detalle del evento", "icono": "bolt", "color": "amber"},
    {"tipo": "filtro", "titulo": "Filtro / Condición", "subtitulo": "Criterio de validación", "icono": "filter_alt", "color": "blue"},
    {"tipo": "espera", "titulo": "Espera / Timer", "subtitulo": "Tiempo de espera antes de disparar", "icono": "schedule", "color": "purple"},
    {"tipo": "accion", "titulo": "Acción: Envío", "subtitulo": "Despacho por WhatsApp/Email", "icono": "send", "color": "emerald"}
  ]
}
PROMPT;

        $response = Http::timeout(10)->post($url, [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => "Diseña la siguiente automatización: {$prompt}"]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->successful()) {
            $raw = $response->json('candidates.0.content.parts.0.text');
            if ($raw) {
                $clean = trim(Str::replace(['```json', '```'], '', $raw));
                $parsed = json_decode($clean, true);
                if (is_array($parsed) && isset($parsed['nombre'], $parsed['evento_disparador'])) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    /**
     * Generador semántico cuando no hay conexión a internet o para modo testing/demo.
     */
    public function generarHeuristicoSemantico(string $prompt): array
    {
        $promptLower = mb_strtolower($prompt);

        if (str_contains($promptLower, 'cumpl') || str_contains($promptLower, 'año') || str_contains($promptLower, 'aniversario')) {
            return [
                'nombre' => 'Cortesía de Cumpleaños VIP',
                'descripcion' => 'Envía automáticamente felicitación y postre de cortesía a clientes en el mes de su cumpleaños.',
                'evento_disparador' => 'cumpleanos_cliente',
                'canal' => 'whatsapp',
                'delay_minutos' => 0,
                'condiciones' => ['Cliente activo', 'Fecha de nacimiento registrada', 'Autoriza WhatsApp'],
                'mensaje_sugerido' => '¡Feliz cumpleaños {{nombre}}! 🎂 En RestoMaster queremos celebrar contigo: presenta este mensaje en tu mesa y disfruta de un postre de autor o cóctel de cortesía. ¡Te esperamos!',
                'nodos' => [
                    ['tipo' => 'disparador', 'titulo' => 'Disparador Cron', 'subtitulo' => 'Día de Cumpleaños 09:00 AM', 'icono' => 'cake', 'color' => 'amber'],
                    ['tipo' => 'filtro', 'titulo' => 'Validación Habeas Data', 'subtitulo' => 'Cliente autoriza WhatsApp y está activo', 'icono' => 'verified_user', 'color' => 'blue'],
                    ['tipo' => 'accion', 'titulo' => 'Despacho WhatsApp', 'subtitulo' => 'Envío de saludo y bono cortesía', 'icono' => 'send', 'color' => 'emerald'],
                ],
            ];
        }

        if (str_contains($promptLower, 'inactiv') || str_contains($promptLower, '30 día') || str_contains($promptLower, 'no vuelve') || str_contains($promptLower, 'regres')) {
            return [
                'nombre' => 'Reactivación de Comensales (+30 Días)',
                'descripcion' => 'Detecta clientes que llevan más de 30 días sin visitarnos y les envía una invitación tentadora con copa de cortesía.',
                'evento_disparador' => 'cliente_inactivo',
                'canal' => 'ambos',
                'delay_minutos' => 0,
                'condiciones' => ['Último pedido > 30 días', 'Gasto histórico > $100.000 COP'],
                'mensaje_sugerido' => 'Hola {{nombre}}, hace días no te deleitamos en RestoMaster ✨. Nuestro chef preparó nuevos cortes a la brasa y queremos invitarte una copa de vino en tu próxima reserva.',
                'nodos' => [
                    ['tipo' => 'disparador', 'titulo' => 'Detección Inactividad', 'subtitulo' => '30 días sin pedidos pagados', 'icono' => 'timelapse', 'color' => 'amber'],
                    ['tipo' => 'filtro', 'titulo' => 'Filtro de Lealtad', 'subtitulo' => 'Comensal con al menos 2 visitas previas', 'icono' => 'filter_alt', 'color' => 'blue'],
                    ['tipo' => 'accion', 'titulo' => 'Omnicanal WhatsApp & Email', 'subtitulo' => 'Envío de invitación con copa de cortesía', 'icono' => 'mark_email_read', 'color' => 'emerald'],
                ],
            ];
        }

        if (str_contains($promptLower, 'vip') || str_contains($promptLower, '500') || str_contains($promptLower, 'elegible')) {
            return [
                'nombre' => 'Invitación Automática al Club VIP',
                'descripcion' => 'Al superar $500.000 COP en 60 días, genera token de invitación firmado y envía formulario de membresía.',
                'evento_disparador' => 'cliente_elegible_vip',
                'canal' => 'whatsapp',
                'delay_minutos' => 60,
                'condiciones' => ['Consumo 60 días >= $500.000 COP', 'No es miembro VIP activo'],
                'mensaje_sugerido' => '¡Felicidades {{nombre}}! 🌟 Por tu lealtad en RestoMaster has calificado al Club VIP Exclusivo. Activa tu membresía y beneficios aquí: {{url_invitacion}}',
                'nodos' => [
                    ['tipo' => 'disparador', 'titulo' => 'Consumo Superado', 'subtitulo' => 'Gasto acumulado >= $500.000 COP en 60 días', 'icono' => 'stars', 'color' => 'amber'],
                    ['tipo' => 'espera', 'titulo' => 'Espera Amigable', 'subtitulo' => 'Pausa de 1 hora tras cobro del pedido', 'icono' => 'schedule', 'color' => 'purple'],
                    ['tipo' => 'accion', 'titulo' => 'Invitación Firmada', 'subtitulo' => 'Genera token SHA-256 y despacha enlace VIP', 'icono' => 'vpn_key', 'color' => 'emerald'],
                ],
            ];
        }

        if (str_contains($promptLower, 'queja') || str_contains($promptLower, 'mala') || str_contains($promptLower, 'insatisf') || str_contains($promptLower, 'negativ') || str_contains($promptLower, 'estrella')) {
            return [
                'nombre' => 'Alerta Inmediata por Encuesta Negativa',
                'descripcion' => 'Si un cliente califica con 1 o 2 estrellas, notifica en tiempo real a gerencia y ofrece contacto prioritario.',
                'evento_disparador' => 'encuesta_negativa',
                'canal' => 'ambos',
                'delay_minutos' => 0,
                'condiciones' => ['Puntaje <= 2 estrellas'],
                'mensaje_sugerido' => 'Estimado(a) {{nombre}}, lamentamos que tu experiencia no haya sido perfecta. Nuestro gerente general se comunicará contigo personalmente para subsanarlo de inmediato.',
                'nodos' => [
                    ['tipo' => 'disparador', 'titulo' => 'Respuesta Encuesta', 'subtitulo' => 'Calificación de 1 o 2 estrellas recibida', 'icono' => 'warning', 'color' => 'rose'],
                    ['tipo' => 'accion', 'titulo' => 'Alerta Gerencial', 'subtitulo' => 'Notificación interna prioritaria a administración', 'icono' => 'notification_important', 'color' => 'amber'],
                    ['tipo' => 'accion', 'titulo' => 'Mensaje de Disculpa', 'subtitulo' => 'Contacto de cortesía y atención personalizada', 'icono' => 'support_agent', 'color' => 'emerald'],
                ],
            ];
        }

        // Por defecto: Post-Cobro de satisfacción
        return [
            'nombre' => 'Satisfacción y Agradecimiento Post-Consumo',
            'descripcion' => 'Despacha encuesta y agradecimiento 30 minutos después de abonado el consumo en mesa o delivery.',
            'evento_disparador' => 'pedido_cobrado',
            'canal' => 'whatsapp',
            'delay_minutos' => 30,
            'condiciones' => ['Pedido en estado pagado', 'Cliente registrado con teléfono'],
            'mensaje_sugerido' => 'Hola {{nombre}}, fue un placer atenderte en RestoMaster ✨. ¿Qué tal estuvo tu velada hoy? Califica tu experiencia aquí: {{url_encuesta}}',
            'nodos' => [
                ['tipo' => 'disparador', 'titulo' => 'Pedido Cobrado', 'subtitulo' => 'Transacción de cobro completada', 'icono' => 'point_of_sale', 'color' => 'amber'],
                ['tipo' => 'espera', 'titulo' => 'Tiempo de Sobremesa', 'subtitulo' => 'Espera 30 minutos', 'icono' => 'schedule', 'color' => 'purple'],
                ['tipo' => 'accion', 'titulo' => 'Encuesta WhatsApp', 'subtitulo' => 'Envío dinámico de encuesta de satisfacción', 'icono' => 'chat', 'color' => 'emerald'],
            ],
        ];
    }

    /**
     * Persiste la automatización generada tanto en el motor de flujos estilo n8n como en las reglas de ejecución CRM.
     */
    public function persistirAutomatizacion(array $flujoData, User $usuario): array
    {
        return DB::transaction(function () use ($flujoData, $usuario) {
            // 1. Crear plantilla de mensaje si viene sugerido
            $codigoBase = Str::slug($flujoData['nombre']);
            $codigo = $codigoBase.'_'.Str::lower(Str::random(6));

            $plantillaWa = CrmPlantilla::create([
                'codigo' => $codigo,
                'nombre' => 'Plantilla: '.Str::limit($flujoData['nombre'], 40),
                'canal' => in_array($flujoData['canal'], ['whatsapp', 'ambos']) ? 'whatsapp' : 'email',
                'asunto' => $flujoData['nombre'],
                'contenido' => $flujoData['mensaje_sugerido'] ?? 'Mensaje de automatización',
                'categoria' => 'fidelizacion',
                'activa' => true,
            ]);

            // 2. Crear regla CRM
            $crmAuto = CrmAutomatizacion::create([
                'nombre' => $flujoData['nombre'],
                'evento_disparador' => $flujoData['evento_disparador'] ?? 'pedido_cobrado',
                'canal' => $flujoData['canal'] ?? 'whatsapp',
                'delay_minutos' => (int) ($flujoData['delay_minutos'] ?? 0),
                'plantilla_whatsapp_id' => $plantillaWa->id,
                'plantilla_email_id' => $plantillaWa->id,
                'activa' => true,
                'total_disparos' => 0,
            ]);

            // 3. Crear Flujo en el Motor de Base de Datos estilo n8n
            $flujo = AutomatizacionFlujo::create([
                'nombre' => $flujoData['nombre'],
                'descripcion' => $flujoData['descripcion'] ?? 'Generado automáticamente por el Asistente IA',
                'estado' => 'activo',
                'disparador_tipo' => 'evento',
                'disparador_clave' => $flujoData['evento_disparador'] ?? 'pedido_cobrado',
                'disparador_config' => [
                    'delay_minutos' => $flujoData['delay_minutos'] ?? 0,
                    'canal' => $flujoData['canal'] ?? 'whatsapp',
                ],
                'condiciones' => $flujoData['condiciones'] ?? [],
                'version_actual' => 1,
                'origen' => 'ia',
                'creado_por' => $usuario->id,
                'actualizado_por' => $usuario->id,
            ]);

            // 4. Crear Pasos / Nodos
            $nodos = $flujoData['nodos'] ?? [];
            foreach ($nodos as $idx => $nodo) {
                AutomatizacionPaso::create([
                    'flujo_id' => $flujo->id,
                    'orden' => $idx + 1,
                    'tipo' => $nodo['tipo'] ?? 'whatsapp',
                    'nombre' => $nodo['titulo'] ?? 'Paso '.($idx + 1),
                    'config' => [
                        'color' => $nodo['color'] ?? 'amber',
                        'icono' => $nodo['icono'] ?? 'bolt',
                        'subtitulo' => $nodo['subtitulo'] ?? '',
                    ],
                ]);
            }

            // 5. Auditoría
            AutomatizacionAuditoria::create([
                'flujo_id' => $flujo->id,
                'usuario_id' => $usuario->id,
                'accion' => 'crear',
                'antes' => null,
                'despues' => [
                    'prompt_original' => $flujoData['nombre'],
                    'crm_auto_id' => $crmAuto->id,
                ],
                'ip' => request()->ip() ?? '127.0.0.1',
            ]);

            return [
                'crm_automatizacion' => $crmAuto,
                'flujo' => $flujo,
            ];
        });
    }
}
