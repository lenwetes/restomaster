<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\DeliveryService;
use App\Services\FidelizacionService;
use App\Services\MenuService;
use App\Services\PedidoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RemediacionDineroTurnosTest extends TestCase
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
        $rol = Role::firstOrCreate(
            ['slug' => $slugRol],
            ['nombre' => ucfirst($slugRol), 'descripcion' => $slugRol]
        );

        return User::factory()->create(['role_id' => $rol->id, 'sucursal_id' => $sucursalId, 'activo' => true]);
    }

    private function abrirTurnoEn(int $sucursalId, User $user): TurnoCaja
    {
        $caja = Caja::create(['sucursal_id' => $sucursalId, 'nombre' => 'Caja', 'codigo' => 'CAJA-'.$sucursalId, 'activa' => true]);

        return app(CajaService::class)->abrirTurno($caja, $user, 100000.00, 'Apertura');
    }

    private function crearPedidoConItems(User $user, int $sucursalId, int $cantidad = 2, float $precio = 45000.00): Pedido
    {
        $menu = app(MenuService::class);
        $categoria = $menu->crearCategoria(['nombre' => 'Parrilla', 'icono' => '🍖', 'orden' => 1, 'activo' => true]);
        $producto = $menu->crearProducto([
            'categoria_id' => $categoria->id,
            'nombre' => 'Bife de Chorizo',
            'precio' => $precio,
            'costo' => 12000.00,
            'area_cocina' => 'caliente',
            'activo' => true,
        ]);

        $pedido = app(PedidoService::class)->crearPedido(
            ['tipo' => 'mesa', 'estado' => 'creado', 'descuento' => 0, 'costo_envio' => 0],
            [['producto_id' => $producto->id, 'cantidad' => $cantidad]],
            $user
        );
        $pedido->update(['sucursal_id' => $sucursalId]);

        return $pedido;
    }

    public function test_mixto_vincula_efectivo_y_tarjeta_al_turno(): void
    {
        $s = $this->crearSucursal('S1');
        $user = $this->crearUsuario('admin', $s->id);
        $turno = $this->abrirTurnoEn($s->id, $user);
        $pedido = $this->crearPedidoConItems($user, $s->id);
        $total = (float) $pedido->total;

        $cobrado = app(PedidoService::class)->cobrarPedido($pedido, 'mixto', $total, 20000.00);

        $this->assertEquals(20000.00, (float) $cobrado->monto_pago_efectivo);
        $this->assertEquals($total - 20000.00, (float) $cobrado->monto_pago_tarjeta);

        $this->assertSame($turno->id, $cobrado->turno_caja_id, 'El cobro debe quedar vinculado al turno.');
        $turno->refresh();
        $this->assertEquals(20000.00, (float) $turno->total_ventas_efectivo);
        $this->assertEquals($total - 20000.00, (float) $turno->total_ventas_tarjeta);
        $this->assertEquals(0.0, (float) $turno->total_ventas_transferencia);
    }

    public function test_datafono_se_clasifica_como_tarjeta_no_como_transferencia(): void
    {
        $s = $this->crearSucursal('S2');
        $user = $this->crearUsuario('admin', $s->id);
        $turno = $this->abrirTurnoEn($s->id, $user);
        $pedido = $this->crearPedidoConItems($user, $s->id);

        app(PedidoService::class)->cobrarPedido($pedido, 'datafono', (float) $pedido->total);

        $turno->refresh();
        $this->assertEquals((float) $pedido->total, (float) $turno->total_ventas_tarjeta,
            'datafono debe sumar a ventas_tarjeta (antes caía en transferencia).');
        $this->assertEquals(0.0, (float) $turno->total_ventas_transferencia);
    }

    public function test_vincular_pedido_mixto_doble_invocacion_acumula_desglose_sin_perder_metodo(): void
    {
        $s = $this->crearSucursal('S3');
        $user = $this->crearUsuario('admin', $s->id);
        $turno = $this->abrirTurnoEn($s->id, $user);
        $pedido = $this->crearPedidoConItems($user, $s->id, 1, 100000.00);
        $cobrado = app(PedidoService::class)->cobrarPedido($pedido, 'mixto', 100000.00, 40000.00);

        app(CajaService::class)->vincularCobroPedido($turno, $cobrado);

        $turno->refresh();
        $this->assertEquals(80000.00, (float) $turno->total_ventas_efectivo);
        $this->assertEquals(120000.00, (float) $turno->total_ventas_tarjeta);
        $this->assertEquals(0.0, (float) $turno->total_ventas_transferencia);
    }

    public function test_cobrar_pedido_sin_turno_abierto_lanza_domain_exception(): void
    {
        $s = $this->crearSucursal('S4');
        $user = $this->crearUsuario('admin', $s->id);
        $pedido = $this->crearPedidoConItems($user, $s->id);

        $this->expectException(\DomainException::class);
        app(PedidoService::class)->cobrarPedido($pedido, 'efectivo', (float) $pedido->total);
    }

    public function test_cobro_se_vincula_al_turno_abierto_de_la_sucursal_del_pedido(): void
    {
        $sA = $this->crearSucursal('A1');
        $sB = $this->crearSucursal('B1');
        $adminA = $this->crearUsuario('admin', $sA->id);
        $adminB = $this->crearUsuario('admin', $sB->id);

        $turnoA = $this->abrirTurnoEn($sA->id, $adminA);
        $turnoB = $this->abrirTurnoEn($sB->id, $adminB);

        $pedido = $this->crearPedidoConItems($adminA, $sA->id);
        app(PedidoService::class)->cobrarPedido($pedido, 'efectivo', (float) $pedido->total);

        $pedido->refresh();
        $this->assertEquals($turnoA->id, $pedido->turno_caja_id,
            'Debe vincularse al turno abierto de la sucursal del pedido, no al último global.');
        $turnoA->refresh();
        $turnoB->refresh();
        $this->assertEquals((float) $pedido->total, (float) $turnoA->total_ventas_efectivo);
        $this->assertEquals(0.0, (float) $turnoB->total_ventas_efectivo);
    }

    public function test_indice_unico_parcial_impide_dos_turnos_abiertos_por_caja(): void
    {
        $s = $this->crearSucursal('S5');
        $user = $this->crearUsuario('admin', $s->id);
        $caja = Caja::create(['sucursal_id' => $s->id, 'nombre' => 'Caja', 'codigo' => 'CAJA-X', 'activa' => true]);

        app(CajaService::class)->abrirTurno($caja, $user, 100000.00);

        try {
            app(CajaService::class)->abrirTurno($caja, $user, 100000.00);
            $this->fail('Debe rechazarse la apertura de un segundo turno sobre la misma caja.');
        } catch (\InvalidArgumentException) {
            // esperado
        }

        $this->expectException(QueryException::class);
        DB::table('turnos_caja')->insert([
            'caja_id' => $caja->id,
            'user_id' => $user->id,
            'apertura_en' => now(),
            'monto_inicial' => 0,
            'estado' => 'abierto',
        ]);
    }

    public function test_marcar_entregado_no_reeistra_un_pedido_ya_pagado(): void
    {
        $s = $this->crearSucursal('S6');
        $user = $this->crearUsuario('admin', $s->id);
        $turno = $this->abrirTurnoEn($s->id, $user);
        $pedido = $this->crearPedidoConItems($user, $s->id);
        $pagado = app(PedidoService::class)->cobrarPedido($pedido, 'efectivo', (float) $pedido->total);

        $resultado = app(DeliveryService::class)->marcarEntregado($pagado, 'efectivo', (float) $pagado->total);

        $resultado->refresh();
        $this->assertEquals('pagado', $resultado->estado, 'Un pedido ya pagado no debe volver a cobrarse ni cambiar de estado.');
        $this->assertEquals('entregado', $resultado->estado_delivery);
        $this->assertEquals((float) $pagado->total, (float) $resultado->monto_pagado);

        $turno->refresh();
        $this->assertEquals((float) $pagado->total, (float) $turno->total_ventas_efectivo,
            'El cobro no debe registrarse dos veces en el turno.');
    }

    public function test_cobro_contra_entrega_se_vincula_al_turno_de_la_sucursal(): void
    {
        $s = $this->crearSucursal('S7');
        $user = $this->crearUsuario('admin', $s->id);
        $turno = $this->abrirTurnoEn($s->id, $user);

        $cliente = Cliente::create(['nombre' => 'Carlos', 'telefono' => '3001112233', 'activo' => true]);

        $producto = app(MenuService::class)->crearProducto([
            'categoria_id' => app(MenuService::class)->crearCategoria(['nombre' => 'C', 'icono' => '🍣', 'orden' => 1, 'activo' => true])->id,
            'nombre' => 'Nigiri',
            'precio' => 45000.00,
            'costo' => 10000.00,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $pedido = app(DeliveryService::class)->crearPedidoDelivery([
            'sucursal_id' => $s->id,
            'user_id' => $user->id,
            'cliente_id' => $cliente->id,
            'estado_delivery' => 'en_ruta',
            'costo_envio' => 5000.00,
        ]);
        $pedido->update(['sucursal_id' => $s->id]);
        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => 'Nigiri',
            'cantidad' => 1,
            'precio_unitario' => 45000.00,
            'subtotal' => 45000.00,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);
        $pedido->recalcularTotales();
        $pedido->refresh();

        $entregado = app(DeliveryService::class)->marcarEntregado($pedido, 'efectivo', (float) $pedido->total);

        $entregado->refresh();
        $this->assertEquals('pagado', $entregado->estado);
        $this->assertSame($turno->id, $entregado->turno_caja_id,
            'El cobro contra entrega debe quedar vinculado al turno abierto de la sucursal.');
        $this->assertEquals((float) $pedido->total, (float) $entregado->monto_pagado);

        $turno->refresh();
        $this->assertEquals((float) $pedido->total, (float) $turno->total_ventas_efectivo);
    }

    public function test_no_se_pueden_canjear_puntos_en_un_pedido_ya_pagado(): void
    {
        $s = $this->crearSucursal('S8');
        $user = $this->crearUsuario('admin', $s->id);
        $this->abrirTurnoEn($s->id, $user);
        $cliente = Cliente::create(['nombre' => 'Ana', 'telefono' => '3002223344', 'activo' => true, 'puntos_fidelidad' => 1000]);

        $pedido = $this->crearPedidoConItems($user, $s->id);
        $pedido->update(['cliente_id' => $cliente->id]);
        $cobrado = app(PedidoService::class)->cobrarPedido($pedido, 'efectivo', (float) $pedido->total);

        $this->expectException(\InvalidArgumentException::class);
        app(FidelizacionService::class)->canjearPuntos($cliente, 500, $cobrado);
    }

    public function test_terminal_envia_desglose_mixto_al_cobrar(): void
    {
        $s = $this->crearSucursal('S9');
        $user = $this->crearUsuario('admin', $s->id);
        $turno = $this->abrirTurnoEn($s->id, $user);
        $mesa = Mesa::create([
            'numero' => '11',
            'zona' => 'salon',
            'capacidad' => 4,
            'sucursal_id' => $s->id,
            'estado' => 'libre',
        ]);
        $producto = app(MenuService::class)->crearProducto([
            'categoria_id' => app(MenuService::class)->crearCategoria(['nombre' => 'P', 'icono' => '🍝', 'orden' => 1, 'activo' => true])->id,
            'nombre' => 'Pasta',
            'precio' => 100000.00,
            'costo' => 30000.00,
            'area_cocina' => 'cocina',
            'activo' => true,
        ]);

        Volt::actingAs($user)
            ->test('pos.terminal')
            ->set('tipo', 'mesa')
            ->set('mesaId', $mesa->id)
            ->call('agregarProducto', $producto->id)
            ->set('metodoPago', 'mixto')
            ->set('montoPagado', 100000.00)
            ->set('montoEfectivoMixto', 20000.00)
            ->call('procesarCobro')
            ->assertOk();

        $pedido = Pedido::where('mesa_id', $mesa->id)->latest()->first();
        $this->assertNotNull($pedido);
        $this->assertEquals(20000.00, (float) $pedido->monto_pago_efectivo);
        $this->assertEquals(80000.00, (float) $pedido->monto_pago_tarjeta);
        $this->assertEquals('pagado', $pedido->estado);

        $turno->refresh();
        $this->assertEquals(20000.00, (float) $turno->total_ventas_efectivo);
        $this->assertEquals(80000.00, (float) $turno->total_ventas_tarjeta);
    }
}
