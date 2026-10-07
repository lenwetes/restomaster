<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 216mm 279mm; margin: 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1c1917; }
        .membrete { border-bottom: 3px solid #ab2d1b; padding-bottom: 8px; margin-bottom: 12px; }
        .membrete h1 { font-size: 18px; margin: 0; }
        .membrete .sub { font-size: 11px; color: #57534e; }
        .meta { color: #78716c; font-size: 10px; margin-bottom: 12px; }
        h2 { font-size: 13px; margin: 14px 0 6px; color: #ab2d1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { text-align: left; border-bottom: 2px solid #ab2d1b; padding: 6px 4px; font-size: 10px; text-transform: uppercase; }
        td { border-bottom: 1px solid #d6d3d1; padding: 5px 4px; }
        .kpis { width: 100%; margin-top: 6px; }
        .kpis td { border: 1px solid #d6d3d1; padding: 8px; text-align: center; }
        .kpi-valor { font-size: 14px; font-weight: bold; }
        .kpi-delta { font-size: 10px; }
        .analisis { background: #faf7f5; border: 1px solid #d6d3d1; padding: 10px; margin-top: 6px; }
        .analisis li { margin-bottom: 4px; }
        .footer { margin-top: 18px; font-size: 9px; color: #78716c; text-align: center; border-top: 1px solid #d6d3d1; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="membrete">
        <h1>{{ $razon_social }} — Informe Ejecutivo</h1>
        <div class="sub">NIT {{ $nit ?: '—' }} · Comparativa {{ $filtros['desde'] }} al {{ $filtros['hasta'] }}</div>
    </div>
    <div class="meta">Generado: {{ $generado }}</div>

    <h2>KPIs clave con variación</h2>
    <table class="kpis">
        <tr>
            <td>
                <div>Ventas brutas</div>
                <div class="kpi-valor">$ {{ number_format($datos['comparativa']['a']['ventas'], 0, ',', '.') }}</div>
                <div class="kpi-delta">Δ {{ $datos['comparativa']['delta_ventas_pct'] }}%</div>
            </td>
            <td>
                <div>Comandas</div>
                <div class="kpi-valor">{{ $datos['comparativa']['a']['comandas'] }}</div>
                <div class="kpi-delta">Δ {{ $datos['comparativa']['delta_comandas_pct'] }}%</div>
            </td>
            <td>
                <div>Ticket promedio</div>
                <div class="kpi-valor">$ {{ number_format($datos['comparativa']['a']['ticket_promedio'], 0, ',', '.') }}</div>
                <div class="kpi-delta">Δ $ {{ number_format($datos['comparativa']['delta_ticket'], 0, ',', '.') }}</div>
            </td>
            <td>
                <div>Devoluciones</div>
                <div class="kpi-valor">$ {{ number_format($datos['comparativa']['a']['devoluciones'], 0, ',', '.') }}</div>
                <div class="kpi-delta">Δ {{ $datos['comparativa']['delta_devoluciones_pct'] }}%</div>
            </td>
        </tr>
    </table>

    <h2>Comparativa A vs B ({{ $datos['comparativa']['a']['desde'] }} — {{ $datos['comparativa']['a']['hasta'] }} vs {{ $datos['comparativa']['b']['desde'] }} — {{ $datos['comparativa']['b']['hasta'] }})</h2>
    <table>
        <thead><tr><th>Etiqueta</th><th>Ventas A</th><th>Ventas acumuladas A</th></tr></thead>
        <tbody>
            @foreach ($datos['serie']['etiquetas'] as $i => $etiqueta)
                <tr>
                    <td>{{ $etiqueta }}</td>
                    <td>$ {{ number_format($datos['serie']['ventas'][$i] ?? 0, 0, ',', '.') }}</td>
                    <td>$ {{ number_format($datos['serie']['acumulada'][$i] ?? 0, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Top 5 productos del periodo</h2>
    <table>
        <thead><tr><th>Producto</th><th>Cantidad</th><th>Ventas</th></tr></thead>
        <tbody>
            @foreach ($datos['top'] as $fila)
                <tr><td>{{ $fila['producto'] }}</td><td>{{ $fila['cantidad'] }}</td><td>$ {{ number_format($fila['total_ventas'], 0, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Análisis generado por IA ({{ $datos['analisis']['tendencia'] }})</h2>
    <div class="analisis">
        <p>{{ strip_tags($datos['analisis']['infografia']['titulo'] ?? '') }} — tendencia: {{ $datos['analisis']['tendencia'] }} (Δ {{ $datos['analisis']['delta_ventas_pct'] }}%).</p>
        <ul>
            @foreach ($datos['analisis']['recomendaciones'] as $rec)
                <li>{{ $rec }}</li>
            @endforeach
        </ul>
    </div>

    <div class="footer">Generado por RestoMaster IA · {{ $generado }}</div>
</body>
</html>
