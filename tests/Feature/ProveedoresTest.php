<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\CuentaPorPagar;
use App\Models\Insumo;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\User;
use App\Services\CompraService;
use App\Services\CuentasPorPagarService;
use App\Services\ProveedorService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProveedoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_migraciones_crean_tablas_y_columnas(): void
    {
        foreach (['proveedores', 'compras', 'compra_lineas'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta tabla {$tabla}.");
        }
        foreach (['proveedor_id', 'precio_referencia_mercado'] as $col) {
            $this->assertTrue(Schema::hasColumn('insumos', $col), "Falta insumos.{$col}.");
        }
        $this->assertTrue(Schema::hasColumn('cuentas_por_pagar', 'compra_id'));
    }

    public function test_factura_duplicada_por_proveedor_violenta_unique(): void
    {
        $user = User::factory()->create();
        $prov = Proveedor::create(['nombre' => 'Distribuidora Andina']);
        Compra::create(['proveedor_id' => $prov->id, 'numero_factura' => 'F-001', 'fecha' => now()->toDateString(), 'subtotal' => 100, 'forma_pago' => 'contado', 'estado' => 'registrada', 'user_id' => $user->id]);

        $this->expectException(QueryException::class);
        Compra::create(['proveedor_id' => $prov->id, 'numero_factura' => 'F-001', 'fecha' => now()->toDateString(), 'subtotal' => 50, 'forma_pago' => 'contado', 'estado' => 'registrada', 'user_id' => $user->id]);
    }

    public function test_relaciones_proveedor_compra_lineas(): void
    {
        $prov = Proveedor::create(['nombre' => 'Plaza Mayorista']);
        $this->assertInstanceOf(Collection::class, $prov->compras);
        $this->assertInstanceOf(Collection::class, $prov->insumos);
    }

    private function crearUsuario(string $slug, ?string $email = null): User
    {
        $rol = Role::firstOrCreate(['slug' => $slug], ['nombre' => ucfirst($slug)]);
        static $n = 0;

        return User::factory()->create(['role_id' => $rol->id, 'email' => $email ?? $slug.'-'.(++$n).'@test.com']);
    }

    private function crearInsumo(array $over = []): Insumo
    {
        return Insumo::create(array_merge([
            'nombre' => 'Insumo Test '.Str::random(6),
            'codigo' => 'INS-'.Str::upper(Str::random(6)),
            'unidad_medida' => 'kg',
        ], $over));
    }

    public function test_gerente_puede_gestionar_proveedores_y_mesero_no(): void
    {
        $gerente = $this->crearUsuario('gerente');
        $mesero = $this->crearUsuario('mesero');

        $this->assertTrue($gerente->can('create', Proveedor::class));
        $this->assertFalse($mesero->can('create', Proveedor::class));
        $this->assertTrue($gerente->can('anular', Compra::class));
        $this->assertFalse($mesero->can('anular', Compra::class));
    }

    public function test_deny_explicito_bloquea_gestion_de_proveedores(): void
    {
        $gerente = $this->crearUsuario('gerente');
        DB::table('permission_user')->insert(['user_id' => $gerente->id, 'permission' => 'proveedores.crear', 'tipo' => 'deny', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertFalse($gerente->fresh()->can('create', Proveedor::class));
    }

    public function test_factura_contado_mueve_kardex_y_no_crea_cxp(): void
    {
        $admin = $this->crearUsuario('admin');
        $prov = Proveedor::create(['nombre' => 'Andina']);
        $insumo = $this->crearInsumo(['stock_actual' => 10, 'costo_unitario' => 5000]);

        $compra = app(CompraService::class)->registrarFactura(
            ['proveedor_id' => $prov->id, 'numero_factura' => 'F-100', 'fecha' => now()->toDateString(), 'forma_pago' => 'contado'],
            [['insumo_id' => $insumo->id, 'cantidad' => 4, 'costo_unitario' => 6000]],
            $admin
        );

        $this->assertSame('registrada', $compra->estado);
        $this->assertEquals(24000.0, (float) $compra->subtotal);
        $this->assertEquals(14.0, (float) $insumo->fresh()->stock_actual);
        $this->assertEquals(5285.71, (float) $insumo->fresh()->costo_unitario);
        $this->assertDatabaseMissing('cuentas_por_pagar', ['compra_id' => $compra->id]);
    }

    public function test_factura_credito_crea_cxp_con_vencimiento(): void
    {
        $admin = $this->crearUsuario('admin');
        $prov = Proveedor::create(['nombre' => 'Plaza', 'nit' => '9001', 'dias_credito' => 15]);
        $insumo = $this->crearInsumo();

        $compra = app(CompraService::class)->registrarFactura(
            ['proveedor_id' => $prov->id, 'numero_factura' => 'F-200', 'fecha' => '2026-09-01', 'forma_pago' => 'credito'],
            [['insumo_id' => $insumo->id, 'cantidad' => 2, 'costo_unitario' => 10000]],
            $admin
        );

        $cxp = CuentaPorPagar::where('compra_id', $compra->id)->first();
        $this->assertNotNull($cxp);
        $this->assertEquals(20000.0, (float) $cxp->saldo_pendiente);
        $this->assertSame('2026-09-16', $cxp->fecha_vencimiento->toDateString());
    }

    public function test_factura_duplicada_y_linea_invalida_abortan(): void
    {
        $admin = $this->crearUsuario('admin');
        $prov = Proveedor::create(['nombre' => 'Dup']);
        $insumo = $this->crearInsumo();
        $svc = app(CompraService::class);
        $cab = ['proveedor_id' => $prov->id, 'numero_factura' => 'F-300', 'fecha' => now()->toDateString(), 'forma_pago' => 'contado'];
        $lin = [['insumo_id' => $insumo->id, 'cantidad' => 1, 'costo_unitario' => 1000]];
        $svc->registrarFactura($cab, $lin, $admin);

        $this->expectException(InvalidArgumentException::class);
        $svc->registrarFactura($cab, $lin, $admin);
    }

    public function test_linea_con_cantidad_cero_aborta(): void
    {
        $admin = $this->crearUsuario('admin');
        $prov = Proveedor::create(['nombre' => 'Cero']);
        $insumo = $this->crearInsumo();

        $this->expectException(InvalidArgumentException::class);
        app(CompraService::class)->registrarFactura(
            ['proveedor_id' => $prov->id, 'numero_factura' => 'F-301', 'fecha' => now()->toDateString(), 'forma_pago' => 'contado'],
            [['insumo_id' => $insumo->id, 'cantidad' => 0, 'costo_unitario' => 1000]],
            $admin
        );
    }

    public function test_anular_revierte_kardex_y_borra_cxp_sin_pagos(): void
    {
        $admin = $this->crearUsuario('admin');
        $prov = Proveedor::create(['nombre' => 'Revierte']);
        $insumo = $this->crearInsumo(['stock_actual' => 10, 'costo_unitario' => 5000]);
        $svc = app(CompraService::class);
        $compra = $svc->registrarFactura(
            ['proveedor_id' => $prov->id, 'numero_factura' => 'F-400', 'fecha' => now()->toDateString(), 'forma_pago' => 'credito'],
            [['insumo_id' => $insumo->id, 'cantidad' => 4, 'costo_unitario' => 6000]],
            $admin
        );
        $cxpId = CuentaPorPagar::where('compra_id', $compra->id)->value('id');
        $this->assertNotNull($cxpId);

        $anulada = $svc->anularFactura($compra->fresh(), $admin);

        $this->assertSame('anulada', $anulada->estado);
        $this->assertEquals(10.0, (float) $insumo->fresh()->stock_actual);
        // Rounding dust: 2-decimal stored average cannot reverse bit-exact.
        $this->assertEqualsWithDelta(5000.0, (float) $insumo->fresh()->costo_unitario, 0.02);
        $this->assertDatabaseMissing('cuentas_por_pagar', ['id' => $cxpId]);
        $this->assertDatabaseHas('movimientos_inventario', ['tipo' => 'devolucion_compra', 'insumo_id' => $insumo->id]);
    }

    public function test_anular_bloquea_con_pagos_o_sin_stock(): void
    {
        $admin = $this->crearUsuario('admin');
        $prov = Proveedor::create(['nombre' => 'Bloqueo']);
        $insumo = $this->crearInsumo(['stock_actual' => 10, 'costo_unitario' => 5000]);
        $svc = app(CompraService::class);
        $compra = $svc->registrarFactura(
            ['proveedor_id' => $prov->id, 'numero_factura' => 'F-500', 'fecha' => now()->toDateString(), 'forma_pago' => 'credito'],
            [['insumo_id' => $insumo->id, 'cantidad' => 4, 'costo_unitario' => 6000]],
            $admin
        );

        app(CuentasPorPagarService::class)->registrarPago(
            CuentaPorPagar::where('compra_id', $compra->id)->first(), 5000.0, $admin
        );

        $this->expectException(DomainException::class);
        $svc->anularFactura($compra->fresh(), $admin);
    }

    public function test_anular_bloquea_si_stock_insuficiente(): void
    {
        $admin = $this->crearUsuario('admin');
        $prov = Proveedor::create(['nombre' => 'SinStock']);
        $insumo = $this->crearInsumo(['stock_actual' => 10, 'costo_unitario' => 5000]);
        $svc = app(CompraService::class);
        $compra = $svc->registrarFactura(
            ['proveedor_id' => $prov->id, 'numero_factura' => 'F-501', 'fecha' => now()->toDateString(), 'forma_pago' => 'contado'],
            [['insumo_id' => $insumo->id, 'cantidad' => 4, 'costo_unitario' => 6000]],
            $admin
        );
        $insumo->update(['stock_actual' => 1]);

        $this->expectException(DomainException::class);
        $svc->anularFactura($compra->fresh(), $admin);
    }

    public function test_proveedor_crud_con_restrict_y_soft(): void
    {
        $svc = app(ProveedorService::class);
        $prov = $svc->crear(['nombre' => 'CRUD', 'telefono' => '3001112233']);
        $this->assertTrue($prov->activo);

        $insumo = $this->crearInsumo();
        $admin = $this->crearUsuario('admin');
        app(CompraService::class)->registrarFactura(
            ['proveedor_id' => $prov->id, 'numero_factura' => 'F-600', 'fecha' => now()->toDateString(), 'forma_pago' => 'contado'],
            [['insumo_id' => $insumo->id, 'cantidad' => 1, 'costo_unitario' => 1000]],
            $admin
        );

        $svc->desactivar($prov->fresh());
        $this->assertFalse($prov->fresh()->activo);

        $this->expectException(InvalidArgumentException::class);
        $svc->eliminar($prov->fresh());
    }

    public function test_ruta_proveedores_403_para_mesero_y_200_para_gerente(): void
    {
        $this->actingAs($this->crearUsuario('mesero'))->get(route('proveedores'))->assertForbidden();
        $this->actingAs($this->crearUsuario('gerente'))->get(route('proveedores'))->assertOk();
    }

    public function test_gerente_crea_proveedor_y_registra_factura_desde_ui(): void
    {
        $gerente = $this->crearUsuario('gerente');
        $insumo = $this->crearInsumo();

        Volt::actingAs($gerente)->test('proveedores.index')
            ->set('nuevo.nombre', 'Andina SAS')->set('nuevo.nit', '900123')
            ->set('nuevo.telefono', '3001112233')->call('guardarProveedor')
            ->assertDispatched('notificacion');

        $prov = Proveedor::where('nit', '900123')->first();
        $this->assertNotNull($prov);

        Volt::actingAs($gerente)->test('proveedores.index')
            ->call('abrirFactura', $prov->id)
            ->set('factura.numero', 'F-900')->set('factura.forma_pago', 'contado')
            ->set('lineas', [['insumo_id' => $insumo->id, 'cantidad' => 3, 'costo_unitario' => 7000]])
            ->call('guardarFactura')
            ->assertDispatched('notificacion');

        $this->assertDatabaseHas('compras', ['proveedor_id' => $prov->id, 'numero_factura' => 'F-900', 'estado' => 'registrada']);
    }

    public function test_insumo_guarda_proveedor_y_precio_referencia(): void
    {
        $gerente = $this->crearUsuario('gerente');
        $prov = Proveedor::create(['nombre' => 'Andina']);

        Volt::actingAs($gerente)->test('inventario.index')
            ->call('abrirModalNuevoInsumo')
            ->set('nuevoNombre', 'Salmon Fresco Andina')
            ->set('nuevoCodigo', 'INS-ANDINA-1')
            ->set('nuevoStockMinimo', 5)
            ->set('nuevoCostoUnitario', 10000)
            ->set('nuevoProveedorId', $prov->id)
            ->set('nuevoPrecioReferencia', 7500)
            ->call('guardarNuevoInsumo')
            ->assertHasNoErrors();

        $insumo = Insumo::where('codigo', 'INS-ANDINA-1')->first();
        $this->assertNotNull($insumo);
        $this->assertSame($prov->id, (int) $insumo->fresh()->proveedor_id);
        $this->assertEquals(7500.0, (float) $insumo->fresh()->precio_referencia_mercado);
    }

    public function test_editar_insumo_actualiza_proveedor_y_referencia(): void
    {
        $gerente = $this->crearUsuario('gerente');
        $provA = Proveedor::create(['nombre' => 'Prov A']);
        $provB = Proveedor::create(['nombre' => 'Prov B']);
        $insumo = $this->crearInsumo(['proveedor_id' => $provA->id, 'precio_referencia_mercado' => 5000]);

        Volt::actingAs($gerente)->test('inventario.index')
            ->call('abrirEditarInsumo', $insumo->id)
            ->set('edicion.proveedor_id', $provB->id)
            ->set('edicion.precio_referencia_mercado', 6200)
            ->call('guardarEdicionInsumo')
            ->assertDispatched('notificacion');

        $this->assertSame($provB->id, $insumo->fresh()->proveedor_id);
        $this->assertEquals(6200.0, (float) $insumo->fresh()->precio_referencia_mercado);
    }

    public function test_link_ver_ficha_solo_visible_con_acceso_proveedores(): void
    {
        $gerente = $this->crearUsuario('gerente');
        $prov = Proveedor::create(['nombre' => 'Visible SA']);
        $insumo = $this->crearInsumo(['proveedor_id' => $prov->id]);

        Volt::actingAs($gerente)->test('inventario.index')
            ->call('selectInsumo', $insumo->id)
            ->assertSee(route('proveedores'), false);
    }

    public function test_comparador_detecta_mejor_precio_y_delta_vs_referencia(): void
    {
        $admin = $this->crearUsuario('admin');
        $andina = Proveedor::create(['nombre' => 'Andina']);
        $plaza = Proveedor::create(['nombre' => 'Plaza']);
        $insumo = $this->crearInsumo(['precio_referencia_mercado' => 5800]);
        $svc = app(CompraService::class);
        $svc->registrarFactura(
            ['proveedor_id' => $andina->id, 'numero_factura' => 'F-A1', 'fecha' => now()->subDay()->toDateString(), 'forma_pago' => 'contado'],
            [['insumo_id' => $insumo->id, 'cantidad' => 10, 'costo_unitario' => 6000]],
            $admin
        );
        $svc->registrarFactura(
            ['proveedor_id' => $plaza->id, 'numero_factura' => 'F-P1', 'fecha' => now()->toDateString(), 'forma_pago' => 'contado'],
            [['insumo_id' => $insumo->id, 'cantidad' => 10, 'costo_unitario' => 5500]],
            $admin
        );

        $filas = app(ProveedorService::class)->comparadorInsumo($insumo->id);

        $this->assertCount(2, $filas);
        $porProv = collect($filas)->keyBy('proveedor_id');
        $this->assertEquals(6000.0, (float) $porProv[$andina->id]['ultimo_costo']);
        $this->assertEquals(5500.0, (float) $porProv[$plaza->id]['ultimo_costo']);
        $this->assertSame($plaza->id, collect($filas)->sortBy('ultimo_costo')->first()['proveedor_id']);
        $this->assertEqualsWithDelta(3.45, (float) $porProv[$andina->id]['delta_vs_referencia'], 0.01);
        $this->assertEqualsWithDelta(-5.17, (float) $porProv[$plaza->id]['delta_vs_referencia'], 0.01);
    }

    public function test_ficha_resumen_y_gasto_por_proveedor_agregan_en_sql(): void
    {
        $admin = $this->crearUsuario('admin');
        $andina = Proveedor::create(['nombre' => 'Andina R']);
        $plaza = Proveedor::create(['nombre' => 'Plaza R']);
        $insumo = $this->crearInsumo();
        $svc = app(CompraService::class);
        $hoy = now()->toDateString();
        $svc->registrarFactura(['proveedor_id' => $andina->id, 'numero_factura' => 'F-1', 'fecha' => $hoy, 'forma_pago' => 'contado'], [['insumo_id' => $insumo->id, 'cantidad' => 4, 'costo_unitario' => 6000]], $admin);
        $svc->registrarFactura(['proveedor_id' => $andina->id, 'numero_factura' => 'F-2', 'fecha' => $hoy, 'forma_pago' => 'contado'], [['insumo_id' => $insumo->id, 'cantidad' => 2, 'costo_unitario' => 5000]], $admin);
        $svc->registrarFactura(['proveedor_id' => $plaza->id, 'numero_factura' => 'F-3', 'fecha' => $hoy, 'forma_pago' => 'contado'], [['insumo_id' => $insumo->id, 'cantidad' => 2, 'costo_unitario' => 5500]], $admin);

        $resumen = app(ProveedorService::class)->fichaResumen($andina, $hoy, $hoy);

        $this->assertEquals(34000.0, (float) $resumen['total']);
        $this->assertSame(2, (int) $resumen['facturas']);
        $this->assertEquals(17000.0, (float) $resumen['ticket_promedio']);

        $gasto = $svc->gastoPorProveedor($hoy, $hoy);
        $this->assertEquals(34000.0, (float) $gasto->firstWhere('proveedor_id', $andina->id)->total);
        $this->assertEquals(11000.0, (float) $gasto->firstWhere('proveedor_id', $plaza->id)->total);
    }
}
