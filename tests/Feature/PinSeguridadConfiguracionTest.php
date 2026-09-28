<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ConfiguracionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PinSeguridadConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ConfiguracionService $configService;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'password' => Hash::make('Secret123*'),
            'email' => 'admin@restomaster.test',
        ]);

        $this->configService = app(ConfiguracionService::class);
    }

    public function test_establecer_pin_con_hash_y_verificar_correctamente(): void
    {
        $this->assertFalse($this->configService->tienePinSeguridad());

        // Establecer PIN de 4 dígitos
        $this->configService->establecerPinSeguridad('7429', $this->admin);

        $this->assertTrue($this->configService->tienePinSeguridad());
        $this->assertTrue($this->configService->verificarPinSeguridad('7429'));
        $this->assertFalse($this->configService->verificarPinSeguridad('0000'));
        $this->assertFalse($this->configService->verificarPinSeguridad('7428'));
    }

    public function test_establecer_pin_invalido_arroja_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Menos de 4 dígitos o caracteres no numéricos
        $this->configService->establecerPinSeguridad('12a', $this->admin);
    }

    public function test_cambiar_pin_con_password_administrador(): void
    {
        $this->configService->establecerPinSeguridad('1234', $this->admin);

        // Cambio exitoso con password correcto
        $exito = $this->configService->cambiarPinConPassword($this->admin, 'Secret123*', '9876');
        $this->assertTrue($exito);
        $this->assertTrue($this->configService->verificarPinSeguridad('9876'));
        $this->assertFalse($this->configService->verificarPinSeguridad('1234'));

        // Falla con password incorrecto
        $this->expectException(\InvalidArgumentException::class);
        $this->configService->cambiarPinConPassword($this->admin, 'ClaveEquivocada', '5555');
    }

    public function test_generar_y_validar_otp_rescate_pin(): void
    {
        $this->configService->establecerPinSeguridad('1111', $this->admin);

        // Generar OTP de rescate
        $otp = $this->configService->generarOtpRescatePin($this->admin);
        $this->assertEquals(6, strlen($otp));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $otp);

        // Validar OTP y restablecer a nuevo PIN
        $rescatado = $this->configService->validarOtpYRestablecerPin($otp, '4321', $this->admin);
        $this->assertTrue($rescatado);

        $this->assertTrue($this->configService->verificarPinSeguridad('4321'));
        $this->assertFalse($this->configService->verificarPinSeguridad('1111'));
    }

    public function test_otp_invalido_arroja_excepcion(): void
    {
        $this->configService->establecerPinSeguridad('1111', $this->admin);
        $this->configService->generarOtpRescatePin($this->admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->configService->validarOtpYRestablecerPin('000000', '9999', $this->admin);
    }

    public function test_configuracion_livewire_permite_guardar_pin(): void
    {
        Volt::actingAs($this->admin)
            ->test('configuracion.index')
            ->set('tabActiva', 'seguridad')
            ->set('pinForm_nuevoPin', '5678')
            ->set('pinForm_confirmarPin', '5678')
            ->call('guardarPinSeguridad')
            ->assertHasNoErrors();

        $this->assertTrue($this->configService->verificarPinSeguridad('5678'));
    }

    public function test_configuracion_livewire_rescate_con_password(): void
    {
        $this->configService->establecerPinSeguridad('1234', $this->admin);

        Volt::actingAs($this->admin)
            ->test('configuracion.index')
            ->set('tabActiva', 'seguridad')
            ->call('abrirModalRescatePin', 'password')
            ->assertSet('mostrarModalRescatePin', true)
            ->set('pinRescate_password', 'Secret123*')
            ->set('pinRescate_nuevoPin', '9999')
            ->set('pinRescate_confirmarPin', '9999')
            ->call('ejecutarRescateConPassword')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalRescatePin', false);

        $this->assertTrue($this->configService->verificarPinSeguridad('9999'));
    }

    public function test_configuracion_livewire_rescate_con_otp(): void
    {
        $this->configService->establecerPinSeguridad('1234', $this->admin);

        $test = Volt::actingAs($this->admin)
            ->test('configuracion.index')
            ->set('tabActiva', 'seguridad')
            ->call('abrirModalRescatePin', 'otp')
            ->assertSet('mostrarModalRescatePin', true)
            ->assertSet('otpEnviado', true);

        // Obtenemos el OTP generado para validar
        $otp = $this->configService->generarOtpRescatePin($this->admin);

        $test->set('pinRescate_otp', $otp)
            ->set('pinRescate_nuevoPin', '8888')
            ->set('pinRescate_confirmarPin', '8888')
            ->call('ejecutarRescateConOtp')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalRescatePin', false);

        $this->assertTrue($this->configService->verificarPinSeguridad('8888'));
    }
}
