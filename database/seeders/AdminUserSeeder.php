<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Sucursal;
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
        $gerenteRole = Role::where('slug', 'gerente')->first();
        $cajeroRole = Role::where('slug', 'cajero')->first();
        $meseroRole = Role::where('slug', 'mesero')->first();
        $cocinaRole = Role::where('slug', 'cocina')->first();
        $barraRole = Role::where('slug', 'barra')->first();
        $deliveryRole = Role::where('slug', 'delivery')->first();

        $sucursal = Sucursal::first();

        $unifiedPassword = Hash::make('123456');

        $users = [
            [
                'email' => 'admin@sushixpress.com',
                'name' => 'Administrador SushiXpress',
                'role_id' => $adminRole?->id,
                'telefono' => '+57 300 987 6543',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'gerente@sushixpress.com',
                'name' => 'Gerente de Operaciones',
                'role_id' => $gerenteRole?->id,
                'telefono' => '+57 300 111 2233',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'cajero@sushixpress.com',
                'name' => 'Cajero Principal',
                'role_id' => $cajeroRole?->id,
                'telefono' => '+57 300 222 3344',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'mesero@sushixpress.com',
                'name' => 'Mesero Turno Salón',
                'role_id' => $meseroRole?->id,
                'telefono' => '+57 300 333 4455',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'cocina@sushixpress.com',
                'name' => 'Chef de Cocina KDS',
                'role_id' => $cocinaRole?->id,
                'telefono' => '+57 300 444 5566',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'barra@sushixpress.com',
                'name' => 'Bartender & Barra',
                'role_id' => $barraRole?->id,
                'telefono' => '+57 300 555 6677',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'repartidor@sushixpress.com',
                'name' => 'Domiciliario Express',
                'role_id' => $deliveryRole?->id,
                'telefono' => '+57 300 666 7788',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
