<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\CuentaPorPagar;
use App\Models\FacturaElectronica;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\MovimientoCaja;
use App\Models\MovimientoInventario;
use App\Models\PagoCxp;
use App\Models\Pedido;
use App\Models\PlantillaTurno;
use App\Models\Producto;
use App\Models\ProgramacionSemanal;
use App\Models\Proveedor;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\TurnoMeseroSemana;
use App\Models\User;
use App\Models\Zona;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OperacionesMesCompletoSeeder extends Seeder
{
    /**
     * Siembra operaciones gastronómicas y comerciales completas de UN MES (30 DÍAS):
     * 1. Proveedores colombianos, compras de insumos, CxP y pagos parciales/totales.
     * 2. 30 días de turnos de caja (29 días cerrados y cuadrados + 2 turnos abiertos hoy).
     * 3. Movimientos de caja (ingresos, egresos de caja menor, retiros a bóveda).
     * 4. 150+ pedidos históricos pagados con facturas electrónicas DIAN (CUFE SHA-384 y QR).
     * 5. Comandas activas en vivo de hoy (KDS en cocina, solicitado QR, listo para servir, pendiente cobro, delivery en camino).
     * 6. Reservas para todo el mes (pasadas, hoy y próximas semanas).
     * 7. Malla de turnos y rotación equitativa de 10 meseros colombianos.
     */
    public function run(): void
    {
        $sucursal = Sucursal::first();
        if (! $sucursal) {
            $this->command?->error('No se encontró sucursal para sembrar operaciones.');

            return;
        }

        // Cajas
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
        $gerente = User::where('email', 'gerente@restomaster.com')->first() ?? $admin;
        $cajero1 = User::where('email', 'cajero@restomaster.com')->first() ?? $admin;
        $cajero2 = User::where('email', 'mariana.caja@restomaster.com')->first() ?? $cajero1;
        $repartidor = User::where('email', 'delivery@restomaster.com')->first() ?? $admin;

        // Meseros
        $meseros = User::whereHas('role', fn ($q) => $q->where('slug', 'mesero'))->get();
        if ($meseros->isEmpty()) {
            $meseros = User::where('email', 'like', '%mesero%')->get();
        }
        $primerMesero = $meseros->first() ?? $admin;

        // Mesas, Productos, Clientes, Zonas, Insumos
        $mesas = Mesa::where('sucursal_id', $sucursal->id)->get();
        $productos = Producto::where('activo', true)->get();
        $clientes = Cliente::where('activo', true)->get();
        $zonas = Zona::where('sucursal_id', $sucursal->id)->get();
        $insumos = Insumo::all();

        if ($productos->isEmpty() || $mesas->isEmpty()) {
            $this->command?->warn('Se requieren productos y mesas para generar las operaciones.');

            return;
        }

        $this->command?->info('Sembrando proveedores, compras y cuentas por pagar del mes...');

        // =========================================================================
        // 1. PROVEEDORES, COMPRAS DE INSUMOS Y CUENTAS POR PAGAR (CxP)
        // =========================================================================
        $proveedoresData = [
            [
                'nombre' => 'Carnes Frías San Martín Medellín S.A.S.',
                'nit' => '890.123.456-1',
                'telefono' => '+57 604 444 1122',
                'email' => 'ventas@carnesanmartin.com.co',
                'direccion' => 'Calle 29 # 43A-20, Medellín',
                'contacto' => 'Gustavo Adolfo Pérez',
                'dias_credito' => 30,
                'activo' => true,
            ],
            [
                'nombre' => 'Avícola Los Andes de Antioquia',
                'nit' => '900.876.543-2',
                'telefono' => '+57 604 312 8899',
                'email' => 'pedidos@avicolalosandes.com',
                'direccion' => 'Autopista Norte Km 14, Copacabana',
                'contacto' => 'Marcela Cano Rúa',
                'dias_credito' => 15,
                'activo' => true,
            ],
            [
                'nombre' => 'Lácteos y Quesos El Trébol',
                'nit' => '901.345.678-5',
                'telefono' => '+57 604 555 7711',
                'email' => 'contacto@lacteoseltrebol.com',
                'direccion' => 'Carrera 50 # 52-10, San Pedro de los Milagros',
                'contacto' => 'Julián David Giraldo',
                'dias_credito' => 20,
                'activo' => true,
            ],
            [
                'nombre' => 'Frutas y Verduras Del Campo La Ceja',
                'nit' => '800.654.321-9',
                'telefono' => '+57 314 555 9012',
                'email' => 'delcampo.laceja@agromarket.co',
                'direccion' => 'Vereda San Nicolás, La Ceja, Antioquia',
                'contacto' => 'Nora Elena Tobón',
                'dias_credito' => 8,
                'activo' => true,
            ],
            [
                'nombre' => 'Distribuidora de Bebidas y Licores La 70',
                'nit' => '860.005.224-6',
                'telefono' => '+57 604 260 4050',
                'email' => 'licoresla70@distribuciones.com',
                'direccion' => 'Circular 4 # 70-15, Laureles, Medellín',
                'contacto' => 'Rodrigo Morales Quintero',
                'dias_credito' => 30,
                'activo' => true,
            ],
            [
                'nombre' => 'Empaques Ecológicos del Valle S.A.S.',
                'nit' => '901.554.890-3',
                'telefono' => '+57 310 998 7766',
                'email' => 'info@empaquesecologicos.co',
                'direccion' => 'Zona Industrial Guayabal, Carrera 52 # 14-80',
                'contacto' => 'Sandra Milena Hincapié',
                'dias_credito' => 15,
                'activo' => true,
            ],
        ];

        $proveedoresMap = [];
        foreach ($proveedoresData as $pData) {
            $prov = Proveedor::firstOrCreate(['nit' => $pData['nit']], $pData);
            $proveedoresMap[$prov->nombre] = $prov;
        }

        // Registrar compras semanales a lo largo de los 30 días
        $comprasSemanales = [
            // Semana 1 (hace 26 días)
            [
                'prov' => 'Carnes Frías San Martín Medellín S.A.S.',
                'offset' => 26,
                'factura' => 'FAC-CSM-8910',
                'monto' => 3450000.00,
                'concepto' => 'Lomo Angus de res, bife de chorizo y costillas BBQ',
                'estado' => 'pagado',
                'pago_dias' => 10,
            ],
            [
                'prov' => 'Avícola Los Andes de Antioquia',
                'offset' => 24,
                'factura' => 'FAC-AVI-3310',
                'monto' => 1890000.00,
                'concepto' => 'Pechuga fileteada, muslos y alitas seleccionadas',
                'estado' => 'pagado',
                'pago_dias' => 12,
            ],
            // Semana 2 (hace 19 días)
            [
                'prov' => 'Distribuidora de Bebidas y Licores La 70',
                'offset' => 19,
                'factura' => 'FAC-LIC-9021',
                'monto' => 2480000.00,
                'concepto' => 'Cerveza Club Colombia, BBC Monserrate, Ginebra y Ron Medellín 8 Años',
                'estado' => 'pagado',
                'pago_dias' => 14,
            ],
            [
                'prov' => 'Lácteos y Quesos El Trébol',
                'offset' => 17,
                'factura' => 'FAC-TRE-4450',
                'monto' => 1250000.00,
                'concepto' => 'Queso mozzarella en bloque, parmesano reggiano y crema de leche',
                'estado' => 'pagado',
                'pago_dias' => 7,
            ],
            // Semana 3 (hace 11 días)
            [
                'prov' => 'Carnes Frías San Martín Medellín S.A.S.',
                'offset' => 11,
                'factura' => 'FAC-CSM-9140',
                'monto' => 3800000.00,
                'concepto' => 'Reposición lomos angus, costillas baby back y hamburguesas',
                'estado' => 'parcial',
                'abono' => 2000000.00,
                'pago_dias' => 5,
            ],
            [
                'prov' => 'Frutas y Verduras Del Campo La Ceja',
                'offset' => 9,
                'factura' => 'FAC-CAM-1092',
                'monto' => 980000.00,
                'concepto' => 'Aguacate hass, cebolla morada, tomate chonto y limones criollos',
                'estado' => 'pagado',
                'pago_dias' => 4,
            ],
            // Semana 4 (hace 4 días)
            [
                'prov' => 'Avícola Los Andes de Antioquia',
                'offset' => 4,
                'factura' => 'FAC-AVI-3680',
                'monto' => 2150000.00,
                'concepto' => 'Alitas de pollo x100kg y pechugas frescas para parrilla',
                'estado' => 'pendiente',
            ],
            [
                'prov' => 'Empaques Ecológicos del Valle S.A.S.',
                'offset' => 3,
                'factura' => 'FAC-ECO-7740',
                'monto' => 840000.00,
                'concepto' => 'Cajas de cartón kraft para hamburguesas y bolsas biodegradables para delivery',
                'estado' => 'pendiente',
            ],
            // Ayer
            [
                'prov' => 'Distribuidora de Bebidas y Licores La 70',
                'offset' => 1,
                'factura' => 'FAC-LIC-9411',
                'monto' => 1650000.00,
                'concepto' => 'Licores premium para coctelería y refrescos de salón',
                'estado' => 'pendiente',
            ],
        ];

        foreach ($comprasSemanales as $cData) {
            $prov = $proveedoresMap[$cData['prov']] ?? null;
            $fechaEmision = Carbon::today()->subDays($cData['offset']);
            $fechaVencimiento = (clone $fechaEmision)->addDays($prov?->dias_credito ?? 15);

            $compra = Compra::firstOrCreate(
                ['numero_factura' => $cData['factura']],
                [
                    'proveedor_id' => $prov?->id,
                    'fecha' => $fechaEmision,
                    'subtotal' => $cData['monto'],
                    'forma_pago' => 'credito',
                    'estado' => 'recibido',
                    'user_id' => $gerente->id,
                ]
            );

            // Línea de compra si hay insumos
            $insumoMuestra = $insumos->first();
            if ($insumoMuestra && $compra->wasRecentlyCreated) {
                CompraLinea::create([
                    'compra_id' => $compra->id,
                    'insumo_id' => $insumoMuestra->id,
                    'cantidad' => 25,
                    'costo_unitario' => round($cData['monto'] / 25, 2),
                    'subtotal' => $cData['monto'],
                ]);
            }

            // Cuenta por Pagar
            $saldoPendiente = match ($cData['estado']) {
                'pagado' => 0.00,
                'parcial' => $cData['monto'] - ($cData['abono'] ?? 0.00),
                default => $cData['monto'],
            };

            $cxp = CuentaPorPagar::firstOrCreate(
                ['numero_factura' => $cData['factura']],
                [
                    'proveedor_nombre' => $prov?->nombre ?? $cData['prov'],
                    'proveedor_nit' => $prov?->nit ?? '900.000.000-1',
                    'compra_id' => $compra->id,
                    'concepto' => $cData['concepto'],
                    'monto_total' => $cData['monto'],
                    'saldo_pendiente' => $saldoPendiente,
                    'fecha_emision' => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => $cData['estado'],
                    'user_id' => $gerente->id,
                ]
            );

            // Pagos registrados de CxP
            if ($cData['estado'] === 'pagado') {
                PagoCxp::firstOrCreate(
                    [
                        'cuenta_por_pagar_id' => $cxp->id,
                        'concepto' => 'Pago total factura '.$cData['factura'],
                    ],
                    [
                        'user_id' => $admin->id,
                        'monto' => $cData['monto'],
                        'metodo_pago' => 'transferencia',
                        'fecha_pago' => (clone $fechaEmision)->addDays($cData['pago_dias'] ?? 5),
                    ]
                );
            } elseif ($cData['estado'] === 'parcial' && isset($cData['abono'])) {
                PagoCxp::firstOrCreate(
                    [
                        'cuenta_por_pagar_id' => $cxp->id,
                        'concepto' => 'Abono parcial factura '.$cData['factura'],
                    ],
                    [
                        'user_id' => $admin->id,
                        'monto' => $cData['abono'],
                        'metodo_pago' => 'transferencia',
                        'fecha_pago' => (clone $fechaEmision)->addDays($cData['pago_dias'] ?? 3),
                    ]
                );
            }

            // Movimiento de Inventario por compra
            if ($insumoMuestra) {
                MovimientoInventario::firstOrCreate(
                    ['referencia_documento' => 'MOV-'.$cData['factura']],
                    [
                        'insumo_id' => $insumoMuestra->id,
                        'tipo' => 'compra',
                        'cantidad' => 20,
                        'saldo_anterior' => $insumoMuestra->stock_actual,
                        'saldo_posterior' => $insumoMuestra->stock_actual + 20,
                        'costo_unitario' => round($cData['monto'] / 20, 2),
                        'costo_total' => $cData['monto'],
                        'user_id' => $gerente->id,
                        'motivo' => 'Compra registrada: '.$cData['concepto'],
                        'referencia_documento' => 'MOV-'.$cData['factura'],
                        'created_at' => $fechaEmision,
                    ]
                );
            }
        }

        // =========================================================================
        // 2. TURNOS DE CAJA Y OPERACIONES COMERCIALES DE LOS ÚLTIMOS 29 DÍAS
        // =========================================================================
        $this->command?->info('Sembrando 30 días de turnos de caja, comandas pagadas y facturas...');

        $contadorCodigo = 100;
        $prefijo = 'POS';
        $consecutivo = 5000;

        $historialExiste = TurnoCaja::whereDate('apertura_en', '<', Carbon::today())->exists();

        if (! $historialExiste) {
            for ($diaOffset = 29; $diaOffset >= 1; $diaOffset--) {
                $fechaDia = Carbon::today()->subDays($diaOffset);
                $esFinDeSemana = in_array($fechaDia->dayOfWeekIso, [5, 6, 7], true); // Vie, Sáb, Dom

                $montoInicial = 250000.00;
                // Ventas más altas en fin de semana
                $ventasEfectivo = $esFinDeSemana ? rand(900000, 1500000) : rand(450000, 800000);
                $ventasTarjeta = $esFinDeSemana ? rand(1800000, 3200000) : rand(800000, 1600000);
                $ventasTransfer = $esFinDeSemana ? rand(500000, 1200000) : rand(200000, 600000);

                $ingresoExtra = rand(0, 1) ? 50000.00 : 0.00;
                $egresoMenor = rand(15000, 45000);
                $egresoPropina = rand(30000, 80000);
                $retiroBoveda = (float) (floor($ventasEfectivo * 0.70 / 50000) * 50000); // 70% retirado a bóveda

                $montoEsperado = $montoInicial + $ingresoExtra - $egresoMenor - $egresoPropina - $retiroBoveda + $ventasEfectivo;

                $turno = TurnoCaja::create([
                    'caja_id' => $cajaPrincipal->id,
                    'user_id' => $cajero1->id,
                    'monto_inicial' => $montoInicial,
                    'estado' => 'cerrado',
                    'apertura_en' => (clone $fechaDia)->setTime(11, 30),
                    'cierre_en' => (clone $fechaDia)->setTime(23, 30),
                    'total_ingresos' => $ingresoExtra,
                    'total_egresos' => $egresoMenor + $egresoPropina,
                    'total_retiros' => $retiroBoveda,
                    'total_ventas_efectivo' => $ventasEfectivo,
                    'total_ventas_tarjeta' => $ventasTarjeta,
                    'total_ventas_transferencia' => $ventasTransfer,
                    'monto_esperado_efectivo' => $montoEsperado,
                    'monto_real_efectivo' => $montoEsperado,
                    'diferencia' => 0.00,
                    'notas_apertura' => 'Base apertura mediodía $250.000 COP',
                    'notas_cierre' => 'Cierre de turno cuadrado sin novedades ni descuadres.',
                ]);

                // Movimiento 1: Adición de base
                if ($ingresoExtra > 0) {
                    MovimientoCaja::create([
                        'turno_caja_id' => $turno->id,
                        'user_id' => $cajero1->id,
                        'tipo' => 'ingreso',
                        'concepto' => 'Billetes de baja denominación para cambio en caja',
                        'monto' => $ingresoExtra,
                        'metodo_pago' => 'efectivo',
                        'numero_comprobante' => 'ING-'.$fechaDia->format('Ymd').'-01',
                        'created_at' => (clone $fechaDia)->setTime(12, 15),
                    ]);
                }

                // Movimiento 2: Caja menor
                MovimientoCaja::create([
                    'turno_caja_id' => $turno->id,
                    'user_id' => $cajero1->id,
                    'tipo' => 'egreso',
                    'concepto' => 'Caja menor: limones criollos, servilletas y hielo',
                    'monto' => $egresoMenor,
                    'metodo_pago' => 'efectivo',
                    'numero_comprobante' => 'EGR-'.$fechaDia->format('Ymd').'-01',
                    'autorizado_por' => 'Gerencia',
                    'created_at' => (clone $fechaDia)->setTime(15, 30),
                ]);

                // Movimiento 3: Retiro a bóveda
                if ($retiroBoveda > 0) {
                    MovimientoCaja::create([
                        'turno_caja_id' => $turno->id,
                        'user_id' => $cajero1->id,
                        'tipo' => 'retiro',
                        'concepto' => 'Retiro preventivo de efectivo para depósito en bóveda',
                        'monto' => $retiroBoveda,
                        'metodo_pago' => 'efectivo',
                        'numero_comprobante' => 'RET-'.$fechaDia->format('Ymd').'-01',
                        'autorizado_por' => 'Gerente de Operaciones',
                        'created_at' => (clone $fechaDia)->setTime(21, 45),
                    ]);
                }

                // Pedidos históricos de este día (5 a 8 comandas por día)
                $numPedidosDia = $esFinDeSemana ? 8 : 5;
                for ($p = 0; $p < $numPedidosDia; $p++) {
                    $contadorCodigo++;
                    $consecutivo++;
                    $hora = ($p % 2 === 0) ? rand(12, 15) : rand(19, 22);
                    $fechaPedido = (clone $fechaDia)->setTime($hora, rand(5, 55));

                    $cliente = $clientes->isNotEmpty() ? $clientes->random() : null;
                    $mesero = $meseros->isNotEmpty() ? $meseros->random() : $primerMesero;
                    $mesa = $mesas->random();
                    $esDelivery = ($p === ($numPedidosDia - 1));

                    $metodos = ['efectivo', 'tarjeta', 'tarjeta', 'wompi', 'bold'];
                    $metodo = $metodos[array_rand($metodos)];

                    $platosMuestra = $productos->random(min(3, $productos->count()));
                    $subtotal = 0;
                    foreach ($platosMuestra as $pl) {
                        $subtotal += (float) $pl->precio;
                    }

                    $propina = round($subtotal * 0.10, 2);
                    $total = $subtotal + $propina;

                    $pedido = Pedido::create([
                        'codigo' => 'ORD-'.date('Ymd', $fechaDia->timestamp).'-'.str_pad((string) $p, 3, '0', STR_PAD_LEFT),
                        'tipo' => $esDelivery ? 'delivery' : 'mesa',
                        'estado' => 'pagado',
                        'sucursal_id' => $sucursal->id,
                        'mesa_id' => $esDelivery ? null : $mesa->id,
                        'cliente_id' => $cliente?->id,
                        'usuario_id' => $mesero->id,
                        'mesero_id' => $mesero->id,
                        'repartidor_id' => $esDelivery ? $repartidor->id : null,
                        'turno_caja_id' => $turno->id,
                        'nombre_cliente' => $cliente?->nombre ?? 'Cliente Salón Mesa '.$mesa->numero,
                        'subtotal' => $subtotal,
                        'propina' => $propina,
                        'porcentaje_propina' => 10.0,
                        'total' => $total,
                        'metodo_pago' => $metodo,
                        'monto_pagado' => $total,
                        'cambio' => 0.00,
                        'pagado_en' => (clone $fechaPedido)->addMinutes(45),
                        'created_at' => $fechaPedido,
                    ]);

                    // Ítems de la comanda
                    foreach ($platosMuestra as $pl) {
                        ItemPedido::create([
                            'pedido_id' => $pedido->id,
                            'producto_id' => $pl->id,
                            'nombre_producto' => $pl->nombre,
                            'cantidad' => 1,
                            'precio_unitario' => $pl->precio,
                            'subtotal' => $pl->precio,
                            'area_cocina' => $pl->area_cocina ?? 'caliente',
                            'estado_cocina' => 'servido',
                            'iniciado_en' => (clone $fechaPedido)->addMinutes(5),
                            'listo_en' => (clone $fechaPedido)->addMinutes(25),
                            'created_at' => $fechaPedido,
                        ]);
                    }

                    // Factura electrónica DIAN POS con CUFE SHA-384
                    $cufeRaw = "NumFac={$prefijo}-{$consecutivo}&FecFac={$fechaPedido->format('Y-m-d')}&ValFac={$total}&NitFac={$sucursal->nit_ruc}&ClaveTecnica=CLAVE-TECNICA-DEMO-2026";
                    $cufe = hash('sha384', $cufeRaw);

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
        // 3. OPERACIÓN EN VIVO DE HOY (TURNOS ABIERTOS Y COMANDAS ACTIVAS)
        // =========================================================================
        $this->command?->info('Sembrando turnos abiertos y comandas activas de hoy...');

        // Turno en Caja Principal (Abierto)
        $turnoHoy = TurnoCaja::where('caja_id', $cajaPrincipal->id)->where('estado', 'abierto')->first();
        if (! $turnoHoy) {
            $turnoHoy = TurnoCaja::create([
                'caja_id' => $cajaPrincipal->id,
                'user_id' => $cajero1->id,
                'monto_inicial' => 250000.00,
                'monto_esperado_efectivo' => 250000.00,
                'estado' => 'abierto',
                'apertura_en' => Carbon::today()->setTime(11, 00),
                'notas_apertura' => 'Turno Activo Hoy - Base $250.000 COP en efectivo',
            ]);
        }

        // Turno en Caja Barra (Abierto)
        $turnoBarraHoy = TurnoCaja::where('caja_id', $cajaBarra->id)->where('estado', 'abierto')->first();
        if (! $turnoBarraHoy) {
            $turnoBarraHoy = TurnoCaja::create([
                'caja_id' => $cajaBarra->id,
                'user_id' => $cajero2->id,
                'monto_inicial' => 150000.00,
                'monto_esperado_efectivo' => 150000.00,
                'estado' => 'abierto',
                'apertura_en' => Carbon::today()->setTime(11, 30),
                'notas_apertura' => 'Turno Activo Hoy en Barra & Terraza - Base $150.000 COP',
            ]);
        }

        // Movimientos de hoy en Caja Principal
        MovimientoCaja::firstOrCreate(
            ['numero_comprobante' => 'ING-HOY-01'],
            [
                'turno_caja_id' => $turnoHoy->id,
                'user_id' => $cajero1->id,
                'tipo' => 'ingreso',
                'concepto' => 'Cobro anticipo en efectivo para reserva evento cena VIP',
                'monto' => 150000.00,
                'metodo_pago' => 'efectivo',
                'created_at' => now()->subHours(2),
            ]
        );

        MovimientoCaja::firstOrCreate(
            ['numero_comprobante' => 'EGR-HOY-01'],
            [
                'turno_caja_id' => $turnoHoy->id,
                'user_id' => $cajero1->id,
                'tipo' => 'egreso',
                'concepto' => 'Compra urgente de hielo gourmet y servilletas',
                'monto' => 22000.00,
                'metodo_pago' => 'efectivo',
                'autorizado_por' => 'Gerencia',
                'created_at' => now()->subHour(),
            ]
        );

        // COMANDAS EN VIVO DE HOY EN TODAS LAS ETAPAS DEL FLUJO:
        $platoCarnes = $productos->firstWhere('slug', 'bife-de-chorizo-angus-350g') ?? $productos->first();
        $platoBurger = $productos->firstWhere('slug', 'restomaster-burger-master') ?? $productos->skip(2)->first() ?? $productos->first();
        $bebidaBarra = $productos->firstWhere('area_cocina', 'barra') ?? $productos->last();

        // 1. Mesa 1: En cocina KDS
        $m1 = $mesas->firstWhere('numero', '1') ?? $mesas->first();
        $m1->update(['estado' => 'ocupada']);

        $pedCocina = Pedido::firstOrCreate(
            ['codigo' => 'ORD-HOY-101'],
            [
                'tipo' => 'mesa',
                'estado' => 'en_cocina',
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $m1->id,
                'usuario_id' => $primerMesero->id,
                'mesero_id' => $primerMesero->id,
                'turno_caja_id' => $turnoHoy->id,
                'nombre_cliente' => 'Familia Restrepo',
                'subtotal' => ($platoCarnes->precio * 2) + $bebidaBarra->precio,
                'total' => (($platoCarnes->precio * 2) + $bebidaBarra->precio) * 1.10,
                'propina' => (($platoCarnes->precio * 2) + $bebidaBarra->precio) * 0.10,
                'notas' => 'Cortes término 3/4. Sin cebolla en salsas.',
                'created_at' => now()->subMinutes(12),
            ]
        );

        if ($pedCocina->wasRecentlyCreated) {
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
        }

        // 2. Mesa 2: Solicitada vía QR en mesa
        $m2 = $mesas->firstWhere('numero', '2') ?? $mesas->skip(1)->first();
        $m2->update(['estado' => 'ocupada']);

        Pedido::firstOrCreate(
            ['codigo' => 'ORD-HOY-102'],
            [
                'tipo' => 'mesa',
                'estado' => 'solicitado_qr',
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $m2->id,
                'turno_caja_id' => $turnoHoy->id,
                'nombre_cliente' => 'Comensal QR Mesa 2',
                'subtotal' => $platoBurger->precio + $bebidaBarra->precio,
                'total' => $platoBurger->precio + $bebidaBarra->precio,
                'notas' => 'Pedido auto-generado por el cliente desde el código QR en mesa.',
                'created_at' => now()->subMinutes(5),
            ]
        );

        // 3. Mesa 3: Comanda lista para servir (campana en KDS)
        $m3 = $mesas->firstWhere('numero', '3') ?? $mesas->skip(2)->first();
        $m3->update(['estado' => 'ocupada']);

        $pedListo = Pedido::firstOrCreate(
            ['codigo' => 'ORD-HOY-103'],
            [
                'tipo' => 'mesa',
                'estado' => 'listo',
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $m3->id,
                'usuario_id' => $primerMesero->id,
                'mesero_id' => $primerMesero->id,
                'turno_caja_id' => $turnoHoy->id,
                'nombre_cliente' => 'Carlos Andrés Restrepo',
                'subtotal' => $platoBurger->precio * 2,
                'total' => ($platoBurger->precio * 2) * 1.10,
                'propina' => ($platoBurger->precio * 2) * 0.10,
                'created_at' => now()->subMinutes(25),
            ]
        );

        if ($pedListo->wasRecentlyCreated) {
            ItemPedido::create([
                'pedido_id' => $pedListo->id,
                'producto_id' => $platoBurger->id,
                'nombre_producto' => $platoBurger->nombre,
                'cantidad' => 2,
                'precio_unitario' => $platoBurger->precio,
                'subtotal' => $platoBurger->precio * 2,
                'area_cocina' => 'caliente',
                'estado_cocina' => 'listo',
                'iniciado_en' => now()->subMinutes(20),
                'listo_en' => now()->subMinutes(2),
            ]);
        }

        // 4. Mesa 4: Pendiente de cobro en caja
        $m4 = $mesas->firstWhere('numero', '4') ?? $mesas->skip(3)->first();
        $m4->update(['estado' => 'ocupada']);

        Pedido::firstOrCreate(
            ['codigo' => 'ORD-HOY-104'],
            [
                'tipo' => 'mesa',
                'estado' => 'pendiente_cobro',
                'sucursal_id' => $sucursal->id,
                'mesa_id' => $m4->id,
                'usuario_id' => $primerMesero->id,
                'mesero_id' => $primerMesero->id,
                'turno_caja_id' => $turnoHoy->id,
                'nombre_cliente' => 'Dra. Valentina Morales',
                'subtotal' => 145000.00,
                'total' => 159500.00,
                'propina' => 14500.00,
                'metodo_pago' => 'tarjeta',
                'notas' => 'Cliente solicitó la cuenta. Pagará con datáfono contactless.',
                'created_at' => now()->subMinutes(45),
            ]
        );

        // 5. Delivery Activo (En camino con repartidor)
        Pedido::firstOrCreate(
            ['codigo' => 'DLV-HOY-201'],
            [
                'tipo' => 'delivery',
                'estado' => 'en_proceso',
                'estado_delivery' => 'en_camino',
                'sucursal_id' => $sucursal->id,
                'usuario_id' => $cajero1->id,
                'repartidor_id' => $repartidor->id,
                'turno_caja_id' => $turnoHoy->id,
                'nombre_cliente' => 'Santiago Uribe Arango',
                'telefono_cliente' => '+57 312 405 8821',
                'direccion_delivery' => 'Carrera 43A # 1Sur-150, Edificio Torre Ónix, Apto 804',
                'subtotal' => 78000.00,
                'costo_envio' => 8000.00,
                'total' => 86000.00,
                'metodo_pago' => 'efectivo',
                'notas' => 'Timbrar en portería y anunciar para entregar en piso 8.',
                'hora_despacho' => now()->subMinutes(18),
                'created_at' => now()->subMinutes(35),
            ]
        );

        // =========================================================================
        // 4. RESERVAS DEL MES (PASADAS, HOY Y PRÓXIMAS SEMANAS)
        // =========================================================================
        $this->command?->info('Sembrando reservas completas del mes...');

        $reservasMes = [
            // Pasadas
            [
                'nombre_contacto' => 'Laura Restrepo Gómez',
                'telefono_contacto' => '3104589201',
                'email_contacto' => 'laura.restrepo@gmail.com',
                'fecha' => Carbon::today()->subDays(20),
                'hora_llegada' => '13:00',
                'personas' => 4,
                'estado' => 'finalizada',
                'anticipo' => 0,
                'mesa_id' => $mesas->first()?->id,
            ],
            [
                'nombre_contacto' => 'Daniela Sofía Ospina',
                'telefono_contacto' => '3001239988',
                'email_contacto' => 'daniela.ospina@gmail.com',
                'fecha' => Carbon::today()->subDays(12),
                'hora_llegada' => '20:00',
                'personas' => 6,
                'estado' => 'finalizada',
                'anticipo' => 50000.00,
                'mesa_id' => $mesas->skip(1)->first()?->id,
            ],
            [
                'nombre_contacto' => 'Mateo Henao Zuluaga',
                'telefono_contacto' => '3158894432',
                'email_contacto' => 'mateo.henao@sura.com',
                'fecha' => Carbon::today()->subDays(5),
                'hora_llegada' => '21:00',
                'personas' => 2,
                'estado' => 'finalizada',
                'anticipo' => 0,
                'mesa_id' => $mesas->skip(2)->first()?->id,
            ],
            // Hoy
            [
                'nombre_contacto' => 'Andrés Felipe Restrepo',
                'telefono_contacto' => '3004589201',
                'email_contacto' => 'andres.restrepo@empresa.com',
                'fecha' => Carbon::today(),
                'hora_llegada' => '13:30',
                'personas' => 4,
                'estado' => 'confirmada',
                'anticipo' => 0,
                'notas' => 'Almuerzo familiar cumpleaños. Mesa en salón.',
                'mesa_id' => $m1->id,
            ],
            [
                'nombre_contacto' => 'Federico Gutiérrez Saldarriaga',
                'telefono_contacto' => '3186721904',
                'email_contacto' => 'fede.gutierrez@alcaldia.gov.co',
                'fecha' => Carbon::today(),
                'hora_llegada' => '20:30',
                'personas' => 8,
                'estado' => 'confirmada',
                'anticipo' => 200000.00,
                'notas' => 'Cena ejecutiva reservada en Salón VIP.',
                'mesa_id' => $mesas->firstWhere('zona', 'vip')?->id ?? $m3->id,
            ],
            // Próximos días y fin de semana
            [
                'nombre_contacto' => 'Carolina Duque Botero',
                'telefono_contacto' => '3108294411',
                'email_contacto' => 'carolina.duque@bancolombia.com',
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
                'email_contacto' => 'santiago.uribe@nutresa.com',
                'fecha' => Carbon::tomorrow()->addDays(2),
                'hora_llegada' => '20:30',
                'personas' => 4,
                'estado' => 'pendiente',
                'anticipo' => 50000.00,
                'notas' => 'Cena fin de semana terraza exterior.',
                'mesa_id' => $mesas->firstWhere('zona', 'terraza')?->id ?? $m1->id,
            ],
            [
                'nombre_contacto' => 'Juliana Velez Osorio',
                'telefono_contacto' => '3117765544',
                'email_contacto' => 'juliana.velez@gmail.com',
                'fecha' => Carbon::today()->addDays(6),
                'hora_llegada' => '21:00',
                'personas' => 6,
                'estado' => 'confirmada',
                'anticipo' => 100000.00,
                'notas' => 'Celebración de grado en Terraza Lounge.',
                'mesa_id' => $mesas->firstWhere('zona', 'terraza')?->id ?? $m2->id,
            ],
        ];

        foreach ($reservasMes as $rData) {
            $mesaId = $rData['mesa_id'] ?? null;
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
                    'token_publico' => Str::random(32),
                    'created_by' => $cajero1->id,
                ])
            );

            if ($mesaId && $res->wasRecentlyCreated) {
                $res->mesas()->sync([$mesaId]);
            }
        }

        // =========================================================================
        // 5. PROGRAMACIÓN SEMANAL DE MESEROS POR ZONAS (PIZARRA Y MALLA SEMANAL)
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

        $plantillaApertura = PlantillaTurno::firstOrCreate(
            ['nombre' => 'Turno Completo Almuerzo & Cena', 'sucursal_id' => $sucursal->id],
            ['hora_inicio' => '11:30', 'hora_fin' => '23:00', 'activo' => true]
        );

        $zonaSalon = $zonas->firstWhere('slug', 'salon') ?? $zonas->first();
        $zonaTerraza = $zonas->firstWhere('slug', 'terraza') ?? $zonas->skip(1)->first() ?? $zonaSalon;
        $zonaBarra = $zonas->firstWhere('slug', 'barra') ?? $zonas->skip(2)->first() ?? $zonaSalon;

        $zonasRotacion = [$zonaSalon, $zonaTerraza, $zonaBarra];
        $inicioSemana = (clone $ahora)->startOfWeek();

        for ($dia = 0; $dia < 7; $dia++) {
            $fechaDia = (clone $inicioSemana)->addDays($dia);

            foreach ($meseros as $idx => $meseroUser) {
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

        $this->command?->info('✓ Operaciones completas de 1 MES (30 días) sembradas con éxito.');
    }
}
