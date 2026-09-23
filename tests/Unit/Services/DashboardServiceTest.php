<?php

namespace Tests\Unit\Services;

use App\Models\Pedido;
use App\Models\Sucursal;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DashboardService::class);

        $this->sucursal = Sucursal::create([
            'nombre' => 'SushiXpress Principal',
            'codigo' => 'PRV-01',
            'activa' => true,
        ]);
    }

    /**
     * Test de resolución de rangos de fechas (hoy, ayer, semana, mes).
     */
    public function test_resolver_rango_devuelve_fechas_correctas_segun_periodo(): void
    {
        $rangoHoy = $this->service->resolverRango('hoy');
        $this->assertEquals('Hoy', $rangoHoy['etiqueta']);
        $this->assertTrue($rangoHoy['inicio']->isStartOfDay());
        $this->assertTrue($rangoHoy['fin']->isEndOfDay());

        $rangoAyer = $this->service->resolverRango('ayer');
        $this->assertEquals('Ayer', $rangoAyer['etiqueta']);

        $rangoMes = $this->service->resolverRango('mes');
        $this->assertEquals('Este Mes', $rangoMes['etiqueta']);
    }

    /**
     * Test de cálculo de KPIs generales (ventas, ticket promedio y comparativa).
     */
    public function test_kpis_generales_calcula_metricas_financieras(): void
    {
        Pedido::create([
            'codigo' => 'ORD-DASH-01',
            'sucursal_id' => $this->sucursal->id,
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'subtotal' => 50000,
            'total' => 50000,
            'created_at' => now(),
            'pagado_en' => now(),
        ]);

        Pedido::create([
            'codigo' => 'ORD-DASH-02',
            'sucursal_id' => $this->sucursal->id,
            'tipo' => 'mesa',
            'estado' => 'pagado',
            'subtotal' => 150000,
            'total' => 150000,
            'created_at' => now(),
            'pagado_en' => now(),
        ]);

        $kpis = $this->service->kpisGenerales('hoy', $this->sucursal->id);

        $this->assertEquals(200000.00, (float) $kpis['ventas']);
        $this->assertEquals(2, $kpis['transacciones']);
        $this->assertEquals(100000.00, (float) $kpis['ticket_promedio']);
    }
}
