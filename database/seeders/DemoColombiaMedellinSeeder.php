<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\CategoriaInsumo;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\CrmConfiguracion;
use App\Models\CuentaPorPagar;
use App\Models\DireccionCliente;
use App\Models\Encuesta;
use App\Models\EncuestaEnvio;
use App\Models\EncuestaRespuesta;
use App\Models\FacturaElectronica;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\MovimientoCaja;
use App\Models\NotaCredito;
use App\Models\PagoCxp;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Proveedor;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Zona;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoColombiaMedellinSeeder extends Seeder
{
    /**
     * Seeder Maestro de Demostración para Colombia / Medellín:
     * - 24 colaboradores del restaurante (10 meseros, 4 cocina, 3 cajeros, 1 gerente, 1 admin, 5 repartidores).
     * - 10 proveedores antioqueños con NIT, compras, CxP y pagos.
     * - 20 clientes segmentados (VIP, elegibles, frecuentes, normales).
     * - Catálogo completo: 30 platos, 20 tragos, 10 bebidas colombianas, postres con fotos IA vinculadas.
     * - Insumos con stock real, stock crítico, escandallo, merma y recetas.
     * - Plantillas de encuestas y automatizaciones CRM + 40+ encuestas respondidas.
     * - 30 días de operaciones (ventas, cajas, movimientos, facturas DIAN CUFE/QR, notas crédito).
     * - 14 promociones (7 activas + 7 por activar) con imágenes.
     */
    public function run(): void
    {
        $this->command?->info('Iniciando Seeder Maestro Colombia · Medellín (RestoMaster Gastro-OS)...');

        $rawPassword = config('auth.demo_password') ?: 'password';
        $unifiedPassword = Hash::make($rawPassword);

        // 1. SUCURSAL PRINCIPAL
        $sucursal = Sucursal::firstOrCreate(
            ['nit_ruc' => '901.458.789-3'],
            [
                'nombre' => 'RestoMaster Gourmet & Parrilla · El Poblado',
                'direccion' => 'Carrera 35 # 8A-19, Provenza, El Poblado, Medellín',
                'telefono' => '+57 604 444 8899',
                'activo' => true,
            ]
        );

        // 2. ROLES
        $roles = [
            'admin' => Role::firstOrCreate(['slug' => 'admin'], ['nombre' => 'Administrador', 'descripcion' => 'Acceso total']),
            'gerente' => Role::firstOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerente de Operaciones', 'descripcion' => 'Gestión operativa']),
            'cajero' => Role::firstOrCreate(['slug' => 'cajero'], ['nombre' => 'Cajero', 'descripcion' => 'Control de caja']),
            'mesero' => Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero', 'descripcion' => 'Atención de salón']),
            'cocina' => Role::firstOrCreate(['slug' => 'cocina'], ['nombre' => 'Cocina KDS', 'descripcion' => 'Preparación de comandas']),
            'barra' => Role::firstOrCreate(['slug' => 'barra'], ['nombre' => 'Barra / Bar', 'descripcion' => 'Bebidas y coctelería']),
            'delivery' => Role::firstOrCreate(['slug' => 'delivery'], ['nombre' => 'Repartidor Delivery', 'descripcion' => 'Despacho de pedidos']),
        ];

        // 3. PERSONAL (24 Usuarios)
        $personal = [
            // Admin y Gerente
            ['name' => 'Alejandro Restrepo Gómez', 'email' => 'admin@restomaster.com', 'role_id' => $roles['admin']->id, 'telefono' => '+57 300 458 9201'],
            ['name' => 'Valentina Jaramillo Morales', 'email' => 'gerente@restomaster.com', 'role_id' => $roles['gerente']->id, 'telefono' => '+57 310 829 4411'],

            // 3 Cajeros
            ['name' => 'Sebastián Castaño Rivera', 'email' => 'cajero@restomaster.com', 'role_id' => $roles['cajero']->id, 'telefono' => '+57 314 736 1092'],
            ['name' => 'Mariana Zapata Betancur', 'email' => 'mariana.caja@restomaster.com', 'role_id' => $roles['cajero']->id, 'telefono' => '+57 301 552 8490'],
            ['name' => 'Alejandro Builes Correa', 'email' => 'alejandro.caja@restomaster.com', 'role_id' => $roles['cajero']->id, 'telefono' => '+57 318 443 2190'],

            // 4 Cocina + 1 Barra
            ['name' => 'Sebastián Vélez Puerta', 'email' => 'cocina@restomaster.com', 'role_id' => $roles['cocina']->id, 'telefono' => '+57 302 918 2304'],
            ['name' => 'Diego Marín Hincapié', 'email' => 'diego.cocina@restomaster.com', 'role_id' => $roles['cocina']->id, 'telefono' => '+57 311 654 3210'],
            ['name' => 'Mariana Ossa Restrepo', 'email' => 'mariana.cocina@restomaster.com', 'role_id' => $roles['cocina']->id, 'telefono' => '+57 315 889 0012'],
            ['name' => 'Esteban Cano Tobón', 'email' => 'esteban.cocina@restomaster.com', 'role_id' => $roles['cocina']->id, 'telefono' => '+57 312 998 7765'],
            ['name' => 'Andrés Felipe Londoño', 'email' => 'barra@restomaster.com', 'role_id' => $roles['barra']->id, 'telefono' => '+57 317 223 3445'],

            // 10 Meseros
            ['name' => 'Juan David Montoya Ortiz', 'email' => 'mesero@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 312 394 8571'],
            ['name' => 'Carlos Andrés Restrepo', 'email' => 'carlos.restrepo@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 310 456 7890'],
            ['name' => 'Valentina Morales Jaramillo', 'email' => 'valentina.morales@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 312 876 5432'],
            ['name' => 'Mateo Echeverry Londoño', 'email' => 'mateo.echeverry@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 315 234 5678'],
            ['name' => 'Camila Henao Saldarriaga', 'email' => 'camila.henao@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 318 765 4321'],
            ['name' => 'Daniel Montoya Agudelo', 'email' => 'daniel.montoya@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 300 123 9876'],
            ['name' => 'Manuela Arango Correa', 'email' => 'manuela.arango@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 314 567 8901'],
            ['name' => 'Santiago Quintero Peláez', 'email' => 'santiago.quintero@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 316 890 1234'],
            ['name' => 'Isabella Osorio Villa', 'email' => 'isabella.osorio@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 319 012 3456'],
            ['name' => 'Sofía Cardona Betancur', 'email' => 'sofia.cardona@restomaster.com', 'role_id' => $roles['mesero']->id, 'telefono' => '+57 311 345 6789'],

            // 5 Repartidores Delivery
            ['name' => 'Brayan Giraldo Duque', 'email' => 'delivery@restomaster.com', 'role_id' => $roles['delivery']->id, 'telefono' => '+57 320 678 9012'],
            ['name' => 'Kevin Muñoz Botero', 'email' => 'kevin.delivery@restomaster.com', 'role_id' => $roles['delivery']->id, 'telefono' => '+57 301 234 5678'],
            ['name' => 'Camilo Bedoya Rúa', 'email' => 'camilo.delivery@restomaster.com', 'role_id' => $roles['delivery']->id, 'telefono' => '+57 318 901 2345'],
            ['name' => 'Cristian Vargas Serna', 'email' => 'cristian.delivery@restomaster.com', 'role_id' => $roles['delivery']->id, 'telefono' => '+57 315 456 7890'],
            ['name' => 'Jeison Patiño Blandón', 'email' => 'jeison.delivery@restomaster.com', 'role_id' => $roles['delivery']->id, 'telefono' => '+57 312 789 0123'],
        ];

        $usersMap = [];
        foreach ($personal as $p) {
            $user = User::updateOrCreate(
                ['email' => $p['email']],
                array_merge($p, [
                    'password' => $unifiedPassword,
                    'sucursal_id' => $sucursal->id,
                    'activo' => true,
                    'email_verified_at' => now(),
                ])
            );
            $usersMap[$p['email']] = $user;
        }

        // 4. ZONAS Y MESAS
        $zonasData = [
            ['nombre' => 'Salón Principal', 'slug' => 'salon', 'color' => 'terracota', 'icono' => 'mesa', 'orden' => 1, 'mesas_count' => 6],
            ['nombre' => 'Terraza Provenza', 'slug' => 'terraza', 'color' => 'salvia', 'icono' => 'terraza', 'orden' => 2, 'mesas_count' => 5],
            ['nombre' => 'Barra VIP & Mixología', 'slug' => 'barra', 'color' => 'lavanda', 'icono' => 'barra', 'orden' => 3, 'mesas_count' => 4],
        ];

        $zonasMap = [];
        $mesas = [];
        $mesaIndex = 1;
        foreach ($zonasData as $z) {
            $zona = Zona::updateOrCreate(
                ['sucursal_id' => $sucursal->id, 'slug' => $z['slug']],
                ['nombre' => $z['nombre'], 'color' => $z['color'], 'icono' => $z['icono'], 'orden' => $z['orden'], 'activa' => true]
            );
            $zonasMap[$z['slug']] = $zona;

            for ($m = 1; $m <= $z['mesas_count']; $m++) {
                $mesas[] = Mesa::firstOrCreate(
                    ['sucursal_id' => $sucursal->id, 'numero' => (string) $mesaIndex],
                    [
                        'zona' => $z['slug'],
                        'capacidad' => in_array($m, [1, 2], true) ? 4 : (in_array($m, [3, 4], true) ? 2 : 6),
                        'estado' => 'disponible',
                    ]
                );
                $mesaIndex++;
            }
        }

        // 5. CAJAS
        $cajaSalón = Caja::firstOrCreate(
            ['codigo' => 'CAJ-01'],
            ['sucursal_id' => $sucursal->id, 'nombre' => 'Caja Principal Salón', 'activa' => true]
        );
        $cajaBarra = Caja::firstOrCreate(
            ['codigo' => 'CAJ-02'],
            ['sucursal_id' => $sucursal->id, 'nombre' => 'Caja Barra & Terraza', 'activa' => true]
        );

        // 6. 10 PROVEEDORES DE ANTIOQUIA
        $proveedoresData = [
            'carnes' => ['nombre' => 'Carnes Frías San Martín Medellín S.A.S.', 'nit' => '890.123.456-1', 'telefono' => '+57 604 444 1122', 'email' => 'ventas@carnesanmartin.com.co', 'contacto' => 'Gustavo Adolfo Pérez', 'direccion' => 'Calle 29 # 43A-20, Medellín'],
            'avicola' => ['nombre' => 'Avícola Los Andes de Antioquia S.A.S.', 'nit' => '900.876.543-2', 'telefono' => '+57 604 312 8899', 'email' => 'pedidos@avicolalosandes.com', 'contacto' => 'Clara Inés Restrepo', 'direccion' => 'Autopista Sur Km 8, Itagüí'],
            'pescados' => ['nombre' => 'Pescados y Mariscos del Pacífico S.A.S.', 'nit' => '901.234.567-3', 'telefono' => '+57 604 260 5544', 'email' => 'comercial@mariscospacifico.com', 'contacto' => 'Jairo de Jesús Correa', 'direccion' => 'Carrera 52 # 14-30, Medellín'],
            'fruver' => ['nombre' => 'Agropecuaria El Trébol & Central Mayorista', 'nit' => '800.987.654-4', 'telefono' => '+57 604 372 1000', 'email' => 'ventas@eltrebolagro.co', 'contacto' => 'Héctor Fabio Gómez', 'direccion' => 'Bloque 12 Local 24 Central Mayorista, Itagüí'],
            'lacteos' => ['nombre' => 'Lácteos y Derivados del Valle de Aburrá', 'nit' => '900.345.678-5', 'telefono' => '+57 604 448 3322', 'email' => 'pedidos@lacteosaburra.com', 'contacto' => 'Marcela Zapata Uribe', 'direccion' => 'Calle 10 # 42-15, Envigado'],
            'licores' => ['nombre' => 'Distribuidora Mayorista de Licores La 70 Medellín', 'nit' => '901.456.789-6', 'telefono' => '+57 604 411 9900', 'email' => 'licoresla70@distribuidora.co', 'contacto' => 'Felipe Posada Londoño', 'direccion' => 'Circular 4 # 70-12, Medellín'],
            'bebidas' => ['nombre' => 'Bebidas & Gaseosas de Colombia / Postobón & Bavaria', 'nit' => '890.900.123-7', 'telefono' => '+57 604 510 8000', 'email' => 'atencion@bebidascolombia.com', 'contacto' => 'Guillermo Ochoa Gil', 'direccion' => 'Carrera 48 # 26-85, Medellín'],
            'panaderia' => ['nombre' => 'Horno Francés & Brioche Artesanal Poblado', 'nit' => '901.789.012-8', 'telefono' => '+57 604 311 4455', 'email' => 'pedidos@hornofrances.co', 'contacto' => 'Camille Dubois Restrepo', 'direccion' => 'Calle 8 # 36-22, El Poblado, Medellín'],
            'cafe' => ['nombre' => 'Café Pergamino & Cordillera Central Jericó', 'nit' => '900.678.901-9', 'telefono' => '+57 604 268 7788', 'email' => 'origen@pergaminocoffee.com', 'contacto' => 'Pedro Echavarría Botero', 'direccion' => 'Vereda Las Brisas, Jericó, Antioquia'],
            'empaques' => ['nombre' => 'EcoEmpaques Biodegradables de Medellín', 'nit' => '901.890.123-0', 'telefono' => '+57 604 444 6677', 'email' => 'ventas@ecoempaquesmde.com', 'contacto' => 'Natalia Echeverry Mejía', 'direccion' => 'Calle 30A # 65-40, Belén, Medellín'],
        ];

        $proveedoresMap = [];
        foreach ($proveedoresData as $key => $prv) {
            $proveedoresMap[$key] = Proveedor::updateOrCreate(
                ['nit' => $prv['nit']],
                array_merge($prv, ['dias_credito' => 30, 'activo' => true])
            );
        }

        // 7. INSUMOS Y CATEGORÍAS DE INVENTARIO
        $catInsumoCarnes = CategoriaInsumo::firstOrCreate(['slug' => 'carnes-pescados'], ['nombre' => 'Carnes, Aves y Pescados']);
        $catInsumoFruver = CategoriaInsumo::firstOrCreate(['slug' => 'fruver-granos'], ['nombre' => 'Fruver, Granos y Panadería']);
        $catInsumoLacteos = CategoriaInsumo::firstOrCreate(['slug' => 'lacteos-quesos'], ['nombre' => 'Lácteos, Quesos y Salsas']);
        $catInsumoBebidas = CategoriaInsumo::firstOrCreate(['slug' => 'bebidas-licores'], ['nombre' => 'Licores, Bebidas y Café']);
        $catInsumoEmpaques = CategoriaInsumo::firstOrCreate(['slug' => 'empaques-desechables'], ['nombre' => 'Empaques y Suministros']);

        $insumosData = [
            ['codigo' => 'INS-BIF-01', 'nombre' => 'Corte Bife de Chorizo Angus Madurado', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoCarnes->id, 'categoria' => 'Carnes', 'stock_actual' => 28.5, 'stock_minimo' => 8.0, 'costo_unitario' => 38000.00, 'proveedor_key' => 'carnes'],
            ['codigo' => 'INS-RIB-01', 'nombre' => 'Ojo de Bife / Ribeye Prime', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoCarnes->id, 'categoria' => 'Carnes', 'stock_actual' => 22.0, 'stock_minimo' => 6.0, 'costo_unitario' => 45000.00, 'proveedor_key' => 'carnes'],
            ['codigo' => 'INS-LOM-01', 'nombre' => 'Lomo Fino de Res Tierno', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoCarnes->id, 'categoria' => 'Carnes', 'stock_actual' => 3.2, 'stock_minimo' => 8.0, 'costo_unitario' => 42000.00, 'proveedor_key' => 'carnes'], // Stock bajo intencional
            ['codigo' => 'INS-COS-01', 'nombre' => 'Costillar de Cerdo Seleccionado', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoCarnes->id, 'categoria' => 'Carnes', 'stock_actual' => 35.0, 'stock_minimo' => 10.0, 'costo_unitario' => 26000.00, 'proveedor_key' => 'carnes'],
            ['codigo' => 'INS-PEC-01', 'nombre' => 'Pechuga de Pollo Fresca Fileteada', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoCarnes->id, 'categoria' => 'Aves', 'stock_actual' => 30.0, 'stock_minimo' => 10.0, 'costo_unitario' => 22000.00, 'proveedor_key' => 'avicola'],
            ['codigo' => 'INS-ALA-01', 'nombre' => 'Alitas de Pollo Frescas', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoCarnes->id, 'categoria' => 'Aves', 'stock_actual' => 25.0, 'stock_minimo' => 8.0, 'costo_unitario' => 18000.00, 'proveedor_key' => 'avicola'],
            ['codigo' => 'INS-ROB-01', 'nombre' => 'Filete de Róbalo / Corvina del Pacífico', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoCarnes->id, 'categoria' => 'Pescados', 'stock_actual' => 18.0, 'stock_minimo' => 5.0, 'costo_unitario' => 42000.00, 'proveedor_key' => 'pescados'],
            ['codigo' => 'INS-CAM-01', 'nombre' => 'Camarones Jumbo U15 Limpios', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoCarnes->id, 'categoria' => 'Mariscos', 'stock_actual' => 24.0, 'stock_minimo' => 6.0, 'costo_unitario' => 54000.00, 'proveedor_key' => 'pescados'],
            ['codigo' => 'INS-PAP-CRI', 'nombre' => 'Papa Criolla Limpia Selección', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoFruver->id, 'categoria' => 'Fruver', 'stock_actual' => 65.0, 'stock_minimo' => 15.0, 'costo_unitario' => 5500.00, 'proveedor_key' => 'fruver'],
            ['codigo' => 'INS-PAP-RUS', 'nombre' => 'Papa Rústica / Francesa Selección', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoFruver->id, 'categoria' => 'Fruver', 'stock_actual' => 75.0, 'stock_minimo' => 20.0, 'costo_unitario' => 6200.00, 'proveedor_key' => 'fruver'],
            ['codigo' => 'INS-AGU-HAS', 'nombre' => 'Aguacate Hass Calidad Extra', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoFruver->id, 'categoria' => 'Fruver', 'stock_actual' => 40.0, 'stock_minimo' => 10.0, 'costo_unitario' => 8500.00, 'proveedor_key' => 'fruver'],
            ['codigo' => 'INS-LIM-TAH', 'nombre' => 'Limón Tahití Jugoso Fresco', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoFruver->id, 'categoria' => 'Fruver', 'stock_actual' => 30.0, 'stock_minimo' => 8.0, 'costo_unitario' => 4200.00, 'proveedor_key' => 'fruver'],
            ['codigo' => 'INS-PAN-BRI', 'nombre' => 'Pan Brioche Mantequilla Artesanal', 'unidad_medida' => 'unidad', 'categoria_id' => $catInsumoFruver->id, 'categoria' => 'Panadería', 'stock_actual' => 120.0, 'stock_minimo' => 30.0, 'costo_unitario' => 2500.00, 'proveedor_key' => 'panaderia'],
            ['codigo' => 'INS-QUE-PAR', 'nombre' => 'Queso Parmesano Reggiano Rallado', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoLacteos->id, 'categoria' => 'Lácteos', 'stock_actual' => 15.0, 'stock_minimo' => 4.0, 'costo_unitario' => 48000.00, 'proveedor_key' => 'lacteos'],
            ['codigo' => 'INS-CRE-LEC', 'nombre' => 'Crema de Leche Fresca 35% Grasa', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoLacteos->id, 'categoria' => 'Lácteos', 'stock_actual' => 2.5, 'stock_minimo' => 8.0, 'costo_unitario' => 16000.00, 'proveedor_key' => 'lacteos'], // Stock bajo intencional
            ['codigo' => 'INS-GIN-BOT', 'nombre' => 'Ginebra Botánica de Autor', 'unidad_medida' => 'botella', 'categoria_id' => $catInsumoBebidas->id, 'categoria' => 'Licores', 'stock_actual' => 18.0, 'stock_minimo' => 5.0, 'costo_unitario' => 85000.00, 'proveedor_key' => 'licores'],
            ['codigo' => 'INS-RON-MED', 'nombre' => 'Ron Medellín Extra Añejo 8 Años', 'unidad_medida' => 'botella', 'categoria_id' => $catInsumoBebidas->id, 'categoria' => 'Licores', 'stock_actual' => 24.0, 'stock_minimo' => 6.0, 'costo_unitario' => 68000.00, 'proveedor_key' => 'licores'],
            ['codigo' => 'INS-AGU-AZU', 'nombre' => 'Aguardiente Antioqueño Sin Azúcar', 'unidad_medida' => 'botella', 'categoria_id' => $catInsumoBebidas->id, 'categoria' => 'Licores', 'stock_actual' => 30.0, 'stock_minimo' => 8.0, 'costo_unitario' => 42000.00, 'proveedor_key' => 'licores'],
            ['codigo' => 'INS-CAF-ESP', 'nombre' => 'Café Especial de Origen Jericó (Grano)', 'unidad_medida' => 'kg', 'categoria_id' => $catInsumoBebidas->id, 'categoria' => 'Café', 'stock_actual' => 20.0, 'stock_minimo' => 5.0, 'costo_unitario' => 48000.00, 'proveedor_key' => 'cafe'],
            ['codigo' => 'INS-GAS-COL', 'nombre' => 'Gaseosa Colombiana 330ml Vidrio', 'unidad_medida' => 'unidad', 'categoria_id' => $catInsumoBebidas->id, 'categoria' => 'Bebidas', 'stock_actual' => 96.0, 'stock_minimo' => 24.0, 'costo_unitario' => 2800.00, 'proveedor_key' => 'bebidas'],
            ['codigo' => 'INS-GAS-POS', 'nombre' => 'Gaseosa Postobón Manzana 330ml', 'unidad_medida' => 'unidad', 'categoria_id' => $catInsumoBebidas->id, 'categoria' => 'Bebidas', 'stock_actual' => 96.0, 'stock_minimo' => 24.0, 'costo_unitario' => 2800.00, 'proveedor_key' => 'bebidas'],
            ['codigo' => 'INS-CER-CLU', 'nombre' => 'Cerveza Club Colombia Dorada 330ml', 'unidad_medida' => 'unidad', 'categoria_id' => $catInsumoBebidas->id, 'categoria' => 'Cervezas', 'stock_actual' => 120.0, 'stock_minimo' => 36.0, 'costo_unitario' => 4200.00, 'proveedor_key' => 'bebidas'],
            ['codigo' => 'INS-EMP-CAJ', 'nombre' => 'Cajas Térmicas Delivery Caña de Azúcar', 'unidad_medida' => 'unidad', 'categoria_id' => $catInsumoEmpaques->id, 'categoria' => 'Empaques', 'stock_actual' => 350.0, 'stock_minimo' => 80.0, 'costo_unitario' => 1200.00, 'proveedor_key' => 'empaques'],
        ];

        $insumosMap = [];
        foreach ($insumosData as $idat) {
            $prvKey = $idat['proveedor_key'] ?? null;
            $prv = $prvKey ? ($proveedoresMap[$prvKey] ?? null) : null;
            unset($idat['proveedor_key']);

            $insumosMap[$idat['codigo']] = Insumo::updateOrCreate(
                ['codigo' => $idat['codigo']],
                array_merge($idat, [
                    'proveedor_id' => $prv?->id,
                    'proveedor_nombre' => $prv?->nombre,
                    'proveedor_nit' => $prv?->nit,
                    'proveedor_telefono' => $prv?->telefono,
                    'precio_referencia_mercado' => $idat['costo_unitario'] * 1.05,
                    'activo' => true,
                ])
            );
        }

        $insumosPorProveedor = [];
        foreach ($insumosMap as $ins) {
            if ($ins->proveedor_id) {
                $insumosPorProveedor[$ins->proveedor_id][] = $ins;
            }
        }

        // 8. CATEGORÍAS DEL MENÚ / POS
        $catMenuData = [
            ['nombre' => 'Entradas & Picadas', 'slug' => 'entradas-picadas', 'color' => '#f97316', 'icono' => '🥗', 'orden' => 1],
            ['nombre' => 'Cortes a la Parrilla', 'slug' => 'cortes-parrilla', 'color' => '#dc2626', 'icono' => '🥩', 'orden' => 2],
            ['nombre' => 'Pollos & Costillas BBQ', 'slug' => 'pollos-costillas', 'color' => '#ea580c', 'icono' => '🍗', 'orden' => 3],
            ['nombre' => 'Pescados & Mariscos', 'slug' => 'pescados-mariscos-casa', 'color' => '#0284c7', 'icono' => '🐟', 'orden' => 4],
            ['nombre' => 'Pastas & Risottos', 'slug' => 'pastas-artesanales', 'color' => '#10b981', 'icono' => '🍝', 'orden' => 5],
            ['nombre' => 'Hamburguesas Gourmet', 'slug' => 'hamburguesas-sandwiches', 'color' => '#8b5cf6', 'icono' => '🍔', 'orden' => 6],
            ['nombre' => 'Coctelería & Bar', 'slug' => 'cocteleria-barra', 'color' => '#ec4899', 'icono' => '🍸', 'orden' => 7],
            ['nombre' => 'Bebidas Colombianas', 'slug' => 'bebidas-jugos', 'color' => '#06b6d4', 'icono' => '🥤', 'orden' => 8],
            ['nombre' => 'Postres de Autor', 'slug' => 'postres-casa', 'color' => '#d97706', 'icono' => '🍰', 'orden' => 9],
        ];

        $catsMenu = [];
        foreach ($catMenuData as $cm) {
            $catsMenu[$cm['slug']] = Categoria::updateOrCreate(
                ['slug' => $cm['slug']],
                array_merge($cm, ['activo' => true])
            );
        }

        // 9. CATÁLOGO GASTRONÓMICO COMPLETO (30 PLATOS + 20 TRAGOS + 10 BEBIDAS COLOMBIANAS + POSTRES)
        $catalogo = [
            // --- 30 PLATOS (Entradas, Parrilla, Pollos, Pescados, Pastas, Burgers) ---
            ['slug' => 'picada-criolla-restomaster', 'nombre' => 'Picada Criolla RestoMaster (2-3 personas)', 'cat' => 'entradas-picadas', 'precio' => 54000.00, 'costo' => 19500.00, 'area' => 'caliente', 'desc' => 'Chicharrón carnudo crocante, costillitas BBQ, papa criolla dorada, patacones de plátano verde y ají casero.'],
            ['slug' => 'trilogia-de-empanadas-artesanales', 'nombre' => 'Trilogía de Empanadas Artesanales con Ají (3 uds)', 'cat' => 'entradas-picadas', 'precio' => 19000.00, 'costo' => 6500.00, 'area' => 'caliente', 'desc' => 'Empanaditas de maíz crocante rellenas de carne de punta de anca y papa criolla con ají casero.'],
            ['slug' => 'empanaditas-de-punta-de-anca-4-pzs', 'nombre' => 'Empanadas de Punta de Anca Ahumada (4 uds)', 'cat' => 'entradas-picadas', 'precio' => 24000.00, 'costo' => 8200.00, 'area' => 'caliente', 'desc' => 'Rellenas de punta de anca cocida a baja temperatura con hogao criollo antioqueño.'],
            ['slug' => 'ceviche-de-camaron-costeno', 'nombre' => 'Ceviche de Camarón Costeño con Patacón', 'cat' => 'entradas-picadas', 'precio' => 38000.00, 'costo' => 14000.00, 'area' => 'fria', 'desc' => 'Camarones jumbo tiernos en salsa rosada criolla con cebolla morada, cilantro y chips de plátano verde.'],
            ['slug' => 'ceviche-mixto-de-la-casa', 'nombre' => 'Ceviche Mixto Nikkei del Pacífico', 'cat' => 'entradas-picadas', 'precio' => 44000.00, 'costo' => 16000.00, 'area' => 'fria', 'desc' => 'Róbalo fresco, camarones y calamares con leche de tigre al maracuyá y canchita tostada.'],
            ['slug' => 'carpaccio-de-res-trufado', 'nombre' => 'Carpaccio de Lomo de Res Trufado', 'cat' => 'entradas-picadas', 'precio' => 42000.00, 'costo' => 15000.00, 'area' => 'fria', 'desc' => 'Finas láminas de lomo fino Angus, aceite de trufa blanca, alcaparras baby y lajas de parmesano.'],
            ['slug' => 'bruschettas-rusticas-3-pzs', 'nombre' => 'Bruschettas Rústicas de Jamón Serrano (3 uds)', 'cat' => 'entradas-picadas', 'precio' => 26000.00, 'costo' => 9000.00, 'area' => 'fria', 'desc' => 'Pan campesino tostado, confitura de tomates cherry, jamón serrano y reducción balsámica.'],
            ['slug' => 'alitas-bbq-o-crispy', 'nombre' => 'Alitas BBQ o Crispy de la Casa (10 uds)', 'cat' => 'entradas-picadas', 'precio' => 35000.00, 'costo' => 12500.00, 'area' => 'caliente', 'desc' => 'Alitas bañadas en salsa BBQ artesanal o miel mostaza con bastones de apio y aderezo ranch.'],
            ['slug' => 'ensalada-cesar-con-pollo', 'nombre' => 'Ensalada César con Pollo a la Parrilla', 'cat' => 'entradas-picadas', 'precio' => 32000.00, 'costo' => 10500.00, 'area' => 'fria', 'desc' => 'Mix de lechugas frescas, pechuga a la parrilla, croutons al ajillo, queso parmesano y aderezo césar.'],

            ['slug' => 'bife-de-chorizo-angus', 'nombre' => 'Bife de Chorizo Angus a la Brasa (350g)', 'cat' => 'cortes-parrilla', 'precio' => 64000.00, 'costo' => 24500.00, 'area' => 'caliente', 'desc' => 'Corte jugoso madurado 28 días, asado al carbón silvestre con chimichurri rústico y papas francesas.'],
            ['slug' => 'ojo-de-bife-ribeye-400g', 'nombre' => 'Ojo de Bife / Ribeye Angus Prime (400g)', 'cat' => 'cortes-parrilla', 'precio' => 74000.00, 'costo' => 29000.00, 'area' => 'caliente', 'desc' => 'Máxima terneza y marmoleo al fuego vivo, servido con puré de papa criolla y espárragos salteados.'],
            ['slug' => 'baby-beef-a-la-parrilla', 'nombre' => 'Baby Beef Tierno a la Plancha (300g)', 'cat' => 'cortes-parrilla', 'precio' => 59000.00, 'costo' => 22000.00, 'area' => 'caliente', 'desc' => 'Lomo fino de res extra tierno con mantequilla aromatizada de finas hierbas y ensalada fresca.'],
            ['slug' => 'punta-de-anca-tradicional', 'nombre' => 'Punta de Anca Tradicional Asada (350g)', 'cat' => 'cortes-parrilla', 'precio' => 55000.00, 'costo' => 21000.00, 'area' => 'caliente', 'desc' => 'Corte jugoso con su borde dorado característico, plátano maduro al gratín y hogao casero.'],

            ['slug' => 'costillas-de-cerdo-bbq', 'nombre' => 'Costillas de Cerdo Ahumadas en BBQ (450g)', 'cat' => 'pollos-costillas', 'precio' => 49000.00, 'costo' => 18000.00, 'area' => 'caliente', 'desc' => 'Costillitas tiernas braseadas a fuego lento, glaseadas en salsa BBQ de la casa y papas rústicas.'],
            ['slug' => 'costillas-bbq-ahumadas-500g', 'nombre' => 'Costillas BBQ St. Louis Ahumadas (500g)', 'cat' => 'pollos-costillas', 'precio' => 58000.00, 'costo' => 22000.00, 'area' => 'caliente', 'desc' => 'Ahumadas 6 horas en leña de roble, carne suave que se desprende del hueso con ensalada de col.'],
            ['slug' => 'pechuga-en-salsa-champinones', 'nombre' => 'Pechuga de Pollo en Crema de Champiñones', 'cat' => 'pollos-costillas', 'precio' => 38000.00, 'costo' => 13500.00, 'area' => 'caliente', 'desc' => 'Pechuga tierna bañada en salsa cremosa de champiñones parís frescos, arroz blanco y papas.'],
            ['slug' => 'pechuga-gratinada-al-parmesano', 'nombre' => 'Pechuga Gratinada al Parmesano & Tomate', 'cat' => 'pollos-costillas', 'precio' => 41000.00, 'costo' => 14500.00, 'area' => 'caliente', 'desc' => 'Filete de pechuga dorada cubierta con pomodoro casero y queso mozzarella fundido al parmesano.'],

            ['slug' => 'filete-de-robalo-al-ajillo', 'nombre' => 'Filete de Róbalo en Mantequilla de Ajo & Hierbas', 'cat' => 'pescados-mariscos-casa', 'precio' => 57000.00, 'costo' => 22500.00, 'area' => 'caliente', 'desc' => 'Filete de róbalo sellado a la perfección con mantequilla de ajo confitado, puré y vegetales al vapor.'],
            ['slug' => 'cazuela-de-camarones', 'nombre' => 'Cazuela Cremosa de Camarones', 'cat' => 'pescados-mariscos-casa', 'precio' => 49000.00, 'costo' => 19000.00, 'area' => 'caliente', 'desc' => 'Camarones jumbo en fondo cremoso aromatizado con vino blanco, patacones y arroz con coco.'],
            ['slug' => 'cazuela-de-mariscos-del-pacifico', 'nombre' => 'Cazuela de Mariscos Tradicional del Pacífico', 'cat' => 'pescados-mariscos-casa', 'precio' => 62000.00, 'costo' => 24000.00, 'area' => 'caliente', 'desc' => 'Receta insignia con leche de coco natural, camarones, calamares, mejillones y róbalo fresco.'],

            ['slug' => 'fettuccine-alfredo-con-pollo-champinones', 'nombre' => 'Fettuccine Alfredo con Pollo & Champiñones', 'cat' => 'pastas-artesanales', 'precio' => 39000.00, 'costo' => 13000.00, 'area' => 'caliente', 'desc' => 'Pasta fresca artesanal al huevo, salsa alfredo cremosa de parmesano, pollo dorado y champiñones.'],
            ['slug' => 'fettuccine-alfredo-con-pollo', 'nombre' => 'Fettuccine Alfredo Clásico con Pollo', 'cat' => 'pastas-artesanales', 'precio' => 36000.00, 'costo' => 12000.00, 'area' => 'caliente', 'desc' => 'Pasta fresca salteada en salsa mantecada de queso parmesano y pechuga grillé.'],
            ['slug' => 'lasana-tradicional-bolonesa', 'nombre' => 'Lasaña Tradicional a la Boloñesa Gratinada', 'cat' => 'pastas-artesanales', 'precio' => 38000.00, 'costo' => 13500.00, 'area' => 'caliente', 'desc' => 'Capas de pasta artesanal con ragú clásico de carne angus, bechamel cremosa y queso dorado.'],
            ['slug' => 'raviolis-de-espinaca-ricotta', 'nombre' => 'Raviolis de Ricotta & Espinaca en Pomodoro', 'cat' => 'pastas-artesanales', 'precio' => 37000.00, 'costo' => 12800.00, 'area' => 'caliente', 'desc' => 'Raviolis caseros rellenos de queso ricotta artesanal sobre salsa de tomates asados y albahaca.'],
            ['slug' => 'risotto-de-setas-silvestres-trufa', 'nombre' => 'Risotto de Setas Silvestres al Aroma de Trufa', 'cat' => 'pastas-artesanales', 'precio' => 46000.00, 'costo' => 16500.00, 'area' => 'caliente', 'desc' => 'Arroz carnaroli cremoso con variedad de hongos silvestres, vino blanco y mantequilla trufada.'],

            ['slug' => 'hamburguesa-restomaster-angus', 'nombre' => 'Hamburguesa RestoMaster Angus (200g)', 'cat' => 'hamburguesas-sandwiches', 'precio' => 39000.00, 'costo' => 14000.00, 'area' => 'caliente', 'desc' => 'Carne 100% Angus madurada, queso gouda fundido, tocineta ahumada, cebolla caramelizada en pan brioche.'],
            ['slug' => 'hamburguesa-doble-trufa-hongos', 'nombre' => 'Hamburguesa Doble Angus Trufa & Hongos', 'cat' => 'hamburguesas-sandwiches', 'precio' => 47000.00, 'costo' => 17500.00, 'area' => 'caliente', 'desc' => 'Doble carne angus, queso suizo emmental, hongos salteados con alioli de trufa negra en pan brioche.'],
            ['slug' => 'hamburguesa-crunchy-chicken', 'nombre' => 'Hamburguesa Crunchy Chicken Apanada', 'cat' => 'hamburguesas-sandwiches', 'precio' => 34000.00, 'costo' => 11800.00, 'area' => 'caliente', 'desc' => 'Pechuga marinada y apanada extra crocante con ensaladilla tártara casera y pepinillos agridulces.'],
            ['slug' => 'sandwich-de-pulled-pork-braseado', 'nombre' => 'Sandwich de Pulled Pork Braseado', 'cat' => 'hamburguesas-sandwiches', 'precio' => 36000.00, 'costo' => 12500.00, 'area' => 'caliente', 'desc' => 'Bondiola de cerdo desmechada cocida 8 horas en salsa BBQ dulce con ensalada coleslaw en pan brioche.'],
            ['slug' => 'restomaster-burger-master', 'nombre' => 'RestoMaster Burger Edición Master 2026', 'cat' => 'hamburguesas-sandwiches', 'precio' => 42000.00, 'costo' => 15000.00, 'area' => 'caliente', 'desc' => 'Nuestra burger estrella con reducción de vino tinto y moras silvestres, chicharrón crocante y queso costeño asado.'],

            // Postres
            ['slug' => 'volcan-tibio-de-chocolate', 'nombre' => 'Volcán Tibio de Chocolate con Helado', 'cat' => 'postres-casa', 'precio' => 22000.00, 'costo' => 7500.00, 'area' => 'fria', 'desc' => 'Bizcocho tibio de chocolate amargo 70% con centro líquido fluyente y helado de vainilla.'],
            ['slug' => 'volcan-de-chocolate-fondant', 'nombre' => 'Fondant de Chocolate Belga & Frutos Rojos', 'cat' => 'postres-casa', 'precio' => 24000.00, 'costo' => 8200.00, 'area' => 'fria', 'desc' => 'Chocolate belga premium con salsa de moras y arándanos silvestres de Guarne.'],
            ['slug' => 'cheesecake-clasico-de-frutos-rojos', 'nombre' => 'Cheesecake Horneado de Frutos Rojos', 'cat' => 'postres-casa', 'precio' => 21000.00, 'costo' => 7000.00, 'area' => 'fria', 'desc' => 'Base crocante de galleta con crema horneada de queso y coulis artesanal de frutos del bosque.'],
            ['slug' => 'torta-tres-leches-tradicional', 'nombre' => 'Torta Tres Leches Tradicional Colombiana', 'cat' => 'postres-casa', 'precio' => 19000.00, 'costo' => 6000.00, 'area' => 'fria', 'desc' => 'Bizcochuelo esponjoso embebido en mezcla de tres leches con canela y merengue tostado.'],
            ['slug' => 'tiramisu-tradicional-al-mascarpone', 'nombre' => 'Tiramisú Tradicional con Café de Origen', 'cat' => 'postres-casa', 'precio' => 23000.00, 'costo' => 7800.00, 'area' => 'fria', 'desc' => 'Capas de soletillas humedecidas en espresso de Jericó, licor de café y crema de queso mascarpone.'],

            // --- 20 TRAGOS & MIXOLOGÍA ---
            ['slug' => 'gin-tonic-botanico-clasico', 'nombre' => 'Gin Tonic Botánico Clásico', 'cat' => 'cocteleria-barra', 'precio' => 38000.00, 'costo' => 12000.00, 'area' => 'barra', 'desc' => 'Ginebra destilada con enebro silvestre, tónica premium Fever-Tree, bayas de enebro y piel de pepino.'],
            ['slug' => 'gin-tonic-citrico-frutos-rojos', 'nombre' => 'Gin Tonic Cítrico & Frutos Rojos', 'cat' => 'cocteleria-barra', 'precio' => 39000.00, 'costo' => 12500.00, 'area' => 'barra', 'desc' => 'Ginebra aromatizada con fresas y arándanos frescos, tónica rosada y rodaja de toronja deshidratada.'],
            ['slug' => 'moscow-mule-maracuya', 'nombre' => 'Moscow Mule de Maracuyá en Taza de Cobre', 'cat' => 'cocteleria-barra', 'precio' => 36000.00, 'costo' => 11000.00, 'area' => 'barra', 'desc' => 'Vodka premium, zumo fresco de maracuyá, ginger beer picante artesanal y hojas de hierbabuena.'],
            ['slug' => 'mojito-clasico-ron-anejo', 'nombre' => 'Mojito Cubano Clásico con Ron Añejo', 'cat' => 'cocteleria-barra', 'precio' => 34000.00, 'costo' => 10500.00, 'area' => 'barra', 'desc' => 'Ron Medellín 8 años, hierbabuena macerada con azúcar morena, zumo de limón y agua con gas.'],
            ['slug' => 'mojito-clasico-de-ron-anejo', 'nombre' => 'Mojito de Frutos del Bosque', 'cat' => 'cocteleria-barra', 'precio' => 36000.00, 'costo' => 11500.00, 'area' => 'barra', 'desc' => 'Ron añejo, macerado de moras y frambuesas con toque fresco de menta silvestre.'],
            ['slug' => 'espresso-martini-con-cafe-antioqueno', 'nombre' => 'Espresso Martini con Café de Jericó', 'cat' => 'cocteleria-barra', 'precio' => 38000.00, 'costo' => 12000.00, 'area' => 'barra', 'desc' => 'Vodka, licor Kahlúa, espresso recién extraído de café de especialidad antioqueño y granos tostados.'],
            ['slug' => 'margarita-de-frutos-amarillos', 'nombre' => 'Margarita de Maracuyá & Mango Biche', 'cat' => 'cocteleria-barra', 'precio' => 37000.00, 'costo' => 11800.00, 'area' => 'barra', 'desc' => 'Tequila reposado, triple sec, pulpa de maracuyá y escarcha de sal picante tajín.'],
            ['slug' => 'pina-colada-artesanal', 'nombre' => 'Piña Colada Cremosa Artesanal', 'cat' => 'cocteleria-barra', 'precio' => 32000.00, 'costo' => 9500.00, 'area' => 'barra', 'desc' => 'Ron blanco, crema de coco de la costa, zumo de piña natural colada y cereza marrasquino.'],
            ['slug' => 'sangria-tinta-de-la-casa', 'nombre' => 'Sangría Tinta de Autor (Copa Grande)', 'cat' => 'cocteleria-barra', 'precio' => 28000.00, 'costo' => 8500.00, 'area' => 'barra', 'desc' => 'Vino tinto cabernet sauvignon macerado con manzana, naranja, canela y toque de licor de naranja.'],
            ['slug' => 'aperol-spritz-veneciano', 'nombre' => 'Aperol Spritz Clásico con Prosecco', 'cat' => 'cocteleria-barra', 'precio' => 39000.00, 'costo' => 13000.00, 'area' => 'barra', 'desc' => 'Aperol italiano, vino espumoso prosecco, chorrito de soda y rodaja de naranja fresca.'],
            ['slug' => 'sour-maracuya-aguardiente', 'nombre' => 'Antioqueño Sour de Maracuyá', 'cat' => 'cocteleria-barra', 'precio' => 32000.00, 'costo' => 9000.00, 'area' => 'barra', 'desc' => 'Aguardiente antioqueño sin azúcar, reducción de maracuyá, clara batida y gotas de angostura.'],
            ['slug' => 'ron-medellin-extra-anejo-8-anos', 'nombre' => 'Trago Ron Medellín Extra Añejo 8 Años', 'cat' => 'cocteleria-barra', 'precio' => 28000.00, 'costo' => 8000.00, 'area' => 'barra', 'desc' => 'Servido a la roca o puro en copa de cata, notas tostadas de roble y vainilla.'],
            ['slug' => 'ron-zacapa-23-solera', 'nombre' => 'Trago Ron Zacapa Centenario 23 Años', 'cat' => 'cocteleria-barra', 'precio' => 48000.00, 'costo' => 17000.00, 'area' => 'barra', 'desc' => 'Añejamiento en solera a 2.300 msnm con notas dulces de miel y caramelo.'],
            ['slug' => 'aguardiente-antioqueno-azul', 'nombre' => 'Media de Aguardiente Antioqueño Sin Azúcar', 'cat' => 'cocteleria-barra', 'precio' => 52000.00, 'costo' => 21000.00, 'area' => 'barra', 'desc' => 'Media botella 375ml con copas heladas y limón mandarino.'],
            ['slug' => 'whisky-old-parr-12-anos', 'nombre' => 'Trago Whisky Old Parr 12 Años Blended', 'cat' => 'cocteleria-barra', 'precio' => 36000.00, 'costo' => 12000.00, 'area' => 'barra', 'desc' => 'El whisky preferido de las celebraciones en Medellín, servido con hielos macizos.'],
            ['slug' => 'whisky-buchanans-deluxe-12', 'nombre' => 'Trago Whisky Buchanan\'s De Luxe 12 Años', 'cat' => 'cocteleria-barra', 'precio' => 38000.00, 'costo' => 13000.00, 'area' => 'barra', 'desc' => 'Mezcla escocesa suave y cítrica con acabado ahumado elegante.'],
            ['slug' => 'tequila-don-julio-blanco', 'nombre' => 'Trago Tequila Don Julio Blanco 100% Agave', 'cat' => 'cocteleria-barra', 'precio' => 42000.00, 'costo' => 14500.00, 'area' => 'barra', 'desc' => 'Tequila ultra-premium mexicano con notas frescas de agave cocido y pimienta negra.'],
            ['slug' => 'mezcal-artesanal-oaxaqueno', 'nombre' => 'Trago Mezcal Artesanal Espadín Joven', 'cat' => 'cocteleria-barra', 'precio' => 44000.00, 'costo' => 15000.00, 'area' => 'barra', 'desc' => 'Destilado ahumado en horno de tierra con rodajas de naranja y sal de gusano.'],
            ['slug' => 'ginebra-hendricks-pepino', 'nombre' => 'Trago Ginebra Hendrick\'s Escocesa', 'cat' => 'cocteleria-barra', 'precio' => 45000.00, 'costo' => 16000.00, 'area' => 'barra', 'desc' => 'Infusión exclusiva de rosa de damasco y pepinos de los países bajos.'],
            ['slug' => 'vodka-absolut-original', 'nombre' => 'Trago Vodka Absolut Original Sueco', 'cat' => 'cocteleria-barra', 'precio' => 26000.00, 'costo' => 7500.00, 'area' => 'barra', 'desc' => 'Destilado continuo de trigo de invierno sueco, pureza excepcional.'],
            ['slug' => 'baileys-irish-cream-rocas', 'nombre' => 'Copa de Baileys Irish Cream en las Rocas', 'cat' => 'cocteleria-barra', 'precio' => 26000.00, 'costo' => 8000.00, 'area' => 'barra', 'desc' => 'Crema de whisky irlandés suave sobre abundante hielo picado.'],

            // --- 10 BEBIDAS EMBOTELLADAS COLOMBIANAS ---
            ['slug' => 'colombiana-la-nuestra-botella', 'nombre' => 'Gaseosa Colombiana La Nuestra (330ml Vidrio)', 'cat' => 'bebidas-jugos', 'precio' => 8000.00, 'costo' => 2800.00, 'area' => 'barra', 'desc' => 'La gaseosa insignia de Colombia servida helada en botella de vidrio con rodaja de naranja.'],
            ['slug' => 'gaseosa-postobon-manzana', 'nombre' => 'Gaseosa Postobón Manzana (330ml Vidrio)', 'cat' => 'bebidas-jugos', 'precio' => 8000.00, 'costo' => 2800.00, 'area' => 'barra', 'desc' => 'Sabor dulce y refrescante tradicional antioqueño servido en vaso escarchado con hielo.'],
            ['slug' => 'bretana-con-limon-y-sal', 'nombre' => 'Soda Bretaña con Hielo, Limón y Sal', 'cat' => 'bebidas-jugos', 'precio' => 8500.00, 'costo' => 2500.00, 'area' => 'barra', 'desc' => 'Agua con gas carbonatada Bretaña servida con rodaja de limón Tahití y borde de sal marina.'],
            ['slug' => 'jugo-hit-mango-botella', 'nombre' => 'Jugo Hit de Mango en Botella de Vidrio', 'cat' => 'bebidas-jugos', 'precio' => 7500.00, 'costo' => 2400.00, 'area' => 'barra', 'desc' => 'Bebida de néctar de mango natural dulce colombiano bien frío.'],
            ['slug' => 'jugo-hit-lulo-botella', 'nombre' => 'Jugo Hit de Lulo Típico en Botella', 'cat' => 'bebidas-jugos', 'precio' => 7500.00, 'costo' => 2400.00, 'area' => 'barra', 'desc' => 'El sabor cítrico inconfundible del lulo colombiano en botella clásica.'],
            ['slug' => 'cerveza-club-colombia-dorada', 'nombre' => 'Cerveza Club Colombia Dorada (330ml Botella)', 'cat' => 'bebidas-jugos', 'precio' => 11000.00, 'costo' => 4200.00, 'area' => 'barra', 'desc' => 'Lager premium elaborada con malta de cebada seleccionada, sabor noble y suave amargor.'],
            ['slug' => 'cerveza-club-colombia-roja', 'nombre' => 'Cerveza Club Colombia Roja (330ml Botella)', 'cat' => 'bebidas-jugos', 'precio' => 11000.00, 'costo' => 4200.00, 'area' => 'barra', 'desc' => 'Cerveza amber de notas caramelizadas tostadas y color cobrizo brillante.'],
            ['slug' => 'cerveza-club-colombia-negra', 'nombre' => 'Cerveza Club Colombia Negra (330ml Botella)', 'cat' => 'bebidas-jugos', 'precio' => 11000.00, 'costo' => 4200.00, 'area' => 'barra', 'desc' => 'Estilo Dunkel con aroma a café y chocolate amargo tostado.'],
            ['slug' => 'cerveza-aguila-original', 'nombre' => 'Cerveza Águila Original Clásica (330ml)', 'cat' => 'bebidas-jugos', 'precio' => 9500.00, 'costo' => 3500.00, 'area' => 'barra', 'desc' => 'La cerveza más tradicional de Colombia, balanceada, ligera y sumamente refrescante.'],
            ['slug' => 'cerveza-bbc-monserrate-roja', 'nombre' => 'Cerveza Artesanal BBC Monserrate Roja (330ml)', 'cat' => 'bebidas-jugos', 'precio' => 14000.00, 'costo' => 5500.00, 'area' => 'barra', 'desc' => 'Cerveza roja de abadía de Bogotá Beer Company con cuerpo maltoso y lúpulos aromáticos.'],
            ['slug' => 'cerveza-bbc-cajica-miel', 'nombre' => 'Cerveza Artesanal BBC Cajicá Miel (330ml)', 'cat' => 'bebidas-jugos', 'precio' => 14000.00, 'costo' => 5500.00, 'area' => 'barra', 'desc' => 'Ale dorada refrescante infusionada con miel orgánica de abejas de la sabana.'],
            ['slug' => 'agua-cristal-manantial-500ml', 'nombre' => 'Agua Cristal Manantial con Gas / sin Gas (500ml)', 'cat' => 'bebidas-jugos', 'precio' => 6000.00, 'costo' => 1800.00, 'area' => 'barra', 'desc' => 'Agua pura de manantial colombiano en botella reciclable.'],
            ['slug' => 'limonada-de-coco-artesanal', 'nombre' => 'Limonada de Coco Cremosita Artesanal', 'cat' => 'bebidas-jugos', 'precio' => 16000.00, 'costo' => 5200.00, 'area' => 'barra', 'desc' => 'Frappé con leche de coco fresca, zumo de limón Tahití y hierbabuena.'],
        ];

        $productosMap = [];
        foreach ($catalogo as $item) {
            $catId = $catsMenu[$item['cat']]->id ?? $catsMenu['cortes-parrilla']->id;
            $prod = Producto::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'categoria_id' => $catId,
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['desc'],
                    'precio' => $item['precio'],
                    'costo' => $item['costo'],
                    'area_cocina' => $item['area'],
                    'imagen' => '/demo/platos/'.$item['slug'].'.jpg',
                    'activo' => true,
                ]
            );
            $productosMap[$item['slug']] = $prod;
        }

        // 10. 20 CLIENTES EN MEDELLÍN (VIP, Elegibles, Frecuentes, Normales)
        $clientesData = [
            // 5 VIPs
            ['nombre' => 'Santiago Echeverría Restrepo', 'email' => 'santiago.vip@gmail.com', 'telefono' => '+57 300 219 4433', 'vip_estado' => 'activo', 'consumo' => 1450000.00, 'barrio' => 'Provenza, El Poblado'],
            ['nombre' => 'María Camila Londoño Vélez', 'email' => 'camilalondono@hotmail.com', 'telefono' => '+57 312 889 0044', 'vip_estado' => 'activo', 'consumo' => 1280000.00, 'barrio' => 'Manila, El Poblado'],
            ['nombre' => 'Andrés Felipe Uribe Botero', 'email' => 'afuribe@bancolombia.com.co', 'telefono' => '+57 314 556 7788', 'vip_estado' => 'activo', 'consumo' => 1650000.00, 'barrio' => 'Castropol, El Poblado'],
            ['nombre' => 'Valeria Saldarriaga Arango', 'email' => 'valeria.salda@gmail.com', 'telefono' => '+57 315 778 9900', 'vip_estado' => 'activo', 'consumo' => 1120000.00, 'barrio' => 'Laureles 2do Parque'],
            ['nombre' => 'Juan Guillermo Cuartas Peña', 'email' => 'jcuartas@grupoargos.com', 'telefono' => '+57 318 990 1122', 'vip_estado' => 'activo', 'consumo' => 1950000.00, 'barrio' => 'Loma del Campestre, Poblado'],

            // 5 Elegibles a VIP (Consumo entre 500k y 950k COP en 60 días)
            ['nombre' => 'Federico Ochoa Posada', 'email' => 'federico.ochoa@outlook.com', 'telefono' => '+57 311 223 3445', 'vip_estado' => 'elegible', 'consumo' => 780000.00, 'barrio' => 'Conquistadores, Medellín'],
            ['nombre' => 'Daniela Hincapié Toro', 'email' => 'daniela.hincapie@gmail.com', 'telefono' => '+57 310 334 4556', 'vip_estado' => 'elegible', 'consumo' => 640000.00, 'barrio' => 'Envigado La Magnolia'],
            ['nombre' => 'Alejandro Gaviria Correa', 'email' => 'agaviria@epm.com.co', 'telefono' => '+57 316 445 5667', 'vip_estado' => 'elegible', 'consumo' => 890000.00, 'barrio' => 'Ciudad del Río, Medellín'],
            ['nombre' => 'Laura Sofía Gómez Duque', 'email' => 'laurasofia.gomez@yahoo.es', 'telefono' => '+57 317 556 6778', 'vip_estado' => 'elegible', 'consumo' => 720000.00, 'barrio' => 'Sabaneta Parque'],
            ['nombre' => 'Esteban Meza Chavarriaga', 'email' => 'esteban.meza@gmail.com', 'telefono' => '+57 319 667 7889', 'vip_estado' => 'elegible', 'consumo' => 580000.00, 'barrio' => 'Belén Rosales, Medellín'],

            // 5 Frecuentes
            ['nombre' => 'Carolina Montoya Ospina', 'email' => 'caro.montoya@gmail.com', 'telefono' => '+57 313 778 8990', 'vip_estado' => null, 'consumo' => 450000.00, 'barrio' => 'Laureles Nogal'],
            ['nombre' => 'Nicolás Betancur Piedrahíta', 'email' => 'nico.betancur@empresa.com', 'telefono' => '+57 301 889 9001', 'vip_estado' => null, 'consumo' => 420000.00, 'barrio' => 'San Lucas, El Poblado'],
            ['nombre' => 'Mariana Cardona Jaramillo', 'email' => 'mariana.cardona@hotmail.com', 'telefono' => '+57 302 990 0112', 'vip_estado' => null, 'consumo' => 380000.00, 'barrio' => 'Envigado Jardines'],
            ['nombre' => 'Sebastián Peláez Villegas', 'email' => 'spelaez@gmail.com', 'telefono' => '+57 304 112 2334', 'vip_estado' => null, 'consumo' => 490000.00, 'barrio' => 'Los Colores, Medellín'],
            ['nombre' => 'Catalina Morales Tobón', 'email' => 'cata.morales@gmail.com', 'telefono' => '+57 305 223 3445', 'vip_estado' => null, 'consumo' => 360000.00, 'barrio' => 'Poblado Astorga'],

            // 5 Normales / Ocasionales
            ['nombre' => 'Gabriel Jaime Agudelo', 'email' => 'gabriel.agudelo@gmail.com', 'telefono' => '+57 320 334 4556', 'vip_estado' => null, 'consumo' => 120000.00, 'barrio' => 'Estadio, Medellín'],
            ['nombre' => 'Paula Andrea Villegas', 'email' => 'paula.villegas@gmail.com', 'telefono' => '+57 321 445 5667', 'vip_estado' => null, 'consumo' => 95000.00, 'barrio' => 'Calasanz, Medellín'],
            ['nombre' => 'Julián David Quiceno', 'email' => 'julian.quiceno@hotmail.com', 'telefono' => '+57 322 556 6778', 'vip_estado' => null, 'consumo' => 180000.00, 'barrio' => 'Bello Niquía'],
            ['nombre' => 'Diana Marcela Ríos', 'email' => 'diana.rios@gmail.com', 'telefono' => '+57 323 667 7889', 'vip_estado' => null, 'consumo' => 110000.00, 'barrio' => 'Envigado Zuñiga'],
            ['nombre' => 'Tomás Herrón Ceballos', 'email' => 'tomas.herron@gmail.com', 'telefono' => '+57 324 778 8990', 'vip_estado' => null, 'consumo' => 85000.00, 'barrio' => 'La Floresta, Medellín'],
        ];

        $clientesList = [];
        foreach ($clientesData as $cd) {
            $cliente = Cliente::updateOrCreate(
                ['email' => $cd['email']],
                [
                    'nombre' => $cd['nombre'],
                    'telefono' => $cd['telefono'],
                    'tier' => $cd['vip_estado'] === 'activo' ? 'vip' : ($cd['consumo'] > 500000 ? 'frecuente' : 'ocasional'),
                    'vip_estado' => $cd['vip_estado'] ?: 'ninguno',
                    'vip_desde' => $cd['vip_estado'] === 'activo' ? Carbon::now()->subMonths(3) : null,
                    'puntos_fidelidad' => $cd['vip_estado'] === 'activo' ? 450 : 80,
                    'visitas_count' => $cd['consumo'] > 1000000 ? 12 : ($cd['consumo'] > 500000 ? 6 : 2),
                    'total_gastado' => $cd['consumo'],
                    'autoriza_whatsapp' => true,
                    'autoriza_email' => true,
                    'activo' => true,
                ]
            );

            DireccionCliente::firstOrCreate(
                ['cliente_id' => $cliente->id],
                [
                    'etiqueta' => 'Casa',
                    'direccion' => 'Calle 10 # 35-12',
                    'barrio_ciudad' => $cd['barrio'].', Medellín',
                    'telefono_contacto' => $cd['telefono'],
                    'es_predeterminada' => true,
                ]
            );

            $clientesList[] = $cliente;
        }

        // 11. CRM CONFIGURACIÓN & PLANTILLAS
        CrmConfiguracion::updateOrCreate(
            ['sucursal_id' => null],
            [
                'whatsapp_proveedor' => 'meta_cloud',
                'whatsapp_phone_number_id' => '105938472910482',
                'whatsapp_access_token' => 'EAAXDemoTokenMetaCloudRestoMaster992',
                'email_activo' => true,
                'email_remitente_nombre' => 'RestoMaster Medellín',
                'email_remitente_correo' => 'experiencia@restomaster.com',
                'delay_encuesta_minutos' => 15,
                'winback_dias_inactividad' => 45,
            ]
        );

        // 12. PLANTILLAS DE ENCUESTAS
        $encuestaGeneral = Encuesta::updateOrCreate(
            ['nombre' => 'Satisfacción General Post-Consumo'],
            [
                'sucursal_id' => $sucursal->id,
                'activa' => true,
                'disparador' => 'post_pago',
                'delay_horas' => 1,
                'preguntas' => [
                    ['pregunta' => '¿Cómo calificarías la calidad y sazón de nuestros platos hoy?', 'tipo' => 'estrellas'],
                    ['pregunta' => '¿Qué tal fue la atención y amabilidad de tu mesero?', 'tipo' => 'estrellas'],
                    ['pregunta' => '¿La velocidad de entrega de tu orden cumplió tus expectativas?', 'tipo' => 'si_no'],
                    ['pregunta' => '¿Qué detalle o sugerencia compartirías para hacer tu próxima visita extraordinaria?', 'tipo' => 'texto'],
                ],
            ]
        );

        $encuestaBarra = Encuesta::updateOrCreate(
            ['nombre' => 'Auditoría de Calidad en Barra & Coctelería'],
            [
                'sucursal_id' => $sucursal->id,
                'activa' => true,
                'disparador' => 'post_pago',
                'delay_horas' => 2,
                'preguntas' => [
                    ['pregunta' => '¿Cómo estuvo la temperatura y balance de tu cóctel / bebida?', 'tipo' => 'estrellas'],
                    ['pregunta' => '¿La presentación y cristalería fue impecable?', 'tipo' => 'si_no'],
                    ['pregunta' => 'Comentarios sobre la carta de mixología de nuestra barra:', 'tipo' => 'texto'],
                ],
            ]
        );

        $encuestaDelivery = Encuesta::updateOrCreate(
            ['nombre' => 'Experiencia Despacho & Domicilio Delivery'],
            [
                'sucursal_id' => $sucursal->id,
                'activa' => true,
                'disparador' => 'post_pago',
                'delay_horas' => 1,
                'preguntas' => [
                    ['pregunta' => '¿Cómo llegó la temperatura y estado del empaque térmico?', 'tipo' => 'estrellas'],
                    ['pregunta' => '¿La puntualidad del repartidor en moto fue adecuada?', 'tipo' => 'si_no'],
                    ['pregunta' => '¿Qué podemos mejorar en tu experiencia de domicilio?', 'tipo' => 'texto'],
                ],
            ]
        );

        // 13. 14 PROMOCIONES CON IMÁGENES
        $promocionesData = [
            // 7 ACTIVAS
            [
                'slug' => 'jueves-gin-tonic-parrilla-2x1',
                'titulo' => 'Jueves de Gin Tonic 2x1 & Parrilla de Autor',
                'subtitulo' => 'Mixología botánica y cortes al fuego en terraza Provenza',
                'tipo_beneficio' => 'dos_por_uno',
                'imagen_url' => '/images/promo-gin-tonic-2x1.jpg',
                'activo' => true,
                'orden' => 1,
            ],
            [
                'slug' => 'fin-de-semana-angus-prime-20-off',
                'titulo' => '20% OFF en Cortes Tomahawk & Ribeye Prime',
                'subtitulo' => 'Maduración Dry-Aged 45 días al carbón silvestre',
                'tipo_beneficio' => 'descuento_porcentaje',
                'descuento_porcentaje' => 20.00,
                'imagen_url' => '/images/promo-angus-prime-weekend.jpg',
                'activo' => true,
                'orden' => 2,
            ],
            [
                'slug' => 'experiencia-degustacion-maridaje-vip',
                'titulo' => 'Menú Degustación 5 Tiempos con Maridaje Sommelier',
                'subtitulo' => 'Recorrido sensorial guiado por Sommelier y Chef Ejecutivo',
                'tipo_beneficio' => 'precio_fijo',
                'precio_promocional' => 185000.00,
                'precio_original' => 230000.00,
                'imagen_url' => '/images/promo-maridaje-sommelier.jpg',
                'activo' => true,
                'orden' => 3,
            ],
            [
                'slug' => 'almuerzo-ejecutivo-gourmet-poblado',
                'titulo' => 'Almuerzo Ejecutivo Gourmet Poblado',
                'subtitulo' => 'Entrada ligera, corte a elección, bebida y café de origen',
                'tipo_beneficio' => 'precio_fijo',
                'precio_promocional' => 38000.00,
                'precio_original' => 52000.00,
                'imagen_url' => '/images/promo-almuerzo-ejecutivo.jpg',
                'activo' => true,
                'orden' => 4,
            ],
            [
                'slug' => 'martes-de-burgers-cerveza-bbc',
                'titulo' => 'Martes de Burger Angus & Cerveza BBC Artesanal',
                'subtitulo' => 'Cualquier burger de la carta con pinta de BBC Monserrate Roja',
                'tipo_beneficio' => 'precio_fijo',
                'precio_promocional' => 45000.00,
                'precio_original' => 58000.00,
                'imagen_url' => '/images/promo-martes-burger-bbc.jpg',
                'activo' => true,
                'orden' => 5,
            ],
            [
                'slug' => 'happy-hour-terraza-provenza-cocteles',
                'titulo' => 'Happy Hour Terraza Provenza (17:00 a 19:30)',
                'subtitulo' => 'Cocteles de autor seleccionados al 2x1 al atardecer',
                'tipo_beneficio' => 'dos_por_uno',
                'imagen_url' => '/images/promo-happy-hour-terraza.jpg',
                'activo' => true,
                'orden' => 6,
            ],
            [
                'slug' => 'delivery-gratis-poblado-envigado-laureles',
                'titulo' => 'Envío Gratis en Medellín en Pedidos >$80.000',
                'subtitulo' => 'Cobertura prioritaria en El Poblado, Envigado y Laureles',
                'tipo_beneficio' => 'descuento_porcentaje',
                'descuento_porcentaje' => 100.00,
                'imagen_url' => '/images/promo-delivery-gratis-poblado.jpg',
                'activo' => true,
                'orden' => 7,
            ],

            // 7 INACTIVAS / LISTAS PARA ACTIVAR (Borrador)
            [
                'slug' => 'festival-del-mar-y-ceviches-del-pacifico',
                'titulo' => 'Festival del Mar & Ceviches del Pacífico',
                'subtitulo' => 'Cazuelas tradicionales y ceviches nikkei con 25% de descuento',
                'tipo_beneficio' => 'descuento_porcentaje',
                'descuento_porcentaje' => 25.00,
                'imagen_url' => '/images/promo-festival-mar-pacifico.jpg',
                'activo' => false,
                'orden' => 8,
            ],
            [
                'slug' => 'noche-romantica-botella-vino-cortesia',
                'titulo' => 'Noche de Parejas & Botella de Vino Reserva',
                'subtitulo' => 'Por el consumo de dos platos fuertes recibe vino tinto de la casa',
                'tipo_beneficio' => 'precio_fijo',
                'precio_promocional' => 140000.00,
                'precio_original' => 195000.00,
                'imagen_url' => '/images/promo-noche-romantica-vino.jpg',
                'activo' => false,
                'orden' => 9,
            ],
            [
                'slug' => 'cumpleanero-vip-no-paga-plato-fuerte',
                'titulo' => 'Cumpleañero VIP No Paga Plato Fuerte',
                'subtitulo' => 'Ven con 3 acompañantes en tu mes de cumpleaños y tu plato es gratis',
                'tipo_beneficio' => 'descuento_porcentaje',
                'descuento_porcentaje' => 100.00,
                'imagen_url' => '/images/promo-cumpleanero-vip.jpg',
                'activo' => false,
                'orden' => 10,
            ],
            [
                'slug' => 'viernes-de-alitas-club-colombia-3x2',
                'titulo' => 'Viernes de Alitas & Cerveza Club Colombia 3x2',
                'subtitulo' => 'Pagas 2 botellas de Club Colombia y te llevas 3 toda la noche',
                'tipo_beneficio' => 'dos_por_uno',
                'imagen_url' => '/images/promo-alitas-club-colombia.jpg',
                'activo' => false,
                'orden' => 11,
            ],
            [
                'slug' => 'brunch-dominical-mimosas-libres',
                'titulo' => 'Brunch Dominical Provenza con Barra Libre de Mimosas',
                'subtitulo' => 'De 10:00 a 14:00 horas todos los domingos con música acústica',
                'tipo_beneficio' => 'precio_fijo',
                'precio_promocional' => 75000.00,
                'precio_original' => 105000.00,
                'imagen_url' => '/images/promo-brunch-mimosas.jpg',
                'activo' => false,
                'orden' => 12,
            ],
            [
                'slug' => 'festival-de-pastas-frescas-y-risottos',
                'titulo' => 'Festival de Pastas Frescas & Risottos Trufados',
                'subtitulo' => 'Cualquier pasta artesanal a precio especial de martes a jueves',
                'tipo_beneficio' => 'precio_fijo',
                'precio_promocional' => 32000.00,
                'precio_original' => 44000.00,
                'imagen_url' => '/images/promo-festival-pastas.jpg',
                'activo' => false,
                'orden' => 13,
            ],
            [
                'slug' => 'bono-bienvenida-primer-pedido-app',
                'titulo' => 'Bono de Bienvenida $25.000 COP en Primer Pedido',
                'subtitulo' => 'Aplica para pedidos por QR o delivery en la plataforma web',
                'tipo_beneficio' => 'descuento_porcentaje',
                'descuento_porcentaje' => 25.00,
                'imagen_url' => '/images/promo-bono-bienvenida-app.jpg',
                'activo' => false,
                'orden' => 14,
            ],
        ];

        foreach ($promocionesData as $p) {
            Promocion::updateOrCreate(
                ['slug' => $p['slug']],
                array_merge($p, [
                    'descripcion' => $p['titulo'].' — '.$p['subtitulo'].' en RestoMaster Medellín.',
                    'terminos_condiciones' => 'Válido exclusivamente en la sede El Poblado de RestoMaster. No acumulable.',
                    'fecha_inicio' => Carbon::now()->subDays(10),
                    'fecha_fin' => Carbon::now()->addDays(60),
                    'mostrar_en_portada' => $p['activo'],
                    'aplica_salon' => true,
                    'aplica_delivery' => true,
                ])
            );
        }

        // 14. 30 DÍAS DE OPERACIONES HISTÓRICAS (VENTAS, CAJAS, COMPRAS, DIAN, ENCUESTAS)
        $this->command?->info('Sembrando 30 días de operaciones históricas y en vivo...');

        $meserosArray = User::whereHas('role', fn ($q) => $q->where('slug', 'mesero'))->get()->all();
        $primerMesero = $meserosArray[0] ?? $usersMap['admin@restomaster.com'];
        $cajerosArray = User::whereHas('role', fn ($q) => $q->where('slug', 'cajero'))->get()->all();
        $repartidoresArray = User::whereHas('role', fn ($q) => $q->where('slug', 'delivery'))->get()->all();

        $platosKeys = array_keys($productosMap);
        $totalDias = 30;

        $feedbackTextosPositivos = [
            '¡El bife de chorizo en su punto exacto! La atención de %s fue impecable, volveremos pronto.',
            'Excelente la coctelería, el Gin Tonic botánico es de los mejores de Provenza.',
            'Muy recomendada la cazuela de mariscos y los patacones súper crocantes.',
            'El ambiente en la terraza es mágico de noche. Felicitaciones al equipo de cocina.',
            'Gran servicio y rapidez. La hamburguesa angus con trufa superó las expectativas.',
            'Atención de 10 estrellas por parte de %s, nos sugirió un maridaje perfecto.',
        ];

        $feedbackTextosMejora = [
            'La carne estuvo muy rica pero el puré llegó un poco tibio. El mesero solucionó rápido.',
            'El mojito estaba un poco dulce para mi gusto, pero el servicio en mesa fue muy amable.',
            'Demoró 25 minutos la entrada porque el salón estaba lleno, pero valió la pena la espera.',
        ];

        for ($d = $totalDias; $d >= 1; $d--) {
            $fecha = Carbon::today()->subDays($d);
            $esFinDeSemana = in_array($fecha->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY, Carbon::SUNDAY], true);

            // 1. Turno de Caja del Día
            $apertura = $fecha->copy()->setTime(11, 30);
            $cierre = $fecha->copy()->setTime(23, 45);
            $montoApertura = 300000.00;
            $cashierUser = $cajerosArray[$d % count($cajerosArray)] ?? $usersMap['cajero@restomaster.com'];

            $turno1 = TurnoCaja::firstOrCreate(
                ['caja_id' => $cajaSalón->id, 'apertura_en' => $apertura],
                [
                    'user_id' => $cashierUser->id,
                    'monto_inicial' => $montoApertura,
                    'monto_real_efectivo' => $esFinDeSemana ? 4250000.00 : 2350000.00,
                    'total_ventas_efectivo' => $esFinDeSemana ? 3000000.00 : 1500000.00,
                    'total_ventas_tarjeta' => $esFinDeSemana ? 1250000.00 : 850000.00,
                    'cierre_en' => $cierre,
                    'estado' => 'cerrado',
                    'notas_cierre' => 'Cierre cuadrado sin novedades · Sede El Poblado.',
                    'cerrado_por_user_id' => $cashierUser->id,
                    'created_at' => $apertura,
                    'updated_at' => $cierre,
                ]
            );

            // Movimientos de Caja si recién se creó el turno
            if ($turno1->wasRecentlyCreated) {
                MovimientoCaja::create([
                    'turno_caja_id' => $turno1->id,
                    'tipo' => 'ingreso',
                    'monto' => $montoApertura,
                    'concepto' => 'Base inicial de cambio en billetes colombianos',
                    'metodo_pago' => 'efectivo',
                    'user_id' => $turno1->user_id,
                    'created_at' => $apertura,
                ]);

                MovimientoCaja::create([
                    'turno_caja_id' => $turno1->id,
                    'tipo' => 'egreso',
                    'monto' => 45000.00,
                    'concepto' => 'Caja menor: compra urgente de hielo y limones frescos',
                    'metodo_pago' => 'efectivo',
                    'user_id' => $turno1->user_id,
                    'created_at' => $fecha->copy()->setTime(15, 20),
                ]);

                MovimientoCaja::create([
                    'turno_caja_id' => $turno1->id,
                    'tipo' => 'retiro',
                    'monto' => $esFinDeSemana ? 2000000.00 : 1000000.00,
                    'concepto' => 'Retiro de seguridad preventivo a bóveda bancaria',
                    'metodo_pago' => 'efectivo',
                    'user_id' => $turno1->user_id,
                    'created_at' => $fecha->copy()->setTime(19, 00),
                ]);
            }

            // 2. Pedidos y Comandas del Día (5 a 8 comandas por día)
            $comandasDelDia = $esFinDeSemana ? 8 : 5;
            for ($c = 1; $c <= $comandasDelDia; $c++) {
                $codigoPedido = 'MDE-'.$fecha->format('ymd').'-'.str_pad((string) ($d * 20 + $c), 4, '0', STR_PAD_LEFT);
                if (Pedido::where('codigo', $codigoPedido)->exists()) {
                    continue;
                }

                $horaPedido = $fecha->copy()->setTime(rand(12, 22), rand(5, 55));
                $cliente = $clientesList[array_rand($clientesList)];
                $meseroAsignado = $meserosArray[array_rand($meserosArray)] ?? $primerMesero;
                $mesaAsignada = $mesas[array_rand($mesas)];
                $esDelivery = ($c === $comandasDelDia && $d % 2 === 0);

                $pedido = Pedido::forceCreate([
                    'codigo' => $codigoPedido,
                    'sucursal_id' => $sucursal->id,
                    'mesa_id' => $esDelivery ? null : $mesaAsignada->id,
                    'cliente_id' => $cliente->id,
                    'usuario_id' => $meseroAsignado->id,
                    'mesero_id' => $meseroAsignado->id,
                    'turno_caja_id' => $turno1->id,
                    'nombre_cliente' => $cliente->nombre,
                    'telefono_cliente' => $cliente->telefono,
                    'direccion_delivery' => $esDelivery ? 'Calle 10 # 35-12, '.$cliente->direcciones()->first()?->barrio_ciudad : null,
                    'repartidor_id' => $esDelivery ? ($repartidoresArray[array_rand($repartidoresArray)]->id ?? null) : null,
                    'tipo' => $esDelivery ? 'delivery' : 'mesa',
                    'estado' => 'pagado',
                    'metodo_pago' => ['efectivo', 'tarjeta', 'transferencia'][rand(0, 2)],
                    'subtotal' => 0,
                    'total' => 0,
                    'propina' => 0,
                    'descuento' => 0,
                    'pagado_en' => $horaPedido->copy()->addMinutes(45),
                    'created_at' => $horaPedido,
                    'updated_at' => $horaPedido->copy()->addMinutes(45),
                ]);

                // 2 a 4 ítems por pedido
                $cantItems = rand(2, 4);
                $subtotal = 0;
                for ($it = 1; $it <= $cantItems; $it++) {
                    $prodKey = $platosKeys[array_rand($platosKeys)];
                    $producto = $productosMap[$prodKey];
                    $qty = rand(1, 2);
                    $lineTotal = $producto->precio * $qty;
                    $subtotal += $lineTotal;

                    ItemPedido::create([
                        'pedido_id' => $pedido->id,
                        'producto_id' => $producto->id,
                        'nombre_producto' => $producto->nombre,
                        'cantidad' => $qty,
                        'precio_unitario' => $producto->precio,
                        'subtotal' => $lineTotal,
                        'area_cocina' => in_array($producto->categoria?->slug, ['tragos-cocteleria-autor', 'cervezas-artesanales-colombianas', 'bebidas-tipicas-colombianas'], true) ? 'barra' : 'caliente',
                        'estado_cocina' => 'entregado',
                        'notas' => in_array($it, [1], true) ? 'Término medio, salsa aparte' : null,
                        'created_at' => $horaPedido,
                        'updated_at' => $horaPedido->copy()->addMinutes(20),
                    ]);
                }

                $propina = round($subtotal * 0.10);
                $totalFinal = $subtotal + $propina;
                $pedido->update(['subtotal' => $subtotal, 'total' => $totalFinal, 'propina' => $propina, 'monto_pagado' => $totalFinal]);

                // Factura Electrónica DIAN POS
                $prefijo = 'POS';
                $consecutivo = 1000 + $pedido->id;
                $cufeRaw = "{$prefijo}{$consecutivo}{$horaPedido->format('YmdHis')}{$totalFinal}019014587893";
                $cufe = hash('sha384', $cufeRaw);

                FacturaElectronica::firstOrCreate(
                    ['pedido_id' => $pedido->id],
                    [
                        'sucursal_id' => $sucursal->id,
                        'tipo_documento' => 'pos_electronico',
                        'prefijo' => $prefijo,
                        'consecutivo' => $consecutivo,
                        'numero_factura' => "{$prefijo}-{$consecutivo}",
                        'cufe' => $cufe,
                        'qr_cadena' => "NumFac={$prefijo}-{$consecutivo}&FecFac={$horaPedido->format('Y-m-d')}&ValTolFac={$totalFinal}&CUFE={$cufe}",
                        'estado' => 'emitida',
                        'total' => $totalFinal,
                        'cliente_nit' => '222222222222',
                        'cliente_nombre' => $cliente->nombre,
                        'proveedor_tecnologico' => 'factus',
                        'emitida_en' => $horaPedido->copy()->addMinutes(45),
                        'created_at' => $horaPedido->copy()->addMinutes(45),
                    ]
                );

                // Generar Encuesta Respondida (para 40+ pedidos)
                if ($pedido->id % 3 === 0) {
                    $envio = EncuestaEnvio::firstOrCreate(
                        ['pedido_id' => $pedido->id],
                        [
                            'encuesta_id' => $esDelivery ? $encuestaDelivery->id : $encuestaGeneral->id,
                            'cliente_id' => $cliente->id,
                            'token' => Str::random(40),
                            'estado' => 'respondida',
                            'enviada_en' => $horaPedido->copy()->addMinutes(50),
                            'respondida_en' => $horaPedido->copy()->addMinutes(75),
                            'created_at' => $horaPedido->copy()->addMinutes(50),
                        ]
                    );

                    if ($envio->wasRecentlyCreated) {
                        $califEstrellas = ($pedido->id % 7 === 0) ? rand(3, 4) : 5;
                        $textoFeedback = ($califEstrellas >= 4)
                            ? sprintf($feedbackTextosPositivos[array_rand($feedbackTextosPositivos)], $meseroAsignado->name)
                            : $feedbackTextosMejora[array_rand($feedbackTextosMejora)];

                        EncuestaRespuesta::create([
                            'envio_id' => $envio->id,
                            'pregunta_indice' => 0,
                            'tipo_respuesta' => 'estrellas',
                            'valor_estrellas' => $califEstrellas,
                            'created_at' => $envio->respondida_en,
                        ]);

                        EncuestaRespuesta::create([
                            'envio_id' => $envio->id,
                            'pregunta_indice' => 1,
                            'tipo_respuesta' => 'estrellas',
                            'valor_estrellas' => $califEstrellas,
                            'created_at' => $envio->respondida_en,
                        ]);

                        EncuestaRespuesta::create([
                            'envio_id' => $envio->id,
                            'pregunta_indice' => 2,
                            'tipo_respuesta' => 'si_no',
                            'valor_booleano' => true,
                            'created_at' => $envio->respondida_en,
                        ]);

                        EncuestaRespuesta::create([
                            'envio_id' => $envio->id,
                            'pregunta_indice' => 3,
                            'tipo_respuesta' => 'texto',
                            'valor_texto' => $textoFeedback,
                            'created_at' => $envio->respondida_en,
                        ]);
                    }
                }
            }

            // 3. Compras a Proveedores, Líneas de Insumos y CxP vinculadas (30 compras en total rotando los 10 proveedores)
            $keysProveedores = array_keys($proveedoresMap);
            $prvKey = $keysProveedores[($d - 1) % count($keysProveedores)];
            $prv = $proveedoresMap[$prvKey];
            $insumosDisponibles = $insumosPorProveedor[$prv->id] ?? [];

            $lineasCompra = [];
            $montoCompra = 0;
            if (! empty($insumosDisponibles)) {
                foreach ($insumosDisponibles as $ins) {
                    $cant = match ($ins->unidad_medida) {
                        'kg' => rand(15, 35),
                        'botella' => rand(6, 18),
                        'unidad' => rand(30, 80),
                        default => rand(10, 25),
                    };
                    $costoUnit = (float) $ins->costo_unitario;
                    $subtotalLinea = round($cant * $costoUnit, 2);
                    $montoCompra += $subtotalLinea;
                    $lineasCompra[] = [
                        'insumo_id' => $ins->id,
                        'cantidad' => $cant,
                        'costo_unitario' => $costoUnit,
                        'subtotal' => $subtotalLinea,
                    ];
                }
            } else {
                $montoCompra = rand(950000, 2200000);
            }

            $numFactura = 'FAC-'.strtoupper(substr($prvKey, 0, 3)).'-'.(1000 + $d * 14);
            $tieneSoporte = ($d % 2 === 0);
            $soporteDemo = $tieneSoporte ? 'facturas_proveedores/soporte_demo_factura.png' : null;

            $compra = Compra::firstOrCreate(
                ['proveedor_id' => $prv->id, 'numero_factura' => $numFactura],
                [
                    'fecha' => $fecha->format('Y-m-d'),
                    'subtotal' => $montoCompra,
                    'forma_pago' => ($d > 20 && $d % 2 === 1) ? 'contado' : 'credito',
                    'estado' => 'recibida',
                    'soporte_factura' => $soporteDemo,
                    'user_id' => $usersMap['gerente@restomaster.com']->id,
                    'created_at' => $fecha,
                ]
            );

            // Crear líneas de insumos recibidos para esta compra
            foreach ($lineasCompra as $l) {
                CompraLinea::firstOrCreate(
                    ['compra_id' => $compra->id, 'insumo_id' => $l['insumo_id']],
                    [
                        'cantidad' => $l['cantidad'],
                        'costo_unitario' => $l['costo_unitario'],
                        'subtotal' => $l['subtotal'],
                    ]
                );
            }

            // Estados de CxP escalonados por antigüedad para pruebas completas de pagos:
            // - d > 20 (hace 21-30 días): pagada (saldo 0)
            // - d entre 11 y 20 (hace 11-20 días): parcial (50% abonado, 50% pendiente)
            // - d <= 10 (hace 1-10 días): pendiente (100% por pagar)
            $estadoCxp = ($d > 20) ? 'pagada' : (($d > 10) ? 'parcial' : 'pendiente');
            $abonoInicial = ($estadoCxp === 'pagada') ? $montoCompra : (($estadoCxp === 'parcial') ? round($montoCompra * 0.5, 2) : 0);
            $saldoPendiente = round($montoCompra - $abonoInicial, 2);

            $cxp = CuentaPorPagar::firstOrCreate(
                ['compra_id' => $compra->id],
                [
                    'proveedor_nombre' => $prv->nombre,
                    'proveedor_nit' => $prv->nit,
                    'numero_factura' => $numFactura,
                    'concepto' => 'Factura de compra '.$numFactura.' · '.$prv->nombre,
                    'monto_total' => $montoCompra,
                    'saldo_pendiente' => $saldoPendiente,
                    'fecha_emision' => $fecha->format('Y-m-d'),
                    'fecha_vencimiento' => $fecha->copy()->addDays(30)->format('Y-m-d'),
                    'estado' => $estadoCxp,
                    'notas' => 'Factura registrada en sistema con insumos recibidos en bodega',
                    'user_id' => $usersMap['gerente@restomaster.com']->id,
                    'created_at' => $fecha,
                ]
            );

            if ($abonoInicial > 0) {
                PagoCxp::firstOrCreate(
                    ['cuenta_por_pagar_id' => $cxp->id, 'monto' => $abonoInicial],
                    [
                        'user_id' => $usersMap['gerente@restomaster.com']->id,
                        'metodo_pago' => ($d % 3 === 0) ? 'efectivo' : 'transferencia',
                        'fecha_pago' => $fecha->copy()->addDays(rand(2, 6))->format('Y-m-d'),
                        'concepto' => ($estadoCxp === 'pagada')
                            ? 'Pago cancelación total factura '.$numFactura.' Ref: TRF-'.rand(100000, 999999)
                            : 'Abono 50% cartera factura '.$numFactura.' Ref: TRF-'.rand(100000, 999999),
                        'created_at' => $fecha->copy()->addDays(rand(2, 6)),
                    ]
                );
            }

            // 4. Reservas del Mes
            $clienteReserva = $clientesList[array_rand($clientesList)];
            $mesaReserva = $mesas[array_rand($mesas)];
            $tokenPublico = 'RES-MDE-'.$fecha->format('Ymd').'-'.$d;

            $reservaMes = Reserva::firstOrCreate(
                ['token_publico' => $tokenPublico],
                [
                    'sucursal_id' => $sucursal->id,
                    'cliente_id' => $clienteReserva->id,
                    'nombre_contacto' => $clienteReserva->nombre,
                    'telefono_contacto' => $clienteReserva->telefono,
                    'email_contacto' => $clienteReserva->email,
                    'fecha' => $fecha->format('Y-m-d'),
                    'hora_llegada' => '19:30:00',
                    'duracion_min' => 120,
                    'personas' => rand(2, 6),
                    'estado' => ($d % 8 === 0) ? 'cancelada' : 'completada',
                    'origen' => 'sistema',
                    'notas' => 'Cena ejecutiva / celebración de cumpleaños en terraza',
                    'anticipo' => 50000.00,
                    'created_by' => $primerMesero->id,
                    'created_at' => $fecha->copy()->subDays(2),
                ]
            );

            DB::table('reserva_mesa')->insertOrIgnore([
                'reserva_id' => $reservaMes->id,
                'mesa_id' => $mesaReserva->id,
            ]);
        }

        // 15. OPERACIONES ACTIVAS EN VIVO HOY (Para Pruebas Operativas de los Módulos)
        $this->command?->info('Creando comandas activas en vivo de hoy...');

        // 2 Turnos de Caja Abiertos Hoy
        TurnoCaja::firstOrCreate(
            ['caja_id' => $cajaSalón->id, 'estado' => 'abierto'],
            [
                'user_id' => $usersMap['cajero@restomaster.com']->id,
                'monto_inicial' => 300000.00,
                'apertura_en' => Carbon::now()->setTime(11, 0),
                'created_at' => Carbon::now()->setTime(11, 0),
            ]
        );

        TurnoCaja::firstOrCreate(
            ['caja_id' => $cajaBarra->id, 'estado' => 'abierto'],
            [
                'user_id' => $usersMap['mariana.caja@restomaster.com']->id,
                'monto_inicial' => 200000.00,
                'apertura_en' => Carbon::now()->setTime(11, 30),
                'created_at' => Carbon::now()->setTime(11, 30),
            ]
        );

        // Mesa 1: En cocina KDS
        $codP1 = 'MDE-'.Carbon::today()->format('ymd').'-0901';
        if (! Pedido::where('codigo', $codP1)->exists()) {
            $p1 = Pedido::forceCreate([
                'codigo' => $codP1,
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $mesas[0]->id,
                'cliente_id' => $clientesList[0]->id,
                'usuario_id' => $usersMap['carlos.restrepo@restomaster.com']->id,
                'mesero_id' => $usersMap['carlos.restrepo@restomaster.com']->id,
                'nombre_cliente' => $clientesList[0]->nombre,
                'tipo' => 'mesa',
                'estado' => 'en_cocina',
                'subtotal' => 118000.00,
                'total' => 118000.00,
                'created_at' => Carbon::now()->subMinutes(12),
            ]);
            ItemPedido::create(['pedido_id' => $p1->id, 'producto_id' => $productosMap['bife-de-chorizo-angus']->id, 'nombre_producto' => $productosMap['bife-de-chorizo-angus']->nombre, 'cantidad' => 1, 'precio_unitario' => 64000.00, 'subtotal' => 64000.00, 'area_cocina' => 'caliente', 'estado_cocina' => 'en_preparacion']);
            ItemPedido::create(['pedido_id' => $p1->id, 'producto_id' => $productosMap['cazuela-de-mariscos-del-pacifico']->id, 'nombre_producto' => $productosMap['cazuela-de-mariscos-del-pacifico']->nombre, 'cantidad' => 1, 'precio_unitario' => 62000.00, 'subtotal' => 62000.00, 'area_cocina' => 'caliente', 'estado_cocina' => 'en_preparacion']);
        }

        // Mesa 2: Listo para servir
        $codP2 = 'MDE-'.Carbon::today()->format('ymd').'-0902';
        if (! Pedido::where('codigo', $codP2)->exists()) {
            $p2 = Pedido::forceCreate([
                'codigo' => $codP2,
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $mesas[1]->id,
                'cliente_id' => $clientesList[1]->id,
                'usuario_id' => $usersMap['valentina.morales@restomaster.com']->id,
                'mesero_id' => $usersMap['valentina.morales@restomaster.com']->id,
                'nombre_cliente' => $clientesList[1]->nombre,
                'tipo' => 'mesa',
                'estado' => 'listo',
                'subtotal' => 78000.00,
                'total' => 78000.00,
                'created_at' => Carbon::now()->subMinutes(25),
            ]);
            ItemPedido::create(['pedido_id' => $p2->id, 'producto_id' => $productosMap['hamburguesa-restomaster-angus']->id, 'nombre_producto' => $productosMap['hamburguesa-restomaster-angus']->nombre, 'cantidad' => 2, 'precio_unitario' => 39000.00, 'subtotal' => 78000.00, 'area_cocina' => 'caliente', 'estado_cocina' => 'listo']);
        }

        // Mesa 3: Pendiente de cobro en caja
        $codP3 = 'MDE-'.Carbon::today()->format('ymd').'-0903';
        $p3 = Pedido::where('codigo', $codP3)->first();
        if (! $p3) {
            $p3 = Pedido::forceCreate([
                'codigo' => $codP3,
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $mesas[2]->id,
                'cliente_id' => $clientesList[2]->id,
                'usuario_id' => $usersMap['mateo.echeverry@restomaster.com']->id,
                'mesero_id' => $usersMap['mateo.echeverry@restomaster.com']->id,
                'nombre_cliente' => $clientesList[2]->nombre,
                'tipo' => 'mesa',
                'estado' => 'creado',
                'subtotal' => 148000.00,
                'total' => 152000.00,
                'propina' => 4000.00,
                'created_at' => Carbon::now()->subMinutes(55),
            ]);
            ItemPedido::create(['pedido_id' => $p3->id, 'producto_id' => $productosMap['ojo-de-bife-ribeye-400g']->id, 'nombre_producto' => $productosMap['ojo-de-bife-ribeye-400g']->nombre, 'cantidad' => 2, 'precio_unitario' => 74000.00, 'subtotal' => 148000.00, 'area_cocina' => 'caliente', 'estado_cocina' => 'entregado']);
        }

        // Domicilio 1: En camino
        $codP4 = 'MDE-'.Carbon::today()->format('ymd').'-0904';
        if (! Pedido::where('codigo', $codP4)->exists()) {
            $p4 = Pedido::forceCreate([
                'codigo' => $codP4,
                'sucursal_id' => $sucursal->id,
                'mesa_id' => null,
                'cliente_id' => $clientesList[3]->id,
                'usuario_id' => $usersMap['cajero@restomaster.com']->id,
                'mesero_id' => $usersMap['cajero@restomaster.com']->id,
                'repartidor_id' => $usersMap['delivery@restomaster.com']->id,
                'nombre_cliente' => $clientesList[3]->nombre,
                'telefono_cliente' => $clientesList[3]->telefono,
                'direccion_delivery' => 'Calle 10 # 35-12, Laureles, Medellín',
                'tipo' => 'delivery',
                'estado' => 'en_cocina',
                'estado_delivery' => 'en_ruta',
                'subtotal' => 96000.00,
                'total' => 96000.00,
                'created_at' => Carbon::now()->subMinutes(35),
            ]);
            ItemPedido::create(['pedido_id' => $p4->id, 'producto_id' => $productosMap['picada-criolla-restomaster']->id, 'nombre_producto' => $productosMap['picada-criolla-restomaster']->nombre, 'cantidad' => 1, 'precio_unitario' => 54000.00, 'subtotal' => 54000.00, 'area_cocina' => 'caliente', 'estado_cocina' => 'entregado']);
            ItemPedido::create(['pedido_id' => $p4->id, 'producto_id' => $productosMap['colombiana-la-nuestra-botella']->id, 'nombre_producto' => $productosMap['colombiana-la-nuestra-botella']->nombre, 'cantidad' => 2, 'precio_unitario' => 8000.00, 'subtotal' => 16000.00, 'area_cocina' => 'barra', 'estado_cocina' => 'entregado']);
        }

        // Nota Crédito Histórica
        NotaCredito::firstOrCreate(
            ['numero_nc' => 'NC-2026-001'],
            [
                'pedido_id' => $p3->id,
                'sucursal_id' => $sucursal->id,
                'monto' => 39000.00,
                'motivo' => 'error_cargo',
                'descripcion' => 'Plato digitado por duplicado en mesa por mesero',
                'autorizado_por' => $usersMap['gerente@restomaster.com']->id,
                'created_at' => Carbon::now()->subDays(5),
            ]
        );

        // Reserva de hoy
        $reservaHoy = Reserva::firstOrCreate(
            ['fecha' => Carbon::today()->format('Y-m-d'), 'hora_llegada' => '20:00:00'],
            [
                'sucursal_id' => $sucursal->id,
                'cliente_id' => $clientesList[0]->id,
                'nombre_contacto' => $clientesList[0]->nombre,
                'telefono_contacto' => $clientesList[0]->telefono,
                'email_contacto' => $clientesList[0]->email,
                'personas' => 4,
                'duracion_min' => 120,
                'estado' => 'confirmada',
                'origen' => 'sistema',
                'anticipo' => 100000.00,
                'token_publico' => Str::random(40),
                'created_by' => $usersMap['gerente@restomaster.com']->id,
                'notas' => 'Reserva VIP terraza: traer champaña de bienvenida',
                'created_at' => Carbon::now()->subDays(1),
            ]
        );

        DB::table('reserva_mesa')->insertOrIgnore([
            'reserva_id' => $reservaHoy->id,
            'mesa_id' => $mesas[3]->id,
        ]);

        $this->command?->info('✓ Seeder Maestro Colombia · Medellín finalizado con éxito.');
    }
}
