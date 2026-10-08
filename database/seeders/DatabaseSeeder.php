<?php

namespace Database\Seeders;

use App\Models\Pedido;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $dumpFile = database_path('dumps/local_full_data.sql');

        // Si existe el dump completo generado desde la base de datos local y no estamos en entorno de testing
        if (File::exists($dumpFile) && ! app()->environment('testing')) {
            $pedidosExistentes = 0;
            try {
                $pedidosExistentes = Pedido::count();
            } catch (\Throwable $e) {
                // Las tablas pueden estar recién creadas
            }

            if ($pedidosExistentes < 10) {
                $this->command?->info('==> Cargando réplica exacta de base de datos local (local_full_data.sql)...');
                if ($this->cargarDumpLocal($dumpFile)) {
                    $this->command?->info('==> Base de datos local restaurada con éxito (catálogo, usuarios, pedidos, inventario, mesas, cajas, etc.).');

                    $scriptImagenes = base_path('scripts/asegurar_imagenes_demo.php');
                    if (File::exists($scriptImagenes)) {
                        require_once $scriptImagenes;
                    }

                    return;
                }
            } else {
                $this->command?->info("==> La base de datos ya cuenta con datos cargados ({$pedidosExistentes} pedidos). Omitiendo re-importación.");

                return;
            }
        }

        // Fallback por seeders individuales
        $this->call([
            RoleSeeder::class,
            SucursalSeeder::class,
            ZonaSeeder::class,
            ConfiguracionSeeder::class,
            AdminUserSeeder::class,
            CrmIaPlantillaSeeder::class,
            CrmSeeder::class,
            MeseroPruebaSeeder::class,
        ]);

        if (! app()->environment('testing')) {
            $this->call([
                DatosPruebaRealistasSeeder::class,
                OperacionesMesCompletoSeeder::class,
                ClubVipDemoSeeder::class,
                DemoColombiaMedellinSeeder::class,
            ]);
        }
    }

    /**
     * Importa el archivo SQL de la base de datos local mediante psql o fallback PDO.
     */
    protected function cargarDumpLocal(string $dumpFile): bool
    {
        $dbConfig = config('database.connections.pgsql', []);
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? 5432);
        $user = $dbConfig['username'] ?? 'adminresto';
        $dbname = $dbConfig['database'] ?? 'restomaster';
        $password = $dbConfig['password'] ?? 'admin';

        $psqlPath = 'C:\\Program Files\\PostgreSQL\\18\\bin\\psql.exe';
        if (! File::exists($psqlPath)) {
            $psqlPath = 'psql';
        }

        try {
            $process = Process::env(['PGPASSWORD' => $password])
                ->timeout(180)
                ->run([$psqlPath, '-h', $host, '-p', $port, '-U', $user, '-d', $dbname, '-f', $dumpFile, '--quiet']);

            if ($process->successful()) {
                return true;
            }
        } catch (\Throwable $e) {
            // Intentar fallback si psql falla
        }

        // Fallback vía PDO si psql no está disponible
        try {
            $sql = File::get($dumpFile);
            $sqlLimpio = preg_replace('/^\\\\[^\r\n]*[\r\n]+/m', '', $sql);
            if (! empty($sqlLimpio)) {
                DB::unprepared($sqlLimpio);

                return true;
            }
        } catch (\Throwable $e) {
            $this->command?->warn('Error al cargar dump con fallback PDO: '.$e->getMessage());
        }

        return false;
    }
}
