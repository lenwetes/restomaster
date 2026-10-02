<?php

namespace Tests\Feature;

use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\PedidoDevolucion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ReportesComparativosService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 9.1 — Filtros de rango, agrupación dinámica y modo comparativo.
 */
class ReportesComparativosTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Comparativas Test']);

        $roleGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        $this->gerente = User::factory()->create([
            'role_id' => $roleGerente->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'gerente.comp@test.local',
        ]);
    }

    protected function crearVenta(Carbon $fecha, float $total, string $codigo): Pedido
    {
        $pedido = Pedido::create([
            'codigo' => $codigo,
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'sucursal_id' => $this->sucursal->id,
            'subtotal' => $total,
            'total' => $total,
            'metodo_pago' => 'efectivo',
            'pagado_en' => $fecha->copy(),
        ]);
        $producto = Producto::create([
            'nombre' => 'Plato '.$codigo,
            'slug' => 'plato-'.strtolower($codigo),
            'precio' => $total,
            'costo' => 1000,
            'area_cocina' => 'cocina',
            'activo' => true,
        ]);
        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => $producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => $total,
            'subtotal' => $total,
        ]);

        return $pedido;
    }

    public function test_agrupacion_automatica_segun_rango(): void
    {
        $svc = app(ReportesComparativosService::class);

        $this->assertEquals('dia', $svc->resolverAgrupacion('2026-09-01', '2026-09-03'));
        $this->assertEquals('semana', $svc->resolverAgrupacion('2026-09-01', '2026-09-20'));
        $this->assertEquals('quincena', $svc->resolverAgrupacion('2026-09-01', '2026-10-30'));
        $this->assertEquals('mes', $svc->resolverAgrupacion('2026-01-01', '2026-06-01'));
    }

    public function test_serie_por_periodo_suma_ventas_reales(): void
    {
        $this->crearVenta(Carbon::create(2026, 9, 10, 13), 100000, 'CMP-001');
        $this->crearVenta(Carbon::create(2026, 9, 11, 13), 50000, 'CMP-002');

        $serie = app(ReportesComparativosService::class)
            ->seriePorPeriodo('2026-09-10', '2026-09-11', $this->sucursal->id);

        $this->assertEquals('dia', $serie['agrupacion']);
        $this->assertEquals([100000.0, 50000.0], $serie['ventas']);
        $this->assertEquals(150000.0, $serie['total']);
        $this->assertCount(2, $serie['etiquetas']);
    }

    public function test_comparar_calcula_deltas_con_periodo_b_personalizado(): void
    {
        $this->crearVenta(Carbon::create(2026, 9, 10, 13), 100000, 'CMP-A1');
        $this->crearVenta(Carbon::create(2026, 8, 10, 13), 50000, 'CMP-B1');
        $this->crearVenta(Carbon::create(2026, 8, 11, 13), 50000, 'CMP-B2');

        $comp = app(ReportesComparativosService::class)->comparar(
            '2026-09-10',
            '2026-09-10',
            '2026-08-10',
            '2026-08-11',
            $this->sucursal->id
        );

        $this->assertEquals(100000.0, $comp['a']['ventas']);
        $this->assertEquals(1, $comp['a']['comandas']);
        $this->assertEquals(100000.0, $comp['b']['ventas']);
        $this->assertEquals(2, $comp['b']['comandas']);
        $this->assertEquals(0.0, $comp['delta_ventas_pct']);
        $this->assertEquals(-50.0, $comp['delta_comandas_pct']);
        $this->assertEquals(100000.0, $comp['a']['ticket_promedio']);
        $this->assertEquals(50000.0, $comp['delta_ticket']);
    }

    public function test_periodo_anterior_automatico_misma_duracion(): void
    {
        [$desdeB, $hastaB] = app(ReportesComparativosService::class)
            ->periodoAnteriorAutomatico('2026-09-10', '2026-09-12');

        $this->assertEquals('2026-09-07', $desdeB);
        $this->assertEquals('2026-09-09', $hastaB);
    }

    public function test_heatmap_tiene_matriz_7x24_con_venta_ubicada(): void
    {
        // Jueves 10/09/2026 a las 13:00.
        $this->crearVenta(Carbon::create(2026, 9, 10, 13, 30), 100000, 'CMP-H1');

        $heat = app(ReportesComparativosService::class)
            ->heatmapVentas('2026-09-07', '2026-09-13', $this->sucursal->id);

        $this->assertCount(7, $heat['matriz']);
        $this->assertCount(24, $heat['matriz'][0]);
        // ISO: jueves = índice 3 ( Lun=0 ).
        $this->assertEquals(100000.0, $heat['matriz'][3][13]);
        $this->assertEquals(100000.0, $heat['maximo']);
    }

    public function test_top_periodo_respeta_limite(): void
    {
        $this->crearVenta(Carbon::create(2026, 9, 10, 13), 100000, 'CMP-T1');
        $this->crearVenta(Carbon::create(2026, 9, 10, 14), 20000, 'CMP-T2');

        $top = app(ReportesComparativosService::class)
            ->topPeriodo('2026-09-10', '2026-09-10', 1, $this->sucursal->id);

        $this->assertCount(1, $top);
        $this->assertEquals('Plato CMP-T1', $top[0]['producto']);
    }

    public function test_exportar_csv_contiene_encabezados_y_valores(): void
    {
        $this->crearVenta(Carbon::create(2026, 9, 10, 13), 100000, 'CMP-C1');

        $comp = app(ReportesComparativosService::class)->comparar(
            '2026-09-10',
            '2026-09-10',
            '2026-09-09',
            '2026-09-09',
            $this->sucursal->id
        );
        $csv = app(ReportesComparativosService::class)->exportarCsv($comp);

        $this->assertStringContainsString('Período', $csv);
        $this->assertStringContainsString('100000', $csv);
    }

    public function test_devoluciones_entran_en_deltas(): void
    {
        $pedido = $this->crearVenta(Carbon::create(2026, 9, 10, 13), 100000, 'CMP-D1');
        PedidoDevolucion::create([
            'pedido_id' => $pedido->id,
            'cantidad' => 1,
            'monto_devuelto' => 20000,
            'motivo' => 'Error de cobro',
            'autorizado_por' => 'Gerente',
            'user_id' => $this->gerente->id,
        ]);

        $comp = app(ReportesComparativosService::class)->comparar(
            '2026-09-10',
            '2026-09-10',
            '2026-09-09',
            '2026-09-09',
            $this->sucursal->id
        );

        $this->assertEquals(20000.0, $comp['a']['devoluciones']);
        $this->assertArrayHasKey('delta_devoluciones_pct', $comp);
    }
}
