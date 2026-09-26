<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportesGraficasTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $gerente;

    protected User $mesero;

    protected ReporteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $roleGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Principal',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001112233',
            'activo' => true,
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente General',
            'email' => 'gerente@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleGerente->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero Simple',
            'email' => 'mesero@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->service = app(ReporteService::class);
    }

    public function test_datos_grafica_ventas_retorna_estructura_continua_de_fechas(): void
    {
        $hoy = now()->toDateString();
        $hace3Dias = now()->subDays(3)->toDateString();

        Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'PED-001',
            'tipo' => 'en_sitio',
            'estado' => 'pagado',
            'metodo_pago' => 'efectivo',
            'total' => 120000,
            'pagado_en' => now(),
        ]);

        $resultado = $this->service->datosGraficaVentas($hace3Dias, $hoy);

        $this->assertCount(4, $resultado['fechas']);
        $this->assertCount(4, $resultado['etiquetas']);
        $this->assertCount(4, $resultado['ventas']);
        $this->assertEquals(120000, $resultado['total_periodo']);
        $this->assertSame(30000.0, $resultado['promedio_diario']);
    }

    public function test_comparativa_periodos_visual_calcula_series_y_crecimiento(): void
    {
        $hoy = now()->toDateString();
        $ayer = now()->subDay()->toDateString();

        // Pedido período actual
        Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'PED-ACTUAL',
            'tipo' => 'en_sitio',
            'estado' => 'pagado',
            'metodo_pago' => 'tarjeta',
            'total' => 200000,
            'pagado_en' => now(),
        ]);

        // Pedido período anterior
        Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'PED-ANTERIOR',
            'tipo' => 'delivery',
            'estado' => 'pagado',
            'metodo_pago' => 'transferencia',
            'total' => 100000,
            'pagado_en' => now()->subDays(2),
        ]);

        $comparativa = $this->service->comparativaPeriodosVisual($ayer, $hoy);

        $this->assertArrayHasKey('serie_actual', $comparativa);
        $this->assertArrayHasKey('serie_anterior', $comparativa);
        $this->assertArrayHasKey('crecimiento', $comparativa);
        $this->assertEquals(200000, $comparativa['total_actual']);
        $this->assertEquals(100000, $comparativa['total_anterior']);
        $this->assertEquals(100.0, $comparativa['crecimiento']);
    }

    public function test_distribucion_canales_y_metodos_agrupa_facturacion(): void
    {
        $hoy = now()->toDateString();

        Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'PED-SALA',
            'tipo' => 'en_sitio',
            'estado' => 'pagado',
            'metodo_pago' => 'efectivo',
            'total' => 50000,
            'pagado_en' => now(),
        ]);

        Pedido::create([
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'PED-DELIVERY',
            'tipo' => 'delivery',
            'estado' => 'pagado',
            'metodo_pago' => 'tarjeta',
            'total' => 80000,
            'pagado_en' => now(),
        ]);

        $distribucion = $this->service->distribucionCanalesYMetodos($hoy, $hoy);

        $this->assertContains('En Sala', $distribucion['canales']['etiquetas']);
        $this->assertContains('Delivery', $distribucion['canales']['etiquetas']);
        $this->assertContains(50000.0, $distribucion['canales']['series']);
        $this->assertContains(80000.0, $distribucion['canales']['series']);

        $this->assertContains('Efectivo', $distribucion['metodos']['etiquetas']);
        $this->assertContains('Tarjeta', $distribucion['metodos']['etiquetas']);
    }

    public function test_pantalla_reportes_renders_con_apexcharts_y_presets(): void
    {
        $this->actingAs($this->gerente);

        $response = $this->get(route('reportes'));
        $response->assertOk();
        $response->assertSee('Reportes y Analítica');
        $response->assertSee('Gráficas Comparativas');
        $response->assertSee('apexcharts');
        $response->assertSee('chart-ventas-diarias');
        $response->assertSee('chart-comparativa-periodos');
    }

    public function test_presets_rapidos_modifican_rango_en_volt(): void
    {
        Volt::actingAs($this->gerente)
            ->test('reportes.index')
            ->call('setPeriodo', 'hoy')
            ->assertSet('desde', now()->toDateString())
            ->assertSet('hasta', now()->toDateString())
            ->call('setPeriodo', 'ayer')
            ->assertSet('desde', now()->subDay()->toDateString())
            ->assertSet('hasta', now()->subDay()->toDateString());
    }

    public function test_mesero_no_puede_acceder_a_reportes_gerenciales(): void
    {
        $this->actingAs($this->mesero);

        $response = $this->get(route('reportes'));
        $response->assertForbidden();
    }
}
