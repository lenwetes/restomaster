<?php

namespace Tests\Feature;

use App\Events\ClienteElegibleVip;
use App\Events\ClienteVipAprobado;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VipInvitacion;
use App\Policies\ClienteVipPolicy;
use App\Services\ClubVipService;
use Database\Seeders\CrmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClubVipTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $gerente;

    protected User $admin;

    protected User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrmSeeder::class);

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Test Sucursal',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3009998877',
            'activo' => true,
        ]);

        $roleGerente = Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente']);
        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);
        $roleCajero = Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero']);

        $this->gerente = User::create([
            'name' => 'Gerente VIP',
            'email' => 'gerente_vip@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleGerente->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin VIP',
            'email' => 'admin_vip@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'is_active' => true,
        ]);

        $this->cajero = User::create([
            'name' => 'Cajero Test',
            'email' => 'cajero_vip@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleCajero->id,
            'is_active' => true,
        ]);
    }

    public function test_cliente_con_consumo_mayor_a_500mil_en_60_dias_es_marcado_como_elegible(): void
    {
        Event::fake([ClienteElegibleVip::class]);

        $cliente = Cliente::create([
            'nombre' => 'Comensal Gourmet',
            'email' => 'gourmet@test.com',
            'telefono' => '+573001234501',
            'tier' => Cliente::TIER_FRECUENTE,
            'vip_estado' => Cliente::VIP_ESTADO_NINGUNO,
            'activo' => true,
        ]);

        // Crear 2 pedidos pagados en los últimos 30 días que sumen $550.000 COP
        Pedido::create([
            'codigo' => 'VIP-TEST-01',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'pagado',
            'subtotal' => 270000,
            'impuestos' => 30000,
            'total' => 300000,
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ]);

        Pedido::create([
            'codigo' => 'VIP-TEST-02',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'pagado',
            'subtotal' => 225000,
            'impuestos' => 25000,
            'total' => 250000,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $vipService = app(ClubVipService::class);
        $resultado = $vipService->evaluarElegibilidad($cliente);

        $this->assertNotNull($resultado);
        $cliente->refresh();

        $this->assertEquals(Cliente::VIP_ESTADO_ELEGIBLE, $cliente->vip_estado);
        $this->assertNotNull($cliente->vip_elegible_at);

        Event::assertDispatched(ClienteElegibleVip::class, function ($event) use ($cliente) {
            return $event->cliente->id === $cliente->id && $event->consumoVentana >= 500000;
        });
    }

    public function test_cliente_con_consumo_antiguo_o_menor_a_500mil_no_es_elegible(): void
    {
        Event::fake([ClienteElegibleVip::class]);

        $cliente = Cliente::create([
            'nombre' => 'Comensal Menor Gasto',
            'email' => 'menor@test.com',
            'telefono' => '+573001234502',
            'tier' => Cliente::TIER_OCASIONAL,
            'vip_estado' => Cliente::VIP_ESTADO_NINGUNO,
            'activo' => true,
        ]);

        // Pedido de $600.000 hace 75 días (fuera de la ventana de 60 días)
        Pedido::forceCreate([
            'codigo' => 'VIP-OLD-01',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'pagado',
            'subtotal' => 540000,
            'impuestos' => 60000,
            'total' => 600000,
            'created_at' => now()->subDays(75),
            'updated_at' => now()->subDays(75),
        ]);

        // Pedido de $200.000 hace 15 días (dentro de ventana, pero < 500.000)
        Pedido::forceCreate([
            'codigo' => 'VIP-NEW-01',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'pagado',
            'subtotal' => 180000,
            'impuestos' => 20000,
            'total' => 200000,
            'created_at' => now()->subDays(15),
            'updated_at' => now()->subDays(15),
        ]);

        $vipService = app(ClubVipService::class);
        $resultado = $vipService->evaluarElegibilidad($cliente);

        $this->assertFalse($resultado);
        $cliente->refresh();

        $this->assertEquals(Cliente::VIP_ESTADO_NINGUNO, $cliente->vip_estado);
        Event::assertNotDispatched(ClienteElegibleVip::class);
    }

    public function test_generar_invitacion_crea_registro_con_token_valido_y_url(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Valentina Restrepo',
            'email' => 'valentina@test.com',
            'telefono' => '+573001234503',
            'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
            'vip_elegible_at' => now()->subDays(2),
            'activo' => true,
        ]);

        $vipService = app(ClubVipService::class);
        $invitacion = $vipService->generarInvitacion($cliente, $this->gerente, 'whatsapp');

        $this->assertInstanceOf(VipInvitacion::class, $invitacion);
        $this->assertNotEmpty($invitacion->token_plano);
        $this->assertStringContainsString('/vip/registro/', $invitacion->url);
        $this->assertEquals(hash('sha256', $invitacion->token_plano), $invitacion->token_hash);
        $this->assertEquals(VipInvitacion::ESTADO_VIGENTE, $invitacion->estado);
        $this->assertTrue($invitacion->expira_at->isFuture());

        $cliente->refresh();
        $this->assertEquals(Cliente::VIP_ESTADO_INVITADO, $cliente->vip_estado);
    }

    public function test_acceso_a_formulario_publico_con_token_valido(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Camilo Sampedro',
            'email' => 'camilo@test.com',
            'telefono' => '+573001234504',
            'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
            'activo' => true,
        ]);

        $vipService = app(ClubVipService::class);
        $invitacion = $vipService->generarInvitacion($cliente, $this->admin, 'email');
        $rawToken = $invitacion->token_plano;

        $response = $this->get(route('vip.registro', ['token' => $rawToken]));
        $response->assertStatus(200);
        $response->assertSee('Bienvenido al Círculo VIP');
        $response->assertSee('Camilo Sampedro');
    }

    public function test_acceso_con_token_invalido_muestra_vista_de_error(): void
    {
        $response = $this->get(route('vip.registro', ['token' => 'token_falso_que_no_existe_123']));
        $response->assertStatus(200);
        $response->assertSee('Enlace No Válido');
    }

    public function test_registro_formulario_publico_guarda_datos_y_pasa_a_pendiente(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Daniela Herrera',
            'email' => 'daniela@test.com',
            'telefono' => '+573001234505',
            'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
            'activo' => true,
        ]);

        $vipService = app(ClubVipService::class);
        $invitacion = $vipService->generarInvitacion($cliente, $this->admin, 'whatsapp');
        $rawToken = $invitacion->token_plano;

        $response = $this->post(route('vip.registro.guardar', ['token' => $rawToken]), [
            'nombre' => 'Daniela Herrera Montoya',
            'email' => 'daniela.actualizada@test.com',
            'telefono' => '+573009991122',
            'fecha_nacimiento' => '1996-05-15',
            'password' => 'SecretoVip2026*',
            'password_confirmation' => 'SecretoVip2026*',
            'autoriza_whatsapp' => '1',
            'autoriza_email' => '1',
            'acepta_tratamiento_datos' => '1',
        ]);

        $response->assertStatus(200);
        $response->assertSee('¡Solicitud Recibida!');

        $cliente->refresh();
        $this->assertEquals(Cliente::VIP_ESTADO_PENDIENTE, $cliente->vip_estado);
        $this->assertEquals('Daniela Herrera Montoya', $cliente->nombre);
        $this->assertEquals('daniela.actualizada@test.com', $cliente->email);
        $this->assertEquals('1996-05-15', $cliente->fecha_nacimiento?->format('Y-m-d'));
        $this->assertTrue(Hash::check('SecretoVip2026*', $cliente->password));

        $invitacionDb = VipInvitacion::where('cliente_id', $cliente->id)->first();
        $this->assertEquals(VipInvitacion::ESTADO_COMPLETADA, $invitacionDb->estado);
        $this->assertNotNull($invitacionDb->usada_at);
    }

    public function test_solo_admin_y_gerente_pueden_aprobar_o_rechazar_cliente_vip(): void
    {
        $policy = app(ClienteVipPolicy::class);

        $this->assertTrue($policy->aprobar($this->admin));
        $this->assertTrue($policy->aprobar($this->gerente));
        $this->assertFalse($policy->aprobar($this->cajero));

        $this->assertTrue($policy->invitar($this->admin));
        $this->assertTrue($policy->invitar($this->gerente));
        $this->assertFalse($policy->invitar($this->cajero));
    }

    public function test_aprobacion_de_cliente_vip_actualiza_estado_a_activo_y_tier_vip(): void
    {
        Event::fake([ClienteVipAprobado::class]);

        $cliente = Cliente::create([
            'nombre' => 'Mateo Londoño',
            'email' => 'mateo@test.com',
            'telefono' => '+573001234506',
            'vip_estado' => Cliente::VIP_ESTADO_PENDIENTE,
            'tier' => Cliente::TIER_FRECUENTE,
            'activo' => true,
        ]);

        $vipService = app(ClubVipService::class);
        $clienteAprobado = $vipService->aprobarSolicitud($cliente, $this->gerente, 'Cumple todos los requisitos');

        $this->assertEquals(Cliente::VIP_ESTADO_ACTIVO, $clienteAprobado->vip_estado);
        $this->assertEquals(Cliente::TIER_VIP, $clienteAprobado->tier);
        $this->assertEquals($this->gerente->id, $clienteAprobado->vip_aprobado_por);
        $this->assertNotNull($clienteAprobado->vip_desde);

        Event::assertDispatched(ClienteVipAprobado::class, function ($event) use ($cliente) {
            return $event->cliente->id === $cliente->id;
        });
    }

    public function test_suspension_de_cliente_vip_actualiza_estado(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Santiago Rendón',
            'email' => 'santiago@test.com',
            'telefono' => '+573001234507',
            'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
            'tier' => Cliente::TIER_VIP,
            'vip_desde' => now()->subMonths(3),
            'activo' => true,
        ]);

        $vipService = app(ClubVipService::class);
        $clienteSuspendido = $vipService->suspenderVip($cliente, $this->gerente, 'Inactividad prolongada');

        $this->assertEquals(Cliente::VIP_ESTADO_SUSPENDIDO, $clienteSuspendido->vip_estado);
        $this->assertEquals(Cliente::TIER_FRECUENTE, $clienteSuspendido->tier);
    }

    public function test_pantalla_clientes_renderiza_correctamente_con_kpis_y_club_vip(): void
    {
        $response = $this->actingAs($this->gerente)->get(route('clientes'));

        $response->assertStatus(200);
        $response->assertSee('Directorio');
        $response->assertSee('Total Comensales');
        $response->assertSee('Club VIP');
    }
}
