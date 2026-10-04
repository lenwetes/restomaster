<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VipInvitacion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClubVipDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sucursal = Sucursal::first() ?? Sucursal::create([
            'nombre' => 'RestoMaster Provenza',
            'direccion' => 'Cra 35 # 8A-12, Medellín',
            'telefono' => '+573001234567',
            'activo' => true,
        ]);

        $admin = User::first();
        $producto = Producto::first();

        // 1. Promociones Exclusivas VIP para Demo
        Promocion::updateOrCreate(
            ['slug' => 'copa-vino-autor-vip'],
            [
                'titulo' => 'Copa de Vino de Autor o Postre de Cortesía',
                'subtitulo' => 'Beneficio Exclusivo Miembros Club VIP',
                'descripcion' => 'Exclusivo para miembros activos del Club VIP RestoMaster en salón.',
                'tipo_beneficio' => 'cortesia',
                'descuento_porcentaje' => 100,
                'aplica_salon' => true,
                'aplica_delivery' => false,
                'mostrar_en_portada' => true,
                'activo' => true,
                'fecha_inicio' => now()->subMonth(),
                'fecha_fin' => now()->addMonths(6),
            ]
        );

        Promocion::updateOrCreate(
            ['slug' => 'degustacion-maridado-20-vip'],
            [
                'titulo' => '20% OFF en Menú de Degustación Maridado',
                'subtitulo' => 'Martes a Jueves · Club VIP',
                'descripcion' => 'Válido de martes a jueves para socios VIP y hasta 3 acompañantes.',
                'tipo_beneficio' => 'descuento_porcentaje',
                'descuento_porcentaje' => 20,
                'aplica_salon' => true,
                'aplica_delivery' => true,
                'mostrar_en_portada' => true,
                'activo' => true,
                'fecha_inicio' => now()->subMonth(),
                'fecha_fin' => now()->addMonths(6),
            ]
        );

        // 2. Catálogo de 20 Clientes con Diferentes Estados VIP
        $personas = [
            // 6 VIP Activos
            [
                'nombre' => 'Alejandro Restrepo Botero',
                'email' => 'alejandro.restrepo@demo.com',
                'telefono' => '+573105550001',
                'fecha_nacimiento' => Carbon::now()->subYears(34)->setMonth(now()->month)->setDay(15), // Cumple este mes
                'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
                'tier' => Cliente::TIER_VIP,
                'vip_desde' => now()->subMonths(3),
                'consumo' => 1850000,
                'pedidos' => 6,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Valeria Gómez Echeverri',
                'email' => 'valeria.gomez@demo.com',
                'telefono' => '+573105550002',
                'fecha_nacimiento' => Carbon::now()->subYears(29)->setMonth(now()->month)->setDay(22), // Cumple este mes
                'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
                'tier' => Cliente::TIER_VIP,
                'vip_desde' => now()->subMonths(2),
                'consumo' => 2400000,
                'pedidos' => 8,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Santiago Montoya Uribe',
                'email' => 'santiago.montoya@demo.com',
                'telefono' => '+573105550003',
                'fecha_nacimiento' => '1985-04-12',
                'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
                'tier' => Cliente::TIER_VIP,
                'vip_desde' => now()->subMonths(4),
                'consumo' => 1200000,
                'pedidos' => 4,
                'autoriza_wa' => true,
                'autoriza_mail' => false, // Solo WA
            ],
            [
                'nombre' => 'Camila Arango Londoño',
                'email' => 'camila.arango@demo.com',
                'telefono' => '+573105550004',
                'fecha_nacimiento' => '1992-09-05',
                'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
                'tier' => Cliente::TIER_VIP,
                'vip_desde' => now()->subMonth(),
                'consumo' => 950000,
                'pedidos' => 3,
                'autoriza_wa' => false, // Solo Email
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Juan Pablo Vélez Gil',
                'email' => 'juanp.velez@demo.com',
                'telefono' => '+573105550005',
                'fecha_nacimiento' => '1988-11-20',
                'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
                'tier' => Cliente::TIER_VIP,
                'vip_desde' => now()->subDays(45),
                'consumo' => 1600000,
                'pedidos' => 5,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Mariana Posada Correa',
                'email' => 'mariana.posada@demo.com',
                'telefono' => '+573105550006',
                'fecha_nacimiento' => '1995-07-08',
                'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
                'tier' => Cliente::TIER_VIP,
                'vip_desde' => now()->subDays(20),
                'consumo' => 780000,
                'pedidos' => 3,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],

            // 5 Elegibles (Superan $500.000 en 60 días)
            [
                'nombre' => 'Esteban Saldarriaga',
                'email' => 'esteban.salda@demo.com',
                'telefono' => '+573105550007',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 890000,
                'pedidos' => 4,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Natalia Cárdenas Rico',
                'email' => 'natalia.cardenas@demo.com',
                'telefono' => '+573105550008',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 650000,
                'pedidos' => 3,
                'autoriza_wa' => true,
                'autoriza_mail' => false,
            ],
            [
                'nombre' => 'Daniel Ochoa Jaramillo',
                'email' => 'daniel.ochoa@demo.com',
                'telefono' => '+573105550009',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 720000,
                'pedidos' => 3,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Isabela Henao Mejía',
                'email' => 'isabela.henao@demo.com',
                'telefono' => '+573105550010',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 540000,
                'pedidos' => 2,
                'autoriza_wa' => false,
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Felipe Trujillo Bedoya',
                'email' => 'felipe.trujillo@demo.com',
                'telefono' => '+573105550011',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 610000,
                'pedidos' => 3,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],

            // 3 Invitados (Invitación enviada)
            [
                'nombre' => 'Sebastián Quintero',
                'email' => 'sebastian.q@demo.com',
                'telefono' => '+573105550012',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_INVITADO,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 820000,
                'pedidos' => 3,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
                'invitacion_tipo' => 'vigente',
            ],
            [
                'nombre' => 'Carolina Duque Piedrahita',
                'email' => 'carolina.duque@demo.com',
                'telefono' => '+573105550013',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_INVITADO,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 740000,
                'pedidos' => 3,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
                'invitacion_tipo' => 'vigente',
            ],
            [
                'nombre' => 'Mateo Villegas Zuluaga',
                'email' => 'mateo.villegas@demo.com',
                'telefono' => '+573105550014',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_INVITADO,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 580000,
                'pedidos' => 2,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
                'invitacion_tipo' => 'expirada',
            ],

            // 2 Pendientes de Aprobación (Formulario enviado por el cliente)
            [
                'nombre' => 'Paula Andrea Morales',
                'email' => 'paula.morales@demo.com',
                'telefono' => '+573105550015',
                'fecha_nacimiento' => '1990-03-18',
                'vip_estado' => Cliente::VIP_ESTADO_PENDIENTE,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 990000,
                'pedidos' => 4,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Andrés Felipe Castaño',
                'email' => 'andres.castano@demo.com',
                'telefono' => '+573105550016',
                'fecha_nacimiento' => '1987-12-04',
                'vip_estado' => Cliente::VIP_ESTADO_PENDIENTE,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => null,
                'consumo' => 840000,
                'pedidos' => 3,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],

            // 1 Suspendido
            [
                'nombre' => 'Nicolás Rincón Gil',
                'email' => 'nicolas.rincon@demo.com',
                'telefono' => '+573105550017',
                'fecha_nacimiento' => '1982-06-25',
                'vip_estado' => Cliente::VIP_ESTADO_SUSPENDIDO,
                'tier' => Cliente::TIER_FRECUENTE,
                'vip_desde' => now()->subMonths(5),
                'consumo' => 1100000,
                'pedidos' => 4,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],

            // 3 Normales cerca del tope ($350.000 - $480.000)
            [
                'nombre' => 'David Bermúdez Cano',
                'email' => 'david.bermudez@demo.com',
                'telefono' => '+573105550018',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_NINGUNO,
                'tier' => Cliente::TIER_OCASIONAL,
                'vip_desde' => null,
                'consumo' => 480000,
                'pedidos' => 2,
                'autoriza_wa' => true,
                'autoriza_mail' => true,
            ],
            [
                'nombre' => 'Laura Vanessa Rojas',
                'email' => 'laura.rojas@demo.com',
                'telefono' => '+573105550019',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_NINGUNO,
                'tier' => Cliente::TIER_OCASIONAL,
                'vip_desde' => null,
                'consumo' => 390000,
                'pedidos' => 1,
                'autoriza_wa' => true,
                'autoriza_mail' => false,
            ],
            [
                'nombre' => 'Jorge Mario Agudelo',
                'email' => 'jorge.agudelo@demo.com',
                'telefono' => '+573105550020',
                'fecha_nacimiento' => null,
                'vip_estado' => Cliente::VIP_ESTADO_NINGUNO,
                'tier' => Cliente::TIER_OCASIONAL,
                'vip_desde' => null,
                'consumo' => 320000,
                'pedidos' => 1,
                'autoriza_wa' => false,
                'autoriza_mail' => false,
            ],
        ];

        foreach ($personas as $idx => $p) {
            $cliente = Cliente::updateOrCreate(
                ['email' => $p['email']],
                [
                    'nombre' => $p['nombre'],
                    'telefono' => $p['telefono'],
                    'fecha_nacimiento' => $p['fecha_nacimiento'],
                    'password' => bcrypt('password'),
                    'tier' => $p['tier'],
                    'vip_estado' => $p['vip_estado'],
                    'vip_elegible_at' => in_array($p['vip_estado'], [Cliente::VIP_ESTADO_ELEGIBLE, Cliente::VIP_ESTADO_INVITADO, Cliente::VIP_ESTADO_PENDIENTE, Cliente::VIP_ESTADO_ACTIVO]) ? now()->subDays(15) : null,
                    'vip_desde' => $p['vip_desde'],
                    'vip_aprobado_por' => $p['vip_estado'] === Cliente::VIP_ESTADO_ACTIVO ? $admin?->id : null,
                    'puntos_fidelidad' => (int) ($p['consumo'] / 1000),
                    'total_gastado' => $p['consumo'],
                    'visitas_count' => $p['pedidos'],
                    'activo' => true,
                    'acepta_tratamiento_datos' => true,
                    'fecha_autorizacion_datos' => now()->subMonths(2),
                    'autoriza_whatsapp' => $p['autoriza_wa'],
                    'autoriza_email' => $p['autoriza_mail'],
                ]
            );

            // Generar pedidos pagados reales en la ventana de 60 días para respaldar el consumo si no existen aún
            $pedidosExistentes = $cliente->pedidos()->where('codigo', 'like', 'DEMO-VIP-%')->count();
            if ($pedidosExistentes < $p['pedidos']) {
                $montoPorPedido = (float) ($p['consumo'] / max(1, $p['pedidos']));
                for ($i = $pedidosExistentes; $i < $p['pedidos']; $i++) {
                    $fechaPedido = now()->subDays(rand(5, 55));
                    $pedido = Pedido::create([
                        'codigo' => 'DEMO-VIP-'.strtoupper(Str::random(6)),
                        'cliente_id' => $cliente->id,
                        'sucursal_id' => $sucursal->id,
                        'usuario_id' => $admin?->id,
                        'tipo' => 'mesa',
                        'estado' => 'pagado',
                        'subtotal' => $montoPorPedido * 0.9,
                        'impuestos' => $montoPorPedido * 0.1,
                        'total' => $montoPorPedido,
                        'metodo_pago' => 'tarjeta',
                        'created_at' => $fechaPedido,
                        'updated_at' => $fechaPedido,
                    ]);

                    if ($producto) {
                        ItemPedido::create([
                            'pedido_id' => $pedido->id,
                            'producto_id' => $producto->id,
                            'nombre_producto' => $producto->nombre,
                            'cantidad' => 1,
                            'precio_unitario' => $montoPorPedido,
                            'subtotal' => $montoPorPedido,
                            'area_cocina' => 'sushi',
                            'estado_cocina' => 'entregado',
                        ]);
                    }
                }
            }

            // Invitaciones si aplica
            if (isset($p['invitacion_tipo'])) {
                VipInvitacion::updateOrCreate(
                    ['cliente_id' => $cliente->id],
                    [
                        'token_hash' => hash('sha256', Str::random(40)),
                        'canal' => 'whatsapp',
                        'enviada_por' => $admin?->id,
                        'expira_at' => $p['invitacion_tipo'] === 'expirada' ? now()->subDay() : now()->addDays(5),
                        'estado' => $p['invitacion_tipo'] === 'expirada' ? VipInvitacion::ESTADO_EXPIRADA : VipInvitacion::ESTADO_VIGENTE,
                    ]
                );
            }
        }
    }
}
