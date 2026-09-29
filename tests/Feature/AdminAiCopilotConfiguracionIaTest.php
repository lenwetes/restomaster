<?php

namespace Tests\Feature;

use App\Models\CrmConfiguracion;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Ai\AdminAiCopilotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Fase 2.3 — Pestaña "IA & Copiloto" en /configuracion.
 */
class AdminAiCopilotConfiguracionIaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create([
            'nombre' => 'Sede Test IA',
            'direccion' => 'Calle 1 # 1-1',
            'telefono' => '3001234500',
            'activo' => true,
        ]);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Admin IA',
            'email' => 'admin.ia@test.local',
        ]);

        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Mesero IA',
            'email' => 'mesero.ia@test.local',
        ]);
    }

    public function test_configuracion_ia_guarda_api_key_cifrada_y_no_expone_completa(): void
    {
        Volt::actingAs($this->admin)
            ->test('configuracion.index')
            ->set('tabActiva', 'ia')
            ->set('iaForm.activo', true)
            ->set('iaForm.proveedor', 'gemini')
            ->set('iaForm.modelo', 'gemini-2.5-flash')
            ->set('iaForm.api_key', 'AIza-test-clave-secreta-12345')
            ->call('guardarIa')
            ->assertHasNoErrors()
            ->assertSet('iaForm.api_key', '')
            ->assertSet('iaTieneClave', true);

        $config = CrmConfiguracion::whereNull('sucursal_id')->first();
        $this->assertTrue((bool) $config->ia_activo);
        $this->assertEquals('gemini-2.5-flash', $config->ia_modelo);

        $raw = DB::table('crm_configuraciones')->where('id', $config->id)->value('ia_api_key');
        $this->assertNotEquals('AIza-test-clave-secreta-12345', $raw);
        $this->assertEquals('AIza-test-clave-secreta-12345', $config->obtenerApiKeyIa());
    }

    public function test_configuracion_ia_probar_conexion_en_testing_retorna_latencia_y_modelo(): void
    {
        $config = CrmConfiguracion::activa(null);
        $config->ia_activo = true;
        $config->ia_proveedor = 'gemini';
        $config->ia_modelo = 'gemini-2.5-flash';
        $config->ia_api_key = 'AIza-test-clave-valida-67890';
        $config->save();

        Volt::actingAs($this->admin)
            ->test('configuracion.index')
            ->set('tabActiva', 'ia')
            ->call('probarConexionIa')
            ->assertSet('iaEstado', 'conectado')
            ->assertNotSet('iaLatencia', null);

        /** @var AdminAiCopilotService $svc */
        $svc = app(AdminAiCopilotService::class);
        $resultado = $svc->probarConexionIa(null);
        $this->assertTrue($resultado['ok']);
        $this->assertNotNull($resultado['latencia_ms']);
        $this->assertEquals('gemini-2.5-flash', $resultado['modelo']);
    }

    public function test_configuracion_ia_sin_clave_muestra_no_configurado(): void
    {
        Volt::actingAs($this->admin)
            ->test('configuracion.index')
            ->set('tabActiva', 'ia')
            ->assertSet('iaEstado', 'no_configurado')
            ->assertSet('iaTieneClave', false);
    }

    public function test_configuracion_ia_requiere_permiso_admin(): void
    {
        Volt::actingAs($this->mesero)
            ->test('configuracion.index')
            ->set('tabActiva', 'ia')
            ->set('iaForm.api_key', 'AIza-intento-mesero-12345')
            ->call('guardarIa')
            ->assertForbidden();
    }
}
