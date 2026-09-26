<?php

namespace Tests\Feature;

use App\Jobs\EnviarEncuestaClienteJob;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Encuesta;
use App\Models\EncuestaEnvio;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\EncuestaService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EncuestaFidelizacionTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected Cliente $cliente;

    protected EncuestaService $encuestaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Principal',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001112233',
            'activo' => true,
        ]);

        $this->cliente = Cliente::create([
            'nombre' => 'María Castro',
            'email' => 'maria.castro@test.com',
            'telefono' => '3004445566',
            'puntos_fidelidad' => 100,
            'visitas_count' => 3,
            'total_gastado' => 150000,
            'activo' => true,
        ]);

        $this->encuestaService = app(EncuestaService::class);
    }

    public function test_crear_encuesta_y_generar_envio_con_token(): void
    {
        $encuesta = $this->encuestaService->obtenerEncuestaActiva('post_pago', $this->sucursal->id);
        $this->assertInstanceOf(Encuesta::class, $encuesta);

        $envio = $this->encuestaService->generarEnvio($encuesta, $this->cliente);
        $this->assertInstanceOf(EncuestaEnvio::class, $envio);
        $this->assertSame('pendiente', $envio->estado);
        $this->assertNotEmpty($envio->token);
        $this->assertSame($this->cliente->id, $envio->cliente_id);
    }

    public function test_responder_encuesta_otorga_puntos_y_actualiza_rating_cliente(): void
    {
        $encuesta = $this->encuestaService->obtenerEncuestaActiva('post_pago', $this->sucursal->id);
        $envio = $this->encuestaService->generarEnvio($encuesta, $this->cliente);

        $respuestas = [
            0 => 5, // 5 estrellas
            1 => 5, // 5 estrellas
            2 => 5, // 5 estrellas
            3 => '¡Excelente comida y atención fantástica!', // comentario
        ];

        $resultado = $this->encuestaService->responderEncuesta($envio, $respuestas);

        // 50 puntos base + 25 bonus 5 estrellas con comentario = 75 puntos
        $this->assertSame(75, $resultado['puntos_ganados']);
        $this->assertSame('respondida', $envio->fresh()->estado);

        // Cliente suma 100 + 75 = 175 puntos
        $this->assertSame(175, $this->cliente->fresh()->puntos_fidelidad);
        $this->assertSame(1, $this->cliente->fresh()->encuestas_respondidas);
        $this->assertEquals(5.0, (float) $this->cliente->fresh()->rating_promedio);
    }

    public function test_formulario_publico_encuesta_renders_y_guarda(): void
    {
        $encuesta = $this->encuestaService->obtenerEncuestaActiva('post_pago', $this->sucursal->id);
        $envio = $this->encuestaService->generarEnvio($encuesta, $this->cliente);

        // GET público
        $responseGet = $this->get(route('encuesta.responder', $envio->token));
        $responseGet->assertOk();
        $responseGet->assertSee($encuesta->nombre);
        $responseGet->assertSee('María Castro');

        // POST público
        $responsePost = $this->post(route('encuesta.guardar', $envio->token), [
            'respuestas' => [
                0 => 4,
                1 => 4,
                2 => 4,
                3 => 'Todo muy rico',
            ],
        ]);

        $responsePost->assertOk();
        $responsePost->assertSee('¡Muchas gracias por tu opinión!');
        $responsePost->assertSee('+50'); // 50 puntos base (no es 5 estrellas)
        $this->assertSame('respondida', $envio->fresh()->estado);
    }

    public function test_cobrar_pedido_con_cliente_dispara_job_envio_encuesta(): void
    {
        Queue::fake();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);
        $admin = User::create([
            'name' => 'Admin Cashier',
            'email' => 'cajero@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja 1',
            'codigo' => 'CAJA-01',
            'activa' => true,
        ]);

        TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $admin->id,
            'monto_inicial' => 100000,
            'apertura_en' => now(),
            'estado' => 'abierto',
        ]);

        $categoria = Categoria::create(['nombre' => 'Sushi', 'slug' => 'sushi', 'activa' => true]);
        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Roll Dragón',
            'slug' => 'roll-dragon',
            'precio' => 45000,
            'disponible' => true,
        ]);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '5',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'ocupada',
        ]);

        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedido([
            'sucursal_id' => $this->sucursal->id,
            'cliente_id' => $this->cliente->id,
            'mesa_id' => $mesa->id,
            'mesero_id' => $admin->id,
            'tipo_consumo' => 'en_sitio',
            'estado' => 'entregado',
        ], [
            ['producto_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => 45000],
        ], $admin);

        $pedidoService->cobrarPedido($pedido, 'efectivo', 90000, 0);

        Queue::assertPushed(EnviarEncuestaClienteJob::class, function ($job) use ($pedido) {
            return $job->pedidoId === $pedido->id;
        });
    }
}
