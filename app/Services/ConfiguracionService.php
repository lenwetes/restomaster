<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ConfiguracionService
{
    public function obtener(string $grupo, string $clave, mixed $default = null): mixed
    {
        $config = Configuracion::where('grupo', $grupo)->where('clave', $clave)->first();

        if (! $config) {
            return $default;
        }

        if ($clave === 'password' && ! empty($config->valor)) {
            try {
                return Crypt::decryptString($config->valor);
            } catch (\Throwable $e) {
                return $config->valor;
            }
        }

        return $config->valor;
    }

    public function guardar(string $grupo, string $clave, mixed $valor): void
    {
        if ($clave === 'password' && ! empty($valor)) {
            try {
                Crypt::decryptString($valor);
            } catch (\Throwable $e) {
                $valor = Crypt::encryptString($valor);
            }
        }

        Configuracion::updateOrCreate(
            ['grupo' => $grupo, 'clave' => $clave],
            ['valor' => $valor],
        );
    }

    public function obtenerGrupo(string $grupo): array
    {
        return Configuracion::where('grupo', $grupo)
            ->pluck('valor', 'clave')
            ->toArray();
    }

    public function regenerarWebhookToken(): string
    {
        $token = Str::random(48);

        $this->guardar('reservas', 'webhook_token', $token);

        return $token;
    }

    public function probarConexionDatabase(array $params): array
    {
        $inicio = microtime(true);
        $host = $params['host'] ?? '127.0.0.1';
        $port = (int) ($params['port'] ?? 5432);
        $database = $params['database'] ?? 'postgres';
        $username = $params['username'] ?? 'postgres';
        $password = $params['password'] ?? '';
        $sslmode = $params['sslmode'] ?? 'prefer';

        try {
            $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_TIMEOUT => 3,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query('SELECT 1');
            $latencia = round((microtime(true) - $inicio) * 1000, 2);

            return [
                'ok' => true,
                'latencia_ms' => $latencia,
                'mensaje' => "Conexión exitosa con PostgreSQL en {$host}:{$port} ({$latencia} ms).",
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Error de conexión: '.$e->getMessage(),
            ];
        }
    }

    public function obtenerBackups(): array
    {
        $backupDir = storage_path('app/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $archivos = File::files($backupDir);
        $backups = [];

        foreach ($archivos as $archivo) {
            if (in_array($archivo->getExtension(), ['sql', 'dump', 'json'], true)) {
                $backups[] = [
                    'nombre' => $archivo->getFilename(),
                    'ruta' => $archivo->getRealPath(),
                    'tamano_kb' => round($archivo->getSize() / 1024, 1),
                    'tamano_mb' => round($archivo->getSize() / (1024 * 1024), 2),
                    'fecha' => date('Y-m-d H:i:s', $archivo->getMTime()),
                ];
            }
        }

        usort($backups, fn ($a, $b) => strcmp($b['fecha'], $a['fecha']));

        return $backups;
    }

    public function eliminarBackup(string $nombre): bool
    {
        $archivo = storage_path('app/backups/'.basename($nombre));
        if (File::exists($archivo)) {
            return File::delete($archivo);
        }

        return false;
    }

    public function valoresPorDefectoTicket80mm(): array
    {
        return [
            'nombre_comercial' => 'RESTOMASTER GASTRO',
            'lema' => 'Restaurante & Bar · Cocina Artesanal y Parrilla',
            'razon_social' => 'RestoMaster Colombia S.A.S.',
            'nit' => '901.458.789-3',
            'regimen' => 'IVA Régimen Común - Tarifa Especial',
            'direccion' => 'Cra 35 # 8A-12, El Poblado, Medellín',
            'telefono' => '+57 (4) 444-5566 · WhatsApp: +57 300 123 4567',
            'ciudad' => 'Medellín, Antioquia',
            'mensaje_bienvenida' => '¡Bienvenidos a una experiencia gastronómica única!',
            'resolucion_dian' => 'Resolución DIAN N° 1876400001234 del 2026-01-15',
            'rango_autorizado' => 'Prefijo POS desde SEC-001 hasta SEC-50000',
            'mostrar_desglose_impuestos' => true,
            'mostrar_datos_mesero' => true,
            'sugerir_propina' => true,
            'porcentaje_propina' => 10,
            'mensaje_propina' => 'Propina sugerida 10%: El servicio es voluntario',
            'pie_pagina' => '¡Muchas gracias por su preferencia! Esperamos su pronta visita en RestoMaster.',
            'redes_sociales' => 'Instagram: @restomaster · www.restomaster.co',
            'politica_cambios' => 'Verifique su pedido al momento de la entrega. Conserve este comprobante.',
            'mostrar_qr' => true,
        ];
    }

    public function restablecerConfiguraciones(): void
    {
        // Restablecer valores de ticket
        foreach ($this->valoresPorDefectoTicket80mm() as $clave => $valor) {
            $this->guardar('ticket_80mm', $clave, $valor);
        }

        // Restablecer valores generales
        $this->guardar('general', 'razon_social', 'RestoMaster S.A.S.');
        $this->guardar('general', 'nit', '901.458.789-3');
        $this->guardar('general', 'direccion', 'Cra 35 # 8A-12, El Poblado');
        $this->guardar('general', 'telefono', '+57 300 123 4567');
        $this->guardar('general', 'ciudad', 'Medellín, Colombia');
        $this->guardar('general', 'regimen', 'Común');
        $this->guardar('general', 'moneda', 'COP');
        $this->guardar('general', 'simbolo_moneda', '$');
        $this->guardar('general', 'impuesto_porcentaje', 8);
        $this->guardar('general', 'costo_envio_base', 8000);
        $this->guardar('impresion', 'pie_ticket', '¡Gracias por preferir RestoMaster!');
    }
}
