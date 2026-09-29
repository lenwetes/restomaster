<?php

use App\Models\Impresora;
use App\Services\ConfiguracionService;
use App\Services\ImpresionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $tabActiva = 'factura'; // 'factura', 'database', 'impresoras', 'dian', 'empresa', 'reservas', 'reset'

    public array $dianForm = [];

    public array $empresaForm = [];

    public array $reservasForm = [];

    public array $ticketForm = [];

    public array $dbForm = [];

    public array $impresoraForm = [
        'nombre' => '',
        'tipo_conexion' => 'red',
        'ip_address' => '192.168.1.100',
        'puerto' => 9100,
        'area' => 'caja',
        'ancho_columnas' => 48,
        'copias' => 1,
        'activa' => true,
        'descripcion' => '',
    ];

    public ?int $impresoraEnEdicion = null;

    public bool $mostrarModalImpresora = false;

    public string $webhookToken = '';

    public mixed $archivoBackup = null;

    public string $pinForm_nuevoPin = '';
    public string $pinForm_confirmarPin = '';
    public string $pinForm_pinActual = '';
    public string $pinRescate_password = '';
    public string $pinRescate_otp = '';
    public string $pinRescate_nuevoPin = '';
    public string $pinRescate_confirmarPin = '';
    public bool $mostrarModalRescatePin = false;
    public string $modoRescatePin = 'password';
    public bool $otpEnviado = false;

    public array $iaForm = [
        'activo' => false,
        'proveedor' => 'gemini',
        'modelo' => 'gemini-2.5-flash',
        'api_key' => '',
    ];

    public string $iaEstado = 'no_configurado';

    public ?int $iaLatencia = null;

    public string $iaModeloActivo = '';

    public string $iaUltimoError = '';

    public bool $iaTieneClave = false;

    public string $iaClaveEnmascarada = '';

    public function mount(): void
    {
        $svc = app(ConfiguracionService::class);

        $this->dianForm = [
            'razon_social' => $svc->obtener('general', 'razon_social', 'RestoMaster Colombia S.A.S.'),
            'nit' => $svc->obtener('general', 'nit', '901.458.789-3'),
            'regimen' => $svc->obtener('general', 'regimen', 'Común'),
            'ambiente' => $svc->obtener('dian', 'ambiente', 'habilitacion'),
            'tipo_documento' => $svc->obtener('dian', 'tipo_documento', '01'),
            'resolucion_numero' => $svc->obtener('dian', 'resolucion_numero', '1876400001234'),
            'resolucion_fecha' => $svc->obtener('dian', 'resolucion_fecha', '2026-01-15'),
            'prefijo' => $svc->obtener('dian', 'prefijo', 'MP'),
            'desde' => $svc->obtener('dian', 'desde', '1'),
            'hasta' => $svc->obtener('dian', 'hasta', '50000'),
            'vigente' => (bool) $svc->obtener('dian', 'vigente', true),
            'envio_activo' => (bool) $svc->obtener('dian', 'envio_activo', false),
        ];

        $this->empresaForm = [
            'razon_social' => $svc->obtener('general', 'razon_social', 'RestoMaster Colombia S.A.S.'),
            'nit' => $svc->obtener('general', 'nit', '901.458.789-3'),
            'direccion' => $svc->obtener('general', 'direccion', 'Cra 35 # 8A-12, El Poblado'),
            'telefono' => $svc->obtener('general', 'telefono', '+57 300 123 4567'),
            'ciudad' => $svc->obtener('general', 'ciudad', 'Medellín, Colombia'),
            'moneda' => $svc->obtener('general', 'moneda', 'COP'),
            'simbolo_moneda' => $svc->obtener('general', 'simbolo_moneda', '$'),
            'impuesto_porcentaje' => (float) $svc->obtener('general', 'impuesto_porcentaje', 8.0),
            'costo_envio_base' => (float) $svc->obtener('general', 'costo_envio_base', 8000.0),
        ];

        $this->reservasForm = [
            'webhook_activo' => (bool) $svc->obtener('reservas', 'webhook_activo', false),
        ];
        $this->webhookToken = $svc->obtener('reservas', 'webhook_token', '');

        $defaultsTicket = $svc->valoresPorDefectoTicket80mm();
        $this->ticketForm = [];
        foreach ($defaultsTicket as $k => $def) {
            $this->ticketForm[$k] = $svc->obtener('ticket_80mm', $k, $def);
        }

        $this->dbForm = [
            'host' => $svc->obtener('database_external', 'host', config('database.connections.pgsql.host', '127.0.0.1')),
            'port' => (int) $svc->obtener('database_external', 'port', config('database.connections.pgsql.port', 5432)),
            'database' => $svc->obtener('database_external', 'database', config('database.connections.pgsql.database', 'restomaster')),
            'username' => $svc->obtener('database_external', 'username', config('database.connections.pgsql.username', 'postgres')),
            'password' => $svc->obtener('database_external', 'password', ''),
            'sslmode' => $svc->obtener('database_external', 'sslmode', 'prefer'),
            'activo' => (bool) $svc->obtener('database_external', 'activo', false),
        ];

        $this->cargarEstadoIa();
    }

    public function guardarDian(): void
    {
        $this->authorize('administrar-configuracion');

        $validated = $this->validate([
            'dianForm.razon_social' => ['required', 'string', 'max:255'],
            'dianForm.nit' => ['nullable', 'string', 'max:30'],
            'dianForm.regimen' => ['required', 'string', 'max:60'],
            'dianForm.ambiente' => ['required', 'in:habilitacion,produccion'],
            'dianForm.tipo_documento' => ['required', 'string', 'max:4'],
            'dianForm.resolucion_numero' => ['nullable', 'string', 'max:30'],
            'dianForm.prefijo' => ['nullable', 'string', 'max:10'],
        ]);

        $svc = app(ConfiguracionService::class);
        foreach (['razon_social', 'nit', 'regimen'] as $k) {
            $svc->guardar('general', $k, $validated['dianForm'][$k]);
        }
        foreach (['ambiente', 'tipo_documento', 'resolucion_numero', 'resolucion_fecha', 'prefijo', 'desde', 'hasta', 'vigente', 'envio_activo'] as $k) {
            $svc->guardar('dian', $k, $validated['dianForm'][$k] ?? $this->dianForm[$k]);
        }
        session()->flash('status', 'Configuración DIAN guardada con éxito.');
        $this->dispatch('notificacion', ['mensaje' => 'Configuración DIAN guardada', 'tipo' => 'success']);
    }

    public function guardarEmpresa(): void
    {
        $this->authorize('administrar-configuracion');

        $validated = $this->validate([
            'empresaForm.razon_social' => ['required', 'string', 'max:255'],
            'empresaForm.nit' => ['nullable', 'string', 'max:30'],
            'empresaForm.direccion' => ['nullable', 'string', 'max:255'],
            'empresaForm.telefono' => ['nullable', 'string', 'max:30'],
            'empresaForm.ciudad' => ['nullable', 'string', 'max:100'],
            'empresaForm.moneda' => ['required', 'string', 'max:10'],
            'empresaForm.simbolo_moneda' => ['required', 'string', 'max:5'],
            'empresaForm.impuesto_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'empresaForm.costo_envio_base' => ['nullable', 'numeric', 'min:0'],
        ]);

        $svc = app(ConfiguracionService::class);
        foreach ($validated['empresaForm'] as $k => $v) {
            $svc->guardar('general', $k, $v);
        }

        session()->flash('status', 'Datos del establecimiento comercial guardados.');
        $this->dispatch('notificacion', ['mensaje' => 'Parámetros de empresa guardados', 'tipo' => 'success']);
    }

    public function guardarTicket(): void
    {
        $this->authorize('administrar-configuracion');

        $this->validate([
            'ticketForm.nombre_comercial' => ['required', 'string', 'max:100'],
            'ticketForm.razon_social' => ['nullable', 'string', 'max:150'],
            'ticketForm.nit' => ['nullable', 'string', 'max:50'],
            'ticketForm.direccion' => ['nullable', 'string', 'max:150'],
            'ticketForm.telefono' => ['nullable', 'string', 'max:50'],
            'ticketForm.pie_pagina' => ['nullable', 'string', 'max:255'],
        ]);

        $svc = app(ConfiguracionService::class);
        foreach ($this->ticketForm as $k => $v) {
            $svc->guardar('ticket_80mm', $k, $v);
        }

        session()->flash('status', 'Diseño de ticket térmico 80mm guardado.');
        $this->dispatch('notificacion', ['mensaje' => 'Diseño de tirilla guardado', 'tipo' => 'success']);
    }

    public function restablecerTicket(): void
    {
        $this->authorize('administrar-configuracion');

        $svc = app(ConfiguracionService::class);
        $defaults = $svc->valoresPorDefectoTicket80mm();
        $this->ticketForm = $defaults;
        foreach ($defaults as $k => $v) {
            $svc->guardar('ticket_80mm', $k, $v);
        }

        session()->flash('status', 'Diseño de ticket restablecido a valores sugeridos.');
        $this->dispatch('notificacion', ['mensaje' => 'Ticket restablecido a estándar', 'tipo' => 'info']);
    }

    public function probarConexionDb(): void
    {
        $this->authorize('administrar-configuracion');

        $this->validate([
            'dbForm.host' => ['required', 'string'],
            'dbForm.port' => ['required', 'integer'],
            'dbForm.database' => ['required', 'string'],
            'dbForm.username' => ['required', 'string'],
        ]);

        $svc = app(ConfiguracionService::class);
        $resultado = $svc->probarConexionDatabase($this->dbForm);
        $this->testDbResultado = $resultado;

        if ($resultado['ok']) {
            $this->dispatch('notificacion', ['mensaje' => $resultado['mensaje'], 'tipo' => 'success']);
        } else {
            $this->dispatch('notificacion', ['mensaje' => $resultado['error'], 'tipo' => 'error']);
        }
    }

    public function guardarConexionDb(): void
    {
        $this->authorize('administrar-configuracion');

        $validated = $this->validate([
            'dbForm.host' => ['required', 'string', 'max:253'],
            'dbForm.port' => ['required', 'integer', 'min:1', 'max:65535'],
            'dbForm.database' => ['required', 'string', 'max:63', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'dbForm.username' => ['required', 'string', 'max:63'],
            'dbForm.password' => ['nullable', 'string', 'max:255'],
            'dbForm.sslmode' => ['nullable', 'in:disable,allow,prefer,require,verify-ca,verify-full'],
        ]);

        $svc = app(ConfiguracionService::class);
        foreach ($validated['dbForm'] as $k => $v) {
            if ($k === 'password' && ($v === null || $v === '')) {
                continue; // No sobrescribir la clave cifrada existente con vacío.
            }
            $svc->guardar('database_external', $k, $v);
        }

        session()->flash('status', 'Parámetros de conexión a base de datos guardados.');
        $this->dispatch('notificacion', ['mensaje' => 'Conexión a base de datos guardada', 'tipo' => 'success']);
    }

    public function crearBackup(): void
    {
        $this->authorize('administrar-configuracion');

        try {
            Artisan::call('restomaster:backup');
            Artisan::call('restomaster:backup-storage');
            session()->flash('status', 'Copia de seguridad completa generada con éxito (base de datos + archivos).');
            $this->dispatch('notificacion', ['mensaje' => 'Copia completa generada: BD + archivos', 'tipo' => 'success']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Fallo al generar copia de seguridad: '.$e->getMessage());
            session()->flash('error', 'No se pudo completar la copia de seguridad. Revisa los registros del sistema.');
            $this->dispatch('notificacion', ['mensaje' => 'Fallo al generar la copia de seguridad.', 'tipo' => 'error']);
        }
    }

    public function eliminarBackup(string $nombre): void
    {
        $this->authorize('administrar-configuracion');

        $svc = app(ConfiguracionService::class);
        $svc->eliminarBackup($nombre);
        session()->flash('status', "Copia {$nombre} eliminada.");
        $this->dispatch('notificacion', ['mensaje' => 'Backup eliminado', 'tipo' => 'info']);
    }

    public function restaurarBackup(): void
    {
        $this->authorize('administrar-configuracion');

        $this->validate([
            'archivoBackup' => ['required', 'file', 'max:51200', 'mimes:sql,txt'], // max 50MB
        ]);

        $extension = strtolower($this->archivoBackup->getClientOriginalExtension());
        if (! in_array($extension, ['sql', 'txt'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'archivoBackup' => 'El archivo de respaldo debe tener extensión .sql o .txt',
            ]);
        }

        $svc = app(ConfiguracionService::class);

        try {
            $svc->restaurarVolcadoSql($this->archivoBackup->getRealPath());
            $this->reset('archivoBackup');
            session()->flash('status', 'Base de datos restaurada exitosamente desde el archivo de respaldo.');
            $this->dispatch('notificacion', ['mensaje' => 'Respaldo importado y restaurado', 'tipo' => 'success']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Fallo al restaurar respaldo: '.$e->getMessage());
            session()->flash('error', 'Error al restaurar: '.$e->getMessage());
            $this->dispatch('notificacion', ['mensaje' => 'Fallo al restaurar: archivo inválido o corrupto.', 'tipo' => 'error']);
        }
    }

    public function restaurarCopia(string $nombre): void
    {
        $this->authorize('administrar-configuracion');

        try {
            $svc = app(ConfiguracionService::class);
            $mensaje = $svc->restaurarCopia($nombre);
            session()->flash('status', $mensaje.' El sistema quedó operativo.');
            $this->dispatch('notificacion', ['mensaje' => $mensaje, 'tipo' => 'success']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Fallo al restaurar copia: '.$e->getMessage());
            session()->flash('error', 'No se pudo restaurar: '.$e->getMessage());
            $this->dispatch('notificacion', ['mensaje' => 'Fallo al restaurar la copia.', 'tipo' => 'error']);
        }

        $this->redirect(route('configuracion'), navigate: true);
    }

    public array $impresorasDetectadasSO = [];

    public function abrirNuevaImpresora(): void
    {
        $this->impresoraEnEdicion = null;
        $this->impresoraForm = [
            'nombre' => '',
            'tipo_conexion' => 'usb_local',
            'driver_nombre' => '',
            'ip_address' => '192.168.1.100',
            'puerto' => 9100,
            'area' => 'caja',
            'ancho_columnas' => 48,
            'copias' => 1,
            'activa' => true,
            'descripcion' => '',
        ];
        $this->impresorasDetectadasSO = [];
        $this->mostrarModalImpresora = true;
    }

    public function editarImpresora(int $id): void
    {
        $impresora = Impresora::findOrFail($id);
        $this->impresoraEnEdicion = $id;
        $this->impresoraForm = [
            'nombre' => $impresora->nombre,
            'tipo_conexion' => $impresora->tipo_conexion,
            'driver_nombre' => $impresora->driver_nombre ?: '',
            'ip_address' => $impresora->ip_address ?: '',
            'puerto' => $impresora->puerto ?: 9100,
            'area' => $impresora->area,
            'ancho_columnas' => $impresora->ancho_columnas,
            'copias' => $impresora->copias,
            'activa' => (bool) $impresora->activa,
            'descripcion' => $impresora->descripcion ?? '',
        ];
        $this->impresorasDetectadasSO = [];
        $this->mostrarModalImpresora = true;
    }

    public function detectarImpresorasSO(): void
    {
        $svc = app(ImpresionService::class);
        $this->impresorasDetectadasSO = $svc->obtenerImpresorasInstaladasSO();

        if (! empty($this->impresorasDetectadasSO)) {
            $this->dispatch('notificacion', ['mensaje' => 'Se detectaron '.count($this->impresorasDetectadasSO).' impresoras en el equipo', 'tipo' => 'success']);
            if (empty($this->impresoraForm['driver_nombre'])) {
                $this->impresoraForm['driver_nombre'] = $this->impresorasDetectadasSO[0];
            }
        } else {
            $this->dispatch('notificacion', ['mensaje' => 'No se detectaron impresoras locales en el equipo', 'tipo' => 'warning']);
        }
    }

    public function seleccionarDriverDetectado(string $driver): void
    {
        $this->impresoraForm['driver_nombre'] = $driver;
        if (empty($this->impresoraForm['nombre'])) {
            $this->impresoraForm['nombre'] = $driver;
        }
    }

    public function guardarImpresora(): void
    {
        $this->authorize('administrar-configuracion');

        $this->validate([
            'impresoraForm.nombre' => ['required', 'string', 'max:100'],
            'impresoraForm.tipo_conexion' => ['required', 'in:red,red_ip,usb,usb_local,driver_sistema,driver_navegador,serie,virtual,virtual_simulador'],
            'impresoraForm.driver_nombre' => ['nullable', 'string', 'max:150'],
            'impresoraForm.ip_address' => ['nullable', 'string', 'max:45'],
            'impresoraForm.puerto' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'impresoraForm.area' => ['required', 'string'],
            'impresoraForm.ancho_columnas' => ['required', 'integer', 'in:32,40,42,48'],
            'impresoraForm.copias' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        if ($this->impresoraEnEdicion) {
            $impresora = Impresora::findOrFail($this->impresoraEnEdicion);
            $impresora->update($this->impresoraForm);
            session()->flash('status', "Impresora '{$impresora->nombre}' actualizada.");
        } else {
            Impresora::create($this->impresoraForm);
            session()->flash('status', 'Nueva impresora agregada al sistema.');
        }

        $this->mostrarModalImpresora = false;
        $this->dispatch('notificacion', ['mensaje' => 'Configuración de impresora guardada', 'tipo' => 'success']);
    }

    public function toggleImpresora(int $id): void
    {
        $this->authorize('administrar-configuracion');

        $imp = Impresora::findOrFail($id);
        $imp->update(['activa' => ! $imp->activa]);
        $estado = $imp->activa ? 'activada' : 'desactivada';
        session()->flash('status', "Impresora '{$imp->nombre}' {$estado}.");
    }

    public function enviarImpresionPrueba(int $id): void
    {
        $impresora = Impresora::findOrFail($id);
        $svc = app(ImpresionService::class);
        $trabajo = $svc->probarImpresora($impresora, Auth::user());

        $this->dispatch('notificacion', ['mensaje' => "Test de impresión #{$trabajo->id} enviado a {$impresora->nombre}", 'tipo' => 'info']);
    }

    public function toggleWebhook(): void
    {
        $this->authorize('administrar-configuracion');

        $this->validate(['reservasForm.webhook_activo' => ['boolean']]);
        app(ConfiguracionService::class)->guardar('reservas', 'webhook_activo', (bool) $this->reservasForm['webhook_activo']);
        session()->flash('status', 'Estado del webhook actualizado.');
    }

    public function regenerarToken(): void
    {
        $this->authorize('administrar-configuracion');

        $this->webhookToken = app(ConfiguracionService::class)->regenerarWebhookToken();
        session()->flash('status', 'Token de webhook regenerado.');
        $this->dispatch('notificacion', ['mensaje' => 'Nuevo token generado', 'tipo' => 'info']);
    }

    public function restablecerFabrica(): void
    {
        $this->authorize('administrar-configuracion');

        $svc = app(ConfiguracionService::class);
        $svc->restablecerConfiguraciones();
        $this->mount();
        session()->flash('status', 'Todas las configuraciones del sistema fueron restablecidas a valores de fábrica.');
        $this->dispatch('notificacion', ['mensaje' => 'Configuraciones restablecidas', 'tipo' => 'success']);
    }

    public function guardarPinSeguridad(): void
    {
        $this->authorize('administrar-configuracion');
        $svc = app(ConfiguracionService::class);

        if ($svc->tienePinSeguridad()) {
            if (empty($this->pinForm_pinActual)) {
                $this->addError('pinForm_pinActual', 'Debes ingresar el PIN actual para autorizar el cambio.');

                return;
            }
            if (! $svc->verificarPinSeguridad($this->pinForm_pinActual)) {
                $this->addError('pinForm_pinActual', 'El PIN actual ingresado no coincide.');

                return;
            }
        }

        if (! preg_match('/^[0-9]{4,6}$/', $this->pinForm_nuevoPin)) {
            $this->addError('pinForm_nuevoPin', 'El nuevo PIN debe tener entre 4 y 6 dígitos numéricos (0-9).');

            return;
        }

        if ($this->pinForm_nuevoPin !== $this->pinForm_confirmarPin) {
            $this->addError('pinForm_confirmarPin', 'La confirmación del PIN no coincide con el nuevo PIN.');

            return;
        }

        $svc->establecerPinSeguridad($this->pinForm_nuevoPin, Auth::user());
        $this->reset(['pinForm_nuevoPin', 'pinForm_confirmarPin', 'pinForm_pinActual']);
        session()->flash('status', 'PIN de seguridad de supervisor actualizado exitosamente.');
        $this->dispatch('notificacion', ['mensaje' => 'PIN de seguridad guardado', 'tipo' => 'success']);
    }

    public function abrirModalRescatePin(string $modo = 'password'): void
    {
        $this->modoRescatePin = $modo;
        $this->reset(['pinRescate_password', 'pinRescate_otp', 'pinRescate_nuevoPin', 'pinRescate_confirmarPin', 'otpEnviado']);
        $this->resetErrorBag();
        $this->mostrarModalRescatePin = true;

        if ($modo === 'otp') {
            $this->solicitarOtpRescate();
        }
    }

    public function cerrarModalRescatePin(): void
    {
        $this->mostrarModalRescatePin = false;
        $this->reset(['pinRescate_password', 'pinRescate_otp', 'pinRescate_nuevoPin', 'pinRescate_confirmarPin', 'otpEnviado']);
        $this->resetErrorBag();
    }

    public function solicitarOtpRescate(): void
    {
        $this->authorize('administrar-configuracion');
        $svc = app(ConfiguracionService::class);
        $user = Auth::user();

        if ($user) {
            $svc->generarOtpRescatePin($user);
            $this->otpEnviado = true;
            $this->dispatch('notificacion', ['mensaje' => "Código de rescate enviado a {$user->email}", 'tipo' => 'info']);
        }
    }

    public function ejecutarRescateConPassword(): void
    {
        $this->authorize('administrar-configuracion');
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->validate([
            'pinRescate_password' => ['required', 'string'],
            'pinRescate_nuevoPin' => ['required', 'regex:/^[0-9]{4,6}$/'],
            'pinRescate_confirmarPin' => ['required', 'same:pinRescate_nuevoPin'],
        ], [
            'pinRescate_password.required' => 'Debes ingresar tu contraseña de inicio de sesión.',
            'pinRescate_nuevoPin.required' => 'Debes ingresar el nuevo PIN.',
            'pinRescate_nuevoPin.regex' => 'El nuevo PIN debe tener entre 4 y 6 dígitos numéricos.',
            'pinRescate_confirmarPin.same' => 'La confirmación del PIN no coincide.',
        ]);

        $svc = app(ConfiguracionService::class);

        try {
            $svc->cambiarPinConPassword($user, $this->pinRescate_password, $this->pinRescate_nuevoPin);
            $this->cerrarModalRescatePin();
            session()->flash('status', 'PIN restablecido exitosamente mediante validación de contraseña de administrador.');
            $this->dispatch('notificacion', ['mensaje' => 'PIN restablecido con éxito', 'tipo' => 'success']);
        } catch (\Throwable $e) {
            $this->addError('pinRescate_password', $e->getMessage());
        }
    }

    public function ejecutarRescateConOtp(): void
    {
        $this->authorize('administrar-configuracion');
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->validate([
            'pinRescate_otp' => ['required', 'string', 'size:6'],
            'pinRescate_nuevoPin' => ['required', 'regex:/^[0-9]{4,6}$/'],
            'pinRescate_confirmarPin' => ['required', 'same:pinRescate_nuevoPin'],
        ], [
            'pinRescate_otp.required' => 'Debes ingresar el código de 6 dígitos recibido en tu correo.',
            'pinRescate_otp.size' => 'El código de rescate debe contener exactamente 6 dígitos.',
            'pinRescate_nuevoPin.regex' => 'El nuevo PIN debe tener entre 4 y 6 dígitos numéricos.',
            'pinRescate_confirmarPin.same' => 'La confirmación del PIN no coincide.',
        ]);

        $svc = app(ConfiguracionService::class);

        try {
            $svc->validarOtpYRestablecerPin($this->pinRescate_otp, $this->pinRescate_nuevoPin, $user);
            $this->cerrarModalRescatePin();
            session()->flash('status', 'PIN restablecido exitosamente mediante código de rescate OTP.');
            $this->dispatch('notificacion', ['mensaje' => 'PIN restablecido con éxito', 'tipo' => 'success']);
        } catch (\Throwable $e) {
            $this->addError('pinRescate_otp', $e->getMessage());
        }
    }

    public function cargarEstadoIa(): void
    {
        $config = \App\Models\CrmConfiguracion::activa(null);
        $clave = $config->obtenerApiKeyIa();

        $this->iaForm['activo'] = (bool) $config->ia_activo;
        $this->iaForm['proveedor'] = $config->ia_proveedor ?: 'gemini';
        $this->iaForm['modelo'] = $config->ia_modelo ?: 'gemini-2.5-flash';
        $this->iaForm['api_key'] = '';
        $this->iaTieneClave = ! empty($clave);
        $this->iaClaveEnmascarada = $this->iaTieneClave ? '••••••••'.substr((string) $clave, -4) : '';
        $this->iaEstado = $this->iaTieneClave && $this->iaForm['activo'] ? $this->iaEstado : 'no_configurado';
        if (! $this->iaTieneClave) {
            $this->iaLatencia = null;
            $this->iaModeloActivo = '';
        }
    }

    public function guardarIa(): void
    {
        $this->authorize('administrar-configuracion');

        $this->validate([
            'iaForm.activo' => ['required', 'boolean'],
            'iaForm.proveedor' => ['required', 'in:gemini,openai'],
            'iaForm.modelo' => ['required', 'string', 'max:60'],
            'iaForm.api_key' => ['nullable', 'string', 'min:10', 'max:200'],
        ], [
            'iaForm.modelo.required' => 'Debes indicar el modelo activo.',
            'iaForm.api_key.min' => 'La API Key parece demasiado corta.',
        ]);

        $config = \App\Models\CrmConfiguracion::activa(null);
        $config->ia_activo = (bool) $this->iaForm['activo'];
        $config->ia_proveedor = $this->iaForm['proveedor'];
        $config->ia_modelo = $this->iaForm['modelo'];
        if (! empty($this->iaForm['api_key'])) {
            $config->ia_api_key = trim($this->iaForm['api_key']);
        }
        $config->save();

        $this->iaForm['api_key'] = '';
        $this->iaEstado = 'no_configurado';
        $this->iaLatencia = null;
        $this->iaModeloActivo = '';
        $this->iaUltimoError = '';
        $this->cargarEstadoIa();

        session()->flash('status', 'Configuración de IA guardada. Usa "Probar conexión" para verificar el semáforo.');
        $this->dispatch('notificacion', ['mensaje' => 'Configuración de IA guardada', 'tipo' => 'success']);
    }

    public function probarConexionIa(): void
    {
        $this->authorize('administrar-configuracion');

        $resultado = app(\App\Services\Ai\AdminAiCopilotService::class)->probarConexionIa(null);

        if ($resultado['ok']) {
            $this->iaEstado = 'conectado';
            $this->iaLatencia = $resultado['latencia_ms'];
            $this->iaModeloActivo = $resultado['modelo'];
            $this->iaUltimoError = '';
            session()->flash('status', 'Conexión con IA verificada ('.$resultado['latencia_ms'].' ms · '.$resultado['modelo'].').');
            $this->dispatch('notificacion', ['mensaje' => 'IA conectada correctamente', 'tipo' => 'success']);
        } else {
            $this->iaEstado = ($this->iaTieneClave || ! empty($this->iaForm['api_key'])) ? 'error' : 'no_configurado';
            $this->iaLatencia = $resultado['latencia_ms'];
            $this->iaModeloActivo = $resultado['modelo'];
            $this->iaUltimoError = (string) ($resultado['error'] ?? 'Error de conexión.');
            $this->dispatch('notificacion', ['mensaje' => 'No se pudo conectar con la IA', 'tipo' => 'error']);
        }
    }

    public function with(): array
    {
        $svc = app(ConfiguracionService::class);

        return [
            'pieTicket' => $svc->obtener('impresion', 'pie_ticket', ''),
            'backups' => $svc->obtenerBackups(),
            'impresoras' => Impresora::orderBy('id')->get(),
            'tienePin' => $svc->tienePinSeguridad(),
            'pinActualizadoEn' => $svc->obtener('seguridad', 'pin_actualizado_en'),
        ];
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[26px] text-primary">settings_suggest</span>
            <div>
                <h1 class="text-xl font-black tracking-tight text-on-surface">Centro de Configuración Integral</h1>
                <p class="text-xs text-on-surface-variant font-medium">Personalización de tickets 80mm, base de datos, hardware fiscal e integraciones</p>
            </div>
        </div>
        <span class="rounded-full bg-secondary/15 px-3 py-1 text-xs font-black text-secondary border border-secondary/30">CFG-01 ENTERPRISE</span>
    </div>
</x-slot>

<div class="space-y-6">
    <!-- Barra superior de acento -->
    <div class="h-1.5 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    <!-- Mensajes de estado -->
    @if (session('status'))
        <div class="rounded-2xl border border-secondary/40 bg-secondary/10 p-4 text-xs font-bold text-secondary flex items-center gap-2 animate-fade-in">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-2xl border border-error/40 bg-error/10 p-4 text-xs font-bold text-error flex items-center gap-2 animate-fade-in">
            <span class="material-symbols-outlined text-[18px]">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Selector de pestañas -->
    <div class="flex flex-wrap gap-2 p-1.5 rounded-2xl bg-surface-container-low border border-outline-variant/20 shadow-xs">
        @php
            $tabs = [
                'factura' => ['label' => 'Maquetador Ticket 80mm', 'icon' => 'receipt_long'],
                'database' => ['label' => 'Base de Datos & Backups', 'icon' => 'database'],
                'impresoras' => ['label' => 'Impresoras Térmicas', 'icon' => 'print'],
                'dian' => ['label' => 'DIAN / Facturación', 'icon' => 'verified_user'],
                'empresa' => ['label' => 'Establecimiento & Moneda', 'icon' => 'storefront'],
                'reservas' => ['label' => 'Reservas & Webhook', 'icon' => 'webhook'],
                'ia' => ['label' => 'IA & Copiloto', 'icon' => 'smart_toy'],
                'seguridad' => ['label' => 'Seguridad & PIN', 'icon' => 'shield_lock'],
                'reset' => ['label' => 'Restablecimiento', 'icon' => 'restart_alt'],
            ];
        @endphp

        @foreach ($tabs as $key => $tab)
            <button
                type="button"
                wire:click="$set('tabActiva', '{{ $key }}')"
                class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition-all cursor-pointer {{ $tabActiva === $key ? 'bg-primary text-on-primary shadow-md' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}"
            >
                <span class="material-symbols-outlined text-[18px]">{{ $tab['icon'] }}</span>
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 1: MAQUETADOR DE TICKET / FACTURA 80MM (LIVE PREVIEW EN VIVO)      -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'factura')
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start animate-fade-in">
            <!-- Formulario de Configuración del Ticket -->
            <div class="lg:col-span-7 space-y-6">
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm space-y-5">
                    <div class="border-b border-outline-variant/15 pb-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">design_services</span>
                                Personalizador de Tirilla / Colilla 80mm
                            </h2>
                            <p class="text-xs text-on-surface-variant mt-0.5">Modifica los textos, información fiscal, cabeceras y pie de página de las tirillas térmicas.</p>
                        </div>
                        <button
                            type="button"
                            wire:click="restablecerTicket"
                            class="text-xs font-bold text-on-surface-variant hover:text-primary flex items-center gap-1 cursor-pointer"
                            title="Restablecer plantilla predeterminada"
                        >
                            <span class="material-symbols-outlined text-[16px]">refresh</span>
                            Plantilla Estándar
                        </button>
                    </div>

                    <form wire:submit="guardarTicket" class="space-y-4">
                        <!-- Sección Cabecera -->
                        <div class="space-y-3">
                            <span class="text-xs font-extrabold text-primary uppercase tracking-wider block">1. Encabezado del Negocio</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Nombre Comercial</label>
                                    <input type="text" wire:model.live="ticketForm.nombre_comercial" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: RESTOMASTER" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Lema / Slogan</label>
                                    <input type="text" wire:model.live="ticketForm.lema" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: Gastronomía de Autor & Parrilla" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Razón Social</label>
                                    <input type="text" wire:model.live="ticketForm.razon_social" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="RestoMaster Colombia S.A.S." />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">NIT / Identificación Tributaria</label>
                                    <input type="text" wire:model.live="ticketForm.nit" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="NIT 901.458.789-3" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Dirección Física</label>
                                    <input type="text" wire:model.live="ticketForm.direccion" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Cra 35 # 8A-12, El Poblado" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Teléfono / WhatsApp</label>
                                    <input type="text" wire:model.live="ticketForm.telefono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="+57 300 123 4567" />
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-on-surface-variant">Mensaje de Bienvenida</label>
                                <input type="text" wire:model.live="ticketForm.mensaje_bienvenida" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="¡Bienvenidos a nuestra mesa!" />
                            </div>
                        </div>

                        <!-- Sección Fiscal y Parámetros -->
                        <div class="space-y-3 pt-3 border-t border-outline-variant/15">
                            <span class="text-xs font-extrabold text-primary uppercase tracking-wider block">2. Información Fiscal & Opciones</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Régimen Tributario</label>
                                    <input type="text" wire:model.live="ticketForm.regimen" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Texto Resolución DIAN</label>
                                    <input type="text" wire:model.live="ticketForm.resolucion_dian" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                                <label class="flex items-center gap-2 p-2 rounded-xl bg-surface-container-low border border-outline-variant/20 cursor-pointer">
                                    <input type="checkbox" wire:model.live="ticketForm.mostrar_desglose_impuestos" class="rounded text-primary focus:ring-0" />
                                    <span class="text-xs font-bold text-on-surface">Desglose de Impuestos (IVA/INC)</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 rounded-xl bg-surface-container-low border border-outline-variant/20 cursor-pointer">
                                    <input type="checkbox" wire:model.live="ticketForm.mostrar_datos_mesero" class="rounded text-primary focus:ring-0" />
                                    <span class="text-xs font-bold text-on-surface">Mostrar Mesero & Terminal</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 rounded-xl bg-surface-container-low border border-outline-variant/20 cursor-pointer">
                                    <input type="checkbox" wire:model.live="ticketForm.sugerir_propina" class="rounded text-primary focus:ring-0" />
                                    <span class="text-xs font-bold text-on-surface">Sugerir Propina Voluntaria (10%)</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 rounded-xl bg-surface-container-low border border-outline-variant/20 cursor-pointer">
                                    <input type="checkbox" wire:model.live="ticketForm.mostrar_qr" class="rounded text-primary focus:ring-0" />
                                    <span class="text-xs font-bold text-on-surface">Imprimir Código QR de Comprobante</span>
                                </label>
                            </div>
                        </div>

                        <!-- Sección Pie de Página -->
                        <div class="space-y-3 pt-3 border-t border-outline-variant/15">
                            <span class="text-xs font-extrabold text-primary uppercase tracking-wider block">3. Pie de Tirilla (Footer)</span>
                            <div>
                                <label class="text-xs font-bold text-on-surface-variant">Mensaje de Despedida / Agradecimiento</label>
                                <input type="text" wire:model.live="ticketForm.pie_pagina" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="¡Muchas gracias por su visita!" />
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Redes Sociales / Web</label>
                                    <input type="text" wire:model.live="ticketForm.redes_sociales" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="@restomaster · restomaster.com" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Términos o Política de Reclamo</label>
                                    <input type="text" wire:model.live="ticketForm.politica_cambios" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Conserve este tiquete para garantías." />
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3">
                            <button
                                type="submit"
                                class="rounded-2xl bg-primary px-6 py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container transition-all cursor-pointer"
                            >
                                ✓ Guardar Diseño de Tirilla
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SIMULADOR TÉRMICO LIVE PREVIEW 80MM -->
            <div class="lg:col-span-5 sticky top-24">
                <div class="rounded-3xl border border-outline-variant/25 bg-surface-container-low p-5 shadow-lg space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] text-secondary">visibility</span>
                            <span class="text-xs font-black uppercase tracking-wider text-on-surface">Simulador Térmico 80mm</span>
                        </div>
                        <span class="text-[10px] font-mono font-bold bg-surface-container px-2 py-0.5 rounded text-on-surface-variant">ESC/POS · 48 Col</span>
                    </div>

                    <!-- Rollo continuo de papel térmico -->
                    <div class="relative mx-auto max-w-[340px] rounded-lg bg-white p-5 text-gray-900 shadow-2xl font-mono text-[11px] leading-tight border-x border-gray-300 select-none">
                        <!-- Corte dentado superior -->
                        <div class="absolute -top-2 left-0 right-0 h-2 bg-gradient-to-r from-transparent via-gray-200 to-transparent" style="background-image: radial-gradient(circle, transparent 2px, white 2px); background-size: 8px 8px;"></div>

                        <!-- Header del ticket -->
                        <div class="text-center space-y-1 pb-2 border-b border-dashed border-gray-400">
                            <p class="text-base font-black tracking-wider uppercase">{{ $ticketForm['nombre_comercial'] ?: 'RESTOMASTER' }}</p>
                            @if(!empty($ticketForm['lema']))
                                <p class="text-[10px] font-semibold text-gray-600">{{ $ticketForm['lema'] }}</p>
                            @endif
                            <p class="text-[10px] font-bold">{{ $ticketForm['razon_social'] ?: 'RestoMaster Colombia S.A.S.' }}</p>
                            <p class="text-[10px]">{{ $ticketForm['nit'] ?: 'NIT: 901.458.789-3' }}</p>
                            <p class="text-[9px] text-gray-600">{{ $ticketForm['regimen'] ?: 'IVA Régimen Común' }}</p>
                            <p class="text-[10px]">{{ $ticketForm['direccion'] ?: 'Cra 35 # 8A-12, Medellín' }}</p>
                            <p class="text-[10px]">{{ $ticketForm['telefono'] ?: 'Tel: +57 300 123 4567' }}</p>
                            @if(!empty($ticketForm['mensaje_bienvenida']))
                                <p class="text-[10px] italic pt-1 text-gray-700 font-sans">"{{ $ticketForm['mensaje_bienvenida'] }}"</p>
                            @endif
                        </div>

                        <!-- Info de Factura y Transacción -->
                        <div class="py-2 space-y-0.5 text-[10px] border-b border-dashed border-gray-400">
                            <div class="flex justify-between font-bold">
                                <span>TIQUETE POS: SEC-000492</span>
                                <span>MESA #4</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>FECHA: {{ now()->format('d/m/Y H:i') }}</span>
                                @if(!empty($ticketForm['mostrar_datos_mesero']))
                                    <span>MESERO: Carlos M.</span>
                                @endif
                            </div>
                            @if(!empty($ticketForm['resolucion_dian']))
                                <p class="text-[8px] text-gray-500 pt-0.5">{{ $ticketForm['resolucion_dian'] }}</p>
                            @endif
                        </div>

                        <!-- Tabla de Items simulados -->
                        <div class="py-2 border-b border-dashed border-gray-400 space-y-1.5">
                            <div class="flex justify-between font-bold text-[10px] pb-1 border-b border-gray-300">
                                <span class="w-8">CANT</span>
                                <span class="flex-1">DESCRIPCIÓN</span>
                                <span class="text-right w-16">TOTAL</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="w-8 font-bold">2</span>
                                <span class="flex-1">Dragon Roll Imperial</span>
                                <span class="text-right w-16">$90,000</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="w-8 font-bold">1</span>
                                <span class="flex-1">Salmón Nigiri Especial</span>
                                <span class="text-right w-16">$32,000</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="w-8 font-bold">2</span>
                                <span class="flex-1">Té Verde Matcha Frío</span>
                                <span class="text-right w-16">$16,000</span>
                            </div>
                        </div>

                        <!-- Totales y Cálculos -->
                        <div class="py-2 space-y-1 border-b border-dashed border-gray-400 text-[10px]">
                            <div class="flex justify-between">
                                <span>SUBTOTAL:</span>
                                <span>$138,000.00</span>
                            </div>
                            @if(!empty($ticketForm['mostrar_desglose_impuestos']))
                                <div class="flex justify-between text-gray-600">
                                    <span>IMPOCONSUMO (8%):</span>
                                    <span>$11,040.00</span>
                                </div>
                            @endif
                            @if(!empty($ticketForm['sugerir_propina']))
                                <div class="flex justify-between text-gray-600">
                                    <span>PROPINA SUGERIDA (10%):</span>
                                    <span>$13,800.00</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-xs font-black pt-1 border-t border-gray-400">
                                <span>TOTAL A PAGAR:</span>
                                <span class="text-sm">${{ !empty($ticketForm['sugerir_propina']) ? '162,840.00' : '149,040.00' }}</span>
                            </div>
                            <div class="flex justify-between text-gray-600 text-[9px] pt-1">
                                <span>FORMA PAGO: TARJETA CRÉDITO</span>
                                <span>APROB: 839211</span>
                            </div>
                        </div>

                        <!-- Footer y Mensaje de despedida -->
                        <div class="pt-3 text-center space-y-1.5">
                            @if(!empty($ticketForm['pie_pagina']))
                                <p class="text-[10px] font-bold">{{ $ticketForm['pie_pagina'] }}</p>
                            @endif
                            @if(!empty($ticketForm['redes_sociales']))
                                <p class="text-[9px] text-gray-600 font-semibold">{{ $ticketForm['redes_sociales'] }}</p>
                            @endif
                            @if(!empty($ticketForm['politica_cambios']))
                                <p class="text-[8px] text-gray-500 leading-normal">{{ $ticketForm['politica_cambios'] }}</p>
                            @endif

                            @if(!empty($ticketForm['mostrar_qr']))
                                <!-- Simulación visual de código QR fiscal -->
                                <div class="pt-2 flex flex-col items-center">
                                    <div class="w-20 h-20 bg-gray-900 p-1 flex items-center justify-center rounded">
                                        <div class="w-full h-full bg-white grid grid-cols-4 gap-0.5 p-1">
                                            <div class="bg-gray-900"></div><div class="bg-transparent"></div><div class="bg-gray-900"></div><div class="bg-gray-900"></div>
                                            <div class="bg-transparent"></div><div class="bg-gray-900"></div><div class="bg-transparent"></div><div class="bg-gray-900"></div>
                                            <div class="bg-gray-900"></div><div class="bg-gray-900"></div><div class="bg-transparent"></div><div class="bg-transparent"></div>
                                            <div class="bg-gray-900"></div><div class="bg-transparent"></div><div class="bg-gray-900"></div><div class="bg-gray-900"></div>
                                        </div>
                                    </div>
                                    <span class="text-[8px] text-gray-500 mt-1">Verificación Factura DIAN</span>
                                </div>
                            @endif

                            <p class="text-[8px] text-gray-400 pt-2">*** SOFTWARE POS RESTOMASTER ***</p>
                        </div>

                        <!-- Corte dentado inferior -->
                        <div class="absolute -bottom-2 left-0 right-0 h-2 bg-gradient-to-r from-transparent via-gray-200 to-transparent" style="background-image: radial-gradient(circle, transparent 2px, white 2px); background-size: 8px 8px;"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- TAB 2: BASE DE DATOS, ENLACE EXTERNO & COPIAS DE SEGURIDAD             -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'database')
        <div class="space-y-6 animate-fade-in">
            <!-- Conexión a Base de Datos en Línea -->
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm space-y-4">
                <div class="border-b border-outline-variant/15 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">cloud_sync</span>
                            Enlace a Base de Datos PostgreSQL en Línea / Remota
                        </h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Configura una instancia en la nube (AWS RDS, Supabase, Neon o Servidor VPS) para replicación o migración.</p>
                    </div>
                    @if($testDbResultado['ok'] === true)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-secondary/15 text-secondary border border-secondary/30 text-xs font-bold">
                            <span class="w-2 h-2 rounded-full bg-secondary"></span>
                            Conectado ({{ $testDbResultado['latencia_ms'] }} ms)
                        </span>
                    @elseif($testDbResultado['ok'] === false)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-error/15 text-error border border-error/30 text-xs font-bold">
                            <span class="w-2 h-2 rounded-full bg-error"></span>
                            Sin conexión
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Dirección del Host / IP</label>
                        <input type="text" wire:model="dbForm.host" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono text-on-surface focus:border-primary focus:ring-0" placeholder="ep-silent-moon.aws.neon.tech" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Puerto</label>
                        <input type="number" wire:model="dbForm.port" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono text-on-surface focus:border-primary focus:ring-0" placeholder="5432" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre de la Base de Datos</label>
                        <input type="text" wire:model="dbForm.database" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono text-on-surface focus:border-primary focus:ring-0" placeholder="restomaster_cloud" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Usuario</label>
                        <input type="text" wire:model="dbForm.username" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono text-on-surface focus:border-primary focus:ring-0" placeholder="postgres" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Contraseña</label>
                        <input type="password" wire:model="dbForm.password" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono text-on-surface focus:border-primary focus:ring-0" placeholder="••••••••••••" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Modo SSL</label>
                        <select wire:model="dbForm.sslmode" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0">
                            <option value="prefer">Prefer (Recomendado)</option>
                            <option value="require">Require (Obligatorio en Cloud)</option>
                            <option value="disable">Disable (Solo Red Local)</option>
                        </select>
                    </div>
                </div>

                @if(!empty($testDbResultado['error']))
                    <div class="p-3 rounded-xl bg-error/10 border border-error/20 text-xs font-mono text-error">
                        {{ $testDbResultado['error'] }}
                    </div>
                @endif

                <div class="flex items-center justify-between pt-2">
                    <button
                        type="button"
                        wire:click="probarConexionDb"
                        class="rounded-xl border border-outline-variant/30 bg-surface-container-high px-4 py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container cursor-pointer flex items-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[16px]">network_check</span>
                        Probar Conexión en Línea
                    </button>
                    <button
                        type="button"
                        wire:click="guardarConexionDb"
                        class="rounded-xl bg-primary px-5 py-2.5 text-xs font-black text-on-primary shadow-sm hover:bg-primary-container cursor-pointer"
                    >
                        Guardar Parámetros BD
                    </button>
                </div>
            </div>

            <!-- Copias de Seguridad (Backups) e Importación -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Generador y Lista de Backups -->
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                        <div>
                            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">backup</span>
                                Copias de Seguridad del Sistema
                            </h3>
                            <p class="text-xs text-on-surface-variant">Copia completa: base de datos (.sql) + archivos e imágenes (.zip).</p>
                        </div>
                        <button
                            type="button"
                            wire:click="crearBackup"
                            wire:loading.attr="disabled"
                            class="rounded-xl bg-secondary px-3.5 py-2 text-xs font-black text-white shadow-sm hover:bg-secondary/90 disabled:opacity-60 cursor-pointer flex items-center gap-1"
                        >
                            <span class="material-symbols-outlined text-[16px]">add_circle</span>
                            Crear Copia Completa
                        </button>
                    </div>
                    <p wire:loading wire:target="crearBackup" class="text-[11px] font-bold text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px] animate-spin">progress_activity</span>
                        Generando respaldo de BD + archivos, un momento…
                    </p>

                    <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                        @forelse($backups as $bk)
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-surface-container-low border border-outline-variant/20">
                                <div class="flex items-center gap-2.5">
                                    <span class="material-symbols-outlined text-primary text-[22px]">{{ ($bk['tipo'] ?? 'bd') === 'archivos' ? 'folder_zip' : 'description' }}</span>
                                    <div>
                                        <p class="text-xs font-mono font-bold text-on-surface flex items-center gap-1.5">
                                            {{ $bk['nombre'] }}
                                            <span class="rounded-full px-1.5 py-px text-[9px] font-black uppercase tracking-wide {{ ($bk['tipo'] ?? 'bd') === 'archivos' ? 'bg-tertiary/15 text-tertiary border border-tertiary/30' : 'bg-primary/10 text-primary border border-primary/25' }}">
                                                {{ ($bk['tipo'] ?? 'bd') === 'archivos' ? 'Archivos' : 'BD' }}
                                            </span>
                                        </p>
                                        <p class="text-[10px] text-on-surface-variant">{{ $bk['fecha'] }} · {{ $bk['tamano_kb'] }} KB</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="restaurarCopia('{{ $bk['nombre'] }}')"
                                        wire:confirm="¿Restaurar esta copia? Los datos o archivos actuales serán reemplazados."
                                        class="p-1.5 rounded-lg text-secondary hover:bg-secondary/10 cursor-pointer"
                                        title="Restaurar esta copia"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">history</span>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="eliminarBackup('{{ $bk['nombre'] }}')"
                                        class="p-1.5 rounded-lg text-error hover:bg-error/10 cursor-pointer"
                                        title="Eliminar este respaldo"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-on-surface-variant text-xs font-medium">
                                No se han generado copias de seguridad locales todavía.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Importar / Restaurar Backup -->
                <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm space-y-4">
                    <div class="border-b border-outline-variant/15 pb-3">
                        <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">cloud_download</span>
                            Importar y Restaurar Base de Datos
                        </h3>
                        <p class="text-xs text-on-surface-variant">Sube un archivo de respaldo .sql para sobreescribir o restaurar datos del POS.</p>
                    </div>

                    <form wire:submit="restaurarBackup" class="space-y-4">
                        <div class="p-6 rounded-2xl border-2 border-dashed border-outline-variant/40 bg-surface-container-low flex flex-col items-center justify-center gap-2 text-center">
                            <span class="material-symbols-outlined text-[36px] text-primary">upload_file</span>
                            <p class="text-xs font-bold text-on-surface">Selecciona o arrastra tu archivo .sql de respaldo</p>
                            <input type="file" wire:model="archivoBackup" accept=".sql,.dump,.txt" class="text-xs text-on-surface-variant cursor-pointer" />
                            @error('archivoBackup') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">warning</span>
                            <span>La restauración ejecutará las sentencias SQL contenidas en el archivo sobre la base de datos actual.</span>
                        </div>

                        <button
                            type="submit"
                            @if(!$archivoBackup) disabled @endif
                            class="w-full rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container disabled:opacity-50 transition-all cursor-pointer"
                        >
                            Ejecutar Restauración del Respaldo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- TAB 3: IMPRESORAS TÉRMICAS & HARDWARE ESC/POS                         -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'impresoras')
        <div class="space-y-6 animate-fade-in">
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div>
                        <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">print</span>
                            Impresoras Térmicas de Red & Punto de Venta
                        </h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Asigna y parametriza las terminales de impresión por socket TCP (puerto 9100) para comanda y tirilla.</p>
                    </div>
                    <button
                        type="button"
                        wire:click="abrirNuevaImpresora"
                        class="rounded-xl bg-primary px-4 py-2.5 text-xs font-black text-on-primary shadow-sm hover:bg-primary-container cursor-pointer flex items-center gap-1"
                    >
                        <span class="material-symbols-outlined text-[18px]">add</span>
                        + Nueva Impresora
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($impresoras as $imp)
                        <div class="p-4 rounded-2xl border border-outline-variant/25 bg-surface-container-low space-y-3 relative group">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[24px] text-primary">local_printshop</span>
                                    <div>
                                        <h4 class="text-xs font-extrabold text-on-surface">{{ $imp->nombre }}</h4>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Área: {{ $imp->area }}</span>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $imp->activa ? 'bg-secondary/20 text-secondary border border-secondary/30' : 'bg-surface-container-high text-on-surface-variant' }}">
                                    {{ $imp->activa ? 'ACTIVA' : 'INACTIVA' }}
                                </span>
                            </div>

                            <div class="space-y-1 text-[11px] font-mono text-on-surface-variant bg-surface-container-lowest p-2.5 rounded-xl border border-outline-variant/15">
                                @if($imp->tipo_conexion === 'usb_local' || $imp->tipo_conexion === 'driver_sistema')
                                    <p>Driver SO: <strong class="text-on-surface text-primary">{{ $imp->driver_nombre ?: 'Sin driver asignado' }}</strong></p>
                                @else
                                    <p>IP: <strong class="text-on-surface">{{ $imp->ip_address }}:{{ $imp->puerto }}</strong></p>
                                @endif
                                <p>Papel: <strong class="text-on-surface">{{ $imp->ancho_columnas === 48 ? '80mm (48 col)' : '58mm (42 col)' }}</strong></p>
                                <p>Conexión: <strong class="text-on-surface uppercase">{{ $imp->tipo_conexion }}</strong> · Copias: {{ $imp->copias }}</p>
                            </div>

                            <div class="flex items-center justify-between pt-1 border-t border-outline-variant/15">
                                <button
                                    type="button"
                                    wire:click="enviarImpresionPrueba({{ $imp->id }})"
                                    class="text-xs font-bold text-secondary hover:underline flex items-center gap-1 cursor-pointer"
                                >
                                    <span class="material-symbols-outlined text-[16px]">receipt</span>
                                    Ticket Prueba
                                </button>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="toggleImpresora({{ $imp->id }})"
                                        class="p-1 rounded-lg text-on-surface-variant hover:text-on-surface cursor-pointer"
                                        title="Activar/Desactivar"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">{{ $imp->activa ? 'toggle_on' : 'toggle_off' }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="editarImpresora({{ $imp->id }})"
                                        class="p-1 rounded-lg text-primary hover:bg-primary/10 cursor-pointer"
                                        title="Editar"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full p-8 text-center text-xs text-on-surface-variant">
                            No hay impresoras configuradas en el sistema.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Modal de Impresora -->
        @if($mostrarModalImpresora)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4 animate-fade-in">
                <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20 space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                        <h3 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">print</span>
                            {{ $impresoraEnEdicion ? 'Editar Impresora' : 'Nueva Impresora Térmica' }}
                        </h3>
                        <button wire:click="$set('mostrarModalImpresora', false)" class="text-on-surface-variant hover:text-on-surface cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>

                    <form wire:submit="guardarImpresora" class="space-y-3.5">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Nombre de la Impresora</label>
                            <input type="text" wire:model="impresoraForm.nombre" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: Impresora Caja Principal" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-bold text-on-surface-variant">Tipo de Conexión</label>
                                <select wire:model.live="impresoraForm.tipo_conexion" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0">
                                    <option value="usb_local">USB / Driver Local (Windows)</option>
                                    <option value="red">Red Ethernet / Wi-Fi (Socket TCP)</option>
                                    <option value="driver_navegador">Navegador Web (Diálogo Imprimir)</option>
                                    <option value="virtual_simulador">Virtual (Simulador Archivo)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-on-surface-variant">Área Asignada</label>
                                <select wire:model="impresoraForm.area" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0">
                                    <option value="caja">Caja / Facturación</option>
                                    <option value="sushi">Cocina Fría & Entradas</option>
                                    <option value="caliente">Cocina Caliente & Parrilla</option>
                                    <option value="barra">Barra / Bebidas</option>
                                </select>
                            </div>
                        </div>

                        {{-- Campos condicionales según tipo de conexión --}}
                        @if(($impresoraForm['tipo_conexion'] ?? '') === 'usb_local' || ($impresoraForm['tipo_conexion'] ?? '') === 'driver_sistema')
                            <div class="rounded-2xl border border-primary/20 bg-primary/5 p-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold text-on-surface">Nombre del Driver en Windows</label>
                                    <button 
                                        type="button" 
                                        wire:click="detectarImpresorasSO" 
                                        wire:loading.attr="disabled"
                                        class="text-[11px] font-bold text-primary hover:underline flex items-center gap-1 cursor-pointer"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">search</span>
                                        <span wire:loading.remove wire:target="detectarImpresorasSO">Detectar del Equipo</span>
                                        <span wire:loading wire:target="detectarImpresorasSO">Detectando...</span>
                                    </button>
                                </div>
                                <input 
                                    type="text" 
                                    wire:model="impresoraForm.driver_nombre" 
                                    class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-xs font-mono font-bold text-on-surface focus:border-primary focus:ring-0" 
                                    placeholder="Ej: POS-80, Epson TM-T20, Generic / Text Only" 
                                />

                                @if(!empty($impresorasDetectadasSO))
                                    <div class="pt-1">
                                        <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider mb-1">Impresoras detectadas en este PC:</p>
                                        <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                                            @foreach($impresorasDetectadasSO as $driverName)
                                                <button
                                                    type="button"
                                                    wire:click="seleccionarDriverDetectado('{{ $driverName }}')"
                                                    class="rounded-lg border px-2 py-1 text-[11px] font-mono transition-all cursor-pointer {{ ($impresoraForm['driver_nombre'] ?? '') === $driverName ? 'border-primary bg-primary text-on-primary font-bold' : 'border-outline-variant/30 bg-surface-container-low text-on-surface hover:bg-surface-container-high' }}"
                                                >
                                                    {{ $driverName }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <p class="text-[10px] text-on-surface-variant">El sistema enviará el texto térmico formateado directo a la cola de impresión de Windows.</p>
                            </div>
                        @elseif(($impresoraForm['tipo_conexion'] ?? '') === 'red')
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Dirección IP</label>
                                    <input type="text" wire:model="impresoraForm.ip_address" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono text-on-surface focus:border-primary focus:ring-0" placeholder="192.168.1.200" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-on-surface-variant">Puerto TCP</label>
                                    <input type="number" wire:model="impresoraForm.puerto" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono text-on-surface focus:border-primary focus:ring-0" placeholder="9100" />
                                </div>
                            </div>
                        @else
                            <div class="p-3 rounded-2xl bg-surface-container-low border border-outline-variant/20 text-xs text-on-surface-variant">
                                Esta modalidad usará la ventana emergente de impresión nativa de tu explorador o guardará registros de simulación en disco.
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-bold text-on-surface-variant">Ancho de Papel</label>
                                <select wire:model="impresoraForm.ancho_columnas" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0">
                                    <option value="48">80 mm (48 Columnas)</option>
                                    <option value="42">58 mm (42 Columnas)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-on-surface-variant">Número de Copias</label>
                                <input type="number" min="1" max="5" wire:model="impresoraForm.copias" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono text-on-surface focus:border-primary focus:ring-0" />
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-2 pt-2">
                            <button type="button" wire:click="$set('mostrarModalImpresora', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-2.5 text-xs font-extrabold text-on-surface-variant hover:text-on-surface cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit" class="rounded-2xl bg-primary py-2.5 text-xs font-black text-on-primary shadow-md hover:bg-primary-container cursor-pointer">
                                Guardar Impresora
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif

    <!-- ===================================================================== -->
    <!-- TAB 4: FACTURACIÓN ELECTRÓNICA & RESOLUCIÓN DIAN                      -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'dian')
        <form wire:submit="guardarDian" class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 space-y-4 animate-fade-in shadow-sm">
            <div class="border-b border-outline-variant/15 pb-3">
                <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">verified</span>
                    Parametrización DIAN / Facturación Electrónica
                </h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Parámetros del emisor y rangos autorizados para documentos electrónicos en Colombia.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="text-xs font-bold text-on-surface-variant">Razón social</label>
                    <input type="text" wire:model="dianForm.razon_social" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">NIT</label>
                    <input type="text" wire:model="dianForm.nit" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Régimen</label>
                    <input type="text" wire:model="dianForm.regimen" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Ambiente</label>
                    <select wire:model="dianForm.ambiente" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0">
                        <option value="habilitacion">Habilitación</option>
                        <option value="produccion">Producción</option>
                    </select></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Tipo documento</label>
                    <input type="text" wire:model="dianForm.tipo_documento" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">N° resolución</label>
                    <input type="text" wire:model="dianForm.resolucion_numero" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Prefijo</label>
                    <input type="text" wire:model="dianForm.prefijo" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Fecha resolución</label>
                    <input type="date" wire:model="dianForm.resolucion_fecha" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Rango desde</label>
                    <input type="text" wire:model="dianForm.desde" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Rango hasta</label>
                    <input type="text" wire:model="dianForm.hasta" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
            </div>
            <div class="flex items-center gap-4 pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="dianForm.vigente" class="rounded text-primary focus:ring-0" />
                    <span class="text-xs font-bold text-on-surface-variant">Resolución vigente</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="dianForm.envio_activo" class="rounded text-primary focus:ring-0" />
                    <span class="text-xs font-bold text-on-surface-variant">Facturación electrónica activa</span>
                </label>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-6 py-3 text-xs font-black text-on-primary shadow-sm hover:bg-primary-container cursor-pointer">
                Guardar Configuración DIAN
            </button>
        </form>
    @endif

    <!-- ===================================================================== -->
    <!-- TAB 5: ESTABLECIMIENTO & PARÁMETROS COMERCIALES                       -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'empresa')
        <form wire:submit="guardarEmpresa" class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 space-y-4 animate-fade-in shadow-sm">
            <div class="border-b border-outline-variant/15 pb-3">
                <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">storefront</span>
                    Datos del Establecimiento Comercial & Moneda
                </h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Información general del restaurante, moneda base y tarifas operativas.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Razón Social</label>
                    <input type="text" wire:model="empresaForm.razon_social" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">NIT / Cédula</label>
                    <input type="text" wire:model="empresaForm.nit" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Dirección</label>
                    <input type="text" wire:model="empresaForm.direccion" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Teléfono</label>
                    <input type="text" wire:model="empresaForm.telefono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Ciudad & País</label>
                    <input type="text" wire:model="empresaForm.ciudad" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Moneda</label>
                        <input type="text" wire:model="empresaForm.moneda" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold focus:border-primary focus:ring-0" placeholder="COP" />
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Símbolo</label>
                        <input type="text" wire:model="empresaForm.simbolo_moneda" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm font-bold text-center focus:border-primary focus:ring-0" placeholder="$" />
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Impuesto al Consumo / IVA (%)</label>
                    <input type="number" step="0.1" wire:model="empresaForm.impuesto_porcentaje" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" placeholder="8.0" />
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Costo Base de Envío Delivery</label>
                    <input type="text" inputmode="decimal" data-miles data-decimales="0" wire:model="empresaForm.costo_envio_base" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" placeholder="8000" />
                </div>
            </div>
            <p class="text-[11px] text-on-surface-variant">Pie de ticket actual: <code class="font-mono bg-surface-container-low px-1.5 py-0.5 rounded">{{ $pieTicket }}</code></p>
            <button type="submit" class="rounded-xl bg-primary px-6 py-3 text-xs font-black text-on-primary shadow-sm hover:bg-primary-container cursor-pointer">
                Guardar Datos de Establecimiento
            </button>
        </form>
    @endif

    <!-- ===================================================================== -->
    <!-- TAB 6: RESERVAS & WEBHOOK N8N / WHATSAPP                              -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'reservas')
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 space-y-4 animate-fade-in shadow-sm">
            <div class="border-b border-outline-variant/15 pb-3">
                <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">webhook</span>
                    Integración de Reservas & Webhook Externo
                </h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Permite a bots de WhatsApp (vía n8n o agregadores) registrar reservas automáticamente.</p>
            </div>
            <div>
                <label class="text-xs font-bold text-on-surface-variant">Token Secreto de Seguridad (X-Webhook-Token)</label>
                <div class="mt-1 flex items-center gap-2">
                    <code class="flex-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono break-all text-on-surface select-all">{{ $webhookToken }}</code>
                    <button type="button" wire:click="regenerarToken" class="rounded-xl bg-secondary px-4 py-2 text-xs font-bold text-white shadow-xs cursor-pointer">Regenerar</button>
                </div>
                <p class="mt-2 text-[11px] text-on-surface-variant">Endpoint: <code class="font-mono bg-surface-container-low px-1.5 py-0.5 rounded">POST /api/reservas</code> con header <code class="font-mono bg-surface-container-low px-1.5 py-0.5 rounded">X-Webhook-Token</code>.</p>
            </div>
            <div class="flex items-center gap-2 pt-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="reservasForm.webhook_activo" wire:change="toggleWebhook" class="rounded text-primary focus:ring-0" />
                    <span class="text-xs font-bold text-on-surface">Webhook de reservas activo y escuchando peticiones</span>
                </label>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- TAB SEGURIDAD: PIN DE AUTORIZACIÓN & RESCATE DE SUPERVISOR            -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'seguridad')
        <div class="space-y-6 animate-fade-in">
            <!-- Encabezado y Estado Actual -->
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-outline-variant/15 pb-4">
                    <div>
                        <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">shield_lock</span>
                            PIN de Seguridad para Autorizaciones Rápidas
                        </h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">
                            Utilizado por supervisores, administradores y gerentes para autorizar devoluciones de ítems, reaperturas, descuentos y aperturas de gaveta en POS y Caja.
                        </p>
                    </div>

                    <div>
                        @if ($tienePin)
                            <div class="inline-flex items-center gap-2 rounded-2xl bg-secondary/15 border border-secondary/30 px-3.5 py-1.5 text-xs font-black text-secondary">
                                <span class="material-symbols-outlined text-[18px]">verified_user</span>
                                <span>PIN ACTIVO Y PROTEGIDO</span>
                            </div>
                        @else
                            <div class="inline-flex items-center gap-2 rounded-2xl bg-amber-500/15 border border-amber-500/30 px-3.5 py-1.5 text-xs font-black text-amber-500">
                                <span class="material-symbols-outlined text-[18px]">warning</span>
                                <span>SIN PIN CONFIGURADO</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Formulario de Configuración / Cambio de PIN -->
                <form wire:submit.prevent="guardarPinSeguridad" class="mt-6 max-w-xl space-y-4">
                    @if ($tienePin)
                        <div>
                            <label class="text-xs font-bold text-on-surface">PIN Actual</label>
                            <input
                                type="password"
                                maxlength="6"
                                wire:model="pinForm_pinActual"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-4 py-2.5 text-sm tracking-widest focus:border-primary focus:ring-0"
                                placeholder="••••"
                            />
                            @error('pinForm_pinActual')
                                <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-on-surface">
                                {{ $tienePin ? 'Nuevo PIN (4 a 6 dígitos)' : 'Crear PIN (4 a 6 dígitos)' }}
                            </label>
                            <input
                                type="password"
                                maxlength="6"
                                wire:model="pinForm_nuevoPin"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-4 py-2.5 text-sm tracking-widest focus:border-primary focus:ring-0"
                                placeholder="1234"
                            />
                            @error('pinForm_nuevoPin')
                                <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-xs font-bold text-on-surface">Confirmar Nuevo PIN</label>
                            <input
                                type="password"
                                maxlength="6"
                                wire:model="pinForm_confirmarPin"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-4 py-2.5 text-sm tracking-widest focus:border-primary focus:ring-0"
                                placeholder="1234"
                            />
                            @error('pinForm_confirmarPin')
                                <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            class="rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-on-primary shadow-md hover:bg-primary/90 cursor-pointer flex items-center gap-1.5"
                        >
                            <span class="material-symbols-outlined text-[16px]">save</span>
                            <span>{{ $tienePin ? 'Actualizar PIN de Seguridad' : 'Guardar PIN de Seguridad' }}</span>
                        </button>
                    </div>
                </form>

                <!-- Canales de Rescate / Olvido de PIN -->
                @if ($tienePin)
                    <div class="mt-8 border-t border-outline-variant/15 pt-5">
                        <h3 class="text-xs font-black text-on-surface flex items-center gap-1.5 uppercase tracking-wider text-on-surface-variant">
                            <span class="material-symbols-outlined text-primary text-[18px]">lock_reset</span>
                            ¿Olvidaste o perdiste el PIN de seguridad?
                        </h3>
                        <p class="text-xs text-on-surface-variant mt-1">
                            Como Administrador del restaurante, dispones de dos mecanismos de rescate seguros para restablecerlo:
                        </p>

                        <div class="mt-3 flex flex-wrap gap-3">
                            <button
                                type="button"
                                wire:click="abrirModalRescatePin('password')"
                                class="rounded-xl bg-surface-container-high border border-outline-variant/30 px-3.5 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-highest cursor-pointer flex items-center gap-1.5 shadow-xs"
                            >
                                <span class="material-symbols-outlined text-primary text-[16px]">key</span>
                                <span>Rescatar con mi contraseña de usuario</span>
                            </button>

                            <button
                                type="button"
                                wire:click="abrirModalRescatePin('otp')"
                                class="rounded-xl bg-surface-container-high border border-outline-variant/30 px-3.5 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-highest cursor-pointer flex items-center gap-1.5 shadow-xs"
                            >
                                <span class="material-symbols-outlined text-secondary text-[16px]">mail</span>
                                <span>Enviar código de rescate OTP a mi correo</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- TAB IA: IA & COPILOTO (Gemini Function Calling)                         -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'ia')
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 space-y-5 animate-fade-in shadow-sm">
            <div class="border-b border-outline-variant/15 pb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">smart_toy</span>
                        IA & Copiloto Ejecutivo
                    </h2>
                    <p class="text-sm text-on-surface mt-0.5">Conecta Gemini para respuestas con herramientas reales (ventas, caja, inventario, meseros, platos).</p>
                </div>
                @if ($iaEstado === 'conectado')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/15 px-3 py-1.5 text-sm font-bold text-secondary border border-secondary/30">
                        <span>🟢</span><span>Conectado</span>
                    </span>
                @elseif ($iaEstado === 'error')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-error/10 px-3 py-1.5 text-sm font-bold text-error border border-error/30">
                        <span>🔴</span><span>Error</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-high px-3 py-1.5 text-sm font-bold text-on-surface-variant border border-outline-variant/30">
                        <span>⚫</span><span>No configurado</span>
                    </span>
                @endif
            </div>

            <form wire:submit="guardarIa" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="flex items-center gap-2 p-3 rounded-2xl bg-surface-container-low border border-outline-variant/20 cursor-pointer min-h-[44px]">
                        <input type="checkbox" wire:model.live="iaForm.activo" class="rounded text-primary focus:ring-0 w-5 h-5" />
                        <span class="text-sm font-bold text-on-surface">Copiloto IA activo</span>
                    </label>
                    <div>
                        <label class="text-sm font-bold text-on-surface">Proveedor</label>
                        <select wire:model="iaForm.proveedor" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-sm text-on-surface focus:border-primary focus:ring-0 min-h-[44px]">
                            <option value="gemini">Gemini (Google)</option>
                            <option value="openai">OpenAI</option>
                        </select>
                        @error('iaForm.proveedor')
                            <p class="text-sm text-error font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-bold text-on-surface">Modelo activo</label>
                        <input type="text" wire:model="iaForm.modelo" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-sm text-on-surface focus:border-primary focus:ring-0 min-h-[44px]" placeholder="gemini-2.5-flash" />
                        @error('iaForm.modelo')
                            <p class="text-sm text-error font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">key</span>
                            Gemini API Key
                            @if ($iaTieneClave)
                                <span class="text-xs font-mono text-on-surface">({{ $iaClaveEnmascarada }})</span>
                            @endif
                        </label>
                        <input type="password" wire:model="iaForm.api_key" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-sm text-on-surface focus:border-primary focus:ring-0 min-h-[44px]" placeholder="{{ $iaTieneClave ? 'Dejar vacío para conservar la clave actual' : 'Pega la API Key de Gemini' }}" />
                        @error('iaForm.api_key')
                            <p class="text-sm text-error font-medium mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-on-surface mt-1">Se guarda cifrada. Nunca se muestra completa ni se registra en logs.</p>
                    </div>
                </div>

                @if ($iaLatencia !== null || $iaModeloActivo !== '' || $iaUltimoError !== '')
                    <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-low p-4 text-sm text-on-surface space-y-1">
                        @if ($iaLatencia !== null)
                            <p class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">speed</span> Latencia: <strong>{{ $iaLatencia }} ms</strong></p>
                        @endif
                        @if ($iaModeloActivo !== '')
                            <p class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">memory</span> Modelo: <strong>{{ $iaModeloActivo }}</strong></p>
                        @endif
                        @if ($iaUltimoError !== '')
                            <p class="text-sm font-medium">{{ $iaUltimoError }}</p>
                        @endif
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-on-primary shadow-xs cursor-pointer min-h-[44px] min-w-[44px]">
                        Guardar configuración IA
                    </button>
                    <button type="button" wire:click="probarConexionIa" class="rounded-xl bg-secondary px-5 py-2.5 text-sm font-bold text-white shadow-xs cursor-pointer min-h-[44px] min-w-[44px] flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">wifi_tethering</span>
                        Probar conexión
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- TAB 7: RESTABLECIMIENTO / RESET DE FÁBRICA                            -->
    <!-- ===================================================================== -->
    @if ($tabActiva === 'reset')
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 space-y-4 animate-fade-in shadow-sm">
            <div class="border-b border-outline-variant/15 pb-3">
                <h2 class="text-base font-extrabold text-error flex items-center gap-2">
                    <span class="material-symbols-outlined text-error">restart_alt</span>
                    Zona de Seguridad: Restablecer Valores de Fábrica
                </h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Restaura todas las variables de maquetación de tickets, datos generales y opciones fiscales a sus valores recomendados originales.</p>
            </div>

            <div class="p-4 rounded-2xl bg-error/10 border border-error/30 text-xs text-error space-y-2">
                <p class="font-bold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">warning</span>
                    Atención: Esta acción sobreescribirá los parámetros personalizados actuales.
                </p>
                <p class="text-on-surface-variant">Los datos de pedidos, inventario y ventas no se perderán. Solo se reiniciarán las configuraciones de empresa y maquetador de tirillas.</p>
            </div>

            <div class="pt-2">
                <button
                    type="button"
                    wire:click="restablecerFabrica"
                    wire:confirm="¿Estás seguro de que deseas restablecer todas las configuraciones a sus valores originales de fábrica?"
                    class="rounded-2xl bg-error px-6 py-3 text-xs font-black text-white shadow-md hover:bg-error/90 cursor-pointer"
                >
                    Restablecer Todas las Configuraciones a Fábrica
                </button>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL DE RESCATE / RECUPERACIÓN DE PIN                                -->
    <!-- ===================================================================== -->
    @if ($mostrarModalRescatePin)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fade-in">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20 space-y-4">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <h3 class="text-base font-extrabold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">lock_reset</span>
                        Rescate de PIN de Seguridad
                    </h3>
                    <button type="button" wire:click="cerrarModalRescatePin" class="text-on-surface-variant hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                @if ($modoRescatePin === 'password')
                    <div class="space-y-4 text-xs">
                        <p class="text-on-surface-variant">
                            Ingresa tu <strong>contraseña de inicio de sesión</strong> de Administrador para autorizar el restablecimiento inmediato del PIN.
                        </p>

                        <div>
                            <label class="font-bold text-on-surface">Contraseña de Administrador</label>
                            <input
                                type="password"
                                wire:model="pinRescate_password"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs focus:border-primary focus:ring-0"
                                placeholder="••••••••"
                            />
                            @error('pinRescate_password')
                                <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="font-bold text-on-surface">Nuevo PIN (4-6 dígitos)</label>
                                <input
                                    type="password"
                                    maxlength="6"
                                    wire:model="pinRescate_nuevoPin"
                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs tracking-widest focus:border-primary focus:ring-0"
                                    placeholder="••••"
                                />
                                @error('pinRescate_nuevoPin')
                                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="font-bold text-on-surface">Confirmar PIN</label>
                                <input
                                    type="password"
                                    maxlength="6"
                                    wire:model="pinRescate_confirmarPin"
                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs tracking-widest focus:border-primary focus:ring-0"
                                    placeholder="••••"
                                />
                                @error('pinRescate_confirmarPin')
                                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" wire:click="cerrarModalRescatePin" class="rounded-xl px-4 py-2 font-bold text-on-surface-variant hover:bg-surface-container-high cursor-pointer">Cancelar</button>
                            <button type="button" wire:click="ejecutarRescateConPassword" class="rounded-xl bg-primary px-4 py-2 font-bold text-on-primary shadow-xs cursor-pointer">Restablecer PIN</button>
                        </div>
                    </div>
                @else
                    <div class="space-y-4 text-xs">
                        <div class="rounded-2xl bg-secondary/10 border border-secondary/30 p-3 text-secondary">
                            <p class="font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">mail</span>
                                Código de rescate enviado a {{ auth()->user()?->email }}
                            </p>
                            <p class="text-[11px] mt-0.5 text-on-surface-variant">Revisa tu bandeja de entrada o spam. El código es válido por 15 minutos.</p>
                        </div>

                        <div>
                            <label class="font-bold text-on-surface">Código de Rescate (6 dígitos)</label>
                            <input
                                type="text"
                                maxlength="6"
                                wire:model="pinRescate_otp"
                                class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm text-center font-mono tracking-widest focus:border-primary focus:ring-0"
                                placeholder="123456"
                            />
                            @error('pinRescate_otp')
                                <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="font-bold text-on-surface">Nuevo PIN (4-6 dígitos)</label>
                                <input
                                    type="password"
                                    maxlength="6"
                                    wire:model="pinRescate_nuevoPin"
                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs tracking-widest focus:border-primary focus:ring-0"
                                    placeholder="••••"
                                />
                                @error('pinRescate_nuevoPin')
                                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="font-bold text-on-surface">Confirmar PIN</label>
                                <input
                                    type="password"
                                    maxlength="6"
                                    wire:model="pinRescate_confirmarPin"
                                    class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs tracking-widest focus:border-primary focus:ring-0"
                                    placeholder="••••"
                                />
                                @error('pinRescate_confirmarPin')
                                    <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-between items-center pt-2">
                            <button type="button" wire:click="solicitarOtpRescate" class="text-primary hover:underline font-bold text-[11px] cursor-pointer">Reenviar código</button>
                            <div class="flex gap-2">
                                <button type="button" wire:click="cerrarModalRescatePin" class="rounded-xl px-4 py-2 font-bold text-on-surface-variant hover:bg-surface-container-high cursor-pointer">Cancelar</button>
                                <button type="button" wire:click="ejecutarRescateConOtp" class="rounded-xl bg-secondary px-4 py-2 font-bold text-white shadow-xs cursor-pointer">Restablecer con OTP</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>