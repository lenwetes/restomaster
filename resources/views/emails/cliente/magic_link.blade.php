<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Seguro a RestoMaster</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 30px 15px; }
        .card { max-width: 480px; margin: 0 auto; background: #1e293b; border-radius: 16px; border: 1px solid #334155; padding: 32px 24px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4); text-align: center; }
        .logo { font-size: 24px; font-weight: 800; color: #f43f5e; letter-spacing: -0.5px; margin-bottom: 24px; }
        .logo span { color: #f8fafc; }
        h1 { font-size: 20px; font-weight: 700; color: #f8fafc; margin: 0 0 12px 0; }
        p { font-size: 14px; line-height: 1.6; color: #94a3b8; margin: 0 0 24px 0; }
        .btn { display: inline-block; background: linear-gradient(135deg, #e11d48, #be123c); color: #ffffff !important; text-decoration: none; font-weight: 600; font-size: 15px; padding: 14px 32px; border-radius: 10px; box-shadow: 0 4px 14px rgba(225, 29, 72, 0.35); }
        .footer { margin-top: 28px; padding-top: 20px; border-top: 1px solid #334155; font-size: 12px; color: #64748b; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Resto<span>Master</span> 🍣</div>
        <h1>Tu Enlace Seguro de Acceso</h1>
        <p>Has solicitado iniciar sesión en el portal de clientes de RestoMaster. Haz clic en el botón de abajo para ingresar directamente a tu cuenta sin contraseña:</p>
        <div style="margin: 30px 0;">
            <a href="{{ $magicUrl }}" class="btn" target="_blank">Ingresar a mi Perfil VIP</a>
        </div>
        <p style="font-size: 12px; color: #64748b;">Este enlace es único y expirará en 20 minutos. Si tú no solicitaste este acceso, puedes ignorar este correo con total tranquilidad.</p>
        <div class="footer">
            RestoMaster — Sistema Integral de Gestión Gastronómica<br>
            Seguridad y Privacidad de Datos Garantizada
        </div>
    </div>
</body>
</html>
