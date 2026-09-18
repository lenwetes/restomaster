<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CargarDatosDemoCommand extends Command
{
    protected $signature = 'restomaster:seed-demo';

    protected $aliases = ['db:seed-demo', 'sushixpress:seed-demo'];

    protected $description = 'Carga la base de datos de RestoMaster con datos realistas completos para pruebas en entorno Colombia (platos, inventario, personal, pedidos, KDS, reservas)';

    public function handle(): int
    {
        $this->info('==========================================================');
        $this->info(' RestoMaster — Carga de Datos Realistas de Prueba (Colombia)');
        $this->info('==========================================================');

        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\DatosPruebaRealistasSeeder',
        ]);

        $this->newLine();
        $this->info('✓ Base de datos poblada exitosamente con catálogo completo, insumos, equipo de trabajo, pedidos y reservas.');

        return self::SUCCESS;
    }
}
