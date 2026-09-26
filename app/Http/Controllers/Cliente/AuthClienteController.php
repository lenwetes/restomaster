<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteSocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthClienteController extends Controller
{
    /**
     * Mostrar la pantalla de login/registro público para clientes.
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        $token = $request->cookie('cliente_session_token');
        if ($token) {
            $cliente = Cliente::where('auth_token', $token)
                ->where(fn ($q) => $q->whereNull('auth_token_expires_at')->orWhere('auth_token_expires_at', '>', now()))
                ->first();

            if ($cliente) {
                return redirect()->route('cliente.perfil');
            }
        }

        return view('cliente.login');
    }

    /**
     * Redirigir a Google OAuth2.
     */
    public function redirectToGoogle(): SymfonyResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Procesar el callback de Google OAuth2.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();
        } catch (\Throwable $e) {
            Log::warning('Error en Google OAuth: '.$e->getMessage());

            return redirect()->route('cliente.login')
                ->with('error', 'No fue posible autenticar con Google. Por favor intenta de nuevo.');
        }

        $email = strtolower(trim($googleUser->getEmail() ?? ''));
        $googleId = (string) $googleUser->getId();

        if (empty($email) && empty($googleId)) {
            return redirect()->route('cliente.login')
                ->with('error', 'Google no proporcionó datos de identificación suficientes.');
        }

        // 1. Buscar por cuenta social existente
        $socialAccount = ClienteSocialAccount::where('provider', 'google')
            ->where('provider_id', $googleId)
            ->first();

        $cliente = null;
        if ($socialAccount) {
            $cliente = $socialAccount->cliente;
        }

        if (! $cliente) {
            // 2. Buscar cliente por email
            $cliente = ! empty($email) ? Cliente::where('email', $email)->first() : null;

            if (! $cliente) {
                // Crear nuevo cliente
                $cliente = Cliente::create([
                    'nombre' => $googleUser->getName() ?: 'Cliente Google',
                    'email' => $email ?: null,
                    'tier' => Cliente::TIER_OCASIONAL,
                    'puntos_fidelidad' => 0,
                    'total_gastado' => 0,
                    'visitas_count' => 0,
                    'activo' => true,
                    'acepta_tratamiento_datos' => true,
                    'fecha_autorizacion_datos' => now(),
                    'canal_autorizacion_datos' => 'google_oauth',
                    'autoriza_email' => true,
                    'avatar_url' => $googleUser->getAvatar(),
                    'proveedor_auth' => 'google',
                ]);
            }

            // Vincular cuenta social si no existía
            if (! $socialAccount) {
                ClienteSocialAccount::create([
                    'cliente_id' => $cliente->id,
                    'provider' => 'google',
                    'provider_id' => $googleId,
                    'provider_token' => $googleUser->token ?? null,
                    'provider_refresh_token' => $googleUser->refreshToken ?? null,
                    'avatar_url' => $googleUser->getAvatar(),
                    'nombre_proveedor' => $googleUser->getName(),
                    'email_proveedor' => $email,
                ]);
            }
        }

        if (! $cliente instanceof Cliente) {
            return redirect()->route('cliente.login')
                ->with('error', 'No fue posible vincular o recuperar el perfil de cliente.');
        }

        return $this->iniciarSesionCliente($cliente, 'google');
    }

    /**
     * Enviar Magic Link por correo electrónico.
     */
    public function sendMagicLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'El formato del correo es inválido.',
        ]);

        $email = strtolower(trim($request->input('email')));

        $magicUrl = URL::temporarySignedRoute(
            'cliente.magic_verify',
            now()->addMinutes(20),
            ['email' => $email]
        );

        Log::info("Magic Link generado para {$email}: {$magicUrl}");

        return back()->with('magic_sent', true)->with('magic_email', $email)->with('magic_link_debug', $magicUrl);
    }

    /**
     * Validar el Magic Link firmado y autenticar al cliente.
     */
    public function verifyMagicLink(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('cliente.login')
                ->with('error', 'El enlace de acceso ha expirado o es inválido. Solicita uno nuevo.');
        }

        $email = strtolower(trim($request->query('email', '')));

        if (empty($email)) {
            return redirect()->route('cliente.login')
                ->with('error', 'Enlace de acceso incompleto.');
        }

        $cliente = Cliente::where('email', $email)->first();

        if (! $cliente) {
            $nombreParte = explode('@', $email)[0];
            $cliente = Cliente::create([
                'nombre' => ucwords(str_replace(['.', '_', '-'], ' ', $nombreParte)),
                'email' => $email,
                'tier' => Cliente::TIER_OCASIONAL,
                'puntos_fidelidad' => 0,
                'total_gastado' => 0,
                'visitas_count' => 0,
                'activo' => true,
                'acepta_tratamiento_datos' => true,
                'fecha_autorizacion_datos' => now(),
                'canal_autorizacion_datos' => 'magic_link',
                'autoriza_email' => true,
                'proveedor_auth' => 'email_magic',
            ]);
        }

        return $this->iniciarSesionCliente($cliente, 'email_magic');
    }

    /**
     * Cerrar sesión del portal de clientes.
     */
    public function logout(Request $request): RedirectResponse
    {
        $token = $request->cookie('cliente_session_token');

        if ($token) {
            Cliente::where('auth_token', $token)->update([
                'auth_token' => null,
                'auth_token_expires_at' => null,
            ]);
        }

        Cookie::queue(Cookie::forget('cliente_session_token'));

        return redirect()->route('cliente.login')
            ->with('info', 'Has cerrado sesión correctamente.');
    }

    /**
     * Generar token seguro, persistir y configurar cookie HTTP-only.
     */
    private function iniciarSesionCliente(Cliente $cliente, string $proveedor): RedirectResponse
    {
        $token = Str::random(60);
        $expira = now()->addDays(30);

        $cliente->update([
            'auth_token' => $token,
            'auth_token_expires_at' => $expira,
            'proveedor_auth' => $proveedor,
        ]);

        Cookie::queue('cliente_session_token', $token, 60 * 24 * 30, null, null, false, true);

        return redirect()->route('cliente.perfil')
            ->with('success', "¡Bienvenido, {$cliente->nombre}!");
    }
}
