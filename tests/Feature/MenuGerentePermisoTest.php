<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MenuGerentePermisoTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $mesero;

    private Categoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $rolGerente = Role::firstOrCreate(
            ['slug' => 'gerente'],
            ['nombre' => 'Gerente', 'descripcion' => 'Gerente del restaurante']
        );
        $rolMesero = Role::firstOrCreate(
            ['slug' => 'mesero'],
            ['nombre' => 'Mesero', 'descripcion' => 'Atención en mesas']
        );

        $sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'SUC-01'],
            ['nombre' => 'Sucursal Principal', 'direccion' => 'Calle 10 # 40-20', 'activa' => true]
        );

        $this->gerente = User::create([
            'name' => 'Gerente Test',
            'email' => 'gerente.menu@sushixpress.com',
            'telefono' => '3001112233',
            'role_id' => $rolGerente->id,
            'sucursal_id' => $sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero Test',
            'email' => 'mesero.menu@sushixpress.com',
            'telefono' => '3004445566',
            'role_id' => $rolMesero->id,
            'sucursal_id' => $sucursal->id,
            'password' => bcrypt('password123'),
            'activo' => true,
        ]);

        $this->categoria = Categoria::create([
            'nombre' => 'Maki Rolls',
            'slug' => 'maki-rolls',
            'icono' => '🍣',
            'orden' => 1,
            'activo' => true,
        ]);
    }

    public function test_gerente_puede_crear_categoria_y_plato(): void
    {
        $this->actingAs($this->gerente);

        Volt::test('menu.index')
            ->call('abrirNuevaCategoria')
            ->assertSet('mostrarModalCategoria', true)
            ->set('categoriaForm.nombre', 'Nigiris Especiales')
            ->set('categoriaForm.icono', '🍣')
            ->set('categoriaForm.orden', 2)
            ->call('guardarCategoria')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias', [
            'nombre' => 'Nigiris Especiales',
        ]);

        Volt::test('menu.index')
            ->call('abrirNuevoProducto', $this->categoria->id)
            ->assertSet('mostrarModalProducto', true)
            ->set('productoForm.categoria_id', $this->categoria->id)
            ->set('productoForm.nombre', 'Salmón Skin Roll')
            ->set('productoForm.precio', 35000)
            ->set('productoForm.costo', 12000)
            ->set('productoForm.area_cocina', 'sushi')
            ->call('guardarProducto')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Salmón Skin Roll',
            'categoria_id' => $this->categoria->id,
        ]);
    }

    public function test_gerente_puede_desactivar_plato(): void
    {
        $this->actingAs($this->gerente);

        $producto = Producto::create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'Tempura Roll',
            'slug' => 'tempura-roll',
            'precio' => 28000,
            'costo' => 9000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        Volt::test('menu.index')
            ->call('toggleProducto', $producto->id)
            ->assertHasNoErrors();

        $this->assertFalse($producto->fresh()->activo);
    }

    public function test_mesero_no_puede_crear_platos_y_recibe_403(): void
    {
        $this->actingAs($this->mesero);

        Volt::test('menu.index')
            ->call('abrirNuevoProducto')
            ->assertStatus(403);
    }
}
