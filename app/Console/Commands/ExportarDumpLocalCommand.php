<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Aliases(['db:dump-local'])]
#[Description('Exporta el estado completo de la base de datos local a database/dumps/local_full_data.sql para usar en redeploys')]
#[Signature('restomaster:dump-local {--force : Sobrescribir archivo existente sin preguntar}')]
class ExportarDumpLocalCommand extends Command
{
    public function handle(): int
    {
        $this->info('==========================================================');
        $this->info(' RestoMaster — Exportación de Datos Locales para Redeploy');
        $this->info('==========================================================');

        $dumpsDir = database_path('dumps');
        if (! File::isDirectory($dumpsDir)) {
            File::makeDirectory($dumpsDir, 0755, true);
        }

        $dumpFile = database_path('dumps/local_full_data.sql');

        $dbConfig = config('database.connections.pgsql', []);
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? 5432);
        $user = $dbConfig['username'] ?? 'adminresto';
        $dbname = $dbConfig['database'] ?? 'restomaster';
        $password = $dbConfig['password'] ?? 'admin';

        $pgDumpPath = 'C:\\Program Files\\PostgreSQL\\18\\bin\\pg_dump.exe';
        if (! File::exists($pgDumpPath)) {
            $pgDumpPath = 'pg_dump';
        }

        $this->info("Extrayendo datos de [{$dbname}] hacia [{$dumpFile}]...");

        $excludeTables = [
            '--exclude-table=migrations',
            '--exclude-table=sessions',
            '--exclude-table=cache',
            '--exclude-table=cache_locks',
            '--exclude-table=jobs',
            '--exclude-table=job_batches',
            '--exclude-table=failed_jobs',
        ];

        $command = array_merge(
            [$pgDumpPath, '-h', $host, '-p', $port, '-U', $user, '-d', $dbname, '--data-only', '--inserts'],
            $excludeTables,
            ['-f', $dumpFile]
        );

        try {
            $process = Process::env(['PGPASSWORD' => $password])
                ->timeout(120)
                ->run($command);

            if ($process->successful()) {
                $sizeMb = number_format(File::size($dumpFile) / (1024 * 1024), 2);
                $this->info("✓ Dump exportado exitosamente: {$dumpFile} ({$sizeMb} MB).");
                $this->info('Este archivo será utilizado automáticamente por DatabaseSeeder en el redeploy.');

                return self::SUCCESS;
            }

            $this->error('Error al ejecutar pg_dump: '.$process->errorOutput());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Excepción al ejecutar pg_dump: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
