<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\TrabajadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthorizePoliciesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $gerente;

    private User $cajero;

    private User $mesero;

    private User $cocina;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin', 'descripcion' => 'Admin']);
        $roleGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente', 'descripcion' => 'Gerente']);
        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero', 'descripcion' => 'Cajero']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero', 'descripcion' => 'Mesero']);
        $roleCocina = Role::create(['nombre' => 'Cocina', 'slug' => 'cocina', 'descripcion' => 'Cocina']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $this->admin = User::factory()->create(['role_id' => $roleAdmin->id, 'sucursal_id' => $this->sucursal->id]);
        $this->gerente = User::factory()->create(['role_id' => $roleGerente->id, 'sucursal_id' => $this->sucursal->id]);
        $this->cajero = User::factory()->create(['role_id' => $roleCajero->id, 'sucursal_id' => $this->sucursal->id]);
        $this->mesero = User::factory()->create(['role_id' => $roleMesero->id, 'sucursal_id' => $this->sucursal->id]);
        $this->cocina = User::factory()->create(['role_id' => $roleCocina->id, 'sucursal_id' => $this->sucursal->id]);
    }

    public function test_cajero_no_puede_crear_nueva_caja_terminal(): void
    {
        $this->actingAs($this->cajero);

        Volt::test('caja.control')
            ->set('formCaja.nombre', 'Terminal Fraudulenta')
            ->set('formCaja.codigo', 'CAJ-999')
            ->call('guardarNuevaCaja')
            ->assertForbidden();
    }

    public function test_gerente_puede_crear_nueva_caja_terminal(): void
    {
        $this->actingAs($this->gerente);

        Volt::test('caja.control')
            ->set('formCaja.nombre', 'Terminal Terraza')
            ->set('formCaja.codigo', 'CAJ-TERRAZA')
            ->call('guardarNuevaCaja')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cajas', ['codigo' => 'CAJ-TERRAZA']);
    }

    public function test_mesero_no_puede_crear_ni_eliminar_mesas(): void
    {
        $this->actingAs($this->mesero);

        Volt::test('mesas.index')
            ->set('formMesa.numero', '99')
            ->set('formMesa.zona', 'salon')
            ->set('formMesa.capacidad', 4)
            ->set('formMesa.sucursal_id', $this->sucursal->id)
            ->call('guardarMesa')
            ->assertForbidden();
    }

    public function test_cambiar_estado_mesa_rechaza_estado_invalido(): void
    {
        $this->actingAs($this->mesero);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '10',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => 'libre',
        ]);

        Volt::test('mesas.index')
            ->call('cambiarEstado', $mesa->id, 'estado_inexistente_hack')
            ->assertStatus(422);
    }

    public function test_cajero_no_puede_registrar_compras_o_mermas_en_inventario(): void
    {
        $this->actingAs($this->cajero);

        $insumo = Insumo::create([
            'nombre' => 'Atún Rojo',
            'codigo' => 'INS-ATUN',
            'categoria' => 'pescados',
            'unidad_medida' => 'kg',
            'stock_actual' => 5,
            'stock_minimo' => 1,
            'costo_unitario' => 60000,
        ]);

        Volt::test('inventario.index')
            ->set('selectedInsumoId', $insumo->id)
            ->set('mermaCantidad', 1)
            ->set('mermaMotivo', 'Prueba no autorizada')
            ->call('registrarMerma')
            ->assertForbidden();
    }

    public function test_cajero_no_puede_guardar_conexion_db_en_configuracion(): void
    {
        $this->actingAs($this->cajero);

        Volt::test('configuracion.index')
            ->call('guardarConexionDb')
            ->assertForbidden();
    }

    public function test_admin_super_usuario_puede_realizar_cualquier_mutacion(): void
    {
        $this->actingAs($this->admin);

        Volt::test('configuracion.index')
            ->call('guardarConexionDb')
            ->assertHasNoErrors();
    }

    public function test_crear_trabajador_sin_password_no_usa_secret_sino_password_aleatorio(): void
    {
        $trabajadorService = app(TrabajadorService::class);
        $user = $trabajadorService->crear([
            'nombre' => 'Test Worker',
            'email' => 'worker_secure@sushixpress.com',
            'role_id' => $this->mesero->role_id,
        ]);

        $this->assertFalse(Hash::check('secret', $user->password));
        $this->assertFalse(Hash::check('123456', $user->password));
        $this->assertNotEmpty($user->password);
    }

    public function test_cajero_no_puede_auto_autorizarse_retiro_o_egreso(): void
    {
        $this->actingAs($this->cajero);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Test',
            'codigo' => 'CAJ-ATT',
            'activa' => true,
        ]);

        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->cajero->id,
            'apertura_en' => now(),
            'monto_inicial' => 100000,
            'estado' => 'abierto',
        ]);

        Volt::test('caja.control')
            ->set('turnoId', $turno->id)
            ->set('tipoMovimiento', 'egreso')
            ->set('montoMovimiento', 20000)
            ->set('conceptoMovimiento', 'Compra suministros')
            ->set('autorizadoPor', $this->cajero->name)
            ->call('registrarMovimiento')
            ->assertHasErrors(['autorizadoPor']);
    }

    public function test_caja_service_rechaza_tipo_movimiento_invalido(): void
    {
        $caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Test 2',
            'codigo' => 'CAJ-ATT2',
            'activa' => true,
        ]);

        $turno = TurnoCaja::create([
            'caja_id' => $caja->id,
            'user_id' => $this->cajero->id,
            'apertura_en' => now(),
            'monto_inicial' => 100000,
            'estado' => 'abierto',
        ]);

        $cajaService = app(CajaService::class);

        $this->expectException(\InvalidArgumentException::class);
        $cajaService->registrarMovimiento($turno, 'tipo_invalido', 1000, 'Prueba');
    }
}
