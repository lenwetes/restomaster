<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 216mm 279mm; margin: 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1c1917; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .empresa { font-size: 11px; color: #57534e; margin-bottom: 2px; }
        .meta { color: #78716c; font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; border-bottom: 2px solid #ab2d1b; padding: 6px 4px; font-size: 10px; text-transform: uppercase; }
        td { border-bottom: 1px solid #d6d3d1; padding: 5px 4px; }
        .total { font-weight: bold; border-top: 2px solid #1c1917; }
        .footer { margin-top: 18px; font-size: 9px; color: #78716c; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $razon_social }}</h1>
    <div class="empresa">NIT {{ $nit ?: '—' }}</div>
    <div class="meta">Reporte: {{ ucfirst($reporte) }} · del {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }} · Generado: {{ $generado }}</div>

    @if ($reporte === 'ventas' || $reporte === 'estado')
        <table>
            <thead>
                <tr><th>Concepto</th><th>Transacciones</th><th>Valor</th></tr>
            </thead>
            <tbody>
                @foreach (($datos['por_producto'] ?? ($datos['resultado']['detalle']['ingresos'] ?? [])) as $fila)
                    <tr><td>{{ $fila['producto'] ?? $fila['cuenta'] }}</td><td>{{ $fila['cantidad'] ?? $fila['movimientos'] }}</td><td>$ {{ number_format($fila['ventas'] ?? $fila['total'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($reporte === 'clientes')
        <table>
            <thead><tr><th>Cliente</th><th>Visitas</th><th>Gastado</th></tr></thead>
            <tbody>
                @foreach ($datos['top_clientes'] as $fila)
                    <tr><td>{{ $fila['cliente'] }}</td><td>{{ $fila['visitas'] }}</td><td>$ {{ number_format($fila['gastado'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p>Tiempo promedio de entrega: {{ $datos['tiempos']['promedio_min'] }} min ({{ $datos['tiempos']['entregados'] }} entregas).</p>
    @elseif ($reporte === 'meseros')
        <table>
            <thead><tr><th>Mesero</th><th>Comandas</th><th>Ventas Netas</th><th>Propinas</th><th>Total Recaudado</th><th>Ticket Prom.</th></tr></thead>
            <tbody>
                @foreach (($datos['resumen']['meseros'] ?? []) as $fila)
                    <tr>
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['comandas_cerradas'] }}</td>
                        <td>$ {{ number_format($fila['ventas_netas'], 0, ',', '.') }}</td>
                        <td>$ {{ number_format($fila['propinas_recaudadas'], 0, ',', '.') }}</td>
                        <td>$ {{ number_format($fila['total_con_propina'], 0, ',', '.') }}</td>
                        <td>$ {{ number_format($fila['ticket_promedio'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>TOTAL GENERAL</td>
                    <td>{{ $datos['resumen']['totales']['total_comandas'] ?? 0 }}</td>
                    <td>$ {{ number_format($datos['resumen']['totales']['total_ventas_netas'] ?? 0, 0, ',', '.') }}</td>
                    <td>$ {{ number_format($datos['resumen']['totales']['total_propinas'] ?? 0, 0, ',', '.') }}</td>
                    <td>$ {{ number_format($datos['resumen']['totales']['total_con_propinas'] ?? 0, 0, ',', '.') }}</td>
                    <td>$ {{ number_format($datos['resumen']['totales']['ticket_promedio_general'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @elseif ($reporte === 'reservas')
        <table>
            <thead><tr><th>Métrica</th><th>Valor</th></tr></thead>
            <tbody>
                <tr><td>Total reservas</td><td>{{ $datos['resumen']['total'] }}</td></tr>
                <tr><td>Confirmadas</td><td>{{ $datos['resumen']['confirmadas'] }}</td></tr>
                <tr><td>Canceladas</td><td>{{ $datos['resumen']['canceladas'] }}</td></tr>
                <tr><td>No se mostraron</td><td>{{ $datos['resumen']['no_shows'] }}</td></tr>
                <tr class="total"><td>Cumplimiento</td><td>{{ $datos['resumen']['cumplimiento_porcentaje'] }}%</td></tr>
            </tbody>
        </table>
    @endif

    <div class="footer">RestoMaster · {{ $razon_social }} · Documento generado por el sistema</div>
</body>
</html>