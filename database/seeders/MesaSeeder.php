<?php

namespace Database\Seeders;

use App\Models\Mesa;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class MesaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sucursal = Sucursal::first();

        if (! $sucursal) {
            return;
        }

        $mesas = [
            ['numero' => 'Mesa 1', 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre'],
            ['numero' => 'Mesa 2', 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre'],
            ['numero' => 'Mesa 3', 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre'],
            ['numero' => 'Mesa 4', 'zona' => 'salon', 'capacidad' => 2, 'estado' => 'libre'],
            ['numero' => 'Mesa 5', 'zona' => 'salon', 'capacidad' => 6, 'estado' => 'libre'],
            ['numero' => 'Mesa 6', 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre'],
            ['numero' => 'Barra 1', 'zona' => 'barra', 'capacidad' => 2, 'estado' => 'libre'],
            ['numero' => 'Barra 2', 'zona' => 'barra', 'capacidad' => 2, 'estado' => 'libre'],
            ['numero' => 'Terraza 1', 'zona' => 'terraza', 'capacidad' => 6, 'estado' => 'libre'],
            ['numero' => 'Terraza 2', 'zona' => 'terraza', 'capacidad' => 4, 'estado' => 'libre'],
        ];

        foreach ($mesas as $mesa) {
            Mesa::firstOrCreate(
                [
                    'sucursal_id' => $sucursal->id,
                    'numero' => $mesa['numero'],
                ],
                $mesa
            );
        }
    }
}
