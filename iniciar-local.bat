@echo off
title RestoMaster - Entorno Local Completo (WebSockets + Redis + App)
echo ================================================================
echo        RESTOMASTER - INICIO DE SERVICIOS EN LOCAL
echo ================================================================
echo.

echo 1. Verificando Servicio de Redis en Windows (Puerto 6379)...
sc start Redis >nul 2>&1
echo    [OK] Redis activo y escuchando en 127.0.0.1:6379.
echo.

echo 2. Iniciando Servidor WebSockets (Laravel Reverb en Puerto 8080)...
start "RestoMaster - Reverb WebSockets (8080)" php artisan reverb:start --debug
echo    [OK] Servidor de WebSockets (Reverb) iniciado en http://localhost:8080.
echo.

echo 3. Iniciando Servidor Web de Laravel (Puerto 8000)...
echo    [OK] Abriendo aplicacion en http://localhost:8000
echo ================================================================
echo Para detener la aplicacion, presiona Ctrl+C en esta ventana.
echo ================================================================
php artisan serve
