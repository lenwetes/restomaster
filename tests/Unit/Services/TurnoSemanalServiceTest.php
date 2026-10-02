<?php

namespace Tests\Unit\Services;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\TurnoSemanalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnoSemanalServiceTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $admin;

    private User $mesero;

    private TurnoSemanalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'codigo' => 'PRV-01',
            'direccion' => 'Cra 35 # 8A-12',
            'activa' => true,
        ]);

        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);
        $roleMesero = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero']);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'sucursal_id' => $this->sucursal->id,
            'is_active' => true,
        ]);

        $this->mesero = User::create([
            'name' => 'Mesero Test',
            'email' => 'mesero@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'is_active' => true,
        ]);

        $this->service = app(TurnoSemanalService::class);
    }

    public function test_solo_admin_o_gerente_pueden_gestionar_programacion_semanal(): void
    {
        $this->expectException(AuthorizationException::class);

        // Un mesero no tiene permiso para crear o ver programación
        $this->service->obtenerOCrearSemana($this->sucursal->id, 40, 2026, $this->mesero);
    }

    public function test_creacion_idempotente_de_semana_iso_por_admin(): void
    {
        $prog1 = $this->service->obtenerOCrearSemana($this->sucursal->id, 42, 2026, $this->admin);
        $this->assertEquals(42, $prog1->semana_iso);
        $this->assertEquals(2026, $prog1->anio);
        $this->assertEquals('borrador', $prog1->estado);

        // Llamada idéntica no debe duplicar registro
        $prog2 = $this->service->obtenerOCrearSemana($this->sucursal->id, 42, 2026, $this->admin);
        $this->assertEquals($prog1->id, $prog2->id);
    }

    public function test_rechazo_de_semana_iso_fuera_de_rango(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->obtenerOCrearSemana($this->sucursal->id, 55, 2026, $this->admin);
    }
}
