<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EncuestaEnvio;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerfilClienteController extends Controller
{
    /**
     * Muestra el portal y perfil privado del cliente.
     */
    public function showPerfil(Request $request): View
    {
        /** @var Cliente $cliente */
        $cliente = $request->attributes->get('cliente');

        $pedidosRecientes = $cliente->pedidos()
            ->with(['items.producto'])
            ->latest()
            ->take(5)
            ->get();

        $reservasRecientes = $cliente->hasMany(Reserva::class, 'cliente_id')
            ->latest()
            ->take(5)
            ->get();

        $encuestasPendientes = EncuestaEnvio::with('encuesta')
            ->where('cliente_id', $cliente->id)
            ->where('estado', 'pendiente')
            ->where(fn ($q) => $q->whereNull('expira_en')->orWhere('expira_en', '>', now()))
            ->latest()
            ->get();

        $movimientosPuntos = $cliente->movimientosPuntos()
            ->latest()
            ->take(10)
            ->get();

        return view('cliente.perfil', compact(
            'cliente',
            'pedidosRecientes',
            'reservasRecientes',
            'encuestasPendientes',
            'movimientosPuntos'
        ));
    }
}
