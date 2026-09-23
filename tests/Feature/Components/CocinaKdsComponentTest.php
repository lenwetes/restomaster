<?php

namespace Tests\Feature\Components;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CocinaKdsComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $cocinero;

    private Sucursal $sucursal;

    private Mesa $mesa;

    private Producto $productoSushi;

    private Producto $productoCaliente;

    private Pedido $pedido;

    private ItemPedido $itemSushi;

    private ItemPedido $itemCaliente;

    protected function setUp(): void
    {
        parent::setUp();

        $roleCocina = Role::create(['nombre' => 'Cocina', 'slug' => 'cocina']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Laureles',
            'codigo' => 'LAU-01',
            'activa' => true,
        ]);

        $this->cocinero = User::factory()->create([
            'role_id' => $roleCocina->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 8,
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'ocupada',
        ]);

        $catSushi = Categoria::create(['nombre' => 'Sushi', 'slug' => 'sushi', 'activo' => true]);
        $catCaliente = Categoria::create(['nombre' => 'Wok & Caliente', 'slug' => 'wok-caliente', 'activo' => true]);

        $this->productoSushi = Producto::create([
            'categoria_id' => $catSushi->id,
            'nombre' => 'Nigiri Salmón 4 Piezas',
            'slug' => 'nigiri-salmon-4-piezas',
            'precio' => 24000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $this->productoCaliente = Producto::create([
            'categoria_id' => $catCaliente->id,
            'nombre' => 'Ramen Tonkotsu',
            'slug' => 'ramen-tonkotsu',
            'precio' => 42000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        $this->pedido = Pedido::create([
            'codigo' => 'ORD-KDS-01',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $this->mesa->id,
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'subtotal' => 66000,
            'total' => 66000,
        ]);

        $this->itemSushi = ItemPedido::create([
            'pedido_id' => $this->pedido->id,
            'producto_id' => $this->productoSushi->id,
            'nombre_producto' => $this->productoSushi->nombre,
            'cantidad' => 1,
            'precio_unitario' => 24000,
            'subtotal' => 24000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        $this->itemCaliente = ItemPedido::create([
            'pedido_id' => $this->pedido->id,
            'producto_id' => $this->productoCaliente->id,
            'nombre_producto' => $this->productoCaliente->nombre,
            'cantidad' => 1,
            'precio_unitario' => 42000,
            'subtotal' => 42000,
            'area_cocina' => 'caliente',
            'estado_cocina' => 'pendiente',
        ]);
    }

    /**
     * Test de renderizado del KDS mostrando comandas y platos de la sucursal.
     */
    public function test_kds_renderiza_comandas_activas(): void
    {
        $this->actingAs($this->cocinero);

        Volt::test('cocina.kds')
            ->assertSee('Nigiri Salmón 4 Piezas')
            ->assertSee('Ramen Tonkotsu')
            ->assertSee('Mesa 8');
    }

    /**
     * Test de filtrado por área de cocina gastronómica (sushi vs caliente).
     */
    public function test_kds_filtra_por_area_de_cocina(): void
    {
        $this->actingAs($this->cocinero);

        // Al filtrar por 'sushi', solo debe verse el Nigiri
        Volt::test('cocina.kds')
            ->set('areaSeleccionada', 'sushi')
            ->assertSee('Nigiri Salmón 4 Piezas')
            ->assertDontSee('Ramen Tonkotsu');
    }

    /**
     * Test de transición de estado del plato: tomar y marcar listo.
     */
    public function test_cocinero_puede_tomar_y_marcar_listo_un_item(): void
    {
        $this->actingAs($this->cocinero);

        $component = Volt::test('cocina.kds')
            ->call('tomarItem', $this->itemSushi->id);

        $this->itemSushi->refresh();
        $this->assertSame('en_preparacion', $this->itemSushi->estado_cocina);

        $component->call('marcarListo', $this->itemSushi->id);

        $this->itemSushi->refresh();
        $this->assertSame('listo', $this->itemSushi->estado_cocina);
    }
}
