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
                        'precio' => 9.50,
                        'costo' => 3.20,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Philadelphia Roll',
                        'descripcion' => 'Salmón premium, queso crema suave y sésamo (8 cortes)',
                        'precio' => 10.50,
                        'costo' => 4.10,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Spicy Tuna Roll',
                        'descripcion' => 'Atún rojo picante con aceite de sésamo y cebollín (8 cortes)',
                        'precio' => 11.00,
                        'costo' => 4.50,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Avocado Maki',
                        'descripcion' => 'Rollo fino de palta y arroz envuelto en alga nori (6 cortes)',
                        'precio' => 7.50,
                        'costo' => 2.00,
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
                        'precio' => 14.50,
                        'costo' => 5.20,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Rainbow Roll',
                        'descripcion' => 'California roll cubierto de salmón, atún rojo, pez blanco y palta',
                        'precio' => 13.50,
                        'costo' => 5.50,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Volcano Roll',
                        'descripcion' => 'Roll tempurizado relleno de salmón y queso, bañado en salsa picante gratinada',
                        'precio' => 15.00,
                        'costo' => 5.80,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Ebi Crunch',
                        'descripcion' => 'Langostino crujiente, queso crema y cebollín, envuelto en panko crocante',
                        'precio' => 12.50,
                        'costo' => 4.30,
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
                        'precio' => 5.50,
                        'costo' => 2.10,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Nigiri Atún Rojo (2 pzs)',
                        'descripcion' => 'Láminas de atún rojo maguro sobre arroz shari',
                        'precio' => 6.00,
                        'costo' => 2.40,
                        'area_cocina' => 'sushi',
                    ],
                    [
                        'nombre' => 'Sashimi Mixto (9 cortes)',
                        'descripcion' => '3 cortes de salmón, 3 de atún y 3 de pesca blanca del día',
                        'precio' => 16.50,
                        'costo' => 6.80,
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
                        'precio' => 6.50,
                        'costo' => 2.20,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Edamames Sal Marina',
                        'descripcion' => 'Vainas de soja al vapor sazonadas con sal marina en escamas',
                        'precio' => 4.50,
                        'costo' => 1.20,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Ebi Tempura (4 pzs)',
                        'descripcion' => 'Langostinos gigantes rebozados en tempura japonesa crujiente',
                        'precio' => 8.50,
                        'costo' => 3.10,
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
                        'precio' => 3.50,
                        'costo' => 0.80,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Cerveza Asahi Super Dry (330ml)',
                        'descripcion' => 'Cerveza japonesa premium importada',
                        'precio' => 5.00,
                        'costo' => 2.10,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Limonada de Jengibre y Menta',
                        'descripcion' => 'Bebida artesanal refrescante con jengibre fresco',
                        'precio' => 3.80,
                        'costo' => 0.90,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Sake Caliente Gekkeikan (180ml)',
                        'descripcion' => 'Sake tradicional servido a temperatura óptima',
                        'precio' => 7.50,
                        'costo' => 2.80,
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
                Producto::firstOrCreate(['slug' => $prodData['slug']], $prodData);
            }
        }
    }
}
