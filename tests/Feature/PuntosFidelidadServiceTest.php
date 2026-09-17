<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MenuService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PuntosFidelidadServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearSucursal(string $codigo): Sucursal
    {
        return Sucursal::create([
            'nombre' => 'Sucursal '.$codigo,
            'codigo' => $codigo,
            'direccion' => 'Calle 1',
            'activa' => true,
        ]);
    }

    private function crearUsuario(string $slugRol, int $sucursalId): User
    {
        $role = Role::firstOrCreate(['slug' => $slugRol], [
            'nombre' => ucfirst($slugRol),
            'descripcion' => 'Rol '.$slugRol,
        ]);

        return User::create([
            'name' => 'Usuario '.$slugRol.' '.uniqid(),
            'email' => $slugRol.'_'.uniqid().'@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id,
            'sucursal_id' => $sucursalId,
            'activo' => true,
        ]);
    }

    public function test_descuento_puntos_sin_canje_rechazado(): void
    {
        $s = $this->crearSucursal('PTS');
        $admin = $this->crearUsuario('admin', $s->id);
        $cliente = Cliente::create(['nombre' => 'P', 'telefono' => '3009998877', 'activo' => true, 'puntos_fidelidad' => 500]);

        $menu = app(MenuService::class);
        $cat = $menu->crearCategoria(['nombre' => 'P', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
        $prod = $menu->crearProducto(['categoria_id' => $cat->id, 'nombre' => 'P', 'precio' => 50000, 'costo' => 5000, 'area_cocina' => 'sushi', 'activo' => true]);
        $items = [['producto_id' => $prod->id, 'cantidad' => 1]];

        try {
            app(PedidoService::class)->crearPedido([
                'tipo' => 'mesa',
                'sucursal_id' => $s->id,
                'cliente_id' => $cliente->id,
                'descuento_puntos' => 25000,
                'puntos_canjeados' => 0,
            ], $items, $admin);
            $this->fail('No debe aplicarse descuento de puntos sin canjear puntos.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('puntos', $e->getMessage());
        }
    }
}
