<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\CategoriaInsumo;
use App\Models\Cliente;
use App\Models\CuentaPorPagar;
use App\Models\DireccionCliente;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\PermisoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatosPruebaRealistasSeeder extends Seeder
{
    /**
     * Llena la base de datos con un catálogo gastronómico completo, inventario con recetas,
     * equipo de trabajo colombiano (2 cajeros, admin, meseros, cocina, delivery),
     * mesas, clientes fidelizados, pedidos históricos, reservas y turnos de caja activos.
     */
    public function run(): void
    {
        $this->command?->info('Iniciando carga de datos realistas para RestoMaster Colombia...');

        // 1. Sucursal Principal
        $sucursal = Sucursal::first() ?? Sucursal::create([
            'nombre' => 'RestoMaster Provenza · Medellín',
            'direccion' => 'Carrera 35 # 8A-19, Provenza, El Poblado, Medellín',
            'telefono' => '+57 604 444 8899',
            'nit_ruc' => '901.458.789-3',
            'activo' => true,
        ]);

        // 2. Roles
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador']);
        $gerenteRole = Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente de Operaciones']);
        $cajeroRole = Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero']);
        $meseroRole = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero']);
        $cocinaRole = Role::firstOrCreate(['slug' => 'cocina'], ['nombre' => 'Jefe de Cocina / KDS']);
        $barraRole = Role::firstOrCreate(['slug' => 'barra'], ['nombre' => 'Bartender / Barra']);
        $deliveryRole = Role::firstOrCreate(['slug' => 'delivery'], ['nombre' => 'Repartidor Delivery']);

        // Contraseña unificada para demo
        $rawPassword = env('DEMO_USERS_PASSWORD', 'restomaster2026');
        $unifiedPassword = Hash::make($rawPassword);

        // 3. Equipo de Trabajo (Colombianos reales con nombres, teléfonos y correos)
        $personal = [
            // Administrador
            [
                'email' => 'admin@restomaster.com',
                'name' => 'Alejandro Restrepo Gómez',
                'role_id' => $adminRole->id,
                'telefono' => '+57 300 458 9201',
                'slug_rol' => 'admin',
            ],
            // Gerente
            [
                'email' => 'gerente@restomaster.com',
                'name' => 'Valentina Jaramillo Morales',
                'role_id' => $gerenteRole->id,
                'telefono' => '+57 310 829 4411',
                'slug_rol' => 'gerente',
            ],
            // 2 Cajeros
            [
                'email' => 'cajero@restomaster.com',
                'name' => 'Sebastián Castaño Rivera',
                'role_id' => $cajeroRole->id,
                'telefono' => '+57 314 736 1092',
                'slug_rol' => 'cajero',
            ],
            [
                'email' => 'mariana.caja@restomaster.com',
                'name' => 'Mariana Zapata Betancur',
                'role_id' => $cajeroRole->id,
                'telefono' => '+57 301 552 8490',
                'slug_rol' => 'cajero',
            ],
            // 4 Meseros
            [
                'email' => 'mesero@restomaster.com',
                'name' => 'Juan David Montoya Ortiz',
                'role_id' => $meseroRole->id,
                'telefono' => '+57 300 123 9988',
                'slug_rol' => 'mesero',
            ],
            [
                'email' => 'daniela.mesero@restomaster.com',
                'name' => 'Daniela Cárdenas Ospina',
                'role_id' => $meseroRole->id,
                'telefono' => '+57 312 405 8821',
                'slug_rol' => 'mesero',
            ],
            [
                'email' => 'mateo.mesero@restomaster.com',
                'name' => 'Mateo Henao Zuluaga',
                'role_id' => $meseroRole->id,
                'telefono' => '+57 315 889 4432',
                'slug_rol' => 'mesero',
            ],
            [
                'email' => 'camila.mesero@restomaster.com',
                'name' => 'Camila Salazar Posada',
                'role_id' => $meseroRole->id,
                'telefono' => '+57 318 672 1904',
                'slug_rol' => 'mesero',
            ],
            // Cocina & Barra
            [
                'email' => 'cocina@restomaster.com',
                'name' => 'Carlos Mario Echeverri (Chef Ejecutivo)',
                'role_id' => $cocinaRole->id,
                'telefono' => '+57 300 781 2234',
                'slug_rol' => 'cocina',
            ],
            [
                'email' => 'esteban.cocina@restomaster.com',
                'name' => 'Esteban Quintero Londoño (Sous Chef)',
                'role_id' => $cocinaRole->id,
                'telefono' => '+57 302 998 1145',
                'slug_rol' => 'cocina',
            ],
            [
                'email' => 'barra@restomaster.com',
                'name' => 'Andrés Felipe Vélez (Head Bartender)',
                'role_id' => $barraRole->id,
                'telefono' => '+57 311 632 7709',
                'slug_rol' => 'barra',
            ],
            // 3 Delivery / Repartidores
            [
                'email' => 'delivery@restomaster.com',
                'name' => 'Brayan Stiven Muñoz',
                'role_id' => $deliveryRole->id,
                'telefono' => '+57 301 882 3341',
                'slug_rol' => 'delivery',
            ],
            [
                'email' => 'jhoan.delivery@restomaster.com',
                'name' => 'Jhoan Alexis Arango',
                'role_id' => $deliveryRole->id,
                'telefono' => '+57 313 776 5522',
                'slug_rol' => 'delivery',
            ],
            [
                'email' => 'kevin.delivery@restomaster.com',
                'name' => 'Kevin Andrés Pineda',
                'role_id' => $deliveryRole->id,
                'telefono' => '+57 304 551 9087',
                'slug_rol' => 'delivery',
            ],
        ];

        $usersByEmail = [];
        $permisoService = app(PermisoService::class);

        foreach ($personal as $p) {
            $user = User::updateOrCreate(
                ['email' => $p['email']],
                [
                    'name' => $p['name'],
                    'role_id' => $p['role_id'],
                    'telefono' => $p['telefono'],
                    'sucursal_id' => $sucursal->id,
                    'activo' => true,
                    'password' => $unifiedPassword,
                    'email_verified_at' => now(),
                ]
            );
            $usersByEmail[$p['email']] = $user;
            $permisoService->aplicarPlantilla($user, $p['slug_rol']);
        }

        // 4. Cajas y Apertura de Turnos Activos
        $cajaPrincipal = Caja::firstOrCreate(
            ['codigo' => 'CAJ-01'],
            [
                'sucursal_id' => $sucursal->id,
                'nombre' => 'Caja Principal Salón',
                'activa' => true,
            ]
        );

        $cajaBarra = Caja::firstOrCreate(
            ['codigo' => 'CAJ-02'],
            [
                'sucursal_id' => $sucursal->id,
                'nombre' => 'Caja Barra & Terraza',
                'activa' => true,
            ]
        );

        $cajero1 = $usersByEmail['cajero@restomaster.com'];
        $cajero2 = $usersByEmail['mariana.caja@restomaster.com'];

        // Asegurar turno abierto en Caja 1
        $turno1 = TurnoCaja::where('caja_id', $cajaPrincipal->id)->where('estado', 'abierto')->first();
        if (! $turno1) {
            $turno1 = TurnoCaja::create([
                'caja_id' => $cajaPrincipal->id,
                'user_id' => $cajero1->id,
                'apertura_en' => Carbon::today()->setTime(11, 30),
                'monto_inicial' => 200000.00,
                'monto_esperado_efectivo' => 200000.00,
                'estado' => 'abierto',
                'notas_apertura' => 'Apertura de turno almuerzo - Base $200.000 COP',
            ]);
        }

        // Turno en Caja 2
        $turno2 = TurnoCaja::where('caja_id', $cajaBarra->id)->where('estado', 'abierto')->first();
        if (! $turno2) {
            $turno2 = TurnoCaja::create([
                'caja_id' => $cajaBarra->id,
                'user_id' => $cajero2->id,
                'apertura_en' => Carbon::today()->setTime(12, 00),
                'monto_inicial' => 150000.00,
                'monto_esperado_efectivo' => 150000.00,
                'estado' => 'abierto',
                'notas_apertura' => 'Apertura turno barra - Base $150.000 COP',
            ]);
        }

        // 5. Categorías de Insumos (con Color e Ícono)
        $catInsumosData = [
            ['nombre' => 'Pescados & Mariscos Frescos', 'slug' => 'pescados-mariscos', 'color' => '#0284c7', 'icono' => 'set_meal', 'orden' => 1],
            ['nombre' => 'Carnes Selectas & Proteínas', 'slug' => 'carnes-proteinas', 'color' => '#dc2626', 'icono' => 'lunch_dining', 'orden' => 2],
            ['nombre' => 'Granos, Arroces & Fideos', 'slug' => 'granos-arroces', 'color' => '#d97706', 'icono' => 'grain', 'orden' => 3],
            ['nombre' => 'Vegetales Frescos & Huerta', 'slug' => 'vegetales-huerta', 'color' => '#16a34a', 'icono' => 'eco', 'orden' => 4],
            ['nombre' => 'Salsas & Especias Especiales', 'slug' => 'salsas-especias', 'color' => '#7c3aed', 'icono' => 'kitchen', 'orden' => 5],
            ['nombre' => 'Licores & Coctelería de Barra', 'slug' => 'licores-barra', 'color' => '#db2777', 'icono' => 'local_bar', 'orden' => 6],
            ['nombre' => 'Bebidas Frías & Cervezas', 'slug' => 'bebidas-frias', 'color' => '#06b6d4', 'icono' => 'sports_bar', 'orden' => 7],
            ['nombre' => 'Lácteos & Quesos', 'slug' => 'lacteos-quesos', 'color' => '#f59e0b', 'icono' => 'egg', 'orden' => 8],
        ];

        $catsInsumo = [];
        foreach ($catInsumosData as $ci) {
            $catsInsumo[$ci['slug']] = CategoriaInsumo::updateOrCreate(
                ['slug' => $ci['slug']],
                $ci
            );
        }

        // 6. Insumos con Stock, Unidad de Medida y Costo en Pesos Colombianos
        $insumosData = [
            // Pescados & Mariscos
            [
                'categoria_id' => $catsInsumo['pescados-mariscos']->id,
                'codigo' => 'INS-SAL-01',
                'nombre' => 'Salmón Noruego Fresco (Filete)',
                'unidad_medida' => 'kg',
                'stock_actual' => 28.5,
                'stock_minimo' => 8.0,
                'costo_unitario' => 65000.00,
                'proveedor_nombre' => 'Pescados y Mariscos del Pacífico S.A.S.',
            ],
            [
                'categoria_id' => $catsInsumo['pescados-mariscos']->id,
                'codigo' => 'INS-ATU-01',
                'nombre' => 'Atún Aleta Amarilla Grado Sashimi',
                'unidad_medida' => 'kg',
                'stock_actual' => 16.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 72000.00,
                'proveedor_nombre' => 'Pescados y Mariscos del Pacífico S.A.S.',
            ],
            [
                'categoria_id' => $catsInsumo['pescados-mariscos']->id,
                'codigo' => 'INS-LAN-01',
                'nombre' => 'Langostinos Tigre U15 Pelados',
                'unidad_medida' => 'kg',
                'stock_actual' => 22.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 58000.00,
                'proveedor_nombre' => 'Distribuidora Marina del Caribe',
            ],
            [
                'categoria_id' => $catsInsumo['pescados-mariscos']->id,
                'codigo' => 'INS-PES-01',
                'nombre' => 'Pesca Blanca del Día (Corvina/Róbalo)',
                'unidad_medida' => 'kg',
                'stock_actual' => 14.5,
                'stock_minimo' => 4.0,
                'costo_unitario' => 42000.00,
                'proveedor_nombre' => 'Pescados y Mariscos del Pacífico S.A.S.',
            ],
            // Carnes
            [
                'categoria_id' => $catsInsumo['carnes-proteinas']->id,
                'codigo' => 'INS-LOM-01',
                'nombre' => 'Lomo Fino de Res Angus',
                'unidad_medida' => 'kg',
                'stock_actual' => 25.0,
                'stock_minimo' => 7.0,
                'costo_unitario' => 48000.00,
                'proveedor_nombre' => 'Carnes Frías San Martín',
            ],
            [
                'categoria_id' => $catsInsumo['carnes-proteinas']->id,
                'codigo' => 'INS-CER-01',
                'nombre' => 'Panceta de Cerdo Ahumada Chashu',
                'unidad_medida' => 'kg',
                'stock_actual' => 18.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 28000.00,
                'proveedor_nombre' => 'Carnes Frías San Martín',
            ],
            [
                'categoria_id' => $catsInsumo['carnes-proteinas']->id,
                'codigo' => 'INS-POL-01',
                'nombre' => 'Pechuga de Pollo Fresca',
                'unidad_medida' => 'kg',
                'stock_actual' => 24.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 22000.00,
                'proveedor_nombre' => 'Avícola Los Andes',
            ],
            // Granos & Arroces
            [
                'categoria_id' => $catsInsumo['granos-arroces']->id,
                'codigo' => 'INS-ARR-01',
                'nombre' => 'Arroz Koshihikari Especial Sushi',
                'unidad_medida' => 'kg',
                'stock_actual' => 95.0,
                'stock_minimo' => 25.0,
                'costo_unitario' => 9500.00,
                'proveedor_nombre' => 'Importadora Oriental de Colombia',
            ],
            [
                'categoria_id' => $catsInsumo['granos-arroces']->id,
                'codigo' => 'INS-FID-01',
                'nombre' => 'Fideos Ramen Frescos Artesanales',
                'unidad_medida' => 'kg',
                'stock_actual' => 32.0,
                'stock_minimo' => 10.0,
                'costo_unitario' => 14000.00,
                'proveedor_nombre' => 'Fideos & Masas Niponas',
            ],
            // Vegetales
            [
                'categoria_id' => $catsInsumo['vegetales-huerta']->id,
                'codigo' => 'INS-AGU-01',
                'nombre' => 'Aguacate Hass Calidad Extra',
                'unidad_medida' => 'kg',
                'stock_actual' => 40.0,
                'stock_minimo' => 10.0,
                'costo_unitario' => 8500.00,
                'proveedor_nombre' => 'Agrícola San Jerónimo',
            ],
            [
                'categoria_id' => $catsInsumo['vegetales-huerta']->id,
                'codigo' => 'INS-PEP-01',
                'nombre' => 'Pepino Cohombro Seleccionado',
                'unidad_medida' => 'kg',
                'stock_actual' => 20.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 4200.00,
                'proveedor_nombre' => 'Central Mayorista de Antioquia',
            ],
            [
                'categoria_id' => $catsInsumo['vegetales-huerta']->id,
                'codigo' => 'INS-CEB-01',
                'nombre' => 'Cebolla Morada Ocañera',
                'unidad_medida' => 'kg',
                'stock_actual' => 28.0,
                'stock_minimo' => 8.0,
                'costo_unitario' => 4800.00,
                'proveedor_nombre' => 'Central Mayorista de Antioquia',
            ],
            // Salsas & Especias
            [
                'categoria_id' => $catsInsumo['salsas-especias']->id,
                'codigo' => 'INS-SOY-01',
                'nombre' => 'Salsa de Soya Kikkoman',
                'unidad_medida' => 'lt',
                'stock_actual' => 45.0,
                'stock_minimo' => 12.0,
                'costo_unitario' => 24000.00,
                'proveedor_nombre' => 'Importadora Oriental de Colombia',
            ],
            [
                'categoria_id' => $catsInsumo['salsas-especias']->id,
                'codigo' => 'INS-NOR-01',
                'nombre' => 'Algas Nori Gold (Paquete 50 Hojas)',
                'unidad_medida' => 'unidad',
                'stock_actual' => 50.0,
                'stock_minimo' => 15.0,
                'costo_unitario' => 35000.00,
                'proveedor_nombre' => 'Importadora Oriental de Colombia',
            ],
            // Lácteos
            [
                'categoria_id' => $catsInsumo['lacteos-quesos']->id,
                'codigo' => 'INS-QUE-01',
                'nombre' => 'Queso Crema Philadelphia',
                'unidad_medida' => 'kg',
                'stock_actual' => 30.0,
                'stock_minimo' => 8.0,
                'costo_unitario' => 32000.00,
                'proveedor_nombre' => 'Lácteos del Valle S.A.',
            ],
            // Barra & Licores
            [
                'categoria_id' => $catsInsumo['licores-barra']->id,
                'codigo' => 'INS-SAK-01',
                'nombre' => 'Sake Junmai Botella 720ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 24.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 68000.00,
                'proveedor_nombre' => 'Licores Finos de Colombia',
            ],
            [
                'categoria_id' => $catsInsumo['licores-barra']->id,
                'codigo' => 'INS-GIN-01',
                'nombre' => 'Ginebra Tanqueray London Dry 750ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 18.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 85000.00,
                'proveedor_nombre' => 'Licores Finos de Colombia',
            ],
            [
                'categoria_id' => $catsInsumo['licores-barra']->id,
                'codigo' => 'INS-VOD-01',
                'nombre' => 'Vodka Smirnoff 750ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 16.0,
                'stock_minimo' => 4.0,
                'costo_unitario' => 52000.00,
                'proveedor_nombre' => 'Licores Finos de Colombia',
            ],
            // Bebidas
            [
                'categoria_id' => $catsInsumo['bebidas-frias']->id,
                'codigo' => 'INS-CER-ASA',
                'nombre' => 'Cerveza Asahi Super Dry (330ml)',
                'unidad_medida' => 'unidad',
                'stock_actual' => 72.0,
                'stock_minimo' => 24.0,
                'costo_unitario' => 8500.00,
                'proveedor_nombre' => 'Importadora Oriental de Colombia',
            ],
            [
                'categoria_id' => $catsInsumo['bebidas-frias']->id,
                'codigo' => 'INS-CER-CLU',
                'nombre' => 'Cerveza Club Colombia Dorada (330ml)',
                'unidad_medida' => 'unidad',
                'stock_actual' => 120.0,
                'stock_minimo' => 36.0,
                'costo_unitario' => 4200.00,
                'proveedor_nombre' => 'Bavaria S.A.',
            ],
            [
                'categoria_id' => $catsInsumo['bebidas-frias']->id,
                'codigo' => 'INS-PUL-MAR',
                'nombre' => 'Pulpa de Maracuyá Natural 100%',
                'unidad_medida' => 'kg',
                'stock_actual' => 18.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 12000.00,
                'proveedor_nombre' => 'Pulpas del Oriente',
            ],
        ];

        $insumosMap = [];
        foreach ($insumosData as $idat) {
            $ins = Insumo::updateOrCreate(
                ['codigo' => $idat['codigo']],
                array_merge($idat, ['activo' => true])
            );
            $insumosMap[$idat['codigo']] = $ins;
        }

        // 7. Categorías de la Carta / Menú (con Color distintivo para el POS e Ícono)
        $catMenuData = [
            ['nombre' => 'Rolls Especiales', 'slug' => 'rolls-especiales', 'color' => '#f97316', 'icono' => '🍣', 'orden' => 1],
            ['nombre' => 'Nigiris & Sashimis', 'slug' => 'nigiris-sashimis', 'color' => '#ef4444', 'icono' => 'set_meal', 'orden' => 2],
            ['nombre' => 'Entradas & Gyozas', 'slug' => 'entradas-gyozas', 'color' => '#eab308', 'icono' => 'ramen_dining', 'orden' => 3],
            ['nombre' => 'Woks, Arroces & Ramen', 'slug' => 'woks-ramen', 'color' => '#10b981', 'icono' => 'lunch_dining', 'orden' => 4],
            ['nombre' => 'Robata & Platos Fuertes', 'slug' => 'robata-grill', 'color' => '#8b5cf6', 'icono' => 'restaurant', 'orden' => 5],
            ['nombre' => 'Coctelería de Autor', 'slug' => 'cocteleria-autor', 'color' => '#ec4899', 'icono' => 'local_bar', 'orden' => 6],
            ['nombre' => 'Cervezas & Bebidas', 'slug' => 'cervezas-bebidas', 'color' => '#06b6d4', 'icono' => 'sports_bar', 'orden' => 7],
            ['nombre' => 'Postres Artesanales', 'slug' => 'postres-artesanales', 'color' => '#d97706', 'icono' => 'icecream', 'orden' => 8],
        ];

        $catsMenu = [];
        foreach ($catMenuData as $cm) {
            $catsMenu[$cm['slug']] = Categoria::updateOrCreate(
                ['slug' => $cm['slug']],
                array_merge($cm, ['activo' => true])
            );
        }

        // 8. Catálogo Completo de Platillos y Bebidas (en COP)
        $productosData = [
            // Rolls Especiales (Cocina Sushi)
            [
                'categoria_id' => $catsMenu['rolls-especiales']->id,
                'codigo' => 'PROD-DRAG',
                'nombre' => 'Dragon Roll Especial (10 bocados)',
                'slug' => 'dragon-roll-especial',
                'descripcion' => 'Langostino crocante en panko, aguacate Hass y salmón fresco flameado con salsa unagi y masago.',
                'precio' => 42000.00,
                'costo' => 16500.00,
                'area_cocina' => 'sushi',
                'recetas' => [
                    ['insumo' => 'INS-SAL-01', 'cant' => 0.080],
                    ['insumo' => 'INS-LAN-01', 'cant' => 0.060],
                    ['insumo' => 'INS-ARR-01', 'cant' => 0.120],
                    ['insumo' => 'INS-NOR-01', 'cant' => 1.0],
                    ['insumo' => 'INS-AGU-01', 'cant' => 0.050],
                ],
            ],
            [
                'categoria_id' => $catsMenu['rolls-especiales']->id,
                'codigo' => 'PROD-TIGR',
                'nombre' => 'Ojo de Tigre Roll Tempura (10 bocados)',
                'slug' => 'ojo-de-tigre-roll',
                'descripcion' => 'Salmón, atún rojo, queso philadelphia y cebollín, frito en tempura crocante con hilos de teriyaki.',
                'precio' => 39000.00,
                'costo' => 15000.00,
                'area_cocina' => 'sushi',
                'recetas' => [
                    ['insumo' => 'INS-SAL-01', 'cant' => 0.060],
                    ['insumo' => 'INS-ATU-01', 'cant' => 0.050],
                    ['insumo' => 'INS-QUE-01', 'cant' => 0.040],
                    ['insumo' => 'INS-ARR-01', 'cant' => 0.120],
                    ['insumo' => 'INS-NOR-01', 'cant' => 1.0],
                ],
            ],
            [
                'categoria_id' => $catsMenu['rolls-especiales']->id,
                'codigo' => 'PROD-PHILA',
                'nombre' => 'Filadelfia Clásico Roll (10 bocados)',
                'slug' => 'filadelfia-clasico-roll',
                'descripcion' => 'Salmón fresco del pacífico, queso crema philadelphia y semillas de sésamo tostadas.',
                'precio' => 34000.00,
                'costo' => 12500.00,
                'area_cocina' => 'sushi',
                'recetas' => [
                    ['insumo' => 'INS-SAL-01', 'cant' => 0.090],
                    ['insumo' => 'INS-QUE-01', 'cant' => 0.050],
                    ['insumo' => 'INS-ARR-01', 'cant' => 0.120],
                    ['insumo' => 'INS-NOR-01', 'cant' => 1.0],
                ],
            ],
            [
                'categoria_id' => $catsMenu['rolls-especiales']->id,
                'codigo' => 'PROD-ACEV',
                'nombre' => 'Acevichado Nikkei Roll (10 bocados)',
                'slug' => 'acevichado-nikkei-roll',
                'descripcion' => 'Langostino apanado, pesca blanca fresca, salsa acevichada de ají amarillo y canchita chulpe.',
                'precio' => 44000.00,
                'costo' => 17000.00,
                'area_cocina' => 'sushi',
                'recetas' => [
                    ['insumo' => 'INS-LAN-01', 'cant' => 0.070],
                    ['insumo' => 'INS-PES-01', 'cant' => 0.060],
                    ['insumo' => 'INS-ARR-01', 'cant' => 0.120],
                    ['insumo' => 'INS-NOR-01', 'cant' => 1.0],
                ],
            ],
            // Nigiris & Sashimis (Sushi)
            [
                'categoria_id' => $catsMenu['nigiris-sashimis']->id,
                'codigo' => 'PROD-SASH-SAL',
                'nombre' => 'Sashimi Salmón Noruego (5 Cortes)',
                'slug' => 'sashimi-salmon-noruego',
                'descripcion' => 'Finos cortes de salmón fresco noruego calidad superior con wasabi artesanal y jengibre encurtido.',
                'precio' => 36000.00,
                'costo' => 14000.00,
                'area_cocina' => 'sushi',
                'recetas' => [
                    ['insumo' => 'INS-SAL-01', 'cant' => 0.120],
                ],
            ],
            [
                'categoria_id' => $catsMenu['nigiris-sashimis']->id,
                'codigo' => 'PROD-SASH-ATU',
                'nombre' => 'Sashimi Atún Aleta Amarilla (5 Cortes)',
                'slug' => 'sashimi-atun-aleta-amarilla',
                'descripcion' => 'Cortes gruesos de atún fresco del pacífico con emulsión de soya y sésamo.',
                'precio' => 40000.00,
                'costo' => 16000.00,
                'area_cocina' => 'sushi',
                'recetas' => [
                    ['insumo' => 'INS-ATU-01', 'cant' => 0.120],
                ],
            ],
            // Entradas & Gyozas (Cocina Caliente)
            [
                'categoria_id' => $catsMenu['entradas-gyozas']->id,
                'codigo' => 'PROD-GYOZ-CER',
                'nombre' => 'Gyozas de Cerdo & Shiitake (5 uds)',
                'slug' => 'gyozas-cerdo-shiitake',
                'descripcion' => 'Empanaditas japonesas rellenas de cerdo especiado, selladas a la plancha con salsa ponzu.',
                'precio' => 26000.00,
                'costo' => 9000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-CER-01', 'cant' => 0.100],
                    ['insumo' => 'INS-CEB-01', 'cant' => 0.030],
                ],
            ],
            [
                'categoria_id' => $catsMenu['entradas-gyozas']->id,
                'codigo' => 'PROD-CEV-NIK',
                'nombre' => 'Ceviche Clásico Nikkei',
                'slug' => 'ceviche-clasico-nikkei',
                'descripcion' => 'Pesca fresca del día marinada en leche de tigre de ají amarillo, cebolla morada, aguacate y choclo.',
                'precio' => 36000.00,
                'costo' => 13000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-PES-01', 'cant' => 0.120],
                    ['insumo' => 'INS-CEB-01', 'cant' => 0.040],
                    ['insumo' => 'INS-AGU-01', 'cant' => 0.050],
                ],
            ],
            // Woks & Ramen (Cocina Caliente)
            [
                'categoria_id' => $catsMenu['woks-ramen']->id,
                'codigo' => 'PROD-RAM-TON',
                'nombre' => 'Ramen Tonkotsu Tradicional',
                'slug' => 'ramen-tonkotsu-tradicional',
                'descripcion' => 'Caldo concentrado de 12 horas, fideos ramen frescos, chashu de panceta, huevo marinado y nori.',
                'precio' => 42000.00,
                'costo' => 14500.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-FID-01', 'cant' => 0.180],
                    ['insumo' => 'INS-CER-01', 'cant' => 0.100],
                    ['insumo' => 'INS-SOY-01', 'cant' => 0.020],
                ],
            ],
            [
                'categoria_id' => $catsMenu['woks-ramen']->id,
                'codigo' => 'PROD-CHAU-ESP',
                'nombre' => 'Arroz Chaufa Especial al Wok',
                'slug' => 'arroz-chaufa-especial',
                'descripcion' => 'Arroz salteado a fuego vivo con lomo de res, pollo, tortilla de huevo, cebollín y soya oscura.',
                'precio' => 38000.00,
                'costo' => 13000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-ARR-01', 'cant' => 0.150],
                    ['insumo' => 'INS-POL-01', 'cant' => 0.080],
                    ['insumo' => 'INS-LOM-01', 'cant' => 0.060],
                    ['insumo' => 'INS-SOY-01', 'cant' => 0.030],
                ],
            ],
            // Robata & Platos Fuertes (Cocina Caliente)
            [
                'categoria_id' => $catsMenu['robata-grill']->id,
                'codigo' => 'PROD-LOM-SALT',
                'nombre' => 'Lomo Saltado Nikkei al Wok',
                'slug' => 'lomo-saltado-nikkei',
                'descripcion' => 'Lomo fino 250g salteado con cebolla morada, tomate criollo, papas rústicas y arroz jazmín.',
                'precio' => 54000.00,
                'costo' => 22000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-LOM-01', 'cant' => 0.250],
                    ['insumo' => 'INS-CEB-01', 'cant' => 0.080],
                    ['insumo' => 'INS-SOY-01', 'cant' => 0.030],
                ],
            ],
            [
                'categoria_id' => $catsMenu['robata-grill']->id,
                'codigo' => 'PROD-SALM-GRILL',
                'nombre' => 'Salmón Glaseado al Miso',
                'slug' => 'salmon-glaseado-al-miso',
                'descripcion' => 'Filete de salmón 200g a la parrilla robata con glaseado dulce de miso y vegetales salteados.',
                'precio' => 58000.00,
                'costo' => 24000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-SAL-01', 'cant' => 0.200],
                ],
            ],
            // Coctelería de Autor (Barra)
            [
                'categoria_id' => $catsMenu['cocteleria-autor']->id,
                'codigo' => 'PROD-GIN-LYCH',
                'nombre' => 'Gin Tonic de Lychee & Cardamomo',
                'slug' => 'gin-tonic-lychee-cardamomo',
                'descripcion' => 'Ginebra Tanqueray, tónica premium, frutos dulces de lychee y perfume de cardamomo.',
                'precio' => 36000.00,
                'costo' => 11000.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-GIN-01', 'cant' => 0.060],
                ],
            ],
            [
                'categoria_id' => $catsMenu['cocteleria-autor']->id,
                'codigo' => 'PROD-MOSC-MULE',
                'nombre' => 'Moscow Mule Maracuyá',
                'slug' => 'moscow-mule-maracuya',
                'descripcion' => 'Vodka Smirnoff, pulpa de maracuyá colombiana, ginger beer artesanal y menta fresca.',
                'precio' => 34000.00,
                'costo' => 10000.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-VOD-01', 'cant' => 0.060],
                    ['insumo' => 'INS-PUL-MAR', 'cant' => 0.050],
                ],
            ],
            [
                'categoria_id' => $catsMenu['cocteleria-autor']->id,
                'codigo' => 'PROD-SAKE-COCK',
                'nombre' => 'Copa de Sake Junmai Importado',
                'slug' => 'copa-sake-junmai',
                'descripcion' => 'Sake japonés puro de arroz servido frío o caliente en taza ochoko tradicional.',
                'precio' => 28000.00,
                'costo' => 9000.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-SAK-01', 'cant' => 0.150],
                ],
            ],
            // Cervezas & Bebidas (Barra)
            [
                'categoria_id' => $catsMenu['cervezas-bebidas']->id,
                'codigo' => 'PROD-CERV-ASAHI',
                'nombre' => 'Cerveza Asahi Super Dry (330ml)',
                'slug' => 'cerveza-asahi-super-dry',
                'descripcion' => 'Cerveza japonesa lager premium de final seco y refrescante.',
                'precio' => 18000.00,
                'costo' => 8500.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-CER-ASA', 'cant' => 1.0],
                ],
            ],
            [
                'categoria_id' => $catsMenu['cervezas-bebidas']->id,
                'codigo' => 'PROD-CERV-CLUB',
                'nombre' => 'Cerveza Club Colombia Dorada (330ml)',
                'slug' => 'cerveza-club-colombia-dorada',
                'descripcion' => 'Cerveza colombiana tipo pilsen con notas de malta tostada.',
                'precio' => 12000.00,
                'costo' => 4200.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-CER-CLU', 'cant' => 1.0],
                ],
            ],
            [
                'categoria_id' => $catsMenu['cervezas-bebidas']->id,
                'codigo' => 'PROD-LIM-COCO',
                'nombre' => 'Limonada de Coco Artesanal',
                'slug' => 'limonada-de-coco-artesanal',
                'descripcion' => 'Zumo de limón recién exprimido, crema de coco caribeña y hielo frappé cremoso.',
                'precio' => 15000.00,
                'costo' => 4500.00,
                'area_cocina' => 'barra',
                'recetas' => [],
            ],
            // Postres (Cocina Caliente)
            [
                'categoria_id' => $catsMenu['postres-artesanales']->id,
                'codigo' => 'PROD-MOCHI-MIX',
                'nombre' => 'Mochis Helados Artesanales (3 uds)',
                'slug' => 'mochis-helados-artesanales',
                'descripcion' => 'Masa de arroz glutinoso rellena de helado: Té verde Matcha, Frutos Rojos y Chocolate.',
                'precio' => 22000.00,
                'costo' => 7500.00,
                'area_cocina' => 'caliente',
                'recetas' => [],
            ],
            [
                'categoria_id' => $catsMenu['postres-artesanales']->id,
                'codigo' => 'PROD-CHEE-JAP',
                'nombre' => 'Cheesecake Japonés Esponjoso',
                'slug' => 'cheesecake-japones-esponjoso',
                'descripcion' => 'Tarta soufflé de queso suave con coulis de frutos del bosque andinos.',
                'precio' => 24000.00,
                'costo' => 8000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-QUE-01', 'cant' => 0.080],
                ],
            ],
        ];

        $productosMap = [];
        foreach ($productosData as $pData) {
            $recetasList = $pData['recetas'];
            unset($pData['recetas'], $pData['codigo']);

            $prod = Producto::updateOrCreate(
                ['slug' => $pData['slug']],
                array_merge($pData, ['activo' => true])
            );
            $productosMap[$pData['slug']] = $prod;

            // Recetas
            Receta::where('producto_id', $prod->id)->delete();
            foreach ($recetasList as $rItem) {
                if (isset($insumosMap[$rItem['insumo']])) {
                    Receta::create([
                        'producto_id' => $prod->id,
                        'insumo_id' => $insumosMap[$rItem['insumo']]->id,
                        'cantidad' => $rItem['cant'],
                        'merma_esperada_pct' => 5.0,
                    ]);
                }
            }
        }

        // 9. Mesas en Zonas de Restaurante (Salón, Terraza, Barra, VIP)
        $mesasData = [
            // Salón
            ['numero' => '1', 'capacidad' => 4, 'zona' => 'salon', 'estado' => 'libre'],
            ['numero' => '2', 'capacidad' => 4, 'zona' => 'salon', 'estado' => 'libre'],
            ['numero' => '3', 'capacidad' => 4, 'zona' => 'salon', 'estado' => 'ocupada'],
            ['numero' => '4', 'capacidad' => 2, 'zona' => 'salon', 'estado' => 'libre'],
            ['numero' => '5', 'capacidad' => 6, 'zona' => 'salon', 'estado' => 'libre'],
            ['numero' => '6', 'capacidad' => 6, 'zona' => 'salon', 'estado' => 'libre'],
            // Terraza
            ['numero' => '7', 'capacidad' => 4, 'zona' => 'terraza', 'estado' => 'ocupada'],
            ['numero' => '8', 'capacidad' => 4, 'zona' => 'terraza', 'estado' => 'libre'],
            ['numero' => '9', 'capacidad' => 6, 'zona' => 'terraza', 'estado' => 'libre'],
            ['numero' => '10', 'capacidad' => 6, 'zona' => 'terraza', 'estado' => 'libre'],
            // Barra
            ['numero' => 'B1', 'capacidad' => 2, 'zona' => 'barra', 'estado' => 'libre'],
            ['numero' => 'B2', 'capacidad' => 2, 'zona' => 'barra', 'estado' => 'ocupada'],
            ['numero' => 'B3', 'capacidad' => 2, 'zona' => 'barra', 'estado' => 'libre'],
            ['numero' => 'B4', 'capacidad' => 2, 'zona' => 'barra', 'estado' => 'libre'],
            // VIP
            ['numero' => 'VIP-1', 'capacidad' => 8, 'zona' => 'vip', 'estado' => 'reservada'],
            ['numero' => 'VIP-2', 'capacidad' => 10, 'zona' => 'vip', 'estado' => 'libre'],
        ];

        $mesero1 = $usersByEmail['mesero@restomaster.com'];
        $mesero2 = $usersByEmail['daniela.mesero@restomaster.com'];
        $mesero3 = $usersByEmail['mateo.mesero@restomaster.com'];

        $mesasMap = [];
        foreach ($mesasData as $mData) {
            $meseroId = null;
            if ($mData['numero'] === '3') {
                $meseroId = $mesero1->id;
            } elseif ($mData['numero'] === '7') {
                $meseroId = $mesero2->id;
            } elseif ($mData['numero'] === 'B2') {
                $meseroId = $mesero3->id;
            }

            $m = Mesa::updateOrCreate(
                ['numero' => $mData['numero'], 'sucursal_id' => $sucursal->id],
                [
                    'capacidad' => $mData['capacidad'],
                    'zona' => $mData['zona'],
                    'estado' => $mData['estado'],
                    'mesero_id' => $meseroId,
                ]
            );
            $mesasMap[$mData['numero']] = $m;
        }

        // 10. Clientes Colombianos con Fidelización y Direcciones
        $clientesData = [
            [
                'nombre' => 'Andrés Felipe Restrepo Londoño',
                'telefono' => '3004589201',
                'email' => 'andres.restrepo@gmail.com',
                'documento' => '1017234890',
                'tier' => 'vip',
                'puntos_fidelidad' => 2850,
                'total_gastado' => 1850000.00,
                'visitas_count' => 14,
                'alergias' => 'Ninguna',
                'preferencias' => 'Mesa en terraza, Dragon Roll con salsa extra',
                'direccion' => 'Carrera 25 # 3Sur-45, Apto 1202, Edificio Bosques del Poblado',
                'barrio' => 'El Poblado, Medellín',
            ],
            [
                'nombre' => 'Carolina Duque Botero',
                'telefono' => '3108294411',
                'email' => 'carolina.duque@hotmail.com',
                'documento' => '1036782114',
                'tier' => 'frecuente',
                'puntos_fidelidad' => 1420,
                'total_gastado' => 980000.00,
                'visitas_count' => 8,
                'alergias' => 'Mariscos crudos (solo consume langostinos cocidos)',
                'preferencias' => 'Lomo saltado y ramen caliente',
                'direccion' => 'Circular 4 # 73-28, Casa 101',
                'barrio' => 'Laureles, Medellín',
            ],
            [
                'nombre' => 'Mateo Gómez Jaramillo',
                'telefono' => '3147361092',
                'email' => 'mateo.gomez@epm.com.co',
                'documento' => '1020456789',
                'tier' => 'frecuente',
                'puntos_fidelidad' => 980,
                'total_gastado' => 650000.00,
                'visitas_count' => 5,
                'alergias' => null,
                'preferencias' => 'Coctelería Gin Tonic y ceviches',
                'direccion' => 'Calle 36D Sur # 27A-15, Apto 503',
                'barrio' => 'La Magnolia, Envigado',
            ],
            [
                'nombre' => 'Valentina Morales Echeverri',
                'telefono' => '3015528490',
                'email' => 'vale.morales@bancolombia.com.co',
                'documento' => '1152441902',
                'tier' => 'vip',
                'puntos_fidelidad' => 3400,
                'total_gastado' => 2400000.00,
                'visitas_count' => 18,
                'alergias' => 'Gluten (prefiere sashimis y nigiris sin panko)',
                'preferencias' => 'Sashimi de atún y cócteles sin azúcar añadido',
                'direccion' => 'Transversal 39B # 72-10',
                'barrio' => 'Segundo Parque de Laureles, Medellín',
            ],
            [
                'nombre' => 'Santiago Uribe Arango',
                'telefono' => '3124058821',
                'email' => 'santiago.uribe@sura.com.co',
                'documento' => '71345890',
                'tier' => 'ocasional',
                'puntos_fidelidad' => 350,
                'total_gastado' => 220000.00,
                'visitas_count' => 2,
                'alergias' => null,
                'preferencias' => 'Almuerzos ejecutivos chaufa y cervezas',
                'direccion' => 'Carrera 43A # 1Sur-150, Edificio Torre Ónix',
                'barrio' => 'El Poblado, Medellín',
            ],
            [
                'nombre' => 'Juliana Pérez Henao',
                'telefono' => '3158894432',
                'email' => 'juli.perez@yahoo.es',
                'documento' => '1019876543',
                'tier' => 'frecuente',
                'puntos_fidelidad' => 820,
                'total_gastado' => 540000.00,
                'visitas_count' => 4,
                'alergias' => null,
                'preferencias' => 'Mochis helados y roll tempura',
                'direccion' => 'Calle 10 # 32-40, Apto 302',
                'barrio' => 'Provenza, Medellín',
            ],
            [
                'nombre' => 'Federico Gutiérrez Saldarriaga',
                'telefono' => '3186721904',
                'email' => 'fede.gutierrez@gmail.com',
                'documento' => '70890123',
                'tier' => 'vip',
                'puntos_fidelidad' => 4100,
                'total_gastado' => 3200000.00,
                'visitas_count' => 22,
                'alergias' => null,
                'preferencias' => 'Salón VIP para cenas de negocios, Asahi fría',
                'direccion' => 'Carrera 28 # 10-120, Casa 4',
                'barrio' => 'Las Lomas, El Poblado, Medellín',
            ],
            [
                'nombre' => 'Mariana Ospina Betancur',
                'telefono' => '3001239988',
                'email' => 'mariana.ospina@gmail.com',
                'documento' => '1037654321',
                'tier' => 'ocasional',
                'puntos_fidelidad' => 150,
                'total_gastado' => 110000.00,
                'visitas_count' => 1,
                'alergias' => null,
                'preferencias' => 'Pedidos para llevar y delivery',
                'direccion' => 'Calle 33 # 65C-20',
                'barrio' => 'Conquistadores, Medellín',
            ],
        ];

        $clientesMap = [];
        foreach ($clientesData as $cData) {
            $dir = $cData['direccion'];
            $barrio = $cData['barrio'];
            unset($cData['direccion'], $cData['barrio']);

            $cl = Cliente::updateOrCreate(
                ['documento' => $cData['documento']],
                array_merge($cData, [
                    'activo' => true,
                    'acepta_tratamiento_datos' => true,
                    'fecha_autorizacion_datos' => now()->subDays(rand(10, 60)),
                    'canal_autorizacion_datos' => 'pos_terminal',
                    'autoriza_whatsapp' => true,
                    'autoriza_email' => true,
                ])
            );
            $clientesMap[$cData['documento']] = $cl;

            DireccionCliente::updateOrCreate(
                ['cliente_id' => $cl->id, 'es_predeterminada' => true],
                [
                    'etiqueta' => 'Principal / Domicilio',
                    'direccion' => $dir,
                    'referencia_apto' => '',
                    'barrio_ciudad' => $barrio,
                    'telefono_contacto' => $cl->telefono,
                    'es_predeterminada' => true,
                ]
            );
        }

        // 11. Pedidos Históricos (Últimos 5 días) y Pedidos de Hoy
        // Generar historial rico para reportes, ventas, KDS y propinas
        $diasAtras = [4, 3, 2, 1, 0];
        $meserosList = [$mesero1, $mesero2, $mesero3, $usersByEmail['camila.mesero@restomaster.com']];
        $repartidoresList = [$usersByEmail['delivery@restomaster.com'], $usersByEmail['jhoan.delivery@restomaster.com']];
        $clientesList = array_values($clientesMap);

        $contadorPedidos = 100;

        foreach ($diasAtras as $dias) {
            $fechaBase = Carbon::today()->subDays($dias);
            $numPedidosDia = $dias === 0 ? 5 : 4;

            for ($i = 0; $i < $numPedidosDia; $i++) {
                $contadorPedidos++;
                $horaAlmuerzo = $i % 2 === 0;
                $hora = $horaAlmuerzo ? rand(12, 14) : rand(19, 22);
                $minuto = rand(10, 55);
                $fechaPedido = (clone $fechaBase)->setTime($hora, $minuto);

                $cliente = $clientesList[array_rand($clientesList)];
                $mesero = $meserosList[array_rand($meserosList)];
                $tipoPedido = ($i === 3 && $dias > 0) ? 'delivery' : 'mesa';

                // Mesa aleatoria
                $mesaNumero = (string) rand(1, 10);
                $mesaObj = $mesasMap[$mesaNumero] ?? $mesasMap['1'];

                // 2 a 4 productos
                $platosKeys = [
                    'dragon-roll-especial',
                    'ojo-de-tigre-roll',
                    'filadelfia-clasico-roll',
                    'gyozas-cerdo-shiitake',
                    'ramen-tonkotsu-tradicional',
                    'lomo-saltado-nikkei',
                    'gin-tonic-lychee-cardamomo',
                    'cerveza-asahi-super-dry',
                    'limonada-de-coco-artesanal',
                ];

                $seleccionados = array_rand(array_flip($platosKeys), rand(2, 3));
                if (! is_array($seleccionados)) {
                    $seleccionados = [$seleccionados];
                }

                $subtotal = 0;
                $itemsData = [];

                foreach ($seleccionados as $pSlug) {
                    $prod = $productosMap[$pSlug];
                    $cant = rand(1, 2);
                    $sub = (float) $prod->precio * $cant;
                    $subtotal += $sub;

                    $itemsData[] = [
                        'producto_id' => $prod->id,
                        'nombre_producto' => $prod->nombre,
                        'cantidad' => $cant,
                        'precio_unitario' => $prod->precio,
                        'subtotal' => $sub,
                        'area_cocina' => $prod->area_cocina,
                        'estado_cocina' => 'servido',
                        'inventario_descontado' => true,
                        'iniciado_en' => (clone $fechaPedido)->addMinutes(2),
                        'listo_en' => (clone $fechaPedido)->addMinutes(rand(12, 22)),
                    ];
                }

                $propina = round($subtotal * 0.10, 0); // 10% voluntaria
                $total = $subtotal + $propina;
                $metodos = ['efectivo', 'tarjeta', 'transferencia'];
                $metodo = $metodos[array_rand($metodos)];

                $codigoPedido = 'ORD-'.date('Ymd', $fechaPedido->timestamp).'-'.str_pad($contadorPedidos, 4, '0', STR_PAD_LEFT);

                $pedido = Pedido::firstOrCreate(
                    ['codigo' => $codigoPedido],
                    [
                        'tipo' => $tipoPedido,
                        'estado' => 'pagado',
                        'sucursal_id' => $sucursal->id,
                        'mesa_id' => $tipoPedido === 'mesa' ? $mesaObj->id : null,
                        'usuario_id' => $cajero1->id,
                        'mesero_id' => $mesero->id,
                        'turno_caja_id' => $turno1->id,
                        'nombre_cliente' => $cliente->nombre,
                        'telefono_cliente' => $cliente->telefono,
                        'cliente_id' => $cliente->id,
                        'repartidor_id' => $tipoPedido === 'delivery' ? $repartidoresList[0]->id : null,
                        'subtotal' => $subtotal,
                        'descuento' => 0,
                        'total' => $total,
                        'propina' => $propina,
                        'porcentaje_propina' => 10.0,
                        'metodo_pago' => $metodo,
                        'monto_pagado' => $total,
                        'monto_pago_efectivo' => $metodo === 'efectivo' ? $total : 0,
                        'monto_pago_tarjeta' => $metodo === 'tarjeta' ? $total : 0,
                        'cambio' => 0,
                        'pagado_en' => (clone $fechaPedido)->addMinutes(45),
                        'created_at' => $fechaPedido,
                        'updated_at' => (clone $fechaPedido)->addMinutes(45),
                    ]
                );

                if ($pedido->wasRecentlyCreated) {
                    foreach ($itemsData as $it) {
                        ItemPedido::create(array_merge($it, ['pedido_id' => $pedido->id]));
                    }
                }
            }
        }

        // 12. Pedidos Activos en Sala (Mesas 3, 7 y Barra B2) para ver KDS y POS en vivo
        // Mesa 3: Ocupada con comanda en cocina
        $pedidoActivo1 = Pedido::firstOrCreate(
            ['codigo' => 'ORD-'.date('Ymd').'-0991'],
            [
                'tipo' => 'mesa',
                'estado' => 'en_preparacion',
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $mesasMap['3']->id,
                'usuario_id' => $cajero1->id,
                'mesero_id' => $mesero1->id,
                'turno_caja_id' => $turno1->id,
                'nombre_cliente' => 'Andrés Restrepo',
                'telefono_cliente' => '3004589201',
                'cliente_id' => $clientesMap['1017234890']->id,
                'subtotal' => 104000.00,
                'descuento' => 0,
                'total' => 114400.00,
                'propina' => 10400.00,
                'porcentaje_propina' => 10.0,
                'created_at' => now()->subMinutes(14),
                'updated_at' => now()->subMinutes(14),
            ]
        );

        if ($pedidoActivo1->wasRecentlyCreated) {
            ItemPedido::create([
                'pedido_id' => $pedidoActivo1->id,
                'producto_id' => $productosMap['dragon-roll-especial']->id,
                'nombre_producto' => 'Dragon Roll Especial (10 bocados)',
                'cantidad' => 1,
                'precio_unitario' => 42000.00,
                'subtotal' => 42000.00,
                'area_cocina' => 'sushi',
                'estado_cocina' => 'en_preparacion',
                'notas' => 'Sin wasabi dentro del rollo',
                'iniciado_en' => now()->subMinutes(10),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo1->id,
                'producto_id' => $productosMap['gyozas-cerdo-shiitake']->id,
                'nombre_producto' => 'Gyozas de Cerdo & Shiitake (5 uds)',
                'cantidad' => 1,
                'precio_unitario' => 26000.00,
                'subtotal' => 26000.00,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'listo',
                'iniciado_en' => now()->subMinutes(12),
                'listo_en' => now()->subMinutes(2),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo1->id,
                'producto_id' => $productosMap['gin-tonic-lychee-cardamomo']->id,
                'nombre_producto' => 'Gin Tonic de Lychee & Cardamomo',
                'cantidad' => 1,
                'precio_unitario' => 36000.00,
                'subtotal' => 36000.00,
                'area_cocina' => 'barra',
                'estado_cocina' => 'servido',
                'iniciado_en' => now()->subMinutes(13),
                'listo_en' => now()->subMinutes(8),
            ]);
        }

        // Mesa 7 (Terraza): Ocupada, lista para pedir cuenta
        $pedidoActivo2 = Pedido::firstOrCreate(
            ['codigo' => 'ORD-'.date('Ymd').'-0992'],
            [
                'tipo' => 'mesa',
                'estado' => 'servido',
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $mesasMap['7']->id,
                'usuario_id' => $cajero1->id,
                'mesero_id' => $mesero2->id,
                'turno_caja_id' => $turno1->id,
                'nombre_cliente' => 'Carolina Duque',
                'telefono_cliente' => '3108294411',
                'cliente_id' => $clientesMap['1036782114']->id,
                'subtotal' => 96000.00,
                'descuento' => 0,
                'total' => 105600.00,
                'propina' => 9600.00,
                'porcentaje_propina' => 10.0,
                'created_at' => now()->subMinutes(35),
                'updated_at' => now()->subMinutes(15),
            ]
        );

        if ($pedidoActivo2->wasRecentlyCreated) {
            ItemPedido::create([
                'pedido_id' => $pedidoActivo2->id,
                'producto_id' => $productosMap['lomo-saltado-nikkei']->id,
                'nombre_producto' => 'Lomo Saltado Nikkei al Wok',
                'cantidad' => 1,
                'precio_unitario' => 54000.00,
                'subtotal' => 54000.00,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'servido',
                'iniciado_en' => now()->subMinutes(30),
                'listo_en' => now()->subMinutes(18),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo2->id,
                'producto_id' => $productosMap['ramen-tonkotsu-tradicional']->id,
                'nombre_producto' => 'Ramen Tonkotsu Tradicional',
                'cantidad' => 1,
                'precio_unitario' => 42000.00,
                'subtotal' => 42000.00,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'servido',
                'iniciado_en' => now()->subMinutes(30),
                'listo_en' => now()->subMinutes(16),
            ]);
        }

        // 13. Reservas Colombianas (Hoy y Próximos Días)
        $reservasData = [
            [
                'nombre_contacto' => 'Andrés Felipe Restrepo',
                'telefono_contacto' => '3004589201',
                'email_contacto' => 'andres.restrepo@gmail.com',
                'fecha' => Carbon::today(),
                'hora_llegada' => '13:00',
                'personas' => 4,
                'estado' => 'confirmada',
                'anticipo' => 0,
                'notas' => 'Almuerzo familiar cumpleaños. Prefieren mesa 3 o salón principal.',
                'mesa_id' => $mesasMap['3']->id,
            ],
            [
                'nombre_contacto' => 'Valentina Morales Echeverri',
                'telefono_contacto' => '3015528490',
                'email_contacto' => 'vale.morales@bancolombia.com.co',
                'fecha' => Carbon::today(),
                'hora_llegada' => '20:00',
                'personas' => 6,
                'estado' => 'confirmada',
                'anticipo' => 100000.00,
                'notas' => 'Cena con amigas en terraza. Anticipo pagado por Nequi.',
                'mesa_id' => $mesasMap['9']->id,
            ],
            [
                'nombre_contacto' => 'Federico Gutiérrez Saldarriaga',
                'telefono_contacto' => '3186721904',
                'email_contacto' => 'fede.gutierrez@gmail.com',
                'fecha' => Carbon::today(),
                'hora_llegada' => '20:30',
                'personas' => 8,
                'estado' => 'confirmada',
                'anticipo' => 200000.00,
                'notas' => 'Cena ejecutiva reservada en Salón VIP-1. Atender con carta de autor.',
                'mesa_id' => $mesasMap['VIP-1']->id,
            ],
            [
                'nombre_contacto' => 'Carolina Duque Botero',
                'telefono_contacto' => '3108294411',
                'email_contacto' => 'carolina.duque@hotmail.com',
                'fecha' => Carbon::tomorrow(),
                'hora_llegada' => '14:00',
                'personas' => 2,
                'estado' => 'pendiente',
                'anticipo' => 0,
                'notas' => 'Almuerzo en barra de sushi.',
                'mesa_id' => $mesasMap['B1']->id,
            ],
            [
                'nombre_contacto' => 'Santiago Uribe Arango',
                'telefono_contacto' => '3124058821',
                'email_contacto' => 'santiago.uribe@sura.com.co',
                'fecha' => Carbon::tomorrow()->addDay(),
                'hora_llegada' => '21:00',
                'personas' => 4,
                'estado' => 'confirmada',
                'anticipo' => 50000.00,
                'notas' => 'Terraza exterior.',
                'mesa_id' => $mesasMap['8']->id,
            ],
        ];

        foreach ($reservasData as $rData) {
            $mesaId = $rData['mesa_id'];
            unset($rData['mesa_id']);

            $res = Reserva::firstOrCreate(
                [
                    'nombre_contacto' => $rData['nombre_contacto'],
                    'fecha' => $rData['fecha'],
                    'hora_llegada' => $rData['hora_llegada'],
                ],
                array_merge($rData, [
                    'sucursal_id' => $sucursal->id,
                    'duracion_min' => 120,
                    'origen' => 'whatsapp',
                    'created_by' => $cajero1->id,
                ])
            );

            if ($mesaId && $res->wasRecentlyCreated) {
                $res->mesas()->sync([$mesaId]);
            }
        }

        // 14. Facturas de Proveedores (Cuentas por Pagar)
        $cxpData = [
            [
                'proveedor_nombre' => 'Pescados y Mariscos del Pacífico S.A.S.',
                'proveedor_nit' => '900.876.543-2',
                'numero_factura' => 'FAC-PAC-4482',
                'concepto' => 'Compra semanal de Salmón Noruego y Atún Aleta Amarilla',
                'monto_total' => 2850000.00,
                'saldo_pendiente' => 0.00,
                'fecha_emision' => Carbon::today()->subDays(3),
                'fecha_vencimiento' => Carbon::today()->addDays(25),
                'estado' => 'pagado',
                'user_id' => $usersByEmail['admin@restomaster.com']->id,
            ],
            [
                'proveedor_nombre' => 'Carnes Frías San Martín',
                'proveedor_nit' => '890.123.456-1',
                'numero_factura' => 'FAC-CSM-9912',
                'concepto' => 'Lomo fino de res Angus y panceta de cerdo ahumada',
                'monto_total' => 1650000.00,
                'saldo_pendiente' => 650000.00,
                'fecha_emision' => Carbon::today()->subDays(2),
                'fecha_vencimiento' => Carbon::today()->addDays(12),
                'estado' => 'parcial',
                'user_id' => $usersByEmail['admin@restomaster.com']->id,
            ],
            [
                'proveedor_nombre' => 'Bavaria S.A.',
                'proveedor_nit' => '860.005.224-6',
                'numero_factura' => 'FAC-BAV-88231',
                'concepto' => 'Reposición Cerveza Club Colombia Dorada x5 cajas',
                'monto_total' => 504000.00,
                'saldo_pendiente' => 504000.00,
                'fecha_emision' => Carbon::today()->subDay(),
                'fecha_vencimiento' => Carbon::today()->addDays(15),
                'estado' => 'pendiente',
                'user_id' => $usersByEmail['admin@restomaster.com']->id,
            ],
        ];

        foreach ($cxpData as $cxp) {
            CuentaPorPagar::firstOrCreate(
                ['numero_factura' => $cxp['numero_factura']],
                $cxp
            );
        }

        // 15. Movimientos Iniciales de Inventario (Compras iniciales de stock)
        foreach ($insumosMap as $ins) {
            MovimientoInventario::firstOrCreate(
                ['referencia_documento' => 'FAC-INI-'.$ins->codigo],
                [
                    'insumo_id' => $ins->id,
                    'tipo' => 'compra',
                    'cantidad' => $ins->stock_actual,
                    'saldo_anterior' => 0,
                    'saldo_posterior' => $ins->stock_actual,
                    'costo_unitario' => $ins->costo_unitario,
                    'costo_total' => round($ins->stock_actual * (float) $ins->costo_unitario, 2),
                    'user_id' => $usersByEmail['admin@restomaster.com']->id,
                    'motivo' => "Carga inicial de inventario · Proveedor: {$ins->proveedor_nombre}",
                    'referencia_documento' => 'FAC-INI-'.$ins->codigo,
                ]
            );
        }

        $this->command?->info('✓ Carga de datos reales colombianos para RestoMaster completada con éxito.');
    }
}
