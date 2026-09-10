<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionesRbacTest extends TestCase
{
    use RefreshDatabase;

    private NotificacionService $notificacionService;

    private Sucursal $sucursal;

    private User $admin;

    private User $mesero;

    private User $cocinero;

    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificacionService = app(NotificacionService::class);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        $roleCocina = Role::create(['nombre' => 'Cocina', 'slug' => 'cocina']);
        $roleCaja = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero User',
            'email' => 'mesero@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->cocinero = User::create([
            'name' => 'Cocinero User',
            'email' => 'cocina@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleCocina->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->cajero = User::create([
            'name' => 'Cajero User',
            'email' => 'cajero@test.com',
            'password' => bcrypt('password123'),
            'role_id' => $roleCaja->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    public function test_restricciones_notificaciones_segun_rol_usuario(): void
    {
        // 1. Insumo con stock crítico (<= stock_minimo)
        Insumo::create([
            'codigo' => 'INS-SALMON-01',
            'nombre' => 'Salmón Fresco Chileno',
            'categoria' => 'Pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 2.0,
            'stock_minimo' => 5.0,
            'costo_unitario' => 35000,
            'activo' => true,
        ]);

        // 2. Mesa y pedido QR pendiente de asignación
        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M10',
            'capacidad' => 4,
            'estado' => 'libre',
        ]);

        Pedido::create([
            'codigo' => 'PED-QR-001',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'usuario_id' => null,
            'tipo' => 'mesa',
            'estado' => 'solicitado_qr',
            'canal_origen' => 'qr_mesa',
            'subtotal' => 45000,
            'total' => 45000,
        ]);

        // 3. Plato listo en cocina
        $cat = Categoria::create(['nombre' => 'Rolls', 'slug' => 'rolls', 'activa' => true]);
        $prod = Producto::create([
            'nombre' => 'Salmón Roll',
            'slug' => 'salmon-roll',
            'categoria_id' => $cat->id,
            'precio' => 45000,
            'activo' => true,
        ]);

        $pedidoActivo = Pedido::create([
            'codigo' => 'PED-ACT-002',
            'sucursal_id' => $this->sucursal->id,
            'mesa_id' => $mesa->id,
            'usuario_id' => $this->mesero->id,
            'tipo' => 'mesa',
            'estado' => 'en_preparacion',
            'subtotal' => 45000,
            'total' => 45000,
        ]);

        ItemPedido::create([
            'pedido_id' => $pedidoActivo->id,
            'producto_id' => $prod->id,
            'nombre_producto' => $prod->nombre,
            'cantidad' => 1,
            'precio_unitario' => 45000,
            'subtotal' => 45000,
            'total' => 45000,
            'estado_cocina' => 'listo',
            'listo_en' => now(),
        ]);

        // 4. Reserva para hoy
        $cliente = Cliente::create(['nombre' => 'Cliente VIP', 'telefono' => '3001112233']);
        Reserva::create([
            'sucursal_id' => $this->sucursal->id,
            'cliente_id' => $cliente->id,
            'nombre_contacto' => 'Cliente VIP',
            'telefono_contacto' => '3001112233',
            'fecha' => Carbon::today()->toDateString(),
            'hora_llegada' => '19:00',
            'personas' => 2,
            'estado' => 'confirmada',
        ]);

        // VERIFICACIÓN 1: Administrador recibe las 4 categorías (Total = 4)
        $resAdmin = $this->notificacionService->obtenerResumen($this->admin);
        $this->assertCount(1, $resAdmin['stock_critico']);
        $this->assertCount(1, $resAdmin['pedidos_qr']);
        $this->assertCount(1, $resAdmin['platos_listos']);
        $this->assertCount(1, $resAdmin['reservas_hoy']);
        $this->assertEquals(4, $resAdmin['total']);

        // VERIFICACIÓN 2: Mesero SOLO recibe pedidos QR y platos listos (NO stock, NO reservas, Total = 2)
        $resMesero = $this->notificacionService->obtenerResumen($this->mesero);
        $this->assertCount(1, $resMesero['pedidos_qr']);
        $this->assertCount(1, $resMesero['platos_listos']);
        $this->assertCount(0, $resMesero['stock_critico']);
        $this->assertCount(0, $resMesero['reservas_hoy']);
        $this->assertEquals(2, $resMesero['total']);

        // VERIFICACIÓN 3: Cocina SOLO recibe stock crítico (NO pedidos QR, NO platos listos, Total = 1)
        $resCocina = $this->notificacionService->obtenerResumen($this->cocinero);
        $this->assertCount(1, $resCocina['stock_critico']);
        $this->assertCount(0, $resCocina['pedidos_qr']);
        $this->assertCount(0, $resCocina['platos_listos']);
        $this->assertCount(0, $resCocina['reservas_hoy']);
        $this->assertEquals(1, $resCocina['total']);

        // VERIFICACIÓN 4: Cajero recibe pedidos QR, platos listos y reservas (NO stock crítico, Total = 3)
        $resCajero = $this->notificacionService->obtenerResumen($this->cajero);
        $this->assertCount(1, $resCajero['pedidos_qr']);
        $this->assertCount(1, $resCajero['platos_listos']);
        $this->assertCount(1, $resCajero['reservas_hoy']);
        $this->assertCount(0, $resCajero['stock_critico']);
        $this->assertEquals(3, $resCajero['total']);
    }
}
