<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MeseroPosOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private User $mesero;

    private User $admin;

    private Mesa $mesa;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);

        $this->mesero = User::factory()->create([
            'name' => 'Carlos Mesero',
            'email' => 'mesero@sushixpress.com',
            'role_id' => $roleMesero->id,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Boss',
            'email' => 'admin@sushixpress.com',
            'role_id' => $roleAdmin->id,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Maki Rolls',
            'slug' => 'maki-rolls',
            'icono' => '🍣',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'California Roll',
            'slug' => 'california-roll',
            'precio' => 28000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $sucursal->id,
            'numero' => 4,
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'libre',
            'activo' => true,
        ]);
    }

    public function test_login_con_rol_mesero_redirige_directamente_a_pos(): void
    {
        $component = Volt::test('pages.auth.login')
            ->set('form.email', $this->mesero->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('pos', absolute: false));

        $this->assertAuthenticatedAs($this->mesero);
    }

    public function test_mesero_accediendo_a_dashboard_es_redirigido_a_pos(): void
    {
        $response = $this->actingAs($this->mesero)->get(route('dashboard'));

        $response->assertRedirect(route('pos'));
    }

    public function test_admin_accediendo_a_dashboard_mantiene_acceso_normal(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_navegacion_para_mesero_solo_muestra_terminal_pos_y_oculta_modulos_administrativos(): void
    {
        $response = $this->actingAs($this->mesero)->get(route('pos'));

        $response->assertOk();
        $response->assertSee('Terminal Mesero');
        $response->assertSee('Terminal POS');
        $response->assertSee('Mesero: Carlos Mesero');

        // Los enlaces a módulos administrativos o ajenos al rol mesero NO deben estar en el menú
        $response->assertDontSee('href="'.route('dashboard').'"', false);
        $response->assertDontSee('href="'.route('caja').'"', false);
        $response->assertDontSee('href="'.route('inventario').'"', false);
        $response->assertDontSee('href="'.route('menu').'"', false);
        $response->assertDontSee('href="'.route('clientes').'"', false);
        $response->assertDontSee('href="'.route('delivery').'"', false);
        $response->assertDontSee('href="'.route('reportes').'"', false);
        $response->assertDontSee('Caja #01: Abierta');
        $response->assertDontSee('Sincronizado DIAN');
        $response->assertDontSee('+ Nuevo Producto');
    }

    public function test_pos_oculta_opcion_delivery_para_rol_mesero(): void
    {
        $response = $this->actingAs($this->mesero)->get(route('pos'));

        $response->assertOk();
        $response->assertSee('En Mesa');
        $response->assertSee('Para Llevar');
        $response->assertDontSee('wire:click="$set(\'tipo\', \'delivery\')"', false);
    }

    public function test_mesero_envia_comanda_a_cocina_y_permanece_en_pos(): void
    {
        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id)
            ->call('agregarProducto', $this->producto->id);

        $component->call('enviarACocina');

        $component->assertRedirect(route('pos'));

        $this->assertDatabaseHas('pedidos', [
            'mesa_id' => $this->mesa->id,
            'estado' => 'en_cocina',
            'usuario_id' => $this->mesero->id,
        ]);
    }

    public function test_mesero_cobra_pedido_directamente_en_mesa_y_libera_mesa_permaneciendo_en_pos(): void
    {
        $caja = Caja::create([
            'sucursal_id' => $this->mesa->sucursal_id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        // Crear turno de caja abierto para contabilización
        TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->admin->id,
            'monto_inicial' => 100000,
            'monto_esperado_efectivo' => 100000,
            'estado' => 'abierto',
            'apertura_en' => now(),
        ]);

        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id)
            ->call('agregarProducto', $this->producto->id)
            ->call('abrirModalCobro')
            ->set('metodoPago', 'tarjeta')
            ->set('montoPagado', 28000)
            ->call('procesarCobro');

        $component->assertSet('mostrarTicket', true);
        $this->assertNotNull($component->get('pedidoCompletado'));

        $component->call('cerrarTicket');

        $component->assertRedirect(route('pos'));

        // Verificar pedido pagado
        $this->assertDatabaseHas('pedidos', [
            'mesa_id' => $this->mesa->id,
            'estado' => 'pagado',
            'metodo_pago' => 'tarjeta',
        ]);

        // Verificar que la mesa fue liberada a 'por_limpiar'
        $this->mesa->refresh();
        $estado = is_string($this->mesa->estado) ? $this->mesa->estado : $this->mesa->estado->value;
        $this->assertEquals('por_limpiar', $estado);
    }

    public function test_cambiar_mesa_en_pos_carga_comanda_existente_reactivamente(): void
    {
        // Crear comanda activa previa para la mesa
        $pedido = Pedido::create([
            'codigo' => 'ORD-TEST-001',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'mesa_id' => $this->mesa->id,
            'usuario_id' => $this->mesero->id,
            'total' => 28000,
            'subtotal' => 28000,
        ]);

        $pedido->items()->create([
            'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 28000,
            'subtotal' => 28000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'en_preparacion',
        ]);

        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id);

        $carrito = $component->get('carrito');
        $this->assertArrayHasKey($this->producto->id, $carrito);
        $this->assertEquals(1, $carrito[$this->producto->id]['cantidad']);
        $this->assertEquals(28000, $component->get('subtotal'));
    }

    public function test_mesero_puede_alternar_entre_vistas_pc_tablet_y_movil(): void
    {
        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal');

        // Vista por defecto es PC
        $component->assertSet('vistaMesero', 'pc');
        $component->assertSee('desktop_windows');

        // Cambiar a Tablet
        $component->call('cambiarVista', 'tablet');
        $component->assertSet('vistaMesero', 'tablet');

        // Cambiar a Móvil
        $component->call('cambiarVista', 'movil');
        $component->assertSet('vistaMesero', 'movil');
    }

    public function test_vista_movil_activa_barra_flotante_y_drawer_de_comanda(): void
    {
        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->set('mesaId', $this->mesa->id)
            ->call('cambiarVista', 'movil')
            ->call('agregarProducto', $this->producto->id);

        // En vista móvil debe verse la barra inferior flotante
        $component->assertSee('Comanda');
        $component->assertSee('28.000');
        $component->assertSee('Ver Comanda');

        // Abrir el drawer de comanda móvil
        $component->set('mostrarComandaMovil', true);
        $component->assertSee('Comanda en Mano (Móvil)');
        $component->assertSee('California Roll');
    }

    public function test_selector_de_vistas_no_es_visible_para_otros_roles(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pos'));

        $response->assertOk();
        $response->assertDontSee('id="btnVistaPc"', false);
        $response->assertDontSee('id="btnVistaTablet"', false);
        $response->assertDontSee('id="btnVistaMovil"', false);
    }

    public function test_pos_terminal_eager_loads_categoria_sin_n_plus_1(): void
    {
        $component = Volt::actingAs($this->mesero)
            ->test('pos.terminal');

        $component->assertOk();
        $productos = $component->viewData('productos');
        $this->assertNotEmpty($productos);
        $this->assertTrue($productos->first()->relationLoaded('categoria'));
    }
}
