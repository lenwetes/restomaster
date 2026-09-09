<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-admin-only', function () {
            return 'admin-ok';
        })->middleware(['auth', 'role:admin']);

        Route::get('/test-pos-access', function () {
            return 'pos-ok';
        })->middleware(['auth', 'role:cajero,mesero']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/test-admin-only');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_any_role_protected_route(): void
    {
        $adminRole = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->get('/test-pos-access');
        $response->assertOk();
        $response->assertSee('pos-ok');
    }

    public function test_user_with_allowed_role_can_access_route(): void
    {
        $meseroRole = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        $mesero = User::factory()->create(['role_id' => $meseroRole->id]);

        $response = $this->actingAs($mesero)->get('/test-pos-access');
        $response->assertOk();
        $response->assertSee('pos-ok');
    }

    public function test_user_with_unauthorized_role_gets_403(): void
    {
        $cocinaRole = Role::create(['nombre' => 'Cocina', 'slug' => 'cocina']);
        $cocinero = User::factory()->create(['role_id' => $cocinaRole->id]);

        $response = $this->actingAs($cocinero)->get('/test-pos-access');
        $response->assertForbidden();
    }
}
