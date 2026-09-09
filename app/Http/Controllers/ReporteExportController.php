<?php

namespace App\Http\Controllers;

use App\Services\ConfiguracionService;
use App\Services\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReporteExportController extends Controller
{
    private const REPORTES_VALIDOS = ['estado', 'ventas', 'clientes', 'reservas'];

    public function pdf(Request $request): Response
    {
        [$reporte, $desde, $hasta, $datos] = $this->datos($request);

        $empresa = app(ConfiguracionService::class);

        $pdf = Pdf::loadView('pdf.reporte', [
            'reporte' => $reporte,
            'desde' => $desde,
            'hasta' => $hasta,
            'datos' => $datos,
            'razon_social' => $empresa->obtener('general', 'razon_social', ''),
            'nit' => $empresa->obtener('general', 'nit', ''),
            'generado' => now()->format('d/m/Y H:i'),
        ])->setPaper('letter', 'portrait');

        return $pdf->download("reporte-{$reporte}-{$desde}-{$hasta}.pdf");
    }

    public function csv(Request $request): Response
    {
        [$reporte, $desde, $hasta, $datos] = $this->datos($request);

        $filas = [$this->encabezados($reporte), ...$this->filas($reporte, $datos)];

        $csv = "\xEF\xBB\xBF";

        foreach ($filas as $fila) {
            $csv .= implode(';', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $fila)) . "\r\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reporte-' . $reporte . '-' . $desde . '-' . $hasta . '.csv"',
        ]);
    }

    private function datos(Request $request): array
    {
        $validated = $request->validate([
            'reporte' => ['required', 'in:' . implode(',', self::REPORTES_VALIDOS)],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $service = app(ReporteService::class);
        $reporte = $validated['reporte'];
        $desde = $validated['desde'];
        $hasta = $validated['hasta'];

        $datos = match ($reporte) {
            'ventas' => ['por_periodo' => $service->ventasPorPeriodo($desde, $hasta), 'por_tipo' => $service->ventasPorTipo($desde, $hasta), 'por_producto' => $service->ventasPorProducto($desde, $hasta, 10), 'por_trabajador' => $service->ventasPorTrabajador($desde, $hasta), 'comparativa' => $service->comparativaPeriodos($desde, $hasta)],
            'clientes' => ['top_clientes' => $service->topClientes($desde, $hasta, 10), 'tiempos' => $service->tiemposEntrega($desde, $hasta)],
            'reservas' => ['resumen' => $service->resumenReservas($desde, $hasta)],
            default => ['resultado' => $service->estadoResultados($desde, $hasta)],
        };

        return [$reporte, $desde, $hasta, $datos];
    }

    private function encabezados(string $reporte): array
    {
        return match ($reporte) {
            'ventas' => ['Producto', 'Cantidad', 'Ventas', 'Costo', 'Margen'],
            'clientes' => ['Cliente', 'Visitas', 'Gastado'],
            'reservas' => ['Concepto', 'Valor'],
            default => ['Cuenta', 'Total', 'Movimientos'],
        };
    }

    private function filas(string $reporte, array $datos): array
    {
        return match ($reporte) {
            'ventas' => collect($datos['por_producto'])->map(fn ($fila) => [$fila['producto'], $fila['cantidad'], $fila['ventas'], $fila['costo'], $fila['margen']])->all(),
            'clientes' => collect($datos['top_clientes'])->map(fn ($fila) => [$fila['cliente'], $fila['visitas'], $fila['gastado']])->all(),
            'reservas' => [["{$datos['resumen']['total']} total reservas", ''], ["{$datos['resumen']['confirmadas']} confirmadas", $datos['resumen']['cumplimiento_porcentaje'] . '%'], ["{$datos['resumen']['canceladas']} canceladas", ''], ["{$datos['resumen']['no_shows']} no-shows", '']],
            default => collect($datos['resultado']['detalle']['ingresos'])->map(fn ($fila) => [$fila['cuenta'], $fila['total'], $fila['movimientos']])->all(),
        };
    }
}