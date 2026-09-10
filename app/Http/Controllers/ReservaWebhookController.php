<?php

namespace App\Http\Controllers;

use App\Services\ConfiguracionService;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservaWebhookController extends Controller
{
    public function crear(Request $request): JsonResponse
    {
        $cfg = app(ConfiguracionService::class);

        if (! $cfg->obtener('reservas', 'webhook_activo', false)) {
            return response()->json(['error' => 'Webhook de reservas desactivado.'], 403);
        }

        $tokenEsperado = $cfg->obtener('reservas', 'webhook_token', '');
        $tokenRecibido = $request->header('X-Webhook-Token', '');

        if ($tokenEsperado === '' || ! hash_equals($tokenEsperado, $tokenRecibido)) {
            return response()->json(['error' => 'Token de webhook inválido.'], 401);
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'fecha' => ['required', 'date', 'after_or_equal:'.now()->toDateString()],
            'hora' => ['required', 'date_format:H:i'],
            'personas' => ['required', 'integer', 'min:1'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $reserva = app(ReservaService::class)->crear([
                'nombre_contacto' => $validated['nombre'],
                'telefono_contacto' => $validated['telefono'],
                'email_contacto' => $validated['email'] ?? null,
                'fecha' => $validated['fecha'],
                'hora_llegada' => $validated['hora'],
                'personas' => (int) $validated['personas'],
                'notas' => $validated['notas'] ?? null,
            ], 'webhook');
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'reserva_id' => $reserva->id,
            'token_publico' => $reserva->token_publico,
        ], 201);
    }
}
