<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asuntoPersonalizado ?? 'Tu opinión nos importa - RestoMaster' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 580px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .header .badge {
            display: inline-block;
            background: #e11d48;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 9999px;
            margin-top: 8px;
            letter-spacing: 0.5px;
        }
        .body-content {
            padding: 36px 32px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .text {
            font-size: 15px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 24px;
        }
        .points-box {
            background: #fff1f2;
            border: 1px dashed #f43f5e;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            margin-bottom: 28px;
        }
        .points-box strong {
            color: #be123c;
            font-size: 16px;
        }
        .cta-container {
            text-align: center;
            margin: 32px 0 20px 0;
        }
        .btn-cta {
            display: inline-block;
            background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
            color: #ffffff !important;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(225, 29, 72, 0.35);
        }
        .footer {
            background: #f1f5f9;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>RestoMaster</h1>
            <div class="badge">Experiencia & Calidad VIP</div>
        </div>

        <div class="body-content">
            <div class="greeting">¡Hola, {{ $cliente->nombre }}! 🍣</div>

            @if(!empty($contenidoHtml))
                <div class="text">
                    {!! $contenidoHtml !!}
                </div>
            @else
                <div class="text">
                    Gracias por habernos acompañado en <strong>RestoMaster</strong>. Tu satisfacción y la calidad de cada plato son nuestra mayor prioridad.
                </div>

                <div class="points-box">
                    🎁 <strong>¡Gana +50 Puntos de Fidelidad VIP!</strong><br>
                    <span style="font-size: 13px; color: #881337;">Solo toma 30 segundos calificar tu servicio y plato favorito.</span>
                </div>

                <div class="cta-container">
                    <a href="{{ $urlEncuesta }}" class="btn-cta">
                        ⭐ Calificar mi Experiencia
                    </a>
                </div>

                <div class="text" style="font-size: 13px; color: #94a3b8; text-align: center; margin-top: 20px;">
                    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                    <a href="{{ $urlEncuesta }}" style="color: #e11d48; word-break: break-all;">{{ $urlEncuesta }}</a>
                </div>
            @endif
        </div>

        <div class="footer">
            © {{ date('Y') }} RestoMaster. Todos los derechos reservados.<br>
            Este mensaje fue enviado a {{ $cliente->email }} porque visitaste nuestras instalaciones o autorizaste notificaciones.
        </div>
    </div>
</body>
</html>
