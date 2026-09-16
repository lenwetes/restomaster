<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Sucursal::firstOrCreate(
            ['nombre' => 'RestoMaster Principal'],
            [
                'direccion' => 'Cra 35 # 8A-12, Provenza, Medellín',
                'telefono' => '+57 300 123 4567',
                'nit_ruc' => '901.458.789-3',
                'activo' => true,
            ]
        );
    }
}
