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
        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 1, 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre', 'activa' => true]);

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
