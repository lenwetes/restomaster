<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\Ai\AdminAiCopilotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2 — Arquitectura Agéntica (Gemini Function Calling)
 *
 * 2.1 — 5 tools con consultas DB reales
 * 2.2 — Integración Function Calling + multi-turn + fallback offline
 */
class AdminAiCopilotFunctionCallingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $mesero;

    protected Sucursal $sucursal;

    protected Caja $caja;

    protected TurnoCaja $turno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Test FunctionCalling',
            'direccion' => 'Calle 1 # 1-1',
            'telefono' => '3001234500',
            'activo' => true,
        ]);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Admin FC',
            'email' => 'admin.fc@test.local',
        ]);

        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Mesero FC',
            'email' => 'mesero.fc@test.local',
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal FC',
            'codigo' => 'CAJ-01',
            'tipo' => 'principal',
            'activa' => true,
        ]);

        $this->turno = TurnoCaja::create([
            'caja_id' => $this->caja->id,
            'user_id' => $this->admin->id,
            'apertura_en' => now()->subHours(3),
            'monto_inicial' => 200000,
            'estado' => 'abierto',
        ]);
    }

    // ── 2.1 Catálogo ──────────────────────────────────────────────

    public function test_definiciones_herramientas_contiene_seis_tools_con_esquema_gemini(): void
    {
        $tools = AdminAiCopilotService::definicionesHerramientas();

        $this->assertCount(6, $tools);

        $nombres = collect($tools)->pluck('name')->all();
        $this->assertContains('consultar_ventas', $nombres);
        $this->assertContains('consultar_movimientos_caja', $nombres);
        $this->assertContains('consultar_inventario', $nombres);
        $this->assertContains('consultar_rendimiento_meseros', $nombres);
        $this->assertContains('consultar_platos_estrella', $nombres);
        $this->assertContains('ejecutar_sql_analytics', $nombres);

        foreach ($tools as $tool) {
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('parameters', $tool);
            $this->assertArrayHasKey('properties', $tool['parameters']);
        }
    }

    public function test_tool_consultar_ventas_retorna_totales_reales(): void
    {
        Pedido::create([
            'codigo' => 'PED-FC-001',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'turno_caja_id' => $this->turno->id,
            'subtotal' => 85000,
            'descuento' => 0,
            'total' => 85000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->ejecutarTool('consultar_ventas', ['periodo' => 'hoy', 'agrupacion' => 'hora'], $this->admin);

        $this->assertTrue($resultado['ok']);
        $this->assertEquals('consultar_ventas', $resultado['tool']);
        $this->assertEquals(85000, (float) $resultado['datos']['total_ventas']);
        $this->assertEquals(1, (int) $resultado['datos']['total_pedidos']);
    }

    public function test_tool_consultar_movimientos_caja_filtra_por_tipo(): void
    {
        MovimientoCaja::create([
            'turno_caja_id' => $this->turno->id,
            'user_id' => $this->admin->id,
            'tipo' => 'egreso',
            'concepto' => 'Compra servilletas',
            'monto' => 15000,
        ]);
        MovimientoCaja::create([
            'turno_caja_id' => $this->turno->id,
            'user_id' => $this->admin->id,
            'tipo' => 'retiro',
            'concepto' => 'Retiro banco',
            'monto' => 50000,
        ]);

        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->ejecutarTool('consultar_movimientos_caja', ['tipo' => 'egreso'], $this->admin);

        $this->assertTrue($resultado['ok']);
        $this->assertEquals(15000, (float) $resultado['datos']['total_monto']);
        $this->assertEquals(1, (int) $resultado['datos']['total_movimientos']);
    }

    public function test_tool_consultar_inventario_solo_alertas_y_por_nombre(): void
    {
        Insumo::create([
            'nombre' => 'Salmon FC Critico',
            'codigo' => 'SAL-FC-01',
            'categoria' => 'Pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 1.0,
            'stock_minimo' => 10.0,
            'costo_unitario' => 45000,
            'activo' => true,
        ]);
        Insumo::create([
            'nombre' => 'Arroz FC Ok',
            'codigo' => 'ARR-FC-01',
            'categoria' => 'Granos',
            'unidad_medida' => 'kg',
            'stock_actual' => 100.0,
            'stock_minimo' => 10.0,
            'costo_unitario' => 5000,
            'activo' => true,
        ]);

        $copilot = app(AdminAiCopilotService::class);

        $alertas = $copilot->ejecutarTool('consultar_inventario', ['solo_alertas' => true], $this->admin);
        $this->assertTrue($alertas['ok']);
        $this->assertEquals(1, (int) $alertas['datos']['total_alertas']);

        $porNombre = $copilot->ejecutarTool('consultar_inventario', ['insumo' => 'Arroz FC', 'solo_alertas' => false], $this->admin);
        $this->assertTrue($porNombre['ok']);
        $this->assertGreaterThanOrEqual(1, count($porNombre['datos']['insumos']));
    }

    public function test_tool_consultar_rendimiento_meseros_agrupa_por_mesero(): void
    {
        Pedido::create([
            'codigo' => 'PED-FC-MES-01',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'mesero_id' => $this->mesero->id,
            'subtotal' => 120000,
            'descuento' => 0,
            'total' => 120000,
            'metodo_pago' => 'tarjeta',
            'pagado_en' => now(),
        ]);

        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->ejecutarTool('consultar_rendimiento_meseros', ['periodo' => 'hoy'], $this->admin);

        $this->assertTrue($resultado['ok']);
        $this->assertGreaterThanOrEqual(1, (int) $resultado['datos']['total_meseros']);
        $nombres = collect($resultado['datos']['ranking'])->pluck('nombre')->all();
        $this->assertContains('Mesero FC', $nombres);
    }

    public function test_tool_consultar_platos_estrella_respeta_limite(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Rolls FC',
            'slug' => 'rolls-fc',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Roll FC Salmon',
            'slug' => 'roll-fc-salmon',
            'precio' => 35000,
            'costo' => 12000,
            'area_cocina' => 'cocina',
            'activo' => true,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'PED-FC-PLATO-01',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 70000,
            'descuento' => 0,
            'total' => 70000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);
        $pedido->forceFill(['total' => 70000])->save();

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => 'Roll FC Salmon',
            'cantidad' => 2,
            'precio_unitario' => 35000,
            'subtotal' => 70000,
        ]);

        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->ejecutarTool('consultar_platos_estrella', ['limite' => 3], $this->admin);

        $this->assertTrue($resultado['ok']);
        $this->assertLessThanOrEqual(3, count($resultado['datos']['platos']));
        $this->assertGreaterThanOrEqual(1, count($resultado['datos']['platos']));
    }

    public function test_tool_invalido_retorna_error_seguro_sin_excepcion(): void
    {
        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->ejecutarTool('herramienta_inexistente', [], $this->admin);

        $this->assertFalse($resultado['ok']);
        $this->assertArrayHasKey('error', $resultado);
    }

    public function test_tool_rechaza_categoria_maliciosa_sin_inyeccion_sql(): void
    {
        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->ejecutarTool(
            'consultar_platos_estrella',
            ['limite' => 5, 'categoria' => "'; DROP TABLE productos; --"],
            $this->admin
        );

        $this->assertTrue($resultado['ok']);
        $this->assertTrue(\Schema::hasTable('productos'));
    }

    // ── 2.2 Function Calling + fallback ───────────────────────────

    public function test_function_calling_fallback_offline_en_testing_retorna_null(): void
    {
        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->procesarConFunctionCalling('ventas de hoy?', $this->admin);

        $this->assertNull($resultado);
    }

    public function test_seleccionar_tool_local_mapea_ventas_y_egresos(): void
    {
        $copilot = app(AdminAiCopilotService::class);

        $ventas = $copilot->seleccionarToolLocal('ventas de hoy?');
        $this->assertEquals('consultar_ventas', $ventas['tool']);

        $egresos = $copilot->seleccionarToolLocal('cuánto dinero de la base ha salido');
        $this->assertEquals('consultar_movimientos_caja', $egresos['tool']);

        $inventario = $copilot->seleccionarToolLocal('qué insumos están agotados');
        $this->assertEquals('consultar_inventario', $inventario['tool']);

        $meseros = $copilot->seleccionarToolLocal('ventas por mesero');
        $this->assertEquals('consultar_rendimiento_meseros', $meseros['tool']);

        $platos = $copilot->seleccionarToolLocal('top platos más vendidos');
        $this->assertEquals('consultar_platos_estrella', $platos['tool']);
    }

    public function test_procesar_consulta_offline_no_crashea_y_usa_datos_reales(): void
    {
        Pedido::create([
            'codigo' => 'PED-FC-OFF-01',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => 50000,
            'descuento' => 0,
            'total' => 50000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->procesarConsulta('ventas de hoy?', $this->admin);

        $this->assertEquals('ventas', $resultado['tipo']);
        $this->assertArrayHasKey('datos', $resultado);
    }

    // ── Gráficos en ruta Function Calling ───────────────────────

    public function test_tool_ventas_15_dias_incluye_serie_diaria_que_suma_el_total(): void
    {
        foreach ([2, 5, 14] as $i => $diasAtras) {
            Pedido::create([
                'codigo' => 'PED-FC-SERIE-0'.$i,
                'tipo' => 'mesa',
                'estado' => 'pagado',
                'sucursal_id' => $this->sucursal->id,
                'turno_caja_id' => $this->turno->id,
                'subtotal' => 40000,
                'descuento' => 0,
                'total' => 40000,
                'metodo_pago' => $i % 2 === 0 ? 'efectivo' : 'tarjeta',
                'pagado_en' => now()->subDays($diasAtras)->setHour(13),
            ]);
        }

        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->ejecutarTool('consultar_ventas', ['periodo' => 'ultimos_15_dias', 'agrupacion' => 'dia'], $this->admin);

        $this->assertTrue($resultado['ok']);
        $this->assertArrayHasKey('serie', $resultado['datos']);
        $this->assertSame('dia', $resultado['datos']['serie_tipo']);
        // Rango inicio→fin inclusive: subDays(15)→hoy = 16 días
        $this->assertCount(16, $resultado['datos']['serie']);
        $this->assertEqualsWithDelta(
            (float) $resultado['datos']['total_ventas'],
            array_sum(array_column($resultado['datos']['serie'], 'valor')),
            0.01
        );
    }

    public function test_tool_ventas_hoy_incluye_serie_horaria_de_24(): void
    {
        $copilot = app(AdminAiCopilotService::class);
        $resultado = $copilot->ejecutarTool('consultar_ventas', ['periodo' => 'hoy', 'agrupacion' => 'dia'], $this->admin);

        $this->assertTrue($resultado['ok']);
        $this->assertSame('hora', $resultado['datos']['serie_tipo']);
        $this->assertCount(24, $resultado['datos']['serie']);
    }

    public function test_builder_genera_grafico_bar_con_serie_y_doughnut_por_metodo_pago(): void
    {
        Pedido::create([
            'codigo' => 'PED-FC-GRAF-01',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'turno_caja_id' => $this->turno->id,
            'subtotal' => 60000,
            'descuento' => 0,
            'total' => 60000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now()->subDays(2),
        ]);

        $copilot = app(AdminAiCopilotService::class);
        $tool = $copilot->ejecutarTool('consultar_ventas', ['periodo' => 'ultimos_15_dias', 'agrupacion' => 'dia'], $this->admin);

        $barras = $copilot->construirGraficoTool('consultar_ventas', $tool['datos'], $this->admin);
        $this->assertNotNull($barras);
        $this->assertEquals('bar', $barras['tipo']);
        $this->assertCount(16, $barras['etiquetas']);
        $this->assertCount(16, $barras['valores']);
        $this->assertEqualsWithDelta(60000.0, array_sum($barras['valores']), 0.01);

        $toolPagos = $copilot->ejecutarTool('consultar_ventas', ['periodo' => 'ultimos_15_dias', 'agrupacion' => 'metodo_pago'], $this->admin);
        $dona = $copilot->construirGraficoTool('consultar_ventas', $toolPagos['datos'], $this->admin);
        $this->assertNotNull($dona);
        $this->assertEquals('doughnut', $dona['tipo']);
        $this->assertContains('Efectivo', $dona['etiquetas']);
    }

    public function test_builder_genera_ranking_para_meseros_y_platos(): void
    {
        Pedido::create([
            'codigo' => 'PED-FC-RANK-01',
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'mesero_id' => $this->mesero->id,
            'subtotal' => 90000,
            'descuento' => 0,
            'total' => 90000,
            'metodo_pago' => 'efectivo',
            'pagado_en' => now(),
        ]);

        $copilot = app(AdminAiCopilotService::class);
        $meseros = $copilot->ejecutarTool('consultar_rendimiento_meseros', ['periodo' => 'ultimos_15_dias'], $this->admin);
        $grafMeseros = $copilot->construirGraficoTool('consultar_rendimiento_meseros', $meseros['datos'], $this->admin);
        $this->assertNotNull($grafMeseros);
        $this->assertEquals('ranking', $grafMeseros['tipo']);
        $this->assertContains('Mesero FC', $grafMeseros['etiquetas']);
    }

    public function test_formatear_respuesta_tool_ventas_adjunta_grafico(): void
    {
        $copilot = app(AdminAiCopilotService::class);
        $tool = $copilot->ejecutarTool('consultar_ventas', ['periodo' => 'ultimos_15_dias', 'agrupacion' => 'dia'], $this->admin);

        $ref = new \ReflectionMethod(AdminAiCopilotService::class, 'formatearRespuestaTool');
        $ref->setAccessible(true);
        $respuesta = $ref->invoke($copilot, 'consultar_ventas', $tool, 'graficos por dias ultimos 15 dias', $this->admin);

        $this->assertEquals('ventas', $respuesta['tipo']);
        $this->assertArrayHasKey('grafico', $respuesta['datos']);
        $this->assertEquals('bar', $respuesta['datos']['grafico']['tipo']);
    }

    public function test_blade_del_drawer_ofrece_descarga_png_de_graficos(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/admin/copilot-drawer.blade.php'));

        $this->assertStringContainsString('__copilotoDescargarGrafico', $blade);
        $this->assertStringContainsString('Js::from', $blade);
        $this->assertStringContainsString('.png', $blade);
    }

    public function test_tool_ejecutar_sql_analytics_ejecuta_select_con_guardas_y_grafico(): void
    {
        $copilot = app(AdminAiCopilotService::class);

        // Bloquea DML
        $dml = $copilot->ejecutarTool('ejecutar_sql_analytics', ['sql' => 'DELETE FROM mesas'], $this->admin);
        $this->assertFalse($dml['ok']);

        // Ejecuta SELECT válido y genera gráfico
        $res = $copilot->ejecutarTool('ejecutar_sql_analytics', [
            'sql' => 'SELECT tipo, count(*) as total FROM cajas WHERE sucursal_id = :sucursal_id GROUP BY tipo;',
            'titulo' => 'Tipos de Cajas',
            'tipo_grafico' => 'doughnut',
            'columna_etiqueta' => 'tipo',
            'columna_valor' => 'total',
        ], $this->admin);

        $this->assertTrue($res['ok']);
        $this->assertEquals('ejecutar_sql_analytics', $res['tool']);
        $this->assertArrayHasKey('grafico', $res['datos']);
        $this->assertEquals('doughnut', $res['datos']['grafico']['tipo']);
    }
}
