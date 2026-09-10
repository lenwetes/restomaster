<?php

namespace Tests\Feature;

use App\Models\AsientoContable;
use App\Models\Role;
use App\Models\User;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase2ReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $mesero;

    private ReporteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->gerente = User::factory()->create(['role_id' => Role::where('slug', 'gerente')->value('id')]);
        $this->mesero = User::factory()->create(['role_id' => Role::where('slug', 'mesero')->value('id')]);

        $this->service = app(ReporteService::class);
    }

    public function test_pantalla_reportes_solo_disponible_para_gerente(): void
    {
        $this->actingAs($this->mesero)->get(route('reportes'))->assertForbidden();

        $this->actingAs($this->gerente)->get(route('reportes'))->assertOk();
        $this->actingAs($this->gerente)->get(route('reportes'))->assertSeeVolt('reportes.index');
    }

    public function test_estado_resultados_suma_ingresos_gastos_y_resultado(): void
    {
        AsientoContable::create([
            'fecha' => '2026-06-15',
            'tipo' => 'ingreso',
            'cuenta' => 'ventas_restaurante',
            'concepto' => 'Venta POS',
            'monto' => 500000,
        ]);
        AsientoContable::create([
            'fecha' => '2026-06-15',
            'tipo' => 'ingreso',
            'cuenta' => 'ingresos_extraordinarios',
            'concepto' => 'Sobrante',
            'monto' => 10000,
        ]);
        AsientoContable::create([
            'fecha' => '2026-06-15',
            'tipo' => 'gasto',
            'cuenta' => 'gastos_operativos',
            'concepto' => 'Proveedores',
            'monto' => 180000,
        ]);
        AsientoContable::create([
            'fecha' => '2026-06-15',
            'tipo' => 'gasto',
            'cuenta' => 'faltante_caja',
            'concepto' => 'Descuadre',
            'monto' => 5000,
        ]);

        $resultado = $this->service->estadoResultados('2026-06-01', '2026-06-30');

        $this->assertSame(500000.0, $resultado['ingresos']['ventas_netas']);
        $this->assertSame(10000.0, $resultado['ingresos']['otros_ingresos']);
        $this->assertSame(510000.0, $resultado['ingresos']['total']);
        $this->assertSame(180000.0, $resultado['gastos']['gastos_operativos']);
        $this->assertSame(5000.0, $resultado['gastos']['otros_gastos']);
        $this->assertSame(185000.0, $resultado['gastos']['total']);
        $this->assertSame(325000.0, $resultado['resultado_neto']);
    }

    public function test_estado_resultados_respeta_rango_de_fechas(): void
    {
        AsientoContable::create(['fecha' => '2026-01-10', 'tipo' => 'ingreso', 'cuenta' => 'ventas_restaurante', 'concepto' => 'Venta', 'monto' => 100000]);
        AsientoContable::create(['fecha' => '2026-02-10', 'tipo' => 'ingreso', 'cuenta' => 'ventas_restaurante', 'concepto' => 'Venta', 'monto' => 999999]);

        $resultado = $this->service->estadoResultados('2026-01-01', '2026-01-31');

        $this->assertSame(100000.0, $resultado['ingresos']['ventas_netas']);
        $this->assertSame(100000.0, $resultado['resultado_neto']);
    }

    public function test_estado_resultados_agrupa_detalle_por_cuenta(): void
    {
        AsientoContable::create(['fecha' => '2026-03-01', 'tipo' => 'ingreso', 'cuenta' => 'ventas_restaurante', 'concepto' => 'Venta 1', 'monto' => 100000]);
        AsientoContable::create(['fecha' => '2026-03-02', 'tipo' => 'ingreso', 'cuenta' => 'ventas_restaurante', 'concepto' => 'Venta 2', 'monto' => 50000]);
        AsientoContable::create(['fecha' => '2026-03-03', 'tipo' => 'gasto', 'cuenta' => 'gastos_operativos', 'concepto' => 'Servicios', 'monto' => 30000]);

        $resultado = $this->service->estadoResultados('2026-03-01', '2026-03-31');

        $this->assertCount(1, $resultado['detalle']['ingresos']);
        $this->assertSame('ventas_restaurante', $resultado['detalle']['ingresos'][0]['cuenta']);
        $this->assertSame(150000.0, $resultado['detalle']['ingresos'][0]['total']);
        $this->assertCount(1, $resultado['detalle']['gastos']);
    }
}
