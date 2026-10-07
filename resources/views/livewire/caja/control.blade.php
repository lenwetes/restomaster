<?php

use App\Models\User;

use App\Models\Caja;
use App\Models\ItemPedido;
use App\Models\NotaCredito;
use App\Models\Pedido;
use App\Models\TurnoCaja;
use App\Services\CajaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class () extends Component {
    // Shift selection and active shift
    public ?int $turnoId = null;

    public ?int $cajaSeleccionadaId = null;

    // Shift opening form
    public bool $mostrarModalApertura = false;

    public float $fondoInicial = 150000.0;

    public string $notasApertura = '';

    // Movement (Egreso / Retiro / Ingreso) modal
    public bool $mostrarModalMovimiento = false;

    public string $tipoMovimiento = 'egreso'; // egreso, retiro, ingreso

    public float $montoMovimiento = 0.0;

    public string $conceptoMovimiento = '';

    public string $comprobanteMovimiento = '';

    public string $autorizadoPor = '';

    // Arqueo y Cierre de Turno (CAJ-04 / CAJ-05)
    public bool $mostrarModalCierre = false;

    public float $montoContado = 0.0;

    public string $notasCierre = '';

    public ?array $reporteZ = null;

    public bool $mostrarModalReporteZ = false;

    // Nueva Terminal de Caja State
    public bool $modalNuevaCajaOpen = false;

    public array $formCaja = [
        'nombre' => '',
        'codigo' => '',
    ];

    // Vista y Gestión Individual por Caja (F7-04)
    public string $vistaCaja = 'operacion'; // 'operacion', 'por_caja', 'comparativo'

    public ?int $cajaReporteSeleccionadaId = null;

    public string $filtroFechaDesde = '';

    public string $filtroFechaHasta = '';

    // Gestión y Administración de Terminales State
    public bool $modalGestionTerminalesOpen = false;

    public ?int $cajaEditandoId = null;

    public array $formEditarCaja = [
        'nombre' => '',
        'codigo' => '',
        'tipo' => 'principal',
        'descripcion' => '',
    ];

    // Previsualización Rápida de Tickets Generados (Auditoría Anti-Fantasma)
    public bool $modalPrevisualizarTicket = false;

    public ?int $ticketPrevisualizadoId = null;

    public ?Pedido $pedidoTicketSeleccionado = null;

    public ?string $ticketTextoEscPos = null;

    public string $modoVistaTicket = 'visual'; // 'visual' o 'escpos'

    public bool $modalDevolucionItem = false;
    public ?ItemPedido $itemSeleccionadoDevolucion = null;
    public int $cantidadDevolucion = 1;
    public string $motivoDevolucion = 'Error de digitación del cajero';
    public string $metodoReembolsoDevolucion = 'efectivo';
    public string $pinAutorizacionDevolucion = '';
    public string $supervisorNombreDevolucion = '';
    public string $motivoNcDevolucion = 'error_cargo';
    public string $descripcionNcDevolucion = '';

    // Fase 8.1 — Cobros pendientes solicitados por meseros
    public bool $mostrarModalCobroPendiente = false;
    public ?int $cobroPendienteId = null;
    public string $metodoPagoPendiente = 'efectivo';
    public float $montoPagadoPendiente = 0.0;
    public float $propinaPendiente = 0.0;

    /**
     * Fase 8.1 — Solicitudes de cobro pendientes de la sucursal.
     */
    public function cobrosPendientes()
    {
        $sucursalId = Auth::user()?->sucursal_id;

        return Pedido::with(['mesa', 'mesero', 'usuario'])
            ->where('estado', 'pendiente_cobro')
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->orderBy('updated_at')
            ->get();
    }

    public ?array $notificacionCobroFlotante = null;
    public int $conteoPrevioCobrosPendientes = 0;

    public function verificarNuevasSolicitudesCobro(): void
    {
        $pendientes = $this->cobrosPendientes();
        $conteoActual = $pendientes->count();

        if ($conteoActual > $this->conteoPrevioCobrosPendientes && $conteoActual > 0) {
            $ultimo = $pendientes->last();
            $this->notificacionCobroFlotante = [
                'pedido_id' => $ultimo->id,
                'codigo' => $ultimo->codigo,
                'mesa' => $ultimo->mesa ? 'Mesa ' . $ultimo->mesa->numero : 'Mostrador',
                'mesero' => $ultimo->mesero?->name ?? $ultimo->usuario?->name ?? 'Mesero',
                'total' => (float) $ultimo->total,
                'hora' => now()->format('h:i A'),
            ];
            $this->dispatch('sonar-campana-caja');
        }

        $this->conteoPrevioCobrosPendientes = $conteoActual;
    }

    public function descartarNotificacionCobro(): void
    {
        $this->notificacionCobroFlotante = null;
    }

    public function abrirCobroPendiente(int $pedidoId): void
    {
        $this->authorize('cobrar', Pedido::class);
        $this->descartarNotificacionCobro();
        $this->cobroPendienteId = $pedidoId;
        $this->dispatch('abrir-modal-cobro-unificado', pedidoId: $pedidoId);
    }

    #[\Livewire\Attributes\On('pedido-cobrado-exitosamente')]
    public function alCobrarPedidoExitosamente(array $datos): void
    {
        $this->notificacionCobroFlotante = null;
        $this->conteoPrevioCobrosPendientes = $this->cobrosPendientes()->count();
    }

    public function cobrarPendiente(): void
    {
        $this->authorize('cobrar', Pedido::class);

        $this->validate([
            'cobroPendienteId' => ['required', 'integer', 'exists:pedidos,id'],
            'metodoPagoPendiente' => ['required', 'string', 'max:30'],
            'montoPagadoPendiente' => ['required', 'numeric', 'min:0'],
            'propinaPendiente' => ['nullable', 'numeric', 'min:0'],
        ]);

        $pedido = Pedido::findOrFail($this->cobroPendienteId);
        abort_if($pedido->estado !== 'pendiente_cobro', 422, 'El pedido ya no está pendiente de cobro.');
        abort_if(Auth::user()?->sucursal_id && $pedido->sucursal_id !== Auth::user()->sucursal_id, 403);

        try {
            app(\App\Services\PedidoService::class)->cobrarPedido(
                $pedido,
                $this->metodoPagoPendiente,
                (float) $this->montoPagadoPendiente,
                null,
                (float) ($this->propinaPendiente ?? 0.0),
                0.0
            );
        } catch (\Throwable $e) {
            $this->addError('montoPagadoPendiente', $e->getMessage());

            return;
        }

        $this->cerrarCobroPendiente();
        $this->dispatch('notificacion', [
            'mensaje' => "Cobro procesado: {$pedido->codigo}. Ticket generado para el mesero.",
            'tipo' => 'success',
        ]);
    }

    public function cerrarCobroPendiente(): void
    {
        $this->cobroPendienteId = null;
        $this->metodoPagoPendiente = 'efectivo';
        $this->montoPagadoPendiente = 0.0;
        $this->propinaPendiente = 0.0;
    }

    public function abrirModalDevolucion(int $itemId): void
    {
        $this->authorize('create', NotaCredito::class);

        $item = ItemPedido::with(['pedido', 'producto'])->findOrFail($itemId);
        if ($item->cantidadDisponibleDevolucion() <= 0) {
            $this->dispatch('notificacion', ['mensaje' => 'Este ítem ya fue devuelto en su totalidad.', 'tipo' => 'warning']);

            return;
        }

        $this->itemSeleccionadoDevolucion = $item;
        $this->cantidadDevolucion = 1;
        $this->motivoDevolucion = 'Error de digitación del cajero';
        $this->motivoNcDevolucion = 'error_cargo';
        $this->descripcionNcDevolucion = '';
        $this->metodoReembolsoDevolucion = $item->pedido?->metodo_pago ?? 'efectivo';
        $this->pinAutorizacionDevolucion = '';
        $this->supervisorNombreDevolucion = '';
        $this->resetErrorBag();
        $this->modalDevolucionItem = true;
    }

    public function cerrarModalDevolucion(): void
    {
        $this->modalDevolucionItem = false;
        $this->itemSeleccionadoDevolucion = null;
        $this->pinAutorizacionDevolucion = '';
        $this->resetErrorBag();
    }

    public function agregarDigitoPinDevolucion(string $digito): void
    {
        if (strlen($this->pinAutorizacionDevolucion) < 6) {
            $this->pinAutorizacionDevolucion .= $digito;
        }
    }

    public function borrarDigitoPinDevolucion(): void
    {
        $this->pinAutorizacionDevolucion = substr($this->pinAutorizacionDevolucion, 0, -1);
    }

    public function limpiarPinDevolucion(): void
    {
        $this->pinAutorizacionDevolucion = '';
    }

    public function procesarDevolucionItem(): void
    {
        if (! $this->itemSeleccionadoDevolucion) {
            return;
        }

        $this->authorize('create', NotaCredito::class);

        $this->validate([
            'motivoNcDevolucion' => ['required', 'string', 'in:error_cargo,producto_defectuoso,cambio_pedido,otro'],
            'descripcionNcDevolucion' => ['nullable', 'string', 'max:500'],
            'cantidadDevolucion' => ['required', 'integer', 'min:1'],
        ]);

        $user = Auth::user();
        $configSvc = app(\App\Services\ConfiguracionService::class);
        $esSupervisor = $user && in_array($user->role?->slug, ['admin', 'gerente'], true);

        $autorizadoPor = $user?->name ?? 'Supervisor';

        if (! $esSupervisor) {
            if ($configSvc->tienePinSeguridad()) {
                if (empty($this->pinAutorizacionDevolucion)) {
                    $this->addError('pinAutorizacionDevolucion', 'Debes ingresar el PIN de supervisor para autorizar la devolución.');

                    return;
                }
                if (! $configSvc->verificarPinSeguridad($this->pinAutorizacionDevolucion)) {
                    $this->addError('pinAutorizacionDevolucion', 'PIN de supervisor incorrecto.');

                    return;
                }
                $autorizadoPor = 'Supervisor (PIN Autorizado)';
            } else {
                if (empty(trim($this->supervisorNombreDevolucion))) {
                    $this->addError('supervisorNombreDevolucion', 'Ingresa el nombre del supervisor que autoriza.');

                    return;
                }
                $autorizadoPor = trim($this->supervisorNombreDevolucion);
            }
        } else {
            $autorizadoPor = ($user->name ?? 'Admin') . ' (' . ucfirst($user->role?->slug ?? 'Admin') . ')';
        }

        try {
            $item = $this->itemSeleccionadoDevolucion;
            $montoNc = round((float) $item->precio_unitario * (int) $this->cantidadDevolucion, 2);

            // Fase 8.3 — Nota de Crédito obligatoria antes de devolver.
            $notaCredito = NotaCredito::emitir(
                pedido: $item->pedido,
                motivo: $this->motivoNcDevolucion,
                autorizadoPor: $user,
                monto: $montoNc,
                descripcion: trim($this->descripcionNcDevolucion) !== '' ? trim($this->descripcionNcDevolucion) : $this->motivoDevolucion
            );

            $devolucion = app(\App\Services\PedidoService::class)->devolverItemPedido(
                item: $this->itemSeleccionadoDevolucion,
                cantidad: $this->cantidadDevolucion,
                motivo: $this->motivoDevolucion,
                autorizadoPor: $autorizadoPor,
                usuario: $user,
                metodoReembolso: $this->metodoReembolsoDevolucion,
                notaCreditoId: $notaCredito->id
            );

            $nombreProducto = $this->itemSeleccionadoDevolucion->nombre_producto;
            $monto = number_format((float) $devolucion->monto_devuelto, 0, ',', '.');

            $this->cerrarModalDevolucion();

            if ($this->pedidoTicketSeleccionado) {
                $this->abrirPrevisualizarTicket($this->pedidoTicketSeleccionado->id);
            }

            $this->dispatch('notificacion', [
                'mensaje' => "Devolución exitosa con {$notaCredito->numero_nc}: {$devolucion->cantidad}x {$nombreProducto} (-$" . $monto . '). Stock e inventario reajustados.',
                'tipo' => 'success',
            ]);
        } catch (\Throwable $e) {
            $this->addError('generalDevolucion', $e->getMessage());
        }
    }

    public function abrirPrevisualizarTicket(int $pedidoId): void
    {
        $pedido = Pedido::with([
            'mesa',
            'mesero',
            'usuario',
            'cliente',
            'items.producto',
            'turnoCaja.caja',
            'turnoCaja.cajero',
        ])->findOrFail($pedidoId);

        $usuario = Auth::user();
        if ($usuario && ! $usuario->isAdmin() && $usuario->sucursal_id && $pedido->sucursal_id != $usuario->sucursal_id) {
            abort(403, 'No autorizado para acceder a tickets de otra sucursal.');
        }

        $this->pedidoTicketSeleccionado = $pedido;
        $this->ticketPrevisualizadoId = $pedidoId;
        $this->ticketTextoEscPos = app(\App\Services\ImpresionService::class)->formatearTicketVentaTexto($this->pedidoTicketSeleccionado);
        $this->modalPrevisualizarTicket = true;
    }

    public function previsualizarUltimoTicket(): void
    {
        $query = Pedido::whereNotNull('pagado_en')->orderByDesc('pagado_en')->orderByDesc('id');
        if ($this->turnoId) {
            $query->where('turno_caja_id', $this->turnoId);
        }
        $ultimo = $query->first();

        if (! $ultimo) {
            $this->dispatch('notificacion', [
                'mensaje' => 'Aún no hay tickets cobrados en este turno.',
                'tipo' => 'warning',
            ]);

            return;
        }

        $this->abrirPrevisualizarTicket($ultimo->id);
    }

    public function cerrarModalTicket(): void
    {
        $this->modalPrevisualizarTicket = false;
        $this->pedidoTicketSeleccionado = null;
        $this->ticketPrevisualizadoId = null;
        $this->ticketTextoEscPos = null;
    }

    public function reenviarImpresionTicket(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);
        $usuario = Auth::user();
        if ($usuario && ! $usuario->isAdmin() && $usuario->sucursal_id && $pedido->sucursal_id != $usuario->sucursal_id) {
            abort(403, 'No autorizado para imprimir tickets de otra sucursal.');
        }
        app(\App\Services\ImpresionService::class)->despacharTicketVenta($pedido, $usuario);

        $this->dispatch('notificacion', [
            'mensaje' => "Ticket #{$pedido->codigo} re-enviado exitosamente a la cola de impresión.",
            'tipo' => 'success',
        ]);
    }

    public function abrirModalGestionTerminales(): void
    {
        $this->modalGestionTerminalesOpen = true;
        $this->cajaEditandoId = null;
    }

    public function iniciarEdicionCaja(int $id): void
    {
        $caja = Caja::findOrFail($id);
        $usuario = Auth::user();
        if ($usuario && ! $usuario->isAdmin() && $usuario->sucursal_id && $caja->sucursal_id != $usuario->sucursal_id) {
            abort(403, 'No autorizado para editar cajas de otra sucursal.');
        }
        $this->cajaEditandoId = $caja->id;
        $this->formEditarCaja = [
            'nombre' => $caja->nombre,
            'codigo' => $caja->codigo,
            'tipo' => $caja->tipo ?? 'principal',
            'descripcion' => $caja->descripcion ?? '',
        ];
    }

    public function cancelarEdicionCaja(): void
    {
        $this->cajaEditandoId = null;
        $this->formEditarCaja = ['nombre' => '', 'codigo' => '', 'tipo' => 'principal', 'descripcion' => ''];
    }

    public function guardarEdicionCaja(): void
    {
        if (! $this->cajaEditandoId) {
            return;
        }

        $caja = Caja::findOrFail($this->cajaEditandoId);
        $this->authorize('update', $caja);

        $this->validate([
            'formEditarCaja.nombre' => 'required|string|max:60',
            'formEditarCaja.codigo' => 'required|string|max:20|unique:cajas,codigo,' . $caja->id,
            'formEditarCaja.tipo' => 'nullable|string|in:principal,barra,delivery',
            'formEditarCaja.descripcion' => 'nullable|string|max:255',
        ]);

        app(CajaService::class)->actualizarCaja($caja, $this->formEditarCaja, Auth::user());
        $this->cajaEditandoId = null;

        $this->dispatch('notificacion', [
            'mensaje' => "Terminal {$caja->fresh()->nombre} actualizada con éxito.",
            'tipo' => 'success',
        ]);
    }

    public function alternarEstadoCaja(int $id): void
    {
        $caja = Caja::findOrFail($id);
        $this->authorize('update', $caja);

        app(CajaService::class)->alternarEstadoCaja($caja, Auth::user());

        $this->dispatch('notificacion', [
            'mensaje' => "Terminal {$caja->nombre} ahora está " . ($caja->fresh()->activa ? 'activa' : 'inactiva') . '.',
            'tipo' => 'info',
        ]);
    }

    public function eliminarCaja(int $id): void
    {
        $caja = Caja::findOrFail($id);
        $this->authorize('delete', $caja);

        try {
            $nombre = $caja->nombre;
            app(CajaService::class)->eliminarCaja($caja, Auth::user());

            if ($this->cajaSeleccionadaId === $id) {
                $this->cajaSeleccionadaId = Caja::value('id');
            }

            $this->dispatch('notificacion', [
                'mensaje' => "Terminal {$nombre} eliminada permanentemente.",
                'tipo' => 'success',
            ]);
        } catch (\DomainException $e) {
            $this->dispatch('notificacion', [
                'mensaje' => $e->getMessage(),
                'tipo' => 'error',
            ]);
        }
    }

    public function abrirModalNuevaCaja(): void
    {
        $conteo = Caja::count() + 1;
        $this->formCaja = [
            'nombre' => "Caja {$conteo} Barra",
            'codigo' => "CAJA-0{$conteo}",
            'tipo' => 'principal',
            'descripcion' => '',
        ];
        $this->modalNuevaCajaOpen = true;
    }

    public function guardarNuevaCaja(): void
    {
        $this->authorize('create', Caja::class);

        $this->validate([
            'formCaja.nombre' => 'required|string|max:60',
            'formCaja.codigo' => 'required|string|max:20|unique:cajas,codigo',
            'formCaja.tipo' => 'nullable|string|in:principal,barra,delivery',
            'formCaja.descripcion' => 'nullable|string|max:255',
        ]);

        $caja = app(CajaService::class)->crearCaja($this->formCaja, Auth::user());
        $this->cajaSeleccionadaId = $caja->id;
        $this->modalNuevaCajaOpen = false;

        $this->dispatch('notificacion', [
            'mensaje' => "Terminal {$caja->nombre} ({$caja->codigo}) creada exitosamente.",
            'tipo' => 'success',
        ]);
    }

    public function mount(): void
    {
        $this->conteoPrevioCobrosPendientes = $this->cobrosPendientes()->count();
        app(CajaService::class)->asegurarIndiceParcialTurnos();

        $userSucursalId = Auth::user()?->sucursal_id;
        $cajasQuery = Caja::query();
        if ($userSucursalId) {
            $cajasQuery->where('sucursal_id', $userSucursalId);
        }
        $caja = $cajasQuery->first() ?? Caja::first();
        if ($caja) {
            $this->cajaSeleccionadaId = $caja->id;
        }

        $turnosQuery = TurnoCaja::where('estado', 'abierto');
        if ($userSucursalId) {
            $turnosQuery->whereHas('caja', fn ($q) => $q->where('sucursal_id', $userSucursalId));
        }
        $turnoActivo = $turnosQuery->latest()->first();
        if ($turnoActivo) {
            $this->turnoId = $turnoActivo->id;
            $this->montoContado = 0.0;
        }
    }

    public function abrirModalApertura(): void
    {
        app(CajaService::class)->asegurarIndiceParcialTurnos();
        $this->mostrarModalApertura = true;
    }

    public function abrirTurno(): void
    {
        $this->authorize('abrir', TurnoCaja::class);

        $this->validate([
            'cajaSeleccionadaId' => 'required|exists:cajas,id',
            'fondoInicial' => 'required|numeric|min:0',
        ]);

        $sucursalId = Auth::user()?->sucursal_id;
        $caja = Caja::when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->findOrFail($this->cajaSeleccionadaId);
        $cajaService = app(CajaService::class);

        try {
            $turno = $cajaService->abrirTurno($caja, Auth::user(), $this->fondoInicial, $this->notasApertura);
            $this->turnoId = $turno->id;
            $this->mostrarModalApertura = false;
            $this->notasApertura = '';

            $this->dispatch('notificacion', [
                'mensaje' => "¡Turno #{$turno->id} abierto con éxito en {$caja->nombre}!",
                'tipo' => 'success',
            ]);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Database\QueryException && str_contains($e->getMessage(), 'turnos_caja_caja_id_abierto_unique')) {
                $this->addError('fondoInicial', "La caja {$caja->nombre} ya tiene un turno abierto actualmente.");
            } else {
                $this->addError('fondoInicial', $e->getMessage());
            }
        }
    }

    public function abrirModalMovimiento(string $tipo): void
    {
        $this->tipoMovimiento = in_array($tipo, ['ingreso', 'egreso', 'retiro']) ? $tipo : 'ingreso';
        $this->montoMovimiento = 0.0;
        $this->conceptoMovimiento = '';
        $this->comprobanteMovimiento = '';
        $this->autorizadoPor = '';
        $this->mostrarModalMovimiento = true;
    }

    public function abrirGavetaManual(): void
    {
        $this->authorize('guardarMovimiento', TurnoCaja::class);
        app(\App\Services\ImpresionService::class)->despacharAperturaGaveta(Auth::user());

        $this->dispatch('notificacion', [
            'mensaje' => 'Señal de apertura enviada a la gaveta de dinero.',
            'tipo' => 'success',
        ]);
    }

    private function obtenerTurnoValido(): TurnoCaja
    {
        $userSucursalId = Auth::user()?->sucursal_id;
        $query = TurnoCaja::where('id', $this->turnoId);

        // rol intencional, no permiso: alcance por sucursal (identidad de dominio, no catálogo)
        if ($userSucursalId && ! in_array(Auth::user()?->role?->slug, ['admin'], true)) {
            $query->whereHas('caja', fn ($q) => $q->where('sucursal_id', $userSucursalId));
        }

        return $query->firstOrFail();
    }

    public function registrarMovimiento(): void
    {
        $this->authorize('guardarMovimiento', TurnoCaja::class);

        $rules = [
            'tipoMovimiento' => 'required|in:ingreso,egreso,retiro',
            'montoMovimiento' => 'required|numeric|min:1',
            'conceptoMovimiento' => 'required|string|min:3',
        ];

        if (in_array($this->tipoMovimiento, ['egreso', 'retiro'])) {
            $rules['autorizadoPor'] = 'required|string|min:3';
        }

        $this->validate($rules);

        $usuarioActual = Auth::user();
        // rol intencional, no permiso: segregación cajero/supervisor al auto-autorizar (identidad de dominio, no catálogo)
        if (in_array($this->tipoMovimiento, ['egreso', 'retiro'])
            && strcasecmp(trim($this->autorizadoPor), trim($usuarioActual?->name ?? '')) === 0
            && ! in_array($usuarioActual?->role?->slug, ['admin', 'gerente'])) {
            $this->addError('autorizadoPor', 'Un cajero no puede auto-autorizarse un egreso o retiro. Requiere autorización de un superior.');

            return;
        }

        $turno = $this->obtenerTurnoValido();
        $cajaService = app(CajaService::class);

        try {
            $cajaService->registrarMovimiento(
                $turno,
                $this->tipoMovimiento,
                $this->montoMovimiento,
                $this->conceptoMovimiento,
                'efectivo',
                $this->comprobanteMovimiento,
                $this->autorizadoPor,
                $usuarioActual
            );

            $this->mostrarModalMovimiento = false;
            $this->dispatch('notificacion', [
                'mensaje' => "Movimiento de {$this->tipoMovimiento} registrado correctamente.",
                'tipo' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->addError('montoMovimiento', $e->getMessage());
        }
    }

    public function abrirModalCierre(): void
    {
        $turno = $this->obtenerTurnoValido();
        app(CajaService::class)->recalcularEsperado($turno);
        $this->montoContado = 0.0;
        $this->notasCierre = '';
        $this->mostrarModalCierre = true;
    }

    public function seleccionarTurno(int $id): void
    {
        // Cambia el contexto operativo (movimientos, arqueo, reportes) al turno elegido.
        // Misma regla de alcance que obtenerTurnoValido: sucursal propia salvo admin.
        $userSucursalId = Auth::user()?->sucursal_id;
        $query = TurnoCaja::where('id', $id)->where('estado', 'abierto');
        if ($userSucursalId && ! in_array(Auth::user()?->role?->slug, ['admin'], true)) {
            $query->whereHas('caja', fn ($q) => $q->where('sucursal_id', $userSucursalId));
        }
        $turno = $query->firstOrFail();

        $this->turnoId = $turno->id;
        $this->cajaSeleccionadaId = $turno->caja_id;
        $this->montoContado = 0.0;
        $this->notasCierre = '';
        $this->reporteZ = null;

        $this->dispatch('notificacion', [
            'mensaje' => "Operando ahora en {$turno->caja->nombre} (Turno #{$turno->id}).",
            'tipo' => 'info',
        ]);
    }

    public function ejecutarCierreTurno(): void
    {
        $this->authorize('cerrar', TurnoCaja::class);

        $this->validate([
            'montoContado' => 'required|numeric|min:0',
        ]);

        $turno = $this->obtenerTurnoValido();
        $cajaService = app(CajaService::class);

        try {
            $turno = $cajaService->cerrarTurno($turno, $this->montoContado, Auth::user(), $this->notasCierre);
            $this->reporteZ = $cajaService->generarReporteZ($turno);
            $this->mostrarModalCierre = false;
            $this->mostrarModalReporteZ = true;

            $this->dispatch('notificacion', [
                'mensaje' => "Turno #{$turno->id} cerrado correctamente. Arqueo completado.",
                'tipo' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->addError('montoContado', $e->getMessage());
        }
    }

    public function generarReporteX(): void
    {
        if (! $this->turnoId) {
            return;
        }
        $turno = $this->obtenerTurnoValido();
        $this->reporteZ = app(CajaService::class)->generarReporteZ($turno);
        $this->mostrarModalReporteZ = true;
    }

    public function cerrarModalReporteZ(): void
    {
        $this->mostrarModalReporteZ = false;
        $this->reporteZ = null;

        // Refresh active shift
        $turnoActivo = TurnoCaja::where('estado', 'abierto')->latest()->first();
        $this->turnoId = $turnoActivo?->id;
    }

    public function with(): array
    {
        $turnoActivo = $this->turnoId ? TurnoCaja::with([
            'caja.sucursal',
            'cajero',
            'movimientos.usuario',
            'pedidos' => fn ($q) => $q->orderByDesc('pagado_en')->orderByDesc('id'),
            'pedidos.mesa',
            'pedidos.usuario',
            'pedidos.mesero',
            'pedidos.items.producto',
        ])->withCount('pedidos')->find($this->turnoId) : null;

        $cajas = Caja::where('activa', true)->get();
        $ultimosTurnos = TurnoCaja::with(['caja', 'cajero'])->latest()->take(5)->get();

        // Selector de contexto operativo: turnos abiertos visibles para el usuario (alcance por sucursal).
        $userSucursalId = Auth::user()?->sucursal_id;
        $turnosAbiertosQuery = TurnoCaja::with(['caja', 'cajero'])->withCount('movimientos')->where('estado', 'abierto');
        if ($userSucursalId && ! in_array(Auth::user()?->role?->slug, ['admin'], true)) {
            $turnosAbiertosQuery->whereHas('caja', fn ($q) => $q->where('sucursal_id', $userSucursalId));
        }
        $turnosAbiertos = $turnosAbiertosQuery->latest()->get();

        // Gestión Individual y Comparativa de Cajas (F7-04)
        $cajaReporteService = app(\App\Services\CajaReporteService::class);
        $resumenPorCaja = null;
        $historialTurnosPorCaja = collect();
        $gastosPorCaja = null;
        $comparativaCajas = null;

        if ($this->vistaCaja === 'por_caja') {
            $cajaIdReporte = $this->cajaReporteSeleccionadaId ?? ($cajas->first()?->id ?? 1);
            $resumenPorCaja = $cajaReporteService->resumenPorCaja($cajaIdReporte, $this->filtroFechaDesde ?: null, $this->filtroFechaHasta ?: null);
            $historialTurnosPorCaja = $cajaReporteService->historialTurnos($cajaIdReporte);
            $gastosPorCaja = $cajaReporteService->gastosPorCaja($cajaIdReporte, $this->filtroFechaDesde ?: null, $this->filtroFechaHasta ?: null);
        } elseif ($this->vistaCaja === 'comparativo') {
            $comparativaCajas = $cajaReporteService->compararCajas(Auth::user()?->sucursal_id ?? 1, $this->filtroFechaDesde ?: null, $this->filtroFechaHasta ?: null);
        }

        $ticketSvc = app(\App\Services\ConfiguracionService::class);
        $ticketDefaults = $ticketSvc->valoresPorDefectoTicket80mm();
        $ticketConfig = array_merge($ticketDefaults, $ticketSvc->obtenerGrupo('ticket_80mm'));

        return [
            'turno' => $turnoActivo,
            'cajas' => $cajas,
            'todasLasCajas' => Caja::withCount('turnos')->orderBy('id')->get(),
            'ultimosTurnos' => $ultimosTurnos,
            'turnosAbiertos' => $turnosAbiertos,
            'resumenPorCaja' => $resumenPorCaja,
            'historialTurnosPorCaja' => $historialTurnosPorCaja,
            'gastosPorCaja' => $gastosPorCaja,
            'comparativaCajas' => $comparativaCajas,
            'ticketConfig' => $ticketConfig,
            'cobrosPendientes' => $this->cobrosPendientes(),
        ];
    }
}; ?>

<div 
    class="space-y-6 relative"
    wire:poll.4s="verificarNuevasSolicitudesCobro"
    x-data="{
        sonarCampana() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const now = ctx.currentTime;
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now);
                gain1.gain.setValueAtTime(0.3, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.6);

                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880, now + 0.15);
                gain2.gain.setValueAtTime(0.4, now + 0.15);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.15);
                osc2.stop(now + 1.2);
            } catch(e) {}
        }
    }"
    @sonar-campana-caja.window="sonarCampana()"
>
    <!-- NOTIFICACIÓN VISUAL FLOTANTE: SOLICITUD DE COBRO EN VIVO -->
    @if($notificacionCobroFlotante)
        <div class="fixed top-5 right-5 z-50 max-w-sm w-full animate-bounce shadow-2xl">
            <div class="p-4 rounded-3xl bg-gradient-to-r from-[#2a170f] via-[#361f14] to-[#2a170f] border-2 border-amber-500 shadow-[0_10px_35px_rgba(245,158,11,0.35)] flex items-start gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500 text-[#140e0b] flex items-center justify-center shrink-0 shadow-md">
                    <span class="material-symbols-outlined text-[24px]">notifications_active</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-400">¡Cobro Solicitado!</span>
                        <span class="text-[10px] text-[#a89086]">{{ $notificacionCobroFlotante['hora'] }}</span>
                    </div>
                    <h3 class="text-xs font-black text-white mt-0.5">
                        {{ $notificacionCobroFlotante['mesa'] }} · ${{ number_format($notificacionCobroFlotante['total'], 0, ',', '.') }}
                    </h3>
                    <p class="text-[11px] text-[#d6c4bc]">
                        Mesero: <span class="font-bold text-amber-300">{{ $notificacionCobroFlotante['mesero'] }}</span>
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <button 
                            type="button"
                            wire:click="abrirCobroPendiente({{ $notificacionCobroFlotante['pedido_id'] }})"
                            class="px-3 py-1 rounded-xl bg-gradient-to-r from-[#10b981] to-[#059669] text-white font-black text-xs hover:brightness-110 shadow cursor-pointer"
                        >
                            💳 Cobrar Ahora
                        </button>
                        <button 
                            type="button"
                            wire:click="descartarNotificacionCobro"
                            class="px-2.5 py-1 rounded-xl bg-[#1c130f] border border-[#3d2b22] text-[#a89086] hover:text-white font-bold text-xs cursor-pointer"
                        >
                            Descartar
                        </button>
                    </div>
                </div>
                <button wire:click="descartarNotificacionCobro" class="text-[#a89086] hover:text-white cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
            </div>
        </div>
    @endif

    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">payments</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Control de Caja y Operaciones del Turno
                </h1>
                <span class="rounded-full bg-secondary-container/50 px-2.5 py-0.5 text-[11px] font-bold text-on-secondary-container border border-secondary/30">
                    CAJ-01
                </span>
                @if($cobrosPendientes->count() > 0)
                    <span class="rounded-full bg-amber-500/15 px-2.5 py-0.5 text-[11px] font-black text-amber-500 border border-amber-500/40 animate-pulse">
                        Cobros Pendientes: {{ $cobrosPendientes->count() }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Sesiones independientes, arqueo ciego, control de efectivo y cierre fiscal Z
            </p>
        </div>
        <div class="flex items-center gap-2">
            @can('create', App\Models\Caja::class)
                <button
                    wire:click="abrirModalGestionTerminales"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest px-3.5 py-2 text-xs font-extrabold text-on-surface transition-all active:scale-95"
                >
                    <span class="material-symbols-outlined text-[16px] text-primary">devices</span>
                    <span>Gestionar Terminales</span>
                </button>
                <button
                    wire:click="abrirModalNuevaCaja"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-secondary px-3.5 py-2 text-xs font-extrabold text-on-secondary shadow-sm hover:bg-secondary-fixed-dim transition-all active:scale-95"
                >
                    <span class="material-symbols-outlined text-[16px]">add_box</span>
                    <span>+ Nueva Terminal</span>
                </button>
            @endcan
            <a
                href="{{ route('pos') }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest px-3.5 py-2 text-xs font-extrabold text-on-surface transition-all"
            >
                <span class="material-symbols-outlined text-[16px] text-primary">point_of_sale</span>
                <span>Ir al POS</span>
            </a>
        </div>
    </header>

    <!-- Fase 8.1 — Cobros Pendientes solicitados por meseros -->
    @if($cobrosPendientes->count() > 0)
        <div class="rounded-3xl border border-amber-500/30 bg-surface-container-lowest p-5 shadow-sm space-y-3 animate-fade-in">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500 text-[20px]">pending_actions</span>
                    Cobros Pendientes
                    <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-black text-amber-500 border border-amber-500/40">{{ $cobrosPendientes->count() }}</span>
                </h2>
                <p class="text-[11px] text-on-surface-variant font-medium">Solicitudes de salón en tiempo real</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                @foreach($cobrosPendientes as $pendiente)
                    <div class="rounded-2xl bg-surface-container-low border border-outline-variant/20 p-3.5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-black text-on-surface">{{ $pendiente->mesa ? 'Mesa '.$pendiente->mesa->numero : $pendiente->codigo }}</span>
                                <span class="text-[11px] text-on-surface-variant">{{ $pendiente->mesero?->name ?? $pendiente->usuario?->name ?? 'Mesero' }}</span>
                            </div>
                            <p class="text-[11px] text-on-surface-variant mt-0.5">
                                ${{ number_format((float) $pendiente->total, 0, ',', '.') }} · en espera {{ $pendiente->updated_at?->diffForHumans(null, true) ?? '' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="abrirCobroPendiente({{ $pendiente->id }})"
                            class="shrink-0 rounded-xl bg-secondary px-4 py-2.5 text-xs font-black text-white shadow-sm hover:bg-secondary-fixed-dim active:scale-95 transition cursor-pointer min-h-[44px]"
                        >
                            Procesar cobro
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($mostrarModalCobroPendiente && $cobroPendienteId)
        @php $pedidoPendiente = $cobrosPendientes->firstWhere('id', $cobroPendienteId); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fade-in">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-outline-variant/20 space-y-4">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <h3 class="text-sm font-black text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">point_of_sale</span>
                        Cobrar solicitud de salón
                    </h3>
                    <button type="button" wire:click="cerrarCobroPendiente" class="text-on-surface-variant hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                @if($pedidoPendiente)
                    <div class="rounded-2xl bg-surface-container-low p-3.5 border border-outline-variant/20 text-xs space-y-1">
                        <p class="font-black text-on-surface text-sm">{{ $pedidoPendiente->mesa ? 'Mesa '.$pedidoPendiente->mesa->numero : $pedidoPendiente->codigo }}</p>
                        <p class="text-on-surface-variant">Mesero: {{ $pedidoPendiente->mesero?->name ?? '—' }} · Total: ${{ number_format((float) $pedidoPendiente->total, 0, ',', '.') }}</p>
                    </div>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-on-surface">Método de pago</label>
                        <select wire:model="metodoPagoPendiente" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-xs text-on-surface focus:border-primary focus:ring-0 min-h-[44px]">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="mixto">Mixto</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-on-surface">Monto recibido</label>
                        <input type="number" min="0" step="100" wire:model="montoPagadoPendiente" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-xs text-on-surface focus:border-primary focus:ring-0 min-h-[44px]" />
                        @error('montoPagadoPendiente')
                            <p class="text-xs text-error font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface">Propina (opcional)</label>
                    <input type="number" min="0" step="100" wire:model="propinaPendiente" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-xs text-on-surface focus:border-primary focus:ring-0 min-h-[44px]" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" wire:click="cerrarCobroPendiente" class="rounded-xl px-4 py-2.5 font-bold text-on-surface-variant hover:bg-surface-container-high cursor-pointer min-h-[44px]">Cancelar</button>
                    <button type="button" wire:click="cobrarPendiente" class="rounded-xl bg-secondary px-5 py-2.5 font-black text-white shadow-sm hover:bg-secondary-fixed-dim cursor-pointer min-h-[44px]">Confirmar cobro</button>
                </div>
            </div>
        </div>
    @endif

    <!-- PESTAÑAS DE NAVEGACIÓN: OPERACIÓN EN VIVO / GESTIÓN POR CAJA / COMPARATIVO -->
    <div class="flex items-center gap-2 p-1.5 bg-surface-container rounded-2xl border border-surface-container-highest max-w-xl">
        <button
            type="button"
            wire:click="$set('vistaCaja', 'operacion')"
            class="flex-1 py-2 px-3 text-xs font-black rounded-xl transition-all flex items-center justify-center gap-1.5 {{ $vistaCaja === 'operacion' ? 'bg-surface-container-lowest text-primary shadow-sm font-extrabold border border-surface-container-highest' : 'text-on-surface-variant hover:text-on-surface' }}"
        >
            <span class="material-symbols-outlined text-[16px]">point_of_sale</span>
            <span>Turno Operativo</span>
        </button>
        <button
            type="button"
            wire:click="$set('vistaCaja', 'por_caja')"
            class="flex-1 py-2 px-3 text-xs font-black rounded-xl transition-all flex items-center justify-center gap-1.5 {{ $vistaCaja === 'por_caja' ? 'bg-surface-container-lowest text-primary shadow-sm font-extrabold border border-surface-container-highest' : 'text-on-surface-variant hover:text-on-surface' }}"
        >
            <span class="material-symbols-outlined text-[16px]">bar_chart</span>
            <span>Gestión por Caja</span>
        </button>
        <button
            type="button"
            wire:click="$set('vistaCaja', 'comparativo')"
            class="flex-1 py-2 px-3 text-xs font-black rounded-xl transition-all flex items-center justify-center gap-1.5 {{ $vistaCaja === 'comparativo' ? 'bg-surface-container-lowest text-primary shadow-sm font-extrabold border border-surface-container-highest' : 'text-on-surface-variant hover:text-on-surface' }}"
        >
            <span class="material-symbols-outlined text-[16px]">compare_arrows</span>
            <span>Comparativo</span>
        </button>
    </div>

    @if($vistaCaja === 'operacion')
    @if($turno && $turno->estado === 'abierto')
        <!-- SUB-HEADER CONTEXTUAL Y COMANDOS DE TURNO (Stitch CAJ-01 Aura Gastro Expressive OS) -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-extrabold text-on-surface tracking-tight">
                        {{ $turno->caja->nombre }}
                    </h2>
                    <div class="flex items-center gap-1.5 bg-secondary-container/40 border border-secondary/30 px-3 py-1 rounded-full">
                        <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                        <span class="text-xs text-on-secondary-container uppercase font-extrabold tracking-wider">
                            Turno #{{ $turno->id }} · ACTIVO
                        </span>
                    </div>
                    <span class="text-[10px] font-bold font-mono text-on-surface-variant bg-surface-container-low px-2 py-0.5 rounded-md border border-surface-container-high">
                        {{ $turno->caja->codigo }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-on-surface-variant">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-primary">badge</span>
                        <span><strong>Cajero:</strong> {{ $turno->cajero->name }}</span>
                    </div>
                    <span class="text-surface-container-highest hidden sm:inline">•</span>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-primary">schedule</span>
                        <span><strong>Apertura:</strong> {{ $turno->apertura_en->format('d/m/Y H:i') }} (hace {{ $turno->apertura_en->diffForHumans(null, true) }})</span>
                    </div>
                    <span class="text-surface-container-highest hidden sm:inline">•</span>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-secondary">encrypted</span>
                        <span class="text-secondary font-medium">Bóveda de Seguridad Conectada</span>
                    </div>
                </div>
            </div>

            <!-- BOTONES DE ACCIÓN RÁPIDA (Stitch CAJ-01) -->
            <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                <button 
                    wire:click="abrirModalMovimiento('ingreso')"
                    class="h-11 px-3.5 rounded-xl bg-secondary/15 hover:bg-secondary/25 text-secondary transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-secondary/30" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    <span>+ Registrar Ingreso</span>
                </button>
                <button 
                    wire:click="abrirModalMovimiento('egreso')"
                    class="h-11 px-3.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-surface-container-high" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px] text-error">price_change</span>
                    <span>Registrar Egreso</span>
                </button>
                <button 
                    wire:click="abrirModalMovimiento('retiro')"
                    class="h-11 px-3.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-surface-container-high" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px] text-tertiary">account_balance</span>
                    <span>Retiro a Banco</span>
                </button>
                <button 
                    wire:click="abrirGavetaManual"
                    class="h-11 px-3.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-surface-container-high" 
                    type="button"
                    title="Enviar comando ESC/POS para abrir cajón de dinero físicamente"
                >
                    <span class="material-symbols-outlined text-[18px] text-primary">meeting_room</span>
                    <span>Abrir Gaveta</span>
                </button>
                <button 
                    wire:click="generarReporteX"
                    class="h-11 px-3.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-xs font-extrabold border border-surface-container-high" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">receipt</span>
                    <span>Corte X Parcial</span>
                </button>
                <button 
                    wire:click="abrirModalCierre"
                    class="h-11 px-4 rounded-xl bg-primary text-on-primary hover:bg-primary-container transition-all flex items-center gap-1.5 shadow-md shadow-primary/20 active:scale-95 text-xs font-black" 
                    type="button"
                >
                    <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                    <span>Arquear y Cerrar Turno</span>
                </button>
            </div>
        </div>

        <!-- SELECTOR DE TURNO ACTIVO (múltiples cajas abiertas) -->
        @if($turnosAbiertos->count() > 1)
            <div class="bg-surface-container-lowest rounded-3xl px-5 py-3 border border-surface-container-highest shadow-sm flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3" role="tablist" aria-label="Seleccionar caja y turno activo">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-on-surface-variant shrink-0">
                    <span class="material-symbols-outlined text-[16px] text-primary">sync_alt</span>
                    Operando en:
                </span>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach($turnosAbiertos as $t)
                        <button
                            type="button"
                            role="tab"
                            aria-selected="{{ $t->id === $turno?->id ? 'true' : 'false' }}"
                            wire:click="seleccionarTurno({{ $t->id }})"
                            class="h-11 px-3.5 rounded-xl text-xs font-extrabold transition-all active:scale-95 flex items-center gap-1.5 border {{ $t->id === $turno?->id ? 'bg-primary text-on-primary border-primary shadow-md shadow-primary/20' : 'bg-surface-container hover:bg-surface-container-high text-on-surface border-surface-container-high' }}"
                        >
                            <span class="material-symbols-outlined text-[16px]">point_of_sale</span>
                            <span>{{ $t->caja->codigo }}</span>
                            <span class="opacity-70 font-bold">· {{ $t->cajero->name }} · {{ $t->apertura_en->format('H:i') }} · {{ $t->movimientos_count }} movs</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- TARJETAS DE MÉTRICAS / TOTALES EN VIVO (Stitch CAJ-01) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Fondo Inicial -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col justify-between relative overflow-hidden hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Fondo Inicial de Apertura</span>
                        <div class="font-mono text-2xl lg:text-3xl font-black text-on-surface mt-1">
                            ${{ number_format($turno->monto_inicial, 2) }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-surface-container flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[22px]">savings</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center justify-between text-xs text-on-surface-variant">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px] text-secondary">check_circle</span>
                        <span>Registrado a las {{ $turno->apertura_en->format('H:i') }}h</span>
                    </span>
                    <span class="text-[10px] font-bold bg-surface-container px-2 py-0.5 rounded text-on-surface">Base Fija</span>
                </div>
            </div>

            <!-- Ventas Totales del Turno -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col justify-between relative overflow-hidden hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ventas Totales del Turno</span>
                        <div class="font-mono text-2xl lg:text-3xl font-black text-primary mt-1">
                            ${{ number_format($turno->total_ventas, 2) }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-primary-fixed text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">point_of_sale</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container-high flex flex-col gap-1 text-[11px] text-on-surface-variant">
                    <div class="flex justify-between items-center font-bold">
                        <span>{{ $turno->pedidos_count ?? 0 }} comandas cobradas</span>
                        <span class="text-secondary">En vivo</span>
                    </div>
                    <div class="flex items-center gap-1 font-mono text-[10px]">
                        <span>Ef: <strong class="text-on-surface">${{ number_format($turno->total_ventas_efectivo, 0) }}</strong></span>
                        <span>•</span>
                        <span>Tarj: <strong class="text-on-surface">${{ number_format($turno->total_ventas_tarjeta, 0) }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Egresos y Pagos Menores -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col justify-between relative overflow-hidden hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">Egresos y Retiros</span>
                        <div class="font-mono text-2xl lg:text-3xl font-black text-error mt-1">
                            -${{ number_format((float)$turno->total_egresos + (float)$turno->total_retiros, 2) }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-error-container text-error flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">shopping_bag</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center justify-between text-xs text-on-surface-variant">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px] text-error">receipt_long</span>
                        <span>{{ $turno->movimientos->count() }} autorizados</span>
                    </span>
                    <span class="text-[10px] font-bold bg-error-container text-error px-2 py-0.5 rounded">Auditable</span>
                </div>
            </div>

            <!-- Efectivo Esperado en Gaveta -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-secondary/40 shadow-sm flex flex-col justify-between relative overflow-hidden ring-1 ring-secondary/20 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-secondary">Efectivo Esperado en Gaveta</span>
                            <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                        </div>
                        <div class="font-mono text-2xl lg:text-3xl font-black text-secondary mt-1">
                            ${{ number_format($turno->monto_esperado_efectivo, 2) }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-secondary-container text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">payments</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container-high flex items-center justify-between text-xs text-on-surface-variant">
                    <span class="text-[10px] font-mono">Fondo + Ef. Ventas + Ingresos - Egresos</span>
                    <span class="text-[10px] font-bold text-on-secondary-container bg-secondary-container/50 px-2 py-0.5 rounded">Cuadre Automático</span>
                </div>
            </div>
        </div>

        <!-- TICKETS COBRADOS DEL TURNO (ventas registradas en esta caja) -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-secondary">receipt_long</span>
                    <h3 class="text-sm font-extrabold text-on-surface">Tickets Cobrados del Turno · {{ $turno->caja->codigo }}</h3>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button 
                        type="button"
                        wire:click="previsualizarUltimoTicket"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary/10 hover:bg-primary/20 text-primary text-xs font-black border border-primary/20 transition-all cursor-pointer shadow-xs active:scale-95"
                        title="Previsualizar el último ticket generado en este turno"
                    >
                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                        <span>Previsualizar Último Ticket</span>
                    </button>
                    <span class="text-xs font-mono text-on-surface-variant bg-surface-container px-2.5 py-1 rounded-xl">
                        {{ $turno->pedidos->count() }} tickets · ${{ number_format($turno->pedidos->sum('total'), 2) }}
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-container-high text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                            <th class="py-3 px-3 rounded-l-xl">Hora</th>
                            <th class="py-3 px-3">Ticket</th>
                            <th class="py-3 px-3">Mesa / Cliente</th>
                            <th class="py-3 px-3">Método</th>
                            <th class="py-3 px-3">Cambio</th>
                            <th class="py-3 px-3 text-right">Total</th>
                            <th class="py-3 px-3 text-center rounded-r-xl">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high font-medium">
                        @forelse($turno->pedidos as $ticket)
                            <tr class="hover:bg-surface-container-low/60 transition-colors">
                                <td class="py-2.5 px-3 font-mono text-on-surface-variant">{{ $ticket->pagado_en?->format('H:i') ?? $ticket->created_at->format('H:i') }}</td>
                                <td class="py-2.5 px-3 font-mono font-bold text-on-surface">{{ $ticket->codigo ?? ('#'.$ticket->id) }}</td>
                                <td class="py-2.5 px-3 text-on-surface">{{ $ticket->mesa?->numero ? 'Mesa '.$ticket->mesa->numero : ($ticket->nombre_cliente ?? 'Mostrador') }}</td>
                                <td class="py-2.5 px-3">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase bg-surface-container-high text-on-surface-variant">{{ $ticket->metodo_pago ?? '—' }}</span>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-on-surface-variant">${{ number_format((float) $ticket->cambio, 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-black text-secondary">${{ number_format((float) $ticket->total, 2) }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <button 
                                        wire:click="abrirPrevisualizarTicket({{ $ticket->id }})"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-surface-container hover:bg-primary hover:text-white text-on-surface font-bold text-xs border border-surface-container-highest transition-all cursor-pointer shadow-xs active:scale-95"
                                        title="Ver ticket térmico generado y verificar datos"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">receipt_long</span>
                                        <span>Ver Ticket</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 px-3 text-center text-on-surface-variant text-xs font-medium">
                                    Aún no hay tickets cobrados en este turno.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MOVEMENTS & AUDIT LOG TABLE (Stitch CAJ-01) -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">history</span>
                    <h3 class="text-sm font-extrabold text-on-surface">Historial de Movimientos y Gastos del Turno</h3>
                </div>
                <span class="text-xs font-mono text-on-surface-variant">
                    {{ $turno->movimientos->count() }} transacciones de caja
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-container-high text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                            <th class="py-3 px-3 rounded-l-xl">Hora</th>
                            <th class="py-3 px-3">Tipo</th>
                            <th class="py-3 px-3">Concepto / Motivo</th>
                            <th class="py-3 px-3">Método</th>
                            <th class="py-3 px-3">Comprobante</th>
                            <th class="py-3 px-3">Autorizado Por</th>
                            <th class="py-3 px-3 text-right rounded-r-xl">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high font-medium">
                        @forelse($turno->movimientos as $mov)
                            @php
                                $tipoColor = match($mov->tipo) {
                                    'egreso' => 'text-error bg-error-container/60 border-error/30',
                                    'retiro' => 'text-tertiary bg-tertiary-container/30 border-tertiary/30',
                                    'ingreso' => 'text-secondary bg-secondary-container/60 border-secondary/30',
                                    default => 'text-on-surface-variant',
                                };
                            @endphp
                            <tr class="hover:bg-surface-container-low/50 transition-colors">
                                <td class="py-3 px-3 font-mono text-on-surface-variant">{{ $mov->created_at->format('H:i:s') }}</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded-full border text-[10px] font-extrabold uppercase {{ $tipoColor }}">
                                        {{ $mov->tipo }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-on-surface font-semibold">{{ $mov->concepto }}</td>
                                <td class="py-3 px-3 text-on-surface-variant capitalize">{{ $mov->metodo_pago }}</td>
                                <td class="py-3 px-3 font-mono text-on-surface-variant">{{ $mov->numero_comprobante ?? 'S/C' }}</td>
                                <td class="py-3 px-3 text-on-surface-variant">{{ $mov->autorizado_por ?? $mov->usuario->name }}</td>
                                <td class="py-3 px-3 text-right font-mono font-bold {{ $mov->tipo === 'ingreso' ? 'text-secondary' : 'text-error' }}">
                                    {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-on-surface-variant">
                                    No hay egresos o retiros registrados en este turno todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @else
        <!-- NO OPEN SHIFT STATE: Prompt to open a new shift -->
        <div class="rounded-3xl border-2 border-dashed border-surface-container-highest bg-surface-container-lowest p-12 text-center shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-primary-fixed text-primary flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-[36px]">point_of_sale</span>
            </div>
            <h2 class="text-xl font-extrabold text-on-surface">No hay ningún turno de caja abierto</h2>
            <p class="text-xs text-on-surface-variant max-w-md mx-auto mt-1.5 leading-relaxed">
                Para comenzar a recibir pagos en el POS, registrar comandas y gestionar cobros de mesas, es obligatorio realizar la apertura de turno con un fondo inicial en gaveta.
            </p>

            <button 
                wire:click="abrirModalApertura"
                class="mt-6 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container active:scale-95 transition-all"
            >
                <span class="material-symbols-outlined text-[18px]">lock_open</span>
                <span>Abrir Turno de Caja con Fondo Inicial</span>
            </button>
        </div>

        <!-- Recent Closed Shifts Summary -->
        <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm">
            <h3 class="text-sm font-extrabold text-on-surface mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">fact_check</span>
                <span>Últimos Turnos y Arqueos Finalizados</span>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-surface-container-high text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                            <th class="py-2.5 px-3 rounded-l-xl">Turno</th>
                            <th class="py-2.5 px-3">Caja</th>
                            <th class="py-2.5 px-3">Cajero</th>
                            <th class="py-2.5 px-3">Apertura</th>
                            <th class="py-2.5 px-3">Cierre</th>
                            <th class="py-2.5 px-3 text-right">Ventas Totales</th>
                            <th class="py-2.5 px-3 text-right rounded-r-xl">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high">
                        @forelse($ultimosTurnos as $t)
                            <tr class="hover:bg-surface-container-low/50 transition-colors">
                                <td class="py-2.5 px-3 font-mono font-bold text-on-surface">#{{ $t->id }}</td>
                                <td class="py-2.5 px-3 text-on-surface">{{ $t->caja->nombre }}</td>
                                <td class="py-2.5 px-3 text-on-surface-variant">{{ $t->cajero->name }}</td>
                                <td class="py-2.5 px-3 font-mono text-on-surface-variant">{{ $t->apertura_en->format('d/m/y H:i') }}</td>
                                <td class="py-2.5 px-3 font-mono text-on-surface-variant">{{ $t->cierre_en?->format('d/m/y H:i') ?? 'Abierto' }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-primary">${{ number_format($t->total_ventas, 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold {{ $t->diferencia >= 0 ? 'text-secondary' : 'text-error' }}">
                                    {{ $t->diferencia >= 0 ? '+' : '' }}${{ number_format($t->diferencia, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-on-surface-variant">No hay historial previo de turnos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    @elseif($vistaCaja === 'por_caja')
        <!-- PANEL DE CONTROL Y REPORTES POR CAJA INDIVIDUAL (F7-04) -->
        <div class="space-y-6">
            <!-- Barra superior de selección y filtros de caja -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20 shrink-0">
                        <span class="material-symbols-outlined text-[22px]">point_of_sale</span>
                    </div>
                    <div>
                        <label class="text-[10px] font-extrabold uppercase text-on-surface-variant block tracking-wider">Caja Seleccionada</label>
                        <select wire:model.live="cajaReporteSeleccionadaId" class="mt-0.5 h-10 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                            @foreach($todasLasCajas as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }}) · {{ ucfirst($c->tipo ?? 'principal') }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                    <div>
                        <label class="text-[10px] font-extrabold uppercase text-on-surface-variant block tracking-wider">Desde</label>
                        <input type="date" wire:model.live="filtroFechaDesde" class="h-10 rounded-xl border border-surface-container-high bg-surface-container-low px-2.5 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                    </div>
                    <div>
                        <label class="text-[10px] font-extrabold uppercase text-on-surface-variant block tracking-wider">Hasta</label>
                        <input type="date" wire:model.live="filtroFechaHasta" class="h-10 rounded-xl border border-surface-container-high bg-surface-container-low px-2.5 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                    </div>
                    @if($filtroFechaDesde || $filtroFechaHasta)
                        <button type="button" wire:click="$set('filtroFechaDesde', ''); $set('filtroFechaHasta', '')" class="mt-4 px-3 py-2 text-xs font-bold rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant transition-all">
                            Limpiar Filtros
                        </button>
                    @endif
                </div>
            </div>

            @if($resumenPorCaja)
                <!-- Tarjetas KPI de la Caja Seleccionada -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container-highest shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-extrabold text-on-surface-variant uppercase">Ventas Totales</span>
                            <span class="p-2 rounded-xl bg-primary/10 text-primary material-symbols-outlined text-[18px]">payments</span>
                        </div>
                        <p class="text-2xl font-black font-mono text-primary">${{ number_format($resumenPorCaja['total_ventas'], 0, ',', '.') }}</p>
                        <div class="mt-2 text-[11px] text-on-surface-variant flex flex-col gap-0.5 font-mono">
                            <span>Efectivo: ${{ number_format($resumenPorCaja['ventas_efectivo'], 0, ',', '.') }}</span>
                            <span>Tarjetas: ${{ number_format($resumenPorCaja['ventas_tarjeta'], 0, ',', '.') }}</span>
                            <span>Transf: ${{ number_format($resumenPorCaja['ventas_transferencia'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container-highest shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-extrabold text-on-surface-variant uppercase">Total Egresos / Gastos</span>
                            <span class="p-2 rounded-xl bg-error/10 text-error material-symbols-outlined text-[18px]">trending_down</span>
                        </div>
                        <p class="text-2xl font-black font-mono text-error">${{ number_format($resumenPorCaja['total_gastos'], 0, ',', '.') }}</p>
                        <p class="mt-2 text-[11px] text-on-surface-variant">Gastos operativos pagados desde esta terminal</p>
                    </div>

                    <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container-highest shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-extrabold text-on-surface-variant uppercase">Balance Neto Caja</span>
                            <span class="p-2 rounded-xl bg-secondary/10 text-secondary material-symbols-outlined text-[18px]">account_balance_wallet</span>
                        </div>
                        <p class="text-2xl font-black font-mono {{ $resumenPorCaja['neto'] >= 0 ? 'text-secondary' : 'text-error' }}">
                            {{ $resumenPorCaja['neto'] >= 0 ? '+' : '' }}${{ number_format($resumenPorCaja['neto'], 0, ',', '.') }}
                        </p>
                        <p class="mt-2 text-[11px] text-on-surface-variant">Ingresos netos menos egresos</p>
                    </div>

                    <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container-highest shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-extrabold text-on-surface-variant uppercase">Estado Operativo</span>
                            <span class="p-2 rounded-xl bg-surface-container text-on-surface material-symbols-outlined text-[18px]">info</span>
                        </div>
                        @if($resumenPorCaja['tiene_turno_activo'])
                            <div class="flex items-center gap-1.5 text-secondary font-black text-sm">
                                <span class="w-2.5 h-2.5 rounded-full bg-secondary animate-pulse"></span>
                                <span>TURNO EN CURSO</span>
                            </div>
                            <p class="text-[11px] text-on-surface-variant mt-1">Cajero: <strong>{{ $resumenPorCaja['turno_activo']['cajero'] }}</strong></p>
                        @else
                            <div class="flex items-center gap-1.5 text-on-surface-variant font-bold text-sm">
                                <span class="w-2.5 h-2.5 rounded-full bg-surface-container-highest"></span>
                                <span>SIN TURNO ACTIVO</span>
                            </div>
                            <p class="text-[11px] text-on-surface-variant mt-1">{{ $resumenPorCaja['turnos_count'] }} turnos históricos</p>
                        @endif
                    </div>
                </div>

                <!-- Historial de Turnos de esta Caja -->
                <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">history</span>
                            <h3 class="text-sm font-extrabold text-on-surface">Historial de Turnos de {{ $resumenPorCaja['caja']['nombre'] }}</h3>
                        </div>
                        <span class="text-xs text-on-surface-variant font-mono">{{ $historialTurnosPorCaja->count() }} turnos</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-surface-container-high text-on-surface-variant uppercase text-[10px] font-black">
                                    <th class="py-2.5 px-3">Turno</th>
                                    <th class="py-2.5 px-3">Cajero</th>
                                    <th class="py-2.5 px-3">Apertura</th>
                                    <th class="py-2.5 px-3">Cierre</th>
                                    <th class="py-2.5 px-3 text-right">Fondo Base</th>
                                    <th class="py-2.5 px-3 text-right">Ventas</th>
                                    <th class="py-2.5 px-3 text-right">Egresos</th>
                                    <th class="py-2.5 px-3 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-container-high/60">
                                @forelse($historialTurnosPorCaja as $t)
                                    <tr class="hover:bg-surface-container/40 transition-colors">
                                        <td class="py-3 px-3 font-mono font-bold text-primary">#{{ $t->id }}</td>
                                        <td class="py-3 px-3 font-medium text-on-surface">{{ $t->user?->name ?? 'N/A' }}</td>
                                        <td class="py-3 px-3 text-on-surface-variant font-mono">{{ $t->apertura_en?->format('d/m/Y H:i') }}</td>
                                        <td class="py-3 px-3 text-on-surface-variant font-mono">{{ $t->cierre_en?->format('d/m/Y H:i') ?? 'En curso...' }}</td>
                                        <td class="py-3 px-3 text-right font-mono">${{ number_format($t->monto_inicial, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-mono font-bold text-on-surface">${{ number_format((float)$t->total_ventas_efectivo + (float)$t->total_ventas_tarjeta + (float)$t->total_ventas_transferencia, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-mono text-error">-${{ number_format((float)$t->total_egresos, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-center">
                                            @if($t->estado === 'abierto')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-secondary-container/50 text-on-secondary-container border border-secondary/30">Abierto</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-surface-container-high text-on-surface-variant">Cerrado</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-6 text-on-surface-variant">No se encontraron turnos registrados para esta caja.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($gastosPorCaja && $gastosPorCaja['conteo'] > 0)
                    <!-- Desglose de Gastos de esta Caja -->
                    <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-error text-[20px]">money_off</span>
                                <h3 class="text-sm font-extrabold text-on-surface">Gastos Registrados en {{ $resumenPorCaja['caja']['nombre'] }}</h3>
                            </div>
                            <span class="text-xs font-mono font-bold text-error">Total: ${{ number_format($gastosPorCaja['total_gastos'], 0, ',', '.') }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-surface-container-high text-on-surface-variant uppercase text-[10px] font-black">
                                        <th class="py-2.5 px-3">Fecha</th>
                                        <th class="py-2.5 px-3">Concepto</th>
                                        <th class="py-2.5 px-3">Categoría</th>
                                        <th class="py-2.5 px-3">Autorizado por</th>
                                        <th class="py-2.5 px-3 text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-container-high/60">
                                    @foreach($gastosPorCaja['movimientos'] as $g)
                                        <tr class="hover:bg-surface-container/40 transition-colors">
                                            <td class="py-3 px-3 font-mono text-on-surface-variant">{{ \Illuminate\Support\Carbon::parse($g['fecha'])->format('d/m/Y H:i') }}</td>
                                            <td class="py-3 px-3 font-bold text-on-surface">{{ $g['concepto'] }}</td>
                                            <td class="py-3 px-3 text-on-surface-variant">{{ $g['categoria'] ?? 'Operativo' }}</td>
                                            <td class="py-3 px-3 text-on-surface-variant">{{ $g['autorizado_por'] ?? 'N/A' }}</td>
                                            <td class="py-3 px-3 text-right font-mono font-bold text-error">-${{ number_format($g['monto'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @elseif($vistaCaja === 'comparativo')
        <!-- PANEL COMPARATIVO ENTRE TODAS LAS CAJAS DE LA SEDE (F7-04) -->
        <div class="space-y-6">
            @if($comparativaCajas)
                <!-- Gran Total de la Sede -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container-highest shadow-sm">
                        <span class="text-xs font-extrabold text-on-surface-variant uppercase">Facturación Total Sede</span>
                        <p class="text-2xl font-black font-mono text-primary mt-2">${{ number_format($comparativaCajas['gran_total_ventas'], 0, ',', '.') }}</p>
                        <p class="text-[11px] text-on-surface-variant mt-1">{{ $comparativaCajas['total_cajas'] }} terminales en la sucursal</p>
                    </div>

                    <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container-highest shadow-sm">
                        <span class="text-xs font-extrabold text-on-surface-variant uppercase">Gastos Totales Registrados</span>
                        <p class="text-2xl font-black font-mono text-error mt-2">${{ number_format($comparativaCajas['gran_total_gastos'], 0, ',', '.') }}</p>
                        <p class="text-[11px] text-on-surface-variant mt-1">Egresos operativos consolidados</p>
                    </div>

                    <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container-highest shadow-sm">
                        <span class="text-xs font-extrabold text-on-surface-variant uppercase">Balance Neto de Cajas</span>
                        <p class="text-2xl font-black font-mono {{ $comparativaCajas['gran_total_neto'] >= 0 ? 'text-secondary' : 'text-error' }} mt-2">
                            {{ $comparativaCajas['gran_total_neto'] >= 0 ? '+' : '' }}${{ number_format($comparativaCajas['gran_total_neto'], 0, ',', '.') }}
                        </p>
                        <p class="text-[11px] text-on-surface-variant mt-1">Margen neto operativo en cajas</p>
                    </div>
                </div>

                <!-- Tabla Comparativa Caja por Caja -->
                <div class="bg-surface-container-lowest rounded-3xl p-5 border border-surface-container-highest shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">compare_arrows</span>
                            <h3 class="text-sm font-extrabold text-on-surface">Comparativa Financiera por Terminal de Cobro</h3>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-surface-container-high text-on-surface-variant uppercase text-[10px] font-black">
                                    <th class="py-2.5 px-3">Caja / Terminal</th>
                                    <th class="py-2.5 px-3">Tipo</th>
                                    <th class="py-2.5 px-3 text-center">Turnos</th>
                                    <th class="py-2.5 px-3 text-right">Efectivo</th>
                                    <th class="py-2.5 px-3 text-right">Tarjeta</th>
                                    <th class="py-2.5 px-3 text-right">Transferencia</th>
                                    <th class="py-2.5 px-3 text-right">Total Ventas</th>
                                    <th class="py-2.5 px-3 text-right">Egresos</th>
                                    <th class="py-2.5 px-3 text-right">Neto</th>
                                    <th class="py-2.5 px-3 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-container-high/60">
                                @foreach($comparativaCajas['cajas'] as $cItem)
                                    <tr class="hover:bg-surface-container/40 transition-colors">
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-on-surface">{{ $cItem['caja']['nombre'] }}</div>
                                            <div class="text-[10px] font-mono text-on-surface-variant">{{ $cItem['caja']['codigo'] }}</div>
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-surface-container text-on-surface-variant border border-surface-container-high">
                                                {{ $cItem['caja']['tipo'] }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-center font-mono">{{ $cItem['turnos_count'] }}</td>
                                        <td class="py-3 px-3 text-right font-mono">${{ number_format($cItem['ventas_efectivo'], 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-mono">${{ number_format($cItem['ventas_tarjeta'], 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-mono">${{ number_format($cItem['ventas_transferencia'], 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-mono font-black text-primary">${{ number_format($cItem['total_ventas'], 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-mono text-error">-${{ number_format($cItem['total_gastos'], 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-mono font-bold {{ $cItem['neto'] >= 0 ? 'text-secondary' : 'text-error' }}">
                                            ${{ number_format($cItem['neto'], 0, ',', '.') }}
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            @if($cItem['tiene_turno_activo'])
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-secondary-container/50 text-on-secondary-container">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Activo
                                                </span>
                                            @else
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium bg-surface-container text-on-surface-variant">Cerrada</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- MODAL: APERTURA DE CAJA -->
    @if($mostrarModalApertura)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">lock_open</span>
                        </div>
                        <h3 class="text-base font-extrabold text-on-surface">Apertura de Turno de Caja</h3>
                    </div>
                    <button wire:click="$set('mostrarModalApertura', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Seleccionar Caja Terminal:</label>
                        <select 
                            wire:model="cajaSeleccionadaId" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                        >
                            @foreach($cajas as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Fondo Inicial de Efectivo en Gaveta:</label>
                        <input 
                            type="text" 
                            inputmode="decimal" 
                            data-miles data-decimales="0"
                            wire:model="fondoInicial" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                        />
                        @error('fondoInicial') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Notas de Apertura (Opcional):</label>
                        <textarea 
                            wire:model="notasApertura" 
                            rows="2"
                            placeholder="Ej: Base de cambio entregada por el gerente de turno..."
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2.5 text-xs text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary focus:ring-0"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button 
                        wire:click="$set('mostrarModalApertura', false)" 
                        wire:loading.attr="disabled"
                        wire:target="abrirTurno"
                        class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface disabled:opacity-50"
                    >
                        Cancelar
                    </button>
                    <button 
                        wire:click="abrirTurno" 
                        wire:loading.attr="disabled"
                        wire:target="abrirTurno"
                        class="rounded-xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 transition-all"
                    >
                        <span wire:loading.remove wire:target="abrirTurno">✓ Confirmar Apertura</span>
                        <span wire:loading wire:target="abrirTurno" class="inline-flex items-center gap-1.5">
                            <span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-solid border-current border-r-transparent"></span>
                            <span>Abriendo...</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: REGISTRAR INGRESO / EGRESO / RETIRO -->
    @if($mostrarModalMovimiento)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        @if($tipoMovimiento === 'ingreso')
                            <div class="w-8 h-8 rounded-lg bg-secondary-container text-secondary flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">add_circle</span>
                            </div>
                            <h3 class="text-base font-extrabold text-on-surface">Registrar Ingreso de Efectivo</h3>
                        @elseif($tipoMovimiento === 'retiro')
                            <div class="w-8 h-8 rounded-lg bg-tertiary-container text-tertiary flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">account_balance</span>
                            </div>
                            <h3 class="text-base font-extrabold text-on-surface">Registrar Retiro a Banco</h3>
                        @else
                            <div class="w-8 h-8 rounded-lg bg-error-container text-error flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">price_change</span>
                            </div>
                            <h3 class="text-base font-extrabold text-on-surface">Registrar Egreso de Caja</h3>
                        @endif
                    </div>
                    <button wire:click="$set('mostrarModalMovimiento', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Monto del Movimiento:</label>
                        <input 
                            type="text" 
                            inputmode="decimal" 
                            data-miles data-decimales="2"
                            wire:model="montoMovimiento" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
                            placeholder="0.00"
                        />
                        @error('montoMovimiento') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Concepto / Motivo:</label>
                        <input 
                            type="text" 
                            wire:model="conceptoMovimiento" 
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0"
                            placeholder="{{ $tipoMovimiento === 'ingreso' ? 'Ej: Inyección de cambio / sencillo, aporte a caja...' : 'Ej: Compra de hielo de urgencia, insumos...' }}"
                        />
                        @error('conceptoMovimiento') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">N° Recibo / Comprobante:</label>
                            <input 
                                type="text" 
                                wire:model="comprobanteMovimiento" 
                                class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0 font-mono"
                                placeholder="{{ $tipoMovimiento === 'ingreso' ? 'REC-001 (Opc.)' : 'FAC-1234' }}"
                            />
                        </div>
                        <div>
                            @if($tipoMovimiento === 'ingreso')
                                <label class="text-xs font-bold text-on-surface-variant">Entregado por (Opcional):</label>
                                <input 
                                    type="text" 
                                    wire:model="autorizadoPor" 
                                    class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0"
                                    placeholder="Nombre de quien aporta"
                                />
                            @else
                                <label class="text-xs font-bold text-on-surface-variant">Autorizado por:</label>
                                <input 
                                    type="text" 
                                    wire:model="autorizadoPor" 
                                    class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0"
                                    placeholder="Superior que autoriza"
                                />
                                @error('autorizadoPor') <span class="text-[11px] text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button 
                        wire:click="$set('mostrarModalMovimiento', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cancelar
                    </button>
                    @if($tipoMovimiento === 'ingreso')
                        <button 
                            wire:click="registrarMovimiento" 
                            wire:loading.attr="disabled"
                            wire:target="registrarMovimiento"
                            class="rounded-xl bg-secondary py-3 text-xs font-black text-on-secondary shadow-md hover:bg-secondary-fixed-dim disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="registrarMovimiento">✓ Registrar Ingreso</span>
                            <span wire:loading wire:target="registrarMovimiento">Registrando…</span>
                        </button>
                    @else
                        <button 
                            wire:click="registrarMovimiento" 
                            wire:loading.attr="disabled"
                            wire:target="registrarMovimiento"
                            class="rounded-xl bg-error py-3 text-xs font-black text-on-error shadow-md hover:opacity-90 disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="registrarMovimiento">✓ Registrar Salida</span>
                            <span wire:loading wire:target="registrarMovimiento">Registrando…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: ARQUEO CIEGO Y CIERRE FISCAL Z (Stitch CAJ-04 / CAJ-05) -->
    @if($mostrarModalCierre && $turno)
        @php
            $diferenciaActual = (float)$montoContado - (float)$turno->monto_esperado_efectivo;
            $esCuadrada = abs($diferenciaActual) < 0.01;
            $esSobrante = $diferenciaActual > 0.01;
            $esFaltante = $diferenciaActual < -0.01;
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">lock_reset</span>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-on-surface">Arqueo Ciego y Cierre de Turno</h3>
                            <p class="text-[11px] text-on-surface-variant flex items-center gap-1.5">
                                <span>Turno #{{ $turno->id }} · {{ $turno->caja->nombre }}</span>
                                <span class="rounded-md bg-primary/10 border border-primary/25 px-1.5 py-px font-mono font-black text-primary">{{ $turno->caja->codigo }}</span>
                            </p>
                        </div>
                    </div>
                    <button wire:click="$set('mostrarModalCierre', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <!-- Summary banner -->
                    <div class="grid grid-cols-2 gap-2 bg-surface-container-low p-3 rounded-2xl border border-surface-container-high text-xs">
                        <div>
                            <span class="text-on-surface-variant text-[10px] uppercase font-bold">Fondo Inicial:</span>
                            <p class="font-mono font-bold text-on-surface">${{ number_format($turno->monto_inicial, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[10px] uppercase font-bold">Ventas Efectivo:</span>
                            <p class="font-mono font-bold text-primary">+${{ number_format($turno->total_ventas_efectivo, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[10px] uppercase font-bold">Egresos / Retiros:</span>
                            <p class="font-mono font-bold text-error">-${{ number_format((float)$turno->total_egresos + (float)$turno->total_retiros, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[10px] uppercase font-bold">Efectivo Teórico:</span>
                            <p class="font-mono font-black text-secondary">${{ number_format($turno->monto_esperado_efectivo, 2) }}</p>
                        </div>
                    </div>

                    <!-- Blind Cash Input -->
                    <div>
                        <label class="text-xs font-extrabold text-on-surface flex items-center justify-between">
                            <span>Efectivo Físico Contado en Gaveta:</span>
                            <span class="text-[10px] text-primary font-mono">Conteo Real</span>
                        </label>
                        <input 
                            type="text" 
                            inputmode="decimal" 
                            data-miles data-decimales="2"
                            wire:model.live="montoContado" 
                            class="mt-1 w-full rounded-xl border-2 border-surface-container-high bg-surface-container-low p-3.5 font-mono text-2xl font-black text-on-surface focus:border-primary focus:ring-0"
                        />
                    </div>

                    <!-- Discrepancy Beacon Badge -->
                    <div class="p-3 rounded-2xl border flex items-center justify-between {{ $esCuadrada ? 'bg-secondary-container/40 border-secondary/30 text-secondary' : ($esSobrante ? 'bg-primary-fixed/50 border-primary/30 text-primary' : 'bg-error-container/40 border-error/30 text-error') }}">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[22px]">
                                {{ $esCuadrada ? 'check_circle' : ($esSobrante ? 'warning' : 'error') }}
                            </span>
                            <div class="flex flex-col">
                                <span class="text-xs font-extrabold uppercase tracking-wider">
                                    {{ $esCuadrada ? 'Caja Cuadrada Exacta' : ($esSobrante ? 'Sobrante en Caja (+)' : 'Faltante en Caja (-)') }}
                                </span>
                                <span class="text-[10px] opacity-80">
                                    {{ $esCuadrada ? 'El dinero físico coincide al 100% con el sistema.' : 'Se registrará asiento contable de ajuste.' }}
                                </span>
                            </div>
                        </div>
                        <div class="font-mono text-lg font-black">
                            {{ $diferenciaActual >= 0 ? '+' : '' }}${{ number_format($diferenciaActual, 2) }}
                        </div>
                    </div>

                    <!-- Closing notes -->
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Observaciones de Cierre (Justificación si hay descuadre):</label>
                        <textarea 
                            wire:model="notasCierre" 
                            rows="2"
                            placeholder="Comentario sobre el turno, detalle del recuento o justificación..."
                            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0 placeholder:text-on-surface-variant/50"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button 
                        wire:click="$set('mostrarModalCierre', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cancelar
                    </button>
                    <button 
                        wire:click="ejecutarCierreTurno" 
                        wire:loading.attr="disabled"
                        wire:target="ejecutarCierreTurno"
                        @disabled($turno->estado !== 'abierto')
                        class="rounded-xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary-container disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="ejecutarCierreTurno">✓ Confirmar y Emitir Reporte Z</span>
                        <span wire:loading wire:target="ejecutarCierreTurno">Cerrando turno…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- THERMAL REPORTE FISCAL Z MODAL (80mm Simulation) -->
    @if($mostrarModalReporteZ && $reporteZ)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-sm rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest font-mono text-xs">
                <!-- Header -->
                <div class="text-center border-b border-dashed border-surface-container-high pb-4">
                    <p class="text-base font-black tracking-tight text-primary">🍽️ RESTOMASTER 🍽️</p>
                    <p class="text-[11px] font-bold text-on-surface">CORTE DE CAJA — REPORTE FISCAL Z</p>
                    <p class="text-[10px] text-on-surface-variant">{{ $reporteZ['sucursal'] }} • {{ $reporteZ['caja_nombre'] }}</p>
                    <p class="text-[9px] text-on-surface-variant/60">NIT: 901.884.200-1 · Res. DIAN 18764022</p>
                </div>

                <!-- Shift context info -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span>TURNO:</span>
                        <span class="font-bold text-primary">#{{ $reporteZ['turno_id'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>CAJERO:</span>
                        <span>{{ $reporteZ['cajero'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>APERTURA:</span>
                        <span>{{ $reporteZ['apertura'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>CIERRE:</span>
                        <span>{{ $reporteZ['cierre'] }}</span>
                    </div>
                </div>

                <!-- Sales breakdown -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span>FONDO INICIAL:</span>
                        <span class="font-bold">${{ number_format($reporteZ['fondo_inicial'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>TRANSACCIONES POS:</span>
                        <span>{{ $reporteZ['total_transacciones'] }} pedidos</span>
                    </div>
                    <div class="flex justify-between font-bold text-on-surface pt-1 border-t border-dashed border-surface-container-high">
                        <span>VENTAS TOTALES:</span>
                        <span class="text-primary">${{ number_format($reporteZ['total_ventas'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pl-2">
                        <span>• Efectivo:</span>
                        <span>${{ number_format($reporteZ['desglose_pagos']['efectivo'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pl-2">
                        <span>• Tarjeta:</span>
                        <span>${{ number_format($reporteZ['desglose_pagos']['tarjeta'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant pl-2">
                        <span>• Transferencia/Otros:</span>
                        <span>${{ number_format($reporteZ['desglose_pagos']['transferencia'], 2) }}</span>
                    </div>
                </div>

                <!-- Expenses and Cash Count -->
                <div class="py-3 border-b border-dashed border-surface-container-high space-y-1 text-[11px]">
                    <div class="flex justify-between text-error">
                        <span>EGRESOS Y RETIROS:</span>
                        <span>-${{ number_format($reporteZ['total_egresos'] + $reporteZ['total_retiros'], 2) }}</span>
                    </div>
                    <div class="flex justify-between pt-1">
                        <span>EFECTIVO ESPERADO:</span>
                        <span class="font-bold text-secondary">${{ number_format($reporteZ['monto_esperado'], 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-on-surface">
                        <span>EFECTIVO CONTADO:</span>
                        <span>${{ number_format($reporteZ['monto_real'], 2) }}</span>
                    </div>
                    <div class="flex justify-between font-black pt-1 border-t border-dashed border-surface-container-high {{ $reporteZ['diferencia'] >= 0 ? 'text-secondary' : 'text-error' }}">
                        <span>DIFERENCIA ({{ $reporteZ['estado_cuadre'] }}):</span>
                        <span>{{ $reporteZ['diferencia'] >= 0 ? '+' : '' }}${{ number_format($reporteZ['diferencia'], 2) }}</span>
                    </div>
                </div>

                <!-- Fiscal Footer -->
                <div class="pt-3 text-center text-[9px] text-on-surface-variant space-y-1">
                    <p class="font-bold text-on-surface">CIERRE AUDITABLE Y CONCILIADO</p>
                    <p>Transmitido automáticamente a contabilidad interna</p>
                </div>

                <!-- Action buttons -->
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button 
                        onclick="window.print()" 
                        class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-bold text-on-surface hover:bg-surface-container-high"
                    >
                        🖨️ Imprimir
                    </button>
                    <button 
                        wire:click="cerrarModalReporteZ" 
                        class="rounded-xl bg-primary py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container"
                    >
                        ✓ Entendido
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: PREVISUALIZACIÓN Y AUDITORÍA RÁPIDA DE TICKET FISCAL GENERADO -->
    @if($modalPrevisualizarTicket && $pedidoTicketSeleccionado)
        <div 
            x-data 
            @keydown.escape.window="$wire.cerrarModalTicket()" 
            role="dialog" 
            aria-modal="true" 
            aria-labelledby="modal-ticket-preview-title" 
            class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 overflow-y-auto animate-fade-in"
            wire:click.self="cerrarModalTicket"
        >
            <div class="w-full max-w-lg rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-surface-container-highest max-h-[92vh] flex flex-col">
                
                <!-- CABECERA DEL MODAL -->
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3 mb-3 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center border border-secondary/20">
                            <span class="material-symbols-outlined text-[20px]">verified</span>
                        </div>
                        <div>
                            <h3 id="modal-ticket-preview-title" class="text-sm font-black text-on-surface flex items-center gap-1.5">
                                <span>Ticket Fiscal POS #{{ $pedidoTicketSeleccionado->codigo ?? $pedidoTicketSeleccionado->id }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                                    Generado en BD
                                </span>
                            </h3>
                            <p class="text-[11px] text-on-surface-variant font-mono">
                                ID Transacción: #{{ $pedidoTicketSeleccionado->id }} · {{ $pedidoTicketSeleccionado->pagado_en?->format('d/m/Y H:i:s') ?? $pedidoTicketSeleccionado->created_at->format('d/m/Y H:i:s') }}
                            </p>
                        </div>
                    </div>

                    <button 
                        type="button" 
                        wire:click="cerrarModalTicket" 
                        aria-label="Cerrar previsualización de ticket"
                        class="min-w-[36px] min-h-[36px] flex items-center justify-center rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <!-- SELECTOR DE VISTA: VISUAL vs ESC/POS IMPRESORA -->
                <div class="flex items-center justify-between mb-3 bg-surface-container-low p-1 rounded-2xl border border-surface-container-highest shrink-0">
                    <span class="text-[11px] font-extrabold text-on-surface-variant px-2">Formato de Ticket:</span>
                    <div class="flex items-center gap-1">
                        <button 
                            type="button" 
                            wire:click="$set('modoVistaTicket', 'visual')"
                            class="px-3 py-1 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $modoVistaTicket === 'visual' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}"
                        >
                            <span class="inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">receipt</span>
                                <span>Térmico 80mm</span>
                            </span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="$set('modoVistaTicket', 'escpos')"
                            class="px-3 py-1 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $modoVistaTicket === 'escpos' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}"
                        >
                            <span class="inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">terminal</span>
                                <span>Texto ESC/POS</span>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- CUERPO CON SCROLL -->
                <div class="overflow-y-auto flex-1 pr-1 space-y-3 font-mono text-xs">
                    @if($modoVistaTicket === 'visual')
                        <div class="rounded-2xl border border-dashed border-surface-container-high bg-surface-container-low/40 p-4 space-y-3 text-on-surface">
                            
                            <!-- ENCABEZADO FISCAL -->
                            <div class="text-center border-b border-dashed border-surface-container-high pb-3 space-y-0.5">
                                <p class="text-base font-black tracking-tight text-primary">{{ $ticketConfig['nombre_comercial'] ?? 'RESTOMASTER' }}</p>
                                @if(!empty($ticketConfig['lema']))
                                    <p class="text-[11px] text-on-surface-variant">{{ $ticketConfig['lema'] }}</p>
                                @endif
                                @if(!empty($ticketConfig['razon_social']))
                                    <p class="text-[10px] text-on-surface-variant/80">{{ $ticketConfig['razon_social'] }}</p>
                                @endif
                                <p class="text-[10px] text-on-surface-variant/80">
                                    NIT: {{ $ticketConfig['nit'] ?? '901.884.200-1' }}
                                    @if(!empty($ticketConfig['regimen'])) · {{ $ticketConfig['regimen'] }} @endif
                                </p>
                                @if(!empty($ticketConfig['direccion']))
                                    <p class="text-[10px] text-on-surface-variant/80">{{ $ticketConfig['direccion'] }}</p>
                                @endif
                                @if(!empty($ticketConfig['telefono']))
                                    <p class="text-[10px] text-on-surface-variant/80">Tel: {{ $ticketConfig['telefono'] }}</p>
                                @endif
                                @if(!empty($ticketConfig['resolucion_dian']))
                                    <p class="text-[9px] text-on-surface-variant/70">{{ $ticketConfig['resolucion_dian'] }}</p>
                                @endif
                                @if(!empty($ticketConfig['rango_autorizado']))
                                    <p class="text-[9px] text-on-surface-variant/70">{{ $ticketConfig['rango_autorizado'] }}</p>
                                @endif
                            </div>

                            <!-- DATOS DE LA TRANSACCIÓN -->
                            <div class="border-b border-dashed border-surface-container-high pb-3 space-y-1 text-[11px]">
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">FACTURA POS:</span>
                                    <span class="font-black text-on-surface">#{{ $pedidoTicketSeleccionado->codigo ?? $pedidoTicketSeleccionado->id }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">FECHA / HORA:</span>
                                    <span class="font-bold">{{ $pedidoTicketSeleccionado->pagado_en?->format('d/m/Y H:i') ?? $pedidoTicketSeleccionado->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">CAJERO:</span>
                                    <span class="font-bold">{{ $pedidoTicketSeleccionado->usuario?->name ?? 'Caja Central' }}</span>
                                </div>
                                @if($pedidoTicketSeleccionado->mesero)
                                    <div class="flex justify-between">
                                        <span class="text-on-surface-variant">MESERO:</span>
                                        <span>{{ $pedidoTicketSeleccionado->mesero->name }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">UBICACIÓN / CLIENTE:</span>
                                    <span class="font-bold">
                                        {{ $pedidoTicketSeleccionado->mesa?->numero ? 'Mesa #'.$pedidoTicketSeleccionado->mesa->numero : ($pedidoTicketSeleccionado->nombre_cliente ?? 'Mostrador / Venta Directa') }}
                                    </span>
                                </div>
                                @if($pedidoTicketSeleccionado->cliente)
                                    <div class="flex justify-between">
                                        <span class="text-on-surface-variant">CLIENTE VIP:</span>
                                        <span>{{ $pedidoTicketSeleccionado->cliente->nombre }} ({{ $pedidoTicketSeleccionado->cliente->puntos_fidelidad }} pts)</span>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">TURNO DE CAJA:</span>
                                    <span>#{{ $pedidoTicketSeleccionado->turno_caja_id ?? '1' }} ({{ $pedidoTicketSeleccionado->turnoCaja?->caja?->nombre ?? 'Caja' }})</span>
                                </div>
                            </div>

                            <!-- TABLA DE ITEMS VENDIDOS -->
                            <div class="border-b border-dashed border-surface-container-high pb-3 space-y-1.5">
                                <div class="flex justify-between text-[10px] font-black text-on-surface-variant border-b border-dashed border-surface-container-high/60 pb-1">
                                    <span class="w-8">CNT</span>
                                    <span class="flex-1 px-1">PRODUCTO</span>
                                    <span class="w-16 text-right">VR.UNI</span>
                                    <span class="w-16 text-right">TOTAL</span>
                                </div>
                                @forelse($pedidoTicketSeleccionado->items as $item)
                                    <div class="flex items-center justify-between text-[11px] leading-tight hover:bg-surface-container/40 p-1 rounded-lg">
                                        <div class="flex items-center gap-1 flex-1 min-w-0">
                                            <span class="w-6 font-black text-primary">{{ $item->cantidad }}x</span>
                                            <div class="truncate">
                                                <span class="font-bold text-on-surface">{{ $item->nombre_producto }}</span>
                                                @if((int)($item->cantidad_devuelta ?? 0) > 0)
                                                    <span class="ml-1 px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-500/15 text-rose-500 border border-rose-500/30">
                                                        -{{ $item->cantidad_devuelta }} devuelto
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="w-14 text-right text-on-surface-variant font-mono">${{ number_format((float) $item->precio_unitario, 0, ',', '.') }}</span>
                                        <span class="w-16 text-right font-black text-on-surface font-mono">${{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
                                        @if($item->cantidadDisponibleDevolucion() > 0)
                                            <button
                                                type="button"
                                                wire:click="abrirModalDevolucion({{ $item->id }})"
                                                class="ml-1.5 px-2 py-0.5 rounded-lg bg-error/15 hover:bg-error text-error hover:text-white border border-error/30 text-[10px] font-black transition-all cursor-pointer flex items-center gap-0.5 shrink-0"
                                                title="Corregir o devolver cantidad de este producto"
                                            >
                                                <span class="material-symbols-outlined text-[12px]">keyboard_return</span>
                                                <span>Devolver</span>
                                            </button>
                                        @endif
                                    </div>
                                    @if($item->notas)
                                        <p class="text-[10px] text-amber-500 italic pl-8">↳ {{ $item->notas }}</p>
                                    @endif
                                @empty
                                    <p class="text-center text-[10px] text-on-surface-variant py-2">Sin items asociados.</p>
                                @endforelse
                            </div>

                            <!-- TOTALES Y DESGLOSE FINANCIERO -->
                            <div class="space-y-1 text-[11px] border-b border-dashed border-surface-container-high pb-3">
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">SUBTOTAL:</span>
                                    <span class="font-mono">${{ number_format((float) $pedidoTicketSeleccionado->subtotal, 2) }}</span>
                                </div>
                                @if((float) $pedidoTicketSeleccionado->descuento > 0)
                                    <div class="flex justify-between text-secondary">
                                        <span>DESCUENTO:</span>
                                        <span class="font-mono">-${{ number_format((float) $pedidoTicketSeleccionado->descuento, 2) }}</span>
                                    </div>
                                @endif
                                @if((float) $pedidoTicketSeleccionado->propina > 0)
                                    <div class="flex justify-between text-on-surface-variant">
                                        <span>PROPINA VOLUNTARIA:</span>
                                        <span class="font-mono">${{ number_format((float) $pedidoTicketSeleccionado->propina, 2) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between text-sm font-black text-on-surface pt-1 border-t border-dashed border-surface-container-high">
                                    <span>TOTAL COBRADO:</span>
                                    <span class="text-primary font-mono">${{ number_format((float) $pedidoTicketSeleccionado->total, 2) }}</span>
                                </div>
                            </div>

                            <!-- PAGO Y CAMBIO -->
                            <div class="space-y-1 text-[11px] border-b border-dashed border-surface-container-high pb-3">
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">MÉTODO DE PAGO:</span>
                                    <span class="font-black uppercase text-secondary">{{ $pedidoTicketSeleccionado->metodo_pago ?? 'Efectivo' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">MONTO RECIBIDO:</span>
                                    <span class="font-mono font-bold">${{ number_format((float) ($pedidoTicketSeleccionado->monto_pagado ?? $pedidoTicketSeleccionado->total), 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">CAMBIO / DEVUELTA:</span>
                                    <span class="font-mono font-black text-secondary">${{ number_format((float) $pedidoTicketSeleccionado->cambio, 2) }}</span>
                                </div>
                            </div>

                            <!-- PIE Y AUDITORÍA VERIFICADA -->
                            <div class="text-center pt-1 space-y-1 text-[10px] text-on-surface-variant">
                                @if(!empty($ticketConfig['pie_pagina']))
                                    <p class="font-bold text-on-surface">{{ $ticketConfig['pie_pagina'] }}</p>
                                @endif
                                <p class="text-[9px] text-emerald-600 font-bold flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                    <span>REGISTRO REAL EN BD · IDEMPOTENCIA: {{ substr($pedidoTicketSeleccionado->idempotencia_uuid ?? ('UUID-'.$pedidoTicketSeleccionado->id), 0, 18) }}...</span>
                                </p>
                            </div>
                        </div>
                    @else
                        <!-- FORMATO TEXTO PURO ESC/POS -->
                        <div class="rounded-2xl bg-black text-emerald-400 p-4 border border-surface-container-highest shadow-inner font-mono text-[11px] whitespace-pre overflow-x-auto leading-relaxed select-all">
                            {{ $ticketTextoEscPos ?? 'Cargando texto térmico...' }}
                        </div>
                    @endif
                </div>

                <!-- ACCIONES INFERIORES -->
                <div class="mt-4 pt-3 border-t border-surface-container-high flex flex-wrap items-center justify-between gap-2 shrink-0">
                    <button 
                        type="button"
                        onclick="window.print()" 
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-surface-container-high bg-surface-container hover:bg-surface-container-high text-on-surface font-bold text-xs transition cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[16px]">print</span>
                        <span>Imprimir Web</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            wire:click="reenviarImpresionTicket({{ $pedidoTicketSeleccionado->id }})"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-secondary/15 hover:bg-secondary/25 border border-secondary/30 text-secondary font-black text-xs transition cursor-pointer"
                            title="Re-encolar trabajo a la impresora física de tickets"
                        >
                            <span class="material-symbols-outlined text-[16px]">local_printshop</span>
                            <span>Reimprimir Térmica</span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="cerrarModalTicket" 
                            class="px-4 py-2 rounded-xl bg-primary text-on-primary font-bold text-xs hover:bg-primary-container transition shadow-xs cursor-pointer"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: GESTIÓN DE TERMINALES DE CAJA -->
    @if($modalGestionTerminalesOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
            <div class="w-full max-w-2xl rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
                            <span class="material-symbols-outlined text-[20px]">devices</span>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-on-surface">Gestión de Terminales de Caja</h3>
                            <p class="text-[11px] text-on-surface-variant">Edita nombres, códigos, activa/desactiva o elimina puntos de cobro físicos.</p>
                        </div>
                    </div>
                    <button wire:click="$set('modalGestionTerminalesOpen', false)" class="text-on-surface-variant hover:text-on-surface transition-colors p-1 rounded-lg">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="space-y-2.5 max-h-[60vh] overflow-y-auto pr-1">
                    @forelse($todasLasCajas as $cajaItem)
                        <div class="rounded-2xl border {{ $cajaItem->activa ? 'border-surface-container-highest bg-surface-container-low/40' : 'border-dashed border-surface-container-high bg-surface-container-highest/20 opacity-75' }} p-4 transition-all hover:shadow-sm">
                            @if($cajaEditandoId === $cajaItem->id)
                                <!-- MODO EDICIÓN EN LÍNEA -->
                                <form wire:submit="guardarEdicionCaja" class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-[11px] font-bold text-on-surface-variant block mb-1">Nombre Terminal:</label>
                                            <input 
                                                type="text" 
                                                wire:model="formEditarCaja.nombre" 
                                                class="w-full h-10 rounded-xl border border-primary/40 bg-surface-container-lowest px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                                                required
                                            />
                                            @error('formEditarCaja.nombre') <span class="text-[11px] text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="text-[11px] font-bold text-on-surface-variant block mb-1">Código Único:</label>
                                            <input 
                                                type="text" 
                                                wire:model="formEditarCaja.codigo" 
                                                class="w-full h-10 rounded-xl border border-primary/40 bg-surface-container-lowest px-3 font-mono text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                                                required
                                            />
                                            @error('formEditarCaja.codigo') <span class="text-[11px] text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 pt-1">
                                        <button 
                                            type="button" 
                                            wire:click="cancelarEdicionCaja" 
                                            class="rounded-xl border border-surface-container-high bg-surface-container px-3 py-1.5 text-xs font-bold text-on-surface-variant hover:text-on-surface"
                                        >
                                            Cancelar
                                        </button>
                                        <button 
                                            type="submit" 
                                            class="rounded-xl bg-primary px-4 py-1.5 text-xs font-black text-on-primary shadow-sm hover:bg-primary-container"
                                        >
                                            ✓ Guardar Cambios
                                        </button>
                                    </div>
                                </form>
                            @else
                                <!-- MODO VISUALIZACIÓN -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0 w-3 h-3 rounded-full {{ $cajaItem->activa ? 'bg-secondary' : 'bg-surface-container-highest' }}" title="{{ $cajaItem->activa ? 'Terminal activa' : 'Terminal inactiva' }}"></div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-extrabold text-on-surface">{{ $cajaItem->nombre }}</span>
                                                <span class="font-mono text-[11px] font-bold px-2 py-0.5 rounded-md bg-surface-container text-on-surface-variant border border-surface-container-highest">
                                                    {{ $cajaItem->codigo }}
                                                </span>
                                                @if($cajaItem->activa)
                                                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-secondary-container/40 text-on-secondary-container border border-secondary/20">
                                                        Activa
                                                    </span>
                                                @else
                                                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant">
                                                        Inactiva
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 text-[11px] text-on-surface-variant mt-1">
                                                <span>{{ $cajaItem->turnos_count }} {{ $cajaItem->turnos_count === 1 ? 'turno registrado' : 'turnos registrados' }}</span>
                                                @if($cajaItem->turnos_count > 0)
                                                    <span>•</span>
                                                    <span class="text-amber-600 font-medium">Contiene auditoría contable</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 self-end sm:self-center">
                                        <!-- BOTÓN ACTIVAR / DESACTIVAR -->
                                        <button 
                                            wire:click="alternarEstadoCaja({{ $cajaItem->id }})" 
                                            class="inline-flex items-center gap-1 rounded-xl px-2.5 py-1.5 text-xs font-bold transition-all {{ $cajaItem->activa ? 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 border border-amber-500/20' : 'bg-secondary/10 text-secondary hover:bg-secondary/20 border border-secondary/20' }}"
                                            title="{{ $cajaItem->activa ? 'Desactivar esta terminal' : 'Activar esta terminal' }}"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">{{ $cajaItem->activa ? 'power_settings_new' : 'check_circle' }}</span>
                                            <span>{{ $cajaItem->activa ? 'Desactivar' : 'Activar' }}</span>
                                        </button>

                                        <!-- BOTÓN EDITAR -->
                                        <button 
                                            wire:click="iniciarEdicionCaja({{ $cajaItem->id }})" 
                                            class="inline-flex items-center gap-1 rounded-xl border border-surface-container-highest bg-surface-container px-2.5 py-1.5 text-xs font-bold text-on-surface hover:bg-surface-container-high transition-all"
                                            title="Editar nombre y código"
                                        >
                                            <span class="material-symbols-outlined text-[16px] text-primary">edit</span>
                                            <span>Editar</span>
                                        </button>

                                        <!-- BOTÓN ELIMINAR (SOLO ADMIN) -->
                                        @can('delete', App\Models\Caja::class)
                                            @if($cajaItem->turnos_count === 0)
                                                <button 
                                                    wire:click="eliminarCaja({{ $cajaItem->id }})" 
                                                    wire:confirm="¿Seguro que deseas eliminar permanentemente la terminal {{ $cajaItem->nombre }}? Esta acción no se puede deshacer."
                                                    class="inline-flex items-center gap-1 rounded-xl bg-error/10 text-error hover:bg-error/20 border border-error/20 px-2.5 py-1.5 text-xs font-bold transition-all"
                                                    title="Eliminar terminal (sin turnos)"
                                                >
                                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                                    <span>Eliminar</span>
                                                </button>
                                            @else
                                                <button 
                                                    disabled
                                                    class="inline-flex items-center gap-1 rounded-xl bg-surface-container text-on-surface-variant/40 border border-surface-container-highest px-2.5 py-1.5 text-xs font-medium cursor-not-allowed opacity-60"
                                                    title="No se puede eliminar porque tiene turnos y ventas asociadas. Puedes desactivarla."
                                                >
                                                    <span class="material-symbols-outlined text-[16px]">lock</span>
                                                    <span>Eliminar</span>
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-6 text-xs text-on-surface-variant">
                            No hay terminales configuradas en el sistema.
                        </div>
                    @endforelse
                </div>

                <div class="pt-3 border-t border-surface-container-high flex items-center justify-between">
                    <button 
                        type="button" 
                        wire:click="abrirModalNuevaCaja" 
                        class="inline-flex items-center gap-1.5 rounded-xl bg-secondary/15 text-secondary border border-secondary/30 px-3 py-2 text-xs font-bold hover:bg-secondary/25 transition-all"
                    >
                        <span class="material-symbols-outlined text-[16px]">add_box</span>
                        <span>+ Nueva Terminal</span>
                    </button>
                    <button 
                        type="button"
                        wire:click="$set('modalGestionTerminalesOpen', false)" 
                        class="rounded-xl border border-surface-container-high bg-surface-container px-4 py-2 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: NUEVA TERMINAL DE CAJA -->
    @if($modalNuevaCajaOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 animate-fade-in">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-surface-container-highest space-y-4">
                <div class="flex items-center justify-between border-b border-surface-container-high pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-secondary-container/50 text-secondary flex items-center justify-center border border-secondary/30">
                            <span class="material-symbols-outlined text-[20px]">add_box</span>
                        </div>
                        <h3 class="text-base font-extrabold text-on-surface">Nueva Terminal de Caja</h3>
                    </div>
                    <button wire:click="$set('modalNuevaCajaOpen', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form wire:submit="guardarNuevaCaja" class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Nombre Descriptivo de la Caja:</label>
                        <input 
                            type="text" 
                            wire:model="formCaja.nombre" 
                            placeholder="Ej. Caja 2 Barra & Coctelería"
                            class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                            required
                        />
                        @error('formCaja.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Código (Único):</label>
                            <input 
                                type="text" 
                                wire:model="formCaja.codigo" 
                                placeholder="Ej. CAJA-02"
                                class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 font-mono text-xs font-bold text-on-surface focus:border-primary focus:ring-0"
                                required
                            />
                            @error('formCaja.codigo') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant block mb-1">Tipo de Terminal:</label>
                            <select wire:model="formCaja.tipo" class="w-full h-11 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                <option value="principal">Principal / Salón</option>
                                <option value="barra">Barra & Bebidas</option>
                                <option value="delivery">Delivery & Domicilios</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant block mb-1">Descripción / Ubicación (Opcional):</label>
                        <input 
                            type="text" 
                            wire:model="formCaja.descripcion" 
                            placeholder="Ubicación física en el restaurante..."
                            class="w-full h-10 rounded-xl border border-surface-container-high bg-surface-container-low px-3 text-xs text-on-surface focus:border-primary focus:ring-0"
                        />
                    </div>

                    <div class="pt-3 border-t border-surface-container-high grid grid-cols-2 gap-2">
                        <button 
                            type="button"
                            wire:click="$set('modalNuevaCajaOpen', false)" 
                            class="rounded-xl border border-surface-container-high bg-surface-container py-2.5 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            class="rounded-xl bg-primary py-2.5 text-xs font-black text-on-primary shadow-md hover:bg-primary-container"
                        >
                            ✓ Crear Terminal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL TÁCTIL: DEVOLUCIÓN / REEMBOLSO RÁPIDO DE ÍTEM -->
    @if($modalDevolucionItem && $itemSeleccionadoDevolucion)
        <div 
            x-data 
            @keydown.escape.window="$wire.cerrarModalDevolucion()" 
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fade-in"
        >
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest text-on-surface p-6 shadow-2xl border border-outline-variant/20 flex flex-col space-y-4">
                
                <!-- CABECERA -->
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-error/15 text-error flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">keyboard_return</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-on-surface">Devolución / Corrección de Ítem</h3>
                            <p class="text-[11px] text-on-surface-variant font-medium">Reajuste atómico de caja, stock y contabilidad</p>
                        </div>
                    </div>
                    <button type="button" wire:click="cerrarModalDevolucion" class="text-on-surface-variant hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                @error('generalDevolucion')
                    <div class="p-3 rounded-xl bg-error/10 border border-error/30 text-xs text-error font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">error</span>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <!-- DETALLES DEL PRODUCTO -->
                <div class="rounded-2xl bg-surface-container-low p-3.5 border border-outline-variant/20 space-y-2">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-extrabold text-on-surface">{{ $itemSeleccionadoDevolucion->nombre_producto }}</span>
                        <span class="font-mono font-bold text-primary">${{ number_format((float) $itemSeleccionadoDevolucion->precio_unitario, 0, ',', '.') }} c/u</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-on-surface-variant">
                        <span>Total cobrado: {{ $itemSeleccionadoDevolucion->cantidad }} unids (${{ number_format((float) $itemSeleccionadoDevolucion->subtotal, 0, ',', '.') }})</span>
                        <span>Disponible: <strong class="text-on-surface">{{ $itemSeleccionadoDevolucion->cantidadDisponibleDevolucion() }} unids</strong></span>
                    </div>
                </div>

                <!-- SELECTOR DE CANTIDAD A DEVOLVER -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-on-surface flex justify-between">
                        <span>Cantidad a Devolver / Reembolsar:</span>
                        <span class="text-primary font-black font-mono">
                            Total Reembolso: -${{ number_format((float) $itemSeleccionadoDevolucion->precio_unitario * $cantidadDevolucion, 0, ',', '.') }}
                        </span>
                    </label>
                    <div class="flex items-center gap-3">
                        <button 
                            type="button" 
                            wire:click="$set('cantidadDevolucion', {{ max(1, $cantidadDevolucion - 1) }})"
                            class="w-10 h-10 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-black text-lg flex items-center justify-center cursor-pointer transition shadow-xs"
                        >
                            -
                        </button>
                        <span class="flex-1 text-center font-black text-base text-on-surface font-mono">{{ $cantidadDevolucion }}</span>
                        <button 
                            type="button" 
                            wire:click="$set('cantidadDevolucion', {{ min($itemSeleccionadoDevolucion->cantidadDisponibleDevolucion(), $cantidadDevolucion + 1) }})"
                            class="w-10 h-10 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-black text-lg flex items-center justify-center cursor-pointer transition shadow-xs"
                        >
                            +
                        </button>
                    </div>
                </div>

                <!-- NOTA DE CRÉDITO OBLIGATORIA (Fase 8.3) -->
                <div class="space-y-1 rounded-2xl bg-error/5 border border-error/25 p-3">
                    <label class="text-xs font-black text-on-surface flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-error text-[16px]">receipt_long</span>
                        Nota de Crédito obligatoria
                    </label>
                    <select wire:model="motivoNcDevolucion" class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-xs text-on-surface focus:border-primary focus:ring-0 min-h-[44px]">
                        <option value="error_cargo">Error de cargo / cobro de más</option>
                        <option value="producto_defectuoso">Producto defectuoso</option>
                        <option value="cambio_pedido">Cambio de pedido</option>
                        <option value="otro">Otro</option>
                    </select>
                    @error('motivoNcDevolucion')
                        <p class="text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                    <textarea wire:model="descripcionNcDevolucion" rows="2" maxlength="500" placeholder="Detalle adicional de la NC (opcional)" class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0"></textarea>
                </div>

                <!-- MOTIVO -->
                <div class="space-y-1">
                    <label class="text-xs font-bold text-on-surface">Motivo de la Devolución:</label>
                    <select wire:model="motivoDevolucion" class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0">
                        <option value="Error de digitación del cajero">Error de digitación del cajero (Cobro de más)</option>
                        <option value="Cliente cambió de opinión">Cliente cambió de opinión / Devolución</option>
                        <option value="Bebida/plato no consumido">Bebida o plato no consumido en mesa/barra</option>
                        <option value="Comanda mal tomada por mesero">Comanda mal tomada por mesero</option>
                        <option value="Plato con inconformidad">Inconformidad con el producto</option>
                        <option value="Otro motivo extraordinario">Otro motivo extraordinario</option>
                    </select>
                </div>

                <!-- AUTORIZACIÓN: SUPERVISOR O KEYPAD DE PIN -->
                @php
                    $user = auth()->user();
                    $esSupervisor = $user && in_array($user->role?->slug, ['admin', 'gerente'], true);
                    $configSvc = app(\App\Services\ConfiguracionService::class);
                    $tienePin = $configSvc->tienePinSeguridad();
                @endphp

                @if($esSupervisor)
                    <div class="rounded-xl bg-secondary/10 border border-secondary/25 p-2.5 text-xs text-secondary flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">verified_user</span>
                        <span>Autorizado directamente por <strong>{{ $user->name }}</strong> ({{ ucfirst($user->role?->slug ?? 'Admin') }})</span>
                    </div>
                @else
                    @if($tienePin)
                        <div class="space-y-2 border-t border-outline-variant/15 pt-2">
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-bold text-on-surface flex items-center gap-1">
                                    <span class="material-symbols-outlined text-primary text-[15px]">lock</span>
                                    PIN de Supervisor Requerido:
                                </span>
                                <span class="font-mono text-sm tracking-widest text-primary font-black">
                                    {{ str_repeat('•', strlen($pinAutorizacionDevolucion)) ?: 'Ingrese PIN' }}
                                </span>
                            </div>

                            @error('pinAutorizacionDevolucion')
                                <p class="text-xs text-error font-medium">{{ $message }}</p>
                            @enderror

                            <!-- KEYPAD NUMÉRICO TÁCTIL 3x4 -->
                            <div class="grid grid-cols-3 gap-1.5 pt-1">
                                @foreach(['1','2','3','4','5','6','7','8','9'] as $digito)
                                    <button 
                                        type="button" 
                                        wire:click="agregarDigitoPinDevolucion('{{ $digito }}')"
                                        class="h-10 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-mono font-black text-sm flex items-center justify-center cursor-pointer transition shadow-xs"
                                    >
                                        {{ $digito }}
                                    </button>
                                @endforeach
                                <button 
                                    type="button" 
                                    wire:click="limpiarPinDevolucion"
                                    class="h-10 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant font-bold text-xs flex items-center justify-center cursor-pointer transition"
                                >
                                    C
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="agregarDigitoPinDevolucion('0')"
                                    class="h-10 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-mono font-black text-sm flex items-center justify-center cursor-pointer transition shadow-xs"
                                >
                                    0
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="borrarDigitoPinDevolucion"
                                    class="h-10 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant font-bold text-xs flex items-center justify-center cursor-pointer transition"
                                >
                                    ⌫
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="space-y-1 border-t border-outline-variant/15 pt-2">
                            <label class="text-xs font-bold text-on-surface">Nombre del Supervisor que Autoriza:</label>
                            <input 
                                type="text" 
                                wire:model="supervisorNombreDevolucion" 
                                placeholder="Nombre del gerente o encargado..."
                                class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs text-on-surface focus:border-primary focus:ring-0"
                            />
                            @error('supervisorNombreDevolucion')
                                <p class="text-xs text-error font-medium mt-0.5">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                @endif

                <!-- ACCIONES -->
                <div class="pt-3 border-t border-outline-variant/15 grid grid-cols-2 gap-2">
                    <button 
                        type="button" 
                        wire:click="cerrarModalDevolucion"
                        class="rounded-xl border border-outline-variant/30 bg-surface-container py-2.5 text-xs font-extrabold text-on-surface-variant hover:text-on-surface cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button 
                        type="button" 
                        wire:click="procesarDevolucionItem"
                        class="rounded-xl bg-error py-2.5 text-xs font-black text-white shadow-md hover:bg-error/90 cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                        <span>Confirmar Devolución</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- Modal Maestro Unificado de Cobro de Tickets -->
    <livewire:caja.modal-cobro-unificado />
</div>
