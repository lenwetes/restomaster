<?php

namespace App\Http\Controllers;

use App\Services\Ai\AdminAiCopilotService;
use App\Services\ConfiguracionService;
use App\Services\ReportesComparativosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InformeEjecutivoController extends Controller
{
    /**
     * Informe PDF ejecutivo con KPIs, comparativa, análisis IA y gráficos en tabla.
     * Acceso: solo admin y gerente.
     */
    public function descargar(Request $request): Response
    {
        $this->autorizar($request);

        $filtros = $this->validarFiltros($request);
        $datos = $this->construirDatos($filtros, $request->user());

        $empresa = app(ConfiguracionService::class);
        $pdf = Pdf::loadView('pdf.informe-ejecutivo', [
            'razon_social' => $empresa->obtener('general', 'razon_social', 'RestoMaster'),
            'nit' => $empresa->obtener('general', 'nit', ''),
            'filtros' => $filtros,
            'datos' => $datos,
            'generado' => now()->format('d/m/Y H:i'),
        ])->setPaper('letter', 'portrait');

        return $pdf->download("informe-ejecutivo-{$filtros['desde']}-{$filtros['hasta']}.pdf");
    }

    /**
     * Exporta la comparativa A vs B en CSV.
     * Acceso: solo admin y gerente.
     */
    public function csv(Request $request): Response
    {
        $this->autorizar($request);

        $filtros = $this->validarFiltros($request);
        $comparativa = $this->comparativa($filtros, $request->user()?->sucursal_id);
        $csv = app(ReportesComparativosService::class)->exportarCsv($comparativa);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="comparativa-'.$filtros['desde'].'-'.$filtros['hasta'].'.csv"',
        ]);
    }

    protected function autorizar(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isGerente()), 403);
    }

    protected function validarFiltros(Request $request): array
    {
        $validated = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'comparar' => ['nullable', 'boolean'],
            'desde_b' => ['nullable', 'date'],
            'hasta_b' => ['nullable', 'date', 'after_or_equal:desde_b'],
        ]);

        if (Carbon::parse($validated['desde'])->diffInDays(Carbon::parse($validated['hasta'])) > 366) {
            abort(422, 'El rango de fechas no puede superar los 366 días.');
        }

        return $validated;
    }

    protected function comparativa(array $filtros, ?int $sucursalId): array
    {
        $svc = app(ReportesComparativosService::class);
        $comparar = filter_var($filtros['comparar'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($comparar && ! empty($filtros['desde_b']) && ! empty($filtros['hasta_b'])) {
            return $svc->comparar($filtros['desde'], $filtros['hasta'], $filtros['desde_b'], $filtros['hasta_b'], $sucursalId);
        }

        [$desdeB, $hastaB] = $svc->periodoAnteriorAutomatico($filtros['desde'], $filtros['hasta']);

        return $svc->comparar($filtros['desde'], $filtros['hasta'], $desdeB, $hastaB, $sucursalId);
    }

    protected function construirDatos(array $filtros, $usuario): array
    {
        $svc = app(ReportesComparativosService::class);
        $sucursalId = $usuario?->sucursal_id;

        $comparativa = $this->comparativa($filtros, $sucursalId);
        $serie = $svc->seriePorPeriodo($filtros['desde'], $filtros['hasta'], $sucursalId);
        $top = $svc->topPeriodo($filtros['desde'], $filtros['hasta'], 5, $sucursalId);
        $analisis = app(AdminAiCopilotService::class)->analizarReporte($filtros, $usuario);

        return [
            'comparativa' => $comparativa,
            'serie' => $serie,
            'top' => $top,
            'analisis' => $analisis['datos'],
            'resumen_ia' => $analisis['mensaje'],
        ];
    }
}
