<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ConfiguracionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;
use Tests\TestCase;
use ZipArchive;

class ConfiguracionBackupCompletoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $mesero;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin', 'descripcion' => 'Admin']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero', 'descripcion' => 'Mesero']);

        $sucursal = Sucursal::create([
            'nombre' => 'Sede Test',
            'codigo' => 'TST-01',
            'direccion' => 'Calle 1',
            'telefono' => '3000000000',
            'activa' => true,
        ]);

        $this->admin = User::factory()->create(['role_id' => $roleAdmin->id, 'sucursal_id' => $sucursal->id]);
        $this->mesero = User::factory()->create(['role_id' => $roleMesero->id, 'sucursal_id' => $sucursal->id]);

        $this->backupDir = sys_get_temp_dir().'/restomaster_cfg_backup_'.uniqid();
        config()->set('backup.path', $this->backupDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);
        parent::tearDown();
    }

    public function test_admin_genera_copia_completa_bd_y_archivos(): void
    {
        $this->actingAs($this->admin);

        Volt::test('configuracion.index')
            ->call('crearBackup')
            ->assertHasNoErrors();

        $sql = File::glob($this->backupDir.'/restomaster_backup_*.sql') ?: [];
        $zip = File::glob($this->backupDir.'/restomaster_storage_backup_*.zip') ?: [];

        $this->assertCount(1, $sql, 'Debe generarse el volcado de base de datos.');
        $this->assertCount(1, $zip, 'Debe generarse el respaldo de archivos.');
    }

    public function test_mesero_no_puede_generar_copia_de_seguridad(): void
    {
        $this->actingAs($this->mesero);

        Volt::test('configuracion.index')
            ->call('crearBackup')
            ->assertForbidden();
    }

    public function test_admin_restaura_sql_desde_lista_y_datos_vuelven(): void
    {
        $this->actingAs($this->admin);

        $sonda = Role::create(['nombre' => 'Sonda', 'slug' => 'sonda-restore', 'descripcion' => 'Sonda']);

        Volt::test('configuracion.index')
            ->call('crearBackup')
            ->assertHasNoErrors();

        $sql = File::glob($this->backupDir.'/restomaster_backup_*.sql') ?: [];
        $this->assertNotEmpty($sql, 'Debe existir el volcado SQL de la copia.');

        $sonda->delete();
        $this->assertDatabaseMissing('roles', ['slug' => 'sonda-restore']);

        Volt::test('configuracion.index')
            ->call('restaurarCopia', basename($sql[0]))
            ->assertRedirect(route('configuracion'));

        $this->assertDatabaseHas('roles', ['slug' => 'sonda-restore']);
        // El admin sigue operativo tras vaciar y replayar la tabla users.
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_mesero_no_puede_restaurar_copia(): void
    {
        $this->actingAs($this->mesero);

        Volt::test('configuracion.index')
            ->call('restaurarCopia', 'restomaster_backup_x.sql')
            ->assertForbidden();
    }

    public function test_restaurar_zip_extrae_archivos_y_rechaza_traversal(): void
    {
        $svc = app(ConfiguracionService::class);
        $destino = sys_get_temp_dir().'/restomaster_zip_restore_'.uniqid();

        $zipOk = $this->backupDir.'/restomaster_storage_backup_test.zip';
        File::makeDirectory($this->backupDir, 0755, true);
        $zip = new ZipArchive;
        $zip->open($zipOk, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('productos/plato.jpg', 'IMAGEN');
        $zip->close();

        $total = $svc->restaurarZipArchivos($zipOk, $destino);
        $this->assertSame(1, $total);
        $this->assertSame('IMAGEN', File::get($destino.'/productos/plato.jpg'));

        $zipMal = $this->backupDir.'/restomaster_storage_backup_evil.zip';
        $zip2 = new ZipArchive;
        $zip2->open($zipMal, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip2->addFromString('../evil.php', 'X');
        $zip2->close();

        $this->expectException(\DomainException::class);
        $svc->restaurarZipArchivos($zipMal, $destino);
    }

    public function test_restaurar_dump_sin_binario_da_error_claro(): void
    {
        $this->actingAs($this->admin);

        File::makeDirectory($this->backupDir, 0755, true);
        File::put($this->backupDir.'/restomaster_backup_test.dump', 'x');

        Volt::test('configuracion.index')
            ->call('restaurarCopia', 'restomaster_backup_test.dump')
            ->assertRedirect(route('configuracion'));

        $this->assertStringContainsString(
            'pg_restore',
            session('error') ?? '',
            'El error debe explicar que falta pg_restore.'
        );
    }
}
