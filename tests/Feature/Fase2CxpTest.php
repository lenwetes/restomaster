<?php

namespace Tests\Feature;

use App\Models\CuentaPorPagar;
use App\Models\Role;
use App\Models\User;
use App\Services\CuentasPorPagarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase2CxpTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $mesero;

    private CuentasPorPagarService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->gerente = User::factory()->create(['role_id' => Role::where('slug', 'gerente')->value('id')]);
        $this->mesero = User::factory()->create(['role_id' => Role::where('slug', 'mesero')->value('id')]);

        $this->service = app(CuentasPorPagarService::class);
    }

    public function test_pantalla_cxp_solo_disponible_para_gerente(): void
    {
        $this->actingAs($this->mesero)->get(route('cxp'))->assertForbidden();

        $this->actingAs($this->gerente)->get(route('cxp'))->assertOk();
        $this->actingAs($this->gerente)->get(route('cxp'))->assertSeeVolt('cxp.index');
    }

    public function test_crear_cuenta_por_pagar_inicia_saldo_completo(): void
    {
        $cuenta = $this->service->crear([
            'proveedor_nombre' => 'Pesquera El Muelle',
            'proveedor_nit' => '900.111.222-3',
            'concepto' => 'Compra de salmón fresco',
            'monto_total' => 850000,
            'fecha_emision' => '2026-09-01',
            'fecha_vencimiento' => '2026-09-15',
        ]);

        $this->assertDatabaseHas('cuentas_por_pagar', [
            'id' => $cuenta->id,
            'proveedor_nombre' => 'Pesquera El Muelle',
            'monto_total' => 850000,
            'saldo_pendiente' => 850000,
            'estado' => 'pendiente',
        ]);
    }

    public function test_registrar_pago_descuenta_saldo_y_guarda_historial(): void
    {
        $cuenta = $this->crearCuenta();

        $pago = $this->service->registrarPago($cuenta, 300000, $this->gerente, 'efectivo');

        $this->assertDatabaseHas('pagos_cxps', ['id' => $pago->id, 'monto' => 300000, 'metodo_pago' => 'efectivo']);
        $this->assertSame(500000.0, (float) $cuenta->fresh()->saldo_pendiente);
        $this->assertSame('pendiente', $cuenta->fresh()->estado);
    }

    public function test_pago_completo_marca_cuenta_como_pagada(): void
    {
        $cuenta = $this->crearCuenta();

        $this->service->registrarPago($cuenta, 300000, $this->gerente, 'transferencia');
        $this->service->registrarPago($cuenta, 500000, $this->gerente, 'efectivo');

        $this->assertSame(0.0, (float) $cuenta->fresh()->saldo_pendiente);
        $this->assertSame('pagada', $cuenta->fresh()->estado);
    }

    public function test_pago_mayor_a_saldo_lanza_error(): void
    {
        $cuenta = $this->crearCuenta();

        $this->expectException(InvalidArgumentException::class);
        $this->service->registrarPago($cuenta, 999999, $this->gerente, 'efectivo');
    }

    public function test_saldos_por_proveedor_agrupa_saldos(): void
    {
        $this->service->crear(['proveedor_nombre' => 'Pesquera El Muelle', 'proveedor_nit' => '900.1-2', 'concepto' => 'Compra', 'monto_total' => 200000, 'fecha_emision' => '2026-09-01', 'fecha_vencimiento' => '2026-09-15']);
        $this->service->crear(['proveedor_nombre' => 'Pesquera El Muelle', 'proveedor_nit' => '900.1-2', 'concepto' => 'Compra', 'monto_total' => 50000, 'fecha_emision' => '2026-09-01', 'fecha_vencimiento' => '2026-09-15']);
        $this->service->crear(['proveedor_nombre' => 'Mercado Central', 'proveedor_nit' => '901.3-4', 'concepto' => 'Verduras', 'monto_total' => 120000, 'fecha_emision' => '2026-09-01', 'fecha_vencimiento' => '2026-09-15']);

        $saldos = $this->service->saldosPorProveedor();

        $this->assertCount(2, $saldos);
        $this->assertSame(250000.0, (float) $saldos->firstWhere('proveedor_nombre', 'Pesquera El Muelle')['saldo_pendiente']);
        $this->assertSame(120000.0, (float) $saldos->firstWhere('proveedor_nombre', 'Mercado Central')['saldo_pendiente']);
    }

    public function test_componente_puede_registrar_pago_desde_ui(): void
    {
        $cuenta = $this->crearCuenta();

        Volt::actingAs($this->gerente)
            ->test('cxp.index')
            ->set('cuentaPagoId', $cuenta->id)
            ->set('pagoForm.monto', 200000)
            ->set('pagoForm.metodo_pago', 'efectivo')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertSame(600000.0, (float) $cuenta->fresh()->saldo_pendiente);
    }

    private function crearCuenta(): CuentaPorPagar
    {
        return $this->service->crear([
            'proveedor_nombre' => 'Distribuidora Nikkei S.A.S.',
            'proveedor_nit' => '901.555.666-7',
            'concepto' => 'Compra de insumos 09/2026',
            'monto_total' => 800000,
            'fecha_emision' => '2026-09-01',
            'fecha_vencimiento' => '2026-09-15',
        ]);
    }
}
