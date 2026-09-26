<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmIaPlantillaPrivilegio;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\CrmAiAgentService;
use App\Services\Ai\CrmChatOrchestratorService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CrmComprehensiveAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $gerente;

    protected User $cajero;

    protected CrmIaPlantillaPrivilegio $plantilla;

    protected CrmConfiguracion $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SucursalSeeder::class);

        $roleAdmin = Role::where('slug', 'admin')->first();
        $roleGerente = Role::where('slug', 'gerente')->first();
        $roleCajero = Role::where('slug', 'cajero')->first();

        $this->admin = User::create([
            'name' => 'Admin Auditor',
            'email' => 'admin_audit@restomaster.test',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'is_active' => true,
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Auditor',
            'email' => 'gerente_audit@restomaster.test',
            'password' => bcrypt('password'),
            'role_id' => $roleGerente->id,
            'is_active' => true,
        ]);

        $this->cajero = User::create([
            'name' => 'Cajero Auditor',
            'email' => 'cajero_audit@restomaster.test',
            'password' => bcrypt('password'),
            'role_id' => $roleCajero->id,
            'is_active' => true,
        ]);

        $this->plantilla = CrmIaPlantillaPrivilegio::create([
            'nombre' => 'Hostess Completa Auditoría',
            'slug' => 'hostess-auditoria',
            'descripcion' => 'Plantilla para pruebas de auditoría',
            'es_sistema' => false,
            'permitir_menu' => true,
            'permitir_precios' => true,
            'permitir_alergenos' => true,
            'permitir_verificar_mesas' => true,
            'permitir_crear_reservas' => true,
            'max_personas_reserva' => 8,
            'permitir_promociones' => true,
            'permitir_puntos_vip' => true,
            'tono_conducta' => 'amable_calido',
            'prompt_personalidad' => 'Bienvenida hospitalaria Michelin.',
        ]);

        $this->config = CrmConfiguracion::create([
            'whatsapp_proveedor' => 'simulado',
            'whatsapp_access_token' => 'EAAX_meta_super_secret_token_12345',
            'ia_activo' => true,
            'ia_proveedor' => 'gemini',
            'ia_modelo' => 'gemini-2.5-flash',
            'ia_api_key' => 'AIzaSy_fake_audit_key_67890',
            'ia_plantilla_privilegio_id' => $this->plantilla->id,
            'ia_limite_mensajes_por_cliente_dia' => 20,
        ]);
    }

    public function test_whatsapp_access_token_se_almacena_cifrado_en_base_de_datos(): void
    {
        $tokenOriginal = 'EAAX_token_de_meta_altamente_confidencial_999';
        $this->config->update(['whatsapp_access_token' => $tokenOriginal]);

        // Al acceder por Eloquent se desencripta
        $fresh = CrmConfiguracion::find($this->config->id);
        $this->assertEquals($tokenOriginal, $fresh->whatsapp_access_token);

        // En la base de datos cruda NO está en texto plano
        $rawDb = DB::table('crm_configuraciones')->where('id', $this->config->id)->value('whatsapp_access_token');
        $this->assertNotEquals($tokenOriginal, $rawDb);
        $this->assertStringStartsWith('eyJ', $rawDb);
    }

    public function test_cajero_no_puede_modificar_configuraciones_sensibles_crm_403(): void
    {
        // El cajero intenta apagar la IA con el Kill-Switch -> debe arrojar 403 Forbidden
        Volt::actingAs($this->cajero)
            ->test('crm.index')
            ->call('toggleIaManual')
            ->assertForbidden();

        // El cajero intenta guardar configuraciones generales o credenciales SMTP -> 403
        Volt::actingAs($this->cajero)
            ->test('crm.index')
            ->call('guardarConfiguracion')
            ->assertForbidden();

        // El cajero intenta modificar la plantilla de privilegios de IA -> 403
        Volt::actingAs($this->cajero)
            ->test('crm.index')
            ->call('guardarPlantillaIa')
            ->assertForbidden();
    }

    public function test_admin_y_gerente_pueden_modificar_configuraciones_sensibles(): void
    {
        // Admin puede alternar el Kill-Switch
        Volt::actingAs($this->admin)
            ->test('crm.index')
            ->call('toggleIaManual')
            ->assertSet('ia_activo', false);

        // Gerente puede alternar el Kill-Switch
        Volt::actingAs($this->gerente)
            ->test('crm.index')
            ->call('toggleIaManual')
            ->assertSet('ia_activo', true);
    }

    public function test_ai_agent_reconoce_alergias_del_cliente_y_aplica_protocolo(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Valentina Restrepo',
            'telefono' => '+573112223344',
            'alergias' => 'Mariscos y Crustáceos',
            'tier' => 'vip',
            'puntos_fidelidad' => 120,
        ]);

        /** @var CrmAiAgentService $service */
        $service = app(CrmAiAgentService::class);

        $resultado = $service->procesarMensaje(
            mensaje: '¿Tienen protocolos para alergia a los mariscos en su cocina?',
            clienteId: $cliente->id
        );

        $this->assertEquals('autorizado', $resultado['estado']);
        $this->assertContains('consultar_alergenos', $resultado['tools_invocadas']);
        $this->assertStringContainsString('Mariscos y Crustáceos', $resultado['respuesta']);
        $this->assertStringContainsString('seguridad alimentaria', $resultado['respuesta']);
    }

    public function test_ai_agent_informa_puntos_de_fidelidad_cuando_esta_habilitado(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Andrés Londoño',
            'telefono' => '+573009998877',
            'puntos_fidelidad' => 350,
            'tier' => 'vip',
        ]);

        /** @var CrmAiAgentService $service */
        $service = app(CrmAiAgentService::class);

        $resultado = $service->procesarMensaje(
            mensaje: 'Hola, ¿cuántos puntos de fidelidad tengo acumulados?',
            clienteId: $cliente->id
        );

        $this->assertEquals('autorizado', $resultado['estado']);
        $this->assertContains('consultar_puntos_cliente', $resultado['tools_invocadas']);
        $this->assertStringContainsString('350 puntos', $resultado['respuesta']);
        $this->assertStringContainsString('Andrés Londoño', $resultado['respuesta']);
    }

    public function test_ai_agent_informa_promociones_cuando_esta_habilitado(): void
    {
        /** @var CrmAiAgentService $service */
        $service = app(CrmAiAgentService::class);

        $resultado = $service->procesarMensaje('¿Qué promociones o especiales tienen hoy?');

        $this->assertEquals('autorizado', $resultado['estado']);
        $this->assertContains('consultar_promociones', $resultado['tools_invocadas']);
        $this->assertStringContainsString('experiencias gastronómicas especiales', $resultado['respuesta']);
    }

    public function test_orquestador_pasa_historial_multiturn_y_cliente_id_correctamente(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $cliente = Cliente::create([
            'nombre' => 'Carolina Duque',
            'telefono' => '+573201112233',
            'alergias' => 'Gluten',
        ]);

        $conv = $orchestrator->obtenerOCrearConversacion(
            canal: 'whatsapp',
            identificadorRemoto: '+573201112233',
            nombre: 'Carolina Duque',
            clienteId: $cliente->id
        );

        // Turno 1
        $orchestrator->procesarMensajeCliente($conv, 'Hola, tengo una pregunta sobre alérgenos');
        $this->assertDatabaseHas('crm_mensajes', [
            'crm_conversacion_id' => $conv->id,
            'emisor' => 'bot',
        ]);

        // Turno 2 (con historial existente en BD)
        $orchestrator->procesarMensajeCliente($conv, '¿Cuál es el menú recomendado hoy?');

        $mensajes = $conv->mensajes()->orderBy('id')->get();
        $this->assertCount(4, $mensajes); // 2 de cliente + 2 de bot
        $this->assertEquals('cliente', $mensajes[0]->emisor);
        $this->assertEquals('bot', $mensajes[1]->emisor);
        $this->assertEquals('cliente', $mensajes[2]->emisor);
        $this->assertEquals('bot', $mensajes[3]->emisor);
        $this->assertStringContainsString('Carolina Duque', $mensajes[3]->contenido);
    }
}
