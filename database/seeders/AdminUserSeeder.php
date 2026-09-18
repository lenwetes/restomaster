<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $rawPassword = env('DEMO_USERS_PASSWORD') ?: Str::password(16);

        if ($this->command && app()->isLocal()) {
            $this->command->info('Usuarios del sistema configurados correctamente para cada rol.');
        }

        $unifiedPassword = Hash::make($rawPassword);

        $users = [
            [
                'email' => 'admin@restomaster.com',
                'name' => 'Administrador RestoMaster',
                'role_id' => $adminRole?->id,
                'telefono' => '+57 300 987 6543',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'gerente@restomaster.com',
                'name' => 'Gerente de Operaciones',
                'role_id' => $gerenteRole?->id,
                'telefono' => '+57 300 111 2233',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'cajero@restomaster.com',
                'name' => 'Cajero Principal',
                'role_id' => $cajeroRole?->id,
                'telefono' => '+57 300 222 3344',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'mesero@restomaster.com',
                'name' => 'Mesero Turno Salón',
                'role_id' => $meseroRole?->id,
                'telefono' => '+57 300 333 4455',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'cocina@restomaster.com',
                'name' => 'Chef de Cocina KDS',
                'role_id' => $cocinaRole?->id,
                'telefono' => '+57 300 444 5566',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'barra@restomaster.com',
                'name' => 'Bartender Barra Bebidas',
                'role_id' => $barraRole?->id,
                'telefono' => '+57 300 555 6677',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'delivery@restomaster.com',
                'name' => 'Repartidor Delivery',
                'role_id' => $deliveryRole?->id,
                'telefono' => '+57 300 666 7788',
                'sucursal_id' => $sucursal?->id,
                'activo' => true,
                'password' => $unifiedPassword,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            $userExistente = User::where('email', $userData['email'])->first();
            if ($userExistente) {
                unset($userData['password']);
                $userExistente->update($userData);
            } else {
                User::create($userData);
            }
        }
    }
}
