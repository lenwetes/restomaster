#!/usr/bin/env bash
# ==============================================================================
# RestoMaster — Script Nocturno de Respaldo Automatizado con pg_dump y Rotación
# Destino: Almacenamiento de objetos (Cloudflare R2 o AWS S3)
# ==============================================================================
set -eo pipefail

TIMESTAMP=$(date +"%Y-%m-%d_%H%M%S")
BACKUP_DIR="${BACKUP_PATH:-/var/www/html/storage/app/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
DISK="${STORAGE_DISK:-r2}"

mkdir -p "$BACKUP_DIR"

echo "================================================================"
echo " [$(date +"%Y-%m-%d %H:%M:%S")] Iniciando backup nocturno RestoMaster"
echo "================================================================"

# Ejecutar el comando artisan con soporte binario pg_dump y subida a la nube
php artisan restomaster:backup --dump --cloud --disk="$DISK" --keep="$RETENTION_DAYS" --keep-remote="$RETENTION_DAYS"

echo "================================================================"
echo " [$(date +"%Y-%m-%d %H:%M:%S")] Respaldo nocturno finalizado con éxito."
echo "================================================================"
