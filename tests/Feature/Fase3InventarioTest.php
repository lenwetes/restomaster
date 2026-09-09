<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\InventarioService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase3InventarioTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Sucursal $sucursal;
    protected Mesa $mesa;
    protected Insumo $salmon;
    protected Insumo $arroz;
    protected Producto $philadelphiaRoll;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create([
            'nombre' => 'Administrador',
            'slug' => 'admin',
            'descripcion' => 'Acceso total',
        ]);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Poblado MDE-01',
            'direccion' => 'Calle 10 # 36-24',
            'telefono' => '+57 300 123 4567',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Chef Administrador Test',
            'email' => 'admin_inv@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 5,
            'nombre' => 'Mesa 05',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Rolls Clásicos',
            'slug' => 'rolls-clasicos',
            'icono' => '🍣',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->philadelphiaRoll = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Philadelphia Roll',
            'slug' => 'philadelphia-roll',
            'descripcion' => 'Salmón fresco y queso crema',
            'precio' => 10.50,
            'costo' => 4.10,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $this->salmon = Insumo::create([
            'nombre' => 'Lomo de Salmón Pacífico',
            'codigo' => 'SKU-PES-01',
            'categoria' => 'pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 5.000,
            'stock_minimo' => 4.000,
            'capacidad_maxima' => 20.000,
            'costo_unitario' => 50.00,
            'proveedor_nombre' => 'Bahía Solano Seafood',
            'activo' => true,
        ]);

        $this->arroz = Insumo::create([
            'nombre' => 'Arroz Koshihikari Shari',
            'codigo' => 'SKU-ARR-01',
            'categoria' => 'arroz_granos',
            'unidad_medida' => 'kg',
            'stock_actual' => 20.000,
            'stock_minimo' => 10.000,
            'capacidad_maxima' => 50.000,
            'costo_unitario' => 10.00,
            'proveedor_nombre' => 'Molinos Oriente',
            'activo' => true,
        ]);

        // Receta: 1 roll requiere 0.060 kg salmón y 0.120 kg arroz
        Receta::create([
            'producto_id' => $this->philadelphiaRoll->id,
            'insumo_id' => $this->salmon->id,
            'cantidad' => 0.060,
            'merma_esperada_pct' => 0.0,
            'notas' => 'Corte sashimi lomo',
        ]);

        Receta::create([
            'producto_id' => $this->philadelphiaRoll->id,
            'insumo_id' => $this->arroz->id,
            'cantidad' => 0.120,
            'merma_esperada_pct' => 0.0,
            'notas' => 'Shari cocido',
        ]);
    }

    public function test_modelo_insumo_calcula_estados_y_valores(): void
    {
        $this->assertFalse($this->salmon->es_critico);
        $this->assertEquals(250.00, $this->salmon->valor_stock);

        // Si el stock cae a nivel mínimo o inferior
        $this->salmon->update(['stock_actual' => 3.500]);
        $this->assertTrue($this->salmon->fresh()->es_critico);
    }

    public function test_modelo_producto_calcula_costo_receta(): void
    {
        // Salmón: 0.060 * 50 = 3.00, Arroz: 0.120 * 10 = 1.20 => Total 4.20
        $this->assertEquals(4.20, $this->philadelphiaRoll->costo_receta);
    }

    public function test_descontar_por_item_pedido_deduce_stock_y_registra_kardex(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'PED-TEST-01',
            'tipo' => 'mesa',
            'mesa_id' => $this->mesa->id,
            'usuario_id' => $this->admin->id,
            'estado' => 'en_cocina',
            'subtotal' => 21.00,
            'total' => 21.00,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->philadelphiaRoll->id,
            'nombre_producto' => $this->philadelphiaRoll->nombre,
            'cantidad' => 2, // 2 rolls -> 0.120 kg salmón, 0.240 kg arroz
            'precio_unitario' => 10.50,
            'subtotal' => 21.00,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'en_preparacion',
        ]);

        $service = app(InventarioService::class);
        $resultado = $service->descontarPorItemPedido($item);

        $this->assertTrue($resultado);
        $this->assertTrue($item->fresh()->inventario_descontado);

        // 5.000 - (0.060 * 2) = 4.880
        $this->assertEquals(4.880, (float) $this->salmon->fresh()->stock_actual);
        // 20.000 - (0.120 * 2) = 19.760
        $this->assertEquals(19.760, (float) $this->arroz->fresh()->stock_actual);

        // Movimientos Kardex registrados
        $movSalmón = MovimientoInventario::where('insumo_id', $this->salmon->id)
            ->where('tipo', 'consumo_venta')
            ->first();

        $this->assertNotNull($movSalmón);
        $this->assertEquals(0.120, (float) $movSalmón->cantidad);
        $this->assertEquals(5.000, (float) $movSalmón->saldo_anterior);
        $this->assertEquals(4.880, (float) $movSalmón->saldo_posterior);
        $this->assertEquals(6.00, (float) $movSalmón->costo_total); // 0.120 * 50
    }

    public function test_descuento_es_idempotente(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'PED-TEST-02',
            'tipo' => 'mostrador',
            'usuario_id' => $this->admin->id,
            'estado' => 'creado',
            'subtotal' => 10.50,
            'total' => 10.50,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->philadelphiaRoll->id,
            'nombre_producto' => $this->philadelphiaRoll->nombre,
            'cantidad' => 1,
            'precio_unitario' => 10.50,
            'subtotal' => 10.50,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        $service = app(InventarioService::class);

        // Primera deducción
        $primerIntento = $service->descontarPorItemPedido($item);
        $this->assertTrue($primerIntento);
        $stockIntermedio = (float) $this->salmon->fresh()->stock_actual;

        // Segundo intento no debe restar nuevamente
        $segundoIntento = $service->descontarPorItemPedido($item->fresh());
        $this->assertFalse($segundoIntento);
        $this->assertEquals($stockIntermedio, (float) $this->salmon->fresh()->stock_actual);
    }

    public function test_marcar_item_listo_en_cocina_descuenta_inventario_automaticamente(): void
    {
        $pedido = Pedido::create([
            'codigo' => 'PED-TEST-03',
            'tipo' => 'mesa',
            'mesa_id' => $this->mesa->id,
            'usuario_id' => $this->admin->id,
            'estado' => 'en_cocina',
            'subtotal' => 10.50,
            'total' => 10.50,
        ]);

        $item = ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->philadelphiaRoll->id,
            'nombre_producto' => $this->philadelphiaRoll->nombre,
            'cantidad' => 1,
            'precio_unitario' => 10.50,
            'subtotal' => 10.50,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'en_preparacion',
        ]);

        $pedidoService = app(PedidoService::class);
        $pedidoService->marcarItemListo($item);

        $this->assertTrue($item->fresh()->inventario_descontado);
        $this->assertEquals('listo', $item->fresh()->estado_cocina);
        $this->assertEquals(4.940, (float) $this->salmon->fresh()->stock_actual);
    }

    public function test_registrar_compra_actualiza_stock_y_costo_promedio_ponderado(): void
    {
        $service = app(InventarioService::class);

        // Stock actual: 5.0 kg a $50.00 c/u = $250.00
        // Compra: 10.0 kg a $40.00 c/u = $400.00
        // Total: 15.0 kg valuados en $650.00 => Nuevo costo promedio: 650 / 15 = $43.33
        $movimiento = $service->registrarCompra(
            $this->salmon->id,
            10.0,
            40.0,
            'Nuevo Proveedor del Pacífico',
            'FAC-12345',
            $this->admin->id
        );

        $salmonActualizado = $this->salmon->fresh();
        $this->assertEquals(15.000, (float) $salmonActualizado->stock_actual);
        $this->assertEquals(43.33, (float) $salmonActualizado->costo_unitario);
        $this->assertEquals('compra', $movimiento->tipo);
        $this->assertEquals(400.00, (float) $movimiento->costo_total);
    }

    public function test_registrar_merma_reduce_stock_y_registra_motivo(): void
    {
        $service = app(InventarioService::class);

        $movimiento = $service->registrarMerma(
            $this->salmon->id,
            0.500,
            'Deterioro por corte y merma de cola',
            $this->admin->id
        );

        $this->assertEquals(4.500, (float) $this->salmon->fresh()->stock_actual);
        $this->assertEquals('merma', $movimiento->tipo);
        $this->assertEquals(25.00, (float) $movimiento->costo_total); // 0.5 * 50
    }

    public function test_registrar_ajuste_por_conteo_fisico(): void
    {
        $service = app(InventarioService::class);

        $movimiento = $service->registrarAjuste(
            $this->salmon->id,
            7.500, // Estaba en 5.0 -> ajuste positivo de 2.5
            'Conteo físico semanal',
            $this->admin->id
        );

        $this->assertEquals(7.500, (float) $this->salmon->fresh()->stock_actual);
        $this->assertEquals('ajuste_positivo', $movimiento->tipo);
        $this->assertEquals(2.500, (float) $movimiento->cantidad);
    }

    public function test_pantalla_inventario_requiere_autenticacion(): void
    {
        $response = $this->get(route('inventario'));
        $response->assertRedirect(route('login'));
    }

    public function test_pantalla_inventario_renderiza_con_usuario_autenticado(): void
    {
        $response = $this->actingAs($this->admin)->get(route('inventario'));
        $response->assertOk();
        $response->assertSee('Gestión de Inventario y Materias Primas');
        $response->assertSee('Lomo de Salmón Pacífico');
        $response->assertSee('Arroz Koshihikari Shari');
    }
}
