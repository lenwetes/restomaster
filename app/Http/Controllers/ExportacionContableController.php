<?php

namespace App\Http\Controllers;

use App\Services\ExportadorContableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportacionContableController extends Controller
{
    public function __construct(
        protected ExportadorContableService $exportadorService
    ) {}

    /**
     * Exportación de Comprobantes Contables para Siigo Nube.
     */
    public function siigo(Request $request): Response
    {
        [$desde, $hasta] = $this->validarRango($request);
        $data = $this->exportadorService->exportarSiigo($desde, $hasta);
        $csv = $this->exportadorService->formatearCsv($data['encabezados'], $data['filas'], ';');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"siigo_comprobantes_{$desde}_{$hasta}.csv\"",
        ]);
    }

    /**
     * Exportación de Facturas de Venta para Alegra.
     */
    public function alegra(Request $request): Response
    {
        [$desde, $hasta] = $this->validarRango($request);
        $data = $this->exportadorService->exportarAlegra($desde, $hasta);
        $csv = $this->exportadorService->formatearCsv($data['encabezados'], $data['filas'], ';');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"alegra_ventas_{$desde}_{$hasta}.csv\"",
        ]);
    }

    /**
     * Exportación de Archivo Plano para World Office.
     */
    public function worldOffice(Request $request): Response
    {
        [$desde, $hasta] = $this->validarRango($request);
        $txt = $this->exportadorService->exportarWorldOffice($desde, $hasta);

        return response($txt, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"world_office_{$desde}_{$hasta}.txt\"",
        ]);
    }

    /**
     * Exportación de Archivo Plano para Helisa.
     */
    public function helisa(Request $request): Response
    {
        [$desde, $hasta] = $this->validarRango($request);
        $txt = $this->exportadorService->exportarHelisa($desde, $hasta);

        return response($txt, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"helisa_diario_{$desde}_{$hasta}.txt\"",
        ]);
    }

    /**
     * Exportación del Libro Fiscal de Operaciones Diarias (DIAN Art. 616-1) en PDF o CSV.
     */
    public function libroFiscal(Request $request): Response
    {
        [$desde, $hasta] = $this->validarRango($request);
        $formato = $request->query('formato', 'pdf');
        $libro = $this->exportadorService->generarLibroFiscalDian($desde, $hasta);

        if ($formato === 'csv') {
            $encabezados = [
                'Fecha',
                'Comprobante Inicial',
                'Comprobante Final',
                'Total Operaciones',
                'Ingresos Brutos',
                'Base Gravable',
                'INC (8%)',
                'Devoluciones',
                'Ingresos Netos',
                'Gastos Diarios Caja',
                'Saldo Diario Fiscal',
            ];

            $filas = [];
            foreach ($libro['dias'] as $dia) {
                $filas[] = [
                    $dia['fecha_formateada'],
                    $dia['comprobante_inicial'],
                    $dia['comprobante_final'],
                    $dia['total_operaciones'],
                    $dia['ingresos_brutos'],
                    $dia['base_gravable'],
                    $dia['impuesto_consumo_inc'],
                    $dia['total_devoluciones'],
                    $dia['ingresos_netos'],
                    $dia['gastos_diarios_caja'],
                    $dia['saldo_neto_fiscal'],
                ];
            }

            // Fila de totales
            $filas[] = [
                'TOTALES',
                '—',
                '—',
                $libro['totales']['total_operaciones'],
                $libro['totales']['ingresos_brutos'],
                $libro['totales']['base_gravable'],
                $libro['totales']['impuesto_consumo_inc'],
                $libro['totales']['total_devoluciones'],
                $libro['totales']['ingresos_netos'],
                $libro['totales']['gastos_diarios_caja'],
                $libro['totales']['saldo_neto_fiscal'],
            ];

            $csv = $this->exportadorService->formatearCsv($encabezados, $filas, ';');

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"libro_fiscal_dian_{$desde}_{$hasta}.csv\"",
            ]);
        }

        // Generar PDF Apaisado
        $pdf = Pdf::loadView('pdf.libro-fiscal-dian', [
            'libro' => $libro,
        ])->setPaper('letter', 'landscape');

        return $pdf->download("libro_fiscal_dian_{$desde}_{$hasta}.pdf");
    }

    /**
     * Valida el rango de fechas para exportaciones contables.
     *
     * @return array{0: string, 1: string}
     */
    protected function validarRango(Request $request): array
    {
        $validated = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $desdeCarbon = Carbon::parse($validated['desde']);
        $hastaCarbon = Carbon::parse($validated['hasta']);

        if ($desdeCarbon->diffInDays($hastaCarbon) > 366) {
            abort(422, 'El rango de fechas contable no puede superar los 366 días (un año fiscal).');
        }

        return [$validated['desde'], $validated['hasta']];
    }
}
