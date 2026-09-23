<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurnoCajaMultipleShiftsTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Caja $caja;

    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['slug' => 'cajero'],
            ['nombre' => 'Cajero']
        );

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Principal',
            'direccion' => 'Calle 10 # 40-20',
            'telefono' => '3001234567',
            'activa' => true,
        ]);

        $this->caja = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Principal #01 - Salón',
            'codigo' => 'CAJ-01',
            'activa' => true,
        ]);

        $this->cajero = User::factory()->create([
            'role_id' => $role->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    public function test_misma_caja_puede_tener_multiples_turnos_cerrados_y_abrir_uno_nuevo(): void
    {
        $cajaService = app(CajaService::class);

        // 1. Abrir primer turno
        $turno1 = $cajaService->abrirTurno($this->caja, $this->cajero, 100000.0, 'Apertura turno 1');
        $this->assertSame('abierto', $turno1->estado);
        $this->assertSame($this->caja->id, $turno1->caja_id);

        // 2. Cerrar primer turno
        $cajaService->cerrarTurno($turno1, 100000.0, $this->cajero, 'Cierre turno 1');
        $turno1->refresh();
        $this->assertSame('cerrado', $turno1->estado);

        // 3. Abrir un segundo turno en la MISMA caja (no debe fallar con Unique violation)
        $turno2 = $cajaService->abrirTurno($this->caja, $this->cajero, 150000.0, 'Apertura turno 2');
        $this->assertSame('abierto', $turno2->estado);
        $this->assertSame($this->caja->id, $turno2->caja_id);
        $this->assertNotSame($turno1->id, $turno2->id);

        // 4. Cerrar el segundo turno
        $cajaService->cerrarTurno($turno2, 150000.0, $this->cajero, 'Cierre turno 2');
        $turno2->refresh();
        $this->assertSame('cerrado', $turno2->estado);

        // 5. Abrir un tercer turno
        $turno3 = $cajaService->abrirTurno($this->caja, $this->cajero, 200000.0, 'Apertura turno 3');
        $this->assertSame('abierto', $turno3->estado);
        $this->assertSame(3, TurnoCaja::where('caja_id', $this->caja->id)->count());
    }

    public function test_no_se_puede_abrir_segundo_turno_si_ya_existe_uno_abierto_en_la_misma_caja(): void
    {
        $cajaService = app(CajaService::class);

        // Abrir primer turno
        $cajaService->abrirTurno($this->caja, $this->cajero, 100000.0, 'Turno en curso');

        // Intentar abrir otro turno mientras el anterior sigue abierto
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("La caja {$this->caja->nombre} ya tiene un turno abierto");

        $cajaService->abrirTurno($this->caja, $this->cajero, 100000.0, 'Intento concurrente');
    }

    public function test_componente_livewire_caja_control_permite_abrir_turno_con_historial_previo(): void
    {
        $cajaService = app(CajaService::class);

        // Crear turno previo ya cerrado
        $turnoPrevio = $cajaService->abrirTurno($this->caja, $this->cajero, 100000.0, 'Turno de ayer');
        $cajaService->cerrarTurno($turnoPrevio, 100000.0, $this->cajero, 'Cierre de ayer');

        // Probar apertura a través del componente Livewire Volt
        Volt::actingAs($this->cajero)
            ->test('caja.control')
            ->set('cajaSeleccionadaId', $this->caja->id)
            ->set('fondoInicial', 150000.0)
            ->set('notasApertura', 'Apertura desde Livewire')
            ->call('abrirTurno')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalApertura', false);

        $this->assertDatabaseHas('turnos_caja', [
            'caja_id' => $this->caja->id,
            'estado' => 'abierto',
            'monto_inicial' => 150000.0,
        ]);
    }

    public function test_auto_reparacion_de_restriccion_rigida_si_existe_en_base_de_datos(): void
    {
        $cajaService = app(CajaService::class);

        // Turno cerrado previo
        $t1 = $cajaService->abrirTurno($this->caja, $this->cajero, 50000.0);
        $cajaService->cerrarTurno($t1, 50000.0, $this->cajero);

        if (DB::getDriverName() === 'pgsql') {
            // Forzar en BD la restricción rígida previa
            DB::statement('DROP INDEX IF EXISTS turnos_caja_caja_id_abierto_unique');
            DB::statement('ALTER TABLE turnos_caja ADD CONSTRAINT turnos_caja_caja_id_abierto_unique UNIQUE (caja_id)');
        }

        // Al abrir un nuevo turno en la misma caja, el servicio auto-repara la restricción rígida
        $t2 = $cajaService->abrirTurno($this->caja, $this->cajero, 80000.0);
        $this->assertSame('abierto', $t2->estado);
        $this->assertSame(2, TurnoCaja::where('caja_id', $this->caja->id)->count());
    }

    public function test_selector_cambia_turno_activo_entre_cajas(): void
    {
        $cajaService = app(CajaService::class);

        $cajaBarra = Caja::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Caja Barra #02',
            'codigo' => 'CAJ-02',
            'activa' => true,
        ]);

        $turnoA = $cajaService->abrirTurno($this->caja, $this->cajero, 100000.0, 'Apertura salón');
        $turnoB = $cajaService->abrirTurno($cajaBarra, $this->cajero, 50000.0, 'Apertura barra');

        // El selector permite moverse entre turnos abiertos sin importar cuál tomó mount()
        Volt::actingAs($this->cajero)
            ->test('caja.control')
            ->assertSee('Operando en:')
            ->call('seleccionarTurno', $turnoA->id)
            ->assertSet('turnoId', $turnoA->id)
            ->assertSet('cajaSeleccionadaId', $this->caja->id)
            ->assertSet('montoContado', 0.0)
            ->call('seleccionarTurno', $turnoB->id)
            ->assertSet('turnoId', $turnoB->id)
            ->assertSet('cajaSeleccionadaId', $cajaBarra->id);
    }

    public function test_selector_rechaza_turno_de_otra_sucursal(): void
    {
        $sucursalB = Sucursal::create([
            'nombre' => 'Sucursal Norte',
            'direccion' => 'Calle 50 # 10-05',
            'telefono' => '3009998877',
            'activa' => true,
        ]);
        $cajaB = Caja::create([
            'sucursal_id' => $sucursalB->id,
            'nombre' => 'Caja Norte #01',
            'codigo' => 'CAJ-N01',
            'activa' => true,
        ]);
        $turnoB = app(CajaService::class)->abrirTurno($cajaB, $this->cajero, 70000.0, 'Apertura norte');

        // Misma regla que obtenerTurnoValido: fuera de sucursal no existe (404).
        $this->expectException(ModelNotFoundException::class);

        Volt::actingAs($this->cajero)
            ->test('caja.control')
            ->call('seleccionarTurno', $turnoB->id);
    }
}
