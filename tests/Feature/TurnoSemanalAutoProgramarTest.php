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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3.2 — Auto-programación equitativa + modo respetar-hoy.
 */
class TurnoSemanalAutoProgramarTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $admin;

    /** @var array<int, User> */
    protected array $meseros = [];

    /** @var array<int, Zona> */
    protected array $zonas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sede AutoProg Test']);

        $roleAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'email' => 'admin.autoprog@test.local',
        ]);

        foreach (['Ana', 'Luis', 'Sofia', 'Pedro'] as $i => $nombre) {
            $this->meseros[] = User::factory()->create([
                'role_id' => $roleMesero->id,
                'sucursal_id' => $this->sucursal->id,
                'name' => $nombre.' Mesero',
                'email' => "mesero.autoprog{$i}@test.local",
                'activo' => true,
            ]);
        }

        foreach (['Salón' => 'salon', 'Barra' => 'barra', 'Terraza' => 'terraza'] as $nombre => $slug) {
            $this->zonas[] = Zona::create([
                'sucursal_id' => $this->sucursal->id,
                'nombre' => $nombre,
                'slug' => $slug,
                'color' => 'terracota',
                'icono' => 'mesa',
                'activa' => true,
                'orden' => count($this->zonas) + 1,
            ]);
        }

        PlantillaTurno::create([
            'nombre' => 'Almuerzo',
            'hora_inicio' => '11:00',
            'hora_fin' => '16:00',
            'zona_default_id' => $this->zonas[0]->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
        PlantillaTurno::create([
            'nombre' => 'Cena',
            'hora_inicio' => '17:00',
            'hora_fin' => '23:00',
            'zona_default_id' => $this->zonas[1]->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);
    }

    public function test_auto_programar_distribuye_equilibrado_entre_meseros_y_zonas(): void
    {
        $svc = app(TurnoSemanalService::class);
        $semanaFutura = (int) Carbon::now()->addWeeks(2)->isoWeek;
        $anio = (int) Carbon::now()->addWeeks(2)->isoWeekYear;
        $prog = $svc->obtenerOCrearSemana($this->sucursal->id, $semanaFutura, $anio, $this->admin);

        $total = $svc->autoProgramar($prog->id, $this->admin);

        $this->assertGreaterThan(0, $total);

        foreach ($this->meseros as $mesero) {
            $conteo = TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
                ->where('user_id', $mesero->id)
                ->where('es_descanso', false)
                ->count();
            $this->assertGreaterThanOrEqual(6, $conteo, "Mesero {$mesero->name} con pocos turnos: {$conteo}");
        }

        $conteos = [];
        foreach ($this->meseros as $mesero) {
            $conteos[] = TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
                ->where('user_id', $mesero->id)->where('es_descanso', false)->count();
        }
        $this->assertLessThanOrEqual(1, max($conteos) - min($conteos));

        $zonasUsadas = TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
            ->whereNotNull('zona_id')->distinct('zona_id')->count('zona_id');
        $this->assertGreaterThanOrEqual(2, $zonasUsadas);
    }

    public function test_auto_programar_modo_respetar_hoy_no_toca_dias_pasados(): void
    {
        $svc = app(TurnoSemanalService::class);
        $semanaActual = (int) Carbon::today()->isoWeek;
        $anioActual = (int) Carbon::today()->isoWeekYear;
        $prog = $svc->obtenerOCrearSemana($this->sucursal->id, $semanaActual, $anioActual, $this->admin);

        $lunes = Carbon::today()->startOfWeek();
        $pasado = $lunes->copy();
        $marcadorPasado = null;
        if ($pasado->isPast() && ! $pasado->isToday()) {
            $marcadorPasado = TurnoMeseroSemana::create([
                'programacion_semanal_id' => $prog->id,
                'user_id' => $this->meseros[0]->id,
                'fecha' => $pasado->toDateString(),
                'zona_id' => $this->zonas[0]->id,
                'es_descanso' => true,
            ]);
        }

        $svc->autoProgramar($prog->id, $this->admin, true);

        if ($marcadorPasado) {
            $this->assertDatabaseHas('turnos_meseros_semana', [
                'id' => $marcadorPasado->id,
                'es_descanso' => true,
                'zona_id' => $this->zonas[0]->id,
            ]);
        }

        $hoy = Carbon::today()->toDateString();
        $futuros = TurnoMeseroSemana::where('programacion_semanal_id', $prog->id)
            ->whereDate('fecha', '>=', $hoy)
            ->count();
        $this->assertGreaterThan(0, $futuros);
    }

    public function test_auto_programar_requiere_admin_o_gerente(): void
    {
        $svc = app(TurnoSemanalService::class);
        $prog = ProgramacionSemanal::create([
            'semana_iso' => 41,
            'anio' => 2026,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'borrador',
        ]);

        $this->expectException(AuthorizationException::class);
        $svc->autoProgramar($prog->id, $this->meseros[0]);
    }
}
