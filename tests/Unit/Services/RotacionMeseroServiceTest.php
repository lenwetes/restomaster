<?php

namespace Tests\Unit\Services;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Zona;
use App\Services\RotacionMeseroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotacionMeseroServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $mesero1;

    protected User $mesero2;

    protected User $mesero3;

    protected Zona $zonaSalon;

    protected Zona $zonaTerraza;

    protected RotacionMeseroService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sucursal Prueba']);
        $roleMesero = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero']);

        $this->mesero1 = User::create([
            'name' => 'Mesero Uno',
            'email' => 'mesero1@resto.test',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesero2 = User::create([
            'name' => 'Mesero Dos',
            'email' => 'mesero2@resto.test',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesero3 = User::create([
            'name' => 'Mesero Tres',
            'email' => 'mesero3@resto.test',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->zonaSalon = Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Salón Principal',
            'slug' => 'salon',
            'color' => 'terracota',
            'icono' => 'mesa',
            'activa' => true,
            'orden' => 1,
        ]);

        $this->zonaTerraza = Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Terraza',
            'slug' => 'terraza',
            'color' => 'salvia',
            'icono' => 'terraza',
            'activa' => true,
            'orden' => 2,
        ]);

        $this->service = app(RotacionMeseroService::class);
    }

    public function test_asignar_y_remover_meseros_de_zonas(): void
    {
        $rotacion = $this->service->asignarMeseroAZona($this->sucursal->id, 'salon', $this->mesero1->id, $this->zonaSalon->id);

        $this->assertDatabaseHas('rotaciones_meseros', [
            'id' => $rotacion->id,
            'sucursal_id' => $this->sucursal->id,
            'zona_slug' => 'salon',
            'user_id' => $this->mesero1->id,
            'activo' => true,
        ]);

        $this->service->removerMeseroDeZona($rotacion->id);

        $this->assertDatabaseMissing('rotaciones_meseros', [
            'id' => $rotacion->id,
        ]);
    }

    public function test_round_robin_rota_meseros_equitativamente_por_zona(): void
    {
        $this->service->asignarMeseroAZona($this->sucursal->id, 'salon', $this->mesero1->id, $this->zonaSalon->id);
        $this->service->asignarMeseroAZona($this->sucursal->id, 'salon', $this->mesero2->id, $this->zonaSalon->id);

        // Primer turno debe asignar a uno
        $elegido1 = $this->service->obtenerSiguienteMeseroParaZona('salon', $this->sucursal->id);
        $this->assertNotNull($elegido1);

        // Segundo turno debe rotar al otro mesero
        $elegido2 = $this->service->obtenerSiguienteMeseroParaZona('salon', $this->sucursal->id);
        $this->assertNotNull($elegido2);
        $this->assertNotEquals($elegido1->id, $elegido2->id);

        // Tercer turno regresa al primero
        $elegido3 = $this->service->obtenerSiguienteMeseroParaZona('salon', $this->sucursal->id);
        $this->assertEquals($elegido1->id, $elegido3->id);
    }

    public function test_menor_carga_selecciona_mesero_con_menos_mesas(): void
    {
        $this->service->guardarModoRotacion($this->sucursal->id, 'menor_carga');

        $this->service->asignarMeseroAZona($this->sucursal->id, 'salon', $this->mesero1->id, $this->zonaSalon->id);
        $this->service->asignarMeseroAZona($this->sucursal->id, 'salon', $this->mesero2->id, $this->zonaSalon->id);

        // Asignar 2 mesas ocupadas al mesero 1
        Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M1',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::OCUPADA->value,
            'mesero_id' => $this->mesero1->id,
        ]);
        Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'M2',
            'zona' => 'salon',
            'capacidad' => 4,
            'estado' => MesaEstado::OCUPADA->value,
            'mesero_id' => $this->mesero1->id,
        ]);

        // El mesero 2 tiene 0 mesas, debe ser seleccionado
        $elegido = $this->service->obtenerSiguienteMeseroParaZona('salon', $this->sucursal->id);
        $this->assertEquals($this->mesero2->id, $elegido->id);
    }

    public function test_autoasignar_mesa_asigna_mesero_de_zona(): void
    {
        $this->service->asignarMeseroAZona($this->sucursal->id, 'terraza', $this->mesero3->id, $this->zonaTerraza->id);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'T1',
            'zona' => 'terraza',
            'capacidad' => 2,
            'estado' => MesaEstado::LIBRE->value,
            'mesero_id' => null,
        ]);

        $mesero = $this->service->autoasignarMesa($mesa);

        $this->assertEquals($this->mesero3->id, $mesero->id);
        $this->assertEquals($this->mesero3->id, $mesa->fresh()->mesero_id);
    }

    public function test_asignar_mesa_y_mesero_a_reserva(): void
    {
        $this->service->asignarMeseroAZona($this->sucursal->id, 'salon', $this->mesero1->id, $this->zonaSalon->id);

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'VIP-1',
            'zona' => 'salon',
            'capacidad' => 6,
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $reserva = Reserva::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Familia Gómez',
            'telefono_contacto' => '3001234567',
            'fecha' => now()->toDateString(),
            'hora_llegada' => '20:00',
            'personas' => 4,
            'estado' => 'confirmada',
        ]);

        $mesero = $this->service->asignarMesaYMeseroAReserva($reserva, $mesa);

        $this->assertEquals($this->mesero1->id, $mesero->id);
        $this->assertTrue($reserva->mesas()->where('mesas.id', $mesa->id)->exists());
        $this->assertEquals($this->mesero1->id, $mesa->fresh()->mesero_id);
    }
}
