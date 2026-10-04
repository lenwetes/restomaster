<?php

/**
 * Asegura que todas las imágenes requeridas para los 30 platos, 20 tragos,
 * 10 bebidas colombianas y 14 promociones existan físicamente en disco.
 */

$platosDir = __DIR__ . '/../public/demo/platos';
$imagesDir = __DIR__ . '/../public/images';

if (!is_dir($platosDir)) {
    mkdir($platosDir, 0755, true);
}
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0755, true);
}

// Mapeo de slugs a fuentes existentes o copias inteligentes
$platosYTragos = [
    // 30 Platos
    'picada-criolla-restomaster' => 'picada-criolla-restomaster.jpg',
    'trilogia-de-empanadas-artesanales' => 'trilogia-de-empanadas-artesanales.jpg',
    'empanaditas-de-punta-de-anca-4-pzs' => 'empanaditas-de-punta-de-anca-4-pzs.jpg',
    'ceviche-de-camaron-costeno' => 'ceviche-de-camaron-costeno.jpg',
    'ceviche-mixto-de-la-casa' => 'ceviche-mixto-de-la-casa.jpg',
    'carpaccio-de-res-trufado' => 'carpaccio-de-res-trufado.jpg',
    'bruschettas-rusticas-3-pzs' => 'bruschettas-rusticas-3-pzs.jpg',
    'alitas-bbq-o-crispy' => 'alitas-bbq-o-crispy.jpg',
    'ensalada-cesar-con-pollo' => 'ensalada-cesar-con-pollo.jpg',
    'bife-de-chorizo-angus' => 'bife-de-chorizo-angus.jpg',
    'ojo-de-bife-ribeye-400g' => 'ojo-de-bife-ribeye-400g.jpg',
    'baby-beef-a-la-parrilla' => 'baby-beef-a-la-parrilla.jpg',
    'punta-de-anca-tradicional' => 'punta-de-anca-tradicional.jpg',
    'costillas-de-cerdo-bbq' => 'costillas-de-cerdo-bbq.jpg',
    'costillas-bbq-ahumadas-500g' => 'costillas-bbq-ahumadas-500g.jpg',
    'pechuga-en-salsa-champinones' => 'pechuga-en-salsa-champinones.jpg',
    'pechuga-gratinada-al-parmesano' => 'pechuga-gratinada-al-parmesano.jpg',
    'filete-de-robalo-al-ajillo' => 'filete-de-robalo-al-ajillo.jpg',
    'cazuela-de-camarones' => 'cazuela-de-camarones.jpg',
    'cazuela-de-mariscos-del-pacifico' => 'cazuela-de-mariscos-del-pacifico.jpg',
    'fettuccine-alfredo-con-pollo-champinones' => 'fettuccine-alfredo-con-pollo-champinones.jpg',
    'fettuccine-alfredo-con-pollo' => 'fettuccine-alfredo-con-pollo.jpg',
    'lasana-tradicional-bolonesa' => 'lasana-tradicional-bolonesa.jpg',
    'raviolis-de-espinaca-ricotta' => 'raviolis-de-espinaca-ricotta.jpg',
    'risotto-de-setas-silvestres-trufa' => 'risotto-de-setas-silvestres-trufa.jpg',
    'hamburguesa-restomaster-angus' => 'hamburguesa-restomaster-angus.jpg',
    'hamburguesa-doble-trufa-hongos' => 'hamburguesa-doble-trufa-hongos.jpg',
    'hamburguesa-crunchy-chicken' => 'hamburguesa-crunchy-chicken.jpg',
    'sandwich-de-pulled-pork-braseado' => 'sandwich-de-pulled-pork-braseado.jpg',
    'restomaster-burger-master' => 'restomaster-burger-master.jpg',

    // Postres
    'volcan-tibio-de-chocolate' => 'volcan-tibio-de-chocolate.jpg',
    'volcan-de-chocolate-fondant' => 'volcan-de-chocolate-fondant.jpg',
    'cheesecake-clasico-de-frutos-rojos' => 'cheesecake-clasico-de-frutos-rojos.jpg',
    'torta-tres-leches-tradicional' => 'torta-tres-leches-tradicional.jpg',
    'tiramisu-tradicional-al-mascarpone' => 'tiramisu-tradicional-al-mascarpone.jpg',

    // 20 Tragos & Coctelería
    'gin-tonic-botanico-clasico' => 'gin-tonic-botanico-clasico.jpg',
    'gin-tonic-citrico-frutos-rojos' => 'gin-tonic-citrico-frutos-rojos.jpg',
    'moscow-mule-maracuya' => 'moscow-mule-maracuya.jpg',
    'mojito-clasico-ron-anejo' => 'mojito-clasico-ron-anejo.jpg',
    'mojito-clasico-de-ron-anejo' => 'mojito-clasico-de-ron-anejo.jpg',
    'espresso-martini-con-cafe-antioqueno' => 'espresso-martini-con-cafe-antioqueno.jpg',
    'margarita-de-frutos-amarillos' => 'gin-tonic-botanico-clasico.jpg',
    'pina-colada-artesanal' => 'limonada-de-coco-cremosita.jpg',
    'sangria-tinta-de-la-casa' => 'gin-tonic-citrico-frutos-rojos.jpg',
    'aperol-spritz-veneciano' => 'moscow-mule-maracuya.jpg',
    'sour-maracuya-aguardiente' => 'soda-saborizada-maracuya-albahaca.jpg',
    'ron-medellin-extra-anejo-8-anos' => 'mojito-clasico-ron-anejo.jpg',
    'ron-zacapa-23-solera' => 'espresso-martini-con-cafe-antioqueno.jpg',
    'aguardiente-antioqueno-azul' => 'gin-tonic-botanico-clasico.jpg',
    'whisky-old-parr-12-anos' => 'mojito-clasico-ron-anejo.jpg',
    'whisky-buchanans-deluxe-12' => 'espresso-martini-con-cafe-antioqueno.jpg',
    'tequila-don-julio-blanco' => 'gin-tonic-botanico-clasico.jpg',
    'mezcal-artesanal-oaxaqueno' => 'moscow-mule-maracuya.jpg',
    'ginebra-hendricks-pepino' => 'gin-tonic-botanico-clasico.jpg',
    'vodka-absolut-original' => 'moscow-mule-maracuya.jpg',
    'baileys-irish-cream-rocas' => 'tiramisu-tradicional-al-mascarpone.jpg',

    // 10 Bebidas embotelladas colombianas
    'colombiana-la-nuestra-botella' => 'colombiana-la-nuestra-botella.jpg',
    'gaseosa-postobon-manzana' => 'gaseosa-postobon-manzana.jpg',
    'bretana-con-limon-y-sal' => 'soda-saborizada-maracuya-albahaca.jpg',
    'jugo-hit-mango-botella' => 'jugo-natural-lulo-mango-maracuya.jpg',
    'jugo-hit-lulo-botella' => 'jugo-natural-lulo-mango-maracuya.jpg',
    'cerveza-club-colombia-dorada' => 'cerveza-club-colombia-dorada.jpg',
    'cerveza-club-colombia-roja' => 'cerveza-bbc-monserrate-roja.jpg',
    'cerveza-club-colombia-negra' => 'cerveza-club-colombia-dorada.jpg',
    'cerveza-aguila-original' => 'cerveza-club-colombia-dorada.jpg',
    'cerveza-bbc-monserrate-roja' => 'cerveza-bbc-monserrate-roja.jpg',
    'cerveza-bbc-cajica-miel' => 'cerveza-artesanal-ipa-330ml.jpg',
    'agua-cristal-manantial-500ml' => 'limonada-de-coco-artesanal.jpg',
    'limonada-de-coco-artesanal' => 'limonada-de-coco-artesanal.jpg',
];

$creadas = 0;
foreach ($platosYTragos as $slug => $fuente) {
    $destino = $platosDir . '/' . $slug . '.jpg';
    $origen = $platosDir . '/' . $fuente;

    if (!file_exists($destino) && file_exists($origen)) {
        copy($origen, $destino);
        $creadas++;
    }
}

// 14 Promociones en public/images/
$promos = [
    'promo-gin-tonic-2x1.jpg' => 'craft-cocktail.jpg',
    'promo-angus-prime-weekend.jpg' => 'fire-grill-chef.jpg',
    'promo-maridaje-sommelier.jpg' => 'resto-terrace-night.jpg',
    'promo-almuerzo-ejecutivo.jpg' => 'angus-steak.jpg',
    'promo-martes-burger-bbc.jpg' => 'fire-grill-chef.jpg',
    'promo-happy-hour-terraza.jpg' => 'resto-terrace-night.jpg',
    'promo-delivery-gratis-poblado.jpg' => 'restomaster-hero.jpg',
    'promo-festival-mar-pacifico.jpg' => 'truffle-pasta.jpg',
    'promo-noche-romantica-vino.jpg' => 'luxury-wine-cellar.jpg',
    'promo-cumpleanero-vip.jpg' => 'luxury-dessert.jpg',
    'promo-alitas-club-colombia.jpg' => 'craft-cocktail.jpg',
    'promo-brunch-mimosas.jpg' => 'resto-terrace-night.jpg',
    'promo-festival-pastas.jpg' => 'truffle-pasta.jpg',
    'promo-bono-bienvenida-app.jpg' => 'restomaster-hero.jpg',
];

$promosCreadas = 0;
foreach ($promos as $promoArchivo => $fuenteImagen) {
    $destino = $imagesDir . '/' . $promoArchivo;
    $origen = $imagesDir . '/' . $fuenteImagen;
    if (!file_exists($destino) && file_exists($origen)) {
        copy($origen, $destino);
        $promosCreadas++;
    }
}

echo "Proceso completado. Platos/Tragos asegurados: {$creadas}, Promociones aseguradas: {$promosCreadas}.\n";
