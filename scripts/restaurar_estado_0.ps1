# ==============================================================================
# RestoMaster — Script de Restauración a ESTADO 0
# Restaura la base de datos limpia con únicamente los 7 roles y 1 usuario por rol.
# ==============================================================================

param(
    [switch]$Dump,  # Si se especifica, usa el dump SQL nativo database/dumps/estado_0.sql
    [switch]$Force  # Omite confirmación interactiva
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " RestoMaster — Restauración a ESTADO 0 (Base Limpia)" -ForegroundColor Yellow
Write-Host "==========================================================" -ForegroundColor Cyan

$cmdArgs = @("artisan", "restomaster:estado-cero")

if ($Force) {
    $cmdArgs += "--force"
}

if ($Dump) {
    $cmdArgs += "--dump"
}

& php @cmdArgs
