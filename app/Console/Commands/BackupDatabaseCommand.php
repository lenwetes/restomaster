<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'restomaster:backup
                            {--tablas= : Lista opcional de tablas separadas por coma}
                            {--keep=14 : Cantidad de respaldos recientes a conservar en rotación}
                            {--dump : Forzar uso de pg_dump binario si está disponible}';

    protected $aliases = ['db:backup'];

    protected $description = 'Genera un volcado estructurado de respaldo de la base de datos de RestoMaster con streaming y rotación';

    /**
     * Tablas cubiertas por el respaldo (y por la restauración automática).
     * Ordenadas de padres a hijos para que el replay de INSERTs respete
     * las llaves foráneas. Fuente única de verdad: también la usa
     * ConfiguracionService::vaciarTablasRespaldo().
     *
     * @var list<string>
     */
    public const TABLAS = [
        'roles',
        'sucursales',
        'categorias',
        'categoria_insumos',
        'proveedores',
        'clientes',
        'configuraciones',
        'insumos',
        'users',
        'mesas',
        'productos',
        'cajas',
        'impresoras',
        'permission_user',
        'direcciones_cliente',
        'compras',
        'cuentas_por_pagar',
        'reservas',
        'asientos_contables',
        'auditorias',
        'pedidos',
        'recetas',
        'turnos_caja',
        'reserva_mesa',
        'compra_lineas',
        'movimientos_inventario',
        'items_pedido',
        'movimientos_caja',
        'movimientos_puntos',
        'trabajos_impresion',
        'pagos_cxps',
    ];

    public function handle(): int
    {
        $this->info('Iniciando respaldo seguro de base de datos RestoMaster...');
        $inicio = microtime(true);

        $backupDir = config('backup.path', storage_path('app/backups'));
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d_His');
        $conn = config('database.default');
        $keep = (int) $this->option('keep') ?: 14;

        // Intentar pg_dump binario si es pgsql y se solicita o no hay filtro de tablas
        if ($conn === 'pgsql' && ($this->option('dump') || ! $this->option('tablas'))) {
            $dumpResult = $this->ejecutarPgDump($backupDir, $timestamp);
            if ($dumpResult !== null) {
                $this->rotarRespaldos($backupDir, $keep);

                return $dumpResult;
            }
        }

        // Respaldo streaming mediante cursores (bajo uso de memoria y sin desbordamiento)
        $tablasPorDefecto = self::TABLAS;

        if ($this->option('tablas')) {
            $tablas = array_map('trim', explode(',', $this->option('tablas')));
        } else {
            $tablas = $tablasPorDefecto;
        }

        $filename = "restomaster_backup_{$timestamp}.sql";
        $filepath = "{$backupDir}/{$filename}";

        $fp = fopen($filepath, 'w');
        if (! $fp) {
            $this->error("No se pudo abrir el archivo de respaldo: {$filepath}");

            return Command::FAILURE;
        }

        fwrite($fp, "-- ========================================================\n");
        fwrite($fp, "-- RestoMaster POS Enterprise — Volcado de Respaldo\n");
        fwrite($fp, '-- Fecha: '.Carbon::now()->toIso8601String()."\n");
        fwrite($fp, '-- Servidor: '.$conn."\n");
        fwrite($fp, "-- ========================================================\n\n");

        $totalRegistros = 0;

        foreach ($tablas as $tabla) {
            try {
                $count = DB::table($tabla)->count();
                $totalRegistros += $count;

                fwrite($fp, "-- Tabla: {$tabla} ({$count} registros)\n");

                // Streaming por cursor para no materializar todas las filas en RAM
                foreach (DB::table($tabla)->cursor() as $row) {
                    $rowArray = (array) $row;
                    $cols = array_keys($rowArray);
                    $vals = array_map(function ($val) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        if (is_bool($val)) {
                            return $val ? 'true' : 'false';
                        }
                        if (is_int($val) || is_float($val)) {
                            return (string) $val;
                        }

                        // Escape SQL estándar seguro evitando corrupción de backslashes
                        return "'".str_replace("'", "''", (string) $val)."'";
                    }, array_values($rowArray));

                    $colList = '"'.implode('", "', $cols).'"';
                    $valList = implode(', ', $vals);

                    fwrite($fp, "INSERT INTO \"{$tabla}\" ({$colList}) VALUES ({$valList});\n");
                }

                fwrite($fp, "\n");
                $this->line(" <info>✓</info> Tabla <comment>{$tabla}</comment>: {$count} registros respaldados.");
            } catch (\Throwable $e) {
                $this->warn(" <comment>!</comment> Omitida tabla {$tabla}: ".$e->getMessage());
            }
        }

        fclose($fp);

        $duracion = round(microtime(true) - $inicio, 2);
        $tamanoKb = round(filesize($filepath) / 1024, 2);
        $sha256 = hash_file('sha256', $filepath);

        $this->rotarRespaldos($backupDir, $keep);

        $this->newLine();
        $this->info("✓ Respaldo completado exitosamente en {$duracion} s.");
        $this->table(
            ['Propiedad', 'Detalle'],
            [
                ['Archivo Generado', $filename],
                ['Ruta Completa', $filepath],
                ['Tamaño del Archivo', "{$tamanoKb} KB"],
                ['Checksum (SHA256)', substr($sha256, 0, 16).'...'],
                ['Registros Respaldados', (string) $totalRegistros],
                ['Tablas Procesadas', (string) count($tablas)],
            ]
        );

        return Command::SUCCESS;
    }

    protected function ejecutarPgDump(string $backupDir, string $timestamp): ?int
    {
        $dbConfig = config('database.connections.pgsql');
        if (! $dbConfig) {
            return null;
        }

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? 5432);
        $user = $dbConfig['username'] ?? 'postgres';
        $dbname = $dbConfig['database'] ?? 'restomaster';
        $password = $dbConfig['password'] ?? '';

        $filename = "restomaster_backup_{$timestamp}.dump";
        $filepath = "{$backupDir}/{$filename}";

        try {
            $env = array_merge($_ENV, [
                'PGPASSWORD' => $password,
            ]);

            $result = Process::timeout(300)
                ->env($env)
                ->path($backupDir)
                ->run([
                    'pg_dump',
                    '-h', $host,
                    '-p', $port,
                    '-U', $user,
                    '-Fc',
                    '-Z', '9',
                    '-f', $filepath,
                    $dbname,
                ]);

            if ($result->successful() && File::exists($filepath) && filesize($filepath) > 0) {
                $tamanoKb = round(filesize($filepath) / 1024, 2);
                $sha256 = hash_file('sha256', $filepath);
                $this->info('✓ Respaldo nativo pg_dump generado exitosamente (-Fc, compresión 9).');
                $this->table(
                    ['Propiedad', 'Detalle'],
                    [
                        ['Archivo Generado', $filename],
                        ['Ruta Completa', $filepath],
                        ['Formato', 'PostgreSQL Custom Dump (-Fc)'],
                        ['Tamaño del Archivo', "{$tamanoKb} KB"],
                        ['Checksum (SHA256)', substr($sha256, 0, 16).'...'],
                    ]
                );

                return Command::SUCCESS;
            }
        } catch (\Throwable $e) {
            // Si pg_dump no está en el PATH o falla, se continúa con streaming cursor
            $this->comment('pg_dump no disponible o falló en el entorno actual. Utilizando volcado por cursor streaming...');
        }

        return null;
    }

    protected function rotarRespaldos(string $backupDir, int $keep = 14): void
    {
        $archivos = File::glob("{$backupDir}/*_backup_*.*");
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
