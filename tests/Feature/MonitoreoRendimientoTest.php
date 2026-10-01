<?php

namespace Tests\Feature;

use App\Http\Middleware\MonitoreoRendimientoSucursalesMiddleware;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MonitoreoRendimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_registra_tiempos_en_ventana_rodante_de_sucursal(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sede Poblado']);
        $user = User::factory()->create(['sucursal_id' => $sucursal->id, 'activo' => true]);

        $this->actingAs($user);

        $response = $this->get('/turnos'); // ruta web autenticada

        $muestras = Cache::get(MonitoreoRendimientoSucursalesMiddleware::CACHE_PREFIX.$sucursal->id, []);
        $this->assertNotEmpty($muestras);
        $this->assertGreaterThan(0.0, $muestras[0]);
    }

    public function test_calculo_de_percentiles_calcula_p95_y_valida_sla(): void
    {
        $sucursalId = 99;
        // Simulamos 100 muestras entre 50ms y 500ms
        $muestras = range(50, 149);
        Cache::put(MonitoreoRendimientoSucursalesMiddleware::CACHE_PREFIX.$sucursalId, $muestras);

        $metricas = MonitoreoRendimientoSucursalesMiddleware::calcularPercentiles($sucursalId);

        $this->assertSame(100, $metricas['total_muestras']);
        $this->assertGreaterThanOrEqual(95.0, $metricas['p50']);
        $this->assertGreaterThanOrEqual(135.0, $metricas['p90']);
        $this->assertGreaterThanOrEqual(140.0, $metricas['p95']);
        $this->assertTrue($metricas['sla_ok']);
    }

    public function test_comando_metricas_p95_ejecuta_correctamente(): void
    {
        Sucursal::create(['nombre' => 'Sucursal Laureles']);

        $this->artisan('restomaster:metricas-p95')
            ->assertSuccessful()
            ->expectsOutputToContain('Telemetría APM de Rendimiento p95 por Sucursal');
    }
}
