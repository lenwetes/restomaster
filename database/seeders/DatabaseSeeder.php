<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SucursalSeeder::class,
            AdminUserSeeder::class,
            MesaSeeder::class,
            MenuSeeder::class,
            CajaSeeder::class,
            InventarioSeeder::class,
            ClienteSeeder::class,
            ConfiguracionSeeder::class,
            ImpresoraSeeder::class,
        ]);

        if (env('SEED_DEMO_OPERACIONES', false)) {
            $this->call([
                DemoOperacionesSeeder::class,
            ]);
        }
    }
}
