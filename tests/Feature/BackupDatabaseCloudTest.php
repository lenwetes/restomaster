<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDatabaseCloudTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_database_sube_a_almacenamiento_de_objetos_y_rota(): void
    {
        Storage::fake('r2');

        // Simulamos 3 archivos antiguos en el bucket remoto
        Storage::disk('r2')->put('backups/restomaster_backup_2026-01-01_000000.sql', 'antiguo 1');
        Storage::disk('r2')->put('backups/restomaster_backup_2026-01-02_000000.sql', 'antiguo 2');
        Storage::disk('r2')->put('backups/restomaster_backup_2026-01-03_000000.sql', 'antiguo 3');

        $this->artisan('restomaster:backup', [
            '--tablas' => 'roles,sucursales',
            '--cloud' => true,
            '--disk' => 'r2',
            '--keep-remote' => 2, // conservar solo 2
        ])->assertSuccessful();

        $archivos = Storage::disk('r2')->files('backups');

        // Debe haber rotado para tener máximo 2 archivos
        $this->assertCount(2, $archivos);
    }
}
