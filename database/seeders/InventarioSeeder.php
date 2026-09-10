<?php

namespace Database\Seeders;

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first();

        // 1. Catálogo de Insumos / Materias Primas para Sushi
        $insumosData = [
            [
                'nombre' => 'Lomo de Salmón Pacífico Fresco',
                'codigo' => 'SKU-PES-01',
                'categoria' => 'pescados',
                'unidad_medida' => 'kg',
                'stock_actual' => 3.200,
                'stock_minimo' => 8.000,
                'capacidad_maxima' => 35.000,
                'costo_unitario' => 54000.00,
                'proveedor_nombre' => 'Bahía Solano Seafood S.A.S.',
                'proveedor_nit' => '901.234.567-1',
                'proveedor_telefono' => '+57 310 456 7890',
                'ubicacion_almacen' => 'Cámara Fría #01 · Pescados',
                'temperatura_almacen' => '0°C - 2°C',
                'imagen' => 'salmon.jpg',
            ],
            [
                'nombre' => 'Atún Rojo Maguro Aleta Amarilla',
                'codigo' => 'SKU-PES-02',
                'categoria' => 'pescados',
                'unidad_medida' => 'kg',
                'stock_actual' => 4.500,
                'stock_minimo' => 6.000,
                'capacidad_maxima' => 25.000,
                'costo_unitario' => 68000.00,
                'proveedor_nombre' => 'Ocean Pacific Imports',
                'proveedor_nit' => '900.876.543-2',
                'proveedor_telefono' => '+57 311 987 6543',
                'ubicacion_almacen' => 'Cámara Fría #01 · Pescados',
                'temperatura_almacen' => '-2°C',
                'imagen' => 'tuna.jpg',
            ],
            [
                'nombre' => 'Langostinos Tigre Crudos (U-15)',
                'codigo' => 'SKU-PES-03',
                'categoria' => 'pescados',
                'unidad_medida' => 'kg',
                'stock_actual' => 14.500,
                'stock_minimo' => 8.000,
                'capacidad_maxima' => 40.000,
                'costo_unitario' => 42000.00,
                'proveedor_nombre' => 'Bahía Solano Seafood S.A.S.',
                'proveedor_nit' => '901.234.567-1',
                'proveedor_telefono' => '+57 310 456 7890',
                'ubicacion_almacen' => 'Cámara Fría Congelación #02',
                'temperatura_almacen' => '-18°C',
            ],
            [
                'nombre' => 'Kani (Surimi de Cangrejo Japonés)',
                'codigo' => 'SKU-PES-04',
                'categoria' => 'pescados',
                'unidad_medida' => 'kg',
                'stock_actual' => 9.200,
                'stock_minimo' => 5.000,
                'capacidad_maxima' => 25.000,
                'costo_unitario' => 18000.00,
                'proveedor_nombre' => 'Nippon Foods Import',
                'proveedor_nit' => '900.112.334-5',
                'proveedor_telefono' => '+57 312 334 5566',
                'ubicacion_almacen' => 'Cámara Fría #01',
                'temperatura_almacen' => '2°C',
            ],
            [
                'nombre' => 'Arroz Koshihikari para Shari',
                'codigo' => 'SKU-ARR-01',
                'categoria' => 'arroz_granos',
                'unidad_medida' => 'kg',
                'stock_actual' => 52.000,
                'stock_minimo' => 20.000,
                'capacidad_maxima' => 120.000,
                'costo_unitario' => 8500.00,
                'proveedor_nombre' => 'Molinos del Oriente',
                'proveedor_nit' => '890.456.123-9',
                'proveedor_telefono' => '+57 315 223 3445',
                'ubicacion_almacen' => 'Almacén Seco Central · Tarima A1',
                'temperatura_almacen' => 'Ambiente',
            ],
            [
                'nombre' => 'Algas Nori Tostadas Gold (Pack 50 hojas)',
                'codigo' => 'SKU-ALG-01',
                'categoria' => 'algas_nori',
                'unidad_medida' => 'paquete',
                'stock_actual' => 12.000,
                'stock_minimo' => 20.000,
                'capacidad_maxima' => 80.000,
                'costo_unitario' => 14500.00,
                'proveedor_nombre' => 'Tokyo Trading Co.',
                'proveedor_nit' => '901.889.001-3',
                'proveedor_telefono' => '+57 320 998 7766',
                'ubicacion_almacen' => 'Almacén Seco · Estante B2',
                'temperatura_almacen' => 'Ambiente seco',
            ],
            [
                'nombre' => 'Aguacate Hass Calibre 1',
                'codigo' => 'SKU-VEG-01',
                'categoria' => 'vegetales',
                'unidad_medida' => 'kg',
                'stock_actual' => 6.200,
                'stock_minimo' => 10.000,
                'capacidad_maxima' => 35.000,
                'costo_unitario' => 9200.00,
                'proveedor_nombre' => 'Agrícola del Valle Orgánico',
                'proveedor_nit' => '900.556.778-4',
                'proveedor_telefono' => '+57 318 776 5544',
                'ubicacion_almacen' => 'Cámara de Verduras y Ensaladas',
                'temperatura_almacen' => '8°C',
            ],
            [
                'nombre' => 'Pepino Japonés Kiuri',
                'codigo' => 'SKU-VEG-02',
                'categoria' => 'vegetales',
                'unidad_medida' => 'kg',
                'stock_actual' => 15.000,
                'stock_minimo' => 6.000,
                'capacidad_maxima' => 30.000,
                'costo_unitario' => 4500.00,
                'proveedor_nombre' => 'Agrícola del Valle Orgánico',
                'proveedor_nit' => '900.556.778-4',
                'proveedor_telefono' => '+57 318 776 5544',
                'ubicacion_almacen' => 'Cámara de Verduras',
                'temperatura_almacen' => '8°C',
            ],
            [
                'nombre' => 'Queso Crema Philadelphia Gastronómico',
                'codigo' => 'SKU-LAC-01',
                'categoria' => 'lacteos_quesos',
                'unidad_medida' => 'kg',
                'stock_actual' => 22.000,
                'stock_minimo' => 12.000,
                'capacidad_maxima' => 50.000,
                'costo_unitario' => 26000.00,
                'proveedor_nombre' => 'Lácteos Andinos S.A.',
                'proveedor_nit' => '860.123.456-7',
                'proveedor_telefono' => '+57 300 445 6677',
                'ubicacion_almacen' => 'Cámara Lácteos #01',
                'temperatura_almacen' => '4°C',
            ],
            [
                'nombre' => 'Semillas de Sésamo Tostado Bicolor',
                'codigo' => 'SKU-ARR-02',
                'categoria' => 'arroz_granos',
                'unidad_medida' => 'kg',
                'stock_actual' => 8.400,
                'stock_minimo' => 4.000,
                'capacidad_maxima' => 20.000,
                'costo_unitario' => 15000.00,
                'proveedor_nombre' => 'Tokyo Trading Co.',
                'proveedor_nit' => '901.889.001-3',
                'proveedor_telefono' => '+57 320 998 7766',
                'ubicacion_almacen' => 'Almacén Seco · Estante C1',
                'temperatura_almacen' => 'Ambiente',
            ],
            [
                'nombre' => 'Salsa de Soya Fermentada Kikkoman',
                'codigo' => 'SKU-SAL-01',
                'categoria' => 'salsas_condimentos',
                'unidad_medida' => 'l',
                'stock_actual' => 32.000,
                'stock_minimo' => 15.000,
                'capacidad_maxima' => 60.000,
                'costo_unitario' => 18500.00,
                'proveedor_nombre' => 'Nippon Foods Import',
                'proveedor_nit' => '900.112.334-5',
                'proveedor_telefono' => '+57 312 334 5566',
                'ubicacion_almacen' => 'Almacén Seco · Estante C2',
                'temperatura_almacen' => 'Ambiente',
            ],
            [
                'nombre' => 'Salsa Dulce Unagi Kabayaki',
                'codigo' => 'SKU-SAL-02',
                'categoria' => 'salsas_condimentos',
                'unidad_medida' => 'l',
                'stock_actual' => 9.500,
                'stock_minimo' => 5.000,
                'capacidad_maxima' => 25.000,
                'costo_unitario' => 24000.00,
                'proveedor_nombre' => 'Tokyo Trading Co.',
                'proveedor_nit' => '901.889.001-3',
                'proveedor_telefono' => '+57 320 998 7766',
                'ubicacion_almacen' => 'Cámara Salsa / Nevera Barra',
                'temperatura_almacen' => '4°C',
            ],
            [
                'nombre' => 'Pasta de Wasabi Shizuoka',
                'codigo' => 'SKU-SAL-03',
                'categoria' => 'salsas_condimentos',
                'unidad_medida' => 'kg',
                'stock_actual' => 3.800,
                'stock_minimo' => 2.500,
                'capacidad_maxima' => 12.000,
                'costo_unitario' => 38000.00,
                'proveedor_nombre' => 'Nippon Foods Import',
                'proveedor_nit' => '900.112.334-5',
                'proveedor_telefono' => '+57 312 334 5566',
                'ubicacion_almacen' => 'Cámara Fría Barra',
                'temperatura_almacen' => '2°C',
            ],
            [
                'nombre' => 'Panko Japonés Extra Crocante',
                'codigo' => 'SKU-ARR-03',
                'categoria' => 'arroz_granos',
                'unidad_medida' => 'kg',
                'stock_actual' => 18.000,
                'stock_minimo' => 8.000,
                'capacidad_maxima' => 40.000,
                'costo_unitario' => 9500.00,
                'proveedor_nombre' => 'Tokyo Trading Co.',
                'proveedor_nit' => '901.889.001-3',
                'proveedor_telefono' => '+57 320 998 7766',
                'ubicacion_almacen' => 'Almacén Seco · Estante A2',
                'temperatura_almacen' => 'Ambiente',
            ],
            [
                'nombre' => 'Cajas Eco Bento Biodegradable',
                'codigo' => 'SKU-PAC-01',
                'categoria' => 'packaging',
                'unidad_medida' => 'unidad',
                'stock_actual' => 420.000,
                'stock_minimo' => 200.000,
                'capacidad_maxima' => 1500.000,
                'costo_unitario' => 1800.00,
                'proveedor_nombre' => 'EcoPack Colombia S.A.S.',
                'proveedor_nit' => '901.445.667-8',
                'proveedor_telefono' => '+57 301 223 9988',
                'ubicacion_almacen' => 'Bodega Empaques · Rack 01',
                'temperatura_almacen' => 'Ambiente',
            ],
        ];

        $insumosMap = [];
        foreach ($insumosData as $item) {
            $insumo = Insumo::updateOrCreate(['codigo' => $item['codigo']], $item);
            $insumosMap[$item['codigo']] = $insumo;
        }

        // 2. Vincular Recetas (Escandallos) a los Productos del Menú
        $productos = Producto::all()->keyBy('nombre');

        $recetasConfig = [
            'California Roll' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.120, 'merma' => 2.0, 'notas' => 'Arroz shari sazonado'],
                ['codigo' => 'SKU-PES-04', 'cantidad' => 0.040, 'merma' => 0.0, 'notas' => 'Kani desmenuzado'],
                ['codigo' => 'SKU-VEG-01', 'cantidad' => 0.030, 'merma' => 5.0, 'notas' => 'Láminas de palta fresca'],
                ['codigo' => 'SKU-VEG-02', 'cantidad' => 0.020, 'merma' => 2.0, 'notas' => 'Bastones de pepino kiuri'],
                ['codigo' => 'SKU-ALG-01', 'cantidad' => 0.020, 'merma' => 0.0, 'notas' => 'Media hoja nori tostada'],
                ['codigo' => 'SKU-ARR-02', 'cantidad' => 0.005, 'merma' => 0.0, 'notas' => 'Sésamo para cobertura'],
            ],
            'Philadelphia Roll' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.120, 'merma' => 2.0, 'notas' => 'Arroz shari'],
                ['codigo' => 'SKU-PES-01', 'cantidad' => 0.060, 'merma' => 4.0, 'notas' => 'Lomo salmón fresco en tiras'],
                ['codigo' => 'SKU-LAC-01', 'cantidad' => 0.040, 'merma' => 1.0, 'notas' => 'Queso crema en manga'],
                ['codigo' => 'SKU-ALG-01', 'cantidad' => 0.020, 'merma' => 0.0, 'notas' => 'Media hoja nori'],
                ['codigo' => 'SKU-ARR-02', 'cantidad' => 0.005, 'merma' => 0.0, 'notas' => 'Sésamo blanco y negro'],
            ],
            'Spicy Tuna Roll' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.120, 'merma' => 2.0, 'notas' => 'Arroz shari'],
                ['codigo' => 'SKU-PES-02', 'cantidad' => 0.065, 'merma' => 3.0, 'notas' => 'Tartar de atún maguro picante'],
                ['codigo' => 'SKU-ALG-01', 'cantidad' => 0.020, 'merma' => 0.0, 'notas' => 'Media hoja nori'],
                ['codigo' => 'SKU-ARR-02', 'cantidad' => 0.005, 'merma' => 0.0, 'notas' => 'Sésamo tostado'],
                ['codigo' => 'SKU-SAL-01', 'cantidad' => 0.010, 'merma' => 0.0, 'notas' => 'Base salsa picante'],
            ],
            'Avocado Maki' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.090, 'merma' => 1.0, 'notas' => 'Arroz shari'],
                ['codigo' => 'SKU-VEG-01', 'cantidad' => 0.050, 'merma' => 4.0, 'notas' => 'Palta hass en tiras'],
                ['codigo' => 'SKU-ALG-01', 'cantidad' => 0.020, 'merma' => 0.0, 'notas' => 'Media hoja nori'],
            ],
            'Dragon Roll' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.130, 'merma' => 2.0, 'notas' => 'Arroz shari'],
                ['codigo' => 'SKU-PES-03', 'cantidad' => 0.070, 'merma' => 5.0, 'notas' => 'Langostino furai'],
                ['codigo' => 'SKU-LAC-01', 'cantidad' => 0.035, 'merma' => 1.0, 'notas' => 'Queso crema'],
                ['codigo' => 'SKU-VEG-01', 'cantidad' => 0.060, 'merma' => 4.0, 'notas' => 'Cobertura de aguacate'],
                ['codigo' => 'SKU-SAL-02', 'cantidad' => 0.020, 'merma' => 0.0, 'notas' => 'Hilos salsa unagi'],
                ['codigo' => 'SKU-ALG-01', 'cantidad' => 0.020, 'merma' => 0.0, 'notas' => 'Media hoja nori'],
            ],
            'Rainbow Roll' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.120, 'merma' => 2.0, 'notas' => 'Arroz shari'],
                ['codigo' => 'SKU-PES-01', 'cantidad' => 0.035, 'merma' => 3.0, 'notas' => 'Lámina de salmón cobertura'],
                ['codigo' => 'SKU-PES-02', 'cantidad' => 0.035, 'merma' => 3.0, 'notas' => 'Lámina de atún maguro'],
                ['codigo' => 'SKU-VEG-01', 'cantidad' => 0.030, 'merma' => 3.0, 'notas' => 'Lámina de aguacate'],
                ['codigo' => 'SKU-PES-04', 'cantidad' => 0.030, 'merma' => 0.0, 'notas' => 'Relleno de kani'],
                ['codigo' => 'SKU-ALG-01', 'cantidad' => 0.020, 'merma' => 0.0, 'notas' => 'Media hoja nori'],
            ],
            'Volcano Roll' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.130, 'merma' => 2.0, 'notas' => 'Arroz shari'],
                ['codigo' => 'SKU-PES-01', 'cantidad' => 0.050, 'merma' => 4.0, 'notas' => 'Salmón sellado'],
                ['codigo' => 'SKU-LAC-01', 'cantidad' => 0.040, 'merma' => 1.0, 'notas' => 'Queso crema'],
                ['codigo' => 'SKU-ARR-03', 'cantidad' => 0.025, 'merma' => 2.0, 'notas' => 'Rebozado panko crujiente'],
                ['codigo' => 'SKU-SAL-02', 'cantidad' => 0.015, 'merma' => 0.0, 'notas' => 'Salsa gratinada'],
            ],
            'Ebi Crunch' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.120, 'merma' => 2.0, 'notas' => 'Arroz shari'],
                ['codigo' => 'SKU-PES-03', 'cantidad' => 0.060, 'merma' => 4.0, 'notas' => 'Langostino tigre'],
                ['codigo' => 'SKU-LAC-01', 'cantidad' => 0.030, 'merma' => 1.0, 'notas' => 'Queso crema'],
                ['codigo' => 'SKU-ARR-03', 'cantidad' => 0.030, 'merma' => 2.0, 'notas' => 'Panko crocante exterior'],
            ],
            'Nigiri Salmón (2 pzs)' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.050, 'merma' => 1.0, 'notas' => '2 quenelles de shari'],
                ['codigo' => 'SKU-PES-01', 'cantidad' => 0.040, 'merma' => 3.0, 'notas' => '2 láminas de salmón'],
                ['codigo' => 'SKU-SAL-03', 'cantidad' => 0.005, 'merma' => 0.0, 'notas' => 'Wasabi entre arroz y pescado'],
            ],
            'Nigiri Atún Rojo (2 pzs)' => [
                ['codigo' => 'SKU-ARR-01', 'cantidad' => 0.050, 'merma' => 1.0, 'notas' => '2 quenelles de shari'],
                ['codigo' => 'SKU-PES-02', 'cantidad' => 0.040, 'merma' => 3.0, 'notas' => '2 láminas de atún maguro'],
                ['codigo' => 'SKU-SAL-03', 'cantidad' => 0.005, 'merma' => 0.0, 'notas' => 'Wasabi toque'],
            ],
            'Sashimi Mixto (9 cortes)' => [
                ['codigo' => 'SKU-PES-01', 'cantidad' => 0.080, 'merma' => 5.0, 'notas' => '3 cortes lomo salmón'],
                ['codigo' => 'SKU-PES-02', 'cantidad' => 0.080, 'merma' => 5.0, 'notas' => '3 cortes lomo atún'],
                ['codigo' => 'SKU-SAL-03', 'cantidad' => 0.010, 'merma' => 0.0, 'notas' => 'Wasabi artesanal'],
            ],
        ];

        foreach ($recetasConfig as $productoNombre => $ingredientes) {
            if (! isset($productos[$productoNombre])) {
                continue;
            }
            $producto = $productos[$productoNombre];

            foreach ($ingredientes as $item) {
                if (isset($insumosMap[$item['codigo']])) {
                    Receta::updateOrCreate([
                        'producto_id' => $producto->id,
                        'insumo_id' => $insumosMap[$item['codigo']]->id,
                    ], [
                        'cantidad' => $item['cantidad'],
                        'merma_esperada_pct' => $item['merma'],
                        'notas' => $item['notas'],
                    ]);
                }
            }
        }

        // 3. Crear Historial de Movimientos Iniciales para Kardex & Gráficos KDS
        $salmon = $insumosMap['SKU-PES-01'];
        $atun = $insumosMap['SKU-PES-02'];
        $arroz = $insumosMap['SKU-ARR-01'];

        // Compra inicial
        MovimientoInventario::create([
            'insumo_id' => $salmon->id,
            'tipo' => 'compra',
            'cantidad' => 15.000,
            'saldo_anterior' => 0,
            'saldo_posterior' => 15.000,
            'costo_unitario' => 54.00,
            'costo_total' => 810.00,
            'user_id' => $admin?->id,
            'motivo' => 'Factura Guía #4092 - Bahía Solano Seafood S.A.S.',
            'referencia_documento' => 'FAC-4092',
            'created_at' => now()->subHours(6),
        ]);

        // Consumos por comandas del turno
        $consumosMock = [
            ['insumo' => $salmon, 'qty' => 3.500, 'motivo' => 'Consumo COC-01 comanda #101 (Salmón Roll x 14)', 'horas' => 5],
            ['insumo' => $salmon, 'qty' => 4.200, 'motivo' => 'Consumo COC-01 comanda #104 (Nigiris & Rainbow x 18)', 'horas' => 4],
            ['insumo' => $salmon, 'qty' => 3.800, 'motivo' => 'Consumo COC-01 comanda #109 (Volcano Roll x 15)', 'horas' => 2],
            ['insumo' => $atun, 'qty' => 2.800, 'motivo' => 'Consumo COC-01 comanda #102 (Spicy Tuna x 12)', 'horas' => 3],
            ['insumo' => $arroz, 'qty' => 12.500, 'motivo' => 'Consumo diario Shari cocina central (35 rolls)', 'horas' => 3],
        ];

        foreach ($consumosMock as $c) {
            $insumo = $c['insumo'];
            $costoTotal = round($c['qty'] * (float) $insumo->costo_unitario, 2);
            MovimientoInventario::create([
                'insumo_id' => $insumo->id,
                'tipo' => 'consumo_venta',
                'cantidad' => $c['qty'],
                'saldo_anterior' => (float) $insumo->stock_actual + $c['qty'],
                'saldo_posterior' => (float) $insumo->stock_actual,
                'costo_unitario' => $insumo->costo_unitario,
                'costo_total' => $costoTotal,
                'user_id' => $admin?->id,
                'motivo' => $c['motivo'],
                'referencia_documento' => 'KDS-ORD-'.rand(100, 200),
                'created_at' => now()->subHours($c['horas']),
            ]);
        }

        // Mermas reportadas hoy
        MovimientoInventario::create([
            'insumo_id' => $salmon->id,
            'tipo' => 'merma',
            'cantidad' => 0.300,
            'saldo_anterior' => 3.500,
            'saldo_posterior' => 3.200,
            'costo_unitario' => 54.00,
            'costo_total' => 16.20,
            'user_id' => $admin?->id,
            'motivo' => 'Merma operativa de corte y desespinado en estación fría',
            'referencia_documento' => 'MER-001',
            'created_at' => now()->subHours(1),
        ]);

        MovimientoInventario::create([
            'insumo_id' => $insumosMap['SKU-VEG-01']->id,
            'tipo' => 'merma',
            'cantidad' => 0.800,
            'saldo_anterior' => 7.000,
            'saldo_posterior' => 6.200,
            'costo_unitario' => 9.20,
            'costo_total' => 7.36,
            'user_id' => $admin?->id,
            'motivo' => 'Oxidación y sobremaduración palta hass',
            'referencia_documento' => 'MER-002',
            'created_at' => now()->subHours(2),
        ]);
    }
}
