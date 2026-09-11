<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\CuentaPorPagar;
use App\Models\DireccionCliente;
use App\Models\Impresora;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\MovimientoCaja;
use App\Models\MovimientoInventario;
use App\Models\MovimientoPuntos;
use App\Models\PagoCxp;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TrabajoImpresion;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegridadHistoricoFkTest extends TestCase
{
    use RefreshDatabase;

    private Role $roleCajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero', 'descripcion' => 'Cajero']);
    }

    public function test_no_se_puede_eliminar_caja_con_turnos_historicos(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Sede Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Principal',
            'codigo' => 'CAJ-TEST-01',
            'activa' => true,
        ]);

        $user = User::factory()->create(['role_id' => $this->roleCajero->id]);

        TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $user->id,
            'apertura_en' => now(),
            'monto_inicial' => 100000,
            'estado' => 'abierto',
        ]);

        $this->expectException(QueryException::class);
        DB::table('cajas')->where('id', $caja->id)->delete();
    }

    public function test_no_se_puede_eliminar_turno_con_movimientos_caja(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Sede Provenza',
            'codigo' => 'PRV-02',
            'direccion' => 'Cra 35 # 8A-12',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Barra',
            'codigo' => 'CAJ-TEST-02',
            'activa' => true,
        ]);

        $user = User::factory()->create(['role_id' => $this->roleCajero->id]);

        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $user->id,
            'apertura_en' => now(),
            'monto_inicial' => 50000,
            'estado' => 'abierto',
        ]);

        MovimientoCaja::create([
            'turno_caja_id' => $turno->id,
            'user_id' => $user->id,
            'tipo' => 'ingreso',
            'concepto' => 'Base adicional',
            'monto' => 20000,
            'metodo_pago' => 'efectivo',
        ]);

        $this->expectException(QueryException::class);
        DB::table('turnos_caja')->where('id', $turno->id)->delete();
    }

    public function test_no_se_puede_eliminar_impresora_con_trabajos_impresion(): void
    {
        $impresora = Impresora::create([
            'nombre' => 'Térmica Caja 80mm',
            'tipo_conexion' => 'red_ip',
            'ip' => '192.168.1.200',
            'puerto' => 9100,
            'area' => 'caja',
            'ancho_papel' => '80mm',
            'activa' => true,
        ]);

        TrabajoImpresion::create([
            'tipo' => 'ticket_venta',
            'impresora_id' => $impresora->id,
            'area' => 'caja',
            'contenido_texto' => 'TICKET DE VENTA TEST',
            'estado' => 'pendiente',
        ]);

        $this->expectException(QueryException::class);
        DB::table('impresoras')->where('id', $impresora->id)->delete();
    }

    public function test_no_se_puede_eliminar_usuario_con_movimientos_caja(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Sede Provenza',
            'codigo' => 'PRV-03',
            'direccion' => 'Cra 35 # 8A-12',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Express',
            'codigo' => 'CAJ-TEST-03',
            'activa' => true,
        ]);

        $user = User::factory()->create(['role_id' => $this->roleCajero->id]);

        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $user->id,
            'apertura_en' => now(),
            'monto_inicial' => 50000,
            'estado' => 'abierto',
        ]);

        MovimientoCaja::create([
            'turno_caja_id' => $turno->id,
            'user_id' => $user->id,
            'tipo' => 'ingreso',
            'concepto' => 'Base adicional',
            'monto' => 15000,
            'metodo_pago' => 'efectivo',
        ]);

        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->delete();
    }

    public function test_eliminar_categoria_setea_null_en_producto_sin_borrar_producto(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Rolls Especiales',
            'slug' => 'rolls-especiales',
            'icono' => '🍣',
            'orden' => 1,
            'activa' => true,
        ]);

        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Dragón Roll',
            'slug' => 'dragon-roll',
            'precio' => 38000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $categoria->delete();

        $productoRefrescado = Producto::find($producto->id);
        $this->assertNotNull($productoRefrescado);
        $this->assertNull($productoRefrescado->categoria_id);
    }

    public function test_no_se_puede_eliminar_insumo_con_movimientos_inventario(): void
    {
        $insumo = Insumo::create([
            'nombre' => 'Salmón Premium',
            'codigo' => 'INS-TEST-01',
            'categoria' => 'pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'costo_unitario' => 50000,
        ]);

        MovimientoInventario::create([
            'insumo_id' => $insumo->id,
            'tipo' => 'compra',
            'cantidad' => 10,
            'saldo_anterior' => 0,
            'saldo_posterior' => 10,
            'costo_unitario' => 50000,
            'costo_total' => 500000,
        ]);

        $this->expectException(QueryException::class);
        DB::table('insumos')->where('id', $insumo->id)->delete();
    }

    public function test_no_se_puede_eliminar_cuenta_con_pagos_cxp(): void
    {
        $cxp = CuentaPorPagar::create([
            'proveedor_nombre' => 'Pescadería del Mar',
            'proveedor_nit' => '900.123.456-7',
            'concepto' => 'Compra Salmón',
            'monto_total' => 100000,
            'saldo_pendiente' => 50000,
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'estado' => 'parcial',
        ]);

        PagoCxp::create([
            'cuenta_por_pagar_id' => $cxp->id,
            'monto' => 50000,
            'metodo_pago' => 'transferencia',
            'fecha_pago' => now()->toDateString(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('cuentas_por_pagar')->where('id', $cxp->id)->delete();
    }

    public function test_no_se_puede_eliminar_cliente_con_movimientos_puntos(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Camila Duque',
            'telefono' => '3007778899',
            'puntos_fidelidad' => 100,
        ]);

        MovimientoPuntos::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'acumulacion',
            'puntos' => 100,
            'saldo_anterior' => 0,
            'saldo_nuevo' => 100,
            'concepto' => 'Bienvenida',
        ]);

        $this->expectException(QueryException::class);
        DB::table('clientes')->where('id', $cliente->id)->delete();
    }

    public function test_no_se_puede_eliminar_producto_con_items_pedido(): void
    {
        $producto = Producto::create([
            'nombre' => 'Nigiri Sake',
            'slug' => 'nigiri-sake-test',
            'precio' => 22000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $pedido = Pedido::create([
            'codigo' => 'PED-TEST-FK',
            'tipo' => 'mostrador',
            'estado' => 'creado',
            'subtotal' => 22000,
            'total' => 22000,
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'nombre_producto' => 'Nigiri Sake',
            'cantidad' => 1,
            'precio_unitario' => 22000,
            'subtotal' => 22000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'pendiente',
        ]);

        $this->expectException(QueryException::class);
        DB::table('productos')->where('id', $producto->id)->delete();
    }

    public function test_no_se_puede_eliminar_cliente_con_direcciones_historicas(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Comensal Test Direccion',
            'telefono' => '3001239999',
            'activo' => true,
        ]);

        DireccionCliente::create([
            'cliente_id' => $cliente->id,
            'etiqueta' => 'Casa',
            'direccion' => 'Calle 10 # 40-20',
        ]);

        $this->expectException(QueryException::class);
        DB::table('clientes')->where('id', $cliente->id)->delete();
    }

    public function test_no_se_puede_eliminar_producto_con_recetas_activas(): void
    {
        $producto = Producto::create([
            'nombre' => 'Uramaki Roll',
            'slug' => 'uramaki-test-fk',
            'precio' => 35000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $insumo = Insumo::create([
            'nombre' => 'Arroz Koshihikari',
            'codigo' => 'INS-TEST-01',
            'categoria' => 'granos',
            'unidad_medida' => 'kg',
            'costo_unitario' => 12000,
            'activo' => true,
        ]);

        Receta::create([
            'producto_id' => $producto->id,
            'insumo_id' => $insumo->id,
            'cantidad' => 0.150,
        ]);

        $this->expectException(QueryException::class);
        DB::table('productos')->where('id', $producto->id)->delete();
    }

    public function test_no_se_puede_eliminar_insumo_con_recetas_activas(): void
    {
        $producto = Producto::create([
            'nombre' => 'Uramaki Roll 2',
            'slug' => 'uramaki-test-fk-2',
            'precio' => 35000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);

        $insumo = Insumo::create([
            'nombre' => 'Alga Nori Premium',
            'codigo' => 'INS-TEST-02',
            'categoria' => 'secos',
            'unidad_medida' => 'unidad',
            'costo_unitario' => 1500,
            'activo' => true,
        ]);

        Receta::create([
            'producto_id' => $producto->id,
            'insumo_id' => $insumo->id,
            'cantidad' => 1,
        ]);

        $this->expectException(QueryException::class);
        DB::table('insumos')->where('id', $insumo->id)->delete();
    }
}
