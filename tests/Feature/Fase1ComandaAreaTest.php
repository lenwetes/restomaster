<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase1ComandaAreaTest extends TestCase
{
    use RefreshDatabase;

    private User $cocina;

    private User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Cocina', 'slug' => 'cocina']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->cocina = User::factory()->create(['role_id' => Role::where('slug', 'cocina')->value('id')]);
        $this->mesero = User::factory()->create(['role_id' => Role::where('slug', 'mesero')->value('id')]);
    }

    private function crearPedidoConDosAreas(): Pedido
    {
        $categoria = Categoria::create(['nombre' => 'Carta', 'slug' => 'carta']);
        $nigiri = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Nigiri Salmón',
            'slug' => 'nigiri-salmon',
            'precio' => 10000,
            'area_cocina' => 'sushi',
        ]);
        $sakeBomb = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Sake Bomb',
            'slug' => 'sake-bomb',
            'precio' => 10000,
            'area_cocina' => 'barra',
        ]);

        $pedido = Pedido::create([
            'codigo' => 'TST-001',
            'tipo' => 'salon',
            'estado' => 'en_cocina',
            'usuario_id' => $this->mesero->id,
            'subtotal' => 30000,
            'descuento' => 0,
            'total' => 30000,
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $nigiri->id,
            'nombre_producto' => 'Nigiri Salmón',
            'cantidad' => 2,
            'precio_unitario' => 10000,
            'subtotal' => 20000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
            'notas' => 'Sin soja',
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $sakeBomb->id,
            'nombre_producto' => 'Sake Bomb',
            'cantidad' => 1,
            'precio_unitario' => 10000,
            'subtotal' => 10000,
            'area_cocina' => 'barra',
            'estado_cocina' => 'pendiente',
        ]);

        return $pedido;
    }

    public function test_comanda_por_area_filtra_items_de_la_estacion(): void
    {
        $pedido = $this->crearPedidoConDosAreas();

        Volt::actingAs($this->cocina)
            ->test('cocina.kds')
            ->set('areaSeleccionada', 'sushi')
            ->set('comandaParaImprimir', $pedido->id)
            ->assertSet('comandaParaImprimir', $pedido->id)
            ->assertSee('Nigiri Salmón')
            ->assertDontSee('Sake Bomb');
    }

    public function test_comanda_por_area_barra_no_muestra_sushi(): void
    {
        $pedido = $this->crearPedidoConDosAreas();

        Volt::actingAs($this->cocina)
            ->test('cocina.kds')
            ->set('areaSeleccionada', 'barra')
            ->set('comandaParaImprimir', $pedido->id)
            ->assertSet('comandaParaImprimir', $pedido->id)
            ->assertSee('Sake Bomb')
            ->assertDontSee('Nigiri Salmón');
    }

    public function test_marcar_toda_comanda_lista_solo_expide_items_del_area(): void
    {
        $pedido = $this->crearPedidoConDosAreas();

        Volt::actingAs($this->cocina)
            ->test('cocina.kds')
            ->set('areaSeleccionada', 'sushi')
            ->call('marcarTodaComandaLista', $pedido->id);

        $this->assertSame('listo', $pedido->items()->where('area_cocina', 'sushi')->value('estado_cocina'));
        $this->assertSame('pendiente', $pedido->items()->where('area_cocina', 'barra')->value('estado_cocina'));
    }

    public function test_kds_solo_disponible_para_roles_cocina(): void
    {
        Role::create(['nombre' => 'Barra', 'slug' => 'barra']);
        $barra = User::factory()->create(['role_id' => Role::where('slug', 'barra')->value('id')]);

        $this->actingAs($this->mesero)->get(route('cocina'))->assertForbidden();
        $this->actingAs($this->cocina)->get(route('cocina'))->assertOk();
        $this->actingAs($barra)->get(route('cocina'))->assertOk();
    }
}
