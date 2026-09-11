<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Services\ConfiguracionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase5PublicoReservasTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $this->sucursal = Sucursal::create(['nombre' => 'Sede Medellín', 'codigo' => 'MDE-01', 'direccion' => 'Calle 10', 'activa' => true]);
        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 1, 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre']);
        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 2, 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre']);

        app(ConfiguracionService::class)->guardar('reservas', 'webhook_token', 'token-secreto-test');
        app(ConfiguracionService::class)->guardar('reservas', 'webhook_activo', true);
    }

    public function test_formulario_publico_muestra_franjas_disponibles(): void
    {
        $response = $this->get(route('reservas.publico').'?fecha=2026-09-25');
        $response->assertOk();
        $response->assertSee('Reserva');
        $response->assertSee('Disponible');
    }

    public function test_personal_puede_tomar_y_confirmar_reserva_publica_de_usuario_no_registrado(): void
    {
        // Mesa 1 está ocupada en el salón hoy, Mesa 2 disponible
        Mesa::where('numero', 1)->update(['estado' => 'ocupada']);

        // 1. Usuario no registrado solicita reserva desde la web pública
        $response = $this->post(route('reservas.publico'), [
            'nombre' => 'Casimiro García',
            'telefono' => '3001112222',
            'personas' => 2,
            'fecha' => '2026-09-25',
            'hora' => '13:00',
            'notas' => 'Preferencia cerca al jardín',
        ]);
        $response->assertSessionHasNoErrors();
        $reserva = \App\Models\Reserva::where('nombre_contacto', 'Casimiro García')->first();
        $this->assertNotNull($reserva);
        $this->assertSame('solicitada', $reserva->estado);
        $this->assertSame('publico', $reserva->origen);
        $this->assertTrue($reserva->mesas->isEmpty());

        // 2. Personal (cajero/mesero/admin) abre la vista de reservas y confirma la reserva asignando mesa
        $admin = \App\Models\User::create([
            'name' => 'Admin Staff',
            'email' => 'admin_test@sushixpress.com',
            'password' => bcrypt('secret'),
            'role_id' => \App\Models\Role::where('slug', 'admin')->value('id'),
            'activo' => true,
        ]);

        \Livewire\Volt\Volt::actingAs($admin)
            ->test('reservas.index')
            ->set('filtrarEstado', 'solicitadas_pendientes')
            ->assertSee('Casimiro García')
            ->call('abrirDetalle', $reserva->id)
            ->assertSet('reservaSeleccionada', $reserva->id)
            ->call('confirmar')
            ->assertHasNoErrors();

        $reserva->refresh();
        $this->assertSame('confirmada', $reserva->estado);
        $this->assertCount(1, $reserva->mesas);
        $this->assertSame('2', (string) $reserva->mesas->first()->numero);
    }

    public function test_reserva_publica_permite_grupo_grande_con_mesas_combinadas(): void
    {
        // 1. Solicitud para 6 personas cuando cada mesa individual es de 4
        $response = $this->post(route('reservas.publico'), [
            'nombre' => 'Familia Restrepo',
            'telefono' => '3109998877',
            'personas' => 6,
            'fecha' => '2026-09-26',
            'hora' => '20:00',
        ]);
        $response->assertSessionHasNoErrors();
        $reserva = \App\Models\Reserva::where('nombre_contacto', 'Familia Restrepo')->first();
        $this->assertNotNull($reserva);

        // 2. Staff confirma y combina mesas para cubrir los 6 comensales
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin_test2@sushixpress.com'],
            ['name' => 'Admin Staff 2', 'password' => bcrypt('secret'), 'role_id' => \App\Models\Role::where('slug', 'admin')->value('id'), 'activo' => true]
        );

        \Livewire\Volt\Volt::actingAs($admin)
            ->test('reservas.index')
            ->set('filtrarEstado', 'solicitadas_pendientes')
            ->call('abrirDetalle', $reserva->id)
            ->call('confirmar')
            ->assertHasNoErrors();

        $reserva->refresh();
        $this->assertSame('confirmada', $reserva->estado);
        $this->assertGreaterThanOrEqual(1, $reserva->mesas->count());
        $capacidadTotal = $reserva->mesas->sum('capacidad');
        $this->assertGreaterThanOrEqual(6, $capacidadTotal);
    }

    public function test_store_publico_crea_reserva_solicitada(): void
    {
        $response = $this->post(route('reservas.publico'), [
            'nombre' => 'Casimiro García',
            'telefono' => '3001112222',
            'personas' => 2,
            'fecha' => '2026-09-25',
            'hora' => '13:00',
            'notas' => '',
            'empresa' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservas', [
            'nombre_contacto' => 'Casimiro García',
            'estado' => 'solicitada',
            'origen' => 'publico',
        ]);
    }

    public function test_store_publico_rechaza_honeypot(): void
    {
        $this->post(route('reservas.publico'), [
            'nombre' => 'Bot', 'telefono' => '300', 'personas' => 2,
            'fecha' => '2026-09-25', 'hora' => '13:00', 'empresa' => 'spam',
        ])->assertRedirect();

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_webhook_token_valido_crea_reserva(): void
    {
        $response = $this->postJson(route('reservas.webhook'), [
            'nombre' => 'Cliente WhatsApp',
            'telefono' => '3200000001',
            'fecha' => '2026-09-26',
            'hora' => '19:00',
            'personas' => 3,
        ], ['X-Webhook-Token' => 'token-secreto-test']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reservas', [
            'nombre_contacto' => 'Cliente WhatsApp',
            'estado' => 'solicitada',
            'origen' => 'webhook',
        ]);
    }

    public function test_webhook_token_invalido_es_401(): void
    {
        $this->postJson(route('reservas.webhook'), [
            'nombre' => 'X', 'telefono' => '3', 'fecha' => '2026-09-26', 'hora' => '19:00', 'personas' => 1,
        ], ['X-Webhook-Token' => 'incorrecto'])->assertStatus(401);
    }

    public function test_webhook_inactivo_es_403(): void
    {
        app(ConfiguracionService::class)->guardar('reservas', 'webhook_activo', false);

        $this->postJson(route('reservas.webhook'), [
            'nombre' => 'X', 'telefono' => '3', 'fecha' => '2026-09-26', 'hora' => '19:00', 'personas' => 1,
        ], ['X-Webhook-Token' => 'token-secreto-test'])->assertStatus(403);
    }

    public function test_webhook_payload_invalido_es_422(): void
    {
        $this->postJson(route('reservas.webhook'), [
            'nombre' => '', 'telefono' => '', 'fecha' => 'mal', 'hora' => 'xx', 'personas' => 0,
        ], ['X-Webhook-Token' => 'token-secreto-test'])->assertStatus(422);
    }
}
