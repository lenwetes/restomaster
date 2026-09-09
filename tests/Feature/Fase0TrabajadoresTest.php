<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\TrabajadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase0TrabajadoresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $gerente;
    private TrabajadorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);
        $rolGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);
        $rolCajero = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero']);

        $this->admin = User::factory()->create(['role_id' => $rolAdmin->id]);
        $this->gerente = User::factory()->create(['role_id' => $rolGerente->id]);
        $this->cajero = User::factory()->create(['role_id' => $rolCajero->id]);

        $this->service = app(TrabajadorService::class);
    }

    public function test_pantalla_trabajadores_solo_disponible_para_admin(): void
    {
        $this->actingAs($this->gerente)->get(route('trabajadores'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('trabajadores'))->assertOk();
        $this->actingAs($this->admin)->get(route('trabajadores'))->assertSeeVolt('trabajadores.index');
        $this->actingAs($this->admin)->get(route('trabajadores'))->assertSee($this->cajero->name);
    }

    public function test_crear_trabajador_asigna_rol_y_hash_de_password(): void
    {
        $user = $this->service->crear([
            'name' => 'Ana Mesera',
            'email' => 'ana@test.com',
            'password' => 'Secret-123',
            'telefono' => '3001234567',
            'role_id' => $this->cajero->role_id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ana Mesera',
            'email' => 'ana@test.com',
            'role_id' => $this->cajero->role_id,
            'activo' => true,
        ]);
        $this->assertNotEquals('Secret-123', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Secret-123', $user->password));
    }

    public function test_crear_trabajador_rechaza_email_duplicado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->crear([
            'name' => 'Duplicado',
            'email' => $this->cajero->email,
            'password' => 'Secret-123',
            'role_id' => $this->cajero->role_id,
        ]);
    }

    public function test_desactivar_trabajador_con_historial_no_lo_elimina(): void
    {
        $this->service->desactivar($this->cajero);

        $this->assertSame(0, (int) $this->cajero->fresh()->activo);
        $this->assertDatabaseHas('users', ['id' => $this->cajero->id]);
    }

    public function test_admin_no_puede_desactivarse_a_si_mismo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->desactivar($this->admin);
    }

    public function test_editar_trabajador_actualiza_datos_y_rol(): void
    {
        $roleCajeroNuevo = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->service->actualizar($this->cajero, [
            'name' => 'Cajera Actualizada',
            'role_id' => $roleCajeroNuevo->id,
            'telefono' => '3115556677',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->cajero->id,
            'name' => 'Cajera Actualizada',
            'role_id' => $roleCajeroNuevo->id,
            'telefono' => '3115556677',
        ]);
    }

    public function test_componente_puede_abrir_modal_y_crear_desde_ui(): void
    {
        Volt::actingAs($this->admin)
            ->test('trabajadores.index')
            ->set('mostrarModalNuevo', true)
            ->set('nuevo.nombre', 'Mario Mesero')
            ->set('nuevo.email', 'mario@test.com')
            ->set('nuevo.password', 'Secret-123')
            ->set('nuevo.telefono', '3211112222')
            ->set('nuevo.role_id', $this->cajero->role_id)
            ->call('guardarNuevo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Mario Mesero', 'email' => 'mario@test.com']);
    }
}