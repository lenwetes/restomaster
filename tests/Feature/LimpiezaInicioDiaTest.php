<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LimpiezaInicioDiaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $mesero;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Principal', 'direccion' => 'Calle 1']);
        $this->mesero = User::factory()->create();

        $categoria = Categoria::create([
            'nombre' => 'Prueba', 'slug' => 'prueba', 'icono' => '🍣', 'orden' => 1, 'activo' => true,
        ]);
        $this->producto = Producto::create([
            'categoria_id' => $categoria->id, 'nombre' => 'Rol Test', 'slug' => 'rol-test',
            'precio' => 20000, 'area_cocina' => 'sushi', 'activo' => true,
        ]);
    }

    private function crearMesa(string $numero, string $estado = 'libre', ?int $meseroId = null): Mesa
    {
        return Mesa::create([
            'sucursal_id' => $this->sucursal->id, 'numero' => $numero, 'zona' => 'salon',
            'capacidad' => 4, 'estado' => $estado, 'activo' => true, 'mesero_id' => $meseroId,
        ]);
    }

    private function crearPedidoConItem(Mesa $mesa, string $estado = 'en_cocina'): Pedido
    {
        $pedido = Pedido::create([
            'codigo' => 'ORD-TEST-'.$mesa->numero.'-'.$estado, 'tipo' => 'mesa', 'estado' => $estado,
            'mesa_id' => $mesa->id, 'usuario_id' => $this->mesero->id,
            'sucursal_id' => $this->sucursal->id, 'subtotal' => 20000, 'total' => 20000,
        ]);
        ItemPedido::create([
            'pedido_id' => $pedido->id, 'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre, 'cantidad' => 1,
            'precio_unitario' => 20000, 'subtotal' => 20000,
            'area_cocina' => 'sushi', 'estado_cocina' => 'pendiente',
        ]);

        return $pedido;
    }

    public function test_dry_run_sin_force_no_borra_nada(): void
    {
        $mesa = $this->crearMesa('1', 'ocupada', $this->mesero->id);
        $this->crearPedidoConItem($mesa);

        $this->artisan('limpieza:inicio-dia')
            ->assertSuccessful()
            ->expectsOutputToContain('--force');

        $this->assertSame(1, Pedido::count());
        $this->assertSame(1, ItemPedido::count());
        $this->assertSame(1, Mesa::count());
    }

    public function test_force_deja_dia_en_cero_respetando_fks(): void
    {
        $libre = $this->crearMesa('1', 'libre');
        $ocupada = $this->crearMesa('2', 'ocupada', $this->mesero->id);
        $conReserva = $this->crearMesa('3', 'ocupada', $this->mesero->id);

        $reserva = Reserva::create([
            'sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'Test',
            'telefono_contacto' => '3001112233', 'fecha' => now()->toDateString(),
            'hora_llegada' => '19:00', 'personas' => 2, 'estado' => 'confirmada',
        ]);
        $reserva->mesas()->attach($conReserva->id);

        $this->crearPedidoConItem($ocupada, 'en_cocina');
        $this->crearPedidoConItem($conReserva, 'pagado');

        $this->artisan('limpieza:inicio-dia', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Pedido::count());
        $this->assertSame(0, ItemPedido::count());
        $this->assertSame(1, Mesa::count());
        $this->assertSame($libre->id, Mesa::first()->id);
        $this->assertSame(0, DB::table('reserva_mesa')->count());
        $this->assertSame(1, Reserva::count());
    }
}
