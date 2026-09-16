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
                'nombre' => 'Entradas & Tapas',
                'slug' => 'entradas-tapas',
                'icono' => '🥟',
                'orden' => 1,
                'productos' => [
                    [
                        'nombre' => 'Carpaccio de Res Trufado',
                        'descripcion' => 'Láminas finas de lomo de res angus, rúgula fresca, lascas de parmesano reggiano y aceite de trufa blanca.',
                        'precio' => 32000.00,
                        'costo' => 11000.00,
                        'area_cocina' => 'fria',
                    ],
                    [
                        'nombre' => 'Ceviche Mixto de la Casa',
                        'descripcion' => 'Pesca blanca fresca del Pacífico, camarones marinados en leche de tigre al ají amarillo, maíz cancha y batata glaseada.',
                        'precio' => 36000.00,
                        'costo' => 12500.00,
                        'area_cocina' => 'fria',
                    ],
                    [
                        'nombre' => 'Bruschettas Rústicas (3 pzs)',
                        'descripcion' => 'Pan campesino de masa madre tostado, tomates cherry confitados, mozzarella di bufala y pesto artesanal de albahaca.',
                        'precio' => 24000.00,
                        'costo' => 7500.00,
                        'area_cocina' => 'fria',
                    ],
                    [
                        'nombre' => 'Empanaditas de Punta de Anca (4 pzs)',
                        'descripcion' => 'Empanadas crocantes rellenas de carne de res braseada al vino tinto con chimichurri rústico de la casa.',
                        'precio' => 22000.00,
                        'costo' => 7000.00,
                        'area_cocina' => 'caliente',
                    ],
                ],
            ],
            [
                'nombre' => 'Cortes & Parrilla',
                'slug' => 'cortes-parrilla',
                'icono' => '🥩',
                'orden' => 2,
                'productos' => [
                    [
                        'nombre' => 'Bife de Chorizo Angus (350g)',
                        'descripcion' => 'Corte jugoso a la brasa con papas rústicas al romero, vegetales asados y mantequilla aromatizada con hierbas.',
                        'precio' => 58000.00,
                        'costo' => 21000.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Ojo de Bife / Ribeye (400g)',
                        'descripcion' => 'Corte con marmoleo premium a la parrilla, acompañado de puré rústico y chimichurri criollo.',
                        'precio' => 64000.00,
                        'costo' => 24000.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Costillas BBQ Ahumadas (500g)',
                        'descripcion' => 'Costillas tiernas de cerdo cocinadas a baja temperatura por 8 horas, glaseadas en salsa BBQ de panela y ron.',
                        'precio' => 48000.00,
                        'costo' => 16500.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Pechuga Gratinada al Parmesano',
                        'descripcion' => 'Suprema de pollo a la brasa con salsa cremosa de espinacas baby y queso parmesano gratinado al horno.',
                        'precio' => 36000.00,
                        'costo' => 12000.00,
                        'area_cocina' => 'caliente',
                    ],
                ],
            ],
            [
                'nombre' => 'Pastas & Risottos',
                'slug' => 'pastas-risottos',
                'icono' => '🍝',
                'orden' => 3,
                'productos' => [
                    [
                        'nombre' => 'Fettuccine Alfredo con Pollo & Champiñones',
                        'descripcion' => 'Pasta fresca artesanal, salsa cremosa al parmesano reggiano, pechuga a la plancha y portobellos salteados.',
                        'precio' => 38000.00,
                        'costo' => 12500.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Raviolis de Espinaca & Ricotta',
                        'descripcion' => 'Pasta rellena artesanal bañada en salsa pomodoro italiana clásica, aceite de oliva virgen y albahaca fresca.',
                        'precio' => 36000.00,
                        'costo' => 11500.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Risotto de Setas Silvestres & Trufa',
                        'descripcion' => 'Arroz arborio cremoso con variedad de hongos portobello, vino blanco, mantequilla y aceite de trufa negra.',
                        'precio' => 42000.00,
                        'costo' => 14000.00,
                        'area_cocina' => 'caliente',
                    ],
                ],
            ],
            [
                'nombre' => 'Hamburguesas & Sándwiches',
                'slug' => 'hamburguesas-sandwiches',
                'icono' => '🍔',
                'orden' => 4,
                'productos' => [
                    [
                        'nombre' => 'RestoMaster Burger Master',
                        'descripcion' => '200g carne angus seleccionada, tocineta ahumada crujiente, queso cheddar madurado, cebolla caramelizada y pan brioche.',
                        'precio' => 36000.00,
                        'costo' => 12000.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Hamburguesa Doble Trufa & Hongos',
                        'descripcion' => 'Doble smash burger de res (2x100g), queso fundido, salteado de portobellos y mayonesa trufada artesanal.',
                        'precio' => 40000.00,
                        'costo' => 13500.00,
                        'area_cocina' => 'caliente',
                    ],
                    [
                        'nombre' => 'Sándwich de Pulled Pork Braseado',
                        'descripcion' => 'Cerdo desmechado braseado en BBQ de la casa, ensalada fresca de repollo coleslaw en pan brioche artesanal.',
                        'precio' => 32000.00,
                        'costo' => 10000.00,
                        'area_cocina' => 'caliente',
                    ],
                ],
            ],
            [
                'nombre' => 'Postres de Autor',
                'slug' => 'postres',
                'icono' => '🍰',
                'orden' => 5,
                'productos' => [
                    [
                        'nombre' => 'Volcán de Chocolate Fondant',
                        'descripcion' => 'Coulant de chocolate amargo 70% con centro líquido tibio, servido con helado artesanal de vainilla.',
                        'precio' => 20000.00,
                        'costo' => 6000.00,
                        'area_cocina' => 'postres',
                    ],
                    [
                        'nombre' => 'Cheesecake Clásico de Frutos Rojos',
                        'descripcion' => 'Tarta horneada de queso crema sobre base crocante de galleta y coulis casero de moras y fresas silvestres.',
                        'precio' => 18000.00,
                        'costo' => 5500.00,
                        'area_cocina' => 'postres',
                    ],
                    [
                        'nombre' => 'Tiramisú Tradicional al Mascarpone',
                        'descripcion' => 'Capas de soletilla embebidas en espresso aromático y licor de café con crema suave de queso mascarpone.',
                        'precio' => 22000.00,
                        'costo' => 7000.00,
                        'area_cocina' => 'postres',
                    ],
                ],
            ],
            [
                'nombre' => 'Bebidas & Coctelería',
                'slug' => 'bebidas-cocteleria',
                'icono' => '🍹',
                'orden' => 6,
                'productos' => [
                    [
                        'nombre' => 'Limonada de Coco Artesanal',
                        'descripcion' => 'Refrescante batido de crema de coco natural, jugo de lima recién exprimido y hielo frappeado.',
                        'precio' => 12000.00,
                        'costo' => 3500.00,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Gin Tonic Cítrico & Frutos Rojos',
                        'descripcion' => 'Ginebra premium London dry, agua tónica botánica, fresas, moras y bayas de enebro.',
                        'precio' => 28000.00,
                        'costo' => 9500.00,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Mojito Clásico de Ron Añejo',
                        'descripcion' => 'Ron añejo colombiano, macerado de hierbabuena fresca, lima, azúcar de caña y soda fría.',
                        'precio' => 24000.00,
                        'costo' => 8000.00,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Cerveza Artesanal IPA (330ml)',
                        'descripcion' => 'Cerveza artesanal lupulada de Medellín con notas cítricas, florales y amargor equilibrado.',
                        'precio' => 16000.00,
                        'costo' => 6500.00,
                        'area_cocina' => 'barra',
                    ],
                    [
                        'nombre' => 'Soda Saborizada Maracuyá & Albahaca',
                        'descripcion' => 'Bebida gasificada natural con reducción de pulpa de maracuyá y hojas frescas de albahaca.',
                        'precio' => 10000.00,
                        'costo' => 2500.00,
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
