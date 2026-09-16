<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->activo === false) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tu usuario se encuentra inactivo. Consulta con administración.']);
        }

        $userRoleSlug = $user->role?->slug;

        // Admin siempre tiene acceso a todo el sistema
        if ($user->isAdmin()) {
            return $next($request);
        }

        if (! $userRoleSlug || ! in_array($userRoleSlug, $roles, true)) {
            abort(403, 'No tienes permisos suficientes para acceder a este módulo.');
        }

        return $next($request);
    }
}
