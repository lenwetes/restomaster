<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MenuService;
use App\Services\PedidoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacSucursalTest extends TestCase
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

    public function test_mesero_no_puede_asignar_pedido_qr_de_otra_sucursal(): void
    {
        $sA = $this->crearSucursal('QRA');
        $sB = $this->crearSucursal('QRB');
        $meseroA = $this->crearUsuario('mesero', $sA->id);
        $meseroB = $this->crearUsuario('mesero', $sB->id);

        $mesa = Mesa::create(['numero' => '1', 'zona' => 'salon', 'capacidad' => 4, 'sucursal_id' => $sB->id, 'estado' => 'libre']);

        $menu = app(MenuService::class);
        $categoria = $menu->crearCategoria(['nombre' => 'Q', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
        $producto = $menu->crearProducto([
            'categoria_id' => $categoria->id,
            'nombre' => 'Qr Item',
            'precio' => 10000.00,
            'costo' => 3000.00,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
        $pedidoQr = app(PedidoService::class)->crearPedido([
            'tipo' => 'mesa',
            'mesa_id' => $mesa->id,
            'sucursal_id' => $sB->id,
            'canal_origen' => 'qr_mesa',
            'estado' => 'solicitado_qr',
            'usuario_id' => null,
        ], [['producto_id' => $producto->id, 'cantidad' => 1]]);

        $this->expectException(AuthorizationException::class);
        app(PedidoService::class)->asignarMeseroAPedidoQr($pedidoQr->id, $meseroA);
    }
}
