<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SucursalSeeder::class,
            ZonaSeeder::class,
            ConfiguracionSeeder::class,
            AdminUserSeeder::class,
            CrmIaPlantillaSeeder::class,
            MeseroPruebaSeeder::class,
        ]);

        if (! app()->environment('testing')) {
            $this->call([
                DatosPruebaRealistasSeeder::class,
                OperacionesMesCompletoSeeder::class,
            ]);
        }
    }
}
