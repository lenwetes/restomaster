<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'sushixpress:backup {--tablas= : Lista opcional de tablas separadas por coma}';

    protected $description = 'Genera un volcado estructurado de respaldo de la base de datos de SushiXpress';

    public function handle(): int
    {
        $this->info('Iniciando respaldo seguro de base de datos SushiXpress...');
        $inicio = microtime(true);

        $backupDir = storage_path('app/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $tablasPorDefecto = [
            'users',
            'roles',
            'sucursales',
            'mesas',
            'categorias',
            'productos',
            'insumos',
            'recetas',
            'movimientos_inventario',
            'pedidos',
            'items_pedido',
            'cajas',
            'turnos_caja',
            'movimientos_caja',
            'asientos_contables',
            'cuentas_por_pagar',
            'pagos_cxps',
            'clientes',
            'direcciones_cliente',
            'movimientos_puntos',
            'reservas',
            'reserva_mesa',
            'configuraciones',
            'impresoras',
            'trabajos_impresion',
            'auditorias',
        ];

        if ($this->option('tablas')) {
            $tablas = array_map('trim', explode(',', $this->option('tablas')));
        } else {
            $tablas = $tablasPorDefecto;
        }

        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "sushixpress_backup_{$timestamp}.sql";
        $filepath = "{$backupDir}/{$filename}";

        $fp = fopen($filepath, 'w');
        if (! $fp) {
            $this->error("No se pudo abrir el archivo de respaldo: {$filepath}");

            return Command::FAILURE;
        }

        fwrite($fp, "-- ========================================================\n");
        fwrite($fp, "-- SushiXpress POS Enterprise — Volcado de Respaldo\n");
        fwrite($fp, '-- Fecha: '.Carbon::now()->toIso8601String()."\n");
        fwrite($fp, '-- Servidor: '.config('database.default')."\n");
        fwrite($fp, "-- ========================================================\n\n");

        $totalRegistros = 0;

        foreach ($tablas as $tabla) {
            try {
                $registros = DB::table($tabla)->get();
                $count = $registros->count();
                $totalRegistros += $count;

                fwrite($fp, "-- Tabla: {$tabla} ({$count} registros)\n");

                foreach ($registros as $row) {
                    $rowArray = (array) $row;
                    $cols = array_keys($rowArray);
                    $vals = array_map(function ($val) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        if (is_bool($val)) {
                            return $val ? 'true' : 'false';
                        }

                        return "'".addslashes((string) $val)."'";
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

        $this->newLine();
        $this->info("✓ Respaldo completado exitosamente en {$duracion} s.");
        $this->table(
            ['Propiedad', 'Detalle'],
            [
                ['Archivo Generado', $filename],
                ['Ruta Completa', $filepath],
                ['Tamaño del Archivo', "{$tamanoKb} KB"],
                ['Registros Respaldados', (string) $totalRegistros],
                ['Tablas Procesadas', (string) count($tablas)],
            ]
        );

        return Command::SUCCESS;
    }
}
