<?php

namespace Tests\Feature;

use App\Events\SolicitudCobroEnviada;
use App\Models\Caja;
use App\Models\Mesa;
use App\Models\NotificacionUsuario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Fase 8.1 — Centralización de cobros: el mesero solicita, caja cobra.
 */
class CentralizacionCobrosTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    protected User $cajero;

    protected User $mesero;

    protected Mesa $mesa;

    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Cobros Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'admin.cobros@test.local',
        ]);
        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'cajero.cobros@test.local', 'activo' => true,
        ]);
        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id, 'sucursal_id' => $this->sucursal->id, 'name' => 'Mesero Cobros',
            'email' => 'mesero.cobros@test.local', 'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id, 'nombre' => 'Caja Principal', 'codigo' => 'CAJ-01',
            'tipo' => 'principal', 'activa' => true,
        ]);
        TurnoCaja::create([
            'caja_id' => $caja->id, 'user_id' => $this->cajero->id,
            'apertura_en' => now(), 'monto_inicial' => 100000, 'estado' => 'abierto',
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id, 'numero' => 5, 'capacidad' => 4,
            'zona' => 'salon', 'estado' => 'ocupada', 'mesero_id' => $this->mesero->id,
        ]);

        $this->producto = Producto::create([
            'nombre' => 'Roll Cobros', 'slug' => 'roll-cobros', 'precio' => 35000,
            'costo' => 12000, 'area_cocina' => 'cocina', 'activo' => true,
        ]);
    }

    protected function crearPedidoCobrable(): Pedido
    {
        $svc = app(PedidoService::class);

        $pedido = $svc->crearPedido([
            'tipo' => 'mesa',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $this->mesa->id,
            'mesero_id' => $this->mesero->id,
            'usuario_id' => $this->mesero->id,
            'subtotal' => 70000,
            'total' => 70000,
        ], [
            [
                'producto_id' => $this->producto->id,
                'nombre_producto' => $this->producto->nombre,
                'cantidad' => 2,
                'precio_unitario' => 35000,
                'subtotal' => 70000,
                'area_cocina' => 'cocina',
                'estado_cocina' => 'entregado',
            ],
        ], $this->mesero);

        return $pedido->fresh();
    }

    public function test_mesero_solicita_cobro_y_notifica_a_caja(): void
    {
        Event::fake([SolicitudCobroEnviada::class]);
        $pedido = $this->crearPedidoCobrable();

        $resultado = app(PedidoService::class)->solicitarCobroCaja($pedido, $this->mesero);

        $this->assertEquals('pendiente_cobro', $resultado->estado);
        Event::assertDispatched(SolicitudCobroEnviada::class);
        $this->assertDatabaseHas('notificaciones_usuario', [
            'user_id' => $this->cajero->id,
            'tipo' => 'solicitud_cobro',
            'leida' => false,
        ]);
    }

    public function test_solicitar_cobro_duplicado_es_rechazado(): void
    {
        $pedido = $this->crearPedidoCobrable();
        $svc = app(PedidoService::class);
        $svc->solicitarCobroCaja($pedido, $this->mesero);

        $this->expectException(\DomainException::class);
        $svc->solicitarCobroCaja($pedido->fresh(), $this->mesero);
    }

    public function test_mesero_no_puede_cobrar_directamente(): void
    {
        $this->actingAs($this->mesero);
        $pedido = $this->crearPedidoCobrable();

        $this->expectException(HttpException::class);
        app(PedidoService::class)->cobrarPedido($pedido, 'efectivo', 70000.0);
    }

    public function test_terminal_mesero_solicita_en_lugar_de_cobrar(): void
    {
        $pedido = $this->crearPedidoCobrable();
        $pedido->forceFill(['estado' => 'entregado'])->save();

        Volt::actingAs($this->mesero)
            ->test('pos.terminal', ['mesaId' => $this->mesa->id, 'tipo' => 'mesa'])
            ->assertSee('Solicitar cobro a Caja')
            ->call('solicitarCobroCaja')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pedidos', [
            'mesa_id' => $this->mesa->id,
            'estado' => 'pendiente_cobro',
        ]);
    }

    public function test_cajero_procesa_cobro_pendiente_desde_caja(): void
    {
        $pedido = $this->crearPedidoCobrable();
        app(PedidoService::class)->solicitarCobroCaja($pedido, $this->mesero);

        Volt::actingAs($this->cajero)
            ->test('caja.control')
            ->call('abrirCobroPendiente', $pedido->id)
            ->set('metodoPagoPendiente', 'efectivo')
            ->set('montoPagadoPendiente', 70000.0)
            ->call('cobrarPendiente')
            ->assertHasNoErrors();

        $this->assertEquals('pagado', $pedido->fresh()->estado);
    }

    public function test_caja_muestra_badge_cobros_pendientes(): void
    {
        $pedido = $this->crearPedidoCobrable();
        app(PedidoService::class)->solicitarCobroCaja($pedido, $this->mesero);

        $response = $this->actingAs($this->cajero)->get(route('caja'));

        $response->assertOk();
        $response->assertSee('Cobros Pendientes');
        $response->assertSee('Mesa 5');
    }

    public function test_modal_cobro_unificado_permite_vaciar_montos_sin_error(): void
    {
        $pedido = $this->crearPedidoCobrable();

        Volt::actingAs($this->cajero)
            ->test('caja.modal-cobro-unificado')
            ->call('abrir', $pedido->id)
            ->set('montoPagado', '')
            ->assertSet('montoPagado', 0.0)
            ->assertSet('cambio', 0.0)
            ->set('montoPagado', null)
            ->assertSet('montoPagado', 0.0)
            ->set('montoPropina', '')
            ->assertSet('montoPropina', 0.0)
            ->set('montoEfectivoMixto', '')
            ->assertSet('montoEfectivoMixto', 0.0)
            ->assertHasNoErrors();
    }

    public function test_modal_cobro_unificado_billetes_y_cambio(): void
    {
        $pedido = $this->crearPedidoCobrable(); // total 70.000

        Volt::actingAs($this->cajero)
            ->test('caja.modal-cobro-unificado')
            ->call('abrir', $pedido->id)
            ->set('montoPagado', 100000)
            ->assertSet('cambio', 30000.0)
            ->call('setMontoExacto')
            ->assertSet('montoPagado', 70000.0)
            ->assertSet('cambio', 0.0)
            ->call('sumarBillete', 50000)
            ->assertSet('montoPagado', 120000.0)
            ->assertSet('cambio', 50000.0)
            ->call('confirmarCobro')
            ->assertHasNoErrors();

        $this->assertEquals('pagado', $pedido->fresh()->estado);
    }

    public function test_solicitud_cobro_notificacion_contiene_datos_estructurados_para_caja(): void
    {
        Event::fake([SolicitudCobroEnviada::class]);
        $pedido = $this->crearPedidoCobrable();

        app(PedidoService::class)->solicitarCobroCaja($pedido, $this->mesero);

        $notif = NotificacionUsuario::where('user_id', $this->cajero->id)
            ->where('tipo', 'solicitud_cobro')
            ->first();

        $this->assertNotNull($notif);
        $this->assertEquals($pedido->id, $notif->datos['pedido_id']);
        $this->assertEquals($pedido->codigo, $notif->datos['pedido_codigo']);
        $this->assertEquals($this->mesa->numero, $notif->datos['mesa_numero']);
        $this->assertEquals(70000.0, $notif->datos['total']);
        $this->assertStringContainsString('cobro_id='.$pedido->id, $notif->datos['ticket_url']);
    }

    public function test_modal_cobro_unificado_se_abre_con_query_param_cobro_id(): void
    {
        $pedido = $this->crearPedidoCobrable();

        // Simular request query string ?cobro_id=
        request()->merge(['cobro_id' => $pedido->id]);

        Volt::actingAs($this->cajero)
            ->test('caja.modal-cobro-unificado', ['cobro_id' => $pedido->id])
            ->assertSet('pedidoId', $pedido->id)
            ->assertSet('mostrarModal', true)
            ->assertSee($pedido->codigo);
    }
}
