<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            [
                'nombre' => 'Rolls Clásicos',
                'slug' => 'rolls-clasicos',
                'icono' => '🍣',
                'orden' => 1,
                'productos' => [
                    [
                        'nombre' => 'California Roll',
                        'descripcion' => 'Kani, palta fresca, pepino y sésamo tostado (8 cortes)',
                        'precio' => 28000.00,
                        'costo' => 9500.00,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Philadelphia Roll',
                        'descripcion' => 'Salmón premium, queso crema suave y sésamo (8 cortes)',
                        'precio' => 32000.00,
                        'costo' => 11000.00,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Spicy Tuna Roll',
                        'descripcion' => 'Atún rojo picante con aceite de sésamo y cebollín (8 cortes)',
                        'precio' => 34000.00,
                        'costo' => 12500.00,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Avocado Maki',
                        'descripcion' => 'Rollo fino de palta y arroz envuelto en alga nori (6 cortes)',
                        'precio' => 22000.00,
                        'costo' => 7000.00,
                        'area_cocina' => 'sushi',
                    ],
                ],
            ],
            [
                'nombre' => 'Rolls Especiales',
                'slug' => 'rolls-especiales',
                'icono' => '🍱',
                'orden' => 2,
                'productos' => [
                    [
                        'nombre' => 'Dragon Roll',
                        'descripcion' => 'Langostino furai y queso crema por dentro, cubierto de láminas de palta y salsa unagi',
                        'precio' => 38000.00,
                        'costo' => 14000.00,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Rainbow Roll',
                        'descripcion' => 'California roll cubierto de salmón, atún rojo, pez blanco y palta',
                        'precio' => 42000.00,
                        'costo' => 15500.00,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Volcano Roll',
                        'descripcion' => 'Roll tempurizado relleno de salmón y queso, bañado en salsa picante gratinada',
                        'precio' => 45000.00,
                        'costo' => 16000.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Ebi Crunch',
                        'descripcion' => 'Langostino crujiente, queso crema y cebollín, envuelto en panko crocante',
                        'precio' => 36000.00,
                        'costo' => 13000.00,
                        'area_cocina' => 'caliente',
                    ],
                ],
            ],
            [
                'nombre' => 'Nigiris & Sashimi',
                'slug' => 'nigiris-sashimi',
                'icono' => '🥢',
                'orden' => 3,
                'productos' => [
                    [
                        'nombre' => 'Nigiri Salmón (2 pzs)',
                        'descripcion' => 'Bocadillos de arroz shari con lámina de salmón fresco',
                        'precio' => 18000.00,
                        'costo' => 7000.00,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Nigiri Atún Rojo (2 pzs)',
                        'descripcion' => 'Láminas de atún rojo maguro sobre arroz shari',
                        'precio' => 20000.00,
                        'costo' => 8000.00,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Sashimi Mixto (9 cortes)',
                        'descripcion' => '3 cortes de salmón, 3 de atún y 3 de pesca blanca del día',
                        'precio' => 48000.00,
                        'costo' => 18000.00,
                        'area_cocina' => 'sushi',
                    ],
                ],
            ],
            [
                'nombre' => 'Entradas & Calientes',
                'slug' => 'entradas-calientes',
                'icono' => '🥟',
                'orden' => 4,
                'productos' => [
                    [
                        'nombre' => 'Gyozas de Cerdo (5 pzs)',
                        'descripcion' => 'Empanadillas japonesas a la plancha rellenas de cerdo y vegetales',
                        'precio' => 22000.00,
                        'costo' => 8000.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Edamames Sal Marina',
                        'descripcion' => 'Vainas de soja al vapor sazonadas con sal marina en escamas',
                        'precio' => 16000.00,
                        'costo' => 5000.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Ebi Tempura (4 pzs)',
                        'descripcion' => 'Langostinos gigantes rebozados en tempura japonesa crujiente',
                        'precio' => 28000.00,
                        'costo' => 10500.00,
                        'area_cocina' => 'caliente',
                    ],
                ],
            ],
            [
                'nombre' => 'Bebidas & Barra',
                'slug' => 'bebidas-barra',
                'icono' => '🥤',
                'orden' => 5,
                'productos' => [
                    [
                        'nombre' => 'Té Verde Matcha Helado',
                        'descripcion' => 'Infusión tradicional japonesa fría con toque cítrico',
                        'precio' => 10000.00,
                        'costo' => 3000.00,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Cerveza Asahi Super Dry (330ml)',
                        'descripcion' => 'Cerveza japonesa premium importada',
                        'precio' => 18000.00,
                        'costo' => 8500.00,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Limonada de Jengibre y Menta',
                        'descripcion' => 'Bebida artesanal refrescante con jengibre fresco',
                        'precio' => 12000.00,
                        'costo' => 3500.00,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Sake Caliente Gekkeikan (180ml)',
                        'descripcion' => 'Sake tradicional servido a temperatura óptima',
                        'precio' => 28000.00,
                        'costo' => 11000.00,
                        'area_cocina' => 'barra',
                    ],
                ],
            ],
        ];

        foreach ($categorias as $catData) {
            $productos = $catData['productos'];
            unset($catData['productos']);

            $categoria = Categoria::firstOrCreate(['slug' => $catData['slug']], $catData);

            foreach ($productos as $prodData) {
                $prodData['categoria_id'] = $categoria->id;
                $prodData['slug'] = Str::slug($prodData['nombre']);
                Producto::updateOrCreate(['slug' => $prodData['slug']], $prodData);
            }
        }
    }
}
