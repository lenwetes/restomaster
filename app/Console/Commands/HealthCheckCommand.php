<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HealthCheckCommand extends Command
{
    protected $signature = 'restomaster:health';

    protected $aliases = ['sushixpress:health'];

    protected $description = 'Verifica el estado operativo y salud integral de los servicios de RestoMaster';

    public function handle(): int
    {
        $this->info('========================================================');
        $this->info('  RESTOMASTER ENTERPRISE — DIAGNÓSTICO DE SALUD');
        $this->info('  Fecha: '.Carbon::now()->format('Y-m-d H:i:s'));
        $this->info('========================================================');
        $this->newLine();

        $estados = [];
        $hayCriticos = false;

        // 1. Base de datos
        $dbInicio = microtime(true);
        try {
            DB::select('SELECT 1');
            $dbLatencia = round((microtime(true) - $dbInicio) * 1000, 2);
            $estados[] = ['Base de Datos (PostgreSQL)', 'OK', "Conectado ({$dbLatencia} ms)"];
        } catch (\Throwable $e) {
            $hayCriticos = true;
            $estados[] = ['Base de Datos (PostgreSQL)', 'FALLO', $e->getMessage()];
        }

        // 2. Colas de trabajo (Jobs)
        try {
            $jobsPendientes = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $driver = config('queue.default');

            if ($failedJobs > 0) {
                $estados[] = ['Colas de Trabajo (Queue)', 'ADVERTENCIA', "Driver: {$driver} · {$jobsPendientes} pendientes · {$failedJobs} fallidos"];
            } else {
                $estados[] = ['Colas de Trabajo (Queue)', 'OK', "Driver: {$driver} · {$jobsPendientes} pendientes · 0 fallidos"];
            }
        } catch (\Throwable $e) {
            $estados[] = ['Colas de Trabajo (Queue)', 'FALLO', $e->getMessage()];
        }

        // 3. Almacenamiento & Backups
        try {
            $storagePath = storage_path('app');
            $esEscribible = is_writable($storagePath);
            $espacioLibreGb = round(disk_free_space($storagePath) / (1024 * 1024 * 1024), 2);

            if ($esEscribible && $espacioLibreGb > 1.0) {
                $estados[] = ['Almacenamiento Local', 'OK', "Escritura disponible · {$espacioLibreGb} GB libres"];
            } else {
                $estados[] = ['Almacenamiento Local', 'ADVERTENCIA', "Espacio bajo o permisos limitados ({$espacioLibreGb} GB)"];
            }
        } catch (\Throwable $e) {
            $estados[] = ['Almacenamiento Local', 'ADVERTENCIA', $e->getMessage()];
        }

        // 4. Impresoras de Red y Spooler
        try {
            $totalImpresoras = Impresora::count();
            $activas = Impresora::activas()->count();
            $trabajosError = TrabajoImpresion::fallidos()->count();

            $estados[] = ['Impresoras & Spooler', 'OK', "{$activas}/{$totalImpresoras} activas · {$trabajosError} errores en spooler"];
        } catch (\Throwable $e) {
            $estados[] = ['Impresoras & Spooler', 'ADVERTENCIA', $e->getMessage()];
        }

        // 5. Bitácora de Auditoría
        try {
            $totalAuditorias = Auditoria::count();
            $auditoriasHoy = Auditoria::whereDate('created_at', Carbon::today())->count();
            $estados[] = ['Bitácora de Auditoría', 'OK', "{$totalAuditorias} eventos totales ({$auditoriasHoy} hoy)"];
        } catch (\Throwable $e) {
            $estados[] = ['Bitácora de Auditoría', 'ADVERTENCIA', $e->getMessage()];
        }

        $this->table(['Servicio / Componente', 'Estado', 'Detalle Operativo'], $estados);

        if ($hayCriticos) {
            $this->error('Se encontraron fallos críticos en los servicios esenciales.');

            return Command::FAILURE;
        }

        $this->info('Todos los componentes operativos se encuentran en estado saludable.');

        return Command::SUCCESS;
    }
}
