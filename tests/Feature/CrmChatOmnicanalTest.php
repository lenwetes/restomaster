<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmConversacion;
use App\Models\CrmIaPlantillaPrivilegio;
use App\Models\CrmMensaje;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\CrmChatOrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CrmChatOmnicanalTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected CrmIaPlantillaPrivilegio $plantilla;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create([
            'nombre' => 'Administrador',
            'slug' => 'admin',
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'email' => 'admin_chat@restomaster.test',
        ]);

        $this->plantilla = CrmIaPlantillaPrivilegio::create([
            'nombre' => 'Hostess Test',
            'slug' => 'hostess-test',
            'descripcion' => 'Plantilla para pruebas',
            'es_sistema' => true,
            'permitir_menu' => true,
            'permitir_precios' => true,
            'permitir_alergenos' => true,
            'permitir_verificar_mesas' => true,
            'permitir_crear_reservas' => true,
            'max_personas_reserva' => 6,
            'permitir_promociones' => true,
            'tono_conducta' => 'amable_calido',
            'prompt_personalidad' => 'Bienvenida cálida y elegante.',
        ]);

        CrmConfiguracion::create([
            'whatsapp_proveedor' => 'simulado',
            'ia_activo' => true,
            'ia_proveedor' => 'gemini',
            'ia_modelo' => 'gemini-2.5-flash',
            'ia_api_key' => 'fake_api_key_for_testing',
            'ia_plantilla_privilegio_id' => $this->plantilla->id,
            'ia_limite_mensajes_por_cliente_dia' => 15,
        ]);
    }

    public function test_crea_o_recupera_conversacion_web_y_whatsapp(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $uuid = (string) Str::uuid();
        $convWeb = $orchestrator->obtenerOCrearConversacion('web', $uuid, 'Visitante Web');

        $this->assertDatabaseHas('crm_conversaciones', [
            'id' => $convWeb->id,
            'canal' => 'web',
            'session_token' => $uuid,
            'modo_atencion' => 'ia',
        ]);

        $convWa = $orchestrator->obtenerOCrearConversacion('whatsapp', '573001234567', 'Carlos Restrepo');

        $this->assertDatabaseHas('crm_conversaciones', [
            'id' => $convWa->id,
            'canal' => 'whatsapp',
            'identificador_remoto' => '573001234567',
            'nombre_contacto' => 'Carlos Restrepo',
        ]);
    }

    public function test_vincula_automaticamente_con_cliente_existente_por_telefono(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Mariana Gómez',
            'telefono' => '+573109876543',
            'email' => 'mariana_chat_test@example.com',
        ]);

        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $conv = $orchestrator->obtenerOCrearConversacion('whatsapp', '+573109876543');

        $this->assertEquals($cliente->id, $conv->cliente_id);
    }

    public function test_procesa_mensaje_cliente_y_responde_bot_automaticamente(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '¡Hola! Te damos la bienvenida a RestoMaster. Sí, abrimos hoy a las 12pm.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $conv = $orchestrator->obtenerOCrearConversacion('web', (string) Str::uuid());

        $orchestrator->procesarMensajeCliente($conv, '¿A qué hora abren hoy?');

        $this->assertDatabaseHas('crm_mensajes', [
            'crm_conversacion_id' => $conv->id,
            'emisor' => 'cliente',
            'contenido' => '¿A qué hora abren hoy?',
        ]);

        $this->assertDatabaseHas('crm_mensajes', [
            'crm_conversacion_id' => $conv->id,
            'emisor' => 'bot',
        ]);
    }

    public function test_handoff_automatico_cuando_el_cliente_pide_un_humano(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $conv = $orchestrator->obtenerOCrearConversacion('web', (string) Str::uuid());

        $orchestrator->procesarMensajeCliente($conv, 'Por favor quiero hablar con una persona o un asesor');

        $conv->refresh();
        $this->assertEquals('humano', $conv->modo_atencion);
        $this->assertEquals('esperando_humano', $conv->estado);

        $ultimoMensaje = $conv->mensajes()->latest('id')->first();
        $this->assertEquals('bot', $ultimoMensaje->emisor);
        $this->assertStringContainsString('anfitriones', $ultimoMensaje->contenido);
    }

    public function test_staff_responde_desde_crm_y_conmuta_a_modo_humano(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $conv = $orchestrator->obtenerOCrearConversacion('whatsapp', '573005556677');

        $orchestrator->enviarMensajeStaff($conv, 'Hola, soy Mariana la anfitriona. Tu mesa ya está lista.', $this->adminUser->id);

        $conv->refresh();
        $this->assertEquals('humano', $conv->modo_atencion);
        $this->assertEquals('activa', $conv->estado);

        $this->assertDatabaseHas('crm_mensajes', [
            'crm_conversacion_id' => $conv->id,
            'emisor' => 'staff',
            'user_id' => $this->adminUser->id,
            'contenido' => 'Hola, soy Mariana la anfitriona. Tu mesa ya está lista.',
        ]);
    }

    public function test_alternar_modo_atencion_entre_ia_y_humano(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $conv = $orchestrator->obtenerOCrearConversacion('web', (string) Str::uuid());
        $this->assertEquals('ia', $conv->modo_atencion);

        $orchestrator->alternarModoAtencion($conv, 'humano', $this->adminUser->id);
        $conv->refresh();
        $this->assertEquals('humano', $conv->modo_atencion);
        $this->assertEquals($this->adminUser->id, $conv->user_id_asignado);

        $orchestrator->alternarModoAtencion($conv, 'ia');
        $conv->refresh();
        $this->assertEquals('ia', $conv->modo_atencion);
    }

    public function test_webhook_entrante_de_whatsapp_recibe_mensaje_y_crea_conversacion(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '¡Hola! Bienvenido a RestoMaster.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Alejandro Restrepo'],
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.HBgLM...',
                                        'from' => '573009988776',
                                        'text' => ['body' => 'Hola deseo saber el menú'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('crm_conversaciones', [
            'canal' => 'whatsapp',
            'identificador_remoto' => '573009988776',
            'nombre_contacto' => 'Alejandro Restrepo',
        ]);

        $this->assertDatabaseHas('crm_mensajes', [
            'emisor' => 'cliente',
            'contenido' => 'Hola deseo saber el menú',
        ]);
    }

    public function test_renderizado_de_pestaña_chats_en_livewire_crm(): void
    {
        $conv = CrmConversacion::create([
            'canal' => 'web',
            'identificador_remoto' => 'test-session-123',
            'session_token' => 'test-session-123',
            'nombre_contacto' => 'Santiago Vélez',
            'modo_atencion' => 'ia',
            'estado' => 'activa',
            'ultimo_mensaje_texto' => '¿Tienen mesa para hoy?',
            'ultimo_mensaje_at' => now(),
        ]);

        CrmMensaje::create([
            'crm_conversacion_id' => $conv->id,
            'emisor' => 'cliente',
            'contenido' => '¿Tienen mesa para hoy?',
            'canal_origen' => 'web',
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test('crm.index')
            ->set('tab', 'chats')
            ->call('seleccionarChat', $conv->id)
            ->assertSee('Bandeja Omnicanal')
            ->assertSee('Santiago Vélez')
            ->assertSee('¿Tienen mesa para hoy?')
            ->assertSee('Tomar Control (Responder Yo)')
            ->call('conmutarModoChat', 'humano')
            ->assertSee('Devolver a la IA');
    }

    public function test_cerrar_reabrir_y_eliminar_conversacion_desde_livewire_crm(): void
    {
        $conv = CrmConversacion::create([
            'canal' => 'web',
            'identificador_remoto' => 'test-session-close',
            'session_token' => 'test-session-close',
            'nombre_contacto' => 'Cliente Para Cerrar',
            'modo_atencion' => 'humano',
            'estado' => 'activa',
            'ultimo_mensaje_texto' => 'Consulta resuelta, gracias',
            'ultimo_mensaje_at' => now(),
        ]);

        $mensaje = CrmMensaje::create([
            'crm_conversacion_id' => $conv->id,
            'emisor' => 'cliente',
            'contenido' => 'Consulta resuelta, gracias',
            'canal_origen' => 'web',
        ]);

        $this->actingAs($this->adminUser);

        // 1. Cerrar conversación
        Livewire::test('crm.index')
            ->set('tab', 'chats')
            ->call('seleccionarChat', $conv->id)
            ->call('cerrarConversacion', $conv->id)
            ->assertSet('chatConversacionSeleccionadaId', $conv->id);

        $conv->refresh();
        $this->assertEquals('cerrada', $conv->estado);
        $this->assertTrue($conv->esCerrada());

        // 2. Reabrir conversación
        Livewire::test('crm.index')
            ->set('tab', 'chats')
            ->call('seleccionarChat', $conv->id)
            ->call('reabrirConversacion', $conv->id);

        $conv->refresh();
        $this->assertEquals('activa', $conv->estado);
        $this->assertFalse($conv->esCerrada());

        // 3. Responder staff auto-reabre si estaba cerrada
        $conv->cerrar();
        $this->assertTrue($conv->fresh()->esCerrada());

        Livewire::test('crm.index')
            ->set('tab', 'chats')
            ->call('seleccionarChat', $conv->id)
            ->set('chatRespuestaInput', '¡Un placer haberle atendido!')
            ->call('enviarRespuestaStaff');

        $conv->refresh();
        $this->assertEquals('activa', $conv->estado);

        // 4. Eliminar conversación permanentemente con sus mensajes
        Livewire::test('crm.index')
            ->set('tab', 'chats')
            ->call('seleccionarChat', $conv->id)
            ->call('eliminarConversacion', $conv->id)
            ->assertSet('chatConversacionSeleccionadaId', null);

        $this->assertDatabaseMissing('crm_conversaciones', ['id' => $conv->id]);
        $this->assertDatabaseMissing('crm_mensajes', ['id' => $mensaje->id]);
    }

    public function test_filtro_tickets_activos_vs_cerrados_en_livewire_crm(): void
    {
        $convActiva = CrmConversacion::create([
            'canal' => 'web',
            'identificador_remoto' => 'test-active',
            'nombre_contacto' => 'Visitante Activo',
            'modo_atencion' => 'ia',
            'estado' => 'activa',
            'ultimo_mensaje_texto' => 'Necesito ayuda',
            'ultimo_mensaje_at' => now(),
        ]);

        $convCerrada = CrmConversacion::create([
            'canal' => 'web',
            'identificador_remoto' => 'test-closed',
            'nombre_contacto' => 'Visitante Cerrado',
            'modo_atencion' => 'humano',
            'estado' => 'cerrada',
            'ultimo_mensaje_texto' => 'Ya todo listo',
            'ultimo_mensaje_at' => now(),
        ]);

        $this->actingAs($this->adminUser);

        // Por defecto (filtro 'activos'): se ve la activa, NO la cerrada
        Livewire::test('crm.index')
            ->set('tab', 'chats')
            ->assertSee('Visitante Activo')
            ->assertDontSee('Visitante Cerrado');

        // Filtro 'cerradas': se ve la cerrada, NO la activa
        Livewire::test('crm.index')
            ->set('tab', 'chats')
            ->set('chatFiltroEstado', 'cerradas')
            ->assertSee('Visitante Cerrado')
            ->assertDontSee('Visitante Activo');

        // Filtro 'todos': se ven ambas
        Livewire::test('crm.index')
            ->set('tab', 'chats')
            ->set('chatFiltroEstado', 'todos')
            ->assertSee('Visitante Activo')
            ->assertSee('Visitante Cerrado');
    }
}
