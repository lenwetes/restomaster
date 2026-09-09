<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'Administrador',
                'slug' => 'admin',
                'descripcion' => 'Acceso total al sistema y configuración',
            ],
            [
                'nombre' => 'Gerente',
                'slug' => 'gerente',
                'descripcion' => 'Gestión operativa, inventarios y reportes',
            ],
            [
                'nombre' => 'Cajero',
                'slug' => 'cajero',
                'descripcion' => 'Punto de venta, control de caja y cobranza',
            ],
            [
                'nombre' => 'Mesero',
                'slug' => 'mesero',
                'descripcion' => 'Atención de mesas y toma de pedidos táctil',
            ],
            [
                'nombre' => 'Cocina',
                'slug' => 'cocina',
                'descripcion' => 'Pantalla KDS de preparación de sushi y cocina',
            ],
            [
                'nombre' => 'Barra',
                'slug' => 'barra',
                'descripcion' => 'Pantalla KDS de bebidas y barra',
            ],
            [
                'nombre' => 'Delivery',
                'slug' => 'delivery',
                'descripcion' => 'Repartidor y despacho de pedidos a domicilio',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
