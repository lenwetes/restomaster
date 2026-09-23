<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ruta de respaldos automáticos
    |--------------------------------------------------------------------------
    |
    | Directorio donde restomaster:backup y restomaster:backup-storage guardan
    | sus archivos con rotación. Para copia fuera del servidor, apuntar
    | BACKUP_PATH a un disco/volumen montado externo al despliegue.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('app/backups')),

];
