@echo off
chcp 65001 >nul
title 🚀 Sushixpress — Sprint Final (9 Fases)
color 0A

echo.
echo  ╔══════════════════════════════════════════════════════════╗
echo  ║      🚀 SUSHIXPRESS — LANZADOR SPRINT FINAL             ║
echo  ║         9 Fases · 25 Sub-tareas · 13 Test Classes       ║
echo  ╚══════════════════════════════════════════════════════════╝
echo.
echo  📋 Plan: PLAN_MAESTRO_SPRINT_FINAL.md
echo  📌 Lanzador: LANZADOR_MANANA.md
echo.
echo  ── BLOQUE I: Motor IA y Turnos ──────────────────────────
echo  FASE 1  ⚡  Hotfix Copiloto IA
echo  FASE 2  🧠  Function Calling Gemini (5 tools)
echo  FASE 3  📅  Programacion Semanal de Turnos
echo  FASE 4  🔔  Aviso Modal Login Mesero
echo  FASE 5  🧪  Tests Bloque I + npm run build
echo.
echo  ── BLOQUE II: Frontend, RRHH, Caja y Reportes ──────────
echo  FASE 6  📱  Frontend Movil + Botones Compartir Redes
echo  FASE 7  👥  Autorrotacion Equitativa (4 semanas)
echo  FASE 8  💳  Centralizacion Cobros + NC Obligatoria
echo  FASE 9  📊  Reportes Comparativos + IA PDF Ejecutivo
echo.

cd /d "D:\Proyectos\restomaster"

echo  [1/3] Verificando entorno...
call php artisan --version >nul 2>&1
if %errorlevel% neq 0 (
    echo  ❌ ERROR: PHP / Laravel no encontrado.
    pause
    exit /b 1
)
echo  ✓ Laravel OK

call php artisan migrate:status >nul 2>&1
if %errorlevel% neq 0 (
    echo  ⚠ ADVERTENCIA: No se pudo verificar el estado de migraciones.
) else (
    echo  ✓ Base de datos accesible
)

echo.
echo  [2/3] Abriendo archivos de referencia...
start "" "PLAN_MAESTRO_SPRINT_FINAL.md"
start "" "LANZADOR_MANANA.md"

echo.
echo  [3/3] Iniciando servidor de desarrollo...
echo.
echo  ┌─────────────────────────────────────────────────────────┐
echo  │  COPIA este prompt en el chat del agente:               │
echo  │                                                         │
echo  │  "Ejecuta el PLAN_MAESTRO_SPRINT_FINAL.md de           │
echo  │   Sushixpress — implementa las 9 fases en orden        │
echo  │   estricto sin detenerte hasta que todos los tests      │
echo  │   estén en verde y npm run build compile sin errores."  │
echo  └─────────────────────────────────────────────────────────┘
echo.

start cmd /k "title Redis && cd /d D:\Redis && redis-server.exe"
timeout /t 2 /nobreak >nul

start cmd /k "title Reverb WebSocket && cd /d D:\Proyectos\restomaster && php artisan reverb:start --host=127.0.0.1 --port=8080"
timeout /t 2 /nobreak >nul

start cmd /k "title Queue Worker && cd /d D:\Proyectos\restomaster && php artisan queue:work --sleep=3 --tries=3"
timeout /t 1 /nobreak >nul

start cmd /k "title Laravel Dev && cd /d D:\Proyectos\restomaster && php artisan serve"

echo.
echo  ✅ Entorno levantado:
echo     • Redis        → 127.0.0.1:6379
echo     • Reverb WS    → 127.0.0.1:8080
echo     • Queue Worker → background
echo     • Laravel      → http://127.0.0.1:8000
echo.
echo  Presiona cualquier tecla para cerrar este lanzador...
pause >nul
