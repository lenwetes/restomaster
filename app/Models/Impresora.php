<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class Impresora extends Model
{
    use HasFactory;

    protected $table = 'impresoras';

    protected $fillable = [
        'nombre',
        'tipo_conexion',
        'driver_nombre',
        'ip_address',
        'puerto',
        'area',
        'ancho_columnas',
        'copias',
        'activa',
        'descripcion',
    ];

    protected $casts = [
        'puerto' => 'integer',
        'ancho_columnas' => 'integer',
        'copias' => 'integer',
        'activa' => 'boolean',
    ];

    public function trabajos(): HasMany
    {
        return $this->hasMany(TrabajoImpresion::class, 'impresora_id')->latest();
    }

    public function trabajosPendientes(): HasMany
    {
        return $this->hasMany(TrabajoImpresion::class, 'impresora_id')->where('estado', 'pendiente');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopePorArea(Builder $query, string $area): Builder
    {
        return $query->where(function ($q) use ($area) {
            $q->where('area', $area)
                ->orWhere('area', 'todas');
        });
    }

    /**
     * Prueba la conectividad según el tipo de conexión (Socket TCP, Spooler Local Windows o Navegador).
     */
    public function probarConexion(float $timeout = 1.5): array
    {
        if ($this->tipo_conexion === 'virtual_simulador') {
            return [
                'ok' => true,
                'mensaje' => 'Impresora virtual activa (Modo Simulación).',
                'latencia_ms' => 0.5,
            ];
        }

        if ($this->tipo_conexion === 'driver_navegador') {
            return [
                'ok' => true,
                'mensaje' => 'Canal de impresión directo por navegador web (@media print 80mm/58mm) habilitado.',
                'latencia_ms' => 0.1,
            ];
        }

        // Impresora USB o controlador local del sistema operativo
        if (in_array($this->tipo_conexion, ['usb_local', 'driver_sistema', 'usb_compartida'], true)) {
            $driver = trim($this->driver_nombre ?: $this->ip_address ?: '');
            if (empty($driver)) {
                return [
                    'ok' => false,
                    'mensaje' => 'No se ha configurado el nombre de la impresora en el sistema operativo.',
                    'latencia_ms' => null,
                ];
            }

            $inicio = microtime(true);

            try {
                if (PHP_OS_FAMILY === 'Windows') {
                    // Escapar apóstrofes para PowerShell
                    $safeDriver = str_replace("'", "''", $driver);
                    $comando = "powershell.exe -NoProfile -NonInteractive -Command \"Get-Printer -Name '{$safeDriver}' -ErrorAction Stop | Select-Object -ExpandProperty PrinterStatus\"";
                    $output = [];
                    $exitCode = 0;
                    exec($comando, $output, $exitCode);

                    $fin = microtime(true);
                    $latencia = round(($fin - $inicio) * 1000, 2);

                    if ($exitCode === 0) {
                        return [
                            'ok' => true,
                            'mensaje' => "Controlador '{$driver}' detectado en Windows Spooler ({$latencia} ms).",
                            'latencia_ms' => $latencia,
                        ];
                    }

                    return [
                        'ok' => false,
                        'mensaje' => "El controlador '{$driver}' no fue encontrado entre las impresoras instaladas en Windows.",
                        'latencia_ms' => null,
                    ];
                } else {
                    // En Linux/Unix verificar con lpstat
                    $output = [];
                    $exitCode = 0;
                    exec('lpstat -p '.escapeshellarg($driver).' 2>&1', $output, $exitCode);
                    $latencia = round((microtime(true) - $inicio) * 1000, 2);

                    if ($exitCode === 0) {
                        return [
                            'ok' => true,
                            'mensaje' => "Cola CUPS '{$driver}' detectada en el sistema ({$latencia} ms).",
                            'latencia_ms' => $latencia,
                        ];
                    }

                    return [
                        'ok' => false,
                        'mensaje' => "Impresora '{$driver}' no registrada en el subsistema de impresión del SO.",
                        'latencia_ms' => null,
                    ];
                }
            } catch (Throwable $e) {
                return [
                    'ok' => false,
                    'mensaje' => "Error al consultar cola del sistema: {$e->getMessage()}",
                    'latencia_ms' => null,
                ];
            }
        }

        // Impresora de Red Ethernet / Wi-Fi (IP:Puerto)
        if (empty($this->ip_address)) {
            return [
                'ok' => false,
                'mensaje' => 'No tiene una dirección IP asignada.',
                'latencia_ms' => null,
            ];
        }

        $inicio = microtime(true);
        try {
            $fp = false;
            try {
                set_error_handler(static fn () => true);
                $fp = fsockopen($this->ip_address, $this->puerto, $errno, $errstr, $timeout);
            } finally {
                restore_error_handler();
            }

            $fin = microtime(true);
            $latencia = round(($fin - $inicio) * 1000, 2);

            if ($fp) {
                fclose($fp);

                return [
                    'ok' => true,
                    'mensaje' => "Conexión TCP establecida ({$latencia} ms).",
                    'latencia_ms' => $latencia,
                ];
            }

            return [
                'ok' => false,
                'mensaje' => "Inaccesible ({$errstr} - Código {$errno}).",
                'latencia_ms' => null,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'mensaje' => "Error de socket: {$e->getMessage()}",
                'latencia_ms' => null,
            ];
        }
    }

    /**
     * Alias retrocompatible de probarConexion().
     */
    public function probarConexionSocket(float $timeout = 1.0): array
    {
        return $this->probarConexion($timeout);
    }
}
