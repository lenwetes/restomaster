<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardEjecutivoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Sucursal $sucursal;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Poblado',
            'codigo' => 'MDE-01',
            'direccion' => 'Calle 10 # 30-15',
            'activa' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Chef Master Admin',
            'email' => 'admin@restomaster.com',
            'role_id' => $roleAdmin->id,
            'activo' => true,
        ]);

        $this->service = app(DashboardService::class);
    }

    public function test_kpis_generales_calcula_ventas_y_margenes_correctamente(): void
    {
        $categoria = Categoria::create(['nombre' => 'Sushi', 'slug' => 'sushi', 'orden' => 1, 'activo' => true]);
        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Roll Salmón Trufado',
            'slug' => 'roll-salmon',
            'precio' => 40000,
            'costo' => 12000,
            'activo' => true,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'PED-001',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'usuario_id' => $this->admin->id,
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 80000,
            'total' => 80000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => $producto->nombre,
            'cantidad' => 2,
            'precio_unitario' => 40000,
            'subtotal' => 80000,
        ]);

        $kpis = $this->service->kpisGenerales('hoy');

        $this->assertEquals(80000.0, $kpis['ventas']);
        $this->assertEquals(1, $kpis['transacciones']);
        $this->assertEquals(80000.0, $kpis['ticket_promedio']);
        $this->assertEquals(24000.0, $kpis['costo_vendido']); // 2 * 12.000
        $this->assertEquals(30.0, $kpis['food_cost_pct']); // 24.000 / 80.000 = 30%
        $this->assertEquals(56000.0, $kpis['margen_bruto']); // 80.000 - 24.000
    }

    public function test_insumos_en_alerta_identifica_agotados_y_stock_critico(): void
    {
        $proveedor = Proveedor::create([
            'nombre' => 'Pescadería del Mar',
            'contacto' => 'Carlos Pescador',
            'telefono' => '3001234567',
            'activo' => true,
        ]);

        // Insumo 1: Agotado (stock <= 0)
        Insumo::create([
            'nombre' => 'Salmón Fresco Premium',
            'codigo' => 'INS-SAL',
            'unidad_medida' => 'kg',
            'stock_actual' => 0.0,
            'stock_minimo' => 5.0,
            'costo_unitario' => 45000,
            'proveedor_id' => $proveedor->id,
            'activo' => true,
        ]);

        // Insumo 2: Crítico (stock actual <= stock mínimo)
        Insumo::create([
            'nombre' => 'Queso Crema Philadelphia',
            'codigo' => 'INS-QSO',
            'unidad_medida' => 'kg',
            'stock_actual' => 2.0,
            'stock_minimo' => 4.0,
            'costo_unitario' => 22000,
            'proveedor_id' => $proveedor->id,
            'activo' => true,
        ]);

        // Insumo 3: Normal (stock holgado)
        Insumo::create([
            'nombre' => 'Arroz de Sushi Shinode',
            'codigo' => 'INS-ARR',
            'unidad_medida' => 'kg',
            'stock_actual' => 50.0,
            'stock_minimo' => 10.0,
            'costo_unitario' => 8000,
            'activo' => true,
        ]);

        $alertas = $this->service->insumosEnAlerta();

        $this->assertEquals(2, $alertas['total_alertas']);
        $this->assertCount(1, $alertas['agotados']);
        $this->assertEquals('Salmón Fresco Premium', $alertas['agotados']->first()->nombre);
        $this->assertCount(1, $alertas['criticos']);
        $this->assertEquals('Queso Crema Philadelphia', $alertas['criticos']->first()->nombre);
    }

    public function test_pulso_operativo_en_vivo_detecta_caja_kds_y_aforo(): void
    {
        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);
        TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->admin->id,
            'apertura_en' => now()->subHours(2),
            'monto_inicial' => 150000,
            'total_ventas_efectivo' => 200000,
            'total_ventas_tarjeta' => 300000,
            'total_ventas_transferencia' => 50000,
            'monto_esperado_efectivo' => 350000,
            'estado' => 'abierto',
        ]);

        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 1, 'zona' => 'salon', 'capacidad' => 4, 'estado' => MesaEstado::OCUPADA->value]);
        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 2, 'zona' => 'salon', 'capacidad' => 2, 'estado' => MesaEstado::LIBRE->value]);

        // Comanda activa demorada (>20 min)
        $comandaDemorada = Pedido::create([
            'codigo' => 'KDS-999',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'usuario_id' => $this->admin->id,
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 35000,
            'total' => 35000,
        ]);
        $comandaDemorada->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $pulso = $this->service->pulsoOperativo();

        $this->assertTrue($pulso['caja']['hay_turno_abierto']);
        $this->assertEquals(200000.0, $pulso['caja']['ventas_efectivo']);
        $this->assertEquals(350000.0, $pulso['caja']['ventas_digital']); // 300k + 50k
        $this->assertEquals(1, $pulso['kds']['total_activas']);
        $this->assertEquals(1, $pulso['kds']['demoradas']);
        $this->assertTrue($pulso['kds']['alerta_demora']);
        $this->assertEquals(1, $pulso['salon']['mesas_ocupadas']);
        $this->assertEquals(4, $pulso['salon']['comensales_en_sala']);
    }

    public function test_componente_livewire_permite_cambiar_periodo_reactivamente(): void
    {
        $this->actingAs($this->admin);

        Volt::test('dashboard.ejecutivo')
            ->assertSet('periodo', 'hoy')
            ->assertSee('Panel Ejecutivo RestoMaster')
            ->assertSee('Ventas Facturadas')
            ->assertSee('Curva de Ventas')
            ->call('setPeriodo', 'semana')
            ->assertSet('periodo', 'semana')
            ->assertSee('Esta Semana')
            ->call('setPeriodo', 'mes')
            ->assertSet('periodo', 'mes')
            ->assertSee('Este Mes');
    }

    public function test_acceso_a_dashboard_renderiza_vista_sin_lanzadera_estatica(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        // Verifica que la antigua lanzadera redundante ya NO existe
        $response->assertDontSee('Lanzadera de Operaciones Táctiles');
        $response->assertDontSee('Accesos rápidos optimizados para flujo de servicio continuo');

        // Verifica que los nuevos componentes ejecutivos existen
        $response->assertSee('Alerta Primaria de Inventario');
        $response->assertSee('Curva de Ventas');
        $response->assertSee('Pulso de Operaciones en Vivo');
        $response->assertSee('Top Platos Vendidos');
        $response->assertSee('Rendimiento de Meseros');
    }

    public function test_ranking_de_meseros_calcula_ventas_y_propinas_correctamente(): void
    {
        $roleMesero = Role::where('slug', 'mesero')->first();

        $mesero1 = User::factory()->create([
            'name' => 'Carlos Mesero',
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $mesero2 = User::factory()->create([
            'name' => 'Andrea Mesera',
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 10,
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::OCUPADA->value,
            'mesero_id' => $mesero1->id,
        ]);

        Pedido::create([
            'codigo' => 'PED-M1-1',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'usuario_id' => $mesero1->id,
            'mesero_id' => $mesero1->id,
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 100000,
            'total' => 110000,
            'propina' => 10000,
            'pagado_en' => now(),
        ]);

        Pedido::create([
            'codigo' => 'PED-M1-2',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'usuario_id' => $mesero1->id,
            'mesero_id' => $mesero1->id,
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 50000,
            'total' => 55000,
            'propina' => 5000,
            'pagado_en' => now(),
        ]);

        Pedido::create([
            'codigo' => 'PED-M2-1',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'usuario_id' => $mesero2->id,
            'mesero_id' => $mesero2->id,
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 40000,
            'total' => 44000,
            'propina' => 4000,
            'pagado_en' => now(),
        ]);

        $ranking = $this->service->rankingMeseros('hoy', $this->sucursal->id);

        $this->assertEquals(2, $ranking['total_meseros']);
        $this->assertEquals(19000.0, $ranking['total_propinas']);
        $this->assertEquals(3, $ranking['total_pedidos']);

        // Carlos debe ser #1 con 165k (110k + 55k) y 1 mesa activa
        $top1 = $ranking['meseros'][0];
        $this->assertEquals($mesero1->id, $top1['id']);
        $this->assertEquals(1, $top1['posicion']);
        $this->assertEquals(165000.0, $top1['total_ventas']);
        $this->assertEquals(15000.0, $top1['total_propinas']);
        $this->assertEquals(2, $top1['total_pedidos']);
        $this->assertEquals(82500.0, $top1['ticket_promedio']);
        $this->assertEquals(1, $top1['mesas_activas']);

        // Andrea debe ser #2 con 44k
        $top2 = $ranking['meseros'][1];
        $this->assertEquals($mesero2->id, $top2['id']);
        $this->assertEquals(2, $top2['posicion']);
        $this->assertEquals(44000.0, $top2['total_ventas']);
        $this->assertEquals(4000.0, $top2['total_propinas']);
        $this->assertEquals(0, $top2['mesas_activas']);
    }

    public function test_ventas_por_hora_cubre_madrugada_00_a_23(): void
    {
        $mesero = User::factory()->create([
            'role_id' => Role::where('slug', 'mesero')->value('id'),
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        Pedido::create([
            'codigo' => 'PED-MADRU-1',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'usuario_id' => $mesero->id,
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 70000,
            'total' => 70000,
            'pagado_en' => now()->subDay()->startOfDay()->addHours(2),
        ]);

        $curva = $this->service->ventasPorHora('ayer', $this->sucursal->id);

        $this->assertCount(24, $curva);
        $this->assertSame('00:00', $curva[0]['hora']);
        $this->assertSame('23:00', $curva[23]['hora']);

        $porHora = collect($curva)->keyBy('hora');
        $this->assertEquals(70000.0, $porHora['02:00']['ventas']);
        $this->assertSame(1, $porHora['02:00']['transacciones']);
        $this->assertGreaterThan(0, $porHora['02:00']['pct_altura']);
    }
}
