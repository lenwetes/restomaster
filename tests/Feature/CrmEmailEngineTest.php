<?php

namespace Tests\Feature;

use App\Models\CrmConfiguracion;
use App\Models\Role;
use App\Models\User;
use App\Services\CrmEmailService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CrmEmailEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SucursalSeeder::class);
    }

    public function test_configurar_mailer_dinamico_con_smtp_personalizado(): void
    {
        $config = CrmConfiguracion::activa();
        $config->update([
            'email_driver' => 'smtp',
            'email_smtp_host' => 'smtp.mailtrap.io',
            'email_smtp_port' => 2525,
            'email_smtp_username' => 'usuario_prueba',
            'email_smtp_password' => 'secreto123',
            'email_smtp_encryption' => 'tls',
            'email_remitente_nombre' => 'RestoMaster Test',
            'email_remitente_correo' => 'test@restomaster.com',
        ]);

        $config->aplicarConfiguracionMailer();

        $this->assertEquals('smtp', config('mail.default'));
        $this->assertEquals('smtp.mailtrap.io', config('mail.mailers.smtp.host'));
        $this->assertEquals(2525, config('mail.mailers.smtp.port'));
        $this->assertEquals('usuario_prueba', config('mail.mailers.smtp.username'));
        $this->assertEquals('secreto123', config('mail.mailers.smtp.password'));
        $this->assertEquals('tls', config('mail.mailers.smtp.encryption'));
        $this->assertEquals('test@restomaster.com', config('mail.from.address'));
        $this->assertEquals('RestoMaster Test', config('mail.from.name'));
    }

    public function test_envio_correo_de_prueba_con_mail_fake(): void
    {
        Mail::fake();

        $config = CrmConfiguracion::activa();
        $config->update([
            'email_driver' => 'smtp',
            'email_smtp_host' => 'smtp.test.com',
        ]);

        $service = app(CrmEmailService::class);
        $resultado = $service->enviarCorreoPrueba('cliente@destino.com');

        $this->assertTrue($resultado['success']);
        $this->assertStringContainsString('cliente@destino.com', $resultado['mensaje']);
    }

    public function test_componente_livewire_permite_guardar_motor_smtp_y_probar(): void
    {
        Mail::fake();

        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);
        $admin = User::create([
            'name' => 'Admin Email',
            'email' => 'admin_email@restomaster.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
            'is_active' => true,
        ]);

        Volt::actingAs($admin)
            ->test('crm.index')
            ->set('tab', 'configuracion')
            ->set('email_driver', 'smtp')
            ->set('email_smtp_host', 'smtp.gmail.com')
            ->set('email_smtp_port', 587)
            ->set('email_smtp_username', 'contacto@restomaster.com')
            ->set('email_smtp_password', 'clave_app_123')
            ->set('email_correo_pruebas', 'pruebas@cliente.com')
            ->call('guardarConfiguracion')
            ->assertSee('¡Configuración CRM actualizada con éxito!')
            ->call('enviarPruebaEmail')
            ->assertSee('pruebas@cliente.com');

        $configActualizada = CrmConfiguracion::activa();
        $this->assertEquals('smtp', $configActualizada->email_driver);
        $this->assertEquals('smtp.gmail.com', $configActualizada->email_smtp_host);
        $this->assertEquals('clave_app_123', $configActualizada->email_smtp_password);
    }
}
