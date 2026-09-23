<?php

namespace Tests\Feature\Components;

use App\Enums\MesaEstado;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PosTerminalComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $mesero;

    private User $cajero;

    private Sucursal $sucursal;

    private Mesa $mesa;

    private Producto $productoSushi;

    private Producto $productoBebida;

    private Caja $caja;

    private TurnoCaja $turno;

    protected function setUp(): void
    {
        parent::setUp();

        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'activa' => true,
        ]);

        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 4,
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
            'activo' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Especialidades',
            'slug' => 'especialidades',
            'activo' => true,
        ]);

        $this->productoSushi = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Ojo de Tigre Roll',
            'slug' => 'ojo-de-tigre-roll',
            'precio' => 36000,
            'area_cocina' => 'fria',
            'activo' => true,
        ]);

        $this->productoBebida = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Té Verde Japonés',
            'slug' => 'te-verde-japones',
            'precio' => 8000,
            'area_cocina' => 'barra',
            'activo' => true,
        ]);

        $this->caja = app(CajaService::class)->crearCaja([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal POS',
            'codigo' => 'POS-01',
            'activa' => true,
        ]);

        $this->turno = app(CajaService::class)->abrirTurno(
            caja: $this->caja,
            cajero: $this->cajero,
            fondoInicial: 150000.0
        );
    }

    /**
     * Test de renderizado inicial de la terminal POS táctil.
     */
    public function test_pos_terminal_renderiza_correctamente_con_catalogo_y_mesas(): void
    {
        $this->actingAs($this->mesero);

        Volt::test('pos.terminal')
            ->assertSee('Ojo de Tigre Roll')
            ->assertSee('Té Verde Japonés')
            ->assertSee('Mesa 4')
            ->assertSet('tipo', 'mesa')
            ->assertSet('carrito', []);
    }

    /**
     * Test de selección de mesa y adición de productos al carrito.
     */
    public function test_agregar_productos_al_carrito_actualiza_totales(): void
    {
        $this->actingAs($this->mesero);

        $component = Volt::test('pos.terminal')
            ->set('mesaId', $this->mesa->id)
            ->call('agregarProducto', $this->productoSushi->id)
            ->call('agregarProducto', $this->productoBebida->id);

        $carrito = $component->get('carrito');
        $this->assertCount(2, $carrito);
        $this->assertEquals(44000.0, (float) $component->get('subtotal'));
    }

    /**
     * Test de incremento y decremento de cantidades en la comanda.
     */
    public function test_modificar_cantidades_en_carrito_actualiza_subtotal(): void
    {
        $this->actingAs($this->mesero);

        $component = Volt::test('pos.terminal')
            ->set('mesaId', $this->mesa->id)
            ->call('agregarProducto', $this->productoSushi->id)
            ->call('incrementarCantidad', $this->productoSushi->id); // 2 unidades de 36.000 = 72.000

        $this->assertEquals(72000.0, (float) $component->get('subtotal'));

        $component->call('decrementarCantidad', $this->productoSushi->id); // Vuelve a 1 unidad
        $this->assertEquals(36000.0, (float) $component->get('subtotal'));
    }

    /**
     * Test de envío de comanda a cocina y actualización del estado de la mesa.
     */
    public function test_enviar_a_cocina_crea_pedido_y_actualiza_mesa_a_ocupada(): void
    {
        $this->actingAs($this->mesero);

        $component = Volt::test('pos.terminal')
            ->set('mesaId', $this->mesa->id)
            ->call('agregarProducto', $this->productoSushi->id)
            ->call('enviarACocina');

        $this->assertDatabaseHas('pedidos', [
            'mesa_id' => $this->mesa->id,
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
        ]);

        $this->mesa->refresh();
        $this->assertEquals(MesaEstado::OCUPADA->value, $this->mesa->estado);
        $this->assertEmpty($component->get('carrito'));
    }
}
