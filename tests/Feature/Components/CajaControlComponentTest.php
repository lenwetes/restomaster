<?php

namespace Tests\Feature\Components;

use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CajaControlComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private Sucursal $sucursal;

    private Caja $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $roleCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'RESTOMASTER Provenza',
            'codigo' => 'PRV-01',
            'activa' => true,
        ]);

        $this->cajero = User::factory()->create([
            'role_id' => $roleCajero->id,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->caja = app(CajaService::class)->crearCaja([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Barra Provenza',
            'codigo' => 'CAJ-BARRA-01',
            'activa' => true,
        ], $this->cajero);
    }

    /**
     * Test de renderizado del panel de control de caja sin turnos abiertos.
     */
    public function test_caja_control_muestra_opcion_de_apertura_si_no_hay_turno(): void
    {
        $this->actingAs($this->cajero);

        Volt::test('caja.control')
            ->assertSee('No hay ningún turno de caja abierto')
            ->assertSee('Abrir Turno de Caja con Fondo Inicial')
            ->assertSet('turnoId', null);
    }

    /**
     * Test de apertura de turno interactiva desde el componente.
     */
    public function test_abrir_turno_desde_componente_crea_turno_en_bd(): void
    {
        $this->actingAs($this->cajero);

        Volt::test('caja.control')
            ->set('cajaSeleccionadaId', $this->caja->id)
            ->set('fondoInicial', 200000.0)
            ->set('notasApertura', 'Apertura con billetes de baja denominación')
            ->call('abrirTurno');

        $this->assertDatabaseHas('turnos_caja', [
            'caja_id' => $this->caja->id,
            'user_id' => $this->cajero->id,
            'estado' => 'abierto',
            'monto_inicial' => 200000.0,
        ]);
    }

    /**
     * Test de registro de egreso y cierre de turno con arqueo.
     */
    public function test_registrar_egreso_y_cerrar_turno(): void
    {
        $this->actingAs($this->cajero);

        $turno = app(CajaService::class)->abrirTurno(
            caja: $this->caja,
            cajero: $this->cajero,
            fondoInicial: 200000.0
        );

        $component = Volt::test('caja.control')
            ->set('turnoId', $turno->id)
            ->set('tipoMovimiento', 'egreso')
            ->set('montoMovimiento', 30000.0)
            ->set('conceptoMovimiento', 'Bolsas y empaques delivery')
            ->set('autorizadoPor', 'Gerente Carlos Ruiz')
            ->call('registrarMovimiento');

        $this->assertDatabaseHas('movimientos_caja', [
            'turno_caja_id' => $turno->id,
            'tipo' => 'egreso',
            'monto' => 30000.0,
        ]);

        // Cierre de turno: esperado 170.000, contado 170.000
        $component
            ->set('montoContado', 170000.0)
            ->set('notasCierre', 'Cierre cuadrado')
            ->call('ejecutarCierreTurno');

        $turno->refresh();
        $this->assertSame('cerrado', $turno->estado);
        $this->assertEquals(0.0, (float) $turno->diferencia);
    }

    /**
     * Test de cajero en Sucursal 2: abre caja sin error 404 aún existiendo cajas en Sucursal 1.
     */
    public function test_cajero_sucursal_2_abre_caja_sin_error_404_con_multiples_sucursales(): void
    {
        $sucursal2 = Sucursal::create([
            'nombre' => 'RESTOMASTER Poblado',
            'codigo' => 'POB-02',
            'activa' => true,
        ]);

        $cajeroSede2 = User::factory()->create([
            'role_id' => Role::where('slug', 'cajero')->first()->id,
            'sucursal_id' => $sucursal2->id,
        ]);

        $this->actingAs($cajeroSede2);

        // Mount should auto-provision a Caja for Sede 2 and set it as selected
        $component = Volt::test('caja.control');

        $this->assertDatabaseHas('cajas', [
            'sucursal_id' => $sucursal2->id,
            'activa' => true,
        ]);

        $cajaSede2 = Caja::where('sucursal_id', $sucursal2->id)->first();
        $this->assertNotNull($cajaSede2);

        $component
            ->set('cajaSeleccionadaId', $cajaSede2->id)
            ->set('fondoInicial', 150000.0)
            ->call('abrirTurno')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('turnos_caja', [
            'caja_id' => $cajaSede2->id,
            'user_id' => $cajeroSede2->id,
            'estado' => 'abierto',
            'monto_inicial' => 150000.0,
        ]);
    }

    /**
     * Test de selección de caja ajena no lanza 404 sino error de validación.
     */
    public function test_cajero_intentando_abrir_caja_de_otra_sucursal_no_lanza_404(): void
    {
        $sucursal2 = Sucursal::create([
            'nombre' => 'RESTOMASTER Laureles',
            'codigo' => 'LAU-03',
            'activa' => true,
        ]);

        $cajeroSede2 = User::factory()->create([
            'role_id' => Role::where('slug', 'cajero')->first()->id,
            'sucursal_id' => $sucursal2->id,
        ]);

        $this->actingAs($cajeroSede2);

        // Intentar abrir la caja de la Sede 1 ($this->caja->id)
        Volt::test('caja.control')
            ->set('cajaSeleccionadaId', $this->caja->id)
            ->set('fondoInicial', 100000.0)
            ->call('abrirTurno')
            ->assertHasErrors(['cajaSeleccionadaId']);
    }
}
