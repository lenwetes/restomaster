<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmIaPlantillaPrivilegio;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\Zona;
use App\Services\Ai\CrmChatOrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmChatReservaAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected CrmIaPlantillaPrivilegio $plantilla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Principal Gourmet',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001234567',
            'activo' => true,
        ]);

        $zona = Zona::create([
            'nombre' => 'Salón Principal',
            'slug' => 'salon-principal',
            'sucursal_id' => $this->sucursal->id,
            'activa' => true,
        ]);

        Mesa::create([
            'numero' => 1,
            'nombre' => 'Mesa 1',
            'capacidad' => 4,
            'sucursal_id' => $this->sucursal->id,
            'zona_id' => $zona->id,
            'zona' => 'salon-principal',
            'estado' => 'disponible',
            'activa' => true,
        ]);

        $this->plantilla = CrmIaPlantillaPrivilegio::create([
            'nombre' => 'Hostess Concierge Premium',
            'slug' => 'hostess-concierge-premium',
            'descripcion' => 'Plantilla para pruebas de reserva',
            'es_sistema' => true,
            'permitir_menu' => true,
            'permitir_precios' => true,
            'permitir_alergenos' => true,
            'permitir_verificar_mesas' => true,
            'permitir_crear_reservas' => true,
            'max_personas_reserva' => 6,
            'permitir_promociones' => true,
            'tono_conducta' => 'amable_calido',
        ]);

        CrmConfiguracion::create([
            'whatsapp_proveedor' => 'simulado',
            'ia_activo' => true,
            'ia_proveedor' => 'simulado',
            'ia_plantilla_privilegio_id' => $this->plantilla->id,
            'ia_limite_mensajes_por_cliente_dia' => 20,
        ]);
    }

    public function test_reconoce_intencion_y_crea_reserva_completa_desde_el_chat(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $uuid = (string) Str::uuid();
        $conv = $orchestrator->obtenerOCrearConversacion('web', $uuid, 'Visitante Web');

        $mensajeCliente = 'quiero una mesa para las 6 de la tarde mi nombre es luis eduardo , seremos 3 personas y la quiero para hoy';

        $orchestrator->procesarMensajeCliente($conv, $mensajeCliente);

        $conv->refresh();

        // 1. No debe activar handoff a humano por la palabra "personas"
        $this->assertEquals('ia', $conv->modo_atencion);
        $this->assertNotEquals('esperando_humano', $conv->estado);

        // 2. Debe actualizar el nombre de contacto en la conversación
        $this->assertEquals('Luis Eduardo', $conv->nombre_contacto);

        // 3. Debe crear la reserva en base de datos
        $reserva = Reserva::where('nombre_contacto', 'Luis Eduardo')->first();
        $this->assertNotNull($reserva, 'La reserva no fue creada en la base de datos.');

        $this->assertEquals(3, $reserva->personas);
        $this->assertEquals(today()->toDateString(), $reserva->fecha->toDateString());
        $this->assertEquals('18:00', substr((string) $reserva->hora_llegada, 0, 5));
        $this->assertEquals('ia_concierge', $reserva->origen);

        // 4. El mensaje del bot debe confirmar la reserva con su ID
        $ultimoMensaje = $conv->mensajes()->latest('id')->first();
        $this->assertEquals('bot', $ultimoMensaje->emisor);
        $this->assertStringContainsString('Luis Eduardo', $ultimoMensaje->contenido);
        $this->assertStringContainsString('agendada con éxito', $ultimoMensaje->contenido);
        $this->assertStringContainsString("Reserva #{$reserva->id}", $ultimoMensaje->contenido);
    }

    public function test_pide_datos_faltantes_cuando_la_solicitud_esta_incompleta(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $uuid = (string) Str::uuid();
        $conv = $orchestrator->obtenerOCrearConversacion('web', $uuid, 'Visitante Web');

        // El cliente pregunta por disponibilidad pero no da nombre, hora exacta ni personas
        $orchestrator->procesarMensajeCliente($conv, 'hay mesa disponible para hoy en las horas de la tarde?');

        $conv->refresh();
        $this->assertEquals('ia', $conv->modo_atencion);

        // No debe crear reserva aún
        $this->assertEquals(0, Reserva::count());

        $ultimoMensaje = $conv->mensajes()->latest('id')->first();
        $this->assertEquals('bot', $ultimoMensaje->emisor);
        $this->assertStringContainsString('nombre', $ultimoMensaje->contenido);
        $this->assertStringContainsString('hora', $ultimoMensaje->contenido);
    }

    public function test_completa_reserva_en_flujo_multiturn(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $uuid = (string) Str::uuid();
        $conv = $orchestrator->obtenerOCrearConversacion('web', $uuid, 'Visitante Web');

        // Turno 1: el usuario menciona fecha y hora
        $orchestrator->procesarMensajeCliente($conv, 'quiero reservar una mesa para hoy a las 8 de la noche');

        $this->assertEquals(0, Reserva::count());

        // Turno 2: el usuario da nombre y comensales
        $orchestrator->procesarMensajeCliente($conv, 'mi nombre es Carlos Gomez y seremos 2 personas');

        $conv->refresh();
        $this->assertEquals('ia', $conv->modo_atencion);

        $reserva = Reserva::where('nombre_contacto', 'Carlos Gomez')->first();
        $this->assertNotNull($reserva);
        $this->assertEquals(2, $reserva->personas);
        $this->assertEquals(today()->toDateString(), $reserva->fecha->toDateString());
        $this->assertEquals('20:00', substr((string) $reserva->hora_llegada, 0, 5));
    }

    public function test_rechaza_reserva_que_excede_limite_de_comensales(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $uuid = (string) Str::uuid();
        $conv = $orchestrator->obtenerOCrearConversacion('web', $uuid, 'Visitante Web');

        // 10 personas supera el tope de 6 comensales
        $orchestrator->procesarMensajeCliente($conv, 'quiero una mesa para 10 personas hoy a las 7 de la noche a nombre de Roberto');

        $this->assertEquals(0, Reserva::count());

        $ultimoMensaje = $conv->mensajes()->latest('id')->first();
        $this->assertStringContainsString('Para reservas de más de 6 personas', $ultimoMensaje->contenido);
    }

    public function test_mantiene_handoff_humano_cuando_se_pide_un_humano_real(): void
    {
        /** @var CrmChatOrchestratorService $orchestrator */
        $orchestrator = app(CrmChatOrchestratorService::class);

        $uuid = (string) Str::uuid();
        $conv = $orchestrator->obtenerOCrearConversacion('web', $uuid, 'Visitante Web');

        $orchestrator->procesarMensajeCliente($conv, 'Por favor quiero hablar con una persona de servicio al cliente');

        $conv->refresh();
        $this->assertEquals('humano', $conv->modo_atencion);
        $this->assertEquals('esperando_humano', $conv->estado);
    }
}
