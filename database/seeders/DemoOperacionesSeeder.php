<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\CuentaPorPagar;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoOperacionesSeeder extends Seeder
{
    public function run(): void
    {
        $cajero = User::where('email', 'cajero@sushixpress.com')->first();
        $mesero = User::where('email', 'mesero@sushixpress.com')->first();
        $admin = User::where('email', 'admin@sushixpress.com')->first();
        $sucursal = Sucursal::first();

        if (! $cajero || ! $mesero || Pedido::where('codigo', 'ORD-101')->exists()) {
            return;
        }

        $caja = Caja::firstOrCreate(
            ['codigo' => 'CAJ-01'],
            [
                'sucursal_id' => $sucursal?->id,
                'nombre' => 'Caja Principal Salón',
                'activa' => true,
            ]
        );

        // 1. Turno de Caja Abierto
        $turno = TurnoCaja::firstOrCreate(
            ['caja_id' => $caja->id, 'estado' => 'abierto'],
            [
                'user_id' => $cajero->id,
                'monto_inicial' => 200000,
                'monto_esperado_efectivo' => 380000,
                'estado' => 'abierto',
                'apertura_en' => now()->subHours(4),
            ]
        );

        // Mesas y Productos
        $mesas = Mesa::orderBy('numero')->get();
        $productos = Producto::where('activo', true)->get();

        if ($productos->isEmpty() || $mesas->isEmpty()) {
            return;
        }

        $california = $productos->firstWhere('slug', 'california-roll') ?? $productos->first();
        $dragon = $productos->firstWhere('slug', 'dragon-roll') ?? $productos->skip(1)->first() ?? $productos->first();
        $salmon = $productos->firstWhere('slug', 'salmon-nigiri') ?? $productos->skip(2)->first() ?? $productos->first();
        $bebida = $productos->firstWhere('area_cocina', 'barra') ?? $productos->last();

        $cliente1 = Cliente::first();
        $cliente2 = Cliente::skip(1)->first();

        // 2. Pedidos en Mesa Activos (Mesa 1 y Mesa 2 en salón con comandas en KDS)
        $mesa1 = $mesas->first();
        if ($mesa1) {
            $mesa1->update(['estado' => 'ocupada']);

            $pedido1 = Pedido::create([
                'codigo' => 'ORD-101',
                'tipo' => 'mesa',
                'estado' => 'en_cocina',
                'mesa_id' => $mesa1->id,
                'usuario_id' => $mesero->id,
                'cliente_id' => $cliente1?->id,
                'subtotal' => 76000,
                'total' => 76000,
                'turno_caja_id' => $turno->id,
                'notas' => 'Sin wasabi para el cliente de la cabecera',
            ]);

            ItemPedido::create([
                'pedido_id' => $pedido1->id,
                'producto_id' => $california->id,
                'nombre_producto' => $california->nombre,
                'cantidad' => 2,
                'precio_unitario' => $california->precio,
                'subtotal' => $california->precio * 2,
                'area_cocina' => 'sushi',
                'estado_cocina' => 'en_preparacion',
                'iniciado_en' => now()->subMinutes(12),
            ]);

            ItemPedido::create([
                'pedido_id' => $pedido1->id,
                'producto_id' => $bebida->id,
                'nombre_producto' => $bebida->nombre,
                'cantidad' => 2,
                'precio_unitario' => $bebida->precio,
                'subtotal' => $bebida->precio * 2,
                'area_cocina' => 'barra',
                'estado_cocina' => 'listo',
                'listo_en' => now()->subMinutes(5),
            ]);
        }

        $mesa2 = $mesas->skip(1)->first();
        if ($mesa2) {
            $mesa2->update(['estado' => 'ocupada']);

            $pedido2 = Pedido::create([
                'codigo' => 'ORD-102',
                'tipo' => 'mesa',
                'estado' => 'en_cocina',
                'mesa_id' => $mesa2->id,
                'usuario_id' => $mesero->id,
                'cliente_id' => $cliente2?->id,
                'subtotal' => 58000,
                'total' => 58000,
                'turno_caja_id' => $turno->id,
            ]);

            ItemPedido::create([
                'pedido_id' => $pedido2->id,
                'producto_id' => $dragon->id,
                'nombre_producto' => $dragon->nombre,
                'cantidad' => 1,
                'precio_unitario' => $dragon->precio,
                'subtotal' => $dragon->precio,
                'area_cocina' => 'sushi',
                'estado_cocina' => 'pendiente',
            ]);

            ItemPedido::create([
                'pedido_id' => $pedido2->id,
                'producto_id' => $salmon->id,
                'nombre_producto' => $salmon->nombre,
                'cantidad' => 1,
                'precio_unitario' => $salmon->precio,
                'subtotal' => $salmon->precio,
                'area_cocina' => 'sushi',
                'estado_cocina' => 'en_preparacion',
                'iniciado_en' => now()->subMinutes(6),
            ]);
        }

        // 3. Pedidos Pagados del Día (Alimentan Dashboard KPIs)
        $ventasDemo = [
            ['codigo' => 'ORD-095', 'tipo' => 'mostrador', 'subtotal' => 45000, 'total' => 45000, 'metodo' => 'efectivo', 'horas' => 3],
            ['codigo' => 'ORD-096', 'tipo' => 'mesa', 'subtotal' => 115000, 'total' => 115000, 'metodo' => 'tarjeta', 'horas' => 2.5],
            ['codigo' => 'ORD-097', 'tipo' => 'mesa', 'subtotal' => 88000, 'total' => 88000, 'metodo' => 'tarjeta', 'horas' => 2],
            ['codigo' => 'ORD-098', 'tipo' => 'delivery', 'subtotal' => 62000, 'total' => 62000, 'metodo' => 'efectivo', 'horas' => 1.5],
            ['codigo' => 'ORD-099', 'tipo' => 'mesa', 'subtotal' => 135000, 'total' => 135000, 'metodo' => 'tarjeta', 'horas' => 1],
        ];

        foreach ($ventasDemo as $v) {
            $ped = Pedido::create([
                'codigo' => $v['codigo'],
                'tipo' => $v['tipo'],
                'estado' => 'pagado',
                'usuario_id' => $cajero->id,
                'cliente_id' => $cliente1?->id,
                'subtotal' => $v['subtotal'],
                'total' => $v['total'],
                'metodo_pago' => $v['metodo'],
                'monto_pagado' => $v['total'],
                'cambio' => 0,
                'pagado_en' => now()->subHours($v['horas']),
                'turno_caja_id' => $turno->id,
                'created_at' => now()->subHours($v['horas']),
            ]);

            ItemPedido::create([
                'pedido_id' => $ped->id,
                'producto_id' => $california->id,
                'nombre_producto' => $california->nombre,
                'cantidad' => 2,
                'precio_unitario' => $california->precio,
                'subtotal' => $california->precio * 2,
                'area_cocina' => 'sushi',
                'estado_cocina' => 'entregado',
            ]);
        }

        // 4. Reservas de Demostración
        Reserva::firstOrCreate(
            ['nombre_contacto' => 'Santiago Valencia', 'fecha' => now()->toDateString()],
            [
                'sucursal_id' => $sucursal?->id,
                'cliente_id' => $cliente1?->id,
                'telefono_contacto' => '+57 300 123 4567',
                'email_contacto' => 'santiago@example.com',
                'hora_llegada' => '19:30:00',
                'duracion_min' => 90,
                'personas' => 4,
                'estado' => 'confirmada',
                'origen' => 'whatsapp',
                'notas' => 'Celebración de cumpleaños, mesa cerca a ventana.',
                'anticipo' => 50000,
                'token_publico' => Str::random(32),
                'created_by' => $admin?->id,
            ]
        );

        Reserva::firstOrCreate(
            ['nombre_contacto' => 'Camila Restrepo', 'fecha' => now()->toDateString()],
            [
                'sucursal_id' => $sucursal?->id,
                'telefono_contacto' => '+57 301 987 6543',
                'email_contacto' => 'camila@example.com',
                'hora_llegada' => '21:00:00',
                'duracion_min' => 60,
                'personas' => 2,
                'estado' => 'confirmada',
                'origen' => 'manual',
                'anticipo' => 0,
                'token_publico' => Str::random(32),
                'created_by' => $mesero?->id,
            ]
        );

        Reserva::firstOrCreate(
            ['nombre_contacto' => 'Andrés Gómez', 'fecha' => now()->addDay()->toDateString()],
            [
                'sucursal_id' => $sucursal?->id,
                'telefono_contacto' => '+57 312 456 7890',
                'email_contacto' => 'andres.gomez@empresa.com',
                'hora_llegada' => '20:00:00',
                'duracion_min' => 120,
                'personas' => 6,
                'estado' => 'pendiente',
                'origen' => 'web',
                'anticipo' => 0,
                'token_publico' => Str::random(32),
            ]
        );

        // 5. Cuentas por Pagar (CXP)
        CuentaPorPagar::firstOrCreate(
            ['proveedor_nombre' => 'Pescadería del Mar S.A.S.', 'concepto' => 'Lote Salmón Fresco Chileno'],
            [
                'proveedor_nit' => '900.876.543-1',
                'monto_total' => 1250000,
                'saldo_pendiente' => 1250000,
                'fecha_emision' => now()->subDays(4),
                'fecha_vencimiento' => now()->addDays(12),
                'estado' => 'pendiente',
                'notas' => 'Factura crédito a 15 días.',
                'user_id' => $admin?->id,
            ]
        );

        CuentaPorPagar::firstOrCreate(
            ['proveedor_nombre' => 'Insumos de Oriente Ltda.', 'concepto' => 'Arroz Koshihikari y Algas Nori'],
            [
                'proveedor_nit' => '800.123.987-5',
                'monto_total' => 680000,
                'saldo_pendiente' => 340000,
                'fecha_emision' => now()->subDays(15),
                'fecha_vencimiento' => now()->subDays(1),
                'estado' => 'parcial',
                'notas' => 'Abono del 50% realizado.',
                'user_id' => $admin?->id,
            ]
        );
    }
}
