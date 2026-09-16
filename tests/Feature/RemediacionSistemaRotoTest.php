<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class RemediacionSistemaRotoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Prueba',
            'direccion' => 'Calle 1 # 1-1',
            'telefono' => '3001111111',
            'ciudad' => 'Medellin',
            'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Sistema',
            'email' => 'admin.sistema@restomaster.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    private function crearCategoriaConProductos(): Categoria
    {
        $menuService = app(MenuService::class);

        $categoria = $menuService->crearCategoria([
            'nombre' => 'Parrilla',
            'icono' => '🥩',
            'orden' => 1,
            'activo' => true,
        ]);

        $menuService->crearProducto([
            'categoria_id' => $categoria->id,
            'nombre' => 'Bife de Chorizo',
            'precio' => 65000,
            'costo' => 25000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        return $categoria;
    }

    private function crearMesaConPedidoActivo(): Mesa
    {
        $mesa = Mesa::create([
            'numero' => '5',
            'zona' => 'salon',
            'capacidad' => 4,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'ocupada',
        ]);

        $menuService = app(MenuService::class);
        $producto = $menuService->crearProducto([
            'categoria_id' => $this->crearCategoriaConProductos()->id,
            'nombre' => 'Nigiri Salmón',
            'precio' => 12000,
            'costo' => 4000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'P-ACT-001',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'mesa_id' => $mesa->id,
            'usuario_id' => $this->admin->id,
            'subtotal' => 12000,
            'descuento' => 0,
            'total' => 12000,
            'canal_origen' => 'pos',
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => 'Nigiri Salmón',
            'cantidad' => 1,
            'precio_unitario' => 12000,
            'subtotal' => 12000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        return $mesa;
    }

    public function test_menu_publico_no_rompe_con_cache_database(): void
    {
        config(['cache.default' => 'database']);
        Cache::forget(MenuService::CACHE_KEY_PUBLICO);

        $service = app(MenuService::class);
        $this->crearCategoriaConProductos();

        $primera = $service->obtenerMenuPublico();
        $segunda = $service->obtenerMenuPublico();

        $this->assertInstanceOf(Collection::class, $segunda);
        $this->assertGreaterThanOrEqual(1, $segunda->count());
        $this->assertTrue(Cache::has(MenuService::CACHE_KEY_PUBLICO));
        $this->assertIsArray(Cache::get(MenuService::CACHE_KEY_PUBLICO));
    }

    public function test_terminal_no_rompe_con_cache_database(): void
    {
        config(['cache.default' => 'database']);
        Cache::forget('pos.terminal.categorias');
        Cache::forget('pos.terminal.mesas');

        $this->crearMesaConPedidoActivo();

        Livewire::actingAs($this->admin)->test('pos.terminal')->assertOk();
        Livewire::actingAs($this->admin)->test('pos.terminal')->assertOk();

        $this->assertIsArray(Cache::get('pos.terminal.categorias'));
        $this->assertIsArray(Cache::get('pos.terminal.mesas'));
    }

    public function test_mesas_index_renderiza_con_pedido_activo_sin_lazy_load(): void
    {
        $this->crearMesaConPedidoActivo();

        Livewire::actingAs($this->admin)
            ->test('mesas.index')
            ->assertOk();
    }

    public function test_kds_renderiza_con_usuario_de_sucursal(): void
    {
        $this->crearMesaConPedidoActivo();

        Livewire::actingAs($this->admin)
            ->test('cocina.kds')
            ->assertOk();
    }
}
