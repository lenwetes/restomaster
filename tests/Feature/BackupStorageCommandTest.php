<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class BackupStorageCommandTest extends TestCase
{
    private string $fuente;

    private string $destino;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/restomaster_backup_test_'.uniqid();
        $this->fuente = $base.'/fuente';
        $this->destino = $base.'/destino';

        File::makeDirectory($this->fuente.'/productos', 0755, true);
        File::put($this->fuente.'/productos/plato.jpg', str_repeat('A', 1024));
        File::put($this->fuente.'/.gitignore', "*\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->fuente));
        parent::tearDown();
    }

    public function test_genera_zip_con_contenido_y_estructura(): void
    {
        $this->artisan('restomaster:backup-storage', [
            '--fuente' => $this->fuente,
            '--destino' => $this->destino,
        ])->assertSuccessful();

        $zips = File::glob($this->destino.'/restomaster_storage_backup_*.zip') ?: [];
        $this->assertCount(1, $zips);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zips[0]));
        $this->assertNotFalse($zip->locateName('productos/plato.jpg'));

        $contenido = $zip->getFromName('productos/plato.jpg');
        $zip->close();
        $this->assertSame(str_repeat('A', 1024), $contenido);
    }

    public function test_rotacion_conserva_solo_keep_recientes(): void
    {
        File::makeDirectory($this->destino, 0755, true);
        $viejo1 = $this->destino.'/restomaster_storage_backup_2020-01-01_000000.zip';
        $viejo2 = $this->destino.'/restomaster_storage_backup_2021-01-01_000000.zip';
        File::put($viejo1, 'x');
        File::put($viejo2, 'y');
        touch($viejo1, time() - 200000);
        touch($viejo2, time() - 100000);

        $this->artisan('restomaster:backup-storage', [
            '--fuente' => $this->fuente,
            '--destino' => $this->destino,
            '--keep' => 2,
        ])->assertSuccessful();

        $restantes = File::glob($this->destino.'/restomaster_storage_backup_*.zip') ?: [];
        $this->assertCount(2, $restantes);
        $this->assertFileDoesNotExist($viejo1);
        $this->assertFileExists($viejo2);
    }

    public function test_falla_si_fuente_no_existe(): void
    {
        $this->artisan('restomaster:backup-storage', [
            '--fuente' => $this->fuente.'/inexistente',
            '--destino' => $this->destino,
        ])->assertFailed();

        $this->assertEmpty(File::glob($this->destino.'/restomaster_storage_backup_*.zip') ?: []);
    }
}
