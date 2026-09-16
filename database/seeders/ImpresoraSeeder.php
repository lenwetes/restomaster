<?php

namespace Database\Seeders;

use App\Models\Impresora;
use Illuminate\Database\Seeder;

class ImpresoraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $impresoras = [
            [
                'nombre' => 'Térmica Cocina Fría & Entradas',
                'tipo_conexion' => 'red_ip',
                'ip_address' => '192.168.1.201',
                'puerto' => 9100,
                'area' => 'cocina_sushi',
                'ancho_columnas' => 48,
                'copias' => 1,
                'activa' => true,
                'descripcion' => 'Impresora térmica de ensaladas, entradas de autor y platos fríos',
            ],
            [
                'nombre' => 'Térmica Cocina Caliente & Parrilla',
                'tipo_conexion' => 'red_ip',
                'ip_address' => '192.168.1.202',
                'puerto' => 9100,
                'area' => 'cocina_calientes',
                'ancho_columnas' => 48,
                'copias' => 1,
                'activa' => true,
                'descripcion' => 'Impresora térmica de cocina caliente, parrilla, cortes y pastas',
            ],
            [
                'nombre' => 'Térmica Barra de Bebidas & Cocktails',
                'tipo_conexion' => 'red_ip',
                'ip_address' => '192.168.1.203',
                'puerto' => 9100,
                'area' => 'barra',
                'ancho_columnas' => 48,
                'copias' => 1,
                'activa' => true,
                'descripcion' => 'Impresora térmica de barra para cócteles, vinos, licores y cafés',
            ],
            [
                'nombre' => 'Térmica Caja Principal (Facturación)',
                'tipo_conexion' => 'red_ip',
                'ip_address' => '192.168.1.200',
                'puerto' => 9100,
                'area' => 'caja_principal',
                'ancho_columnas' => 48,
                'copias' => 1,
                'activa' => true,
                'descripcion' => 'Impresora térmica de facturación fiscal POS y Reportes Z',
            ],
            [
                'nombre' => 'Simulador Virtual de Impresión',
                'tipo_conexion' => 'virtual_simulador',
                'ip_address' => null,
                'puerto' => 9100,
                'area' => 'todas',
                'ancho_columnas' => 48,
                'copias' => 1,
                'activa' => true,
                'descripcion' => 'Simulador virtual de cinta continua para pruebas operativas',
            ],
        ];

        foreach ($impresoras as $datos) {
            Impresora::firstOrCreate(
                ['nombre' => $datos['nombre']],
                $datos
            );
        }
    }
}
