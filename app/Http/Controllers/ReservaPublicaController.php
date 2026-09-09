<?php

namespace App\Http\Controllers;

use App\Services\ReservaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReservaPublicaController extends Controller
{
    public function create(Request $request): View
    {
        $fecha = $request->query('fecha', now()->addDay()->toDateString());
        if ($fecha < now()->toDateString()) {
            $fecha = now()->addDay()->toDateString();
        }

        $franjas = collect();
        $inicio = Carbon::createFromTime(12, 0);
        $fin = Carbon::createFromTime(21, 30);
        $personas = max(1, (int) $request->query('personas', 2));
        $service = app(ReservaService::class);

        for ($t = $inicio->copy(); $t->lte($fin); $t->addMinutes(30)) {
            $disponible = $service->verificarDisponibilidad($fecha, $t->format('H:i'), $personas)->isNotEmpty();
            $franjas->push(['hora' => $t->format('H:i'), 'disponible' => $disponible]);
        }

        return view('reservas.crear', [
            'fecha' => $fecha,
            'personas' => $personas,
            'franjas' => $franjas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('empresa')) {
            return back();
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:30'],
            'fecha' => ['required', 'date', 'after_or_equal:' . now()->toDateString()],
            'hora' => ['required', 'date_format:H:i'],
            'personas' => ['required', 'integer', 'min:1'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        app(ReservaService::class)->crear([
            'nombre_contacto' => $validated['nombre'],
            'telefono_contacto' => $validated['telefono'],
            'fecha' => $validated['fecha'],
            'hora_llegada' => $validated['hora'],
            'personas' => (int) $validated['personas'],
            'notas' => $validated['notas'] ?? null,
        ], 'publico');

        return back()->with('reservado', 'ok');
    }
}