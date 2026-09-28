<?php

namespace Tests\Feature;

use App\Models\CrmConfiguracion;
use App\Models\CrmIaPlantillaPrivilegio;
use App\Services\Ai\CrmAiAgentService;
use App\Services\Ai\CrmChatOrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmPersuasionOpcionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CrmConfiguracion::create([
            'ia_activo' => true,
            'ia_proveedor' => 'gemini',
            'ia_modelo' => 'gemini-1.5-flash',
            'ia_limite_mensajes_por_cliente_dia' => 50,
        ]);

        CrmIaPlantillaPrivilegio::create([
            'nombre' => 'Hostess Completa',
            'slug' => 'hostess-completa',
            'es_default' => true,
            'permitir_menu' => true,
            'permitir_precios' => true,
            'permitir_alergenos' => true,
            'permitir_verificar_mesas' => true,
            'permitir_crear_reservas' => true,
            'max_personas_reserva' => 6,
            'permitir_promociones' => true,
            'permitir_puntos_vip' => true,
            'tono_conducta' => 'amable_calido',
        ]);
    }

    public function test_pregunta_pedidos_no_repite_saludo_en_bucle_y_persuade_a_usar_opciones(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $uuid = (string) Str::uuid();
        $conv = $orchestrator->obtenerOCrearConversacion('web', $uuid, 'Visitante Web');

        // Caso exacto de la captura de pantalla del usuario: "¿puedo hacer un pedido desde aqui?"
        $orchestrator->procesarMensajeCliente($conv, 'puedo hacer un pedido desde aqui?');

        $conv->refresh();
        $ultimoMensaje = $conv->mensajes()->latest('id')->first();

        $this->assertEquals('bot', $ultimoMensaje->emisor);

        // 1. NO debe caer en el bucle del saludo genérico de bienvenida
        $this->assertStringNotContainsString('Soy la anfitriona virtual de RestoMaster. Puedo orientarte con los platos de nuestra carta, verificar disponibilidad y agendar tu mesa. ¿En qué puedo colaborarte hoy?', $ultimoMensaje->contenido);

        // 2. Debe proporcionar el link al asistente de delivery y pedidos online
        $this->assertStringContainsString('asistente de pedidos y delivery en línea', $ultimoMensaje->contenido);
        $this->assertStringContainsString(route('delivery.publico'), $ultimoMensaje->contenido);

        // 3. Debe ofrecer la opción de agendar mesa en sala
        $this->assertStringContainsString('agendar tu mesa en segundos', $ultimoMensaje->contenido);
    }

    public function test_consultas_de_domicilios_o_delivery_persuaden_al_comensal(): void
    {
        /** @var CrmAiAgentService $service */
        $service = app(CrmAiAgentService::class);

        $resultado = $service->procesarMensaje('¿Hacen domicilios o servicio de delivery?');

        $this->assertEquals('autorizado', $resultado['estado']);
        $this->assertStringNotContainsString('¿En qué puedo colaborarte hoy?', $resultado['respuesta']);
        $this->assertStringContainsString(route('delivery.publico'), $resultado['respuesta']);
        $this->assertStringContainsString('asistente de pedidos y delivery', $resultado['respuesta']);
        $this->assertStringContainsString('agendar tu mesa', $resultado['respuesta']);
    }

    public function test_consulta_no_reconocida_fuera_de_alcance_no_repite_saludo_y_ofrece_alternativas(): void
    {
        /** @var CrmAiAgentService $service */
        $service = app(CrmAiAgentService::class);

        // Pregunta por algo fuera de alcance del restaurante
        $resultado = $service->procesarMensaje('¿Tienen servicio de piscina o valet parking para helicópteros?');

        $this->assertEquals('autorizado', $resultado['estado']);
        // NO debe reiniciar con el saludo genérico en bucle
        $this->assertStringNotContainsString('Soy la anfitriona virtual de RestoMaster. Puedo orientarte con los platos de nuestra carta', $resultado['respuesta']);

        // Debe ofrecer con persuasión las alternativas del restaurante
        $this->assertStringContainsString('Por el momento no dispongo de esa opción', $resultado['respuesta']);
        $this->assertStringContainsString('Agendar tu reserva', $resultado['respuesta']);
        $this->assertStringContainsString('Consultar nuestra carta', $resultado['respuesta']);
        $this->assertStringContainsString('Hablar con un asesor', $resultado['respuesta']);
    }

    public function test_saludo_puro_emite_bienvenida_cordial(): void
    {
        /** @var CrmAiAgentService $service */
        $service = app(CrmAiAgentService::class);

        $resultado = $service->procesarMensaje('Hola, buenas tardes');

        $this->assertEquals('autorizado', $resultado['estado']);
        $this->assertStringContainsString('Soy la anfitriona virtual de RestoMaster', $resultado['respuesta']);
        $this->assertStringContainsString('¿En qué puedo colaborarte hoy?', $resultado['respuesta']);
    }
}
