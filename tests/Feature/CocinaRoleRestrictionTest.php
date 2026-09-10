<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ConfiguracionService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CocinaRoleRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SucursalSeeder::class);
    }

    public function test_cocina_user_is_redirected_from_dashboard_to_cocina(): void
    {
        $cocinaRole = Role::where('slug', 'cocina')->first();
        $user = User::factory()->create([
            'role_id' => $cocinaRole->id,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/cocina');
    }

    public function test_barra_user_is_redirected_from_dashboard_to_cocina(): void
    {
        $barraRole = Role::where('slug', 'barra')->first();
        $user = User::factory()->create([
            'role_id' => $barraRole->id,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/cocina');
    }

    public function test_cocina_user_only_sees_kds_in_navigation(): void
    {
        $cocinaRole = Role::where('slug', 'cocina')->first();
        $user = User::factory()->create([
            'role_id' => $cocinaRole->id,
        ]);

        $response = $this->actingAs($user)->get('/cocina');

        $response->assertOk();
        $response->assertSee('Pantalla KDS Cocina');
        $response->assertDontSee('Cajas');
        $response->assertDontSee('Cuentas por Pagar');
        $response->assertDontSee('Configuración');
    }

    public function test_configuracion_service_ticket_80mm_defaults(): void
    {
        $service = app(ConfiguracionService::class);
        $defaults = $service->valoresPorDefectoTicket80mm();

        $this->assertArrayHasKey('nombre_comercial', $defaults);
        $this->assertArrayHasKey('sugerir_propina', $defaults);
        $this->assertTrue($defaults['sugerir_propina']);
        $this->assertEquals(10, $defaults['porcentaje_propina']);
    }

    public function test_admin_can_update_ticket_80mm_settings(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        Volt::actingAs($admin)
            ->test('configuracion.index')
            ->set('tabActiva', 'factura')
            ->set('ticketForm.nombre_comercial', 'Sushixpress Master')
            ->set('ticketForm.pie_pagina', '¡Gracias por su compra!')
            ->call('guardarTicket')
            ->assertHasNoErrors()
            ->assertSee('Diseño de ticket térmico 80mm guardado.');

        $service = app(ConfiguracionService::class);
        $this->assertEquals('Sushixpress Master', $service->obtener('ticket_80mm', 'nombre_comercial'));
    }
}
