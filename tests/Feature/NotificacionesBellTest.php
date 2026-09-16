<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\NotificacionService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificacionesBellTest extends TestCase
{
    use RefreshDatabase;

    private User $meseroA;

    private User $meseroB;

    private Mesa $mesa;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->meseroA = User::factory()->create([
            'name' => 'Carlos Mesero',
            'email' => 'carlos@sushixpress.com',
            'role_id' => $roleMesero->id,
        ]);

        $this->meseroB = User::factory()->create([
            'name' => 'Andres Servicio',
            'email' => 'andres@sushixpress.com',
            'role_id' => $roleMesero->id,
        ]);

        $sucursal = Sucursal::create([
            'nombre' => 'Sushixpress Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $sucursal->id,
            'numero' => '2',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'ocupada',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Maki Rolls',
            'slug' => 'maki-rolls',
            'icono' => '🍣',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Filadelfia Roll',
            'slug' => 'filadelfia-roll',
            'precio' => 30000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_reloj_topbar_esta_configurado_en_formato_12_horas(): void
    {
        $navigationContent = file_get_contents(resource_path('views/livewire/layout/navigation.blade.php'));
        $this->assertStringContainsString('hour12: true', $navigationContent);
    }

    public function test_servicio_no_cachea_colecciones_eloquent_en_el_resumen(): void
    {
        $notificacionService = app(NotificacionService::class);
        $notificacionService->obtenerResumen($this->meseroA);

        $cacheKey = 'notif.resumen.'.$this->meseroA->id.'.'.$this->meseroA->role_id;
        $cached = Cache::get($cacheKey);

        $this->assertNull(
            $cached,
            'El resumen no debe persistirse en caché: serializar colecciones Eloquent '
            .'en el store de base de datos produce objetos incompletos (__PHP_Incomplete_Class) al hidratarse.'
        );
    }

    public function test_servicio_notificaciones_detecta_pedidos_qr_pendientes(): void
    {
        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedidoDesdeQr($this->mesa, [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
                'precio_unitario' => 30000,
            ],
        ], 'Cliente QR');

        $notificacionService = app(NotificacionService::class);
        $resumen = $notificacionService->obtenerResumen($this->meseroA);

        $this->assertGreaterThanOrEqual(1, $resumen['total']);
        $this->assertTrue($resumen['pedidos_qr']->contains('id', $pedido->id));
    }

    public function test_mesero_puede_atender_pedido_qr_desde_campana_de_notificaciones(): void
    {
        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedidoDesdeQr($this->mesa, [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
                'precio_unitario' => 30000,
            ],
        ], 'Cliente Mesa 2');

        $this->actingAs($this->meseroA);

        Volt::test('layout.navigation')
            ->call('atenderPedidoQr', $pedido->id)
            ->assertSet('tipoNotificacionFlash', 'success')
            ->assertDispatched('notificacion');

        $this->assertEquals($this->meseroA->id, $pedido->fresh()->usuario_id);
        $this->assertEquals('en_cocina', $pedido->fresh()->estado);
    }

    public function test_mesero_recibe_aviso_en_campana_si_pedido_ya_fue_tomado_por_otro_mesero(): void
    {
        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedidoDesdeQr($this->mesa, [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
                'precio_unitario' => 30000,
            ],
        ], 'Cliente Mesa 2');

        // Mesero A toma el pedido primero
        $pedidoService->asignarMeseroAPedidoQr($pedido->id, $this->meseroA);

        // Mesero B intenta tomar el mismo pedido desde su campana de notificaciones
        $this->actingAs($this->meseroB);

        Volt::test('layout.navigation')
            ->call('atenderPedidoQr', $pedido->id)
            ->assertSet('tipoNotificacionFlash', 'warning')
            ->assertSee('ya fue tomado por Carlos Mesero');
    }
}
