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
        $this->info(' RestoMaster — Demo Colombia · Medellín (Despliegue Listo)');
        $this->info('==========================================================');

        $scriptImagenes = base_path('scripts/asegurar_imagenes_demo.php');
        if (File::exists($scriptImagenes)) {
            $this->info('==> Verificando y asegurando catálogo de imágenes físicas...');
            require_once $scriptImagenes;
        }

        $this->call('db:seed', ['--force' => true]);

        $vinculadas = $this->vincularImagenesDemo();
        $this->info("✓ Imágenes de platos vinculadas: {$vinculadas}.");

        $insumosCount = Insumo::count();
        $productosCount = Producto::count();
        $conImagenCount = Producto::whereNotNull('imagen')->where('imagen', '!=', '')->count();

        $this->newLine();
        $this->info('✓ Kit de demostración listo:');
        $this->line("  • {$productosCount} productos/platos/tragos/bebidas ({$conImagenCount} con imagen garantizada)");
        $this->line("  • {$insumosCount} insumos en inventario con escandallo y stock");
        $this->line('  • 24 colaboradores configurados (meseros, cocina, cajeros, repartidores, gerencia)');
        $this->line('  • 10 proveedores colombianos con NIT');
        $this->line('  • 20 clientes segmentados (VIP, Elegibles, Frecuentes, Ocasionales)');
        $this->line('  • 30 días de operaciones históricas (ventas, cajas, compras, DIAN POS y encuestas)');
        $this->line('  • 14 promociones comerciales (7 activas + 7 borradores)');
        $this->line('  • Credenciales unificadas: password / RestoDemo2026');

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
