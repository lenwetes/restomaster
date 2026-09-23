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
            'nombre' => 'Sushixpress Provenza',
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
}
