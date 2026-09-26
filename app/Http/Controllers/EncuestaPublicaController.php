<?php

namespace App\Http\Controllers;

use App\Services\EncuestaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EncuestaPublicaController extends Controller
{
    public function __construct(protected EncuestaService $encuestaService) {}

    /**
     * Muestra el formulario público para responder la encuesta.
     */
    public function mostrar(string $token): View
    {
        $envio = $this->encuestaService->obtenerEnvioPorToken($token);

        if (! $envio) {
            abort(404, 'La encuesta solicitada no existe o el enlace es incorrecto.');
        }

        return view('encuesta.responder', compact('envio'));
    }

    /**
     * Procesa y guarda las respuestas enviadas por el cliente.
     */
    public function guardar(Request $request, string $token): View|RedirectResponse
    {
        $envio = $this->encuestaService->obtenerEnvioPorToken($token);

        if (! $envio) {
            abort(404, 'La encuesta solicitada no existe.');
        }

        if ($envio->estado === 'respondida') {
            return view('encuesta.responder', [
                'envio' => $envio,
                'yaRespondida' => true,
            ]);
        }

        $request->validate([
            'respuestas' => ['required', 'array'],
        ], [
            'respuestas.required' => 'Por favor responde las preguntas de la encuesta.',
        ]);

        $resultado = $this->encuestaService->responderEncuesta($envio, $request->input('respuestas'));

        return view('encuesta.responder', [
            'envio' => $envio->fresh(['encuesta', 'cliente']),
            'completada' => true,
            'puntosGanados' => $resultado['puntos_ganados'],
            'rating' => $resultado['rating'],
        ]);
    }
}
