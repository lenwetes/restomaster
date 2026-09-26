<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Role;
use App\Models\RotacionZona;
use App\Models\Sucursal;
use App\Models\TurnoMeseroZona;
use App\Models\User;
use App\Models\Zona;
use App\Services\ReservaService;
use App\Services\RotacionMeseroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotacionMeseroZonaTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected Zona $zonaSalon;

    protected Zona $zonaTerraza;

    protected User $mesero1;

    protected User $mesero2;

    protected User $mesero3;

    protected User $admin;

    protected RotacionMeseroService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Principal',
            'direccion' => 'Calle 100 # 15-20',
            'telefono' => '3001234567',
            'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesero1 = User::create([
            'name' => 'Mesero Uno',
            'email' => 'mesero1@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesero2 = User::create([
            'name' => 'Mesero Dos',
            'email' => 'mesero2@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesero3 = User::create([
            'name' => 'Mesero Tres',
            'email' => 'mesero3@test.com',
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
            'orden' => 1,
            'activa' => true,
        ]);

        $this->zonaTerraza = Zona::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Terraza',
            'slug' => 'terraza',
            'color' => 'salvia',
            'icono' => 'terraza',
            'orden' => 2,
            'activa' => true,
        ]);

        $this->service = app(RotacionMeseroService::class);
    }

    public function test_configurar_rotacion_crea_cola_ordenada(): void
    {
        $rotacion = $this->service->configurarRotacion(
            $this->zonaSalon,
            [$this->mesero1->id, $this->mesero2->id, $this->mesero3->id],
            'automatico',
            $this->sucursal->id
        );

        $this->assertInstanceOf(RotacionZona::class, $rotacion);
        $this->assertTrue($rotacion->activa);
        $this->assertSame('automatico', $rotacion->modo);

        $cola = $this->service->obtenerColaPorZona($this->zonaSalon->id);
        $this->assertCount(3, $cola);
        $this->assertSame($this->mesero1->id, $cola[0]->mesero_id);
        $this->assertSame(1, $cola[0]->orden);
        $this->assertSame($this->mesero2->id, $cola[1]->mesero_id);
        $this->assertSame(2, $cola[1]->orden);
        $this->assertSame($this->mesero3->id, $cola[2]->mesero_id);
        $this->assertSame(3, $cola[2]->orden);
    }

    public function test_asignar_mesa_automatico_rota_round_robin_y_avanza(): void
    {
        $this->service->configurarRotacion(
            $this->zonaSalon,
            [$this->mesero1->id, $this->mesero2->id],
            'automatico',
            $this->sucursal->id
        );

        $mesa1 = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '1',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $mesa2 = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '2',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
        ]);

        // 1ra asignación -> debe ser mesero 1
        $asignado1 = $this->service->asignarMesaAutomatico($mesa1);
        $this->assertNotNull($asignado1);
        $this->assertSame($this->mesero1->id, $asignado1->id);
        $this->assertSame($this->mesero1->id, $mesa1->fresh()->mesero_id);

        // TurnoMeseroZona de mesero 1 debe tener mesas_activas = 1
        $turno1 = TurnoMeseroZona::where('zona_id', $this->zonaSalon->id)->where('mesero_id', $this->mesero1->id)->first();
        $this->assertSame(1, $turno1->mesas_activas);

        // 2da asignación -> debe ser mesero 2
        $asignado2 = $this->service->asignarMesaAutomatico($mesa2);
        $this->assertNotNull($asignado2);
        $this->assertSame($this->mesero2->id, $asignado2->id);
        $this->assertSame($this->mesero2->id, $mesa2->fresh()->mesero_id);

        // Cola rotó: mesero 1 vuelve a quedar al inicio para la 3ra asignación
        $mesa3 = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '3',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $asignado3 = $this->service->asignarMesaAutomatico($mesa3);
        $this->assertSame($this->mesero1->id, $asignado3->id);
    }

    public function test_liberar_mesa_decrementa_contador_mesas_activas(): void
    {
        $this->service->configurarRotacion(
            $this->zonaSalon,
            [$this->mesero1->id],
            'automatico',
            $this->sucursal->id
        );

        $mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '10',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $this->service->asignarMesaAutomatico($mesa);
        $turno = TurnoMeseroZona::where('zona_id', $this->zonaSalon->id)->where('mesero_id', $this->mesero1->id)->first();
        $this->assertSame(1, $turno->mesas_activas);

        $this->service->liberarMesa($mesa);
        $this->assertSame(0, $turno->fresh()->mesas_activas);
    }

    public function test_reordenar_cola_modifica_posiciones(): void
    {
        $this->service->configurarRotacion(
            $this->zonaSalon,
            [$this->mesero1->id, $this->mesero2->id, $this->mesero3->id],
            'automatico',
            $this->sucursal->id
        );

        // Reordenar invertido: mesero 3 primero, luego 2, luego 1
        $this->service->reordenarCola($this->zonaSalon->id, [
            $this->mesero3->id,
            $this->mesero2->id,
            $this->mesero1->id,
        ]);

        $cola = $this->service->obtenerColaPorZona($this->zonaSalon->id);
        $this->assertSame($this->mesero3->id, $cola[0]->mesero_id);
        $this->assertSame($this->mesero2->id, $cola[1]->mesero_id);
        $this->assertSame($this->mesero1->id, $cola[2]->mesero_id);
    }

    public function test_reserva_confirmada_asigna_mesa_en_zona_preferida_y_mesero_automatico(): void
    {
        $this->service->configurarRotacion(
            $this->zonaTerraza,
            [$this->mesero2->id],
            'automatico',
            $this->sucursal->id
        );

        $mesaSalon = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '101',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $mesaTerraza = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '201',
            'capacidad' => 4,
            'zona' => 'terraza',
            'estado' => MesaEstado::LIBRE->value,
        ]);

        $reservaService = app(ReservaService::class);

        $reserva = $reservaService->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Carlos López',
            'telefono_contacto' => '3109876543',
            'fecha' => now()->toDateString(),
            'hora_llegada' => '14:00',
            'personas' => 2,
            'zona_preferida_id' => $this->zonaTerraza->id,
        ]);

        $reservaService->confirmar($reserva, $this->admin);

        $reservaFresh = $reserva->fresh(['mesas', 'mesero']);
        $this->assertSame('confirmada', $reservaFresh->estado);
        $this->assertTrue($reservaFresh->asignacion_automatica);
        $this->assertSame($this->mesero2->id, $reservaFresh->mesero_id);

        // Verificamos que se asignó la mesa de Terraza (su zona preferida)
        $this->assertTrue($reservaFresh->mesas->contains('id', $mesaTerraza->id));
        $this->assertFalse($reservaFresh->mesas->contains('id', $mesaSalon->id));
    }
}
