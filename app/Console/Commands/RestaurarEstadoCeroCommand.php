<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class RestaurarEstadoCeroCommand extends Command
{
    protected $signature = 'restomaster:estado-cero
                            {--force : Ejecutar sin solicitar confirmación interactiva}
                            {--dump : Restaurar directamente desde el archivo SQL database/dumps/estado_0.sql}';

    protected $aliases = ['db:estado-cero'];

    protected $description = 'Restaura la base de datos de RestoMaster a ESTADO 0 (limpia, sin platos/inventario, solo 1 usuario por rol)';

    public function handle(): int
    {
        $this->info('==========================================================');
        $this->info(' RestoMaster — Restauración a ESTADO 0 (Base Limpia)');
        $this->info('==========================================================');

        if (! $this->option('force')) {
            if (! $this->confirm('¿Está seguro de restablecer la base de datos a Estado 0? Se purgarán pedidos, mesas, platos e inventario.', false)) {
                $this->warn('Operación cancelada.');

                return self::SUCCESS;
            }
        }

        $dumpFile = base_path('database/dumps/estado_0.sql');
        $restauradoConDump = false;

        if ($this->option('dump') && File::exists($dumpFile)) {
            $this->info("Restaurando mediante volcado SQL nativo: {$dumpFile}...");
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
                    ->run([$psqlPath, '-h', $host, '-p', $port, '-U', $user, '-d', $dbname, '-f', $dumpFile, '--quiet']);

                if ($process->successful()) {
                    $restauradoConDump = true;
                    $this->info('Dump SQL importado exitosamente.');
                } else {
                    $this->warn('No se pudo restaurar con psql, recurriendo a migrate:fresh...');
                }
            } catch (\Throwable $e) {
                $this->warn('Error al invocar psql: '.$e->getMessage().', recurriendo a migrate:fresh...');
            }
        }

        if (! $restauradoConDump) {
            $this->info("Ejecutando 'migrate:fresh --seed' con seeders de Estado 0...");
            Artisan::call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ], $this->output);
        }

        Artisan::call('optimize:clear', [], $this->output);

        $usuarios = User::with('role')->orderBy('role_id')->get();

        $this->newLine();
        $this->info('¡Base de datos restablecida a ESTADO 0 con éxito!');
        $this->newLine();

        $rows = $usuarios->map(function ($u) {
            return [
                'ID' => $u->id,
                'Rol' => $u->role?->nombre ?? 'Sin Rol',
                'Slug' => $u->role?->slug ?? '-',
                'Nombre' => $u->name,
                'Email' => $u->email,
                'Password' => 'restomaster2026',
            ];
        })->toArray();

        $this->table(['ID', 'Rol', 'Slug', 'Nombre', 'Email', 'Contraseña'], $rows);

        $this->info(sprintf(
            'Métricas: Usuarios: %d | Roles: %d | Productos: %d | Insumos: %d | Pedidos: %d',
            User::count(),
            Role::count(),
            Producto::count(),
            DB::table('insumos')->count(),
            DB::table('pedidos')->count()
        ));

        return self::SUCCESS;
    }
}
