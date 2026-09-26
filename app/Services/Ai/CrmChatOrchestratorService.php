<?php

namespace App\Services\Ai;

use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmConversacion;
use App\Models\CrmMensaje;
use App\Services\CrmWhatsAppService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CrmChatOrchestratorService
{
    public function __construct(
        protected CrmAiAgentService $aiAgentService,
        protected CrmWhatsAppService $whatsAppService,
        protected AiToolGatekeeper $gatekeeper
    ) {}

    /**
     * Palabras clave que denotan solicitud explícita de hablar con un humano.
     */
    protected const PALABRAS_CLAVE_HUMANO = [
        'humano', 'persona', 'asesor', 'agente', 'hablar con alguien', 'anfitrion', 'anfitrión',
        'gerente', 'administrador', 'queja', 'reclamo', 'problema con', 'atencion humana', 'atención humana',
    ];

    /**
     * Obtiene o crea la conversación correspondiente a un canal e identificador.
     */
    public function obtenerOCrearConversacion(string $canal, string $identificadorRemoto, ?string $nombre = null, ?int $clienteId = null): CrmConversacion
    {
        $identificadorRemoto = trim($identificadorRemoto);

        $query = CrmConversacion::where('canal', $canal);
        if ($canal === 'web') {
            $query->where('session_token', $identificadorRemoto);
        } else {
            $query->where('identificador_remoto', $identificadorRemoto);
        }

        /** @var CrmConversacion|null $conversacion */
        $conversacion = $query->first();

        if (! $conversacion) {
            // Intentar enlazar con cliente si no viene clienteId pero tenemos teléfono
            if (! $clienteId && $canal === 'whatsapp') {
                $cliente = Cliente::where('telefono', $identificadorRemoto)
                    ->orWhere('telefono', Str::replaceFirst('+', '', $identificadorRemoto))
                    ->first();
                $clienteId = $cliente?->id;
                $nombre = $nombre ?: $cliente?->nombre;
            }

            $conversacion = CrmConversacion::create([
                'canal' => $canal,
                'identificador_remoto' => $identificadorRemoto,
                'session_token' => $canal === 'web' ? $identificadorRemoto : null,
                'cliente_id' => $clienteId,
                'nombre_contacto' => $nombre ?: ($canal === 'whatsapp' ? "WhatsApp {$identificadorRemoto}" : 'Visitante Web'),
                'modo_atencion' => 'ia',
                'estado' => 'activa',
                'no_leidos_staff' => 0,
                'no_leidos_cliente' => 0,
            ]);
        } elseif ($clienteId && ! $conversacion->cliente_id) {
            $conversacion->update(['cliente_id' => $clienteId]);
        }

        return $conversacion;
    }

    /**
     * Procesa un mensaje entrante enviado por un cliente.
     */
    public function procesarMensajeCliente(CrmConversacion $conversacion, string $texto, ?string $wamid = null): CrmMensaje
    {
        $texto = trim($texto);

        // 1. Registrar el mensaje del cliente en BD
        $mensajeCliente = CrmMensaje::create([
            'crm_conversacion_id' => $conversacion->id,
            'emisor' => 'cliente',
            'contenido' => $texto,
            'canal_origen' => $conversacion->canal,
            'estado_entrega' => 'entregado',
            'wamid' => $wamid,
        ]);

        $conversacion->update([
            'ultimo_mensaje_texto' => $texto,
            'ultimo_mensaje_at' => now(),
            'no_leidos_staff' => $conversacion->no_leidos_staff + 1,
        ]);

        // 2. Si el chat está en modo humano, la IA no interviene
        if ($conversacion->modo_atencion === 'humano') {
            return $mensajeCliente;
        }

        // 3. Evaluar si el cliente solicita un humano
        if ($this->solicitaHumano($texto)) {
            $conversacion->update([
                'modo_atencion' => 'humano',
                'estado' => 'esperando_humano',
            ]);

            $respuestaHumano = 'Con gusto te comunico con uno de nuestros anfitriones en el restaurante. Ya les he avisado y en breve te responderán por aquí mismo.';
            $this->despacharRespuestaBot($conversacion, $respuestaHumano, ['motivo' => 'handoff_solicitado']);

            return $mensajeCliente;
        }

        // 4. Rate Limiting por remitente (máximo 10 mensajes por minuto)
        $rateKey = 'crm_chat_rate:'.$conversacion->canal.':'.$conversacion->identificador_remoto;
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $respuestaFreno = 'Estás enviando mensajes demasiado rápido. Por favor espera un momento para que podamos atenderte adecuadamente.';
            $this->despacharRespuestaBot($conversacion, $respuestaFreno, ['motivo' => 'rate_limit_excedido']);

            return $mensajeCliente;
        }
        RateLimiter::hit($rateKey, 60);

        // 5. Verificar cuota diaria de IA
        $config = CrmConfiguracion::activa();
        $limiteDiario = (int) ($config->ia_limite_mensajes_por_cliente_dia ?: 25);
        $consultasHoy = CrmMensaje::where('crm_conversacion_id', $conversacion->id)
            ->where('emisor', 'cliente')
            ->whereDate('created_at', today())
            ->count();

        if ($consultasHoy > $limiteDiario) {
            $conversacion->update([
                'modo_atencion' => 'humano',
                'estado' => 'esperando_humano',
            ]);

            $respuestaCuota = 'Has alcanzado el límite de consultas automáticas de hoy. Un anfitrión de nuestro equipo continuará asistiéndote en breve.';
            $this->despacharRespuestaBot($conversacion, $respuestaCuota, ['motivo' => 'cuota_diaria_superada']);

            return $mensajeCliente;
        }

        // 6. Consultar a CrmAiAgentService
        $historialMensajes = $this->obtenerVentanaDeslizante($conversacion);
        $telefono = $conversacion->canal === 'whatsapp' ? $conversacion->identificador_remoto : null;
        $nombreContacto = ($conversacion->nombre_contacto && ! str_starts_with($conversacion->nombre_contacto, 'Visitante Web') && ! str_starts_with($conversacion->nombre_contacto, 'WhatsApp'))
            ? $conversacion->nombre_contacto
            : null;

        $respuestaIa = $this->aiAgentService->procesarConversacion(
            mensaje: $texto,
            clienteId: $conversacion->cliente_id,
            historial: $historialMensajes,
            sucursalId: $conversacion->sucursal_id,
            telefono: $telefono,
            nombreContacto: $nombreContacto,
            conversacion: $conversacion
        );

        $this->despacharRespuestaBot($conversacion, $respuestaIa['respuesta'], [
            'tokens' => $respuestaIa['tokens_estimados'] ?? 0,
            'herramientas' => $respuestaIa['herramientas_ejecutadas'] ?? [],
        ]);

        return $mensajeCliente;
    }

    /**
     * Despacha un mensaje proveniente del bot hacia el canal correspondiente.
     */
    public function despacharRespuestaBot(CrmConversacion $conversacion, string $contenido, array $metadata = []): CrmMensaje
    {
        $mensajeBot = CrmMensaje::create([
            'crm_conversacion_id' => $conversacion->id,
            'emisor' => 'bot',
            'contenido' => $contenido,
            'canal_origen' => $conversacion->canal,
            'estado_entrega' => 'enviado',
            'metadata' => $metadata,
        ]);

        $conversacion->update([
            'ultimo_mensaje_texto' => $contenido,
            'ultimo_mensaje_at' => now(),
            'no_leidos_cliente' => $conversacion->no_leidos_cliente + 1,
        ]);

        // Si es WhatsApp, despachar a Meta API
        if ($conversacion->canal === 'whatsapp') {
            $this->whatsAppService->enviarMensaje(
                telefono: $conversacion->identificador_remoto,
                contenido: $contenido,
                clienteId: $conversacion->cliente_id
            );
        }

        return $mensajeBot;
    }

    /**
     * Envía una respuesta redactada por un usuario del personal (staff) desde el CRM.
     */
    public function enviarMensajeStaff(CrmConversacion $conversacion, string $contenido, int $userId): CrmMensaje
    {
        $contenido = trim($contenido);

        $mensajeStaff = CrmMensaje::create([
            'crm_conversacion_id' => $conversacion->id,
            'emisor' => 'staff',
            'user_id' => $userId,
            'contenido' => $contenido,
            'canal_origen' => $conversacion->canal,
            'estado_entrega' => 'enviado',
        ]);

        $conversacion->update([
            'ultimo_mensaje_texto' => $contenido,
            'ultimo_mensaje_at' => now(),
            'modo_atencion' => 'humano',
            'estado' => 'activa',
            'no_leidos_staff' => 0,
            'no_leidos_cliente' => $conversacion->no_leidos_cliente + 1,
        ]);

        // Si el cliente está en WhatsApp, despachar a su WhatsApp
        if ($conversacion->canal === 'whatsapp') {
            $this->whatsAppService->enviarMensaje(
                telefono: $conversacion->identificador_remoto,
                contenido: $contenido,
                clienteId: $conversacion->cliente_id
            );
        }

        return $mensajeStaff;
    }

    /**
     * Alterna el modo de atención de la conversación entre IA y Humano.
     */
    public function alternarModoAtencion(CrmConversacion $conversacion, string $nuevoModo, ?int $userId = null): void
    {
        $conversacion->cambiarModoAtencion($nuevoModo, $userId);

        if ($nuevoModo === 'ia') {
            $conversacion->update(['estado' => 'activa']);
        }
    }

    /**
     * Recupera la ventana deslizante de memoria (últimos 8 mensajes).
     */
    public function obtenerVentanaDeslizante(CrmConversacion $conversacion, int $limite = 8): array
    {
        return $conversacion->mensajes()
            ->latest('id')
            ->take($limite)
            ->get()
            ->reverse()
            ->map(fn (CrmMensaje $m) => [
                'role' => $m->emisor === 'cliente' ? 'user' : 'model',
                'parts' => [['text' => $m->contenido]],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Detecta si el texto del comensal contiene solicitudes explícitas de atención humana.
     * Evita falsos positivos como la cantidad de comensales en reservas ("3 personas").
     */
    protected function solicitaHumano(string $texto): bool
    {
        $textoLimpio = Str::lower($texto);

        // Limpiar indicaciones de comensales para no confundir con solicitud de humano
        $regexComensales = '/\b(\d+|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\s+personas?\b|\b(?:para|de|mesa\s+para|somos|seremos)\s+un[ao]?\s+persona\b/iu';
        $textoSinComensales = preg_replace($regexComensales, '', $textoLimpio);

        // 1. Patrones contextuales de solicitud de atención humana
        $patronesHumano = [
            '/\b(hablar|comunicar|pasar|transferir|contactar|atender)\s+(con\s+)?(un\s+|una\s+)?(humano|persona|asesor|agente|alguien|operador|anfitri[oó]n)\b/iu',
            '/\b(atenci[oó]n\s+humana|persona\s+real|asesor\s+humano|agente\s+humano|asistente\s+humano)\b/iu',
            '/\b(quiero|necesito)\s+(un\s+|una\s+)?(asesor|humano)\b/iu',
            '/\b(gerente|administrador|supervisora?)\b/iu',
            '/\b(queja|reclamo|denuncia)\b/iu',
            '/\b(problema\s+con\s+mi\s+cuenta|problema\s+con\s+el\s+pago)\b/iu',
            '/\bno\s+quiero\s+(un\s+)?bot\b/iu',
            '/\bhablar\s+con\s+alguien\b/iu',
        ];

        foreach ($patronesHumano as $patron) {
            if (preg_match($patron, $textoSinComensales)) {
                return true;
            }
        }

        // 2. Términos directos sin ambigüedad
        $palabrasDirectas = [
            'asesor', 'atención humana', 'atencion humana',
        ];

        foreach ($palabrasDirectas as $frase) {
            if (str_contains($textoSinComensales, $frase)) {
                return true;
            }
        }

        return false;
    }
}
