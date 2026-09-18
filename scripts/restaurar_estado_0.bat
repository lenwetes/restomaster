@echo off
chcp 65001 >nul
echo ==========================================================
echo  RestoMaster — Restaurar a ESTADO 0 (Base Limpia)
echo ==========================================================
echo ATENCIÓN: Esta acción purgará pedidos, mesas, productos e
echo inventario, dejando solo los 7 roles y 1 usuario por rol.
echo ==========================================================
set /p CONFIRMA=¿Está seguro de restaurar a Estado 0? (s/N): 
if /i not "%CONFIRMA%"=="s" (
    echo Operación cancelada.
    pause
    exit /b 0
)

powershell -ExecutionPolicy Bypass -File "%~dp0restaurar_estado_0.ps1" -Force
pause
