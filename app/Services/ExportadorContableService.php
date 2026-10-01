<?php

namespace App\Services;

use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\PedidoDevolucion;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ExportadorContableService
{
    public function __construct(
        protected ConfiguracionService $configuracionService
    ) {}

    /**
     * Exportación de Comprobantes Contables para Siigo Nube (Plantilla CC-1 / Interfaz Contable).
     * Cumple con partida doble perfecta (Débitos = Créditos) y cuentas estándar PUC Colombia.
     *
     * @return array{encabezados: array<string>, filas: array<array<string|numeric>>}
     */
    public function exportarSiigo(string $desde, string $hasta): array
    {
        $encabezados = [
            'Tipo Comprobante',
            'Consecutivo Comprobante',
            'Fecha Elaboracion',
            'Cuenta Contable',
            'Identificacion Tercero',
            'Sucursal',
            'Centro de Costos',
            'Descripcion',
            'Debito',
            'Credito',
            'Base',
        ];

        $pedidos = $this->obtenerPedidosPagados($desde, $hasta);
        $devoluciones = $this->obtenerDevoluciones($desde, $hasta);

        $filas = [];
        $porcentajeInc = (float) $this->configuracionService->obtener('general', 'impuesto_porcentaje', 8);

        // 1. Asientos por Pedidos Cobrados (Ventas e Ingresos)
        foreach ($pedidos as $pedido) {
            $fecha = Carbon::parse($pedido->pagado_en ?? $pedido->created_at)->format('d/m/Y');
            $docTercero = $this->sanitizarIdentificacion($pedido->cliente?->documento ?? '222222222222');
            $consecutivo = $pedido->codigo;
            $metodo = strtolower($pedido->metodo_pago ?? 'efectivo');

            $totalCobrado = (float) ($pedido->monto_pagado > 0 ? $pedido->monto_pagado : $pedido->total + ($pedido->propina ?? 0));
            $propina = (float) ($pedido->propina ?? 0);
            $totalVenta = (float) $pedido->total;

            // Desglose Base e INC (8%)
            $factor = 1 + ($porcentajeInc / 100);
            $baseGravable = $factor != 0 ? round($totalVenta / $factor, 2) : $totalVenta;
            $valorInc = round($totalVenta - $baseGravable, 2);

            // A. Débito a Caja General (11050501) o Bancos/Adquirente (11100501)
            $cuentaCobro = in_array($metodo, ['tarjeta', 'transferencia', 'online', 'datafono'], true)
                ? '11100501'
                : '11050501';

            $filas[] = [
                'F-1',
                $consecutivo,
                $fecha,
                $cuentaCobro,
                $docTercero,
                0,
                1,
                "Recaudo venta orden {$pedido->codigo} ({$metodo})",
                $totalCobrado,
                0,
                0,
            ];

            // B. Crédito a Ingresos por Restaurante (41350101)
            $filas[] = [
                'F-1',
                $consecutivo,
                $fecha,
                '41350101',
                $docTercero,
                0,
                1,
                "Venta restaurante orden {$pedido->codigo}",
                0,
                $baseGravable,
                $baseGravable,
            ];

            // C. Crédito a Impuesto Nacional al Consumo (24950101)
            if ($valorInc > 0) {
                $filas[] = [
                    'F-1',
                    $consecutivo,
                    $fecha,
                    '24950101',
                    $docTercero,
                    0,
                    1,
                    "INC {$porcentajeInc}% orden {$pedido->codigo}",
                    0,
                    $valorInc,
                    $baseGravable,
                ];
            }

            // D. Crédito a Propinas Voluntarias por Distribuir (28150501)
            if ($propina > 0) {
                $filas[] = [
                    'F-1',
                    $consecutivo,
                    $fecha,
                    '28150501',
                    $docTercero,
                    0,
                    1,
                    "Propina voluntaria orden {$pedido->codigo}",
                    0,
                    $propina,
                    0,
                ];
            }
        }

        // 2. Asientos por Devoluciones y Correcciones (Reversión Contable)
        foreach ($devoluciones as $dev) {
            $fecha = Carbon::parse($dev->created_at)->format('d/m/Y');
            $docTercero = $this->sanitizarIdentificacion($dev->pedido?->cliente?->documento ?? '222222222222');
            $consecutivo = "DEV-{$dev->id}";
            $montoReembolsado = (float) ($dev->monto_devuelto ?? $dev->monto_reembolsado ?? 0);

            $factor = 1 + ($porcentajeInc / 100);
            $baseDevuelta = $factor != 0 ? round($montoReembolsado / $factor, 2) : $montoReembolsado;
            $incDevuelto = round($montoReembolsado - $baseDevuelta, 2);

            // Débito a Devoluciones en Ventas (41750501)
            $filas[] = [
                'NC',
                $consecutivo,
                $fecha,
                '41750501',
                $docTercero,
                0,
                1,
                "Devolución ítem orden {$dev->pedido?->codigo}: {$dev->motivo}",
                $baseDevuelta,
                0,
                $baseDevuelta,
            ];

            // Débito Reversión INC (24950101)
            if ($incDevuelto > 0) {
                $filas[] = [
                    'NC',
                    $consecutivo,
                    $fecha,
                    '24950101',
                    $docTercero,
                    0,
                    1,
                    "Reversión INC devolución orden {$dev->pedido?->codigo}",
                    $incDevuelto,
                    0,
                    $baseDevuelta,
                ];
            }

            // Crédito a Caja General (11050501)
            $filas[] = [
                'NC',
                $consecutivo,
                $fecha,
                '11050501',
                $docTercero,
                0,
                1,
                "Reembolso efectivo al cliente devolución {$consecutivo}",
                0,
                $montoReembolsado,
                0,
            ];
        }

        return [
            'encabezados' => $encabezados,
            'filas' => $filas,
        ];
    }

    /**
     * Exportación de Facturas de Venta / Ingresos para Alegra.
     * Compatible con plantilla de importación masiva de facturas/ventas en Excel o CSV.
     *
     * @return array{encabezados: array<string>, filas: array<array<string|numeric>>}
     */
    public function exportarAlegra(string $desde, string $hasta): array
    {
        $encabezados = [
            'Fecha',
            'Cliente',
            'Identificacion',
            'Numero Factura',
            'Item / Producto',
            'Cantidad',
            'Precio Unitario',
            'Descuento Unitario',
            'Impuesto',
            'Cuenta Contable',
            'Metodo de Pago',
            'Total Linea',
        ];

        $pedidos = $this->obtenerPedidosPagados($desde, $hasta);
        $filas = [];
        $porcentajeInc = (int) $this->configuracionService->obtener('general', 'impuesto_porcentaje', 8);

        foreach ($pedidos as $pedido) {
            $fecha = Carbon::parse($pedido->pagado_en ?? $pedido->created_at)->format('Y-m-d');
            $clienteNombre = $pedido->cliente?->nombre ?? ($pedido->nombre_cliente ?: 'Consumidor Final');
            $identificacion = $this->sanitizarIdentificacion($pedido->cliente?->documento ?? '222222222222');
            $metodo = ucfirst($pedido->metodo_pago ?? 'Efectivo');

            foreach ($pedido->items as $item) {
                $cantEfectiva = $item->cantidad - ($item->cantidad_devuelta ?? 0);
                if ($cantEfectiva <= 0) {
                    continue;
                }

                $filas[] = [
                    $fecha,
                    $clienteNombre,
                    $identificacion,
                    $pedido->codigo,
                    $item->nombre_producto,
                    $cantEfectiva,
                    (float) $item->precio_unitario,
                    0,
                    "INC ({$porcentajeInc}%)",
                    '4135',
                    $metodo,
                    round($cantEfectiva * (float) $item->precio_unitario, 2),
                ];
            }
        }

        return [
            'encabezados' => $encabezados,
            'filas' => $filas,
        ];
    }

    /**
     * Exportación de Archivo Plano para World Office (Formato Estructurado de Movimientos Contables).
     * Delimitador punto y coma (;).
     */
    public function exportarWorldOffice(string $desde, string $hasta): string
    {
        $siigoData = $this->exportarSiigo($desde, $hasta);
        $lineas = [];

        // Encabezado estándar World Office
        $lineas[] = 'TipoDoc;Documento;Fecha;Cuenta;Tercero;CentroCostos;Debito;Credito;Detalle;Base';

        foreach ($siigoData['filas'] as $f) {
            // World Office: [TipoDoc, Num, Fecha AAAA/MM/DD, Cuenta, Tercero, CC, Debito, Credito, Detalle, Base]
            $fechaPartes = explode('/', (string) $f[2]);
            $fechaWo = count($fechaPartes) === 3
                ? "{$fechaPartes[2]}/{$fechaPartes[1]}/{$fechaPartes[0]}"
                : (string) $f[2];

            $lineas[] = implode(';', [
                $f[0], // TipoDoc
                $f[1], // Documento
                $fechaWo, // Fecha
                $f[3], // Cuenta PUC
                $f[4], // Tercero
                str_pad((string) $f[6], 3, '0', STR_PAD_LEFT), // Centro Costos
                number_format((float) $f[8], 2, '.', ''), // Débito
                number_format((float) $f[9], 2, '.', ''), // Crédito
                $this->sanitizarTextoPlano((string) $f[7]), // Detalle
                number_format((float) $f[10], 2, '.', ''), // Base
            ]);
        }

        return implode("\r\n", $lineas);
    }

    /**
     * Exportación de Archivo Plano para Helisa (Estructura Comprobantes de Diario / Ventas).
     * Delimitador tubería (|) y fechas en formato AAAAMMDD.
     */
    public function exportarHelisa(string $desde, string $hasta): string
    {
        $siigoData = $this->exportarSiigo($desde, $hasta);
        $lineas = [];

        // Encabezado Helisa Comprobantes
        $lineas[] = 'COMPROBANTE|CONSECUTIVO|FECHA|CUENTA|NIT_TERCERO|DEBITO|CREDITO|DOCUMENTO_REF|CONCEPTO';

        foreach ($siigoData['filas'] as $f) {
            $fechaPartes = explode('/', (string) $f[2]);
            $fechaHelisa = count($fechaPartes) === 3
                ? "{$fechaPartes[2]}{$fechaPartes[1]}{$fechaPartes[0]}"
                : str_replace('-', '', (string) $f[2]);

            $lineas[] = implode('|', [
                $f[0] === 'F-1' ? '01' : '02',
                substr((string) $f[1], -8),
                $fechaHelisa,
                $f[3],
                $f[4],
                number_format((float) $f[8], 2, '.', ''),
                number_format((float) $f[9], 2, '.', ''),
                $f[1],
                $this->sanitizarTextoPlano((string) $f[7]),
            ]);
        }

        return implode("\r\n", $lineas);
    }

    /**
     * Generación del Libro Fiscal de Operaciones Diarias conforme al Artículo 616-1 del Estatuto Tributario (DIAN).
     * Obligatorio para establecimientos de comercio y restaurantes.
     *
     * @return array{
     *     empresa: array<string, string>,
     *     periodo: array{desde: string, hasta: string},
     *     dias: array<array<string, mixed>>,
     *     totales: array<string, float|int>
     * }
     */
    public function generarLibroFiscalDian(string $desde, string $hasta): array
    {
        $porcentajeInc = (float) $this->configuracionService->obtener('general', 'impuesto_porcentaje', 8);
        $factor = 1 + ($porcentajeInc / 100);

        $periodo = CarbonPeriod::create($desde, $hasta);
        $diasData = [];

        $totales = [
            'dias_con_movimiento' => 0,
            'total_operaciones' => 0,
            'ingresos_brutos' => 0.0,
            'base_gravable' => 0.0,
            'impuesto_consumo_inc' => 0.0,
            'total_devoluciones' => 0.0,
            'ingresos_netos' => 0.0,
            'gastos_diarios_caja' => 0.0,
            'saldo_neto_fiscal' => 0.0,
        ];

        foreach ($periodo as $fechaCarbon) {
            $fechaStr = $fechaCarbon->toDateString();
            $diaInicio = "{$fechaStr} 00:00:00";
            $diaFin = "{$fechaStr} 23:59:59";

            $pedidosDia = Pedido::query()
                ->where('estado', 'pagado')
                ->whereBetween('pagado_en', [$diaInicio, $diaFin])
                ->orderBy('id')
                ->get();

            $devolucionesDia = PedidoDevolucion::query()
                ->whereBetween('created_at', [$diaInicio, $diaFin])
                ->get();

            $devolucionMovimientoIds = $devolucionesDia->pluck('movimiento_caja_id')->filter()->all();

            $egresosCajaDia = MovimientoCaja::query()
                ->where('tipo', 'egreso')
                ->whereBetween('created_at', [$diaInicio, $diaFin])
                ->when(! empty($devolucionMovimientoIds), fn ($q) => $q->whereNotIn('id', $devolucionMovimientoIds))
                ->sum('monto');

            $cantOperaciones = $pedidosDia->count();
            $ingresosBrutos = (float) $pedidosDia->sum('total');
            $baseGravable = round($ingresosBrutos / $factor, 2);
            $valorInc = round($ingresosBrutos - $baseGravable, 2);
            $totalDevoluciones = (float) $devolucionesDia->sum(fn ($d) => $d->monto_devuelto ?? $d->monto_reembolsado ?? 0);
            $ingresosNetos = round($ingresosBrutos - $totalDevoluciones, 2);
            $costosGastos = (float) $egresosCajaDia;
            $saldoNeto = round($ingresosNetos - $costosGastos, 2);

            $comprobanteInicial = $cantOperaciones > 0 ? $pedidosDia->first()->codigo : '—';
            $comprobanteFinal = $cantOperaciones > 0 ? $pedidosDia->last()->codigo : '—';

            if ($cantOperaciones > 0 || $totalDevoluciones > 0 || $costosGastos > 0) {
                $totales['dias_con_movimiento']++;
            }

            $totales['total_operaciones'] += $cantOperaciones;
            $totales['ingresos_brutos'] += $ingresosBrutos;
            $totales['base_gravable'] += $baseGravable;
            $totales['impuesto_consumo_inc'] += $valorInc;
            $totales['total_devoluciones'] += $totalDevoluciones;
            $totales['ingresos_netos'] += $ingresosNetos;
            $totales['gastos_diarios_caja'] += $costosGastos;
            $totales['saldo_neto_fiscal'] += $saldoNeto;

            $diasData[] = [
                'fecha' => $fechaStr,
                'fecha_formateada' => $fechaCarbon->format('d/m/Y'),
                'comprobante_inicial' => $comprobanteInicial,
                'comprobante_final' => $comprobanteFinal,
                'total_operaciones' => $cantOperaciones,
                'ingresos_brutos' => $ingresosBrutos,
                'base_gravable' => $baseGravable,
                'impuesto_consumo_inc' => $valorInc,
                'total_devoluciones' => $totalDevoluciones,
                'ingresos_netos' => $ingresosNetos,
                'gastos_diarios_caja' => $costosGastos,
                'saldo_neto_fiscal' => $saldoNeto,
            ];
        }

        return [
            'empresa' => [
                'razon_social' => $this->configuracionService->obtener('general', 'razon_social', 'RestoMaster S.A.S.'),
                'nit' => $this->configuracionService->obtener('general', 'nit', '901.458.789-3'),
                'direccion' => $this->configuracionService->obtener('general', 'direccion', ''),
                'ciudad' => $this->configuracionService->obtener('general', 'ciudad', ''),
                'regimen' => $this->configuracionService->obtener('general', 'regimen', 'No Responsable de IVA / Impoconsumo'),
                'resolucion_dian' => $this->configuracionService->obtener('ticket_80mm', 'resolucion_dian', ''),
            ],
            'periodo' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'desde_formateado' => Carbon::parse($desde)->format('d/m/Y'),
                'hasta_formateado' => Carbon::parse($hasta)->format('d/m/Y'),
                'generado_en' => now()->format('d/m/Y H:i:s'),
            ],
            'dias' => $diasData,
            'totales' => $totales,
        ];
    }

    /**
     * Construye un string CSV formateado con delimitador de punto y coma, UTF-8 BOM
     * y protección contra inyección de fórmulas de hoja de cálculo (CSV Injection).
     *
     * @param  array<string>  $encabezados
     * @param  array<array<string|numeric>>  $filas
     */
    public function formatearCsv(array $encabezados, array $filas, string $delimitador = ';'): string
    {
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM para Excel

        $escaparCelda = function ($valor) {
            $str = (string) $valor;
            // Prevenir inyección de fórmulas en Excel
            if ($str !== '' && in_array($str[0], ['=', '+', '-', '@'], true)) {
                $str = "'".$str;
            }

            return '"'.str_replace('"', '""', $str).'"';
        };

        // Encabezados
        $csv .= implode($delimitador, array_map($escaparCelda, $encabezados))."\r\n";

        // Filas
        foreach ($filas as $fila) {
            $csv .= implode($delimitador, array_map($escaparCelda, $fila))."\r\n";
        }

        return $csv;
    }

    /**
     * Obtiene los pedidos pagados en un rango con sus relaciones necesarias para contabilidad.
     */
    protected function obtenerPedidosPagados(string $desde, string $hasta): Collection
    {
        return Pedido::query()
            ->with(['cliente', 'items', 'mesero'])
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', ["{$desde} 00:00:00", "{$hasta} 23:59:59"])
            ->orderBy('id')
            ->get();
    }

    /**
     * Obtiene las devoluciones registradas en un rango.
     */
    protected function obtenerDevoluciones(string $desde, string $hasta): Collection
    {
        return PedidoDevolucion::query()
            ->with(['pedido.cliente', 'producto', 'usuario'])
            ->whereBetween('created_at', ["{$desde} 00:00:00", "{$hasta} 23:59:59"])
            ->orderBy('id')
            ->get();
    }

    /**
     * Limpia y normaliza el documento / NIT para software contable.
     */
    protected function sanitizarIdentificacion(?string $documento): string
    {
        if (empty($documento)) {
            return '222222222222';
        }

        // Remover puntos, guiones y espacios
        $limpio = preg_replace('/[^0-9A-Za-z]/', '', $documento);

        return ! empty($limpio) ? $limpio : '222222222222';
    }

    /**
     * Limpia textos para archivos planos evitando saltos de línea y caracteres de control.
     */
    protected function sanitizarTextoPlano(string $texto): string
    {
        $limpio = preg_replace('/[\r\n\t;|]+/', ' ', $texto);

        return trim(substr($limpio, 0, 80));
    }
}
