<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase0RbacRutasTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(string $slugRol): User
    {
        $rol = Role::create([
            'nombre' => ucfirst($slugRol),
            'slug' => $slugRol,
        ]);

        return User::factory()->create([
            'role_id' => $rol->id,
            'activo' => true,
        ]);
    }

    public function test_mesero_accede_a_mesas_y_pos_pero_no_a_caja_ni_inventario(): void
    {
        $mesero = $this->crearUsuario('mesero');

        $this->actingAs($mesero)->get(route('mesas'))->assertOk();
        $this->actingAs($mesero)->get(route('pos'))->assertOk();
        $this->actingAs($mesero)->get(route('caja'))->assertForbidden();
        $this->actingAs($mesero)->get(route('inventario'))->assertForbidden();
        $this->actingAs($mesero)->get(route('cocina'))->assertForbidden();
    }

    public function test_cocina_accede_a_kds_pero_no_a_caja_ni_pos(): void
    {
        $cocinero = $this->crearUsuario('cocina');

        $this->actingAs($cocinero)->get(route('cocina'))->assertOk();
        $this->actingAs($cocinero)->get(route('caja'))->assertForbidden();
        $this->actingAs($cocinero)->get(route('pos'))->assertForbidden();
    }

    public function test_cajero_accede_a_caja_pos_cocina_e_inventario_pero_no_a_reportes(): void
    {
        $cajero = $this->crearUsuario('cajero');

        $this->actingAs($cajero)->get(route('caja'))->assertOk();
        $this->actingAs($cajero)->get(route('pos'))->assertOk();
        $this->actingAs($cajero)->get(route('cocina'))->assertOk();
        $this->actingAs($cajero)->get(route('inventario'))->assertOk();
        $this->actingAs($cajero)->get(route('reportes'))->assertForbidden();
    }

    public function test_gerente_accede_a_inventario_caja_y_reportes_operatorios(): void
    {
        $gerente = $this->crearUsuario('gerente');

        $this->actingAs($gerente)->get(route('inventario'))->assertOk();
        $this->actingAs($gerente)->get(route('caja'))->assertOk();
        $this->actingAs($gerente)->get(route('pos'))->assertOk();
    }

    public function test_admin_accede_a_todos_los_modulos(): void
    {
        $admin = $this->crearUsuario('admin');

        $this->actingAs($admin)->get(route('mesas'))->assertOk();
        $this->actingAs($admin)->get(route('pos'))->assertOk();
        $this->actingAs($admin)->get(route('cocina'))->assertOk();
        $this->actingAs($admin)->get(route('caja'))->assertOk();
        $this->actingAs($admin)->get(route('inventario'))->assertOk();
    }
}
