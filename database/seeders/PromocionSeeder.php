<?php

namespace Database\Seeders;

use App\Models\Promocion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PromocionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Promocion::firstOrCreate(
            ['slug' => 'jueves-gin-tonic-parrilla-2x1'],
            [
                'titulo' => 'Jueves de Gin Tonic 2x1 & Parrilla de Autor',
                'subtitulo' => 'Mixología botánica al atardecer y cortes al fuego en terraza Provenza',
                'descripcion' => 'Disfruta de nuestra selección premium de ginebras artesanales al 2x1 toda la noche, maridadas a la perfección con cortes madurados al fuego vivo de roble. Una velada mágica con música lounge y ambiente bajo las estrellas.',
                'terminos_condiciones' => 'Válido todos los jueves de 18:00 a 23:00 exclusivamente para consumo en salón y terraza. No acumulable con otras promociones.',
                'tipo_beneficio' => 'dos_por_uno',
                'descuento_porcentaje' => null,
                'precio_promocional' => null,
                'precio_original' => null,
                'imagen_url' => '/images/craft-cocktail.jpg',
                'fecha_inicio' => Carbon::today()->subDays(5),
                'fecha_fin' => Carbon::today()->addDays(45),
                'dias_semana' => ['jueves'],
                'aplica_salon' => true,
                'aplica_delivery' => false,
                'mostrar_en_portada' => true,
                'activo' => true,
                'orden' => 1,
            ]
        );

        Promocion::firstOrCreate(
            ['slug' => 'fin-de-semana-angus-prime-20-off'],
            [
                'titulo' => '20% OFF en Cortes Tomahawk & Ribeye Prime',
                'subtitulo' => 'Maduración Dry-Aged 45 días al carbón silvestre con mantequilla de trufas',
                'descripcion' => 'Celebra el fin de semana con nuestros cortes insignia de máxima infiltración servidos en sartén de hierro fundido ardiente. Incluye dos guarniciones artesanales a elección de nuestra huerta orgánica.',
                'terminos_condiciones' => 'Aplica para salón y pedidos delivery express los viernes, sábados y domingos. Descuento aplicado automáticamente en factura.',
                'tipo_beneficio' => 'descuento_porcentaje',
                'descuento_porcentaje' => 20.00,
                'precio_promocional' => null,
                'precio_original' => null,
                'imagen_url' => '/images/fire-grill-chef.jpg',
                'fecha_inicio' => Carbon::today()->subDays(2),
                'fecha_fin' => Carbon::today()->addDays(30),
                'dias_semana' => ['viernes', 'sabado', 'domingo'],
                'aplica_salon' => true,
                'aplica_delivery' => true,
                'mostrar_en_portada' => true,
                'activo' => true,
                'orden' => 2,
            ]
        );

        Promocion::firstOrCreate(
            ['slug' => 'experiencia-degustacion-maridaje-vip'],
            [
                'titulo' => 'Menú Degustación 5 Tiempos con Maridaje Sommelier',
                'subtitulo' => 'Un recorrido sensorial guiado por nuestro Sommelier y Chef Ejecutivo',
                'descripcion' => 'Cena maridaje íntima que incluye aperitivo de bienvenida, entrada de mar, pasta fresca trufada, corte Angus de selección y postre de autor, cada uno armonizado con vinos de reserva.',
                'terminos_condiciones' => 'Requiere reserva previa con mínimo 24 horas de anticipación. Disponible de martes a sábado.',
                'tipo_beneficio' => 'precio_fijo',
                'descuento_porcentaje' => null,
                'precio_promocional' => 185000.00,
                'precio_original' => 230000.00,
                'imagen_url' => '/images/resto-terrace-night.jpg',
                'fecha_inicio' => Carbon::today()->subDays(1),
                'fecha_fin' => Carbon::today()->addDays(60),
                'dias_semana' => ['martes', 'miercoles', 'jueves', 'viernes', 'sabado'],
                'aplica_salon' => true,
                'aplica_delivery' => false,
                'mostrar_en_portada' => true,
                'activo' => true,
                'orden' => 3,
            ]
        );
    }
}
