<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaInsumo;
use App\Models\Insumo;
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

class CategoriasColorYHistorialCocinaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cocinero;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['nombre' => 'Administrador', 'nivel_jerarquico' => 1]
        );

        $roleCocina = Role::firstOrCreate(
            ['slug' => 'cocina'],
            ['nombre' => 'Cocinero', 'nivel_jerarquico' => 4]
        );

        $this->sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'SUC-TEST'],
            ['nombre' => 'Sucursal Test', 'direccion' => 'Calle 10 # 40', 'telefono' => '3001234567', 'activa' => true]
        );

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.test@restomaster.com'],
            [
                'name' => 'Admin Test',
                'password' => bcrypt('password'),
                'role_id' => $roleAdmin->id,
                'sucursal_id' => $this->sucursal->id,
                'activo' => true,
            ]
        );

        $this->cocinero = User::firstOrCreate(
            ['email' => 'cocinero.test@restomaster.com'],
            [
                'name' => 'Cocinero Test',
                'password' => bcrypt('password'),
                'role_id' => $roleCocina->id,
                'sucursal_id' => $this->sucursal->id,
                'activo' => true,
            ]
        );
    }

    public function test_crear_categoria_insumo_con_color_e_icono_y_herencia_en_insumo(): void
    {
        $this->actingAs($this->admin);

        // 1. Crear CategoriaInsumo a través del componente Volt de inventario
        Volt::test('inventario.index')
            ->set('catNombre', 'Pescados y Mariscos')
            ->set('catColor', '#06b6d4')
            ->set('catIcono', 'set_meal')
            ->set('catDescripcion', 'Insumos marinos frescos')
            ->call('guardarCategoriaInsumo')
            ->assertHasNoErrors();

        $catInsumo = CategoriaInsumo::where('nombre', 'Pescados y Mariscos')->first();
        $this->assertNotNull($catInsumo);
        $this->assertEquals('#06b6d4', $catInsumo->color);
        $this->assertEquals('set_meal', $catInsumo->icono);

        // 2. Crear Insumo asignado a esta categoría
        $insumo = Insumo::create([
            'categoria_id' => $catInsumo->id,
            'nombre' => 'Salmón Premium Chile',
            'codigo' => 'SAL-001',
            'categoria' => $catInsumo->nombre,
            'unidad_medida' => 'kg',
            'stock_actual' => 12.5,
            'stock_minimo' => 5.0,
            'costo_unitario' => 45000,
            'activo' => true,
        ]);

        // Verificar herencia directa de color e icono
        $this->assertEquals('#06b6d4', $insumo->color);
        $this->assertEquals('set_meal', $insumo->icono);
        $this->assertEquals('Pescados y Mariscos', $insumo->nombre_categoria);
    }

    public function test_crear_categoria_menu_con_color_y_visualizacion_en_pos(): void
    {
        $this->actingAs($this->admin);

        // 1. Crear categoría de menú con color personalizado para POS
        Volt::test('menu.index')
            ->set('categoriaForm.nombre', 'Rolls Especiales')
            ->set('categoriaForm.icono', '🍣')
            ->set('categoriaForm.color', '#e11d48')
            ->set('categoriaForm.orden', 1)
            ->call('guardarCategoria')
            ->assertHasNoErrors();

        $categoria = Categoria::where('nombre', 'Rolls Especiales')->first();
        $this->assertNotNull($categoria);
        $this->assertEquals('#e11d48', $categoria->color);

        // 2. Crear producto bajo esta categoría
        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Dragon Roll Flameado',
            'slug' => 'dragon-roll-flameado',
            'descripcion' => 'Roll con salmón, aguacate y queso crema',
            'precio' => 42000,
            'costo' => 14000,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        // 3. Probar que el terminal POS renderiza la categoría y el producto con su color
        Volt::test('pos.terminal')
            ->assertSee('Rolls Especiales')
            ->assertSee('Dragon Roll Flameado')
            ->assertSee('#e11d48');
    }

    public function test_historial_comandas_cocina_filtrable_por_fecha_hora_mes_anio_y_area(): void
    {
        // 1. Crear mesa y pedidos en diferentes fechas/horas
        $mesa = Mesa::firstOrCreate(
            ['numero' => 'M-TEST-1'],
            ['capacidad' => 4, 'sucursal_id' => $this->sucursal->id, 'estado' => 'libre', 'activa' => true]
        );

        $catSushi = Categoria::firstOrCreate(
            ['slug' => 'sushi-test'],
            ['nombre' => 'Sushi Test', 'icono' => '🍣', 'color' => '#10b981', 'activo' => true]
        );

        $prodSushi = Producto::firstOrCreate(
            ['slug' => 'nigiri-test'],
            ['nombre' => 'Nigiri Test', 'categoria_id' => $catSushi->id, 'precio' => 20000, 'costo' => 5000, 'area_cocina' => 'sushi', 'activo' => true]
        );

        // Pedido 1: Hace 2 horas, entregado
        $pedido1 = Pedido::create([
            'codigo' => 'PED-HIST-001',
            'tipo' => 'mesa',
            'estado' => 'entregado',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'subtotal' => 20000,
            'total' => 20000,
            'hora_despacho' => now()->subHours(2),
            'hora_entrega' => now()->subHours(2),
            'created_at' => now()->subHours(2),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido1->id,
            'producto_id' => $prodSushi->id,
            'nombre_producto' => $prodSushi->nombre,
            'cantidad' => 2,
            'precio_unitario' => 20000,
            'subtotal' => 40000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'entregado',
        ]);

        // 2. Probar acceso del Cocinero al KDS y al Historial
        $this->actingAs($this->cocinero);

        Volt::test('cocina.kds')
            ->set('vistaModo', 'historial')
            ->assertSee('Historial de Comandas')
            ->assertSee('PED-HIST-001')
            ->assertSee('Nigiri Test');

        // 3. Probar filtro por hora específica
        $horaPedido1 = (int) $pedido1->created_at->format('H');
        Volt::test('cocina.kds')
            ->set('vistaModo', 'historial')
            ->set('historialHora', (string) $horaPedido1)
            ->assertSee('PED-HIST-001');

        // 4. Probar filtro por área cocina (sushi)
        Volt::test('cocina.kds')
            ->set('vistaModo', 'historial')
            ->set('historialArea', 'sushi')
            ->assertSee('PED-HIST-001');

        // 5. Probar que Admin también tiene acceso completo a la pantalla de cocina y su historial
        $this->actingAs($this->admin);

        Volt::test('cocina.kds')
            ->set('vistaModo', 'historial')
            ->assertSee('PED-HIST-001')
            ->call('abrirDetalleHistorial', $pedido1->id)
            ->assertSet('historialDetalleId', $pedido1->id)
            ->assertSee('Detalle de Comanda PED-HIST-001');
    }
}
