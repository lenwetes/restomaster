<?php

namespace Tests\Feature;

use App\Models\PlantillaTurno;
use App\Models\ProgramacionSemanal;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoMeseroSemana;
use App\Models\User;
use App\Models\Zona;
use App\Services\TurnoSemanalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Fase 3.3 — Matriz semanal del administrador (Livewire turnos/index).
 */
class TurnoSemanalMatrizTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    protected User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Matriz Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'admin.matriz@test.local',
        ]);
        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Juan Matriz',
            'email' => 'juan.matriz@test.local',
            'activo' => true,
        ]);

        foreach (['Salón' => 'salon', 'Barra' => 'barra'] as $nombre => $slug) {
            Zona::create([
                'sucursal_id' => $this->sucursal->id,
                'nombre' => $nombre,
                'slug' => $slug,
                'color' => 'terracota',
                'icono' => 'mesa',
                'activa' => true,
                'orden' => 1,
            ]);
        }

        PlantillaTurno::create([
            'nombre' => 'Almuerzo',
            'hora_inicio' => '11:00',
            'hora_fin' => '16:00',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    public function test_admin_accede_a_turnos_y_ve_matriz(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('turnos'))->assertOk()->assertSeeVolt('turnos.index');

        Volt::test('turnos.index')
            ->assertSet('semanaIso', (int) Carbon::today()->isoWeek)
            ->assertSet('anio', (int) Carbon::today()->isoWeekYear);
    }

    public function test_mesero_no_accede_a_turnos(): void
    {
        $this->actingAs($this->mesero);
        $this->get(route('turnos'))->assertForbidden();
    }

    public function test_admin_auto_programa_desde_matriz(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turnos.index')
            ->call('autoProgramar')
            ->assertHasNoErrors();

        $semana = (int) Carbon::today()->isoWeek;
        $anio = (int) Carbon::today()->isoWeekYear;
        $prog = ProgramacionSemanal::where('sucursal_id', $this->sucursal->id)
            ->where('semana_iso', $semana)->where('anio', $anio)->first();

        $this->assertNotNull($prog);
        $this->assertGreaterThan(0, $prog->turnos()->count());
    }

    public function test_admin_publica_desde_matriz(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turnos.index')
            ->call('autoProgramar')
            ->call('publicar')
            ->assertHasNoErrors()
            ->assertSet('estado', 'publicado');

        $this->assertDatabaseHas('programaciones_semanales', [
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'publicado',
        ]);
    }

    public function test_admin_alterna_descanso_en_celda(): void
    {
        $this->actingAs($this->admin);

        $component = Volt::test('turnos.index')->call('autoProgramar');

        $turno = TurnoMeseroSemana::first();
        $this->assertNotNull($turno);

        $component->call('alternarDescanso', $turno->id)->assertHasNoErrors();

        $this->assertDatabaseHas('turnos_meseros_semana', [
            'id' => $turno->id,
            'es_descanso' => true,
        ]);
    }

    public function test_navegacion_semanal_avanza_y_retrocede_iso(): void
    {
        $this->actingAs($this->admin);

        $semana = (int) Carbon::today()->isoWeek;

        Volt::test('turnos.index')
            ->call('semanaSiguiente')
            ->assertSet('semanaIso', $semana === 52 || $semana === 53 ? 1 : $semana + 1)
            ->call('semanaAnterior')
            ->call('semanaAnterior')
            ->assertHasNoErrors();
    }

    public function test_matriz_usa_tokens_del_tema_y_muestra_datos(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turnos.index')
            ->call('autoProgramar')
            ->assertHasNoErrors()
            ->assertSee('Juan Matriz')
            ->assertSee('Salón')
            ->assertDontSee('text-gray-900', false);
    }

    public function test_admin_guarda_reglas_rotacion_desde_matriz(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turnos.index')
            ->set('reglasForm.longitud_ciclo', 2)
            ->set('reglasForm.impedir_zona_repetida', false)
            ->call('guardarReglas')
            ->assertHasNoErrors();

        $reglas = app(TurnoSemanalService::class)->obtenerReglasRotacion($this->sucursal->id);
        $this->assertEquals(2, $reglas['longitud_ciclo']);
        $this->assertFalse($reglas['impedir_zona_repetida']);
    }

    public function test_admin_equilibra_descansos_desde_matriz(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turnos.index')
            ->call('autoProgramar')
            ->call('equilibrarDescansos')
            ->assertHasNoErrors();
    }

    public function test_matriz_muestra_proyeccion_cuatro_semanas(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turnos.index')
            ->assertSee('Proyección de Descansos')
            ->assertSee('Reglas de Rotación');
    }
}
