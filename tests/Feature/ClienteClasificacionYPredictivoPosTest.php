<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\ClienteService;
use App\Services\FidelizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClienteClasificacionYPredictivoPosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_sistema_inicia_limpio_con_exactamente_4_usuarios_principales(): void
    {
        $this->assertSame(7, User::count());
        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Pedido::count());

        $emailsEsperados = [
            'admin@restomaster.com',
            'cajero@restomaster.com',
            'mesero@restomaster.com',
            'cocina@restomaster.com',
        ];

        foreach ($emailsEsperados as $email) {
            $this->assertDatabaseHas('users', ['email' => $email]);
        }
    }

    public function test_cliente_service_crea_cliente_ocasional_solo_con_nombre(): void
    {
        $service = app(ClienteService::class);

        $cliente = $service->buscarOcrearOcasional('Carlos Mario Restrepo');

        $this->assertNotNull($cliente->id);
        $this->assertSame('Carlos Mario Restrepo', $cliente->nombre);
        $this->assertSame(Cliente::TIER_OCASIONAL, $cliente->tier);
        $this->assertNull($cliente->telefono);
        $this->assertFalse($cliente->tieneHabeasData());

        // Llamar nuevamente no duplica el registro
        $mismoCliente = $service->buscarOcrearOcasional('carlos mario restrepo');
        $this->assertSame($cliente->id, $mismoCliente->id);
        $this->assertSame(1, Cliente::whereRaw('LOWER(nombre) = ?', ['carlos mario restrepo'])->count());
    }

    public function test_busqueda_predictiva_requiere_al_menos_4_caracteres(): void
    {
        $service = app(ClienteService::class);

        $cliente = Cliente::create([
            'nombre' => 'Alejandro Morales',
            'tier' => Cliente::TIER_OCASIONAL,
            'puntos_fidelidad' => 0,
            'activo' => true,
        ]);

        // Menos de 4 caracteres devuelve vacío
        $this->assertEmpty($service->buscarPredictivo('Ale', 5));
        $this->assertEmpty($service->buscarPredictivo('Al', 5));

        // Con 4 caracteres o más encuentra al cliente
        $resultados = $service->buscarPredictivo('Alej', 5);
        $this->assertCount(1, $resultados);
        $this->assertSame($cliente->id, $resultados[0]['id']);
        $this->assertSame('Alejandro Morales', $resultados[0]['nombre']);
        $this->assertSame('ocasional', $resultados[0]['tier']);
    }

    public function test_fidelizacion_service_promueve_ocasional_a_frecuente_automaticamente(): void
    {
        $fidelizacion = app(FidelizacionService::class);

        $cliente = Cliente::create([
            'nombre' => 'Diana Marcela Gómez',
            'tier' => Cliente::TIER_OCASIONAL,
            'visitas_count' => 0,
            'puntos_fidelidad' => 0,
            'activo' => true,
        ]);

        $mesa = Mesa::first();
        $pedido1 = Pedido::create([
            'codigo' => 'ORD-TEST-01',
            'tipo' => 'mesa',
            'mesa_id' => $mesa?->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->nombre,
            'sucursal_id' => 1,
            'estado' => 'pagado',
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        // Primera visita: permanece ocasional
        $fidelizacion->registrarVisita($cliente, $pedido1);
        $this->assertSame(1, $cliente->fresh()->visitas_count);
        $this->assertSame(Cliente::TIER_OCASIONAL, $cliente->fresh()->tier);

        $pedido2 = Pedido::create([
            'codigo' => 'ORD-TEST-02',
            'tipo' => 'mesa',
            'mesa_id' => $mesa?->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->nombre,
            'sucursal_id' => 1,
            'estado' => 'pagado',
            'subtotal' => 60000,
            'total' => 60000,
        ]);

        // Segunda visita: evolución automática a frecuente
        $fidelizacion->registrarVisita($cliente, $pedido2);
        $this->assertSame(2, $cliente->fresh()->visitas_count);
        $this->assertSame(Cliente::TIER_FRECUENTE, $cliente->fresh()->tier);
        $this->assertTrue($cliente->fresh()->isFrecuente());
    }

    public function test_consentimiento_habeas_data_ley_1581_persiste_autorizaciones(): void
    {
        $service = app(ClienteService::class);

        $cliente = Cliente::create([
            'nombre' => 'Esteban Henao',
            'tier' => Cliente::TIER_OCASIONAL,
            'puntos_fidelidad' => 0,
            'activo' => true,
        ]);

        $this->assertFalse($cliente->tieneHabeasData());

        $service->registrarConsentimientoHabeasData($cliente, [
            'telefono' => '3001234567',
            'email' => 'esteban@ejemplo.com',
            'direccion' => 'Calle 10 # 43E-20, Apto 502',
            'acepta_tratamiento_datos' => true,
            'canal_autorizacion_datos' => 'pos_terminal',
            'autoriza_whatsapp' => true,
            'autoriza_email' => true,
        ]);

        $cliente->refresh();

        $this->assertTrue($cliente->tieneHabeasData());
        $this->assertSame('3001234567', $cliente->telefono);
        $this->assertSame('esteban@ejemplo.com', $cliente->email);
        $this->assertSame('pos_terminal', $cliente->canal_autorizacion_datos);
        $this->assertNotNull($cliente->fecha_autorizacion_datos);
        $this->assertTrue($cliente->autoriza_whatsapp);
        $this->assertTrue($cliente->autoriza_email);
        $this->assertCount(1, $cliente->direcciones);
        $this->assertStringContainsString('Calle 10 # 43E-20', $cliente->direcciones->first()->direccion);
    }

    public function test_pos_terminal_autocrea_cliente_ocasional_al_enviar_a_cocina(): void
    {
        $cajero = User::where('email', 'cajero@restomaster.com')->first();
        $this->actingAs($cajero);

        $categoria = Categoria::first() ?? Categoria::create([
            'nombre' => 'Maki Rolls',
            'slug' => 'maki-rolls-test',
            'icono' => '🍣',
            'orden' => 1,
            'activo' => true,
        ]);

        $producto = Producto::where('activo', true)->first() ?? Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'California Roll Test',
            'slug' => 'california-roll-test',
            'precio' => 28000,
            'costo' => 10000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
        $mesa = Mesa::first() ?? Mesa::create([
            'numero' => '1',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
            'sucursal_id' => 1,
        ]);

        $component = Volt::test('pos.terminal')
            ->set('mesaId', $mesa->id)
            ->set('tipo', 'mesa')
            ->call('agregarProducto', $producto->id)
            ->set('nombreCliente', 'Gloria Patricia Londoño')
            ->assertSet('clienteId', null)
            ->call('enviarACocina');

        // El cliente debe haber sido creado automáticamente en BD como ocasional
        $clienteCreado = Cliente::where('nombre', 'Gloria Patricia Londoño')->first();
        $this->assertNotNull($clienteCreado);
        $this->assertSame(Cliente::TIER_OCASIONAL, $clienteCreado->tier);

        // El pedido en cocina debe estar vinculado al cliente
        $pedido = Pedido::where('mesa_id', $mesa->id)->latest()->first();
        $this->assertNotNull($pedido);
        $this->assertSame($clienteCreado->id, $pedido->cliente_id);
        $this->assertSame('Gloria Patricia Londoño', $pedido->nombre_cliente);
    }

    public function test_pos_terminal_busqueda_predictiva_en_vivo(): void
    {
        $cajero = User::where('email', 'cajero@restomaster.com')->first();
        $this->actingAs($cajero);

        $cliente = Cliente::create([
            'nombre' => 'Sebastián Caicedo',
            'telefono' => '3119876543',
            'tier' => Cliente::TIER_VIP,
            'puntos_fidelidad' => 1500,
            'activo' => true,
        ]);

        Volt::test('pos.terminal')
            ->set('nombreCliente', 'Seb') // 3 letras -> sin sugerencias
            ->assertSet('mostrarSugerencias', false)
            ->assertSet('sugerenciasClientes', [])
            ->set('nombreCliente', 'Seba') // 4 letras -> activa predictivo
            ->assertSet('mostrarSugerencias', true)
            ->call('seleccionarClientePredictivo', $cliente->id)
            ->assertSet('clienteId', $cliente->id)
            ->assertSet('nombreCliente', 'Sebastián Caicedo')
            ->assertSet('telefonoCliente', '3119876543')
            ->assertSet('puntosDisponibles', 1500)
            ->assertSet('mostrarSugerencias', false);
    }

    public function test_pos_terminal_boton_nuevo_cliente_habeas_data(): void
    {
        $cajero = User::where('email', 'cajero@restomaster.com')->first();
        $this->actingAs($cajero);

        Volt::test('pos.terminal')
            ->set('nombreCliente', 'Carlos Mendoza')
            ->call('abrirModalHabeasData')
            ->assertSet('mostrarModalHabeasData', true)
            ->assertSet('habeasNombre', 'Carlos Mendoza')
            ->set('habeasTelefono', '3007654321')
            ->set('habeasEmail', 'carlos.mendoza@example.com')
            ->set('habeasAcepta', true)
            ->set('habeasWhatsapp', true)
            ->call('guardarHabeasData')
            ->assertSet('mostrarModalHabeasData', false)
            ->assertSet('nombreCliente', 'Carlos Mendoza')
            ->assertSet('telefonoCliente', '3007654321');

        $cliente = Cliente::where('nombre', 'Carlos Mendoza')->first();
        $this->assertNotNull($cliente);
        $this->assertSame('3007654321', $cliente->telefono);
        $this->assertSame('carlos.mendoza@example.com', $cliente->email);
        $this->assertTrue($cliente->acepta_tratamiento_datos);
        $this->assertSame('pos_terminal', $cliente->canal_autorizacion_datos);
        $this->assertSame(Cliente::TIER_FRECUENTE, $cliente->tier);
    }
}
