<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemediacionCredencialDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_existe_password_demo_conocido_en_seeder_ni_login(): void
    {
        $seeder = file_get_contents(database_path('seeders/AdminUserSeeder.php'));
        $login = file_get_contents(resource_path('views/livewire/pages/auth/login.blade.php'));
        $envExample = file_get_contents(base_path('.env.example'));

        foreach (['restomaster2026', 'sushixpress2026', 'password123'] as $cred) {
            $this->assertStringNotContainsString($cred, $seeder, "Seeder contiene fallback demo {$cred}.");
            $this->assertStringNotContainsString($cred, $login, "Login blade contiene fallback demo {$cred}.");
            $this->assertStringNotContainsString($cred, $envExample, ".env.example contiene {$cred}.");
        }
    }
}
