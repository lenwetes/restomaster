<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoMeseroZona;
use App\Models\User;
use App\Models\Zona;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaAsignacionMesaMeseroTest extends TestCase
{
    use RefreshDatabase;

    private ReservaService $service;

    private Sucursal $sucursal;

    private User $mesero;

    private Zona $zona;

    private Mesa $mesa;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $rolMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Medellín',
            'codigo' => 'MDE-01',
            'direccion' => 'Calle 10',
            'activa' => true,
        ]);

        $this->service = app(ReservaService::class);

        $this->mesero = User::factory()->create([
            'name' => 'Carlos Mesero',
            'email' => 'carlos@restomaster.test',
            'role_id' => $rolMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->zona = Zona::create([
            'nombre' => 'Terraza VIP',
            'slug' => 'terraza',
            'sucursal_id' => $this->sucursal->id,
            'activa' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'zona_id' => $this->zona->id,
            'numero' => 10,
            'nombre' => 'Mesa 10 VIP',
            'zona' => 'terraza',
            'capacidad' => 4,
            'estado' => MesaEstado::LIBRE->value,
            'activa' => true,
        ]);

        // Registrar turno de mesero en la zona
        TurnoMeseroZona::create([
            'mesero_id' => $this->mesero->id,
            'zona_id' => $this->zona->id,
            'sucursal_id' => $this->sucursal->id,
            'orden' => 1,
            'mesas_activas' => 0,
            'activo' => true,
        ]);
    }

    public function test_asignar_mesa_manual_asigna_inmediatamente_el_mesero_de_turno(): void
    {
        $reserva = Reserva::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Santiago Valencia',
            'telefono_contacto' => '3001234567',
            'fecha' => now()->toDateString(),
            'hora_llegada' => '19:30',
            'personas' => 4,
            'duracion_min' => 90,
            'estado' => 'confirmada',
            'origen' => 'sistema',
        ]);

        $this->assertNull($reserva->mesero_id);
        $this->assertCount(0, $reserva->mesas);

        // Asignación manual
        $this->service->asignarMesa($reserva, $this->mesa);

        $reserva->refresh();
        $this->mesa->refresh();

        $this->assertCount(1, $reserva->mesas);
        $this->assertEquals($this->mesa->id, $reserva->mesas->first()->id);
        $this->assertEquals($this->mesero->id, $reserva->mesero_id, 'El mesero de turno debe asignarse inmediatamente a la reserva');
        $this->assertEquals($this->mesero->id, $this->mesa->mesero_id, 'El mesero de turno debe asignarse a la mesa');
    }

    public function test_auto_asignar_mesa_busca_disponible_y_asigna_mesero_de_turno(): void
    {
        $reserva = Reserva::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Camila Restrepo',
            'telefono_contacto' => '3019876543',
            'fecha' => now()->toDateString(),
            'hora_llegada' => '21:00',
            'personas' => 3,
            'duracion_min' => 90,
            'estado' => 'confirmada',
            'origen' => 'sistema',
        ]);

        $mesaAsignada = $this->service->autoAsignarMesa($reserva);

        $this->assertNotNull($mesaAsignada);
        $this->assertEquals($this->mesa->id, $mesaAsignada->id);

        $reserva->refresh();
        $this->assertCount(1, $reserva->mesas);
        $this->assertEquals($this->mesero->id, $reserva->mesero_id, 'Autoasignar debe vincular el mesero de turno al instante');
    }

    public function test_desasignar_mesa_libera_la_mesa_y_el_mesero(): void
    {
        $reserva = Reserva::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Laura Perez',
            'telefono_contacto' => '3000000000',
            'fecha' => now()->toDateString(),
            'hora_llegada' => '20:00',
            'personas' => 2,
            'duracion_min' => 90,
            'estado' => 'confirmada',
            'origen' => 'sistema',
        ]);

        $this->service->asignarMesa($reserva, $this->mesa);
        $reserva->refresh();
        $this->assertNotNull($reserva->mesero_id);
        $this->assertCount(1, $reserva->mesas);

        $this->service->desasignarMesas($reserva);
        $reserva->refresh();

        $this->assertCount(0, $reserva->mesas);
        $this->assertNull($reserva->mesero_id);
    }
}
