<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteSocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use Tests\TestCase;

class ClienteAuthSocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_pantalla_login_cliente_renders_correctamente(): void
    {
        $response = $this->get(route('cliente.login'));

        $response->assertOk();
        $response->assertSee('Club de Clientes');
        $response->assertSee('Continuar con Google');
        $response->assertSee('Enviar enlace de acceso');
    }

    public function test_envio_magic_link_genera_enlace_firmado(): void
    {
        $response = $this->post(route('cliente.magic_send'), [
            'email' => 'cliente.nuevo@test.com',
        ]);

        $response->assertSessionHas('magic_sent', true);
        $response->assertSessionHas('magic_email', 'cliente.nuevo@test.com');
        $response->assertSessionHas('magic_link_debug');
    }

    public function test_verificar_magic_link_valido_crea_cliente_e_inicia_sesion(): void
    {
        $email = 'camila.sushi@test.com';
        $magicUrl = URL::temporarySignedRoute(
            'cliente.magic_verify',
            now()->addMinutes(15),
            ['email' => $email]
        );

        $response = $this->get($magicUrl);

        $response->assertRedirect(route('cliente.perfil'));
        $response->assertCookie('cliente_session_token');

        $cliente = Cliente::where('email', $email)->first();
        $this->assertNotNull($cliente);
        $this->assertSame('Camila Sushi', $cliente->nombre);
        $this->assertSame('email_magic', $cliente->proveedor_auth);
        $this->assertNotNull($cliente->auth_token);
        $this->assertTrue($cliente->auth_token_expires_at->isFuture());
    }

    public function test_verificar_magic_link_invalido_o_expirado_rechaza(): void
    {
        $urlInvalida = route('cliente.magic_verify', ['email' => 'hacker@test.com', 'signature' => 'falsa']);

        $response = $this->get($urlInvalida);

        $response->assertRedirect(route('cliente.login'));
        $response->assertSessionHas('error');
    }

    public function test_google_callback_crea_cliente_y_cuenta_social(): void
    {
        $mockUser = Mockery::mock(SocialiteUserContract::class);
        $mockUser->shouldReceive('getId')->andReturn('google-unique-id-999');
        $mockUser->shouldReceive('getName')->andReturn('Juan Pérez');
        $mockUser->shouldReceive('getEmail')->andReturn('juan.perez@gmail.com');
        $mockUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        $mockProvider = Mockery::mock(GoogleProvider::class);
        $mockProvider->shouldReceive('stateless')->andReturnSelf();
        $mockProvider->shouldReceive('user')->andReturn($mockUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($mockProvider);

        $response = $this->get(route('cliente.auth.callback', 'google'));

        $response->assertRedirect(route('cliente.perfil'));
        $response->assertCookie('cliente_session_token');

        $cliente = Cliente::where('email', 'juan.perez@gmail.com')->first();
        $this->assertNotNull($cliente);
        $this->assertSame('Juan Pérez', $cliente->nombre);
        $this->assertSame('google', $cliente->proveedor_auth);

        $social = ClienteSocialAccount::where('cliente_id', $cliente->id)->first();
        $this->assertNotNull($social);
        $this->assertSame('google', $social->provider);
        $this->assertSame('google-unique-id-999', $social->provider_id);
    }

    public function test_perfil_cliente_protegido_requiere_autenticacion(): void
    {
        $response = $this->get(route('cliente.perfil'));
        $response->assertRedirect(route('cliente.login'));
    }

    public function test_perfil_cliente_autenticado_muestra_datos_y_puntos(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Valeria Gómez',
            'email' => 'valeria@test.com',
            'puntos_fidelidad' => 250,
            'tier' => Cliente::TIER_VIP,
            'total_gastado' => 2500000,
            'visitas_count' => 12,
            'activo' => true,
            'auth_token' => 'token-test-valeria-12345',
            'auth_token_expires_at' => now()->addDays(7),
        ]);

        $response = $this->withCookie('cliente_session_token', 'token-test-valeria-12345')
            ->get(route('cliente.perfil'));

        $response->assertOk();
        $response->assertSee('Valeria Gómez');
        $response->assertSee('250');
        $response->assertSee('VIP');
    }

    public function test_logout_invalida_token_y_remueve_cookie(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Pedro Test',
            'email' => 'pedro@test.com',
            'activo' => true,
            'auth_token' => 'token-a-eliminar-987',
            'auth_token_expires_at' => now()->addDays(1),
        ]);

        $response = $this->withCookie('cliente_session_token', 'token-a-eliminar-987')
            ->post(route('cliente.logout'));

        $response->assertRedirect(route('cliente.login'));
        $this->assertNull($cliente->fresh()->auth_token);
    }
}
