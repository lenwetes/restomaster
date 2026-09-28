<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Libro Fiscal de Operaciones Diarias - DIAN</title>
    <style>
        @page { size: 279mm 216mm landscape; margin: 10mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1c1917; margin: 0; padding: 0; }
        .header { border-bottom: 2px solid #ab2d1b; padding-bottom: 8px; margin-bottom: 12px; }
        .titulo-principal { font-size: 14px; font-weight: bold; color: #ab2d1b; text-transform: uppercase; margin: 0 0 2px 0; }
        .subtitulo { font-size: 11px; font-weight: bold; color: #1c1917; margin: 0 0 4px 0; }
        .meta-empresa { font-size: 9px; color: #44403c; line-height: 1.3; }
        .caja-periodo { float: right; text-align: right; font-size: 9px; color: #57534e; }
        .caja-periodo strong { color: #1c1917; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 8.5px; }
        th { background-color: #f5f5f4; color: #292524; font-weight: bold; text-align: right; padding: 5px 4px; border: 1px solid #d6d3d1; font-size: 8px; text-transform: uppercase; }
        th.text-left { text-align: left; }
        th.text-center { text-align: center; }
        td { border: 1px solid #e7e5e4; padding: 4.5px 4px; text-align: right; }
        td.text-left { text-align: left; }
        td.text-center { text-align: center; }
        tr:nth-child(even) td { background-color: #fafaf9; }
        tr.fila-totales td { font-weight: bold; background-color: #f5f5f4; border-top: 2px solid #1c1917; border-bottom: 2px solid #1c1917; font-size: 9px; }
        
        .nota-legal { margin-top: 15px; font-size: 7.5px; color: #78716c; line-height: 1.3; border: 1px dashed #d6d3d1; padding: 6px; border-radius: 4px; }
        .firmas { margin-top: 28px; width: 100%; }
        .firma-col { width: 45%; float: left; text-align: center; font-size: 8.5px; }
        .linea-firma { border-top: 1px solid #1c1917; margin: 30px auto 4px auto; width: 80%; }
        .texto-devolucion { color: #dc2626; font-weight: bold; }
        .saldo-positivo { font-weight: bold; color: #15803d; }
        .saldo-negativo { font-weight: bold; color: #b91c1c; }
        .texto-rojo { color: #dc2626; }
        .texto-verde { color: #15803d; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="caja-periodo">
            <div><strong>Período:</strong> {{ $libro['periodo']['desde_formateado'] }} al {{ $libro['periodo']['hasta_formateado'] }}</div>
            <div><strong>Generado:</strong> {{ $libro['periodo']['generado_en'] }}</div>
            <div><strong>Días con Movimiento:</strong> {{ $libro['totales']['dias_con_movimiento'] }}</div>
        </div>
        <h1 class="titulo-principal">Libro Fiscal de Operaciones Diarias</h1>
        <div class="subtitulo">{{ $libro['empresa']['razon_social'] }} · NIT {{ $libro['empresa']['nit'] }}</div>
        <div class="meta-empresa">
            {{ $libro['empresa']['direccion'] }} · {{ $libro['empresa']['ciudad'] }}<br>
            <strong>Régimen:</strong> {{ $libro['empresa']['regimen'] }}
            @if(!empty($libro['empresa']['resolucion_dian']))
                · <strong>Resolución:</strong> {{ $libro['empresa']['resolucion_dian'] }}
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 8%;">Fecha</th>
                <th class="text-left" style="width: 10%;">Comp. Inicial</th>
                <th class="text-left" style="width: 10%;">Comp. Final</th>
                <th class="text-center" style="width: 6%;">N° Op.</th>
                <th style="width: 11%;">Ingresos Brutos</th>
                <th style="width: 11%;">Base Gravable</th>
                <th style="width: 10%;">INC (8%)</th>
                <th style="width: 10%;">Devoluciones</th>
                <th style="width: 11%;">Ingresos Netos</th>
                <th style="width: 11%;">Costos/Gastos</th>
                <th style="width: 12%;">Saldo Diario</th>
            </tr>
        </thead>
        <tbody>
            @foreach($libro['dias'] as $dia)
                <tr>
                    <td class="text-center">{{ $dia['fecha_formateada'] }}</td>
                    <td class="text-left">{{ $dia['comprobante_inicial'] }}</td>
                    <td class="text-left">{{ $dia['comprobante_final'] }}</td>
                    <td class="text-center">{{ $dia['total_operaciones'] }}</td>
                    <td>$ {{ number_format($dia['ingresos_brutos'], 0, ',', '.') }}</td>
                    <td>$ {{ number_format($dia['base_gravable'], 0, ',', '.') }}</td>
                    <td>$ {{ number_format($dia['impuesto_consumo_inc'], 0, ',', '.') }}</td>
                    <td class="{{ $dia['total_devoluciones'] > 0 ? 'texto-devolucion' : '' }}">
                        {{ $dia['total_devoluciones'] > 0 ? '-$ '.number_format($dia['total_devoluciones'], 0, ',', '.') : '$ 0' }}
                    </td>
                    <td class="font-bold">$ {{ number_format($dia['ingresos_netos'], 0, ',', '.') }}</td>
                    <td>$ {{ number_format($dia['gastos_diarios_caja'], 0, ',', '.') }}</td>
                    <td class="{{ $dia['saldo_neto_fiscal'] >= 0 ? 'saldo-positivo' : 'saldo-negativo' }}">
                        $ {{ number_format($dia['saldo_neto_fiscal'], 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
            <tr class="fila-totales">
                <td colspan="3" class="text-left">TOTALES DEL PERÍODO</td>
                <td class="text-center">{{ $libro['totales']['total_operaciones'] }}</td>
                <td>$ {{ number_format($libro['totales']['ingresos_brutos'], 0, ',', '.') }}</td>
                <td>$ {{ number_format($libro['totales']['base_gravable'], 0, ',', '.') }}</td>
                <td>$ {{ number_format($libro['totales']['impuesto_consumo_inc'], 0, ',', '.') }}</td>
                <td class="texto-rojo">-$ {{ number_format($libro['totales']['total_devoluciones'], 0, ',', '.') }}</td>
                <td>$ {{ number_format($libro['totales']['ingresos_netos'], 0, ',', '.') }}</td>
                <td>$ {{ number_format($libro['totales']['gastos_diarios_caja'], 0, ',', '.') }}</td>
                <td class="texto-verde">$ {{ number_format($libro['totales']['saldo_neto_fiscal'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="nota-legal">
        <strong>BASE LEGAL DIAN:</strong> El presente documento se expide en cumplimiento del Artículo 616-1 del Estatuto Tributario y Decretos Reglamentarios vigentes en la República de Colombia. Registra cronológicamente las operaciones diarias de ingresos devengados y costos/gastos incurridos en el establecimiento de comercio, identificando el comprobante inicial y final de cada jornada. Debe mantenerse permanentemente a disposición de los funcionarios de fiscalización de la DIAN.
    </div>

    <div class="firmas">
        <div class="firma-col" style="margin-left: 5%;">
            <div class="linea-firma"></div>
            <strong>REPRESENTANTE LEGAL / RESPONSABLE</strong><br>
            C.C. _______________________________
        </div>
        <div class="firma-col" style="float: right; margin-right: 5%;">
            <div class="linea-firma"></div>
            <strong>CONTADOR PÚBLICO / REVISOR FISCAL</strong><br>
            T.P. N° _____________________________
        </div>
        <div style="clear: both;"></div>
    </div>
</body>
</html>
