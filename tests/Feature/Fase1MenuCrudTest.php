<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase1MenuCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $admin;

    private User $mesero;

    private MenuService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->gerente = User::factory()->create(['role_id' => Role::where('slug', 'gerente')->value('id')]);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->mesero = User::factory()->create(['role_id' => Role::where('slug', 'mesero')->value('id')]);

        $this->service = app(MenuService::class);
    }

    public function test_pantalla_menu_solo_disponible_para_gerente_y_admin(): void
    {
        $this->actingAs($this->mesero)->get(route('menu'))->assertForbidden();

        $this->actingAs($this->gerente)->get(route('menu'))->assertOk();
        $this->actingAs($this->gerente)->get(route('menu'))->assertSeeVolt('menu.index');

        $this->actingAs($this->admin)->get(route('menu'))->assertOk();
        $this->actingAs($this->admin)->get(route('menu'))->assertSeeVolt('menu.index');
    }

    public function test_crear_categoria_genera_slug_icono_y_orden(): void
    {
        $categoria = $this->service->crearCategoria([
            'nombre' => 'Rollos Especiales',
            'icono' => '🍱',
            'orden' => 2,
        ]);

        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'nombre' => 'Rollos Especiales',
            'slug' => 'rollos-especiales',
            'icono' => '🍱',
            'orden' => 2,
            'activo' => true,
        ]);
    }

    public function test_crear_categoria_rechaza_slug_duplicado(): void
    {
        Categoria::create(['nombre' => 'Entradas', 'slug' => 'entradas']);

        $this->expectException(InvalidArgumentException::class);

        $this->service->crearCategoria(['nombre' => 'Entradas', 'icono' => '🥟', 'orden' => 1]);
    }

    public function test_actualizar_categoria_recalcula_slug(): void
    {
        $categoria = Categoria::create(['nombre' => 'Entradas', 'slug' => 'entradas']);

        $this->service->actualizarCategoria($categoria, ['nombre' => 'Entradas Fritas', 'orden' => 5]);

        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'nombre' => 'Entradas Fritas',
            'slug' => 'entradas-fritas',
            'orden' => 5,
        ]);
    }

    public function test_desactivar_categoria_no_la_elimina(): void
    {
        $categoria = Categoria::create(['nombre' => 'Temakis', 'slug' => 'temakis']);

        $this->service->desactivarCategoria($categoria);

        $this->assertSame(false, (bool) $categoria->fresh()->activo);
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'activo' => false]);
    }

    public function test_crear_producto_valida_categoria_precio_y_area(): void
    {
        $categoria = Categoria::create(['nombre' => 'Nigiris', 'slug' => 'nigiris']);

        $producto = $this->service->crearProducto([
            'categoria_id' => $categoria->id,
            'nombre' => 'Nigiri Salmón',
            'descripcion' => 'Salmón fresco sobre shari',
            'precio' => 12000,
            'costo' => 4500,
            'area_cocina' => 'sushi',
        ]);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'categoria_id' => $categoria->id,
            'nombre' => 'Nigiri Salmón',
            'slug' => 'nigiri-salmon',
            'precio' => 12000,
            'costo' => 4500,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_crear_producto_rechaza_precio_no_positivo(): void
    {
        $categoria = Categoria::create(['nombre' => 'Nigiris', 'slug' => 'nigiris']);

        $this->expectException(InvalidArgumentException::class);

        $this->service->crearProducto([
            'categoria_id' => $categoria->id,
            'nombre' => 'Nigiri Inválido',
            'precio' => 0,
            'area_cocina' => 'sushi',
        ]);
    }

    public function test_actualizar_producto_actualiza_precio_y_area(): void
    {
        $categoria = Categoria::create(['nombre' => 'Sashimis', 'slug' => 'sashimis']);
        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Sashimi Tuna',
            'slug' => 'sashimi-tuna',
            'precio' => 15000,
            'area_cocina' => 'sushi',
        ]);

        $this->service->actualizarProducto($producto, [
            'nombre' => 'Sashimi Tuna Prime',
            'precio' => 18000,
            'area_cocina' => 'barra',
        ]);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'nombre' => 'Sashimi Tuna Prime',
            'slug' => 'sashimi-tuna-prime',
            'precio' => 18000,
            'area_cocina' => 'barra',
        ]);
    }

    public function test_desactivar_producto_lo_oculta_de_catalogo_activo(): void
    {
        $categoria = Categoria::create(['nombre' => 'Postres', 'slug' => 'postres']);
        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Mochi',
            'slug' => 'mochi',
            'precio' => 8000,
            'area_cocina' => 'barra',
        ]);

        $this->service->desactivarProducto($producto);

        $this->assertSame(false, (bool) $producto->fresh()->activo);
        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'activo' => false]);
        $this->assertCount(0, $categoria->fresh()->productos()->get());
    }

    public function test_componente_puede_crear_categoria_desde_ui_solo_admin(): void
    {
        // Mesero no puede guardar categoría
        Volt::actingAs($this->mesero)
            ->test('menu.index')
            ->set('categoriaForm.nombre', 'Combos')
            ->call('guardarCategoria')
            ->assertStatus(403);

        // Admin sí puede crear categoría
        Volt::actingAs($this->admin)
            ->test('menu.index')
            ->set('mostrarModalCategoria', true)
            ->set('categoriaForm.nombre', 'Combos')
            ->set('categoriaForm.icono', '🍱')
            ->set('categoriaForm.orden', 9)
            ->call('guardarCategoria')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias', ['nombre' => 'Combos', 'slug' => 'combos']);
    }

    public function test_componente_puede_crear_producto_desde_ui_solo_admin(): void
    {
        $categoria = Categoria::create(['nombre' => 'Rolls Especiales', 'slug' => 'rolls-especiales']);

        // Mesero no puede guardar producto
        Volt::actingAs($this->mesero)
            ->test('menu.index')
            ->set('productoForm.categoria_id', $categoria->id)
            ->set('productoForm.nombre', 'Dragon Roll Imperial')
            ->set('productoForm.precio', 45000)
            ->call('guardarProducto')
            ->assertStatus(403);

        // Admin sí puede crear producto
        Volt::actingAs($this->admin)
            ->test('menu.index')
            ->call('abrirNuevoProducto', $categoria->id)
            ->assertSet('mostrarModalProducto', true)
            ->set('productoForm.categoria_id', $categoria->id)
            ->set('productoForm.nombre', 'Dragon Roll Imperial')
            ->set('productoForm.descripcion', 'Anguila, aguacate, masago y salsa teriyaki artesanal')
            ->set('productoForm.precio', 45000)
            ->set('productoForm.costo', 16500)
            ->set('productoForm.area_cocina', 'sushi')
            ->call('guardarProducto')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Dragon Roll Imperial',
            'categoria_id' => $categoria->id,
            'precio' => 45000,
            'costo' => 16500,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_reactivar_producto_y_categoria(): void
    {
        $categoria = Categoria::create(['nombre' => 'Bebidas', 'slug' => 'bebidas', 'activo' => false]);
        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Limonada de Coco',
            'slug' => 'limonada-de-coco',
            'precio' => 12000,
            'area_cocina' => 'barra',
            'activo' => false,
        ]);

        $this->service->activarCategoria($categoria);
        $this->assertTrue((bool) $categoria->fresh()->activo);

        $this->service->activarProducto($producto);
        $this->assertTrue((bool) $producto->fresh()->activo);
    }

    public function test_pos_barra_navegacion_categorias_y_boton_crear_producto_solo_admin(): void
    {
        // Admin ve la barra de navegación con flechas y el botón de crear producto
        $this->actingAs($this->admin)
            ->get(route('pos'))
            ->assertOk()
            ->assertSee('id="btnCatNavLeft"', false)
            ->assertSee('id="btnCatNavRight"', false)
            ->assertSee('id="btnPosCrearProductoAdmin"', false);

        // Gerente ve la barra de categorías y el botón de crear producto (ahora habilitado)
        $this->actingAs($this->gerente)
            ->get(route('pos'))
            ->assertOk()
            ->assertSee('id="btnCatNavLeft"', false)
            ->assertSee('id="btnCatNavRight"', false)
            ->assertSee('id="btnPosCrearProductoAdmin"', false);

        // Mesero ve la barra de categorías pero el botón de crear producto es INVISIBLE
        $this->actingAs($this->mesero)
            ->get(route('pos'))
            ->assertOk()
            ->assertSee('id="btnCatNavLeft"', false)
            ->assertSee('id="btnCatNavRight"', false)
            ->assertDontSee('id="btnPosCrearProductoAdmin"', false);
    }

    public function test_modal_categoria_muestra_selector_de_iconos_y_frecuentes(): void
    {
        Volt::actingAs($this->admin)
            ->test('menu.index')
            ->call('abrirNuevaCategoria')
            ->assertSet('mostrarModalCategoria', true)
            ->assertSee('Frecuentes:')
            ->assertSee('Parrilla & Carnes', false)
            ->assertSee('Wok & Ramen', false)
            ->assertSee('Bebidas & Bar', false)
            ->assertSee('Postres & Dulces', false)
            ->set('categoriaForm.nombre', 'Ramen Especial')
            ->set('categoriaForm.icono', '🍜')
            ->set('categoriaForm.orden', 5)
            ->call('guardarCategoria')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias', [
            'nombre' => 'Ramen Especial',
            'icono' => '🍜',
            'orden' => 5,
        ]);
    }
}
