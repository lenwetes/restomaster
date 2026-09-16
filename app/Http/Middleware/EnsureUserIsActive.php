<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->activo === false) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, 'Tu usuario se encuentra inactivo.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Tu usuario se encuentra inactivo. Consulta con administración.',
            ]);
        }

        return $next($request);
    }
}
