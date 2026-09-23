<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class BackupStorageCommand extends Command
{
    protected $signature = 'restomaster:backup-storage
                            {--fuente= : Directorio a respaldar (por defecto storage/app/public)}
                            {--destino= : Directorio de salida (por defecto config backup.path)}
                            {--keep=14 : Cantidad de respaldos recientes a conservar en rotación}';

    protected $description = 'Respalda los archivos públicos del sistema (imágenes de platos) en un ZIP versionado con rotación';

    public function handle(): int
    {
        $this->info('Iniciando respaldo de archivos públicos RestoMaster...');

        if (! extension_loaded('zip')) {
            $this->error('La extensión PHP zip no está disponible en este entorno.');

            return Command::FAILURE;
        }

        $fuente = $this->option('fuente') ?: storage_path('app/public');
        $destino = $this->option('destino') ?: config('backup.path', storage_path('app/backups'));
        $keep = (int) $this->option('keep') ?: 14;

        if (! is_dir($fuente)) {
            $this->error("El directorio fuente no existe: {$fuente}");

            return Command::FAILURE;
        }

        if (! File::exists($destino)) {
            File::makeDirectory($destino, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "restomaster_storage_backup_{$timestamp}.zip";
        $filepath = rtrim($destino, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

        // Listado en dos fases para no releer el ZIP mientras se escribe
        $archivos = $this->listarArchivos($fuente);

        $zip = new ZipArchive;
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("No se pudo crear el archivo de respaldo: {$filepath}");

            return Command::FAILURE;
        }

        foreach ($archivos as $real => $relativo) {
            $zip->addFile($real, $relativo);
        }
        $zip->close();

        if (count($archivos) === 0) {
            $this->warn('La fuente no contiene archivos: se generó un ZIP vacío como marcador.');
        }

        $tamanoKb = round(filesize($filepath) / 1024, 2);
        $sha256 = hash_file('sha256', $filepath);

        $this->rotarRespaldos($destino, $keep);

        $this->newLine();
        $this->info('✓ Respaldo de archivos completado exitosamente.');
        $this->table(
            ['Propiedad', 'Detalle'],
            [
                ['Archivo Generado', $filename],
                ['Ruta Completa', $filepath],
                ['Fuente', $fuente],
                ['Archivos Incluidos', (string) count($archivos)],
                ['Tamaño del Archivo', "{$tamanoKb} KB"],
                ['Checksum (SHA256)', substr($sha256, 0, 16).'...'],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> mapa ruta real => ruta relativa dentro del ZIP
     */
    protected function listarArchivos(string $fuente): array
    {
        $archivos = [];
        $fuenteReal = realpath($fuente) ?: $fuente;

        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fuenteReal, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterador as $info) {
            if (! $info->isFile()) {
                continue;
            }
            // Evitar que respaldos previos se absorban en el siguiente ciclo
            if (str_starts_with($info->getFilename(), 'restomaster_storage_backup_')) {
                continue;
            }
            $real = $info->getRealPath() ?: $info->getPathname();
            $relativo = ltrim(str_replace($fuenteReal, '', $real), DIRECTORY_SEPARATOR);
            $archivos[$real] = str_replace(DIRECTORY_SEPARATOR, '/', $relativo);
        }

        return $archivos;
    }

    protected function rotarRespaldos(string $destino, int $keep = 14): void
    {
        $archivos = File::glob(rtrim($destino, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'restomaster_storage_backup_*.zip') ?: [];
        if (count($archivos) <= $keep) {
            return;
        }

        // Ordenar por tiempo de modificación descendente (más nuevos primero)
        usort($archivos, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $aBorrar = array_slice($archivos, $keep);
        foreach ($aBorrar as $archivo) {
            File::delete($archivo);
            $this->comment('Rotación: eliminado respaldo antiguo '.basename($archivo));
        }
    }
}
