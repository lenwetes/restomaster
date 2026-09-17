<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use App\Services\ClienteService;
use App\Services\DeliveryService;
use App\Services\FidelizacionService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase4ClientesDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $repartidor;

    protected Sucursal $sucursal;

    protected Caja $caja;

    protected Mesa $mesa;

    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create([
            'nombre' => 'Administrador',
            'slug' => 'admin',
            'descripcion' => 'Acceso total',
        ]);

        $roleRepartidor = Role::create([
            'nombre' => 'Repartidor',
            'slug' => 'repartidor',
            'descripcion' => 'Motorizado de despacho',
        ]);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Poblado MDE-01',
            'codigo' => 'MDE-01',
            'direccion' => 'Calle 10 # 36-24, Medellín',
            'telefono' => '+57 300 123 4567',
            'activa' => true,
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal Salón',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Delivery Test',
            'email' => 'admin_delivery@sushixpress.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->repartidor = User::create([
            'name' => 'Carlos Mario Arango (Moto 1)',
            'email' => 'carlos_moto@sushixpress.com',
            'password' => bcrypt('password'),
            'role_id' => $roleRepartidor->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 1,
            'nombre' => 'Mesa 01',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Sushi Rolls',
            'slug' => 'sushi-rolls',
            'icono' => '🍣',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Tiger Roll Especial',
            'slug' => 'tiger-roll-especial',
            'descripcion' => 'Langostino crocante y salmón flameado',
            'precio' => 45000.00,
            'costo' => 18000.00,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_cliente_creacion_y_busqueda_por_telefono(): void
    {
        $clienteService = app(ClienteService::class);

        $cliente = $clienteService->crear([
            'nombre' => 'Juan Esteban Gómez',
            'telefono' => '3009998877',
            'email' => 'juanes@gmail.com',
            'direccion' => 'Cra 43A # 7-50 Apto 802',
            'barrio_ciudad' => 'El Poblado, Medellín',
            'notas_entrega' => 'Timbre 802, reja blanca',
        ]);

        $this->assertNotNull($cliente->id);
        $this->assertEquals('Juan Esteban Gómez', $cliente->nombre);
        $this->assertEquals('3009998877', $cliente->telefono);
        $this->assertEquals(0, $cliente->puntos_fidelidad);
        $this->assertEquals('regular', $cliente->tier);

        // Búsqueda por término (teléfono y nombre)
        $busquedaTel = $clienteService->buscar('3009998877');
        $this->assertCount(1, $busquedaTel);
        $this->assertEquals($cliente->id, $busquedaTel->first()->id);

        $busquedaNombre = $clienteService->buscar('Esteban');
        $this->assertCount(1, $busquedaNombre);
        $this->assertEquals($cliente->id, $busquedaNombre->first()->id);

        // Validación de teléfono duplicado
        $this->expectException(\InvalidArgumentException::class);
        $clienteService->crear([
            'nombre' => 'Otro Cliente Duplicado',
            'telefono' => '3009998877',
        ]);
    }

    public function test_direcciones_cliente_y_predeterminada(): void
    {
        $clienteService = app(ClienteService::class);

        $cliente = $clienteService->crear([
            'nombre' => 'Mariana Ochoa',
            'telefono' => '3115554433',
        ]);

        $dirCasa = $clienteService->agregarDireccion($cliente, [
            'etiqueta' => 'Casa',
            'direccion' => 'Calle 10 # 20-30',
            'barrio_ciudad' => 'Castropol, Medellín',
            'es_predeterminada' => true,
        ]);

        $this->assertTrue($dirCasa->es_predeterminada);
        $this->assertEquals('Calle 10 # 20-30 · Castropol, Medellín', $dirCasa->direccion_completa);

        // Agregar segunda dirección marcada como predeterminada
        $dirOficina = $clienteService->agregarDireccion($cliente, [
            'etiqueta' => 'Oficina',
            'direccion' => 'Cra 43A # 1-50 Torre 2',
            'barrio_ciudad' => 'San Fernando, Medellín',
            'es_predeterminada' => true,
        ]);

        $this->assertTrue($dirOficina->fresh()->es_predeterminada);
        // La dirección previa debió desmarcarse de predeterminada
        $this->assertFalse($dirCasa->fresh()->es_predeterminada);
    }

    public function test_acumulacion_y_canje_de_puntos_fidelizacion(): void
    {
        $clienteService = app(ClienteService::class);
        $fidelizacionService = app(FidelizacionService::class);
        $pedidoService = app(PedidoService::class);

        $cliente = $clienteService->crear([
            'nombre' => 'Andrés Cepeda',
            'telefono' => '3157778899',
        ]);

        app(CajaService::class)->abrirTurno($this->caja, $this->admin, 100000.00, 'Apertura');

        // Crear pedido de salón de $90.000 COP (2 Tiger Rolls)
        $pedido = $pedidoService->crearPedido(
            [
                'mesa_id' => $this->mesa->id,
                'tipo' => 'mesa',
                'cliente_id' => $cliente->id,
            ],
            [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 2,
                    'precio_unitario' => 45000.00,
                ],
            ],
            $this->admin
        );

        $this->assertEquals(90000.00, $pedido->fresh()->total);

        // Cobrar pedido -> Debería generar 9 puntos (90.000 / 10.000)
        $pedidoService->cobrarPedido($pedido, 'tarjeta', 90000.00);

        $cliente->refresh();
        $this->assertEquals(9, $cliente->puntos_fidelidad);
        $this->assertEquals(90000.00, $cliente->total_gastado);
        $this->assertEquals(1, $cliente->visitas_count);

        // Canjear 4 puntos (descuento = 4 * $10 = $40 COP) en un nuevo pedido
        $pedido2 = $pedidoService->crearPedido(
            [
                'mesa_id' => $this->mesa->id,
                'tipo' => 'mesa',
                'cliente_id' => $cliente->id,
            ],
            [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 1,
                    'precio_unitario' => 45000.00,
                ],
            ],
            $this->admin
        );

        $movimientoCanje = $fidelizacionService->canjearPuntos($cliente, 4, $pedido2);
        $this->assertEquals(-4, $movimientoCanje->puntos);
        $this->assertEquals(40.00, $pedido2->fresh()->descuento_puntos);

        $cliente->refresh();
        $this->assertEquals(5, $cliente->puntos_fidelidad);

        // Ajuste manual de puntos auditado
        $fidelizacionService->ajustarPuntos($cliente, 150, 'Bono de bienvenida', $this->admin);
        $cliente->refresh();
        $this->assertEquals(155, $cliente->puntos_fidelidad);
    }

    public function test_creacion_y_flujo_pedido_delivery(): void
    {
        $clienteService = app(ClienteService::class);
        $deliveryService = app(DeliveryService::class);

        $cliente = $clienteService->crear([
            'nombre' => 'Sofía Vergara',
            'telefono' => '3201112233',
            'direccion' => 'Carrera 25 # 3-100 Apto 1201',
            'barrio_ciudad' => 'El Tesoro, Medellín',
        ]);

        $direccion = $cliente->direcciones()->first();

        // Crear pedido delivery
        $pedido = $deliveryService->crearPedidoDelivery([
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->admin->id,
            'cliente_id' => $cliente->id,
            'direccion_cliente_id' => $direccion->id,
            'costo_envio' => 8000.00,
            'metodo_pago' => 'efectivo',
            'notas_delivery' => 'Pagar con billete de $100.000 COP',
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 45000.00,
            'subtotal' => 45000.00,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        $pedido->recalcularTotales();
        $pedido->refresh();

        $this->assertEquals('delivery', $pedido->tipo);
        $this->assertEquals('pendiente', $pedido->estado_delivery);
        $this->assertEquals(8000.00, $pedido->costo_envio);
        $this->assertEquals(53000.00, $pedido->total); // 45000 + 8000
        $this->assertNull($pedido->repartidor_id);

        // 1. Asignar motorizado
        $deliveryService->asignarRepartidor($pedido, $this->repartidor);
        $pedido->refresh();
        $this->assertEquals($this->repartidor->id, $pedido->repartidor_id);
        $this->assertEquals('asignado', $pedido->estado_delivery);

        // 2. Despachar a ruta
        $deliveryService->marcarSalida($pedido);
        $pedido->refresh();
        $this->assertEquals('en_ruta', $pedido->estado_delivery);
        $this->assertNotNull($pedido->hora_despacho);

        // 3. Confirmar entrega
        $deliveryService->marcarEntregado($pedido);
        $pedido->refresh();
        $this->assertEquals('entregado', $pedido->estado_delivery);
        $this->assertEquals('entregado', $pedido->estado);
        $this->assertNotNull($pedido->hora_entrega);
    }

    public function test_liquidacion_recaudo_efectivo_motorizado_en_caja(): void
    {
        $clienteService = app(ClienteService::class);
        $deliveryService = app(DeliveryService::class);
        $cajaService = app(CajaService::class);

        // Abrir turno de caja
        $turnoCaja = $cajaService->abrirTurno($this->caja, $this->admin, 200000.00, 'Apertura inicial');

        $cliente = $clienteService->crear([
            'nombre' => 'Pedro Pascal',
            'telefono' => '3014445566',
        ]);

        // Crear pedido delivery pagado en efectivo
        $pedido = $deliveryService->crearPedidoDelivery([
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->admin->id,
            'cliente_id' => $cliente->id,
            'costo_envio' => 5000.00,
            'metodo_pago' => 'efectivo',
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 45000.00,
            'subtotal' => 45000.00,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        $pedido->recalcularTotales();
        $pedido->refresh();
        $this->assertEquals(50000.00, $pedido->total);

        // Asignar, despachar y entregar cobrado contra entrega
        $deliveryService->asignarRepartidor($pedido, $this->repartidor);
        $deliveryService->marcarSalida($pedido);
        $deliveryService->marcarEntregado($pedido, 'efectivo', 50000.00);

        $pedido->refresh();
        $this->assertFalse((bool) $pedido->recaudo_liquidado);

        // Liquidar efectivo en caja
        $totalLiquidado = $deliveryService->liquidarRecaudoRepartidor($this->repartidor, $turnoCaja);

        $this->assertEquals(50000.00, $totalLiquidado);

        $pedido->refresh();
        $this->assertTrue((bool) $pedido->recaudo_liquidado);

        // Verificar que no se duplica con un movimiento ingreso extra (H1 COD fix)
        $this->assertDatabaseMissing('movimientos_caja', [
            'turno_caja_id' => $turnoCaja->id,
            'tipo' => 'ingreso',
            'monto' => 50000.00,
        ]);
        $turnoCaja->refresh();
        $this->assertEquals(50000.00, (float) $turnoCaja->total_ventas_efectivo);
    }

    public function test_pantalla_clientes_vip_renderiza_correctamente(): void
    {
        $response = $this->actingAs($this->admin)->get(route('clientes'));

        $response->assertStatus(200);
        $response->assertSee('Club Gourmet VIP');
        $response->assertSee('Directorio Activo');
    }

    public function test_pantalla_despacho_delivery_renderiza_correctamente(): void
    {
        $response = $this->actingAs($this->admin)->get(route('delivery'));

        $response->assertStatus(200);
        $response->assertSee('Cola de Despacho');
        $response->assertSee('Flota Delivery');
        $response->assertSee('Flota de Motorizados');
    }
}
