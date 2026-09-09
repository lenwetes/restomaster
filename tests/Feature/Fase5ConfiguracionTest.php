<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Services\ConfiguracionService;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase5ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    private ConfiguracionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        $this->service = app(ConfiguracionService::class);
    }

    public function test_seeder_crea_configuraciones_base(): void
    {
        $this->assertSame('SushiXpress S.A.S.', $this->service->obtener('general', 'razon_social'));
        $this->assertSame('habilitacion', $this->service->obtener('dian', 'ambiente', ''));
        $this->assertFalse($this->service->obtener('reservas', 'webhook_activo', true));
        $this->assertNotNull($this->service->obtener('reservas', 'webhook_token'));
    }

    public function test_seeder_es_idempotente(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->assertDatabaseCount('configuraciones', Configuracion::count());
    }

    public function test_obtener_devuelve_default_si_no_existe(): void
    {
        $this->assertSame('valor-default', $this->service->obtener('nada', 'nada', 'valor-default'));
    }

    public function test_guardar_y_obtener_roundtrip(): void
    {
        $this->service->guardar('dian', 'tipo_documento', '02');
        $this->assertSame('02', $this->service->obtener('dian', 'tipo_documento'));
    }

    public function test_guardar_acepta_arrays(): void
    {
        $this->service->guardar('test', 'datos', ['a' => 1, 'b' => 'x']);
        $this->assertSame(['a' => 1, 'b' => 'x'], $this->service->obtener('test', 'datos'));
    }

    public function test_regenerar_webhook_token_cambia_valor(): void
    {
        $anterior = $this->service->obtener('reservas', 'webhook_token');
        $nuevo = $this->service->regenerarWebhookToken();

        $this->assertNotSame($anterior, $nuevo);
        $this->assertSame($nuevo, $this->service->obtener('reservas', 'webhook_token'));
        $this->assertGreaterThanOrEqual(40, strlen($nuevo));
    }

    public function test_pantalla_configuracion_solo_admin(): void
    {
        $mesero = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'mesero')->value('id')]);
        $this->actingAs($mesero)->get(route('configuracion'))->assertForbidden();

        $admin = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($admin)->get(route('configuracion'))->assertOk();
        $this->actingAs($admin)->get(route('configuracion'))->assertSeeVolt('configuracion.index');
    }

    public function test_guardar_config_dian_desde_ui(): void
    {
        $admin = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'admin')->value('id')]);

        \Livewire\Volt\Volt::actingAs($admin)
            ->test('configuracion.index')
            ->set('dianForm.razon_social', 'SushiXpress S.A.S.')
            ->set('dianForm.nit', '9011234567')
            ->set('dianForm.regimen', 'Simplificado')
            ->set('dianForm.envio_activo', true)
            ->call('guardarDian')
            ->assertHasNoErrors();

        $svc = app(\App\Services\ConfiguracionService::class);
        $this->assertSame('9011234567', $svc->obtener('general', 'nit'));
        $this->assertTrue($svc->obtener('dian', 'envio_activo'));
    }

    public function test_regenerar_token_desde_ui(): void
    {
        $admin = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'admin')->value('id')]);
        $antes = app(\App\Services\ConfiguracionService::class)->obtener('reservas', 'webhook_token');

        \Livewire\Volt\Volt::actingAs($admin)
            ->test('configuracion.index')
            ->call('regenerarToken');

        $despues = app(\App\Services\ConfiguracionService::class)->obtener('reservas', 'webhook_token');
        $this->assertNotSame($antes, $despues);
    }
}