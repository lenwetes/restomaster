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
            ['nombre' => 'SushiXpress Central'],
            [
                'direccion' => 'Av. Gastronómica 123',
                'telefono' => '+52 55 1234 5678',
                'nit_ruc' => 'SX900101-ABC',
                'activo' => true,
            ]
        );
    }
}
