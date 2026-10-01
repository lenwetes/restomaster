<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\FacturaElectronica;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\PlantillaTurno;
use App\Models\Producto;
use App\Models\ProgramacionSemanal;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\TurnoMeseroSemana;
use App\Models\User;
use App\Models\Zona;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OperacionesSemanaCompletaSeeder extends Seeder
{
    /**
     * Siembra operaciones comerciales realistas de una semana completa:
     * - 7 días de turnos de caja (cerrados históricamente + turnos abiertos hoy).
     * - Movimientos de caja (ingresos, egresos de caja menor, retiros a bóveda).
     * - Pedidos en todas las etapas operativas (KDS, salón, delivery, cobro pendiente).
     * - Facturas electrónicas con CUFE y QR oficial.
     * - Reservas de mesa para toda la semana.
     * - Programación semanal completa de turnos de meseros por zonas y descansos.
     */
    public function run(): void
    {
        $sucursal = Sucursal::first();
        if (! $sucursal) {
            $this->command?->error('No se encontró sucursal para sembrar operaciones.');

            return;
        }

        $cajaPrincipal = Caja::firstOrCreate(
            ['codigo' => 'CAJ-01'],
            ['sucursal_id' => $sucursal->id, 'nombre' => 'Caja Principal Salón', 'activa' => true]
        );

        $cajaBarra = Caja::firstOrCreate(
            ['codigo' => 'CAJ-02'],
            ['sucursal_id' => $sucursal->id, 'nombre' => 'Caja Barra & Terraza', 'activa' => true]
        );

        // Usuarios clave
        $admin = User::where('email', 'admin@restomaster.com')->first();
        $cajero1 = User::where('email', 'cajero@restomaster.com')->first();
        $cajero2 = User::where('email', 'mariana.caja@restomaster.com')->first() ?? $cajero1;
        $repartidor = User::where('email', 'delivery@restomaster.com')->first() ?? $admin;

        // Meseros
        $meseros = User::whereHas('role', fn ($q) => $q->where('slug', 'mesero'))->get();
        if ($meseros->isEmpty()) {
            $meseros = User::where('email', 'like', '%mesero%')->get();
        }
        $primerMesero = $meseros->first() ?? $admin;

        // Mesas y Productos
        $mesas = Mesa::where('sucursal_id', $sucursal->id)->get();
        $productos = Producto::where('activo', true)->get();
        $clientes = Cliente::where('activo', true)->get();
        $zonas = Zona::where('sucursal_id', $sucursal->id)->get();

        if ($productos->isEmpty() || $mesas->isEmpty()) {
            $this->command?->warn('Se requieren productos y mesas para generar las comandas.');

            return;
        }

        $this->command?->info('Sembrando operaciones comerciales de los últimos 7 días...');

        $contadorCodigo = 200;

        // =========================================================================
        // 1. SIEMBRA DE LOS 6 DÍAS ANTERIORES (TURNOS CERRADOS, MOVIMIENTOS Y VENTAS)
        // =========================================================================
        for ($diaOffset = 6; $diaOffset >= 1; $diaOffset--) {
            $fechaDia = Carbon::today()->subDays($diaOffset);

            // Turno de Caja Cerrado
            $montoInicial = 200000.00;
            $turno = TurnoCaja::create([
                'caja_id' => $cajaPrincipal->id,
                'user_id' => $cajero1?->id ?? $admin->id,
                'monto_inicial' => $montoInicial,
                'estado' => 'cerrado',
                'apertura_en' => (clone $fechaDia)->setTime(11, 30),
                'cierre_en' => (clone $fechaDia)->setTime(23, 15),
                'total_ingresos' => 50000.00,
                'total_egresos' => 62000.00,
                'total_retiros' => 400000.00,
                'total_ventas_efectivo' => 650000.00,
                'total_ventas_tarjeta' => 980000.00,
                'total_ventas_transferencia' => 220000.00,
                'monto_esperado_efectivo' => 438000.00,
                'monto_real_efectivo' => 438000.00,
                'diferencia' => 0.00,
                'notas_apertura' => 'Base apertura mediodía $200.000 COP',
                'notas_cierre' => 'Cierre de turno cuadrado sin novedades ni descuadres.',
            ]);

            // Movimiento 1: Ingreso de efectivo (base adicional de cambio)
            MovimientoCaja::create([
                'turno_caja_id' => $turno->id,
                'user_id' => $cajero1?->id ?? $admin->id,
                'tipo' => 'ingreso',
                'concepto' => 'Adición de base en billetes de baja denominación para cambio',
                'monto' => 50000.00,
                'metodo_pago' => 'efectivo',
                'numero_comprobante' => 'ING-'.date('Ymd', $fechaDia->timestamp).'-01',
                'created_at' => (clone $fechaDia)->setTime(12, 10),
            ]);

            // Movimiento 2: Egreso de caja menor (compra urgente)
            MovimientoCaja::create([
                'turno_caja_id' => $turno->id,
                'user_id' => $cajero1?->id ?? $admin->id,
                'tipo' => 'egreso',
                'concepto' => 'Compra urgente en supermercado: Limones criollos y hielo gourmet',
                'monto' => 27000.00,
                'metodo_pago' => 'efectivo',
                'numero_comprobante' => 'EGR-'.date('Ymd', $fechaDia->timestamp).'-01',
                'autorizado_por' => 'Gerencia',
                'created_at' => (clone $fechaDia)->setTime(15, 20),
            ]);

            // Movimiento 3: Egreso propinas pagadas
            MovimientoCaja::create([
                'turno_caja_id' => $turno->id,
                'user_id' => $cajero1?->id ?? $admin->id,
                'tipo' => 'egreso',
                'concepto' => 'Liquidación parcial de propinas en efectivo al equipo de servicio',
                'monto' => 35000.00,
                'metodo_pago' => 'efectivo',
                'numero_comprobante' => 'EGR-'.date('Ymd', $fechaDia->timestamp).'-02',
                'autorizado_por' => 'Administración',
                'created_at' => (clone $fechaDia)->setTime(17, 00),
            ]);

            // Movimiento 4: Retiro a bóveda
            MovimientoCaja::create([
                'turno_caja_id' => $turno->id,
                'user_id' => $cajero1?->id ?? $admin->id,
                'tipo' => 'retiro',
                'concepto' => 'Retiro parcial de seguridad para consignación en bóveda principal',
                'monto' => 400000.00,
                'metodo_pago' => 'efectivo',
                'numero_comprobante' => 'RET-'.date('Ymd', $fechaDia->timestamp).'-01',
                'autorizado_por' => 'Gerente de Operaciones',
                'created_at' => (clone $fechaDia)->setTime(21, 30),
            ]);

            // Pedidos Históricos del Día (8 pedidos por día)
            $pedidosPorDia = 8;
            for ($p = 0; $p < $pedidosPorDia; $p++) {
                $contadorCodigo++;
                $hora = ($p < 4) ? rand(12, 14) : rand(19, 22);
                $minuto = rand(10, 50);
                $fechaPedido = (clone $fechaDia)->setTime($hora, $minuto);

                $cliente = $clientes->isNotEmpty() ? $clientes->random() : null;
                $mesero = $meseros->isNotEmpty() ? $meseros->random() : $primerMesero;
                $mesa = $mesas->random();
                $esDelivery = ($p === 7);

                $metodos = ['efectivo', 'tarjeta', 'tarjeta', 'wompi', 'bold'];
                $metodo = $metodos[array_rand($metodos)];

                $platosMuestra = $productos->random(min(3, $productos->count()));
                $subtotal = 0;
                $itemsData = [];

                foreach ($platosMuestra as $prod) {
                    $cant = rand(1, 2);
                    $sub = (float) $prod->precio * $cant;
                    $subtotal += $sub;

                    $itemsData[] = [
                        'producto_id' => $prod->id,
                        'nombre_producto' => $prod->nombre,
                        'cantidad' => $cant,
                        'precio_unitario' => $prod->precio,
                        'subtotal' => $sub,
                        'area_cocina' => $prod->area_cocina ?? 'caliente',
                        'estado_cocina' => 'servido',
                        'iniciado_en' => (clone $fechaPedido)->addMinutes(2),
                        'listo_en' => (clone $fechaPedido)->addMinutes(15),
                    ];
                }

                $propina = round($subtotal * 0.10, 0);
                $total = $subtotal + $propina;
                $codigo = 'ORD-'.date('Ymd', $fechaPedido->timestamp).'-'.str_pad($contadorCodigo, 4, '0', STR_PAD_LEFT);

                $pedido = Pedido::create([
                    'codigo' => $codigo,
                    'tipo' => $esDelivery ? 'delivery' : 'mesa',
                    'estado' => 'pagado',
                    'sucursal_id' => $sucursal->id,
                    'mesa_id' => $esDelivery ? null : $mesa->id,
                    'usuario_id' => $cajero1?->id ?? $admin->id,
                    'mesero_id' => $mesero->id,
                    'repartidor_id' => $esDelivery ? $repartidor->id : null,
                    'turno_caja_id' => $turno->id,
                    'nombre_cliente' => $cliente?->nombre ?? 'Cliente Gourmet',
                    'telefono_cliente' => $cliente?->telefono ?? '3001234567',
                    'cliente_id' => $cliente?->id,
                    'direccion_delivery' => $esDelivery ? 'Calle 10 # 35-20, Poblado' : null,
                    'estado_delivery' => $esDelivery ? 'entregado' : null,
                    'subtotal' => $subtotal,
                    'descuento' => 0,
                    'total' => $total,
                    'propina' => $propina,
                    'porcentaje_propina' => 10.0,
                    'metodo_pago' => $metodo,
                    'monto_pagado' => $total,
                    'monto_pago_efectivo' => $metodo === 'efectivo' ? $total : 0,
                    'monto_pago_tarjeta' => in_array($metodo, ['tarjeta', 'wompi', 'bold']) ? $total : 0,
                    'cambio' => 0,
                    'pagado_en' => (clone $fechaPedido)->addMinutes(50),
                    'created_at' => $fechaPedido,
                    'updated_at' => (clone $fechaPedido)->addMinutes(50),
                ]);

                foreach ($itemsData as $it) {
                    ItemPedido::create(array_merge($it, ['pedido_id' => $pedido->id]));
                }

                // Generar Factura Electrónica POS para 1 de cada 2 pedidos
                if ($p % 2 === 0) {
                    $prefijo = 'POS';
                    $consecutivo = 10000 + $contadorCodigo;
                    $cufe = hash('sha384', "{$prefijo}-{$consecutivo}{$fechaPedido->format('Y-m-d')}{$total}901234567222222222222fc8eac422eba16e22ffd8c6f94b3f40a6e38162c2");

                    FacturaElectronica::create([
                        'pedido_id' => $pedido->id,
                        'sucursal_id' => $sucursal->id,
                        'tipo_documento' => 'pos_electronico',
                        'prefijo' => $prefijo,
                        'consecutivo' => $consecutivo,
                        'numero_factura' => "{$prefijo}-{$consecutivo}",
                        'cufe' => $cufe,
                        'qr_cadena' => "NumFac:{$prefijo}-{$consecutivo}&FecFac:{$fechaPedido->format('Y-m-d')}&ValFac:{$total}&CUFE:{$cufe}",
                        'estado' => 'emitida',
                        'total' => $total,
                        'impuesto' => round($total * 0.08, 2),
                        'cliente_nit' => $cliente?->identificacion ?? '222222222222',
                        'cliente_nombre' => $cliente?->nombre ?? 'Consumidor Final',
                        'proveedor_tecnologico' => 'factus',
                        'emitida_en' => (clone $fechaPedido)->addMinutes(50),
                    ]);
                }
            }
        }

        // =========================================================================
        // 2. OPERACIÓN EN VIVO DE HOY (TURNOS ABIERTOS, MESAS Y COMANDAS ACTIVAS)
        // =========================================================================
        $this->command?->info('Sembrando turnos abiertos y comandas en vivo de hoy...');

        // Turno 1 en Caja Principal (Abierto)
        $turnoHoy = TurnoCaja::where('caja_id', $cajaPrincipal->id)->where('estado', 'abierto')->first();
        if (! $turnoHoy) {
            $turnoHoy = TurnoCaja::create([
                'caja_id' => $cajaPrincipal->id,
                'user_id' => $cajero1?->id ?? $admin->id,
                'monto_inicial' => 250000.00,
                'monto_esperado_efectivo' => 250000.00,
                'estado' => 'abierto',
                'apertura_en' => Carbon::today()->setTime(11, 00),
                'notas_apertura' => 'Turno Activo Hoy - Base $250.000 COP en efectivo',
            ]);
        }

        // Turno 2 en Caja Barra (Abierto)
        $turnoBarraHoy = TurnoCaja::where('caja_id', $cajaBarra->id)->where('estado', 'abierto')->first();
        if (! $turnoBarraHoy) {
            $turnoBarraHoy = TurnoCaja::create([
                'caja_id' => $cajaBarra->id,
                'user_id' => $cajero2?->id ?? $admin->id,
                'monto_inicial' => 150000.00,
                'monto_esperado_efectivo' => 150000.00,
                'estado' => 'abierto',
                'apertura_en' => Carbon::today()->setTime(11, 30),
                'notas_apertura' => 'Turno Activo Hoy en Barra & Terraza - Base $150.000 COP',
            ]);
        }

        // Movimientos de hoy en Caja Principal
        MovimientoCaja::create([
            'turno_caja_id' => $turnoHoy->id,
            'user_id' => $cajero1?->id ?? $admin->id,
            'tipo' => 'ingreso',
            'concepto' => 'Cobro anticipo en efectivo para reserva evento noche',
            'monto' => 150000.00,
            'metodo_pago' => 'efectivo',
            'numero_comprobante' => 'ING-HOY-01',
            'created_at' => now()->subHours(2),
        ]);

        MovimientoCaja::create([
            'turno_caja_id' => $turnoHoy->id,
            'user_id' => $cajero1?->id ?? $admin->id,
            'tipo' => 'egreso',
            'concepto' => 'Compra de hielo gourmet y servilletas de barra',
            'monto' => 22000.00,
            'metodo_pago' => 'efectivo',
            'numero_comprobante' => 'EGR-HOY-01',
            'autorizado_por' => 'Gerencia',
            'created_at' => now()->subHour(),
        ]);

        // COMANDAS ACTIVAS EN VIVO DE HOY:
        $platoCarnes = $productos->firstWhere('slug', 'bife-de-chorizo-angus-350g') ?? $productos->first();
        $platoEntrada = $productos->firstWhere('slug', 'carpaccio-de-res-trufado') ?? $productos->skip(1)->first() ?? $productos->first();
        $platoBurger = $productos->firstWhere('slug', 'restomaster-burger-master') ?? $productos->skip(2)->first() ?? $productos->first();
        $bebidaBarra = $productos->firstWhere('area_cocina', 'barra') ?? $productos->last();

        // 1. Mesa 1: Ocupada con comanda recién enviada a cocina (KDS)
        $m1 = $mesas->firstWhere('numero', '1') ?? $mesas->first();
        $m1->update(['estado' => 'ocupada']);

        $pedCocina = Pedido::create([
            'codigo' => 'ORD-HOY-101',
            'tipo' => 'mesa',
            'estado' => 'en_cocina',
            'sucursal_id' => $sucursal->id,
            'mesa_id' => $m1->id,
            'usuario_id' => $primerMesero->id,
            'mesero_id' => $primerMesero->id,
            'turno_caja_id' => $turnoHoy->id,
            'nombre_cliente' => 'Familia Gómez',
            'subtotal' => ($platoCarnes->precio * 2) + $bebidaBarra->precio,
            'total' => (($platoCarnes->precio * 2) + $bebidaBarra->precio) * 1.10,
            'propina' => (($platoCarnes->precio * 2) + $bebidaBarra->precio) * 0.10,
            'notas' => 'Cortes término 3/4. Sin cebolla en las salsas.',
            'created_at' => now()->subMinutes(12),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedCocina->id,
            'producto_id' => $platoCarnes->id,
            'nombre_producto' => $platoCarnes->nombre,
            'cantidad' => 2,
            'precio_unitario' => $platoCarnes->precio,
            'subtotal' => $platoCarnes->precio * 2,
            'area_cocina' => 'caliente',
            'estado_cocina' => 'en_preparacion',
            'iniciado_en' => now()->subMinutes(10),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedCocina->id,
            'producto_id' => $bebidaBarra->id,
            'nombre_producto' => $bebidaBarra->nombre,
            'cantidad' => 2,
            'precio_unitario' => $bebidaBarra->precio,
            'subtotal' => $bebidaBarra->precio * 2,
            'area_cocina' => 'barra',
            'estado_cocina' => 'en_preparacion',
            'iniciado_en' => now()->subMinutes(11),
        ]);

        // 2. Mesa 2: Solicitada vía QR en mesa (esperando toma del mesero)
        $m2 = $mesas->firstWhere('numero', '2') ?? $mesas->skip(1)->first();
        $m2->update(['estado' => 'ocupada']);

        Pedido::create([
            'codigo' => 'ORD-HOY-102',
            'tipo' => 'mesa',
            'estado' => 'solicitado_qr',
            'sucursal_id' => $sucursal->id,
            'mesa_id' => $m2->id,
            'turno_caja_id' => $turnoHoy->id,
            'nombre_cliente' => 'Comensal QR Mesa 2',
            'subtotal' => $platoBurger->precio + $bebidaBarra->precio,
            'total' => $platoBurger->precio + $bebidaBarra->precio,
            'notas' => 'Pedido auto-generado por el cliente desde el código QR de mesa.',
            'created_at' => now()->subMinutes(5),
        ]);

        // 3. Mesa 3: Comanda lista para servir (notificación al mesero)
        $m3 = $mesas->firstWhere('numero', '3') ?? $mesas->skip(2)->first();
        $m3->update(['estado' => 'ocupada']);

        $pedListo = Pedido::create([
            'codigo' => 'ORD-HOY-103',
            'tipo' => 'mesa',
            'estado' => 'listo',
            'sucursal_id' => $sucursal->id,
            'mesa_id' => $m3->id,
            'usuario_id' => $primerMesero->id,
            'mesero_id' => $primerMesero->id,
            'turno_caja_id' => $turnoHoy->id,
            'nombre_cliente' => 'Carlos Restrepo',
            'subtotal' => $platoBurger->precio * 2,
            'total' => ($platoBurger->precio * 2) * 1.10,
            'propina' => ($platoBurger->precio * 2) * 0.10,
            'created_at' => now()->subMinutes(25),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedListo->id,
            'producto_id' => $platoBurger->id,
            'nombre_producto' => $platoBurger->nombre,
            'cantidad' => 2,
            'precio_unitario' => $platoBurger->precio,
            'subtotal' => $platoBurger->precio * 2,
            'area_cocina' => 'caliente',
            'estado_cocina' => 'listo',
            'iniciado_en' => now()->subMinutes(22),
            'listo_en' => now()->subMinutes(2),
        ]);

        // 4. Mesa 4: Pendiente de cobro en Caja (pidió la cuenta)
        $m4 = $mesas->firstWhere('numero', '4') ?? $mesas->skip(3)->first();
        $m4->update(['estado' => 'ocupada']);

        $pedCobro = Pedido::create([
            'codigo' => 'ORD-HOY-104',
            'tipo' => 'mesa',
            'estado' => 'pendiente_cobro',
            'sucursal_id' => $sucursal->id,
            'mesa_id' => $m4->id,
            'usuario_id' => $primerMesero->id,
            'mesero_id' => $primerMesero->id,
            'turno_caja_id' => $turnoHoy->id,
            'nombre_cliente' => 'Valentina Jaramillo',
            'subtotal' => 95000.00,
            'total' => 104500.00,
            'propina' => 9500.00,
            'porcentaje_propina' => 10.0,
            'created_at' => now()->subMinutes(45),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedCobro->id,
            'producto_id' => $platoCarnes->id,
            'nombre_producto' => $platoCarnes->nombre,
            'cantidad' => 1,
            'precio_unitario' => 65000.00,
            'subtotal' => 65000.00,
            'area_cocina' => 'caliente',
            'estado_cocina' => 'servido',
            'iniciado_en' => now()->subMinutes(40),
            'listo_en' => now()->subMinutes(25),
        ]);

        ItemPedido::create([
            'pedido_id' => $pedCobro->id,
            'producto_id' => $platoEntrada->id,
            'nombre_producto' => $platoEntrada->nombre,
            'cantidad' => 1,
            'precio_unitario' => 30000.00,
            'subtotal' => 30000.00,
            'area_cocina' => 'fria',
            'estado_cocina' => 'servido',
            'iniciado_en' => now()->subMinutes(42),
            'listo_en' => now()->subMinutes(35),
        ]);

        // 5. Delivery Activo (En camino con repartidor)
        Pedido::create([
            'codigo' => 'DLV-HOY-201',
            'tipo' => 'delivery',
            'estado' => 'en_proceso',
            'estado_delivery' => 'en_camino',
            'sucursal_id' => $sucursal->id,
            'usuario_id' => $cajero1?->id ?? $admin->id,
            'repartidor_id' => $repartidor->id,
            'turno_caja_id' => $turnoHoy->id,
            'nombre_cliente' => 'Santiago Uribe Arango',
            'telefono_cliente' => '3124058821',
            'direccion_delivery' => 'Carrera 43A # 1Sur-150, Edificio Torre Ónix, Apto 804',
            'subtotal' => 78000.00,
            'costo_envio' => 8000.00,
            'total' => 86000.00,
            'metodo_pago' => 'efectivo',
            'notas' => 'Timbrar en portería y anunciar para entregar en piso 8.',
            'hora_despacho' => now()->subMinutes(18),
            'created_at' => now()->subMinutes(35),
        ]);

        // =========================================================================
        // 3. RESERVAS DE SALÓN PARA TODA LA SEMANA
        // =========================================================================
        $this->command?->info('Sembrando reservas de toda la semana...');

        $reservasSemana = [
            // Pasadas
            [
                'nombre_contacto' => 'Laura Restrepo',
                'telefono_contacto' => '3104589201',
                'fecha' => Carbon::today()->subDays(3),
                'hora_llegada' => '13:00',
                'personas' => 4,
                'estado' => 'completada',
                'anticipo' => 0,
                'mesa_id' => $mesas->first()?->id,
            ],
            [
                'nombre_contacto' => 'Daniela Sofía Ospina',
                'telefono_contacto' => '3001239988',
                'fecha' => Carbon::today()->subDays(1),
                'hora_llegada' => '20:00',
                'personas' => 6,
                'estado' => 'completada',
                'anticipo' => 50000.00,
                'mesa_id' => $mesas->skip(1)->first()?->id,
            ],
            // Hoy
            [
                'nombre_contacto' => 'Andrés Felipe Restrepo',
                'telefono_contacto' => '3004589201',
                'fecha' => Carbon::today(),
                'hora_llegada' => '13:30',
                'personas' => 4,
                'estado' => 'confirmada',
                'anticipo' => 0,
                'notas' => 'Almuerzo familiar cumpleaños. Mesa en salón.',
                'mesa_id' => $m1->id,
            ],
            [
                'nombre_contacto' => 'Valentina Morales Echeverri',
                'telefono_contacto' => '3015528490',
                'fecha' => Carbon::today(),
                'hora_llegada' => '20:00',
                'personas' => 6,
                'estado' => 'confirmada',
                'anticipo' => 100000.00,
                'notas' => 'Cena con amigas en terraza. Anticipo pagado por Nequi.',
                'mesa_id' => $mesas->firstWhere('zona', 'terraza')?->id ?? $m2->id,
            ],
            [
                'nombre_contacto' => 'Federico Gutiérrez Saldarriaga',
                'telefono_contacto' => '3186721904',
                'fecha' => Carbon::today(),
                'hora_llegada' => '21:00',
                'personas' => 8,
                'estado' => 'confirmada',
                'anticipo' => 200000.00,
                'notas' => 'Cena ejecutiva reservada en Salón VIP.',
                'mesa_id' => $mesas->firstWhere('zona', 'vip')?->id ?? $m3->id,
            ],
            // Mañana y fin de semana
            [
                'nombre_contacto' => 'Carolina Duque Botero',
                'telefono_contacto' => '3108294411',
                'fecha' => Carbon::tomorrow(),
                'hora_llegada' => '14:00',
                'personas' => 2,
                'estado' => 'confirmada',
                'anticipo' => 0,
                'notas' => 'Almuerzo en barra de coctelería.',
                'mesa_id' => $mesas->firstWhere('zona', 'barra')?->id ?? $m4->id,
            ],
            [
                'nombre_contacto' => 'Santiago Uribe Arango',
                'telefono_contacto' => '3124058821',
                'fecha' => Carbon::tomorrow()->addDay(),
                'hora_llegada' => '20:30',
                'personas' => 4,
                'estado' => 'pendiente',
                'anticipo' => 50000.00,
                'notas' => 'Cena fin de semana terraza exterior.',
                'mesa_id' => $mesas->firstWhere('zona', 'terraza')?->id ?? $m1->id,
            ],
        ];

        foreach ($reservasSemana as $rData) {
            $mesaId = $rData['mesa_id'];
            unset($rData['mesa_id']);

            $res = Reserva::create(array_merge($rData, [
                'sucursal_id' => $sucursal->id,
                'duracion_min' => 120,
                'origen' => 'whatsapp',
                'token_publico' => Str::random(32),
                'created_by' => $cajero1?->id ?? $admin->id,
            ]));

            if ($mesaId) {
                $res->mesas()->sync([$mesaId]);
            }
        }

        // =========================================================================
        // 4. PROGRAMACIÓN SEMANAL DE MESEROS POR ZONAS (PIZARRA Y MALLA SEMANAL)
        // =========================================================================
        $this->command?->info('Sembrando turnos semanales de meseros por zonas y descansos...');

        $ahora = Carbon::now();
        $semanaIso = $ahora->isoWeek();
        $anio = $ahora->year;

        $programacion = ProgramacionSemanal::firstOrCreate(
            [
                'semana_iso' => $semanaIso,
                'anio' => $anio,
                'sucursal_id' => $sucursal->id,
            ],
            [
                'estado' => ProgramacionSemanal::ESTADO_PUBLICADO,
                'publicado_por' => $admin->id,
                'publicado_en' => now()->startOfWeek(),
            ]
        );

        // Crear plantillas de turno base si no existen
        $plantillaApertura = PlantillaTurno::firstOrCreate(
            ['nombre' => 'Turno Completo Almuerzo & Cena', 'sucursal_id' => $sucursal->id],
            ['hora_inicio' => '11:30', 'hora_fin' => '23:00', 'activo' => true]
        );

        $zonaSalon = $zonas->firstWhere('slug', 'salon') ?? $zonas->first();
        $zonaTerraza = $zonas->firstWhere('slug', 'terraza') ?? $zonas->skip(1)->first() ?? $zonaSalon;
        $zonaBarra = $zonas->firstWhere('slug', 'barra') ?? $zonas->skip(2)->first() ?? $zonaSalon;

        $zonasRotacion = [$zonaSalon, $zonaTerraza, $zonaBarra];

        // Recorrer los 7 días de la semana (Lunes a Domingo)
        $inicioSemana = (clone $ahora)->startOfWeek();
        for ($dia = 0; $dia < 7; $dia++) {
            $fechaDia = (clone $inicioSemana)->addDays($dia);

            foreach ($meseros as $idx => $meseroUser) {
                // Rotación equitativa: cada mesero tiene 1 día de descanso asignado
                $diaDescanso = ($meseroUser->id % 7);
                $esDescanso = ($dia === $diaDescanso);

                $zonaAsignada = $zonasRotacion[($idx + $dia) % count($zonasRotacion)];

                TurnoMeseroSemana::updateOrCreate(
                    [
                        'programacion_semanal_id' => $programacion->id,
                        'user_id' => $meseroUser->id,
                        'fecha' => $fechaDia->format('Y-m-d'),
                    ],
                    [
                        'zona_id' => $esDescanso ? null : $zonaAsignada?->id,
                        'plantilla_turno_id' => $plantillaApertura->id,
                        'es_descanso' => $esDescanso,
                        'confirmado_por_mesero_en' => $esDescanso ? null : now()->subDays(2),
                    ]
                );
            }
        }

        $this->command?->info('✓ Operaciones completas de 7 días sembradas con éxito.');
    }
}
