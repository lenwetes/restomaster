<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\QrCodeService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MesaQrAutopedidoTest extends TestCase
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
            'numero' => '4',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'libre',
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
            'nombre' => 'Ojo de Tigre Roll',
            'slug' => 'ojo-de-tigre-roll',
            'precio' => 32000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_ruta_corta_qr_redirige_a_menu_de_mesa(): void
    {
        $response = $this->get('/m/4');
        $response->assertRedirect(route('mesa.menu', ['numero' => '4']));
    }

    public function test_menu_publico_carga_correctamente_con_datos_de_mesa_y_precios_en_cop(): void
    {
        $response = $this->get('/mesa/4/menu');
        $response->assertStatus(200);
        $response->assertSee('Mesa #4');
        $response->assertSee('Ojo de Tigre Roll');
        $response->assertSee('32.000');
        $response->assertSee('COP');
    }

    public function test_comensal_puede_crear_pedido_sin_mesero_presente(): void
    {
        Volt::test('mesa.menu-publico', ['numero' => '4'])
            ->call('agregarProducto', $this->producto->id)
            ->set('nombreCliente', 'Mateo Gómez')
            ->set('notasGenerales', 'Salsa de soya extra por favor')
            ->call('enviarPedido')
            ->assertSet('tipoFlash', 'success');

        $this->assertDatabaseHas('pedidos', [
            'mesa_id' => $this->mesa->id,
            'canal_origen' => 'qr_mesa',
            'estado' => 'solicitado_qr',
            'usuario_id' => null,
            'nombre_cliente' => 'Mateo Gómez',
            'total' => 32000,
        ]);

        $this->assertEquals('ocupada', $this->mesa->fresh()->estado);

        $pedido = Pedido::where('mesa_id', $this->mesa->id)->first();
        $this->assertCount(1, $pedido->items);
        $this->assertEquals('pendiente', $pedido->items->first()->estado_cocina);
    }

    public function test_pantalla_de_seguimiento_en_vivo_muestra_estados_del_pedido(): void
    {
        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedidoDesdeQr($this->mesa, [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'precio_unitario' => 32000,
            ],
        ], 'Cliente Prueba');

        session()->put("pedido_qr_{$this->mesa->id}", $pedido->id);

        $test = Volt::test('mesa.menu-publico', ['numero' => '4']);
        $test->assertSee('Mesa #4');
        $test->assertSee('Esperando asignación de mesero');
        $test->assertSee($pedido->codigo);
    }

    public function test_mesero_puede_asignarse_pedido_qr_y_se_envia_a_cocina(): void
    {
        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedidoDesdeQr($this->mesa, [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
                'precio_unitario' => 32000,
            ],
        ], 'Comensal Mesa 4');

        $this->assertNull($pedido->usuario_id);
        $this->assertEquals('solicitado_qr', $pedido->estado);

        // Mesero A toma la asignación
        $pedidoAsignado = $pedidoService->asignarMeseroAPedidoQr($pedido->id, $this->meseroA);

        $this->assertEquals($this->meseroA->id, $pedidoAsignado->usuario_id);
        $this->assertEquals('en_cocina', $pedidoAsignado->estado);
        $this->assertEquals('en_preparacion', $pedidoAsignado->items->first()->estado_cocina);
    }

    public function test_concurrencia_si_otro_mesero_intenta_tomar_pedido_ya_asignado_recibe_aviso_con_nombre(): void
    {
        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedidoDesdeQr($this->mesa, [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
                'precio_unitario' => 32000,
            ],
        ], 'Comensal Mesa 4');

        // Mesero A toma la asignación primero
        $pedidoService->asignarMeseroAPedidoQr($pedido->id, $this->meseroA);

        // Mesero B intenta tomar el mismo pedido que ya fue asignado a Mesero A
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ya fue tomado por Carlos Mesero');

        $pedidoService->asignarMeseroAPedidoQr($pedido->id, $this->meseroB);
    }

    public function test_generador_de_qr_produce_svg_valido_y_url_correcta(): void
    {
        $qrService = app(QrCodeService::class);
        $url = $qrService->urlParaMesa('4');

        $this->assertStringContainsString('/m/4', $url);

        $svg = $qrService->generarSvg($url, 250);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);

        $dataUri = $qrService->generarDataUri($url, 250);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }
}
