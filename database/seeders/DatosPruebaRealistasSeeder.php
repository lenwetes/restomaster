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
use Illuminate\Support\Str;

class DatosPruebaRealistasSeeder extends Seeder
{
    /**
     * Llena la base de datos con un catálogo gastronómico completo de RESTAURANTE GENERAL & PARRILLA,
     * inventario real con escandallos y recetas, equipo de trabajo colombiano (admin, 2 cajeros,
     * meseros, cocina, barra, repartidores), mesas en salón/terraza/barra/VIP, clientes colombianos
     * con fidelización, comandas activas en KDS, pedidos históricos con propinas y reservas.
     */
    public function run(): void
    {
        $this->command?->info('Iniciando carga de datos realistas de Restaurante General para RestoMaster Colombia...');

        // 0. Limpieza defensiva de datos demo previos para garantizar un catálogo libre de sushi
        $pedidosDemoIds = Pedido::where('codigo', 'like', 'ORD-%')->pluck('id');
        if ($pedidosDemoIds->isNotEmpty()) {
            ItemPedido::whereIn('pedido_id', $pedidosDemoIds)->delete();
            Pedido::whereIn('id', $pedidosDemoIds)->delete();
        }

        Receta::query()->delete();
        MovimientoInventario::where('referencia_documento', 'like', 'FAC-INI-%')->delete();

        // Eliminar productos previos si no están referenciados por pedidos externos
        Producto::whereDoesntHave('itemsPedido')->forceDelete();
        Categoria::whereDoesntHave('productos')->delete();

        Insumo::whereDoesntHave('recetas')->whereDoesntHave('movimientos')->forceDelete();
        CategoriaInsumo::whereDoesntHave('insumos')->delete();

        CuentaPorPagar::where('numero_factura', 'like', 'FAC-%')->delete();

        // 1. Sucursal Principal
        $sucursal = Sucursal::first() ?? Sucursal::create([
            'nombre' => 'RestoMaster Gourmet & Parrilla · Medellín',
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

        // Contraseña unificada para demo (aleatoria si no se define por entorno; nunca default débil commiteado)
        $rawPassword = env('DEMO_USERS_PASSWORD') ?: Str::password(16);
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
                'name' => 'Carlos Mario Echeverri (Chef Ejecutivo Parrilla)',
                'role_id' => $cocinaRole->id,
                'telefono' => '+57 300 781 2234',
                'slug_rol' => 'cocina',
            ],
            [
                'email' => 'esteban.cocina@restomaster.com',
                'name' => 'Esteban Quintero Londoño (Sous Chef Cocina)',
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

        // 5. Categorías de Insumos para Restaurante General (con Color e Ícono)
        $catInsumosData = [
            ['nombre' => 'Carnes de Res & Cerdo Selectas', 'slug' => 'carnes-res-cerdo', 'color' => '#dc2626', 'icono' => 'lunch_dining', 'orden' => 1],
            ['nombre' => 'Aves & Pollos de Granja', 'slug' => 'aves-pollos', 'color' => '#ea580c', 'icono' => 'egg', 'orden' => 2],
            ['nombre' => 'Pescados Frescos & Mariscos', 'slug' => 'pescados-mariscos', 'color' => '#0284c7', 'icono' => 'set_meal', 'orden' => 3],
            ['nombre' => 'Papas, Granos & Pastas', 'slug' => 'tuberculos-granos-pastas', 'color' => '#d97706', 'icono' => 'grain', 'orden' => 4],
            ['nombre' => 'Vegetales, Frutas & Huerta', 'slug' => 'vegetales-frutas', 'color' => '#16a34a', 'icono' => 'eco', 'orden' => 5],
            ['nombre' => 'Lácteos, Quesos & Cremas', 'slug' => 'lacteos-quesos', 'color' => '#f59e0b', 'icono' => 'restaurant', 'orden' => 6],
            ['nombre' => 'Licores, Destilados & Barra', 'slug' => 'licores-barra', 'color' => '#db2777', 'icono' => 'local_bar', 'orden' => 7],
            ['nombre' => 'Bebidas Frías & Cervezas', 'slug' => 'bebidas-cervezas', 'color' => '#06b6d4', 'icono' => 'sports_bar', 'orden' => 8],
        ];

        $catsInsumo = [];
        foreach ($catInsumosData as $ci) {
            $catsInsumo[$ci['slug']] = CategoriaInsumo::updateOrCreate(
                ['slug' => $ci['slug']],
                $ci
            );
        }

        // 6. Insumos con Stock, Unidad de Medida y Costo en Pesos Colombianos (COP)
        $insumosData = [
            // Carnes de Res & Cerdo
            [
                'categoria_id' => $catsInsumo['carnes-res-cerdo']->id,
                'codigo' => 'INS-BIF-01',
                'nombre' => 'Bife de Chorizo / Baby Beef Angus (Corte)',
                'unidad_medida' => 'kg',
                'stock_actual' => 28.0,
                'stock_minimo' => 8.0,
                'costo_unitario' => 48000.00,
                'proveedor_nombre' => 'Carnes Frías San Martín Medellín',
            ],
            [
                'categoria_id' => $catsInsumo['carnes-res-cerdo']->id,
                'codigo' => 'INS-COS-01',
                'nombre' => 'Costillas de Cerdo San Luis BBQ',
                'unidad_medida' => 'kg',
                'stock_actual' => 25.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 32000.00,
                'proveedor_nombre' => 'Carnes Frías San Martín Medellín',
            ],
            [
                'categoria_id' => $catsInsumo['carnes-res-cerdo']->id,
                'codigo' => 'INS-TOC-01',
                'nombre' => 'Tocino Carnudo para Chicharrón Crocante',
                'unidad_medida' => 'kg',
                'stock_actual' => 22.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 26000.00,
                'proveedor_nombre' => 'Carnes Frías San Martín Medellín',
            ],
            [
                'categoria_id' => $catsInsumo['carnes-res-cerdo']->id,
                'codigo' => 'INS-CAR-MOL',
                'nombre' => 'Carne Molida Angus para Hamburguesas Gourmet',
                'unidad_medida' => 'kg',
                'stock_actual' => 30.0,
                'stock_minimo' => 8.0,
                'costo_unitario' => 28000.00,
                'proveedor_nombre' => 'Carnes Frías San Martín Medellín',
            ],
            // Aves & Pollos
            [
                'categoria_id' => $catsInsumo['aves-pollos']->id,
                'codigo' => 'INS-PEC-01',
                'nombre' => 'Pechuga de Pollo Fresca Fileteada',
                'unidad_medida' => 'kg',
                'stock_actual' => 32.0,
                'stock_minimo' => 8.0,
                'costo_unitario' => 22000.00,
                'proveedor_nombre' => 'Avícola Los Andes de Antioquia',
            ],
            [
                'categoria_id' => $catsInsumo['aves-pollos']->id,
                'codigo' => 'INS-ALA-01',
                'nombre' => 'Alitas de Pollo Frescas Seleccionadas',
                'unidad_medida' => 'kg',
                'stock_actual' => 24.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 18000.00,
                'proveedor_nombre' => 'Avícola Los Andes de Antioquia',
            ],
            // Pescados & Mariscos
            [
                'categoria_id' => $catsInsumo['pescados-mariscos']->id,
                'codigo' => 'INS-ROB-01',
                'nombre' => 'Filete de Róbalo / Corvina del Pacífico',
                'unidad_medida' => 'kg',
                'stock_actual' => 18.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 42000.00,
                'proveedor_nombre' => 'Pescados y Mariscos del Pacífico S.A.S.',
            ],
            [
                'categoria_id' => $catsInsumo['pescados-mariscos']->id,
                'codigo' => 'INS-CAM-01',
                'nombre' => 'Camarones Jumbo U15 Limpios',
                'unidad_medida' => 'kg',
                'stock_actual' => 20.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 54000.00,
                'proveedor_nombre' => 'Pescados y Mariscos del Pacífico S.A.S.',
            ],
            // Tubérculos, Granos & Pastas
            [
                'categoria_id' => $catsInsumo['tuberculos-granos-pastas']->id,
                'codigo' => 'INS-PAP-CRI',
                'nombre' => 'Papa Criolla Limpia Selección',
                'unidad_medida' => 'kg',
                'stock_actual' => 60.0,
                'stock_minimo' => 15.0,
                'costo_unitario' => 5500.00,
                'proveedor_nombre' => 'Central Mayorista de Antioquia',
            ],
            [
                'categoria_id' => $catsInsumo['tuberculos-granos-pastas']->id,
                'codigo' => 'INS-PAP-RUS',
                'nombre' => 'Papa Rústica / Francesa Selección',
                'unidad_medida' => 'kg',
                'stock_actual' => 70.0,
                'stock_minimo' => 20.0,
                'costo_unitario' => 6200.00,
                'proveedor_nombre' => 'Central Mayorista de Antioquia',
            ],
            [
                'categoria_id' => $catsInsumo['tuberculos-granos-pastas']->id,
                'codigo' => 'INS-ARR-01',
                'nombre' => 'Arroz Blanco Especial Selección Diana',
                'unidad_medida' => 'kg',
                'stock_actual' => 80.0,
                'stock_minimo' => 20.0,
                'costo_unitario' => 4800.00,
                'proveedor_nombre' => 'Central Mayorista de Antioquia',
            ],
            [
                'categoria_id' => $catsInsumo['tuberculos-granos-pastas']->id,
                'codigo' => 'INS-PAS-FET',
                'nombre' => 'Pasta Fettuccine Artesanal al Huevo',
                'unidad_medida' => 'kg',
                'stock_actual' => 25.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 12500.00,
                'proveedor_nombre' => 'Pastas Italianas de Colombia',
            ],
            [
                'categoria_id' => $catsInsumo['tuberculos-granos-pastas']->id,
                'codigo' => 'INS-PAN-BRI',
                'nombre' => 'Pan Brioche Artesanal Mantequilla (unidad)',
                'unidad_medida' => 'unidad',
                'stock_actual' => 120.0,
                'stock_minimo' => 30.0,
                'costo_unitario' => 2500.00,
                'proveedor_nombre' => 'Panadería Francesa Artesanal',
            ],
            // Vegetales & Frutas
            [
                'categoria_id' => $catsInsumo['vegetales-frutas']->id,
                'codigo' => 'INS-AGU-HAS',
                'nombre' => 'Aguacate Hass Calidad Extra',
                'unidad_medida' => 'kg',
                'stock_actual' => 45.0,
                'stock_minimo' => 10.0,
                'costo_unitario' => 8500.00,
                'proveedor_nombre' => 'Agrícola San Jerónimo',
            ],
            [
                'categoria_id' => $catsInsumo['vegetales-frutas']->id,
                'codigo' => 'INS-TOM-CHI',
                'nombre' => 'Tomate Chonto & Cherry Huerta',
                'unidad_medida' => 'kg',
                'stock_actual' => 35.0,
                'stock_minimo' => 8.0,
                'costo_unitario' => 4800.00,
                'proveedor_nombre' => 'Central Mayorista de Antioquia',
            ],
            [
                'categoria_id' => $catsInsumo['vegetales-frutas']->id,
                'codigo' => 'INS-LECH-MIX',
                'nombre' => 'Mix de Lechugas Orgánicas Hidropónicas',
                'unidad_medida' => 'kg',
                'stock_actual' => 20.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 9500.00,
                'proveedor_nombre' => 'Huerta Orgánica del Oriente',
            ],
            [
                'categoria_id' => $catsInsumo['vegetales-frutas']->id,
                'codigo' => 'INS-PUL-FRU',
                'nombre' => 'Pulpa Natural de Frutas (Lulo/Mango/Maracuyá)',
                'unidad_medida' => 'kg',
                'stock_actual' => 35.0,
                'stock_minimo' => 8.0,
                'costo_unitario' => 11000.00,
                'proveedor_nombre' => 'Pulpas Naturales del Valle',
            ],
            [
                'categoria_id' => $catsInsumo['vegetales-frutas']->id,
                'codigo' => 'INS-LIM-TAH',
                'nombre' => 'Limón Tahití Jugoso Fresco',
                'unidad_medida' => 'kg',
                'stock_actual' => 30.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 4200.00,
                'proveedor_nombre' => 'Central Mayorista de Antioquia',
            ],
            // Lácteos, Quesos & Cremas
            [
                'categoria_id' => $catsInsumo['lacteos-quesos']->id,
                'codigo' => 'INS-QUE-PAR',
                'nombre' => 'Queso Parmesano Madurado Rallado',
                'unidad_medida' => 'kg',
                'stock_actual' => 18.0,
                'stock_minimo' => 4.0,
                'costo_unitario' => 46000.00,
                'proveedor_nombre' => 'Lácteos del Valle S.A.',
            ],
            [
                'categoria_id' => $catsInsumo['lacteos-quesos']->id,
                'codigo' => 'INS-QUE-CHE',
                'nombre' => 'Queso Cheddar Americano Fundente',
                'unidad_medida' => 'kg',
                'stock_actual' => 25.0,
                'stock_minimo' => 6.0,
                'costo_unitario' => 32000.00,
                'proveedor_nombre' => 'Lácteos del Valle S.A.',
            ],
            [
                'categoria_id' => $catsInsumo['lacteos-quesos']->id,
                'codigo' => 'INS-CRE-LEC',
                'nombre' => 'Crema de Leche de Campo Fresca',
                'unidad_medida' => 'lt',
                'stock_actual' => 35.0,
                'stock_minimo' => 8.0,
                'costo_unitario' => 14000.00,
                'proveedor_nombre' => 'Colanta Cooperativa Lechera',
            ],
            [
                'categoria_id' => $catsInsumo['lacteos-quesos']->id,
                'codigo' => 'INS-MAN-01',
                'nombre' => 'Mantequilla de Vaca Pura con Sal',
                'unidad_medida' => 'kg',
                'stock_actual' => 20.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 25000.00,
                'proveedor_nombre' => 'Colanta Cooperativa Lechera',
            ],
            // Licores & Barra
            [
                'categoria_id' => $catsInsumo['licores-barra']->id,
                'codigo' => 'INS-RON-MED',
                'nombre' => 'Ron Medellín Añejo 8 Años 750ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 20.0,
                'stock_minimo' => 5.0,
                'costo_unitario' => 58000.00,
                'proveedor_nombre' => 'Fábrica de Licores de Antioquia (FLA)',
            ],
            [
                'categoria_id' => $catsInsumo['licores-barra']->id,
                'codigo' => 'INS-GIN-TAN',
                'nombre' => 'Ginebra Tanqueray London Dry 750ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 16.0,
                'stock_minimo' => 4.0,
                'costo_unitario' => 85000.00,
                'proveedor_nombre' => 'Licores y Vinos del Mundo',
            ],
            [
                'categoria_id' => $catsInsumo['licores-barra']->id,
                'codigo' => 'INS-VOD-SMI',
                'nombre' => 'Vodka Smirnoff Red 750ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 15.0,
                'stock_minimo' => 4.0,
                'costo_unitario' => 52000.00,
                'proveedor_nombre' => 'Licores y Vinos del Mundo',
            ],
            // Bebidas Frías & Cervezas
            [
                'categoria_id' => $catsInsumo['bebidas-cervezas']->id,
                'codigo' => 'INS-CER-CLU',
                'nombre' => 'Cerveza Club Colombia Dorada 330ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 144.0,
                'stock_minimo' => 36.0,
                'costo_unitario' => 4200.00,
                'proveedor_nombre' => 'Bavaria S.A.',
            ],
            [
                'categoria_id' => $catsInsumo['bebidas-cervezas']->id,
                'codigo' => 'INS-CER-BBC',
                'nombre' => 'Cerveza BBC Monserrate Roja 330ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 96.0,
                'stock_minimo' => 24.0,
                'costo_unitario' => 5800.00,
                'proveedor_nombre' => 'Bavaria S.A. / BBC',
            ],
            [
                'categoria_id' => $catsInsumo['bebidas-cervezas']->id,
                'codigo' => 'INS-GAS-MAN',
                'nombre' => 'Gaseosa Postobón Manzana / Colombiana 400ml',
                'unidad_medida' => 'unidad',
                'stock_actual' => 120.0,
                'stock_minimo' => 30.0,
                'costo_unitario' => 2600.00,
                'proveedor_nombre' => 'Postobón S.A.',
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

        // 7. Categorías de la Carta / Menú para Restaurante General (con Color distintivo para el POS e Ícono)
        $catMenuData = [
            ['nombre' => 'Entradas & Picadas', 'slug' => 'entradas-picadas', 'color' => '#f97316', 'icono' => '🥗', 'orden' => 1],
            ['nombre' => 'Cortes a la Parrilla & Asados', 'slug' => 'cortes-parrilla', 'color' => '#dc2626', 'icono' => '🥩', 'orden' => 2],
            ['nombre' => 'Pollos Dorados & Costillas BBQ', 'slug' => 'pollos-costillas', 'color' => '#ea580c', 'icono' => '🍗', 'orden' => 3],
            ['nombre' => 'Pescados & Mariscos de la Casa', 'slug' => 'pescados-mariscos-casa', 'color' => '#0284c7', 'icono' => '🐟', 'orden' => 4],
            ['nombre' => 'Pastas Artesanales & Lasañas', 'slug' => 'pastas-artesanales', 'color' => '#10b981', 'icono' => '🍝', 'orden' => 5],
            ['nombre' => 'Hamburguesas Gourmet & Sandwiches', 'slug' => 'hamburguesas-sandwiches', 'color' => '#8b5cf6', 'icono' => '🍔', 'orden' => 6],
            ['nombre' => 'Coctelería Clásica & de Autor', 'slug' => 'cocteleria-barra', 'color' => '#ec4899', 'icono' => '🍸', 'orden' => 7],
            ['nombre' => 'Bebidas, Jugos Naturales & Cervezas', 'slug' => 'bebidas-jugos', 'color' => '#06b6d4', 'icono' => '🥤', 'orden' => 8],
            ['nombre' => 'Postres Artesanales de la Casa', 'slug' => 'postres-casa', 'color' => '#d97706', 'icono' => '🍰', 'orden' => 9],
        ];

        $catsMenu = [];
        foreach ($catMenuData as $cm) {
            $catsMenu[$cm['slug']] = Categoria::updateOrCreate(
                ['slug' => $cm['slug']],
                array_merge($cm, ['activo' => true])
            );
        }

        // 8. Catálogo Completo de Platillos y Bebidas de Restaurante General (en COP)
        $productosData = [
            // Entradas & Picadas
            [
                'categoria_id' => $catsMenu['entradas-picadas']->id,
                'codigo' => 'PROD-PIC-CRI',
                'nombre' => 'Picada Criolla RestoMaster (2-3 personas)',
                'slug' => 'picada-criolla-restomaster',
                'descripcion' => 'Chicharrón carnudo crocante, costillitas BBQ, papa criolla dorada, patacones de plátano verde y ají casero.',
                'precio' => 52000.00,
                'costo' => 19500.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-TOC-01', 'cant' => 0.200],
                    ['insumo' => 'INS-COS-01', 'cant' => 0.200],
                    ['insumo' => 'INS-PAP-CRI', 'cant' => 0.250],
                ],
            ],
            [
                'categoria_id' => $catsMenu['entradas-picadas']->id,
                'codigo' => 'PROD-EMP-CRI',
                'nombre' => 'Trilogía de Empanadas Artesanales con Ají (3 uds)',
                'slug' => 'trilogia-de-empanadas-artesanales',
                'descripcion' => 'Empanadas crocantes rellenas de carne desmechada de res y papa criolla con ají casero de la huerta.',
                'precio' => 18000.00,
                'costo' => 6000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-CAR-MOL', 'cant' => 0.100],
                    ['insumo' => 'INS-PAP-CRI', 'cant' => 0.080],
                ],
            ],
            [
                'categoria_id' => $catsMenu['entradas-picadas']->id,
                'codigo' => 'PROD-CEV-CHI',
                'nombre' => 'Ceviche de Camarón Costeño con Patacón',
                'slug' => 'ceviche-de-camaron-costeno',
                'descripcion' => 'Camarones jumbo tiernos en salsa rosada criolla con cebolla morada, cilantro fresco y chips de plátano verde.',
                'precio' => 38000.00,
                'costo' => 14000.00,
                'area_cocina' => 'fria', // Cocina Fría & Entradas
                'recetas' => [
                    ['insumo' => 'INS-CAM-01', 'cant' => 0.150],
                    ['insumo' => 'INS-LIM-TAH', 'cant' => 0.050],
                ],
            ],
            [
                'categoria_id' => $catsMenu['entradas-picadas']->id,
                'codigo' => 'PROD-ENS-CES',
                'nombre' => 'Ensalada César con Pollo a la Parrilla',
                'slug' => 'ensalada-cesar-con-pollo',
                'descripcion' => 'Mix de lechugas frescas, pechuga a la parrilla dorada, queso parmesano en lajas, croutons y aderezo césar.',
                'precio' => 32000.00,
                'costo' => 10500.00,
                'area_cocina' => 'fria', // Cocina Fría
                'recetas' => [
                    ['insumo' => 'INS-LECH-MIX', 'cant' => 0.120],
                    ['insumo' => 'INS-PEC-01', 'cant' => 0.120],
                    ['insumo' => 'INS-QUE-PAR', 'cant' => 0.030],
                ],
            ],

            // Cortes a la Parrilla & Asados
            [
                'categoria_id' => $catsMenu['cortes-parrilla']->id,
                'codigo' => 'PROD-BIF-ANG',
                'nombre' => 'Bife de Chorizo Angus a la Brasa (350g)',
                'slug' => 'bife-de-chorizo-angus',
                'descripcion' => 'Corte jugoso y tierno asado a las brasas con chimichurri casero, papas rústicas y ensalada fresca.',
                'precio' => 62000.00,
                'costo' => 24000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-BIF-01', 'cant' => 0.350],
                    ['insumo' => 'INS-PAP-RUS', 'cant' => 0.180],
                    ['insumo' => 'INS-MAN-01', 'cant' => 0.020],
                ],
            ],
            [
                'categoria_id' => $catsMenu['cortes-parrilla']->id,
                'codigo' => 'PROD-BAB-BEE',
                'nombre' => 'Baby Beef Tierno a la Plancha (300g)',
                'slug' => 'baby-beef-a-la-parrilla',
                'descripcion' => 'Lomo fino tierno con mantequilla de finas hierbas acompañado de puré rústico de papa criolla.',
                'precio' => 58000.00,
                'costo' => 22000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-BIF-01', 'cant' => 0.300],
                    ['insumo' => 'INS-PAP-CRI', 'cant' => 0.180],
                    ['insumo' => 'INS-MAN-01', 'cant' => 0.020],
                ],
            ],
            [
                'categoria_id' => $catsMenu['cortes-parrilla']->id,
                'codigo' => 'PROD-PUN-ANC',
                'nombre' => 'Punta de Anca Tradicional Asada (350g)',
                'slug' => 'punta-de-anca-tradicional',
                'descripcion' => 'Corte jugoso con su borde de grasa dorada, plátano asado con queso y hogao de la casa.',
                'precio' => 54000.00,
                'costo' => 21000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-BIF-01', 'cant' => 0.350],
                    ['insumo' => 'INS-TOM-CHI', 'cant' => 0.060],
                ],
            ],

            // Pollos Dorados & Costillas BBQ
            [
                'categoria_id' => $catsMenu['pollos-costillas']->id,
                'codigo' => 'PROD-COS-BBQ',
                'nombre' => 'Costillas de Cerdo Ahumadas en BBQ (450g)',
                'slug' => 'costillas-de-cerdo-bbq',
                'descripcion' => 'Tiernas costillas cocinadas a baja temperatura, glaseadas en salsa BBQ de la casa con papas a la francesa.',
                'precio' => 49000.00,
                'costo' => 18000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-COS-01', 'cant' => 0.450],
                    ['insumo' => 'INS-PAP-RUS', 'cant' => 0.180],
                ],
            ],
            [
                'categoria_id' => $catsMenu['pollos-costillas']->id,
                'codigo' => 'PROD-POL-CHAM',
                'nombre' => 'Pechuga de Pollo en Crema de Champiñones',
                'slug' => 'pechuga-en-salsa-champinones',
                'descripcion' => 'Pechuga tierna dorada a la plancha bañada en salsa cremosa de champiñones con arroz blanco y ensalada.',
                'precio' => 38000.00,
                'costo' => 13500.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-PEC-01', 'cant' => 0.220],
                    ['insumo' => 'INS-CRE-LEC', 'cant' => 0.080],
                    ['insumo' => 'INS-ARR-01', 'cant' => 0.100],
                ],
            ],
            [
                'categoria_id' => $catsMenu['pollos-costillas']->id,
                'codigo' => 'PROD-ALA-BBQ',
                'nombre' => 'Alitas BBQ o Crispy de la Casa (10 uds)',
                'slug' => 'alitas-bbq-o-crispy',
                'descripcion' => 'Alitas doradas bañadas en salsa BBQ dulce o picante suave acompañadas de salsa ranch y papas criollas.',
                'precio' => 34000.00,
                'costo' => 12000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-ALA-01', 'cant' => 0.350],
                    ['insumo' => 'INS-PAP-CRI', 'cant' => 0.150],
                ],
            ],

            // Pescados & Mariscos de la Casa
            [
                'categoria_id' => $catsMenu['pescados-mariscos-casa']->id,
                'codigo' => 'PROD-ROB-ALM',
                'nombre' => 'Filete de Róbalo en Mantequilla de Ajo & Hierbas',
                'slug' => 'filete-de-robalo-al-ajillo',
                'descripcion' => 'Filete fresco a la plancha sobre puré de papa rústica, vegetales salteados y mantequilla aromática.',
                'precio' => 56000.00,
                'costo' => 22000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-ROB-01', 'cant' => 0.220],
                    ['insumo' => 'INS-MAN-01', 'cant' => 0.030],
                    ['insumo' => 'INS-PAP-RUS', 'cant' => 0.150],
                ],
            ],
            [
                'categoria_id' => $catsMenu['pescados-mariscos-casa']->id,
                'codigo' => 'PROD-CAM-AJO',
                'nombre' => 'Cazuela de Camarones al Ajillo & Vino Blanco',
                'slug' => 'cazuela-de-camarones',
                'descripcion' => 'Camarones jumbo salteados en mantequilla de ajo, vino blanco y perejil fresco con arroz blanco y patacón.',
                'precio' => 52000.00,
                'costo' => 20000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-CAM-01', 'cant' => 0.200],
                    ['insumo' => 'INS-MAN-01', 'cant' => 0.030],
                    ['insumo' => 'INS-ARR-01', 'cant' => 0.100],
                ],
            ],

            // Pastas Artesanales & Lasañas
            [
                'categoria_id' => $catsMenu['pastas-artesanales']->id,
                'codigo' => 'PROD-FET-ALF',
                'nombre' => 'Fettuccine Alfredo con Pollo y Parmesano',
                'slug' => 'fettuccine-alfredo-con-pollo',
                'descripcion' => 'Pasta artesanal al dente con salsa bechamel cremosa, pechuga de pollo grillé y queso parmesano gratinado.',
                'precio' => 39000.00,
                'costo' => 13500.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-PAS-FET', 'cant' => 0.160],
                    ['insumo' => 'INS-PEC-01', 'cant' => 0.100],
                    ['insumo' => 'INS-CRE-LEC', 'cant' => 0.080],
                    ['insumo' => 'INS-QUE-PAR', 'cant' => 0.030],
                ],
            ],
            [
                'categoria_id' => $catsMenu['pastas-artesanales']->id,
                'codigo' => 'PROD-LAS-BOL',
                'nombre' => 'Lasaña Tradicional Boloñesa de la Casa',
                'slug' => 'lasana-tradicional-bolonesa',
                'descripcion' => 'Capas de pasta casera con abundante ragú de carne Angus, bechamel suave y queso mozzarella dorado.',
                'precio' => 36000.00,
                'costo' => 12500.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-CAR-MOL', 'cant' => 0.150],
                    ['insumo' => 'INS-TOM-CHI', 'cant' => 0.080],
                    ['insumo' => 'INS-CRE-LEC', 'cant' => 0.060],
                ],
            ],

            // Hamburguesas Gourmet & Sandwiches
            [
                'categoria_id' => $catsMenu['hamburguesas-sandwiches']->id,
                'codigo' => 'PROD-HAM-REST',
                'nombre' => 'Hamburguesa RestoMaster Angus Especial',
                'slug' => 'hamburguesa-restomaster-angus',
                'descripcion' => '200g de carne Angus seleccionada, tocineta ahumada crocante, queso cheddar, cebolla caramelizada y papas.',
                'precio' => 38000.00,
                'costo' => 14000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-CAR-MOL', 'cant' => 0.200],
                    ['insumo' => 'INS-PAN-BRI', 'cant' => 1.0],
                    ['insumo' => 'INS-QUE-CHE', 'cant' => 0.040],
                    ['insumo' => 'INS-TOC-01', 'cant' => 0.040],
                    ['insumo' => 'INS-PAP-RUS', 'cant' => 0.150],
                ],
            ],
            [
                'categoria_id' => $catsMenu['hamburguesas-sandwiches']->id,
                'codigo' => 'PROD-HAM-POLL',
                'nombre' => 'Hamburguesa Crunchy Chicken BBQ',
                'slug' => 'hamburguesa-crunchy-chicken',
                'descripcion' => 'Pechuga de pollo crocante en panko, queso cheddar derretido, lechuga hidropónica y salsa BBQ especial.',
                'precio' => 34000.00,
                'costo' => 12000.00,
                'area_cocina' => 'caliente',
                'recetas' => [
                    ['insumo' => 'INS-PEC-01', 'cant' => 0.180],
                    ['insumo' => 'INS-PAN-BRI', 'cant' => 1.0],
                    ['insumo' => 'INS-QUE-CHE', 'cant' => 0.030],
                    ['insumo' => 'INS-PAP-RUS', 'cant' => 0.150],
                ],
            ],

            // Coctelería Clásica & de Autor
            [
                'categoria_id' => $catsMenu['cocteleria-barra']->id,
                'codigo' => 'PROD-GINT-COL',
                'nombre' => 'Gin Tonic Botánico Clásico',
                'slug' => 'gin-tonic-botanico-clasico',
                'descripcion' => 'Ginebra Tanqueray London Dry, tónica premium, rodajas de limón Tahití y aroma de romero fresco.',
                'precio' => 36000.00,
                'costo' => 11000.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-GIN-TAN', 'cant' => 0.060],
                    ['insumo' => 'INS-LIM-TAH', 'cant' => 0.030],
                ],
            ],
            [
                'categoria_id' => $catsMenu['cocteleria-barra']->id,
                'codigo' => 'PROD-MOJ-MED',
                'nombre' => 'Mojito Clásico de Ron Añejo',
                'slug' => 'mojito-clasico-ron-anejo',
                'descripcion' => 'Ron Medellín Añejo 8 Años, hierbabuena fresca campesina, zumo de limón Tahití, azúcar de caña y soda.',
                'precio' => 32000.00,
                'costo' => 9500.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-RON-MED', 'cant' => 0.060],
                    ['insumo' => 'INS-LIM-TAH', 'cant' => 0.040],
                ],
            ],
            [
                'categoria_id' => $catsMenu['cocteleria-barra']->id,
                'codigo' => 'PROD-MOS-MULE',
                'nombre' => 'Moscow Mule Clásico de Frutas',
                'slug' => 'moscow-mule-maracuya',
                'descripcion' => 'Vodka Smirnoff, pulpa de maracuyá natural colombiana, ginger beer artesanal y toque de menta fresca.',
                'precio' => 34000.00,
                'costo' => 10000.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-VOD-SMI', 'cant' => 0.060],
                    ['insumo' => 'INS-PUL-FRU', 'cant' => 0.050],
                ],
            ],

            // Bebidas, Jugos Naturales & Cervezas
            [
                'categoria_id' => $catsMenu['bebidas-jugos']->id,
                'codigo' => 'PROD-JUG-NAT',
                'nombre' => 'Jugo Natural en Agua o Leche (350ml)',
                'slug' => 'jugo-natural-lulo-mango-maracuya',
                'descripcion' => 'Preparado al momento con pulpa fresca a elegir: Lulo, Mango, Maracuyá o Fresa.',
                'precio' => 12000.00,
                'costo' => 3800.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-PUL-FRU', 'cant' => 0.120],
                ],
            ],
            [
                'categoria_id' => $catsMenu['bebidas-jugos']->id,
                'codigo' => 'PROD-LIM-COCO',
                'nombre' => 'Limonada de Coco Cremosita (400ml)',
                'slug' => 'limonada-de-coco-cremosita',
                'descripcion' => 'Zumo de limón Tahití recién exprimido con crema de coco natural y hielo frappé refrescante.',
                'precio' => 15000.00,
                'costo' => 4500.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-LIM-TAH', 'cant' => 0.060],
                ],
            ],
            [
                'categoria_id' => $catsMenu['bebidas-jugos']->id,
                'codigo' => 'PROD-CER-BBC',
                'nombre' => 'Cerveza Artesanal BBC Monserrate Roja (330ml)',
                'slug' => 'cerveza-bbc-monserrate-roja',
                'descripcion' => 'Cerveza artesanal tipo ale con maltas tostadas caramelizadas y cuerpo balanceado.',
                'precio' => 14000.00,
                'costo' => 5800.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-CER-BBC', 'cant' => 1.0],
                ],
            ],
            [
                'categoria_id' => $catsMenu['bebidas-jugos']->id,
                'codigo' => 'PROD-CER-CLU',
                'nombre' => 'Cerveza Club Colombia Dorada (330ml)',
                'slug' => 'cerveza-club-colombia-dorada',
                'descripcion' => 'Cerveza premium colombiana tipo pilsen dorada bien fría.',
                'precio' => 10000.00,
                'costo' => 4200.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-CER-CLU', 'cant' => 1.0],
                ],
            ],
            [
                'categoria_id' => $catsMenu['bebidas-jugos']->id,
                'codigo' => 'PROD-GAS-POS',
                'nombre' => 'Gaseosa Postobón Manzana o Colombiana (400ml)',
                'slug' => 'gaseosa-postobon-manzana',
                'descripcion' => 'Gaseosa personal bien helada en botella tradicional.',
                'precio' => 7000.00,
                'costo' => 2600.00,
                'area_cocina' => 'barra',
                'recetas' => [
                    ['insumo' => 'INS-GAS-MAN', 'cant' => 1.0],
                ],
            ],

            // Postres Artesanales de la Casa
            [
                'categoria_id' => $catsMenu['postres-casa']->id,
                'codigo' => 'PROD-VOL-CHO',
                'nombre' => 'Volcán Tibio de Chocolate con Helado',
                'slug' => 'volcan-tibio-de-chocolate',
                'descripcion' => 'Bizcochuelo esponjoso tibio con centro líquido fundente de chocolate y bola de helado de vainilla.',
                'precio' => 22000.00,
                'costo' => 7500.00,
                'area_cocina' => 'postres',
                'recetas' => [],
            ],
            [
                'categoria_id' => $catsMenu['postres-casa']->id,
                'codigo' => 'PROD-POS-TRE',
                'nombre' => 'Torta Tres Leches Tradicional',
                'slug' => 'torta-tres-leches-tradicional',
                'descripcion' => 'Bizcochuelo casero bañado en mezcla cremosa de tres leches con toque de canela y merengue tostado.',
                'precio' => 18000.00,
                'costo' => 6000.00,
                'area_cocina' => 'postres', // Repostería / Postres
                'recetas' => [
                    ['insumo' => 'INS-CRE-LEC', 'cant' => 0.080],
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

            // Recetas / Escandallo
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

        // 9. Mesas en Zonas del Restaurante (Salón, Terraza, Barra, VIP)
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

        // 10. Clientes Colombianos con Fidelización y Direcciones en Medellín
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
                'preferencias' => 'Mesa en salón, Bife de Chorizo término tres cuartos con chimichurri extra',
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
                'alergias' => 'Ninguna',
                'preferencias' => 'Costillitas BBQ bien doradas y Fettuccine Alfredo',
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
                'preferencias' => 'Coctelería Gin Tonic botánico y picada criolla',
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
                'alergias' => null,
                'preferencias' => 'Baby beef tierno, ensaladas frescas y cócteles sin azúcar añadido',
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
                'preferencias' => 'Almuerzos ejecutivos, cazuelas y cerveza bien fría',
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
                'preferencias' => 'Hamburguesa Angus especial con papas rústicas',
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
                'preferencias' => 'Salón VIP para cenas de negocios, bife de chorizo y cerveza Club Colombia',
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
                'preferencias' => 'Pedidos para llevar de hamburguesas y lasañas',
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

                // 2 a 3 productos del restaurante general
                $platosKeys = [
                    'bife-de-chorizo-angus',
                    'costillas-de-cerdo-bbq',
                    'pechuga-en-salsa-champinones',
                    'picada-criolla-restomaster',
                    'trilogia-de-empanadas-artesanales',
                    'fettuccine-alfredo-con-pollo',
                    'hamburguesa-restomaster-angus',
                    'gin-tonic-botanico-clasico',
                    'cerveza-bbc-monserrate-roja',
                    'limonada-de-coco-cremosita',
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
                'subtotal' => 116000.00,
                'descuento' => 0,
                'total' => 127600.00,
                'propina' => 11600.00,
                'porcentaje_propina' => 10.0,
                'created_at' => now()->subMinutes(14),
                'updated_at' => now()->subMinutes(14),
            ]
        );

        if ($pedidoActivo1->wasRecentlyCreated) {
            ItemPedido::create([
                'pedido_id' => $pedidoActivo1->id,
                'producto_id' => $productosMap['bife-de-chorizo-angus']->id,
                'nombre_producto' => 'Bife de Chorizo Angus a la Brasa (350g)',
                'cantidad' => 1,
                'precio_unitario' => 62000.00,
                'subtotal' => 62000.00,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'en_preparacion',
                'notas' => 'Término 3/4, chimichurri servido aparte',
                'iniciado_en' => now()->subMinutes(10),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo1->id,
                'producto_id' => $productosMap['trilogia-de-empanadas-artesanales']->id,
                'nombre_producto' => 'Trilogía de Empanadas Artesanales con Ají (3 uds)',
                'cantidad' => 1,
                'precio_unitario' => 18000.00,
                'subtotal' => 18000.00,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'listo',
                'iniciado_en' => now()->subMinutes(12),
                'listo_en' => now()->subMinutes(2),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo1->id,
                'producto_id' => $productosMap['gin-tonic-botanico-clasico']->id,
                'nombre_producto' => 'Gin Tonic Botánico Clásico',
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
                'subtotal' => 102000.00,
                'descuento' => 0,
                'total' => 112200.00,
                'propina' => 10200.00,
                'porcentaje_propina' => 10.0,
                'created_at' => now()->subMinutes(35),
                'updated_at' => now()->subMinutes(15),
            ]
        );

        if ($pedidoActivo2->wasRecentlyCreated) {
            ItemPedido::create([
                'pedido_id' => $pedidoActivo2->id,
                'producto_id' => $productosMap['costillas-de-cerdo-bbq']->id,
                'nombre_producto' => 'Costillas de Cerdo Ahumadas en BBQ (450g)',
                'cantidad' => 1,
                'precio_unitario' => 49000.00,
                'subtotal' => 49000.00,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'servido',
                'iniciado_en' => now()->subMinutes(30),
                'listo_en' => now()->subMinutes(18),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo2->id,
                'producto_id' => $productosMap['fettuccine-alfredo-con-pollo']->id,
                'nombre_producto' => 'Fettuccine Alfredo con Pollo y Parmesano',
                'cantidad' => 1,
                'precio_unitario' => 39000.00,
                'subtotal' => 39000.00,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'servido',
                'iniciado_en' => now()->subMinutes(30),
                'listo_en' => now()->subMinutes(16),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo2->id,
                'producto_id' => $productosMap['cerveza-bbc-monserrate-roja']->id,
                'nombre_producto' => 'Cerveza Artesanal BBC Monserrate Roja (330ml)',
                'cantidad' => 1,
                'precio_unitario' => 14000.00,
                'subtotal' => 14000.00,
                'area_cocina' => 'barra',
                'estado_cocina' => 'servido',
                'iniciado_en' => now()->subMinutes(32),
                'listo_en' => now()->subMinutes(29),
            ]);
        }

        // Barra B2: Ocupada con Hamburguesa y Coctel
        $pedidoActivo3 = Pedido::firstOrCreate(
            ['codigo' => 'ORD-'.date('Ymd').'-0993'],
            [
                'tipo' => 'mesa',
                'estado' => 'en_preparacion',
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $mesasMap['B2']->id,
                'usuario_id' => $cajero2->id,
                'mesero_id' => $mesero3->id,
                'turno_caja_id' => $turno2->id,
                'nombre_cliente' => 'Mateo Gómez',
                'telefono_cliente' => '3147361092',
                'cliente_id' => $clientesMap['1020456789']->id,
                'subtotal' => 85000.00,
                'descuento' => 0,
                'total' => 93500.00,
                'propina' => 8500.00,
                'porcentaje_propina' => 10.0,
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ]
        );

        if ($pedidoActivo3->wasRecentlyCreated) {
            ItemPedido::create([
                'pedido_id' => $pedidoActivo3->id,
                'producto_id' => $productosMap['hamburguesa-restomaster-angus']->id,
                'nombre_producto' => 'Hamburguesa RestoMaster Angus Especial',
                'cantidad' => 1,
                'precio_unitario' => 38000.00,
                'subtotal' => 38000.00,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'en_preparacion',
                'notas' => 'Carne término medio, tocineta bien crujiente',
                'iniciado_en' => now()->subMinutes(8),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo3->id,
                'producto_id' => $productosMap['mojito-clasico-ron-anejo']->id,
                'nombre_producto' => 'Mojito Clásico de Ron Añejo',
                'cantidad' => 1,
                'precio_unitario' => 32000.00,
                'subtotal' => 32000.00,
                'area_cocina' => 'barra',
                'estado_cocina' => 'listo',
                'iniciado_en' => now()->subMinutes(8),
                'listo_en' => now()->subMinutes(3),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedidoActivo3->id,
                'producto_id' => $productosMap['limonada-de-coco-cremosita']->id,
                'nombre_producto' => 'Limonada de Coco Cremosita (400ml)',
                'cantidad' => 1,
                'precio_unitario' => 15000.00,
                'subtotal' => 15000.00,
                'area_cocina' => 'barra',
                'estado_cocina' => 'servido',
                'iniciado_en' => now()->subMinutes(9),
                'listo_en' => now()->subMinutes(5),
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
                'notas' => 'Almuerzo familiar cumpleaños. Prefieren mesa 3 en salón principal.',
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
                'notas' => 'Cena ejecutiva reservada en Salón VIP-1. Atender con carta de carnes y asados.',
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
                'notas' => 'Almuerzo en barra de coctelería.',
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
                'notas' => 'Cena en terraza exterior.',
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
                'proveedor_nombre' => 'Carnes Frías San Martín Medellín',
                'proveedor_nit' => '890.123.456-1',
                'numero_factura' => 'FAC-CSM-9912',
                'concepto' => 'Lomo fino de res Angus, costillas BBQ y carne molida para hamburguesas',
                'monto_total' => 2650000.00,
                'saldo_pendiente' => 650000.00,
                'fecha_emision' => Carbon::today()->subDays(2),
                'fecha_vencimiento' => Carbon::today()->addDays(12),
                'estado' => 'parcial',
                'user_id' => $usersByEmail['admin@restomaster.com']->id,
            ],
            [
                'proveedor_nombre' => 'Avícola Los Andes de Antioquia',
                'proveedor_nit' => '900.876.543-2',
                'numero_factura' => 'FAC-AVI-4482',
                'concepto' => 'Pechuga fresca fileteada y alitas de pollo seleccionadas',
                'monto_total' => 1850000.00,
                'saldo_pendiente' => 0.00,
                'fecha_emision' => Carbon::today()->subDays(3),
                'fecha_vencimiento' => Carbon::today()->addDays(25),
                'estado' => 'pagado',
                'user_id' => $usersByEmail['admin@restomaster.com']->id,
            ],
            [
                'proveedor_nombre' => 'Bavaria S.A.',
                'proveedor_nit' => '860.005.224-6',
                'numero_factura' => 'FAC-BAV-88231',
                'concepto' => 'Cerveza Club Colombia Dorada y BBC Monserrate Roja x6 cajas',
                'monto_total' => 684000.00,
                'saldo_pendiente' => 684000.00,
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

        $this->command?->info('✓ Carga de catálogo de Restaurante General completada con éxito.');
    }
}
