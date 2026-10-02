<?php

namespace App\Console\Commands;

use App\Models\Insumo;
use App\Models\Producto;
use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Aliases(['db:seed-demo'])]
#[Description('Carga todo lo necesario para una demo lista para clientes: esenciales, catálogo parrilla, mesas, cajas, inventario, operación de ejemplo e imágenes de platos')]
#[Signature('restomaster:seed-demo')]
class CargarDatosDemoCommand extends Command
{
    public function handle(): int
    {
        $this->info('==========================================================');
        $this->info(' RestoMaster — Demo lista para clientes en una sola acción');
        $this->info('==========================================================');

        $this->call('db:seed', ['--force' => true]);

        $vinculadas = $this->vincularImagenesDemo();
        $this->info("✓ Imágenes de platos vinculadas: {$vinculadas}.");

        $insumosCount = Insumo::count();
        $productosCount = Producto::count();
        $conImagenCount = Producto::whereNotNull('imagen')->where('imagen', '!=', '')->count();

        $this->newLine();
        $this->info("✓ Demo lista: {$productosCount} platos catalogados ({$conImagenCount} con fotografía), {$insumosCount} insumos en inventario.");

        return self::SUCCESS;
    }

    /**
     * Convención: public/demo/platos/{slug}.jpg (o extensiones/variantes)
     * se vincula a cada plato del menú.
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
            $posibles = [
                $producto->slug.'.jpg',
                $producto->slug.'-350g.jpg',
                $producto->slug.'.png',
                $producto->slug.'.webp',
            ];
            foreach ($posibles as $img) {
                if (File::exists($base.'/'.$img)) {
                    $ruta = '/demo/platos/'.$img;
                    if ($producto->imagen !== $ruta) {
                        $producto->update(['imagen' => $ruta]);
                        $vinculadas++;
                    }
                    break;
                }
            }
        }

        return $vinculadas;
    }
}
