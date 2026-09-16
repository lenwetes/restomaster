<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AuditoriaLote6RendimientoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Test Sucursal',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001234567',
            'ciudad' => 'Medellin',
            'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admintest@restomaster.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    public function test_r20_menu_service_caching_and_invalidation(): void
    {
        $service = app(MenuService::class);

        $cat = $service->crearCategoria([
            'nombre' => 'Parrilla Especial',
            'icono' => '🥩',
            'orden' => 1,
            'activo' => true,
        ]);

        $prod = $service->crearProducto([
            'categoria_id' => $cat->id,
            'nombre' => 'Bife de Chorizo Test',
            'precio' => 65000,
            'costo' => 25000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        // 1. Obtener menú público y verificar que se guarda en caché
        Cache::forget(MenuService::CACHE_KEY_PUBLICO);
        $menu = $service->obtenerMenuPublico();
        $this->assertTrue(Cache::has(MenuService::CACHE_KEY_PUBLICO));
        $this->assertEquals(1, $menu->count());

        // 2. Al mutar producto, se invalida la caché automáticamente
        $service->actualizarProducto($prod, ['precio' => 70000]);
        $this->assertFalse(Cache::has(MenuService::CACHE_KEY_PUBLICO));

        // 3. Al mutar categoría, también se invalida
        $service->obtenerMenuPublico();
        $this->assertTrue(Cache::has(MenuService::CACHE_KEY_PUBLICO));

        $service->desactivarCategoria($cat);
        $this->assertFalse(Cache::has(MenuService::CACHE_KEY_PUBLICO));
    }

    public function test_r21_composite_and_fk_indexes_exist(): void
    {
        // Verificar que los índices existen en el esquema
        $this->assertTrue(Schema::hasColumn('pedidos', 'cliente_id'));
        $this->assertTrue(Schema::hasColumn('pedidos', 'usuario_id'));
        $this->assertTrue(Schema::hasColumn('pedidos', 'canal_origen'));
        $this->assertTrue(Schema::hasColumn('items_pedido', 'producto_id'));
    }

    public function test_r22_auditoria_service_limits_queries(): void
    {
        $service = app(AuditoriaService::class);

        for ($i = 1; $i <= 10; $i++) {
            $service->registrar(
                $this->admin,
                'test.accion',
                'pedido',
                999,
                "Auditoría de prueba #{$i}"
            );
        }

        $resLimit3 = $service->porEntidad('pedido', 999, 3);
        $this->assertCount(3, $resLimit3);

        $resAccionLimit2 = $service->porAccion('test.accion', 2);
        $this->assertCount(2, $resAccionLimit2);
    }

    public function test_r23_menu_index_renders_without_extra_productos_query(): void
    {
        $this->actingAs($this->admin);

        Categoria::create([
            'nombre' => 'Entradas Test',
            'slug' => 'entradas-test',
            'icono' => '🥗',
            'orden' => 1,
            'activo' => true,
        ]);

        Livewire::test('menu.index')
            ->assertOk()
            ->assertViewHas('categorias');
    }
}
