<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\ItemPedido;
use App\Models\MovimientoPuntos;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roleDelivery = Role::whereIn('slug', ['delivery', 'repartidor'])->first();
        if (! $roleDelivery) {
            $roleDelivery = Role::create([
                'nombre' => 'Repartidor',
                'slug' => 'repartidor',
                'descripcion' => 'Despacho y entrega de pedidos a domicilio',
            ]);
        }

        // Crear motorizados / repartidores de prueba
        $moto1 = User::firstOrCreate(
            ['email' => 'carlos.arango@sushixpress.co'],
            [
                'name' => 'Carlos Mario Arango (Moto Suzuki Gixxer 150)',
                'password' => Hash::make('password'),
                'role_id' => $roleDelivery->id,
                'telefono' => '+57 300 456 7890',
                'activo' => true,
            ]
        );

        $moto2 = User::firstOrCreate(
            ['email' => 'mateo.holguin@sushixpress.co'],
            [
                'name' => 'Mateo Holguín (Yamaha FZ-25)',
                'password' => Hash::make('password'),
                'role_id' => $roleDelivery->id,
                'telefono' => '+57 311 654 3210',
                'activo' => true,
            ]
        );

        // Clientes Gastronómicos VIP y Regulares
        $clientesData = [
            [
                'nombre' => 'Roberto Santander',
                'telefono' => '+57 312 445 9821',
                'email' => 'r.santander@inversiones.cl',
                'documento' => 'CC 71.294.019',
                'tier' => 'black',
                'puntos_fidelidad' => 4825,
                'total_gastado' => 3850000,
                'visitas_count' => 14,
                'alergias' => 'Sin Nuez Moscada · Sin Mariscos crudos en rollos fríos',
                'preferencias' => 'Wagyu 3/4 · Nigiris flameados con salsa tare extra',
                'direccion' => 'Cra 43A # 1Sur-150',
                'referencia_apto' => 'Edificio Torre Sur, Apto 802',
                'barrio_ciudad' => 'El Poblado, Medellín',
                'notas_entrega' => 'Portería 24 horas, llamar al llegar',
            ],
            [
                'nombre' => 'Valeria Restrepo',
                'telefono' => '+57 301 234 5678',
                'email' => 'valeria.restrepo@empresa.com',
                'documento' => 'CC 1.037.645.981',
                'tier' => 'vip',
                'puntos_fidelidad' => 1850,
                'total_gastado' => 1850000,
                'visitas_count' => 8,
                'alergias' => 'Alérgica a la pimienta negra',
                'preferencias' => 'Salmón Premium · Sake Junmai helado',
                'direccion' => 'Calle 10 # 36-14',
                'referencia_apto' => 'Urbanización Provenza Real, Casa 12',
                'barrio_ciudad' => 'Provenza, Medellín',
                'notas_entrega' => 'Dejar con el vigilante Don Pedro',
            ],
            [
                'nombre' => 'Santiago Londoño',
                'telefono' => '+57 314 987 6543',
                'email' => 'santiago.londono@tech.co',
                'documento' => 'CC 98.765.432',
                'tier' => 'gold',
                'puntos_fidelidad' => 850,
                'total_gastado' => 850000,
                'visitas_count' => 5,
                'alergias' => 'Vegetariano estricto (Veggie)',
                'preferencias' => 'Rollos con aguacate, pepino japonés y tofu',
                'direccion' => 'Circular 4ta # 72-10',
                'referencia_apto' => 'Apto 401',
                'barrio_ciudad' => 'Laureles, Medellín',
                'notas_entrega' => 'Timbre 401 directo',
            ],
            [
                'nombre' => 'Catalina Gómez',
                'telefono' => '+57 320 555 1234',
                'email' => 'catalina.gomez@correo.com',
                'documento' => 'CC 1.152.441.200',
                'tier' => 'regular',
                'puntos_fidelidad' => 250,
                'total_gastado' => 250000,
                'visitas_count' => 2,
                'alergias' => null,
                'preferencias' => 'Sushi tradicional y gyozas al vapor',
                'direccion' => 'Calle 25 Sur # 48-120',
                'referencia_apto' => 'Torres de San Marcos, Apto 1104',
                'barrio_ciudad' => 'Envigado',
                'notas_entrega' => 'Subir por ascensor torre B',
            ],
        ];

        foreach ($clientesData as $data) {
            $cliente = Cliente::firstOrCreate(
                ['telefono' => $data['telefono']],
                [
                    'nombre' => $data['nombre'],
                    'email' => $data['email'],
                    'documento' => $data['documento'],
                    'tier' => $data['tier'],
                    'puntos_fidelidad' => $data['puntos_fidelidad'],
                    'total_gastado' => $data['total_gastado'],
                    'visitas_count' => $data['visitas_count'],
                    'alergias' => $data['alergias'],
                    'preferencias' => $data['preferencias'],
                    'activo' => true,
                ]
            );

            if ($cliente->direcciones()->count() === 0) {
                $cliente->direcciones()->create([
                    'etiqueta' => 'Principal',
                    'direccion' => $data['direccion'],
                    'referencia_apto' => $data['referencia_apto'],
                    'barrio_ciudad' => $data['barrio_ciudad'],
                    'telefono_contacto' => $data['telefono'],
                    'notas_entrega' => $data['notas_entrega'],
                    'es_predeterminada' => true,
                ]);
            }

            // Historial de puntos de bienvenida / consumo
            if ($cliente->movimientosPuntos()->count() === 0) {
                MovimientoPuntos::create([
                    'cliente_id' => $cliente->id,
                    'tipo' => 'acumulacion',
                    'puntos' => $data['puntos_fidelidad'],
                    'saldo_anterior' => 0,
                    'saldo_nuevo' => $data['puntos_fidelidad'],
                    'concepto' => 'Bienvenida Club Gourmet y consumos históricos',
                ]);
            }
        }

        // Sembrar pedidos de delivery de ejemplo en vivo
        $valeria = Cliente::where('telefono', '+57 301 234 5678')->first();
        $roberto = Cliente::where('telefono', '+57 312 445 9821')->first();
        $santiago = Cliente::where('telefono', '+57 314 987 6543')->first();
        $productos = Producto::take(4)->get();
        $turnoActivo = TurnoCaja::where('estado', 'abierto')->latest()->first();

        // 1. Pedido Listo en Pase (esperando moto)
        if ($valeria && ! Pedido::where('codigo', 'DLV-408')->exists()) {
            $dir = $valeria->direccionPredeterminada;
            $p1 = Pedido::create([
                'codigo' => 'DLV-408',
                'tipo' => 'delivery',
                'estado' => 'listo',
                'estado_delivery' => 'pendiente',
                'canal_origen' => 'web',
                'cliente_id' => $valeria->id,
                'direccion_id' => $dir?->id,
                'nombre_cliente' => $valeria->nombre,
                'telefono_cliente' => $valeria->telefono,
                'direccion_delivery' => $dir?->direccion_completa,
                'costo_envio' => 8000,
                'subtotal' => 140000,
                'descuento' => 0,
                'total' => 148000,
                'metodo_pago' => 'tarjeta',
                'monto_pagado' => 148000,
                'pagado_en' => now(),
                'turno_caja_id' => $turnoActivo?->id,
            ]);

            if ($productos->count() > 0) {
                ItemPedido::create([
                    'pedido_id' => $p1->id,
                    'producto_id' => $productos[0]->id,
                    'nombre_producto' => $productos[0]->nombre,
                    'precio_unitario' => $productos[0]->precio,
                    'cantidad' => 2,
                    'subtotal' => $productos[0]->precio * 2,
                    'estado_cocina' => 'listo',
                ]);
            }
        }

        // 2. Pedido En Ruta con Carlos Arango (Efectivo contra entrega)
        if ($roberto && ! Pedido::where('codigo', 'DLV-409')->exists()) {
            $dir = $roberto->direccionPredeterminada;
            $p2 = Pedido::create([
                'codigo' => 'DLV-409',
                'tipo' => 'delivery',
                'estado' => 'entregado', // Salio del local
                'estado_delivery' => 'en_ruta',
                'canal_origen' => 'whatsapp',
                'cliente_id' => $roberto->id,
                'direccion_id' => $dir?->id,
                'repartidor_id' => $moto1->id,
                'nombre_cliente' => $roberto->nombre,
                'telefono_cliente' => $roberto->telefono,
                'direccion_delivery' => $dir?->direccion_completa,
                'costo_envio' => 10000,
                'subtotal' => 200000,
                'descuento' => 0,
                'total' => 210000,
                'metodo_pago' => 'efectivo',
                'hora_despacho' => now()->subMinutes(15),
                'turno_caja_id' => $turnoActivo?->id,
                'recaudo_liquidado' => false,
            ]);

            if ($productos->count() > 1) {
                ItemPedido::create([
                    'pedido_id' => $p2->id,
                    'producto_id' => $productos[1]->id,
                    'nombre_producto' => $productos[1]->nombre,
                    'precio_unitario' => $productos[1]->precio,
                    'cantidad' => 3,
                    'subtotal' => $productos[1]->precio * 3,
                    'estado_cocina' => 'listo',
                ]);
            }
        }

        // 3. Pedido Entregado con Mateo Holguín (Efectivo pendiente de liquidación)
        if ($santiago && ! Pedido::where('codigo', 'DLV-410')->exists()) {
            $dir = $santiago->direccionPredeterminada;
            $p3 = Pedido::create([
                'codigo' => 'DLV-410',
                'tipo' => 'delivery',
                'estado' => 'pagado',
                'estado_delivery' => 'entregado',
                'canal_origen' => 'telefono',
                'cliente_id' => $santiago->id,
                'direccion_id' => $dir?->id,
                'repartidor_id' => $moto2->id,
                'nombre_cliente' => $santiago->nombre,
                'telefono_cliente' => $santiago->telefono,
                'direccion_delivery' => $dir?->direccion_completa,
                'costo_envio' => 5000,
                'subtotal' => 80000,
                'descuento' => 0,
                'total' => 85000,
                'metodo_pago' => 'efectivo',
                'monto_pagado' => 100000,
                'cambio' => 15000,
                'pagado_en' => now()->subMinutes(5),
                'hora_despacho' => now()->subMinutes(35),
                'hora_entrega' => now()->subMinutes(5),
                'turno_caja_id' => $turnoActivo?->id,
                'recaudo_liquidado' => false, // Listo para liquidar en caja
            ]);

            if ($productos->count() > 2) {
                ItemPedido::create([
                    'pedido_id' => $p3->id,
                    'producto_id' => $productos[2]->id,
                    'nombre_producto' => $productos[2]->nombre,
                    'precio_unitario' => $productos[2]->precio,
                    'cantidad' => 1,
                    'subtotal' => $productos[2]->precio,
                    'estado_cocina' => 'entregado',
                ]);
            }
        }
    }
}
