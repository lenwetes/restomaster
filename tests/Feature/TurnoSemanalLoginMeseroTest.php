<?php

namespace Tests\Feature;

use App\Models\PlantillaTurno;
use App\Models\ProgramacionSemanal;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoMeseroSemana;
use App\Models\User;
use App\Models\Zona;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Fase 4 — Aviso interactivo al login del mesero.
 *
 * El modal aparece solo si hay semana publicada y turnos sin confirmar.
 */
class TurnoSemanalLoginMeseroTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    protected User $mesero;

    protected Zona $zona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Login Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'admin.login@test.local',
        ]);
        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Juan Login',
            'email' => 'juan.login@test.local',
            'activo' => true,
        ]);

        $this->zona = Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Salón',
            'slug' => 'salon',
            'color' => 'terracota',
            'icono' => 'mesa',
            'activa' => true,
            'orden' => 1,
        ]);

        PlantillaTurno::create([
            'nombre' => 'Almuerzo',
            'hora_inicio' => '11:00',
            'hora_fin' => '16:00',
            'zona_default_id' => $this->zona->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    protected function publicarSemanaActual(): ProgramacionSemanal
    {
        $semana = (int) Carbon::today()->isoWeek;
        $anio = (int) Carbon::today()->isoWeekYear;

        $prog = ProgramacionSemanal::create([
            'semana_iso' => $semana,
            'anio' => $anio,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'borrador',
        ]);

        $lunes = Carbon::today()->startOfWeek();
        for ($i = 0; $i < 7; $i++) {
            TurnoMeseroSemana::create([
                'programacion_semanal_id' => $prog->id,
                'user_id' => $this->mesero->id,
                'fecha' => $lunes->copy()->addDays($i)->toDateString(),
                'zona_id' => $this->zona->id,
                'es_descanso' => $i === 2,
            ]);
        }

        $prog->update([
            'estado' => ProgramacionSemanal::ESTADO_PUBLICADO,
            'publicado_por' => $this->admin->id,
            'publicado_en' => now(),
        ]);

        return $prog->fresh();
    }

    public function test_modal_aparece_si_publicado_y_no_confirmado(): void
    {
        $this->publicarSemanaActual();

        Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->assertSet('mostrarModalTurnoSemanal', true)
            ->assertSee('TU HORARIO');
    }

    public function test_modal_no_aparece_si_semana_en_borrador(): void
    {
        $semana = (int) Carbon::today()->isoWeek;
        $anio = (int) Carbon::today()->isoWeekYear;

        $prog = ProgramacionSemanal::create([
            'semana_iso' => $semana,
            'anio' => $anio,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'borrador',
        ]);
        TurnoMeseroSemana::create([
            'programacion_semanal_id' => $prog->id,
            'user_id' => $this->mesero->id,
            'fecha' => Carbon::today()->toDateString(),
            'zona_id' => $this->zona->id,
            'es_descanso' => false,
        ]);

        Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->assertSet('mostrarModalTurnoSemanal', false);
    }

    public function test_modal_no_aparece_si_ya_confirmado(): void
    {
        $prog = $this->publicarSemanaActual();
        TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
            ->where('user_id', $this->mesero->id)
            ->update(['confirmado_por_mesero_en' => now()]);

        Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->assertSet('mostrarModalTurnoSemanal', false);
    }

    public function test_modal_no_aparece_para_admin_sin_turnos(): void
    {
        $this->publicarSemanaActual();

        Volt::actingAs($this->admin)
            ->test('pos.terminal')
            ->assertSet('mostrarModalTurnoSemanal', false);
    }

    public function test_confirmar_registra_confirmacion_y_limpia_notificacion(): void
    {
        $prog = $this->publicarSemanaActual();

        Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->assertSet('mostrarModalTurnoSemanal', true)
            ->call('confirmarTurnoSemanal')
            ->assertSet('mostrarModalTurnoSemanal', false)
            ->assertHasNoErrors();

        $this->assertEquals(0, TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
            ->where('user_id', $this->mesero->id)
            ->whereNull('confirmado_por_mesero_en')
            ->count());

        $this->assertDatabaseMissing('notificaciones_usuario', [
            'user_id' => $this->mesero->id,
            'tipo' => 'turno_semanal',
            'leida' => false,
        ]);
    }

    public function test_posponer_una_vez_oculta_modal_y_luego_exige_confirmar(): void
    {
        $this->publicarSemanaActual();

        Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->assertSet('mostrarModalTurnoSemanal', true)
            ->assertSee('Ver más tarde')
            ->call('posponerTurnoSemanal')
            ->assertSet('mostrarModalTurnoSemanal', false);

        Volt::actingAs($this->mesero)
            ->test('pos.terminal')
            ->assertSet('mostrarModalTurnoSemanal', true)
            ->assertDontSee('Ver más tarde');
    }

    public function test_notificacion_respaldo_visible_en_campana_hasta_confirmar(): void
    {
        $this->publicarSemanaActual();

        Volt::actingAs($this->mesero)->test('pos.terminal');

        $this->assertDatabaseHas('notificaciones_usuario', [
            'user_id' => $this->mesero->id,
            'tipo' => 'turno_semanal',
            'leida' => false,
        ]);

        Cache::forget('notif.resumen.'.$this->mesero->id);
        $resumen = app(NotificacionService::class)->obtenerResumen($this->mesero);

        $this->assertNotEmpty($resumen['turno_semanal']);
        $this->assertGreaterThan(0, $resumen['total']);
    }
}
