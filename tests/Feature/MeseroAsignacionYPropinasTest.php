<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\ImpresionService;
use App\Services\MesaService;
use App\Services\PedidoService;
use App\Services\ReporteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MeseroAsignacionYPropinasTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cajero;

    protected User $mesero1;

    protected User $mesero2;

    protected Sucursal $sucursal;

    protected Caja $caja;

    protected TurnoCaja $turnoCaja;

    protected Mesa $mesa;

    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@restomaster.com')->first();
        $this->cajero = User::where('email', 'cajero@restomaster.com')->first();
        $this->mesero1 = User::where('email', 'mesero@restomaster.com')->first();

        $rolMesero = Role::where('slug', 'mesero')->first();
        $this->mesero2 = User::create([
            'name' => 'Mesero Dos Gómez',
            'email' => 'mesero2@restomaster.com',
            'password' => bcrypt('password'),
            'role_id' => $rolMesero->id,
            'telefono' => '3009998877',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::first() ?? Sucursal::create(['nombre' => 'Principal', 'direccion' => 'Calle 1']);

        $this->caja = Caja::first() ?? Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        $this->turnoCaja = TurnoCaja::where('caja_id', $this->caja->id)->first() ?? TurnoCaja::create([
            'caja_id' => $this->caja->id,
            'user_id' => $this->cajero->id,
            'monto_inicial' => 100000,
            'monto_esperado_efectivo' => 100000,
            'estado' => 'abierto',
            'apertura_en' => now(),
        ]);
        $this->turnoCaja->update(['estado' => 'abierto']);

        $this->mesa = Mesa::first() ?? Mesa::create([
            'numero' => '10',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $categoria = Categoria::first() ?? Categoria::create([
            'nombre' => 'Maki Rolls',
            'slug' => 'maki-rolls-test',
            'icono' => '🍣',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->producto = Producto::first() ?? Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'California Roll Test',
            'slug' => 'california-roll-test',
            'precio' => 28000,
            'costo' => 10000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_mesero_puede_autoasignarse_mesa_libre(): void
    {
        $mesaService = app(MesaService::class);
        $this->mesa->update(['mesero_id' => null, 'estado' => MesaEstado::LIBRE->value]);

        $mesaService->autoasignarMesa($this->mesa, $this->mesero1);

        $this->mesa->refresh();
        $this->assertSame($this->mesero1->id, $this->mesa->mesero_id);
    }

    public function test_mesero_puede_transferir_mesa_libremente_a_otro_mesero_con_auditoria(): void
    {
        $mesaService = app(MesaService::class);
        $pedidoService = app(PedidoService::class);

        // Mesa asignada inicialmente al mesero 1
        $this->mesa->update([
            'mesero_id' => $this->mesero1->id,
            'estado' => MesaEstado::OCUPADA->value,
        ]);

        // Crear pedido activo para la mesa
        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 2, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        $this->assertSame($this->mesero1->id, $pedido->mesero_id);

        // Mesero 1 transfiere mesa libremente a Mesero 2
        $mesaService->transferirMesa($this->mesa, $this->mesero2, $this->mesero1);

        $this->mesa->refresh();
        $pedido->refresh();

        $this->assertSame($this->mesero2->id, $this->mesa->mesero_id);
        $this->assertSame($this->mesero2->id, $pedido->mesero_id);

        // Auditoría registrada con prefijo plural
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'mesas.transferida',
            'entidad_id' => $this->mesa->id,
            'user_id' => $this->mesero1->id,
        ]);
    }

    public function test_comanda_hereda_mesero_de_la_mesa(): void
    {
        $pedidoService = app(PedidoService::class);

        $this->mesa->update([
            'mesero_id' => $this->mesero2->id,
            'estado' => MesaEstado::LIBRE->value,
        ]);

        // Creado por un cajero o sistema central
        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->cajero
        );

        $this->assertSame($this->mesero2->id, $pedido->mesero_id);
    }

    public function test_comanda_creada_por_mesero_autoasigna_mesa_si_estaba_vacia(): void
    {
        $pedidoService = app(PedidoService::class);

        $this->mesa->update([
            'mesero_id' => null,
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        $this->mesa->refresh();

        $this->assertSame($this->mesero1->id, $pedido->mesero_id);
        $this->assertSame($this->mesero1->id, $this->mesa->mesero_id);
    }

    public function test_cobro_pos_con_propina_10_por_ciento_atribuye_a_mesero(): void
    {
        $pedidoService = app(PedidoService::class);

        $this->mesa->update([
            'mesero_id' => $this->mesero1->id,
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 2, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        $subtotal = (float) $pedido->total;
        $propinaSugerida = round($subtotal * 0.10, 2);
        $totalConPropina = $subtotal + $propinaSugerida;

        $pedidoCobrado = $pedidoService->cobrarPedido(
            $pedido,
            'efectivo',
            $totalConPropina + 10000,
            null,
            $propinaSugerida,
            10.0
        );

        $this->assertSame('pagado', $pedidoCobrado->estado);
        $this->assertEquals($propinaSugerida, (float) $pedidoCobrado->propina);
        $this->assertEquals(10.0, (float) $pedidoCobrado->porcentaje_propina);
        $this->assertSame($this->mesero1->id, $pedidoCobrado->mesero_id);
    }

    public function test_cobro_pos_con_propina_voluntaria_personalizada(): void
    {
        $pedidoService = app(PedidoService::class);

        $this->mesa->update(['mesero_id' => $this->mesero2->id]);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero2
        );

        $subtotal = (float) $pedido->total;
        $propinaVoluntaria = 4500.0;
        $totalConPropina = $subtotal + $propinaVoluntaria;

        $pedidoCobrado = $pedidoService->cobrarPedido(
            $pedido,
            'tarjeta',
            $totalConPropina,
            null,
            $propinaVoluntaria,
            null
        );

        $this->assertSame('pagado', $pedidoCobrado->estado);
        $this->assertEquals($propinaVoluntaria, (float) $pedidoCobrado->propina);
        $this->assertNull($pedidoCobrado->porcentaje_propina);
        $this->assertSame($this->mesero2->id, $pedidoCobrado->mesero_id);
    }

    public function test_cobro_pos_sin_propina_registra_cero_correctamente(): void
    {
        $pedidoService = app(PedidoService::class);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        $subtotal = (float) $pedido->total;

        $pedidoCobrado = $pedidoService->cobrarPedido(
            $pedido,
            'efectivo',
            $subtotal,
            null,
            0.0,
            0.0
        );

        $this->assertSame('pagado', $pedidoCobrado->estado);
        $this->assertEquals(0.0, (float) $pedidoCobrado->propina);
        $this->assertEquals(0.0, (float) $pedidoCobrado->porcentaje_propina);
    }

    public function test_ticket_impresion_incluye_mesero_y_desglose_de_propina(): void
    {
        $pedidoService = app(PedidoService::class);
        $impresionService = app(ImpresionService::class);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 2, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        $subtotal = (float) $pedido->total;
        $propina = round($subtotal * 0.10, 2);
        $totalConPropina = $subtotal + $propina;

        $pedidoCobrado = $pedidoService->cobrarPedido(
            $pedido,
            'efectivo',
            $totalConPropina,
            null,
            $propina,
            10.0
        );

        $ticket = $impresionService->formatearTicketVentaTexto($pedidoCobrado);

        $this->assertStringContainsString('MESERO:', $ticket);
        $this->assertStringContainsString($this->mesero1->name, $ticket);
        $this->assertStringContainsString('PROPINA VOLUNTARIA (10%):', $ticket);
        $this->assertStringContainsString('TOTAL A PAGAR:', $ticket);
    }

    public function test_reporte_servicio_calcula_facturacion_propinas_y_ticket_promedio(): void
    {
        $pedidoService = app(PedidoService::class);
        $reporteService = app(ReporteService::class);

        $mesa2 = Mesa::create([
            'numero' => '20',
            'capacidad' => 2,
            'zona' => 'terraza',
            'estado' => MesaEstado::LIBRE->value,
            'sucursal_id' => $this->sucursal->id,
            'mesero_id' => $this->mesero2->id,
        ]);

        // Venta 1: Mesero 1 en Mesa 1
        $this->mesa->update(['mesero_id' => $this->mesero1->id]);
        $p1 = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 2, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );
        $sub1 = (float) $p1->total;
        $prop1 = round($sub1 * 0.10, 2);
        $pedidoService->cobrarPedido($p1, 'efectivo', $sub1 + $prop1, null, $prop1, 10.0);

        // Venta 2: Mesero 2 en Mesa 2 - sin propina
        $p2 = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $mesa2->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero2
        );
        $sub2 = (float) $p2->total;
        $pedidoService->cobrarPedido($p2, 'efectivo', $sub2, null, 0.0, 0.0);

        $hoy = now()->toDateString();
        $reporte = $reporteService->rendimientoMeseros($hoy, $hoy);

        $this->assertEquals($sub1 + $sub2, $reporte['totales']['total_ventas_netas']);
        $this->assertEquals($prop1, $reporte['totales']['total_propinas']);
        $this->assertEquals($sub1 + $sub2 + $prop1, $reporte['totales']['total_con_propinas']);
        $this->assertSame(2, $reporte['totales']['total_comandas']);
        $this->assertSame($this->mesero1->name, $reporte['totales']['mesero_estrella']);

        $rankingM1 = collect($reporte['meseros'])->firstWhere('id', $this->mesero1->id);
        $this->assertNotNull($rankingM1);
        $this->assertEquals($sub1, $rankingM1['ventas_netas']);
        $this->assertEquals($prop1, $rankingM1['propinas_recaudadas']);
        $this->assertEquals(100.0, $rankingM1['efectividad_propina']);

        $rankingM2 = collect($reporte['meseros'])->firstWhere('id', $this->mesero2->id);
        $this->assertNotNull($rankingM2);
        $this->assertEquals($sub2, $rankingM2['ventas_netas']);
        $this->assertEquals(0.0, $rankingM2['propinas_recaudadas']);
        $this->assertEquals(0.0, $rankingM2['efectividad_propina']);
    }

    public function test_mesero_ve_opcion_mesas_en_navegacion(): void
    {
        $response = $this->actingAs($this->mesero1)->get(route('mesas'));

        $response->assertOk();
        $response->assertSee('Salón & Mesas', false);
        $response->assertSee('Terminal POS');
        $response->assertSee('Reservas');
    }

    public function test_mesero_puede_autoasignarse_mesa_y_liberarla_para_relevo(): void
    {
        $this->mesa->update(['mesero_id' => null, 'estado' => MesaEstado::LIBRE->value]);

        // 1. Autoasignar mesa con usuario mesero logueado
        Volt::actingAs($this->mesero1)
            ->test('mesas.index')
            ->call('autoasignarMesa', $this->mesa->id)
            ->assertDispatched('notificacion');

        $this->mesa->refresh();
        $this->assertSame($this->mesero1->id, $this->mesa->mesero_id);

        // 2. Liberar mesa para relevo de turno (Opción B)
        Volt::actingAs($this->mesero1)
            ->test('mesas.index')
            ->call('liberarParaRelevo', $this->mesa->id)
            ->assertDispatched('notificacion');

        $this->mesa->refresh();
        $this->assertNull($this->mesa->mesero_id);

        $this->assertDatabaseHas('auditorias', [
            'accion' => 'mesas.liberada_relevo',
            'entidad_id' => $this->mesa->id,
            'user_id' => $this->mesero1->id,
        ]);
    }

    public function test_companero_puede_tomar_relevo_de_mesa_ocupada_y_recibe_comanda_activa(): void
    {
        $pedidoService = app(PedidoService::class);
        $mesaService = app(MesaService::class);

        // Mesa ocupada atendida por mesero 1 con pedido activo
        $this->mesa->update(['mesero_id' => $this->mesero1->id, 'estado' => MesaEstado::OCUPADA->value]);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        // Mesero 1 libera para relevo antes de entregar el turno
        $mesaService->liberarParaRelevo($this->mesa->fresh(), $this->mesero1);

        $this->mesa->refresh();
        $this->assertNull($this->mesa->mesero_id);

        // Compañero mesero 2 toma el relevo y recibe la comanda activa
        Volt::actingAs($this->mesero2)
            ->test('mesas.index')
            ->call('autoasignarMesa', $this->mesa->id)
            ->assertDispatched('notificacion');

        $this->mesa->refresh();
        $pedido->refresh();

        $this->assertSame($this->mesero2->id, $this->mesa->mesero_id);
        $this->assertSame($this->mesero2->id, $pedido->mesero_id);
    }

    public function test_mesero_no_puede_transferir_mesas_directamente_a_terceros_retorna_403(): void
    {
        $this->mesa->update(['mesero_id' => $this->mesero1->id]);

        Volt::actingAs($this->mesero1)
            ->test('mesas.index')
            ->call('abrirModalTransferir', $this->mesa->id)
            ->assertStatus(403);

        Volt::actingAs($this->mesero1)
            ->test('mesas.index')
            ->set('mesaTransferirId', $this->mesa->id)
            ->set('nuevoMeseroId', $this->mesero2->id)
            ->call('ejecutarTransferenciaMesa')
            ->assertStatus(403);
    }

    public function test_mesero_no_puede_liberar_mesa_de_otro_companero_retorna_403(): void
    {
        $this->mesa->update(['mesero_id' => $this->mesero2->id]);

        Volt::actingAs($this->mesero1)
            ->test('mesas.index')
            ->call('liberarParaRelevo', $this->mesa->id)
            ->assertStatus(403);
    }

    public function test_admin_si_puede_transferir_mesa_directamente(): void
    {
        $this->mesa->update(['mesero_id' => $this->mesero1->id]);

        Volt::actingAs($this->admin)
            ->test('mesas.index')
            ->call('abrirModalTransferir', $this->mesa->id)
            ->assertSet('modalTransferirOpen', true)
            ->set('nuevoMeseroId', $this->mesero2->id)
            ->call('ejecutarTransferenciaMesa')
            ->assertSet('modalTransferirOpen', false)
            ->assertDispatched('notificacion');

        $this->mesa->refresh();
        $this->assertSame($this->mesero2->id, $this->mesa->mesero_id);
    }

    public function test_livewire_pos_terminal_gestiona_propina_reactiva(): void
    {
        $precioUnitario = (float) $this->producto->precio;
        $propinaEsperada10 = round($precioUnitario * 0.10);

        Volt::actingAs($this->cajero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id)
            ->call('agregarProducto', $this->producto->id)
            ->call('abrirModalCobro')
            ->assertSet('tipoPropina', 'cero')
            ->assertSet('montoPropina', 0.0)
            // Seleccionar 10%
            ->call('seleccionarPropina', 'diez_porciento')
            ->assertSet('tipoPropina', 'diez_porciento')
            ->assertSet('porcentajePropina', 10.0)
            ->assertSet('montoPropina', $propinaEsperada10)
            ->assertSee('Propina del Servicio (Voluntaria)')
            // Cambiar a sin propina
            ->call('seleccionarPropina', 'cero')
            ->assertSet('tipoPropina', 'cero')
            ->assertSet('montoPropina', 0.0)
            ->assertSet('porcentajePropina', 0.0)
            // Cambiar a propina libre
            ->call('seleccionarPropina', 'personalizada')
            ->set('montoPropina', 5000.0)
            ->assertSet('montoPropina', 5000.0);
    }

    public function test_livewire_reportes_muestra_pestana_meseros(): void
    {
        Volt::actingAs($this->admin)
            ->test('reportes.index')
            ->set('pestana', 'meseros')
            ->assertSee('Rendimiento Meseros')
            ->assertSee('Tabla de Desempeño y Liquidación de Propinas')
            ->assertSee('Ventas Salón')
            ->assertSee('Propinas Recaudadas');
    }

    public function test_cobro_bloqueado_cuando_comanda_sigue_activa_en_cocina(): void
    {
        $pedidoService = app(PedidoService::class);

        $this->mesa->update([
            'mesero_id' => $this->mesero1->id,
            'estado' => MesaEstado::OCUPADA->value,
        ]);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        $pedidoService->enviarACocina($pedido);

        try {
            $pedidoService->cobrarPedido($pedido->fresh(), 'efectivo', (float) $pedido->fresh()->total, null, 0.0, 0.0);
            $this->fail('Cobrar con comanda activa en cocina debería lanzar 422.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('comanda', $e->getMessage());
        }
    }

    public function test_cobro_con_todo_servido_libera_mesero_de_la_mesa(): void
    {
        $pedidoService = app(PedidoService::class);

        $this->mesa->update([
            'mesero_id' => $this->mesero1->id,
            'estado' => MesaEstado::OCUPADA->value,
        ]);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        $pedido->items()->update(['estado_cocina' => 'entregado']);
        $pedido->update(['estado' => 'entregado']);

        $pedidoCobrado = $pedidoService->cobrarPedido(
            $pedido->fresh(), 'efectivo', (float) $pedido->fresh()->total, null, 0.0, 0.0
        );

        $this->assertSame('pagado', $pedidoCobrado->estado);
        $this->mesa->refresh();
        $this->assertNull($this->mesa->mesero_id);
        $this->assertSame(MesaEstado::POR_LIMPIAR->value, $this->mesa->estado);
    }

    public function test_cobro_rapido_sin_enviar_a_cocina_sigue_permitido(): void
    {
        $pedidoService = app(PedidoService::class);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mostrador', 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->cajero
        );

        $pedidoCobrado = $pedidoService->cobrarPedido(
            $pedido, 'efectivo', (float) $pedido->total, null, 0.0, 0.0
        );

        $this->assertSame('pagado', $pedidoCobrado->estado);
    }

    public function test_mesero_no_puede_crear_pedido_en_mesa_asignada_a_otro_mesero(): void
    {
        $pedidoService = app(PedidoService::class);

        $this->mesa->update([
            'mesero_id' => $this->mesero1->id,
            'estado' => MesaEstado::OCUPADA->value,
        ]);

        $this->expectException(AuthorizationException::class);

        $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero2
        );
    }

    public function test_autoasignar_mesa_de_otro_mesero_queda_bloqueado(): void
    {
        $mesaService = app(MesaService::class);

        $this->mesa->update([
            'mesero_id' => $this->mesero1->id,
            'estado' => MesaEstado::OCUPADA->value,
        ]);

        $this->expectException(AuthorizationException::class);

        $mesaService->autoasignarMesa($this->mesa, $this->mesero2);
    }

    public function test_cajero_puede_cobrar_mesa_atendida_por_mesero(): void
    {
        $pedidoService = app(PedidoService::class);

        $this->mesa->update([
            'mesero_id' => $this->mesero1->id,
            'estado' => MesaEstado::OCUPADA->value,
        ]);

        $pedido = $pedidoService->crearPedido(
            ['tipo' => 'mesa', 'mesa_id' => $this->mesa->id, 'sucursal_id' => $this->sucursal->id],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => (float) $this->producto->precio]],
            $this->mesero1
        );

        $pedido->items()->update(['estado_cocina' => 'entregado']);

        $this->actingAs($this->cajero);

        $pedidoCobrado = $pedidoService->cobrarPedido(
            $pedido->fresh(), 'efectivo', (float) $pedido->fresh()->total, null, 0.0, 0.0
        );

        $this->assertSame('pagado', $pedidoCobrado->estado);
    }

    public function test_dropdown_pos_muestra_mesero_asignado_de_la_mesa(): void
    {
        $this->mesero1->update(['name' => 'Carlos Atencion Mesa']);

        $this->mesa->update([
            'mesero_id' => $this->mesero1->id,
            'estado' => MesaEstado::OCUPADA->value,
        ]);

        Volt::actingAs($this->mesero2)
            ->test('pos.terminal')
            // El modal Bento de mesas muestra el mesero asignado en la tarjeta
            // (sin el prefijo "·" del dropdown anterior, solo existe en vista móvil).
            ->assertSee('Carlos Atencion Mesa');
    }
}
