<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Caja;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\MesaService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AuditoriaLote7IdorYEstadosTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cajeroSucursalA;

    protected User $cajeroSucursalB;

    protected Sucursal $sucursalA;

    protected Sucursal $sucursalB;

    protected Caja $cajaA;

    protected Caja $cajaB;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);
        $cajeroRole = Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero']);
        $gerenteRole = Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente']);

        $this->sucursalA = Sucursal::create([
            'nombre' => 'RestoMaster Poblado',
            'direccion' => 'Cra 43A # 10-50',
            'telefono' => '3001111111',
            'ciudad' => 'Medellin',
            'activo' => true,
        ]);

        $this->sucursalB = Sucursal::create([
            'nombre' => 'RestoMaster Laureles',
            'direccion' => 'Circular 4 # 73-10',
            'telefono' => '3002222222',
            'ciudad' => 'Medellin',
            'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin General',
            'email' => 'admin@restomaster.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'sucursal_id' => $this->sucursalA->id,
            'activo' => true,
        ]);

        $this->cajeroSucursalA = User::create([
            'name' => 'Cajero Poblado',
            'email' => 'cajero.a@restomaster.com',
            'password' => bcrypt('password123'),
            'role_id' => $cajeroRole->id,
            'sucursal_id' => $this->sucursalA->id,
            'activo' => true,
        ]);

        $this->cajeroSucursalB = User::create([
            'name' => 'Cajero Laureles',
            'email' => 'cajero.b@restomaster.com',
            'password' => bcrypt('password123'),
            'role_id' => $cajeroRole->id,
            'sucursal_id' => $this->sucursalB->id,
            'activo' => true,
        ]);

        $this->cajaA = Caja::create([
            'sucursal_id' => $this->sucursalA->id,
            'nombre' => 'Caja Principal Poblado',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        $this->cajaB = Caja::create([
            'sucursal_id' => $this->sucursalB->id,
            'nombre' => 'Caja Principal Laureles',
            'codigo' => 'CAJ-02',
            'activa' => true,
        ]);
    }

    public function test_r24_pos_cobro_blocks_cross_branch_tampering(): void
    {
        $this->actingAs($this->cajeroSucursalB);

        $mesaA = Mesa::create([
            'sucursal_id' => $this->sucursalA->id,
            'numero' => 'M101',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::OCUPADA->value,
        ]);

        $pedidoA = Pedido::create([
            'codigo' => 'ORD-SUC-A',
            'sucursal_id' => $this->sucursalA->id,
            'mesa_id' => $mesaA->id,
            'tipo' => 'mesa',
            'canal_origen' => 'pos',
            'estado' => 'creado',
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        Livewire::test('pos.terminal', ['mesaId' => $mesaA->id])
            ->set('metodoPago', 'efectivo')
            ->set('montoPagado', 50000)
            ->call('procesarCobro')
            ->assertStatus(403);
    }

    public function test_r25_table_state_transitions_and_blocking_active_orders(): void
    {
        $mesaService = app(MesaService::class);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursalA->id,
            'numero' => 'M102',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
        ]);

        // 1. Transición válida libre -> ocupada
        $mesa = $mesaService->cambiarEstado($mesa, MesaEstado::OCUPADA);
        $this->assertEquals(MesaEstado::OCUPADA->value, $mesa->estado);

        // 2. Crear pedido activo en la mesa
        $pedido = Pedido::create([
            'codigo' => 'ORD-ACTIVO-102',
            'sucursal_id' => $this->sucursalA->id,
            'mesa_id' => $mesa->id,
            'tipo' => 'mesa',
            'canal_origen' => 'pos',
            'estado' => 'en_cocina',
            'subtotal' => 30000,
            'total' => 30000,
        ]);

        // 3. Intentar liberar mesa ocupada con pedido activo debe lanzar DomainException
        $this->expectException(DomainException::class);
        $mesaService->cambiarEstado($mesa, MesaEstado::LIBRE);
    }

    public function test_r26_blind_cash_count_defaults_to_zero(): void
    {
        $this->actingAs($this->cajeroSucursalA);

        $turno = TurnoCaja::create([
            'caja_id' => $this->cajaA->id,
            'user_id' => $this->cajeroSucursalA->id,
            'monto_inicial' => 200000,
            'monto_esperado_efectivo' => 350000,
            'estado' => 'abierto',
            'apertura_en' => now(),
        ]);

        Livewire::test('caja.control')
            ->assertSet('montoContado', 0.0)
            ->call('abrirModalCierre')
            ->assertSet('montoContado', 0.0);
    }

    public function test_r27_egreso_authorizer_validations(): void
    {
        $cajaService = app(CajaService::class);

        $turno = TurnoCaja::create([
            'caja_id' => $this->cajaA->id,
            'user_id' => $this->cajeroSucursalA->id,
            'monto_inicial' => 200000,
            'monto_esperado_efectivo' => 200000,
            'estado' => 'abierto',
            'apertura_en' => now(),
        ]);

        // 1. Cajero no puede auto-autorizarse
        try {
            $cajaService->registrarMovimiento(
                $turno,
                'egreso',
                50000,
                'Pago proveedor flores',
                'efectivo',
                null,
                $this->cajeroSucursalA->name,
                $this->cajeroSucursalA
            );
            $this->fail('Se esperaba InvalidArgumentException al auto-autorizarse');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('no puede auto-autorizarse', $e->getMessage());
        }

        // 2. Autorización por usuario con rol admin tiene éxito
        $mov = $cajaService->registrarMovimiento(
            $turno,
            'egreso',
            50000,
            'Pago proveedor flores',
            'efectivo',
            null,
            $this->admin->name,
            $this->cajeroSucursalA
        );

        $this->assertNotNull($mov);
        $this->assertEquals(50000, $mov->monto);
    }
}
