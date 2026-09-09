<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $cajeroRole = Role::where('slug', 'cajero')->first();
        $meseroRole = Role::where('slug', 'mesero')->first();
        $cocinaRole = Role::where('slug', 'cocina')->first();

        User::firstOrCreate(
            ['email' => 'admin@sushixpress.com'],
            [
                'name' => 'Administrador SushiXpress',
                'role_id' => $adminRole?->id,
                'telefono' => '+52 55 9876 5432',
                'activo' => true,
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'cajero@sushixpress.com'],
            [
                'name' => 'Cajero Principal',
                'role_id' => $cajeroRole?->id,
                'telefono' => '+52 55 1111 2222',
                'activo' => true,
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'mesero@sushixpress.com'],
            [
                'name' => 'Mesero Turno Día',
                'role_id' => $meseroRole?->id,
                'telefono' => '+52 55 3333 4444',
                'activo' => true,
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'cocina@sushixpress.com'],
            [
                'name' => 'Jefe de Barra Sushi',
                'role_id' => $cocinaRole?->id,
                'telefono' => '+52 55 5555 6666',
                'activo' => true,
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
