<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthCliente
{
    /**
     * Handle an incoming request for authenticated customers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('cliente_session_token')
            ?? $request->bearerToken()
            ?? $request->header('X-Cliente-Token')
            ?? $request->query('auth_token');

        if (! $token) {
            return $this->noAutorizado($request);
        }

        $cliente = Cliente::where('auth_token', $token)
            ->where(function ($q) {
                $q->whereNull('auth_token_expires_at')
                    ->orWhere('auth_token_expires_at', '>', now());
            })
            ->first();

        if (! $cliente) {
            return $this->noAutorizado($request);
        }

        $request->attributes->set('cliente', $cliente);
        view()->share('clienteAutenticado', $cliente);

        return $next($request);
    }

    private function noAutorizado(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'No autorizado como cliente.'], 401);
        }

        return redirect()->route('cliente.login')
            ->with('error', 'Por favor inicia sesión para acceder a tu perfil y recompensas.');
    }
}
