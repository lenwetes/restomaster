<?php

namespace App\Services;

use App\Console\Commands\BackupDatabaseCommand;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ZipArchive;

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
            } catch (\Throwable) {
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
            } catch (\Throwable) {
                $valor = Crypt::encryptString($valor);
            }
        }

        Configuracion::updateOrCreate(
            ['grupo' => $grupo, 'clave' => $clave],
            ['valor' => $valor],
        );

        Cache::forget("config_grupo_{$grupo}");
    }

    public function obtenerGrupo(string $grupo): array
    {
        return Cache::remember("config_grupo_{$grupo}", 300, fn () => Configuracion::where('grupo', $grupo)
            ->pluck('valor', 'clave')
            ->toArray());
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
        $backupDir = config('backup.path', storage_path('app/backups'));
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $archivos = File::files($backupDir);
        $backups = [];

        foreach ($archivos as $archivo) {
            if (in_array($archivo->getExtension(), ['sql', 'dump', 'json', 'zip'], true)) {
                $esArchivos = $archivo->getExtension() === 'zip'
                    && str_starts_with($archivo->getFilename(), 'restomaster_storage_backup_');
                $backups[] = [
                    'nombre' => $archivo->getFilename(),
                    'ruta' => $archivo->getRealPath(),
                    'tamano_kb' => round($archivo->getSize() / 1024, 1),
                    'tamano_mb' => round($archivo->getSize() / (1024 * 1024), 2),
                    'fecha' => date('Y-m-d H:i:s', $archivo->getMTime()),
                    'tipo' => $esArchivos ? 'archivos' : 'bd',
                ];
            }
        }

        usort($backups, fn ($a, $b) => strcmp($b['fecha'], $a['fecha']));

        return $backups;
    }

    public function eliminarBackup(string $nombre): bool
    {
        $archivo = rtrim(config('backup.path', storage_path('app/backups')), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.basename($nombre);
        if (File::exists($archivo)) {
            return File::delete($archivo);
        }

        return false;
    }

    /**
     * Restaura una copia generada por el sistema desde su nombre de archivo.
     * BD (.sql/.txt: vacía tablas y replaya INSERTs; .dump: pg_restore) o
     * archivos (.zip del respaldo de storage). Deja el sistema operativo.
     *
     * @throws \DomainException si el archivo no es válido o el motor no lo soporta
     */
    public function restaurarCopia(string $nombre): string
    {
        $dir = rtrim(config('backup.path', storage_path('app/backups')), DIRECTORY_SEPARATOR);
        $ruta = $dir.DIRECTORY_SEPARATOR.basename($nombre);

        if (! File::exists($ruta)) {
            throw new \DomainException("El respaldo {$nombre} no existe.");
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

        if ($extension === 'zip' && str_starts_with(basename($ruta), 'restomaster_storage_backup_')) {
            $total = $this->restaurarZipArchivos($ruta, storage_path('app/public'));

            return "Archivos e imágenes restaurados ({$total} elementos).";
        }

        if (in_array($extension, ['sql', 'txt'], true)) {
            $this->restaurarVolcadoSql($ruta);

            return 'Base de datos restaurada desde el volcado SQL.';
        }

        if ($extension === 'dump') {
            $this->restaurarDumpNativo($ruta);

            return 'Base de datos restaurada desde el dump nativo.';
        }

        throw new \DomainException('Tipo de respaldo no soportado para restauración.');
    }

    /**
     * Solo se aceptan volcados generados por restomaster:backup (firma de cabecera).
     *
     * @throws \DomainException
     */
    public function validarVolcadoSql(string $contenido): void
    {
        // La firma vive en la cabecera (la primera línea es un separador decorativo).
        $cabecera = substr(ltrim($contenido), 0, 600);
        if (! str_contains($cabecera, '-- RestoMaster POS Enterprise')) {
            throw new \DomainException('El archivo no es un respaldo válido generado por el sistema.');
        }

        // Denylist de sentencias peligrosas fuera del formato de volcado (solo INSERT).
        if ((bool) preg_match('/^\s*(DROP\s+DATABASE|CREATE\s+(USER|ROLE|EXTENSION)|ALTER\s+SYSTEM|COPY\s+.*FROM\s+PROGRAM|\\\\!)/mi', $contenido)) {
            throw new \DomainException('El archivo contiene sentencias no permitidas en un respaldo.');
        }
    }

    /**
     * @throws \DomainException
     */
    public function restaurarVolcadoSql(string $ruta): void
    {
        $contenido = (string) file_get_contents($ruta);
        $this->validarVolcadoSql($contenido);
        $this->vaciarTablasRespaldo();

        // El volcado viene ordenado de padres a hijos (BackupDatabaseCommand::TABLAS).
        if (DB::getDriverName() === 'sqlite') {
            // PRAGMA no opera dentro de transacción: blindaje extra contra orden inesperado.
            DB::statement('PRAGMA foreign_keys = OFF');
            try {
                DB::unprepared($contenido);
            } finally {
                DB::statement('PRAGMA foreign_keys = ON');
            }

            return;
        }

        DB::transaction(fn () => DB::unprepared($contenido));

        if (DB::getDriverName() === 'pgsql') {
            $this->reanudarSecuencias();
        }
    }

    /**
     * Tras TRUNCATE ... RESTART IDENTITY + replay con ids explícitos, las
     * secuencias quedan en 1 y colisionarían. Se adelantan al MAX(id)+1.
     */
    protected function reanudarSecuencias(): void
    {
        foreach (BackupDatabaseCommand::TABLAS as $tabla) {
            try {
                DB::statement("SELECT setval(pg_get_serial_sequence('\"{$tabla}\"', 'id'), COALESCE((SELECT MAX(\"id\") FROM \"{$tabla}\"), 0) + 1, false)");
            } catch (\Throwable) {
                // Tablas sin secuencia propia (p. ej. pivotes): se omiten.
            }
        }
    }

    /**
     * Vacía las tablas del respaldo en orden seguro por FK para que el
     * replay de INSERTs no choque con llaves existentes. Soporta pgsql y sqlite.
     *
     * @throws \DomainException
     */
    public function vaciarTablasRespaldo(): void
    {
        $driver = DB::getDriverName();
        $tablas = BackupDatabaseCommand::TABLAS;

        if ($driver === 'pgsql') {
            $lista = implode(', ', array_map(fn ($t) => '"'.$t.'"', $tablas));
            DB::statement("TRUNCATE TABLE {$lista} RESTART IDENTITY CASCADE");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            try {
                foreach (array_reverse($tablas) as $tabla) {
                    try {
                        DB::table($tabla)->delete();
                    } catch (\Throwable) {
                        // Tabla inexistente en este esquema: se omite sin abortar.
                    }
                }
            } finally {
                DB::statement('PRAGMA foreign_keys = ON');
            }

            return;
        }

        throw new \DomainException("Restauración automática no soportada para el driver [{$driver}].");
    }

    /**
     * Restaura un dump nativo pg_dump -Fc con pg_restore (--clean).
     *
     * @throws \DomainException
     */
    public function restaurarDumpNativo(string $ruta): void
    {
        $db = config('database.connections.pgsql');
        if (! $db) {
            throw new \DomainException('La restauración nativa requiere conexión pgsql configurada.');
        }

        try {
            $resultado = Process::timeout(300)
                ->env(array_merge($_ENV, ['PGPASSWORD' => $db['password'] ?? '']))
                ->run([
                    'pg_restore',
                    '-h', $db['host'] ?? '127.0.0.1',
                    '-p', (string) ($db['port'] ?? 5432),
                    '-U', $db['username'] ?? 'postgres',
                    '--clean',
                    '--if-exists',
                    '--no-owner',
                    '-d', $db['database'] ?? 'restomaster',
                    $ruta,
                ]);

            if (! $resultado->successful()) {
                throw new \DomainException('pg_restore falló: '.mb_substr($resultado->errorOutput(), 0, 300));
            }
        } catch (\Throwable $e) {
            throw new \DomainException('No se pudo ejecutar pg_restore. Verifica que el binario esté instalado en el servidor. Detalle: '.$e->getMessage());
        }
    }

    /**
     * Extrae un ZIP del respaldo de archivos validando rutas anti-traversal.
     *
     * @throws \DomainException
     */
    public function restaurarZipArchivos(string $ruta, string $destino): int
    {
        $zip = new ZipArchive;
        if ($zip->open($ruta) !== true) {
            throw new \DomainException('El archivo ZIP está corrupto o no se puede abrir.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            if ($nombre === false) {
                continue;
            }
            if (
                str_starts_with($nombre, '/') || str_starts_with($nombre, '\\')
                || str_contains($nombre, '..') || (bool) preg_match('/^[A-Za-z]:/', $nombre)
            ) {
                $zip->close();

                throw new \DomainException('El ZIP contiene rutas no permitidas.');
            }
        }

        $total = $zip->numFiles;
        if (! File::exists($destino)) {
            File::makeDirectory($destino, 0755, true);
        }

        $ok = $zip->extractTo($destino);
        $zip->close();

        if (! $ok) {
            throw new \DomainException('No se pudo extraer el respaldo de archivos.');
        }

        return $total;
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

    /**
     * Comprueba si el PIN de supervisor/administrador está configurado en el sistema.
     */
    public function tienePinSeguridad(): bool
    {
        $hash = $this->obtener('seguridad', 'pin_supervisor_hash');

        return ! empty($hash);
    }

    /**
     * Valida si un PIN numérico ingresado coincide con el hash almacenado.
     */
    public function verificarPinSeguridad(string $pin): bool
    {
        $hash = $this->obtener('seguridad', 'pin_supervisor_hash');
        if (empty($hash)) {
            return false;
        }

        return Hash::check(trim($pin), $hash);
    }

    /**
     * Establece o actualiza el PIN de seguridad del administrador con hash criptográfico.
     */
    public function establecerPinSeguridad(string $pin, ?User $usuario = null): void
    {
        $pinLimpio = trim($pin);
        if (! preg_match('/^[0-9]{4,6}$/', $pinLimpio)) {
            throw new \InvalidArgumentException('El PIN de seguridad debe contener entre 4 y 6 dígitos numéricos (0-9).');
        }

        $hash = Hash::make($pinLimpio);
        $this->guardar('seguridad', 'pin_supervisor_hash', $hash);
        $this->guardar('seguridad', 'pin_actualizado_en', now()->toIso8601String());

        app(AuditoriaService::class)->registrar(
            usuario: $usuario ?? auth()->user(),
            accion: 'seguridad.pin_actualizado',
            entidad: 'configuracion',
            entidadId: null,
            descripcion: 'PIN de seguridad de supervisor/administrador configurado/actualizado.',
            datos: ['usuario' => $usuario?->name ?? auth()->user()?->name]
        );
    }

    /**
     * Permite al administrador cambiar el PIN validando primero su contraseña de cuenta.
     */
    public function cambiarPinConPassword(User $usuario, string $password, string $nuevoPin): bool
    {
        if (! Hash::check($password, $usuario->password)) {
            throw new \InvalidArgumentException('La contraseña de administrador ingresada es incorrecta.');
        }

        $this->establecerPinSeguridad($nuevoPin, $usuario);

        return true;
    }

    /**
     * Genera un código OTP temporal de 6 dígitos para rescate de PIN por correo.
     */
    public function generarOtpRescatePin(User $usuario): string
    {
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $hashOtp = Hash::make($otp);
        $expiraEn = now()->addMinutes(15)->toIso8601String();

        $this->guardar('seguridad', 'pin_reset_otp_hash', $hashOtp);
        $this->guardar('seguridad', 'pin_reset_expira_en', $expiraEn);
        $this->guardar('seguridad', 'pin_reset_user_id', $usuario->id);

        app(AuditoriaService::class)->registrar(
            usuario: $usuario,
            accion: 'seguridad.pin_otp_solicitado',
            entidad: 'configuracion',
            entidadId: null,
            descripcion: "Código OTP de rescate de PIN generado para {$usuario->email}.",
            datos: ['email' => $usuario->email]
        );

        // Envío de correo o log de notificación
        try {
            Mail::raw(
                "RestoMaster - Código de Rescate de PIN de Seguridad\n\nTu código temporal es: {$otp}\n\nEste código expira en 15 minutos. Si no solicitaste este cambio, contacta de inmediato al soporte.",
                function ($message) use ($usuario) {
                    $message->to($usuario->email)
                        ->subject('RestoMaster - Código de Rescate de PIN de Seguridad');
                }
            );
        } catch (\Throwable) {
            Log::info("Código OTP de rescate de PIN para {$usuario->email}: {$otp}");
        }

        return $otp;
    }

    /**
     * Valida el código OTP recibido por correo y restablece el PIN si es válido.
     */
    public function validarOtpYRestablecerPin(string $otp, string $nuevoPin, ?User $usuario = null): bool
    {
        $hashOtp = $this->obtener('seguridad', 'pin_reset_otp_hash');
        $expiraEn = $this->obtener('seguridad', 'pin_reset_expira_en');

        if (empty($hashOtp) || empty($expiraEn)) {
            throw new \InvalidArgumentException('No hay una solicitud de rescate de PIN activa o ha expirado.');
        }

        if (now()->isAfter(Carbon::parse($expiraEn))) {
            throw new \DomainException('El código de rescate ha expirado. Por favor solicita uno nuevo.');
        }

        if (! Hash::check(trim($otp), $hashOtp)) {
            throw new \InvalidArgumentException('El código de rescate ingresado es incorrecto.');
        }

        // Restablecer PIN
        $this->establecerPinSeguridad($nuevoPin, $usuario);

        // Limpiar tokens de rescate
        $this->guardar('seguridad', 'pin_reset_otp_hash', null);
        $this->guardar('seguridad', 'pin_reset_expira_en', null);

        app(AuditoriaService::class)->registrar(
            usuario: $usuario ?? auth()->user(),
            accion: 'seguridad.pin_rescatado_otp',
            entidad: 'configuracion',
            entidadId: null,
            descripcion: 'PIN de seguridad restablecido exitosamente mediante código de rescate OTP.',
            datos: ['usuario' => $usuario?->name ?? auth()->user()?->name]
        );

        return true;
    }
}
