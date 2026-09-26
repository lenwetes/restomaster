<?php

namespace Tests\Feature;

use App\Jobs\DespacharMensajeCrmJob;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmMensajeLog;
use App\Models\CrmPlantilla;
use App\Models\Encuesta;
use App\Models\EncuestaEnvio;
use App\Models\EncuestaRespuesta;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CrmEstadisticasService;
use App\Services\CrmWhatsAppService;
use App\Services\PedidoService;
use Database\Seeders\CrmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CrmAutomatizacionesTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $gerente;

    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrmSeeder::class);

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Poblado',
            'direccion' => 'Cra 43A # 1-50',
            'telefono' => '3001234567',
            'activo' => true,
        ]);

        $roleGerente = Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente']);

        $this->gerente = User::create([
            'name' => 'Carlos Gerente',
            'email' => 'gerente@restomaster.com',
            'password' => bcrypt('password'),
            'role_id' => $roleGerente->id,
            'is_active' => true,
        ]);

        $this->cliente = Cliente::create([
            'nombre' => 'Ana Gómez',
            'email' => 'ana.gomez@test.com',
            'telefono' => '3009876543',
            'puntos_fidelidad' => 150,
            'visitas_count' => 4,
            'total_gastado' => 220000,
            'activo' => true,
            'autoriza_whatsapp' => true,
            'autoriza_email' => true,
        ]);
    }

    public function test_acceso_al_dashboard_crm_por_gerente(): void
    {
        $response = $this->actingAs($this->gerente)->get(route('crm'));

        $response->assertOk();
        $response->assertSee('CRM');
        $response->assertSee('Tablero de Satisfacción');
        $response->assertSee('WhatsApp');
    }

    public function test_calculo_correcto_de_kpis_csat_y_nps(): void
    {
        $encuesta = Encuesta::firstOrCreate(
            ['nombre' => 'Encuesta Test'],
            [
                'disparador' => 'post_pago',
                'activa' => true,
                'preguntas' => [['tipo' => 'estrellas', 'pregunta' => 'General']],
            ]
        );

        // Crear 3 respuestas: dos de 5 estrellas y una de 1 estrella
        $envio1 = EncuestaEnvio::create([
            'encuesta_id' => $encuesta->id,
            'cliente_id' => $this->cliente->id,
            'token' => 'token_test_1',
            'estado' => 'respondida',
            'enviada_en' => now(),
            'respondida_en' => now(),
        ]);
        EncuestaRespuesta::create([
            'envio_id' => $envio1->id,
            'pregunta_indice' => 0,
            'tipo_respuesta' => 'estrellas',
            'valor_estrellas' => 5,
        ]);

        $envio2 = EncuestaEnvio::create([
            'encuesta_id' => $encuesta->id,
            'cliente_id' => $this->cliente->id,
            'token' => 'token_test_2',
            'estado' => 'respondida',
            'enviada_en' => now(),
            'respondida_en' => now(),
        ]);
        EncuestaRespuesta::create([
            'envio_id' => $envio2->id,
            'pregunta_indice' => 0,
            'tipo_respuesta' => 'estrellas',
            'valor_estrellas' => 5,
        ]);

        $envio3 = EncuestaEnvio::create([
            'encuesta_id' => $encuesta->id,
            'cliente_id' => $this->cliente->id,
            'token' => 'token_test_3',
            'estado' => 'respondida',
            'enviada_en' => now(),
            'respondida_en' => now(),
        ]);
        EncuestaRespuesta::create([
            'envio_id' => $envio3->id,
            'pregunta_indice' => 0,
            'tipo_respuesta' => 'estrellas',
            'valor_estrellas' => 1,
        ]);

        $service = app(CrmEstadisticasService::class);
        $kpis = $service->obtenerKpis();

        $this->assertEquals(3, $kpis['total_votos']);
        // CSAT: 2 de 3 satisfechos = 66.7%
        $this->assertEquals(66.7, $kpis['csat']);
        // NPS: 2 promotores (66.7%) - 1 detractor (33.3%) = +33
        $this->assertEquals(33, $kpis['nps']);
        // Promedio: (5 + 5 + 1) / 3 = 3.67
        $this->assertEquals(3.67, $kpis['promedio_estrellas']);
    }

    public function test_envio_simulado_de_mensaje_whatsapp(): void
    {
        $service = app(CrmWhatsAppService::class);

        $log = $service->enviarMensaje(
            telefono: '+573009876543',
            contenido: 'Mensaje de prueba de automatización CRM',
            clienteId: $this->cliente->id
        );

        $this->assertInstanceOf(CrmMensajeLog::class, $log);
        $this->assertEquals('enviado', $log->estado);
        $this->assertEquals('573009876543', $log->destinatario);
        $this->assertStringStartsWith('wamid.HBg', $log->mensaje_id_externo);
        $this->assertDatabaseHas('crm_mensajes_log', [
            'id' => $log->id,
            'estado' => 'enviado',
            'canal' => 'whatsapp',
        ]);
    }

    public function test_disparo_de_automatizacion_crm_en_cobro_de_pedido(): void
    {
        Queue::fake();

        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja POS 1',
            'codigo' => 'CAJA-POS-01',
            'activa' => true,
        ]);

        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->gerente->id,
            'monto_inicial' => 100000,
            'estado' => 'abierto',
            'apertura_en' => now(),
        ]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 15,
            'capacidad' => 4,
            'estado' => 'ocupada',
        ]);

        $categoria = Categoria::create(['nombre' => 'Rolls Especiales', 'slug' => 'rolls-especiales', 'activo' => true]);
        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Dragon Roll Master',
            'slug' => 'dragon-roll-master',
            'precio' => 38000,
            'activo' => true,
        ]);

        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedido([
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'mesero_id' => $this->gerente->id,
            'cliente_id' => $this->cliente->id,
            'tipo' => 'mesa',
        ], [
            ['producto_id' => $producto->id, 'cantidad' => 2],
        ], $this->gerente);

        $pedidoService->enviarACocina($pedido);
        $pedido->items()->update(['estado_cocina' => 'listo']);

        // Cobrar pedido
        $pedidoCobrado = $pedidoService->cobrarPedido($pedido, 'efectivo', 76000);

        $this->assertEquals('pagado', $pedidoCobrado->estado);

        // Verificar que el job de despacho CRM fue encolado
        Queue::assertPushed(DespacharMensajeCrmJob::class, function ($job) use ($pedidoCobrado) {
            return $job->canal === 'whatsapp' &&
                $job->destinatario === $this->cliente->telefono &&
                $job->pedidoId === $pedidoCobrado->id;
        });
    }

    public function test_handshake_verificacion_webhook_whatsapp(): void
    {
        $config = CrmConfiguracion::activa();
        $token = $config->whatsapp_webhook_secret;

        $response = $this->get('/api/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $token,
            'hub_challenge' => 'test_challenge_998811',
        ]));

        $response->assertOk();
        $response->assertSee('test_challenge_998811');
    }

    public function test_actualizacion_de_estado_de_entrega_webhook_whatsapp(): void
    {
        $log = CrmMensajeLog::create([
            'canal' => 'whatsapp',
            'destinatario' => '573009876543',
            'contenido_enviado' => 'Hola',
            'estado' => 'enviado',
            'mensaje_id_externo' => 'wamid.HBgTestMessage123',
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'statuses' => [
                                    [
                                        'id' => 'wamid.HBgTestMessage123',
                                        'status' => 'delivered',
                                        'timestamp' => now()->timestamp,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('crm_mensajes_log', [
            'id' => $log->id,
            'estado' => 'entregado',
        ]);
    }

    public function test_componente_livewire_permite_modificar_configuracion_crm(): void
    {
        Volt::actingAs($this->gerente)
            ->test('crm.index')
            ->set('whatsapp_proveedor', 'meta_cloud')
            ->set('whatsapp_phone_number_id', '998877665544')
            ->set('whatsapp_waba_id', '112233445566')
            ->set('whatsapp_telefono_pruebas', '+573109998877')
            ->call('guardarConfiguracion')
            ->assertSee('¡Configuración CRM actualizada con éxito!');

        $this->assertDatabaseHas('crm_configuraciones', [
            'whatsapp_phone_number_id' => '998877665544',
            'whatsapp_waba_id' => '112233445566',
            'whatsapp_telefono_pruebas' => '+573109998877',
        ]);
    }

    public function test_renderizado_de_plantilla_con_variables_dinamicas(): void
    {
        $plantilla = CrmPlantilla::where('codigo', 'encuesta_satisfaccion_wa')->first();
        $this->assertNotNull($plantilla);

        $render = $plantilla->renderizar([
            'nombre' => 'Carlos Mendoza',
            'restaurante' => 'RestoMaster',
            'url_encuesta' => 'https://restomaster.test/e/abc',
        ]);

        $this->assertStringContainsString('¡Hola Carlos Mendoza!', $render);
        $this->assertStringContainsString('RestoMaster', $render);
        $this->assertStringContainsString('https://restomaster.test/e/abc', $render);
    }
}
