<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\NotaCredito;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Policies\NotaCreditoPolicy;
use App\Services\ConfiguracionService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Fase 8.3 — Auditoría de devoluciones con Nota de Crédito obligatoria.
 */
class NotaCreditoAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    protected User $cajero;

    protected User $mesero;

    protected Producto $producto;

    protected Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede NC Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'admin.nc@test.local',
        ]);
        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'cajero.nc@test.local',
        ]);
        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id, 'sucursal_id' => $this->sucursal->id, 'email' => 'mesero.nc@test.local',
        ]);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id, 'nombre' => 'Caja Principal', 'codigo' => 'CAJ-01',
            'tipo' => 'principal', 'activa' => true,
        ]);
        TurnoCaja::create([
            'caja_id' => $caja->id, 'user_id' => $this->cajero->id,
            'apertura_en' => now(), 'monto_inicial' => 100000, 'estado' => 'abierto',
        ]);

        $this->producto = Producto::create([
            'nombre' => 'Roll NC', 'slug' => 'roll-nc', 'precio' => 24000,
            'costo' => 9000, 'area_cocina' => 'cocina', 'activo' => true,
        ]);

        $svc = app(PedidoService::class);
        $this->pedido = $svc->crearPedido([
            'tipo' => 'barra',
            'sucursal_id' => $this->sucursal->id,
            'total' => 24000.0,
        ], [
            [
                'producto_id' => $this->producto->id,
                'nombre_producto' => $this->producto->nombre,
                'cantidad' => 2,
                'precio_unitario' => 12000,
                'subtotal' => 24000,
                'area_cocina' => 'cocina',
                'estado_cocina' => 'entregado',
            ],
        ], $this->cajero);
        $svc->cobrarPedido($this->pedido, 'efectivo', 48000.0);
        $this->pedido->refresh();
    }

    protected function crearNotaCredito(): NotaCredito
    {
        return NotaCredito::emitir(
            pedido: $this->pedido,
            motivo: 'error_cargo',
            autorizadoPor: $this->cajero,
            monto: 24000.0
        );
    }

    public function test_sin_nc_no_hay_devolucion(): void
    {
        $item = $this->pedido->items()->first();

        $this->expectException(\DomainException::class);
        app(PedidoService::class)->devolverItemPedido(
            item: $item,
            cantidad: 1,
            motivo: 'Error de digitación',
            autorizadoPor: 'Cajero',
            usuario: $this->cajero,
            notaCreditoId: null
        );
    }

    public function test_numero_nc_unico_con_formato(): void
    {
        $nc1 = $this->crearNotaCredito();
        $nc2 = $this->crearNotaCredito();

        $this->assertNotEquals($nc1->numero_nc, $nc2->numero_nc);
        $this->assertMatchesRegularExpression('/^NC-\d{4}-\d+-(\d+)$/', $nc1->numero_nc);
        $this->assertDatabaseHas('notas_credito', ['numero_nc' => $nc1->numero_nc]);
    }

    public function test_solo_cajero_y_admin_crean_nc(): void
    {
        $policy = new NotaCreditoPolicy;

        $this->assertTrue($policy->create($this->cajero));
        $this->assertTrue($policy->create($this->admin));
        $this->assertFalse($policy->create($this->mesero));
    }

    public function test_nc_reutilizada_es_rechazada(): void
    {
        $nc = $this->crearNotaCredito();
        $item = $this->pedido->items()->first();
        $svc = app(PedidoService::class);

        $svc->devolverItemPedido(
            item: $item,
            cantidad: 1,
            motivo: 'error_cargo',
            autorizadoPor: 'Cajero',
            usuario: $this->cajero,
            notaCreditoId: $nc->id
        );

        $this->expectException(\DomainException::class);
        $svc->devolverItemPedido(
            item: $item,
            cantidad: 1,
            motivo: 'error_cargo',
            autorizadoPor: 'Cajero',
            usuario: $this->cajero,
            notaCreditoId: $nc->id
        );
    }

    public function test_caja_procesa_devolucion_con_nc_y_pin(): void
    {
        app(ConfiguracionService::class)->establecerPinSeguridad('1234', $this->admin);
        $item = $this->pedido->items()->first();

        Volt::actingAs($this->cajero)
            ->test('caja.control')
            ->call('abrirModalDevolucion', $item->id)
            ->set('motivoNcDevolucion', 'error_cargo')
            ->set('pinAutorizacionDevolucion', '1234')
            ->set('cantidadDevolucion', 1)
            ->call('procesarDevolucionItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notas_credito', [
            'pedido_id' => $this->pedido->id,
            'motivo' => 'error_cargo',
        ]);
        $this->assertDatabaseHas('pedido_devoluciones', [
            'pedido_id' => $this->pedido->id,
            'cantidad' => 1,
        ]);
    }

    public function test_mesero_no_puede_abrir_devolucion(): void
    {
        $item = $this->pedido->items()->first();

        Volt::actingAs($this->mesero)
            ->test('caja.control')
            ->call('abrirModalDevolucion', $item->id)
            ->assertForbidden();
    }
}
