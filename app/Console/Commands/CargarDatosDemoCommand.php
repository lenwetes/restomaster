<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Database\Seeders\CajaSeeder;
use Database\Seeders\DemoOperacionesSeeder;
use Database\Seeders\ImpresoraSeeder;
use Database\Seeders\InventarioSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\MesaSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CargarDatosDemoCommand extends Command
{
    protected $signature = 'restomaster:seed-demo';

    protected $aliases = ['db:seed-demo'];

    protected $description = 'Carga todo lo necesario para una demo lista para clientes: esenciales, catálogo parrilla, mesas, cajas, inventario, operación de ejemplo e imágenes de platos';

    public function handle(): int
    {
        $this->info('==========================================================');
        $this->info(' RestoMaster — Demo lista para clientes en una sola acción');
        $this->info('==========================================================');

        $this->call('db:seed', ['--force' => true]);

        $seedersDemo = [
            MenuSeeder::class,
            MesaSeeder::class,
            CajaSeeder::class,
            ImpresoraSeeder::class,
            InventarioSeeder::class,
            DemoOperacionesSeeder::class,
        ];

        foreach ($seedersDemo as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        $vinculadas = $this->vincularImagenesDemo();
        $this->info("✓ Imágenes de platos vinculadas: {$vinculadas}.");

        $this->newLine();
        $this->info('✓ Demo lista: catálogo, mesas, cajas, inventario, turno abierto, pedidos de ejemplo e imágenes.');

        return self::SUCCESS;
    }

    /**
     * Convención: public/demo/platos/{slug}.jpg se vincula solo al producto
     * con ese slug. Basta con agregar el archivo para futuros platos.
     */
    protected function vincularImagenesDemo(): int
    {
        $base = public_path('demo/platos');
        if (! File::isDirectory($base)) {
            $this->warn('Sin carpeta public/demo/platos: se omite el vínculo de imágenes.');

            return 0;
        }

        $vinculadas = 0;
        foreach (Producto::all(['id', 'slug', 'imagen']) as $producto) {
            $ruta = "demo/platos/{$producto->slug}.jpg";
            if ($producto->imagen !== '/'.$ruta && File::exists($base.'/'.$producto->slug.'.jpg')) {
                $producto->update(['imagen' => '/'.$ruta]);
                $vinculadas++;
            }
        }

        return $vinculadas;
    }
}
