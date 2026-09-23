<?php

namespace Database\Seeders;

use App\Models\Mesa;
use App\Models\Sucursal;
use App\Models\Zona;
use Illuminate\Database\Seeder;

class ZonaSeeder extends Seeder
{
    public function run(): void
    {
        $clasicas = [
            ['nombre' => 'Salón Principal', 'slug' => 'salon', 'color' => 'terracota', 'icono' => 'mesa', 'orden' => 1],
            ['nombre' => 'Barra / Bar', 'slug' => 'barra', 'color' => 'lavanda', 'icono' => 'barra', 'orden' => 2],
            ['nombre' => 'Terraza', 'slug' => 'terraza', 'color' => 'salvia', 'icono' => 'terraza', 'orden' => 3],
            ['nombre' => 'Área VIP', 'slug' => 'vip', 'color' => 'indigo', 'icono' => 'vip', 'orden' => 4],
        ];

        foreach (Sucursal::all(['id']) as $sucursal) {
            foreach ($clasicas as $z) {
                Zona::firstOrCreate(
                    ['sucursal_id' => $sucursal->id, 'slug' => $z['slug']],
                    $z + ['activa' => true]
                );
            }

            // Adoptar slugs históricos con mesas (patio, primer_piso, personalizados)
            $huerfanos = Mesa::where('sucursal_id', $sucursal->id)
                ->distinct()
                ->pluck('zona')
                ->filter(fn ($slug) => $slug && ! Zona::where('sucursal_id', $sucursal->id)->where('slug', $slug)->exists());
            foreach ($huerfanos as $i => $slug) {
                Zona::firstOrCreate(
                    ['sucursal_id' => $sucursal->id, 'slug' => $slug],
                    ['nombre' => ucfirst(str_replace(['-', '_'], ' ', $slug)), 'color' => 'pizarra', 'icono' => 'mesa', 'orden' => 90 + $i, 'activa' => true]
                );
            }
        }
    }
}
