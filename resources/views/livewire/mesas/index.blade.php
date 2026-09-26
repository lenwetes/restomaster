<?php

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Services\MesaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public string $filtroZona = 'todas';

    public string $filtroEstado = 'todas';

    public string $filtroMesero = 'todos'; // 'todos', 'mis_mesas'

    // Vista Mapa Gráfico (plano por zonas) / Vista Tarjetas
    public bool $vistaMapa = true;

    public ?int $mesaSeleccionadaId = null;

    // Asignación / Transferencia Mesas State
    public bool $modalTransferirOpen = false;

    public ?int $mesaTransferirId = null;

    public ?int $nuevoMeseroId = null;

    // CRUD Mesas State
    public bool $modalMesaOpen = false;

    public ?int $mesaEditandoId = null;

    public array $formMesa = [
        'numero' => '',
        'zona' => 'salon',
        'capacidad' => 4,
        'sucursal_id' => 1,
    ];

    // Modal QR de Mesa State
    public bool $modalQrOpen = false;

    public ?int $mesaQrId = null;

    public string $qrSvg = '';

    public string $qrUrl = '';

    public ?string $mensajeFlash = null;

    public ?string $tipoFlash = 'success';

    // Cancelar Mesa State
    public bool $modalCancelarOpen = false;

    public ?int $mesaCancelarId = null;

    public string $motivoCancelacion = '';

    // Gestionar Zonas State
    public bool $modalZonasOpen = false;

    // Rotación de Meseros State
    public bool $modalRotacionOpen = false;

    public string $modoRotacion = 'round_robin';

    public ?int $rotacionNuevoMeseroId = null;

    public ?string $rotacionNuevaZona = null;

    public ?int $zonaEditandoId = null;

    public array $zonaForm = [
        'nombre' => '',
        'color' => 'terracota',
        'icono' => 'mesa',
        'orden' => 0,
    ];

    public function abrirModalNuevaMesa(): void
    {
        $maxNumero = Mesa::all()->map(fn ($m) => (int) preg_replace('/\D/', '', $m->numero))->max();
        $siguienteNumero = $maxNumero ? $maxNumero + 1 : 1;

        $this->mesaEditandoId = null;
        $this->formMesa = [
            'numero' => (string) $siguienteNumero,
            'zona' => 'salon',
            'capacidad' => 4,
            'sucursal_id' => 1,
        ];
        $this->modalMesaOpen = true;
    }

    public function abrirModalEditarMesa(int $id): void
    {
        $mesa = Mesa::findOrFail($id);
        $this->mesaEditandoId = $mesa->id;
        $this->formMesa = [
            'numero' => $mesa->numero,
            'zona' => $mesa->zona,
            'capacidad' => $mesa->capacidad,
            'sucursal_id' => $mesa->sucursal_id,
        ];
        $this->modalMesaOpen = true;
    }

    public function guardarMesa(): void
    {
        if ($this->mesaEditandoId) {
            $this->authorize('update', Mesa::class);
        } else {
            $this->authorize('create', Mesa::class);
        }

        $reglaUnica = 'unique:mesas,numero';
        if ($this->mesaEditandoId) {
            $reglaUnica .= ','.$this->mesaEditandoId;
        }

        $this->validate([
            'formMesa.numero' => ['required', 'string', 'max:20', $reglaUnica],
            'formMesa.zona' => [
                'required',
                'string',
                'max:40',
                Rule::when(
                    \App\Models\Zona::where('sucursal_id', $this->sucursalEnContexto())->exists(),
                    Rule::exists('zonas', 'slug')->where(fn ($q) => $q->where('sucursal_id', $this->sucursalEnContexto())->where('activa', true)),
                    Rule::in(['salon', 'barra', 'terraza', 'vip', 'patio'])
                ),
            ],
            'formMesa.capacidad' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'formMesa.numero.required' => 'El número o código de la mesa es obligatorio.',
            'formMesa.numero.unique' => 'Ya existe una mesa con este número en el sistema.',
            'formMesa.capacidad.min' => 'La capacidad mínima es de 1 comensal.',
        ]);

        $mesaService = app(MesaService::class);

        try {
            if ($this->mesaEditandoId) {
                $mesa = Mesa::findOrFail($this->mesaEditandoId);
                $mesaService->actualizarMesa($mesa, $this->formMesa, Auth::user());
                $this->mensajeFlash = "Mesa #{$mesa->numero} actualizada exitosamente.";
            } else {
                $mesa = $mesaService->crearMesa($this->formMesa, Auth::user());
                $this->mensajeFlash = "Mesa #{$mesa->numero} creada y disponible en {$mesa->zona}.";
            }

            $this->modalMesaOpen = false;
            $this->tipoFlash = 'success';
        } catch (\Exception $e) {
            $this->mensajeFlash = $e->getMessage();
            $this->tipoFlash = 'error';
        }
    }

    public function eliminarMesa(int $id): void
    {
        $this->authorize('delete', Mesa::class);

        $mesa = Mesa::findOrFail($id);
        $mesaService = app(MesaService::class);

        try {
            $numero = $mesa->numero;
            $mesaService->eliminarMesa($mesa, Auth::user());
            $this->mensajeFlash = "Mesa #{$numero} eliminada del sistema.";
            $this->tipoFlash = 'success';
        } catch (\Exception $e) {
            $this->mensajeFlash = $e->getMessage();
            $this->tipoFlash = 'error';
        }
    }

    public function sucursalEnContexto(): ?int
    {
        return Auth::user()?->sucursal_id ?? \App\Models\Sucursal::value('id') ?? 1;
    }

    public function cambiarEstado(int $mesaId, string $nuevoEstado): void
    {
        $this->authorize('cambiarEstado', Mesa::class);

        abort_unless(in_array($nuevoEstado, array_column(MesaEstado::cases(), 'value'), true), 422, 'Estado de mesa inválido.');

        $mesa = Mesa::findOrFail($mesaId);

        // Si la mesa pasa a libre, remover mesero asignado para el siguiente turno
        if ($nuevoEstado === MesaEstado::LIBRE->value) {
            $mesa->mesero_id = null;
        }

        $mesa->estado = $nuevoEstado;
        $mesa->save();

        $this->dispatch('notificacion', [
            'mensaje' => "Mesa #{$mesa->numero} cambió a estado {$nuevoEstado}",
            'tipo' => 'info',
        ]);
    }

    public function toggleEstado(int $mesaId): void
    {
        $mesa = Mesa::findOrFail($mesaId);
        $siguiente = match ($mesa->estado) {
            'libre' => 'ocupada',
            'ocupada' => 'cuenta_pedida',
            'cuenta_pedida' => 'limpieza',
            'limpieza' => 'libre',
            default => 'libre',
        };
        $this->cambiarEstado($mesaId, $siguiente);
    }

    public function abrirModalQr(int $mesaId): void
    {
        $mesa = Mesa::findOrFail($mesaId);
        $this->mesaQrId = $mesa->id;
        $qrService = app(\App\Services\QrCodeService::class);
        $this->qrUrl = $qrService->urlParaMesa($mesa->numero);
        $this->qrSvg = $qrService->generarSvg($this->qrUrl, 260);
        $this->modalQrOpen = true;
    }

    public function atenderPedidoQr(int $pedidoId): void
    {
        $this->authorize('cambiarEstado', Mesa::class);

        try {
            $pedidoService = app(\App\Services\PedidoService::class);
            $pedido = $pedidoService->asignarMeseroAPedidoQr($pedidoId, Auth::user());
            $this->mensajeFlash = "¡Has tomado la comanda de la Mesa #{$pedido->mesa?->numero}! Pedido enviado a cocina.";
            $this->tipoFlash = 'success';
            $this->dispatch('notificacion', [
                'mensaje' => $this->mensajeFlash,
                'tipo' => 'success',
            ]);
        } catch (\DomainException $e) {
            $this->mensajeFlash = $e->getMessage();
            $this->tipoFlash = 'error';
            $this->dispatch('notificacion', [
                'mensaje' => $this->mensajeFlash,
                'tipo' => 'warning',
            ]);
        } catch (\Throwable $e) {
            $this->mensajeFlash = 'Error al tomar pedido: '.$e->getMessage();
            $this->tipoFlash = 'error';
        }
    }

    public function autoasignarMesa(int $mesaId): void
    {
        $mesa = Mesa::findOrFail($mesaId);
        $this->authorize('cambiarEstado', $mesa);
        abort_if(Auth::user()?->sucursal_id && $mesa->sucursal_id !== Auth::user()->sucursal_id, 403, 'Mesa de otra sucursal.');

        app(\App\Services\MesaService::class)->autoasignarMesa($mesa, Auth::user());

        $this->mensajeFlash = "¡Te has asignado la Mesa #{$mesa->numero}!";
        $this->tipoFlash = 'success';
        $this->dispatch('notificacion', [
            'mensaje' => $this->mensajeFlash,
            'tipo' => 'success',
        ]);
    }

    public function abrirModalTransferir(int $mesaId): void
    {
        // rol intencional, no permiso: reasignar mesas no tiene ability en el catálogo
        abort_unless(in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true), 403, 'Solo administradores, gerentes o cajeros pueden reasignar mesas a otros compañeros.');

        $mesa = Mesa::findOrFail($mesaId);
        $this->mesaTransferirId = $mesa->id;
        $this->nuevoMeseroId = $mesa->mesero_id;
        $this->modalTransferirOpen = true;
    }

    public function ejecutarTransferenciaMesa(): void
    {
        // rol intencional, no permiso: reasignar mesas no tiene ability en el catálogo
        abort_unless(in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true), 403, 'Solo administradores, gerentes o cajeros pueden reasignar mesas a otros compañeros.');

        $this->validate([
            'mesaTransferirId' => 'required|exists:mesas,id',
            'nuevoMeseroId' => 'required|exists:users,id',
        ], [
            'nuevoMeseroId.required' => 'Selecciona el mesero receptor de la mesa.',
        ]);

        $mesa = Mesa::findOrFail($this->mesaTransferirId);
        $nuevoMesero = \App\Models\User::findOrFail($this->nuevoMeseroId);

        app(\App\Services\MesaService::class)->transferirMesa($mesa, $nuevoMesero, Auth::user());

        $this->modalTransferirOpen = false;
        $this->mensajeFlash = "Mesa #{$mesa->numero} asignada / transferida a {$nuevoMesero->name}.";
        $this->tipoFlash = 'success';
        $this->dispatch('notificacion', [
            'mensaje' => $this->mensajeFlash,
            'tipo' => 'success',
        ]);
    }

    public function liberarParaRelevo(int $mesaId): void
    {
        $mesa = Mesa::findOrFail($mesaId);

        // rol intencional, no permiso: el relevo propio es identidad de dominio (mesa que atiendo)
        if (! in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true)) {
            abort_unless($mesa->mesero_id === Auth::id(), 403, 'Solo puedes liberar para relevo las mesas que atiendes actualmente.');
        }

        app(\App\Services\MesaService::class)->liberarParaRelevo($mesa, Auth::user());

        $this->mensajeFlash = "Mesa #{$mesa->numero} liberada para relevo. Tus compañeros ya pueden tomarla.";
        $this->tipoFlash = 'success';
        $this->dispatch('notificacion', [
            'mensaje' => $this->mensajeFlash,
            'tipo' => 'success',
        ]);
    }

    public function desasignarMesero(int $mesaId): void
    {
        // rol intencional, no permiso: desasignar mesero no tiene ability en el catálogo
        abort_unless(in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true), 403, 'Solo administradores, gerentes o cajeros pueden desasignar meseros.');

        $mesa = Mesa::findOrFail($mesaId);
        app(\App\Services\MesaService::class)->asignarMesero($mesa, null, Auth::user());

        $this->mensajeFlash = "Mesa #{$mesa->numero} liberada de mesero asignado.";
        $this->tipoFlash = 'success';
        $this->dispatch('notificacion', [
            'mensaje' => $this->mensajeFlash,
            'tipo' => 'success',
        ]);
    }

    public function abrirModalCancelar(int $mesaId): void
    {
        $user = Auth::user();
        $mesa = Mesa::findOrFail($mesaId);

        // Mesero solo puede cancelar su propia mesa; admin/gerente/cajero cualquiera
        if (! in_array($user?->role?->slug, ['admin', 'gerente', 'cajero'], true)) {
            abort_unless($mesa->mesero_id === Auth::id(), 403, 'Solo puedes cancelar las mesas que atiendes.');
        }

        $this->mesaCancelarId = $mesa->id;
        $this->motivoCancelacion = '';
        $this->modalCancelarOpen = true;
    }

    public function confirmarCancelacion(): void
    {
        $user = Auth::user();
        $mesa = Mesa::findOrFail($this->mesaCancelarId);

        // Doble verificación server-side
        if (! in_array($user?->role?->slug, ['admin', 'gerente', 'cajero'], true)) {
            abort_unless($mesa->mesero_id === Auth::id(), 403, 'Solo puedes cancelar las mesas que atiendes.');
        }

        try {
            app(\App\Services\MesaService::class)->cancelarMesa($mesa, $user, trim($this->motivoCancelacion));
            $this->modalCancelarOpen = false;
            $this->mesaCancelarId = null;
            $this->motivoCancelacion = '';
            $this->mensajeFlash = "Mesa #{$mesa->numero} cancelada y liberada correctamente.";
            $this->tipoFlash = 'success';
            $this->dispatch('notificacion', [
                'mensaje' => $this->mensajeFlash,
                'tipo' => 'success',
            ]);
        } catch (\DomainException $e) {
            $this->mensajeFlash = $e->getMessage();
            $this->tipoFlash = 'error';
            $this->modalCancelarOpen = false;
            $this->dispatch('notificacion', [
                'mensaje' => $e->getMessage(),
                'tipo' => 'warning',
            ]);
        } catch (\Throwable $e) {
            $this->mensajeFlash = 'Error al cancelar la mesa: '.$e->getMessage();
            $this->tipoFlash = 'error';
            $this->modalCancelarOpen = false;
        }
    }

    public function abrirModalZonas(): void
    {
        $this->authorize('create', \App\Models\Zona::class);
        $this->zonaEditandoId = null;
        $this->zonaForm = ['nombre' => '', 'color' => 'terracota', 'icono' => 'mesa', 'orden' => 0];
        $this->modalZonasOpen = true;
    }

    public function iniciarEdicionZona(int $id): void
    {
        $this->authorize('update', \App\Models\Zona::class);
        $zona = \App\Models\Zona::deSucursal($this->sucursalEnContexto())->findOrFail($id);
        $this->zonaEditandoId = $zona->id;
        $this->zonaForm = ['nombre' => $zona->nombre, 'color' => $zona->color, 'icono' => $zona->icono, 'orden' => $zona->orden];
    }

    public function guardarZona(): void
    {
        $this->authorize($this->zonaEditandoId ? 'update' : 'create', \App\Models\Zona::class);

        $this->validate([
            'zonaForm.nombre' => 'required|string|max:60',
            'zonaForm.color' => 'required|in:terracota,salvia,lavanda,ambar,esmeralda,indigo,rosa,pizarra',
            'zonaForm.icono' => 'required|in:mesa,barra,terraza,vip,patio,jardin,balcon,privado',
            'zonaForm.orden' => 'required|integer|min:0|max:99',
        ]);

        $datos = $this->zonaForm + ['sucursal_id' => $this->sucursalEnContexto()];
        if ($this->zonaEditandoId) {
            $zona = \App\Models\Zona::deSucursal($this->sucursalEnContexto())->findOrFail($this->zonaEditandoId);
            $zona->update($datos);
        } else {
            $datos['slug'] = \Illuminate\Support\Str::slug($datos['nombre']);
            if (! $this->zonaEditandoId && \App\Models\Zona::deSucursal($this->sucursalEnContexto())->where('slug', $datos['slug'])->exists()) {
                // Flash en vez de abort(422): un abort en una acción Livewire devuelve
                // respuesta de error sin re-render, por lo que el motivo nunca sería visible.
                $this->mensajeFlash = 'Ya existe una zona con ese nombre en esta sucursal.';
                $this->tipoFlash = 'error';

                return;
            }
            $zona = \App\Models\Zona::create($datos);
        }

        $this->modalZonasOpen = false;
        $this->zonaEditandoId = null;
        $this->dispatch('notificacion', ['mensaje' => "Zona {$zona->nombre} guardada.", 'tipo' => 'success']);
    }

    public function alternarZona(int $id): void
    {
        $this->authorize('update', \App\Models\Zona::class);
        $zona = \App\Models\Zona::deSucursal($this->sucursalEnContexto())->findOrFail($id);

        if ($zona->activa) {
            $mesas = \App\Models\Mesa::where('sucursal_id', $zona->sucursal_id)->where('zona', $zona->slug)->count();
            if ($mesas > 0) {
                // Flash en vez de abort(422): un abort en una acción Livewire devuelve
                // respuesta de error sin re-render, por lo que el motivo nunca sería visible.
                $this->mensajeFlash = "La zona {$zona->nombre} tiene mesas asignadas: reasigna primero.";
                $this->tipoFlash = 'error';

                return;
            }
        }

        $zona->update(['activa' => ! $zona->activa]);
        $this->dispatch('notificacion', ['mensaje' => "Zona {$zona->nombre} actualizada.", 'tipo' => 'info']);
    }

    public function moverMesaAZona(int $mesaId, string $zonaSlug): void
    {
        $this->authorize('mover', \App\Models\Zona::class);

        $mesa = Mesa::where('sucursal_id', $this->sucursalEnContexto())->findOrFail($mesaId);
        app(MesaService::class)->moverMesa($mesa, $zonaSlug);

        $this->dispatch('notificacion', ['mensaje' => "Mesa #{$mesa->numero} movida a {$zonaSlug}.", 'tipo' => 'success']);
    }

    #[On('comanda-actualizada')]
    public function refrescarMesas(): void
    {
        // Forzar re-renderizado automático al completarse pedidos en cocina
    }

    public function abrirModalRotacion(): void
    {
        $this->authorize('update', Mesa::class);
        $this->modoRotacion = app(\App\Services\RotacionMeseroService::class)->obtenerModoRotacion($this->sucursalEnContexto());
        $this->modalRotacionOpen = true;
    }

    public function cambiarModoRotacion(string $nuevoModo): void
    {
        $this->authorize('update', Mesa::class);
        $this->modoRotacion = $nuevoModo;
        app(\App\Services\RotacionMeseroService::class)->guardarModoRotacion($this->sucursalEnContexto(), $nuevoModo);
        $this->dispatch('notificacion', ['mensaje' => "Modo de rotación configurado: {$nuevoModo}.", 'tipo' => 'success']);
    }

    public function agregarMeseroARotacion(): void
    {
        $this->authorize('update', Mesa::class);
        if (! $this->rotacionNuevoMeseroId || ! $this->rotacionNuevaZona) {
            $this->dispatch('notificacion', ['mensaje' => 'Selecciona un mesero y una zona.', 'tipo' => 'error']);

            return;
        }

        app(\App\Services\RotacionMeseroService::class)->asignarMeseroAZona(
            $this->sucursalEnContexto(),
            $this->rotacionNuevaZona,
            $this->rotacionNuevoMeseroId
        );

        $this->rotacionNuevoMeseroId = null;
        $this->dispatch('notificacion', ['mensaje' => 'Mesero asignado a la zona correctamente.', 'tipo' => 'success']);
    }

    public function removerMeseroDeRotacion(int $rotacionId): void
    {
        $this->authorize('update', Mesa::class);
        app(\App\Services\RotacionMeseroService::class)->removerMeseroDeZona($rotacionId);
        $this->dispatch('notificacion', ['mensaje' => 'Mesero removido de la zona.', 'tipo' => 'info']);
    }

    public function toggleActivoRotacion(int $rotacionId): void
    {
        $this->authorize('update', Mesa::class);
        $rot = app(\App\Services\RotacionMeseroService::class)->toggleActivo($rotacionId);
        $estado = $rot->activo ? 'activo' : 'en descanso/inactivo';
        $this->dispatch('notificacion', ['mensaje' => "Mesero marcado {$estado}.", 'tipo' => 'info']);
    }

    public function autoasignarMesasLibres(): void
    {
        $this->authorize('update', Mesa::class);
        $mesas = Mesa::where('sucursal_id', $this->sucursalEnContexto())
            ->whereNull('mesero_id')
            ->get();

        $service = app(\App\Services\RotacionMeseroService::class);
        $asignadas = 0;
        foreach ($mesas as $m) {
            if ($service->autoasignarMesa($m)) {
                $asignadas++;
            }
        }

        $this->dispatch('notificacion', ['mensaje' => "Se auto-asignaron {$asignadas} mesas según rotación.", 'tipo' => 'success']);
    }

    public function with(): array
    {
        $query = Mesa::query()->with(['sucursal', 'mesero', 'pedidos' => function ($q) {
            $q->activos()->latest()->with(['items', 'usuario', 'mesero']);
        }]);

        if ($this->filtroZona !== 'todas') {
            $query->where('zona', $this->filtroZona);
        }

        if ($this->filtroEstado !== 'todas') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroMesero === 'mis_mesas' && Auth::check()) {
            $query->where('mesero_id', Auth::id());
        }

        $mesas = $query->orderBy('numero')->get();

        $meserosDisponibles = \App\Models\User::whereHas('role', fn ($q) => $q->where('slug', 'mesero'))
            ->where('activo', true)
            ->orderBy('name')
            ->get();

        // Global counters optimizados por agregación SQL
        $conteosPorEstado = Mesa::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $conteo = [
            'total' => (int) $conteosPorEstado->sum(),
            'libres' => (int) ($conteosPorEstado[MesaEstado::LIBRE->value] ?? 0),
            'ocupadas' => (int) ($conteosPorEstado[MesaEstado::OCUPADA->value] ?? 0),
            'por_limpiar' => (int) ($conteosPorEstado[MesaEstado::POR_LIMPIAR->value] ?? 0),
            'reservadas' => (int) ($conteosPorEstado[MesaEstado::RESERVADA->value] ?? 0),
        ];

        $mesaQr = $this->mesaQrId ? Mesa::find($this->mesaQrId) : null;

        $zonasDisponibles = Mesa::query()
            ->selectRaw('zona, count(*) as total')
            ->groupBy('zona')
            ->pluck('total', 'zona')
            ->toArray();

        return [
            'mesas' => $mesas,
            'conteo' => $conteo,
            'sucursales' => \App\Models\Sucursal::all(),
            'mesaQr' => $mesaQr,
            'meserosDisponibles' => $meserosDisponibles,
            'mesaSeleccionada' => $this->mesaSeleccionadaId ? $mesas->firstWhere('id', $this->mesaSeleccionadaId) : null,
            'zonasDisponibles' => $zonasDisponibles,
            'zonasCatalogo' => \App\Models\Zona::deSucursal($this->sucursalEnContexto())->activas()->orderBy('orden')->orderBy('nombre')->get()->keyBy('slug'),
            'rotacionesPorZona' => app(\App\Services\RotacionMeseroService::class)->obtenerRotacionesPorSucursal($this->sucursalEnContexto())->groupBy('zona_slug'),
            'modoRotacionActual' => app(\App\Services\RotacionMeseroService::class)->obtenerModoRotacion($this->sucursalEnContexto()),
        ];
    }
}; ?>

<div class="space-y-6" wire:poll.10s>
    <!-- Header Operativo (Aura Gastro Expressive OS) -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-surface-container-lowest p-5 rounded-3xl border border-surface-container-highest shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">table_restaurant</span>
                <h1 class="text-lg sm:text-xl font-extrabold tracking-tight text-on-surface">
                    Salón & Mapa de Mesas
                </h1>
                <span class="whitespace-nowrap shrink-0 rounded-full bg-secondary-container/50 px-2.5 py-0.5 text-[11px] font-bold text-on-secondary-container border border-secondary/30">
                    MES-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Monitoreo visual táctil en tiempo real · Distribución espacial y comensales
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <!-- Toggle Vista: Mapa Gráfico / Tarjetas -->
            <div class="flex w-full sm:w-auto items-center gap-1 rounded-2xl border border-surface-container-highest bg-surface-container-low p-1 shadow-sm" role="group" aria-label="Cambiar vista del salón">
                <button
                    type="button"
                    wire:click="$set('vistaMapa', true)"
                    aria-pressed="{{ $vistaMapa ? 'true' : 'false' }}"
                    class="flex h-9 flex-1 sm:flex-none sm:min-w-[64px] items-center justify-center gap-1 rounded-xl px-2.5 text-[11px] font-extrabold transition-all active:scale-95 cursor-pointer {{ $vistaMapa ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                >
                    <span class="material-symbols-outlined text-[15px]">map</span>
                    <span>Mapa</span>
                </button>
                <button
                    type="button"
                    wire:click="$set('vistaMapa', false)"
                    aria-pressed="{{ ! $vistaMapa ? 'true' : 'false' }}"
                    class="flex h-9 flex-1 sm:flex-none sm:min-w-[84px] items-center justify-center gap-1 rounded-xl px-2.5 text-[11px] font-extrabold transition-all active:scale-95 cursor-pointer {{ ! $vistaMapa ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                >
                    <span class="material-symbols-outlined text-[15px]">grid_view</span>
                    <span>Tarjetas</span>
                </button>
            </div>
            @can('create', App\Models\Zona::class)
                <button
                    wire:click="abrirModalZonas"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high border border-surface-container-highest px-3.5 py-2.5 text-xs font-extrabold text-on-surface transition-all active:scale-95 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">map</span>
                    <span class="whitespace-nowrap">Gestionar Zonas</span>
                </button>
                <button
                    wire:click="abrirModalRotacion"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 px-3.5 py-2.5 text-xs font-extrabold text-amber-500 transition-all active:scale-95 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">sync_alt</span>
                    <span class="whitespace-nowrap">Rotación Meseros</span>
                </button>
            @endcan
            @can('create', App\Models\Mesa::class)
                <button 
                    wire:click="abrirModalNuevaMesa"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 rounded-xl bg-secondary px-3.5 py-2.5 text-xs font-extrabold text-on-secondary shadow-md hover:bg-secondary-fixed-dim transition-all active:scale-95 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    <span class="whitespace-nowrap">+ Nueva Mesa</span>
                </button>
            @endcan
            <a 
                href="{{ route('pos') }}" 
                wire:navigate
                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-extrabold text-on-primary shadow-md hover:bg-primary-container transition-all active:scale-95"
            >
                <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
                <span class="whitespace-nowrap">Abrir POS Táctil</span>
            </a>
        </div>
    </header>
    <!-- Feedback Flash Banner -->
    @if ($mensajeFlash)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="flex items-center justify-between p-4 rounded-2xl shadow-sm border animate-fade-in
                    {{ $tipoFlash === 'success' ? 'bg-secondary-container/40 border-secondary/30 text-on-secondary-container' : 'bg-error-container/40 border-error/30 text-error' }}">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-lg">{{ $tipoFlash === 'success' ? 'check_circle' : 'error' }}</span>
                <span class="font-bold text-xs sm:text-sm">{{ $mensajeFlash }}</span>
            </div>
            <button @click="show = false" class="opacity-70 hover:opacity-100">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
    @endif
    <!-- Status Overview Counters (Stitch MES-01 Aura Gastro Expressive OS) -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <!-- Libres -->
        <button 
            wire:click="$set('filtroEstado', 'libre')"
            class="flex items-center justify-between rounded-2xl border p-4 text-left transition-all {{ $filtroEstado === 'libre' ? 'border-secondary bg-secondary-container/30 ring-2 ring-secondary/40 shadow-sm' : 'border-surface-container-highest bg-surface-container-lowest hover:bg-surface-container-low shadow-sm' }}"
        >
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Mesas Libres</span>
                <p class="text-2xl font-mono font-extrabold text-secondary">{{ $conteo['libres'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary-container/50 text-secondary border border-secondary/30">
                <span class="material-symbols-outlined text-[22px]">check_circle</span>
            </div>
        </button>

        <!-- Ocupadas -->
        <button 
            wire:click="$set('filtroEstado', 'ocupada')"
            class="flex items-center justify-between rounded-2xl border p-4 text-left transition-all {{ $filtroEstado === 'ocupada' ? 'border-primary bg-primary-container/15 ring-2 ring-primary/40 shadow-sm' : 'border-surface-container-highest bg-surface-container-lowest hover:bg-surface-container-low shadow-sm' }}"
        >
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Mesas Ocupadas</span>
                <p class="text-2xl font-mono font-extrabold text-primary">{{ $conteo['ocupadas'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-fixed text-primary border border-primary/30">
                <span class="material-symbols-outlined text-[22px]">restaurant</span>
            </div>
        </button>

        <!-- Por Limpiar -->
        <button 
            wire:click="$set('filtroEstado', 'por_limpiar')"
            class="flex items-center justify-between rounded-2xl border p-4 text-left transition-all {{ $filtroEstado === 'por_limpiar' ? 'border-tertiary bg-tertiary-container/20 ring-2 ring-tertiary/40 shadow-sm' : 'border-surface-container-highest bg-surface-container-lowest hover:bg-surface-container-low shadow-sm' }}"
        >
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Por Limpiar</span>
                <p class="text-2xl font-mono font-extrabold text-tertiary">{{ $conteo['por_limpiar'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-tertiary-container/30 text-tertiary border border-tertiary/30">
                <span class="material-symbols-outlined text-[22px]">cleaning_services</span>
            </div>
        </button>

        <!-- Total Mesas -->
        <button 
            wire:click="$set('filtroEstado', 'todas')"
            class="flex items-center justify-between rounded-2xl border p-4 text-left transition-all {{ $filtroEstado === 'todas' ? 'border-outline bg-surface-container ring-2 ring-outline/30 shadow-sm' : 'border-surface-container-highest bg-surface-container-lowest hover:bg-surface-container-low shadow-sm' }}"
        >
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Total Mesas</span>
                <p class="text-2xl font-mono font-extrabold text-on-surface">{{ $conteo['total'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container text-on-surface border border-surface-container-high">
                <span class="material-symbols-outlined text-[22px]">grid_view</span>
            </div>
        </button>
    </div>

    <!-- Barra de Filtros de Zona y Mesero Unificada -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-3 shadow-xs">
        <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible sm:pb-0">
            <span class="shrink-0 text-xs font-bold text-on-surface-variant px-2 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-primary">filter_alt</span>
                <span>Zonas:</span>
            </span>
            <button 
                type="button"
                wire:click="$set('filtroZona', 'todas')"
                class="shrink-0 inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-extrabold transition-all cursor-pointer {{ $filtroZona === 'todas' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
            >
                <span class="material-symbols-outlined text-[15px]">domain</span>
                <span>Todas</span>
                <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-black {{ $filtroZona === 'todas' ? 'bg-white/20 text-white' : 'bg-surface-container-highest text-on-surface-variant' }}">{{ $conteo['total'] }}</span>
            </button>
            @foreach(collect($zonasDisponibles)->sortBy(fn ($zTotal, $zSlug) => $zonasCatalogo[$zSlug]->orden ?? 99)->toArray() as $zKey => $zCount)
                @php
                    $zInfo = [
                        'nombre' => $zonasCatalogo[$zKey]->nombre ?? ucfirst(str_replace(['-', '_'], ' ', $zKey)),
                        'icono' => \App\Models\Zona::ICONOS[$zonasCatalogo[$zKey]->icono ?? 'mesa'] ?? 'table_restaurant',
                        'dot' => \App\Models\Zona::PALETA[$zonasCatalogo[$zKey]->color ?? 'pizarra']['punto'] ?? 'bg-outline-variant',
                    ];
                @endphp
                <button 
                    type="button"
                    wire:click="$set('filtroZona', '{{ $zKey }}')"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-extrabold transition-all cursor-pointer {{ $filtroZona === $zKey ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                >
                    <span class="h-2 w-2 rounded-full {{ $zInfo['dot'] }}"></span>
                    <span class="material-symbols-outlined text-[15px]">{{ $zInfo['icono'] }}</span>
                    <span>{{ $zInfo['nombre'] }}</span>
                    <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-black {{ $filtroZona === $zKey ? 'bg-white/20 text-white' : 'bg-surface-container-highest text-on-surface-variant' }}">{{ $zCount }}</span>
                </button>
            @endforeach
        </div>

        <div class="flex w-full sm:w-auto flex-wrap items-center gap-2 border-t border-surface-container-high pt-2 sm:border-t-0 sm:pt-0">
            <!-- Filtro Mesero Asignado -->
            <div class="flex items-center gap-1 pl-2 sm:border-l border-surface-container-high">
                <span class="text-xs font-bold text-on-surface-variant flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-primary">person</span>
                    <span>Mesero:</span>
                </span>
                <button 
                    type="button"
                    wire:click="$set('filtroMesero', 'todos')"
                    class="rounded-xl px-3 py-1.5 text-xs font-extrabold transition-all cursor-pointer {{ $filtroMesero === 'todos' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                >
                    Todas
                </button>
                @if(Auth::user()?->role?->slug === 'mesero')
                    <button 
                        type="button"
                        wire:click="$set('filtroMesero', 'mis_mesas')"
                        class="rounded-xl px-3 py-1.5 text-xs font-extrabold transition-all cursor-pointer {{ $filtroMesero === 'mis_mesas' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}"
                    >
                        Mis Mesas
                    </button>
                @endif
            </div>
        </div>

        @if($filtroEstado !== 'todas' || $filtroZona !== 'todas' || $filtroMesero !== 'todos')
            <button 
                type="button"
                wire:click="$set('filtroEstado', 'todas'); $set('filtroZona', 'todas'); $set('filtroMesero', 'todos')"
                class="flex items-center gap-1 text-xs font-bold text-primary hover:underline px-2 cursor-pointer"
            >
                <span class="material-symbols-outlined text-[16px]">close</span>
                <span>Restablecer Filtros</span>
            </button>
        @endif
    </div>

    @if (! $vistaMapa)
    <!-- Mesas Matrix Grid (Stitch MES-01 Aura Gastro Porcelain Squircle Cards) -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @forelse ($mesas as $mesa)
            @php
                $pedidoActivo = $mesa->pedidos->first();
                $tieneCocinaPendiente = $pedidoActivo ? $pedidoActivo->items->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])->isNotEmpty() : false;
                $tieneCocinaLista = $pedidoActivo ? $pedidoActivo->items->where('estado_cocina', 'listo')->isNotEmpty() : false;
                $comandaListaServir = $pedidoActivo && ($pedidoActivo->estado === 'listo' || (! $tieneCocinaPendiente && $tieneCocinaLista));
                $comandaEnCocina = $pedidoActivo && ($tieneCocinaPendiente || in_array($pedidoActivo->estado, ['en_cocina', 'en_preparacion', 'en_proceso']));

                $cardBorder = match($mesa->estado) {
                    'libre' => 'border-secondary/30 hover:border-secondary hover:shadow-md',
                    'ocupada' => $comandaListaServir 
                        ? 'border-emerald-500 ring-2 ring-emerald-500/40 hover:shadow-lg' 
                        : ($comandaEnCocina ? 'border-amber-400 hover:border-amber-500 hover:shadow-md' : 'border-primary/30 hover:border-primary hover:shadow-md'),
                    'por_limpiar' => 'border-tertiary/40 hover:border-tertiary hover:shadow-md',
                    'reservada' => 'border-secondary/30 hover:border-secondary hover:shadow-md',
                    default => 'border-surface-container-highest',
                };
                $badgeStyle = match($mesa->estado) {
                    'libre' => 'bg-secondary-container/60 text-on-secondary-container border-secondary/30',
                    'ocupada' => $comandaListaServir
                        ? 'bg-emerald-100 text-emerald-800 border-emerald-300 font-black animate-pulse'
                        : ($comandaEnCocina ? 'bg-amber-100 text-amber-800 border-amber-300 font-bold' : 'bg-primary-fixed text-on-primary-fixed border-primary/30'),
                    'por_limpiar' => 'bg-tertiary-container/30 text-tertiary border-tertiary/30',
                    'reservada' => 'bg-secondary-container/60 text-on-secondary-container border-secondary/30',
                    default => 'bg-surface-container text-on-surface-variant border-surface-container-high',
                };
            @endphp

            <div class="relative flex flex-col justify-between rounded-3xl border bg-surface-container-lowest p-4 shadow-sm transition-all duration-200 hover:shadow-md {{ $cardBorder }}">
                <div>
                    <!-- Header: Table Number, Pax and Status -->
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-2xl font-mono font-black text-on-surface tracking-tight">
                                    {{ $mesa->numero }}
                                </span>
                                <!-- Botón Ver / Imprimir Código QR -->
                                <button 
                                    wire:click="abrirModalQr({{ $mesa->id }})"
                                    class="p-1 rounded-lg hover:bg-primary/10 text-on-surface-variant hover:text-primary transition-colors cursor-pointer"
                                    title="Código QR / Auto-pedido"
                                >
                                    <span class="material-symbols-outlined text-[16px]">qr_code_2</span>
                                </button>
                                @can('update', App\Models\Mesa::class)
                                    <div class="flex items-center gap-0.5 opacity-60 hover:opacity-100 transition-opacity">
                                        <button 
                                            wire:click="abrirModalEditarMesa({{ $mesa->id }})"
                                            class="p-1 rounded-lg hover:bg-surface-container text-on-surface-variant hover:text-on-surface transition-colors"
                                            title="Editar Mesa"
                                        >
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                        </button>
                                        @if($mesa->estado === 'libre')
                                            <button 
                                              wire:click="eliminarMesa({{ $mesa->id }})"
                                                wire:confirm="¿Deseas eliminar la Mesa #{{ $mesa->numero }}?"
                                                class="p-1 rounded-lg hover:bg-error-container/20 text-on-surface-variant hover:text-error transition-colors"
                                                title="Eliminar Mesa"
                                            >
                                                <span class="material-symbols-outlined text-[15px]">delete</span>
                                            </button>
                                        @endif
                                    </div>
                                @endcan
                            </div>
                            <span class="text-[10px] font-bold text-on-surface-variant block uppercase tracking-wider mt-0.5">
                                Zona {{ $mesa->zona }}
                            </span>
                            @if($mesa->mesero)
                                <div class="mt-1.5 flex items-center gap-1 flex-wrap">
                                    @if($mesa->mesero_id === Auth::id())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-primary/10 text-primary border border-primary/25" title="Mesa a tu cargo">
                                            <span class="material-symbols-outlined text-[13px]">person</span>
                                            <span>Atendida por ti</span>
                                        </span>
                                        <button 
                                            type="button"
                                            wire:click="liberarParaRelevo({{ $mesa->id }})"
                                            wire:confirm="¿Deseas liberar la Mesa #{{ $mesa->numero }} para que un compañero tome el relevo de tu turno?"
                                            class="text-[10px] font-extrabold text-amber-700 bg-amber-500/10 hover:bg-amber-500/20 px-2 py-0.5 rounded-full flex items-center gap-0.5 cursor-pointer transition border border-amber-500/20"
                                            title="Liberar mesa para relevo de descanso o cambio de turno"
                                        >
                                            <span class="material-symbols-outlined text-[12px]">pause_circle</span>
                                            <span>Liberar Relevo</span>
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-surface-container text-on-surface-variant border border-surface-container-high">
                                            <span class="material-symbols-outlined text-[13px]">badge</span>
                                            <span class="truncate max-w-[90px]">Atiende: {{ $mesa->mesero->name }}</span>
                                        </span>
                                        {{-- rol intencional, no permiso: espejo UI del guard de transferencia (sin ability en el catálogo) --}}
                                        @if(in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true))
                                            <button 
                                                type="button"
                                                wire:click="abrirModalTransferir({{ $mesa->id }})"
                                                class="text-[10px] font-extrabold text-primary hover:underline flex items-center gap-0.5 cursor-pointer"
                                                title="Transferir / Reasignar mesa"
                                            >
                                                <span class="material-symbols-outlined text-[12px]">sync_alt</span>
                                                <span>Transferir</span>
                                            </button>
                                        @endif
                                        {{-- Botón Cancelar Mesa: admin/gerente/cajero siempre; mesero solo su propia mesa --}}
                                        @if(in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true) || $mesa->mesero_id === Auth::id())
                                            <button 
                                                type="button"
                                                wire:click="abrirModalCancelar({{ $mesa->id }})"
                                                class="text-[10px] font-extrabold text-error hover:underline flex items-center gap-0.5 cursor-pointer"
                                                title="Cancelar mesa y liberar"
                                            >
                                                <span class="material-symbols-outlined text-[12px]">cancel</span>
                                                <span>Cancelar</span>
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            @elseif($mesa->estado === 'ocupada')
                                <div class="mt-1.5 flex items-center gap-1 flex-wrap">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/15 text-amber-800 border border-amber-500/30 animate-pulse">
                                        <span class="material-symbols-outlined text-[13px]">hourglass_empty</span>
                                        <span>En Relevo</span>
                                    </span>
                {{-- rol intencional, no permiso: tomar relevo = identidad del mesero --}}
                @if(Auth::user()?->role?->slug === 'mesero')
                                        <button 
                                            type="button"
                                            wire:click="autoasignarMesa({{ $mesa->id }})"
                                            class="text-[10px] font-extrabold text-white bg-primary hover:bg-primary-container px-2 py-0.5 rounded-full flex items-center gap-0.5 cursor-pointer transition shadow-xs"
                                            title="Tomar el relevo y continuar atendiendo comanda activa"
                                        >
                                            <span class="material-symbols-outlined text-[12px]">handshake</span>
                                            <span>+ Tomar Relevo</span>
                                        </button>
                                    {{-- rol intencional, no permiso: espejo UI del guard de transferencia (sin ability en el catálogo) --}}
                                    @elseif(in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true))
                                        <button 
                                            type="button"
                                            wire:click="abrirModalTransferir({{ $mesa->id }})"
                                            class="text-[10px] font-extrabold text-primary hover:underline flex items-center gap-0.5 cursor-pointer"
                                            title="Asignar mesero a esta mesa"
                                        >
                                            <span class="material-symbols-outlined text-[12px]">person_add</span>
                                            <span>Asignar</span>
                                        </button>
                                        <button 
                                            type="button"
                                            wire:click="abrirModalCancelar({{ $mesa->id }})"
                                            class="text-[10px] font-extrabold text-error hover:underline flex items-center gap-0.5 cursor-pointer"
                                            title="Cancelar mesa y liberar"
                                        >
                                            <span class="material-symbols-outlined text-[12px]">cancel</span>
                                            <span>Cancelar</span>
                                        </button>
                                    @endif
                                </div>
                            {{-- rol intencional, no permiso: auto-atender mesa = identidad del mesero --}}
                            @elseif(Auth::user()?->role?->slug === 'mesero')
                                <button 
                                    type="button"
                                    wire:click="autoasignarMesa({{ $mesa->id }})"
                                    class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-surface-container-high text-on-surface-variant hover:bg-primary hover:text-on-primary transition cursor-pointer border border-surface-container-highest"
                                    title="Autoasignarme esta mesa"
                                >
                                    <span class="material-symbols-outlined text-[12px]">person_add</span>
                                    <span>+ Atender Mesa</span>
                                </button>
                            {{-- rol intencional, no permiso: espejo UI del guard de transferencia (sin ability en el catálogo) --}}
                            @elseif(in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true))
                                <button 
                                    type="button"
                                    wire:click="abrirModalTransferir({{ $mesa->id }})"
                                    class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest transition cursor-pointer border border-surface-container-highest"
                                    title="Asignar mesero a esta mesa"
                                >
                                    <span class="material-symbols-outlined text-[12px]">person_add</span>
                                    <span>Asignar Mesero</span>
                                </button>
                            @endif
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="rounded-full border px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider {{ $badgeStyle }}">
                                {{ str_replace('_', ' ', $mesa->estado) }}
                            </span>
                            <span class="flex items-center gap-1 text-[11px] font-bold text-on-surface-variant">
                                <span class="material-symbols-outlined text-[14px]">group</span>
                                <span>{{ $mesa->capacidad }} pax</span>
                            </span>
                        </div>
                    </div>

                    <!-- Active Order Container (if occupied / QR pending) -->
                    @if($pedidoActivo && $pedidoActivo->estado === 'solicitado_qr' && !$pedidoActivo->usuario_id)
                        <div class="mt-3.5 rounded-2xl bg-amber-50 border border-amber-300/80 p-2.5 space-y-1.5 shadow-sm animate-pulse">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-amber-900 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">notifications_active</span>
                                    Pedido QR Recibido
                                </span>
                                <span class="text-[11px] font-mono font-black text-amber-900">${{ number_format($pedidoActivo->total, 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[10px] text-amber-800 font-medium">
                                {{ $pedidoActivo->nombre_cliente ?? 'Comensal' }} · {{ $pedidoActivo->items->count() }} platos
                            </p>
                            <button 
                                wire:click="atenderPedidoQr({{ $pedidoActivo->id }})"
                                class="w-full py-1.5 rounded-xl bg-primary text-on-primary text-[11px] font-black shadow-sm hover:bg-primary/90 active:scale-95 transition flex items-center justify-center gap-1 cursor-pointer"
                            >
                                <span class="material-symbols-outlined text-[14px]">handshake</span>
                                <span>⚡ Atender Mesa</span>
                            </button>
                        </div>
                    @elseif($pedidoActivo)
                        <div class="mt-3.5 rounded-2xl bg-surface-container-low p-2.5 text-xs border border-surface-container-high">
                            <div class="flex items-center justify-between font-bold">
                                <span class="text-on-surface font-mono">{{ $pedidoActivo->codigo }}</span>
                                <span class="text-primary font-mono font-extrabold">${{ number_format($pedidoActivo->total, 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[10px] font-medium">
                                @if($comandaListaServir)
                                    <span class="inline-flex items-center gap-1 font-black text-emerald-800 bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 px-2 py-0.5 rounded-full border border-emerald-300/60 animate-pulse">
                                        <span class="material-symbols-outlined text-[13px]">room_service</span>
                                        <span>🛎️ ¡Lista para Servir!</span>
                                    </span>
                                @elseif($comandaEnCocina)
                                    <span class="inline-flex items-center gap-1 font-bold text-amber-800 bg-amber-100 dark:bg-amber-950/60 dark:text-amber-300 px-2 py-0.5 rounded-full border border-amber-300/60">
                                        <span class="material-symbols-outlined text-[13px]">soup_kitchen</span>
                                        <span>⏳ En Cocina</span>
                                    </span>
                                @elseif(in_array($pedidoActivo->estado, ['servido', 'entregado']))
                                    <span class="inline-flex items-center gap-1 font-bold text-sky-800 bg-sky-100 dark:bg-sky-950/60 dark:text-sky-300 px-2 py-0.5 rounded-full border border-sky-300/60">
                                        <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                        <span>🍽️ Servido</span>
                                    </span>
                                @else
                                    <span class="text-on-surface-variant capitalize">Estado: {{ str_replace('_', ' ', $pedidoActivo->estado) }}</span>
                                @endif
                                <span class="text-on-surface-variant font-mono">{{ $pedidoActivo->items->count() }} items</span>
                            </div>
                            @if($pedidoActivo->usuario)
                                <div class="mt-1 text-[10px] text-on-surface-variant flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[12px] text-secondary">person</span>
                                    <span>Mesero: {{ $pedidoActivo->usuario->name }}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="mt-3.5 rounded-2xl border border-dashed border-surface-container-highest p-2 text-center text-[11px] text-on-surface-variant/70 font-medium">
                            Mesa disponible
                        </div>
                    @endif
                </div>

                <!-- Touch Interaction Button (Tactile 48px standard) -->
                <div class="mt-4 pt-3 border-t border-surface-container-high">
                    @if($pedidoActivo && $pedidoActivo->estado === 'solicitado_qr' && !$pedidoActivo->usuario_id)
                        <button 
                            wire:click="atenderPedidoQr({{ $pedidoActivo->id }})" 
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-primary text-xs font-extrabold text-on-primary shadow-sm hover:bg-primary-container transition-all active:scale-95 cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[18px]">handshake</span>
                            <span>⚡ Atender Pedido QR</span>
                        </button>
                    @elseif($mesa->estado === 'libre')
                        <a 
                            href="{{ route('pos', ['mesa_id' => $mesa->id]) }}" 
                            wire:navigate
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-primary text-xs font-extrabold text-on-primary shadow-sm hover:bg-primary-container transition-all active:scale-95"
                        >
                            <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                            <span>+ Abrir Comanda</span>
                        </a>
                    @elseif($mesa->estado === 'por_limpiar')
                        <button 
                            wire:click="cambiarEstado({{ $mesa->id }}, 'libre')" 
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-secondary text-xs font-extrabold text-on-secondary shadow-sm hover:bg-secondary-fixed-dim transition-all active:scale-95"
                        >
                            <span class="material-symbols-outlined text-[18px]">cleaning_services</span>
                            <span>✓ Marcar Limpia</span>
                        </button>
                    @elseif($mesa->estado === 'ocupada')
                        @if($comandaListaServir)
                            <a 
                                href="{{ route('pos', ['mesa_id' => $mesa->id]) }}" 
                                wire:navigate
                                class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-black text-white shadow-md transition-all active:scale-95 animate-pulse"
                            >
                                <span class="material-symbols-outlined text-[18px]">room_service</span>
                                <span>🛎️ ¡Lista! / Cobrar</span>
                            </a>
                        @elseif($comandaEnCocina)
                            <a 
                                href="{{ route('pos', ['mesa_id' => $mesa->id]) }}" 
                                wire:navigate
                                class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-amber-500/15 border border-amber-500/40 text-xs font-black text-amber-900 dark:text-amber-200 hover:bg-amber-500/25 transition-all active:scale-95"
                            >
                                <span class="material-symbols-outlined text-[18px] text-amber-600">soup_kitchen</span>
                                <span>⏳ En Cocina (Ver)</span>
                            </a>
                        @else
                            <a 
                                href="{{ route('pos', ['mesa_id' => $mesa->id]) }}" 
                                wire:navigate
                                class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container border border-primary/30 text-xs font-extrabold text-on-surface hover:bg-surface-container-high transition-all active:scale-95"
                            >
                                <span class="material-symbols-outlined text-[18px] text-primary">receipt_long</span>
                                <span>Ver / Cobrar</span>
                            </a>
                        @endif
                    @else
                        <button 
                            wire:click="cambiarEstado({{ $mesa->id }}, 'libre')" 
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-surface-container text-xs font-extrabold text-on-surface hover:bg-surface-container-high transition-all active:scale-95"
                        >
                            <span>Liberar Mesa</span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-3xl border border-dashed border-surface-container-highest p-12 text-center text-on-surface-variant">
                No se encontraron mesas con los filtros seleccionados.
            </div>
        @endforelse
    </div>
    @else

    {{-- Vista Mapa Gráfico: Plano Arquitectónico de Alta Fidelidad --}}
    @php
        $mesasPorZona = $mesas->groupBy('zona')->sortBy(fn ($mesasZona, $zona) => $zonasCatalogo[$zona]->orden ?? 99);

        // Panel de métricas ejecutivas en sala
        $mapOcupadas = $mesas->where('estado', 'ocupada');
        $mapTotal = $mesas->count();
        $mapPct = $mapTotal > 0 ? (int) round($mapOcupadas->count() / $mapTotal * 100) : 0;
        $mapComensales = 0;
        $mapMinutos = [];
        $mapVentaTotal = 0;

        foreach ($mapOcupadas as $mPlano) {
            $pPlano = $mPlano->pedidos->first();
            if ($pPlano) {
                $cantPlano = (int) $pPlano->items->sum('cantidad');
                $mapComensales += $cantPlano > 0 ? $cantPlano : $mPlano->capacidad;
                $mapVentaTotal += (float) ($pPlano->total ?? 0);
                if ($pPlano->created_at) {
                    $mapMinutos[] = max(0, (int) abs(now()->diffInMinutes($pPlano->created_at)));
                }
            }
        }
        $mapTiempoProm = count($mapMinutos) > 0 ? (int) round(array_sum($mapMinutos) / count($mapMinutos)) : 0;
    @endphp

    <div class="space-y-6" x-data="mapaMesas()">
        <!-- Banner Ejecutivo de Métricas en Vivo del Salón -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- Ocupación Salón -->
            <div class="relative overflow-hidden rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ocupación Salón</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-fixed text-primary">
                        <span class="material-symbols-outlined text-[18px]">pie_chart</span>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-mono text-2xl font-black text-on-surface">{{ $mapOcupadas->count() }}/{{ $mapTotal }}</span>
                    <span class="text-xs font-bold text-primary">({{ $mapPct }}%)</span>
                </div>
                <div class="mt-2 h-1.5 w-full rounded-full bg-surface-container-high overflow-hidden">
                    <div class="h-full rounded-full bg-primary transition-all duration-500" @style(['width: ' . $mapPct . '%'])></div>
                </div>
            </div>

            <!-- Comensales -->
            <div class="relative overflow-hidden rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant">Comensales en Sala</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-secondary-container/60 text-secondary">
                        <span class="material-symbols-outlined text-[18px]">groups</span>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-mono text-2xl font-black text-secondary">{{ $mapComensales }}</span>
                    <span class="text-xs font-semibold text-on-surface-variant">en mesa</span>
                </div>
                <p class="mt-2 text-[10px] font-medium text-on-surface-variant flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-secondary"></span>
                    <span>Capacidad comensales atendida</span>
                </p>
            </div>

            <!-- Ritmo de Servicio -->
            <div class="relative overflow-hidden rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant">Ritmo de Servicio</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                        <span class="material-symbols-outlined text-[18px]">timer</span>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-mono text-2xl font-black text-amber-800">{{ $mapTiempoProm }}</span>
                    <span class="text-xs font-semibold text-on-surface-variant">min / comanda</span>
                </div>
                <p class="mt-2 text-[10px] font-medium text-amber-800 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">bolt</span>
                    <span>{{ $mapTiempoProm > 45 ? 'Demora moderada' : 'Ritmo de rotación óptimo' }}</span>
                </p>
            </div>

            <!-- Venta Activa en Mesas -->
            <div class="relative overflow-hidden rounded-2xl border border-surface-container-highest bg-surface-container-lowest p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-on-surface-variant">Venta Activa en Sala</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800">
                        <span class="material-symbols-outlined text-[18px]">monetization_on</span>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-mono text-2xl font-black text-emerald-800">${{ number_format($mapVentaTotal, 0, ',', '.') }}</span>
                </div>
                <p class="mt-2 text-[10px] font-medium text-emerald-800 flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Subtotal en comandas abiertas</span>
                </p>
            </div>
        </div>

        <!-- Plano Arquitectónico del Restaurante -->
        <div class="grid grid-cols-1 {{ $filtroZona === 'todas' ? 'xl:grid-cols-2' : '' }} gap-6">
            @forelse ($mesasPorZona as $zona => $mesasZona)
                @php
                    $labelZona = $zonasCatalogo[$zona]->nombre ?? ucfirst(str_replace(['-', '_'], ' ', $zona));
                    $iconoZona = \App\Models\Zona::ICONOS[$zonasCatalogo[$zona]->icono ?? 'mesa'] ?? 'table_restaurant';
                    $tinteZona = \App\Models\Zona::PALETA[$zonasCatalogo[$zona]->color ?? 'pizarra']['tinte'] ?? 'bg-surface-container-high/60';
                    $puntoZona = \App\Models\Zona::PALETA[$zonasCatalogo[$zona]->color ?? 'pizarra']['punto'] ?? 'bg-outline-variant';
                    $libresZona = $mesasZona->where('estado', 'libre')->count();
                    $ocupadasZona = $mesasZona->where('estado', 'ocupada')->count();

                    $zonaTheme = match ($zona) {
                        'salon' => [
                            'border'       => 'border-rose-500/30 shadow-[0_4px_24px_rgba(244,63,94,0.15)]',
                            'headerBg'     => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                            'dot'          => 'bg-rose-400',
                            'door'         => '🚪 Entrada Principal',
                            'canvasBg'     => 'bg-[#1e0f0d]',
                            'gridPattern'  => 'bg-[linear-gradient(to_right,#f43f5e18_1px,transparent_1px),linear-gradient(to_bottom,#f43f5e18_1px,transparent_1px)] bg-[size:24px_24px]',
                            'badgeTone'    => 'bg-rose-500/20 text-rose-300 border-rose-500/40',
                            'floorLabel'   => 'ZONA SALÓN PRINCIPAL',
                        ],
                        'barra' => [
                            'border'       => 'border-amber-500/30 shadow-[0_4px_24px_rgba(245,158,11,0.15)]',
                            'headerBg'     => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                            'dot'          => 'bg-amber-400',
                            'door'         => '🍸 Pase de Barra',
                            'canvasBg'     => 'bg-[#1c1509]',
                            'gridPattern'  => 'bg-[linear-gradient(to_right,#f59e0b1a_1px,transparent_1px),linear-gradient(to_bottom,#f59e0b1a_1px,transparent_1px)] bg-[size:24px_24px]',
                            'badgeTone'    => 'bg-amber-500/20 text-amber-300 border-amber-500/40',
                            'floorLabel'   => 'ZONA BARRA &amp; COCKTAILS',
                        ],
                        'terraza' => [
                            'border'       => 'border-emerald-500/30 shadow-[0_4px_24px_rgba(16,185,129,0.15)]',
                            'headerBg'     => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                            'dot'          => 'bg-emerald-400',
                            'door'         => '🌿 Vista Exterior',
                            'canvasBg'     => 'bg-[#0d1c16]',
                            'gridPattern'  => 'bg-[linear-gradient(to_right,#10b9811a_1px,transparent_1px),linear-gradient(to_bottom,#10b9811a_1px,transparent_1px)] bg-[size:24px_24px]',
                            'badgeTone'    => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
                            'floorLabel'   => 'ZONA TERRAZA LOUNGE',
                        ],
                        'vip' => [
                            'border'       => 'border-purple-500/30 shadow-[0_4px_24px_rgba(139,92,246,0.15)]',
                            'headerBg'     => 'bg-purple-500/15 text-purple-300 border-purple-500/30',
                            'dot'          => 'bg-purple-400',
                            'door'         => '👑 Salón Privado',
                            'canvasBg'     => 'bg-[#140f1e]',
                            'gridPattern'  => 'bg-[linear-gradient(to_right,#8b5cf61a_1px,transparent_1px),linear-gradient(to_bottom,#8b5cf61a_1px,transparent_1px)] bg-[size:24px_24px]',
                            'badgeTone'    => 'bg-purple-500/20 text-purple-300 border-purple-500/40',
                            'floorLabel'   => 'SALA EXCLUSIVA VIP',
                        ],
                        default => [
                            'border'       => 'border-surface-container-highest/60 shadow-sm',
                            'headerBg'     => 'bg-surface-container/80 text-on-surface-variant border-surface-container-highest',
                            'dot'          => 'bg-on-surface-variant',
                            'door'         => '🚪 Acceso',
                            'canvasBg'     => 'bg-surface-dim',
                            'gridPattern'  => 'bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:24px_24px]',
                            'badgeTone'    => 'bg-surface-container text-on-surface-variant border-surface-container-highest',
                            'floorLabel'   => mb_strtoupper($labelZona, 'UTF-8'),
                        ],
                    };
                @endphp

                <section aria-label="Zona {{ $labelZona }}" class="flex flex-col rounded-3xl border {{ $zonaTheme['border'] }} bg-surface-container-lowest shadow-sm overflow-hidden transition-all">
                    <!-- Room Header -->
                    <div class="flex items-center justify-between border-b border-surface-container-highest px-5 py-4 bg-surface-container-low/60 backdrop-blur-xs">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl {{ $tinteZona }} border shadow-2xs">
                                <span class="material-symbols-outlined text-[20px]">{{ $iconoZona }}</span>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-sm font-black tracking-tight text-on-surface">{{ $labelZona }}</h2>
                                    <span class="h-2 w-2 rounded-full {{ $puntoZona }}"></span>
                                </div>
                                <p class="text-[11px] font-bold text-on-surface-variant">
                                    {{ $mesasZona->count() }} mesas · {{ $libresZona }} libres · {{ $ocupadasZona }} ocupadas
                                </p>
                            </div>
                        </div>
                        <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full border {{ $zonaTheme['badgeTone'] }} px-3 py-1 text-[11px] font-extrabold shadow-2xs">
                            {{ $zonaTheme['door'] }}
                        </span>
                    </div>

                    <!-- Room Floor Canvas -->
                    <div data-zona-drop="{{ $zona }}" class="relative min-h-[360px] flex-1 p-6 sm:p-8 {{ $zonaTheme['canvasBg'] }} transition-colors duration-300">
                        <!-- Architectural Subtle Tile Grid Background -->
                        <div class="pointer-events-none absolute inset-0 {{ $zonaTheme['gridPattern'] }} opacity-70"></div>
                        <div class="pointer-events-none absolute bottom-3 right-4 text-[9px] font-mono font-black uppercase tracking-widest text-on-surface-variant/30 select-none">
                            {{ $zonaTheme['floorLabel'] }}
                        </div>

                        <!-- Mesas Layout Grid con Simetría Arquitectónica -->
                        <div class="relative z-10 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-y-12 gap-x-6 justify-items-center items-start">
                            @foreach ($mesasZona as $mesa)
                                @php
                                    $pedidoActivo = $mesa->pedidos->first();
                                    $tieneCocinaPendiente = $pedidoActivo ? $pedidoActivo->items->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])->isNotEmpty() : false;
                                    $tieneCocinaLista = $pedidoActivo ? $pedidoActivo->items->where('estado_cocina', 'listo')->isNotEmpty() : false;
                                    $comandaListaServir = $pedidoActivo && ($pedidoActivo->estado === 'listo' || (! $tieneCocinaPendiente && $tieneCocinaLista));
                                    $comandaEnCocina = $pedidoActivo && ($tieneCocinaPendiente || in_array($pedidoActivo->estado, ['en_cocina', 'en_preparacion', 'en_proceso']));

                                    $mapaEstado = match ($mesa->estado) {
                                        'ocupada' => $comandaListaServir ? 'lista_servir' : ($comandaEnCocina ? 'en_cocina' : 'ocupada'),
                                        default => $mesa->estado,
                                    };

                                    $paxMesa = $pedidoActivo ? (int) $pedidoActivo->items->sum('cantidad') : 0;
                                    $paxMesa = $paxMesa > 0 ? $paxMesa : $mesa->capacidad;
                                    $minsMesa = ($pedidoActivo && $pedidoActivo->created_at) ? max(0, (int) abs(now()->diffInMinutes($pedidoActivo->created_at))) : 0;
                                    $esBooth = $mesa->capacidad >= 6;

                                    // Estilos refinados de mesa (Dark Charcoal mode)
                                    $tableStyles = match ($mapaEstado) {
                                        'libre' => [
                                            'disk'  => 'bg-gradient-to-b from-emerald-900/60 to-emerald-950/80 border-emerald-400/70 hover:border-emerald-400 shadow-emerald-900/20',
                                            'ring'  => 'hover:ring-4 hover:ring-emerald-500/30',
                                            'num'   => 'text-emerald-200',
                                            'badge' => 'bg-emerald-500 text-white',
                                            'label' => 'Libre',
                                            'icon'  => 'check_circle',
                                            'chair' => 'border-emerald-500/50 bg-emerald-800/60',
                                        ],
                                        'ocupada' => [
                                            'disk'  => 'bg-gradient-to-b from-rose-900/60 to-rose-950/80 border-rose-400/70 hover:border-rose-400 shadow-rose-900/20',
                                            'ring'  => 'hover:ring-4 hover:ring-rose-500/30',
                                            'num'   => 'text-rose-200',
                                            'badge' => 'bg-rose-500 text-white',
                                            'label' => 'Ocupada',
                                            'icon'  => 'restaurant',
                                            'chair' => 'border-rose-500/50 bg-rose-800/60',
                                        ],
                                        'en_cocina' => [
                                            'disk'  => 'bg-gradient-to-b from-amber-900/60 to-amber-950/80 border-amber-400/70 hover:border-amber-400 shadow-amber-900/20',
                                            'ring'  => 'hover:ring-4 hover:ring-amber-500/30',
                                            'num'   => 'text-amber-200',
                                            'badge' => 'bg-amber-500 text-white',
                                            'label' => 'En Cocina',
                                            'icon'  => 'soup_kitchen',
                                            'chair' => 'border-amber-500/50 bg-amber-800/60',
                                        ],
                                        'lista_servir' => [
                                            'disk'  => 'bg-gradient-to-b from-emerald-700/80 to-emerald-800/90 border-emerald-400 ring-4 ring-emerald-400/40 shadow-emerald-900/30',
                                            'ring'  => 'hover:ring-8 hover:ring-emerald-500/40',
                                            'num'   => 'text-emerald-100 font-black',
                                            'badge' => 'bg-emerald-400 text-emerald-950 animate-pulse',
                                            'label' => '¡Servir!',
                                            'icon'  => 'room_service',
                                            'chair' => 'border-emerald-400 bg-emerald-600/70',
                                        ],
                                        'cuenta_pedida' => [
                                            'disk'  => 'bg-gradient-to-b from-sky-900/60 to-sky-950/80 border-sky-400/70 hover:border-sky-400 shadow-sky-900/20',
                                            'ring'  => 'hover:ring-4 hover:ring-sky-500/30',
                                            'num'   => 'text-sky-200',
                                            'badge' => 'bg-sky-500 text-white',
                                            'label' => 'Cuenta',
                                            'icon'  => 'receipt_long',
                                            'chair' => 'border-sky-500/50 bg-sky-800/60',
                                        ],
                                        'por_limpiar', 'limpieza' => [
                                            'disk'  => 'bg-gradient-to-b from-surface-container to-surface-container-high border-outline/50 hover:border-outline shadow-sm',
                                            'ring'  => 'hover:ring-4 hover:ring-outline/20',
                                            'num'   => 'text-on-surface-variant',
                                            'badge' => 'bg-surface-container-highest text-on-surface-variant',
                                            'label' => 'Limpieza',
                                            'icon'  => 'cleaning_services',
                                            'chair' => 'border-outline/30 bg-surface-container/60',
                                        ],
                                        'reservada' => [
                                            'disk'  => 'bg-gradient-to-b from-purple-900/60 to-purple-950/80 border-purple-400/70 hover:border-purple-400 shadow-purple-900/20',
                                            'ring'  => 'hover:ring-4 hover:ring-purple-500/30',
                                            'num'   => 'text-purple-200',
                                            'badge' => 'bg-purple-500 text-white',
                                            'label' => 'Reservada',
                                            'icon'  => 'event',
                                            'chair' => 'border-purple-500/50 bg-purple-800/60',
                                        ],
                                        default => [
                                            'disk'  => 'bg-surface-container border-outline/40 shadow-xs',
                                            'ring'  => '',
                                            'num'   => 'text-on-surface',
                                            'badge' => 'bg-surface-container-highest text-on-surface-variant',
                                            'label' => ucfirst($mesa->estado),
                                            'icon'  => 'table_restaurant',
                                            'chair' => 'border-outline/30 bg-surface-container/60',
                                        ],
                                    };

                                    // Precalculo de ángulos simétricos para sillas en mesas redondas
                                    $numChairs = max(2, min($mesa->capacidad, 6));
                                    $chairAngles = match($numChairs) {
                                        2 => [0, 180],
                                        3 => [0, 120, 240],
                                        4 => [45, 135, 225, 315],
                                        5 => [0, 72, 144, 216, 288],
                                        default => [0, 60, 120, 180, 240, 300],
                                    };
                                @endphp

                                <div class="flex flex-col items-center select-none" wire:key="mapa-mesa-wrapper-{{ $mesa->id }}">
                                    <!-- Alerta Flotante: Plato Listo para Servir -->
                                    @if ($comandaListaServir)
                                        <div class="mb-1.5 inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2.5 py-0.5 text-[9px] font-black text-white shadow-sm animate-bounce">
                                            <span class="material-symbols-outlined text-[13px]">notifications_active</span>
                                            <span>¡Cocina Lista!</span>
                                        </div>
                                    @endif

                                    @if ($esBooth)
                                        <!-- Mesa Rectangular con Sillas Alineadas Arriba y Abajo -->
                                        <button
                                            type="button"
                                            wire:click="$set('mesaSeleccionadaId', {{ $mesa->id }})"
                                            data-mesa-id="{{ $mesa->id }}"
                                            data-zona-actual="{{ $mesa->zona }}"
                                            class="group relative flex h-32 w-48 sm:w-52 items-center justify-center cursor-pointer transition-transform duration-200 hover:-translate-y-1 active:scale-95"
                                            title="Mesa #{{ $mesa->numero }} · {{ $tableStyles['label'] }} · {{ $mesa->capacidad }} pax"
                                        >
                                            <!-- Fila Superior de Sillas Simétricas -->
                                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 flex items-center justify-center gap-3">
                                                @for ($c = 0; $c < 3; $c++)
                                                    <span aria-hidden="true" class="h-3 w-5 rounded-full border {{ $tableStyles['chair'] }} shadow-2xs"></span>
                                                @endfor
                                            </div>

                                            <!-- Fila Inferior de Sillas Simétricas -->
                                            <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 flex items-center justify-center gap-3">
                                                @for ($c = 0; $c < 3; $c++)
                                                    <span aria-hidden="true" class="h-3 w-5 rounded-full border {{ $tableStyles['chair'] }} shadow-2xs"></span>
                                                @endfor
                                            </div>

                                            <!-- Bancas Laterales para Booth si es VIP -->
                                            @if ($zona === 'vip')
                                                <span aria-hidden="true" class="absolute left-1 top-1/2 h-20 w-3 -translate-y-1/2 rounded-lg border {{ $tableStyles['chair'] }} shadow-xs"></span>
                                                <span aria-hidden="true" class="absolute right-1 top-1/2 h-20 w-3 -translate-y-1/2 rounded-lg border {{ $tableStyles['chair'] }} shadow-xs"></span>
                                            @endif

                                            <!-- Superficie Rectangular Squircle -->
                                            <div class="relative flex h-24 w-36 sm:w-42 flex-col items-center justify-center gap-1 rounded-2xl border-2 {{ $tableStyles['disk'] }} {{ $tableStyles['ring'] }} shadow-md transition-all {{ $mesa->id === $mesaSeleccionadaId ? 'ring-4 ring-primary ring-offset-2' : '' }}">
                                                <span class="text-xs font-mono font-black {{ $tableStyles['num'] }}">#{{ $mesa->numero }}</span>
                                                <span class="inline-flex items-center gap-0.5 rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $tableStyles['badge'] }} shadow-2xs">
                                                    <span class="material-symbols-outlined text-[10px]">{{ $tableStyles['icon'] }}</span>
                                                    <span>{{ $tableStyles['label'] }}</span>
                                                </span>
                                                <span class="text-[9px] font-bold text-on-surface-variant">👥 {{ $mesa->capacidad }} pax</span>
                                            </div>

                                            @if ($mesa->mesero_id === Auth::id())
                                                <span class="absolute -right-1 -top-1 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-white shadow-md ring-2 ring-surface-container-lowest" title="Atendida por ti">
                                                    <span class="material-symbols-outlined text-[14px]">person</span>
                                                </span>
                                            @endif
                                        </button>
                                    @else
                                        <!-- Mesa Redonda con Sillas Radiales en Simetría Perfecta -->
                                        <button
                                            type="button"
                                            wire:click="$set('mesaSeleccionadaId', {{ $mesa->id }})"
                                            data-mesa-id="{{ $mesa->id }}"
                                            data-zona-actual="{{ $mesa->zona }}"
                                            class="group relative flex h-32 w-32 items-center justify-center cursor-pointer transition-transform duration-200 hover:-translate-y-1 active:scale-95"
                                            title="Mesa #{{ $mesa->numero }} · {{ $tableStyles['label'] }} · {{ $mesa->capacidad }} pax"
                                        >
                                            <!-- Sillas Radiales Sencillas & Elegantes con Pre-cálculo PHP -->
                                            @foreach ($chairAngles as $chairAngle)
                                                <span aria-hidden="true" 
                                                      class="absolute left-1/2 top-1/2 -ml-3 -mt-1.5 h-3.5 w-6 rounded-full border {{ $tableStyles['chair'] }} shadow-2xs transition-colors"
                                                      @style(['transform: rotate(' . $chairAngle . 'deg) translateY(-48px)'])>
                                                </span>
                                            @endforeach

                                            <!-- Superficie Circular de la Mesa -->
                                            <div class="relative flex h-24 w-24 flex-col items-center justify-center gap-1 rounded-full border-2 {{ $tableStyles['disk'] }} {{ $tableStyles['ring'] }} shadow-md transition-all {{ $mesa->id === $mesaSeleccionadaId ? 'ring-4 ring-primary ring-offset-2' : '' }}">
                                                <span class="text-xs font-mono font-black {{ $tableStyles['num'] }}">#{{ $mesa->numero }}</span>
                                                <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $tableStyles['badge'] }} shadow-2xs">
                                                    <span class="material-symbols-outlined text-[10px]">{{ $tableStyles['icon'] }}</span>
                                                    <span>{{ $tableStyles['label'] }}</span>
                                                </span>
                                                <span class="text-[9px] font-bold text-stone-400">👥 {{ $mesa->capacidad }} pax</span>
                                            </div>

                                            @if ($mesa->mesero_id === Auth::id())
                                                <span class="absolute right-0 top-0 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-white shadow-md ring-2 ring-white" title="Atendida por ti">
                                                    <span class="material-symbols-outlined text-[14px]">person</span>
                                                </span>
                                            @endif
                                        </button>
                                    @endif

                                    <!-- Tarjeta Mini-Ticket Inferior para Mesas con Comanda -->
                                    @if ($pedidoActivo && $mesa->estado === 'ocupada')
                                        <div class="mt-1.5 flex flex-col items-center rounded-xl border border-surface-container-highest bg-surface-container-low px-3 py-1 shadow-xs backdrop-blur-xs">
                                            <span class="font-mono text-[11px] font-black text-primary">${{ number_format($pedidoActivo->total, 0, ',', '.') }}</span>
                                            <div class="flex items-center gap-1.5 text-[9px] font-bold text-on-surface-variant">
                                                <span class="material-symbols-outlined text-[11px] text-tertiary">timer</span>
                                                <span>{{ $minsMesa }} min</span>
                                                <span>·</span>
                                                <span>{{ $paxMesa }} pax</span>
                                            </div>
                                        </div>
                                    @elseif ($mesa->estado === 'reservada' && $pedidoActivo?->usuario)
                                        <div class="mt-1.5 max-w-[120px] truncate rounded-xl border border-purple-500/40 bg-purple-900/50 px-2.5 py-1 text-center text-[10px] font-bold text-purple-300 shadow-2xs">
                                            {{ $pedidoActivo->usuario->name }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @empty
                <div class="col-span-full rounded-3xl border-2 border-dashed border-surface-container-highest bg-surface-container-lowest p-12 text-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant/40 mb-2">table_restaurant</span>
                    <p class="font-bold text-sm">No se encontraron mesas con los filtros seleccionados.</p>
                </div>
            @endforelse
        </div>

        <!-- Leyenda Elegante y Ayuda Táctil -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-surface-container-highest bg-surface-container-lowest px-4 py-3 shadow-xs">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-on-surface-variant">Estados:</span>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-on-surface">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Libre
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-on-surface">
                    <span class="h-2.5 w-2.5 rounded-full bg-primary"></span> Ocupada
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-on-surface">
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> En Cocina
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-on-surface">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-600 animate-pulse"></span> ¡Lista para Servir!
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-on-surface">
                    <span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span> Cuenta Pedida
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-on-surface">
                    <span class="h-2.5 w-2.5 rounded-full bg-stone-400"></span> Por Limpiar
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-on-surface">
                    <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span> Reservada
                </span>
            </div>
            <div class="flex items-center gap-1 text-[11px] font-bold text-primary">
                <span class="material-symbols-outlined text-[15px]">touch_app</span>
                <span>Toca una mesa para abrir comanda, consultar o cambiar estado</span>
            </div>
        </div>
    </div>

    {{-- Hoja de acciones de la mesa seleccionada (táctil, blancos ≥ 48px) --}}
    @if ($mesaSeleccionada)
        @php
            $mesaSel = $mesaSeleccionada;
            $pedidoActivo = $mesaSel->pedidos->first();
            $tieneCocinaPendiente = $pedidoActivo ? $pedidoActivo->items->whereIn('estado_cocina', ['pendiente', 'en_preparacion'])->isNotEmpty() : false;
            $tieneCocinaLista = $pedidoActivo ? $pedidoActivo->items->where('estado_cocina', 'listo')->isNotEmpty() : false;
            $comandaListaServir = $pedidoActivo && ($pedidoActivo->estado === 'listo' || (! $tieneCocinaPendiente && $tieneCocinaLista));
            $comandaEnCocina = $pedidoActivo && ($tieneCocinaPendiente || in_array($pedidoActivo->estado, ['en_cocina', 'en_preparacion', 'en_proceso']));
            $esQrPendiente = $pedidoActivo && $pedidoActivo->estado === 'solicitado_qr' && ! $pedidoActivo->usuario_id;
            $mapaEstado = match ($mesaSel->estado) {
                'ocupada' => $comandaListaServir ? 'lista_servir' : ($comandaEnCocina ? 'en_cocina' : 'ocupada'),
                default => $mesaSel->estado,
            };
            $mapaVisual = match ($mapaEstado) {
                'libre' => ['clase' => 'bg-secondary text-on-secondary border-secondary/30', 'icono' => 'check_circle', 'etiqueta' => 'Libre'],
                'ocupada' => ['clase' => 'bg-primary text-on-primary border-primary/40', 'icono' => 'restaurant', 'etiqueta' => 'Ocupada'],
                'en_cocina' => ['clase' => 'bg-amber-600 text-white border-amber-500/40', 'icono' => 'soup_kitchen', 'etiqueta' => 'En cocina'],
                'lista_servir' => ['clase' => 'bg-emerald-600 text-white border-emerald-500/50', 'icono' => 'room_service', 'etiqueta' => 'Lista para servir'],
                'por_limpiar', 'limpieza' => ['clase' => 'bg-status-cleaning text-white border-status-cleaning/40', 'icono' => 'cleaning_services', 'etiqueta' => 'Por limpiar'],
                'reservada' => ['clase' => 'bg-indigo-600 text-white border-indigo-500/40', 'icono' => 'event', 'etiqueta' => 'Reservada'],
                'cuenta_pedida' => ['clase' => 'bg-tertiary text-on-tertiary border-tertiary/30', 'icono' => 'receipt_long', 'etiqueta' => 'Cuenta pedida'],
                default => ['clase' => 'bg-surface-container text-on-surface border-surface-container-high', 'icono' => 'table_restaurant', 'etiqueta' => ucfirst($mesaSel->estado)],
            };
            $esValidaRolCentral = in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true);
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4 bg-scrim/70 backdrop-blur-sm animate-fade-in" wire:key="sheet-mesa-{{ $mesaSel->id }}">
            <button type="button" wire:click="$set('mesaSeleccionadaId', null)" aria-label="Cerrar acciones de la mesa {{ $mesaSel->numero }}" class="absolute inset-0 w-full h-full cursor-default"></button>
            <div class="relative w-full sm:max-w-md rounded-t-[2rem] sm:rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-4 sm:p-6 pt-2 sm:pt-3 shadow-2xl space-y-3 sm:space-y-4 max-h-[72vh] sm:max-h-[85vh] overflow-y-auto animate-fade-in">
                <button type="button" wire:click="$set('mesaSeleccionadaId', null)" aria-label="Desliza o toca para cerrar" title="Cerrar" class="mx-auto flex h-7 w-20 items-center justify-center cursor-pointer">
                    <span class="h-1.5 w-12 rounded-full bg-surface-container-highest"></span>
                </button>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-surface-container-low text-primary border border-primary/20 shadow-sm">
                            <span class="material-symbols-outlined text-[26px]">table_restaurant</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-on-surface-variant">Mesa</p>
                            <h3 class="font-mono text-xl font-black text-on-surface">#{{ $mesaSel->numero }}</h3>
                            <p class="text-[11px] font-bold text-on-surface-variant capitalize">{{ $mesaSel->zona }} · {{ $mesaSel->capacidad }} pax{{ $mesaSel->mesero ? ' · ' . $mesaSel->mesero->name : '' }}</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <button
                            type="button"
                            wire:click="$set('mesaSeleccionadaId', null)"
                            aria-label="Cerrar acciones de la mesa"
                            title="Cerrar"
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-surface-container text-on-surface-variant hover:text-on-surface active:scale-90 transition cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[22px]">close</span>
                        </button>
                        <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[10px] font-black capitalize {{ $mapaVisual['clase'] }}">
                            <span class="material-symbols-outlined text-[13px]">{{ $mapaVisual['icono'] }}</span>
                            {{ $mapaVisual['etiqueta'] }}
                        </span>
                    </div>
                </div>

                {{-- Acciones principales --}}
                <div class="grid grid-cols-2 gap-2.5 pt-1">
                    @if ($esQrPendiente)
                        <button wire:click="atenderPedidoQr({{ $pedidoActivo->id }}); $set('mesaSeleccionadaId', null)"
                            class="col-span-2 flex h-14 items-center justify-center gap-2 rounded-2xl bg-primary text-on-primary text-xs font-black shadow-md hover:bg-primary-container active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">handshake</span>
                            <span>⚡ Atender Pedido QR</span>
                        </button>
                    @elseif ($mesaSel->estado === 'libre')
                        <a href="{{ route('pos', ['mesa_id' => $mesaSel->id]) }}" wire:navigate
                            class="col-span-2 flex h-14 items-center justify-center gap-2 rounded-2xl bg-primary text-on-primary text-xs font-black shadow-md hover:bg-primary-container active:scale-95 transition">
                            <span class="material-symbols-outlined text-[20px]">add_shopping_cart</span>
                            <span>＋ Abrir Comanda</span>
                        </a>
                    @elseif (in_array($mesaSel->estado, ['por_limpiar', 'limpieza'], true))
                        <button wire:click="cambiarEstado({{ $mesaSel->id }}, 'libre'); $set('mesaSeleccionadaId', null)"
                            class="col-span-2 flex h-14 items-center justify-center gap-2 rounded-2xl bg-secondary text-on-secondary text-xs font-black shadow-md hover:bg-secondary-fixed-dim active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">cleaning_services</span>
                            <span>✓ Marcar Limpia</span>
                        </button>
                    @elseif ($mesaSel->estado === 'ocupada')
                        <a href="{{ route('pos', ['mesa_id' => $mesaSel->id]) }}" wire:navigate
                            class="col-span-2 flex h-14 items-center justify-center gap-2 rounded-2xl text-xs shadow-md active:scale-95 transition {{ $comandaListaServir ? 'bg-emerald-600 text-white font-black animate-pulse' : ($comandaEnCocina ? 'bg-amber-500/15 text-amber-900 dark:text-amber-200 border border-amber-500/40 font-black' : 'bg-surface-container text-on-surface border border-primary/30 font-extrabold') }}">
                            <span class="material-symbols-outlined text-[20px]">{{ $comandaListaServir ? 'room_service' : ($comandaEnCocina ? 'soup_kitchen' : 'receipt_long') }}</span>
                            <span>{{ $comandaListaServir ? '🛎️ ¡Lista! / Cobrar' : ($comandaEnCocina ? '⏳ En Cocina (Ver)' : 'Ver / Cobrar') }}</span>
                        </a>
                    @else
                        <button wire:click="cambiarEstado({{ $mesaSel->id }}, 'libre'); $set('mesaSeleccionadaId', null)"
                            class="col-span-2 flex h-14 items-center justify-center gap-2 rounded-2xl bg-surface-container text-on-surface text-xs font-extrabold active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">lock_open</span>
                            <span>Liberar Mesa</span>
                        </button>
                    @endif

                    {{-- Acciones secundarias --}}
                    <button wire:click="abrirModalQr({{ $mesaSel->id }}); $set('mesaSeleccionadaId', null)"
                        class="flex h-14 items-center justify-center gap-1.5 rounded-2xl bg-surface-container text-on-surface text-[11px] font-extrabold active:scale-95 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">qr_code_2</span>
                        <span>QR Auto-pedido</span>
                    </button>
                    @can('update', App\Models\Mesa::class)
                        <button wire:click="abrirModalEditarMesa({{ $mesaSel->id }}); $set('mesaSeleccionadaId', null)"
                            class="flex h-14 items-center justify-center gap-1.5 rounded-2xl bg-surface-container text-on-surface text-[11px] font-extrabold active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                            <span>Editar</span>
                        </button>
                    @endcan

                    @if ($mesaSel->mesero_id === Auth::id())
                        <button wire:click="liberarParaRelevo({{ $mesaSel->id }}); $set('mesaSeleccionadaId', null)" wire:confirm="¿Deseas liberar la Mesa #{{ $mesaSel->numero }} para un relevo?"
                            class="flex h-14 items-center justify-center gap-1.5 rounded-2xl border border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300 text-[11px] font-extrabold active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">pause_circle</span>
                            <span>Liberar Relevo</span>
                        </button>
                    @elseif ($esValidaRolCentral)
                        <button wire:click="abrirModalTransferir({{ $mesaSel->id }}); $set('mesaSeleccionadaId', null)"
                            class="flex h-14 items-center justify-center gap-1.5 rounded-2xl bg-surface-container text-on-surface text-[11px] font-extrabold active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">{{ $mesaSel->mesero ? 'swap_horiz' : 'assignment_ind' }}</span>
                            <span>{{ $mesaSel->mesero ? 'Transferir' : 'Asignar Mesero' }}</span>
                        </button>
                    @elseif (! $mesaSel->mesero_id)
                        <button wire:click="autoasignarMesa({{ $mesaSel->id }}); $set('mesaSeleccionadaId', null)"
                            class="flex h-14 items-center justify-center gap-1.5 rounded-2xl bg-primary/10 border border-primary/30 text-primary text-[11px] font-extrabold active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
                            <span>＋ Atender Mesa</span>
                        </button>
                    @endif

                    @if ($esValidaRolCentral)
                        <button wire:click="abrirModalCancelar({{ $mesaSel->id }}); $set('mesaSeleccionadaId', null)"
                            class="flex h-14 items-center justify-center gap-1.5 rounded-2xl bg-error-container/50 text-error text-[11px] font-extrabold active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">cancel</span>
                            <span>Cancelar Mesa</span>
                        </button>
                    @elseif ($mesaSel->mesero_id === Auth::id())
                        <button wire:click="abrirModalCancelar({{ $mesaSel->id }}); $set('mesaSeleccionadaId', null)"
                            class="flex h-14 items-center justify-center gap-1.5 rounded-2xl bg-error-container/50 text-error text-[11px] font-extrabold active:scale-95 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">cancel</span>
                            <span>Cancelar Mesa</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
    @endif

    <!-- Modal Crear / Editar Mesa -->
    @if ($modalMesaOpen)
        <div x-data @keydown.escape.window="$wire.set('modalMesaOpen', false)" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-scrim/60 backdrop-blur-sm animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-mesa-title" class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/30 space-y-5">
                <div class="flex items-center justify-between border-b border-outline-variant/20 pb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-secondary-container/50 flex items-center justify-center text-secondary border border-secondary/30">
                            <span class="material-symbols-outlined text-[20px]">table_restaurant</span>
                        </div>
                        <div>
                            <h2 id="modal-mesa-title" class="text-base font-extrabold text-on-surface">
                                {{ $mesaEditandoId ? "Editar Mesa #{$formMesa['numero']}" : "Crear Nueva Mesa" }}
                            </h2>
                            <p class="text-[11px] text-on-surface-variant">Configuración espacial del salón</p>
                        </div>
                    </div>
                    <button 
                        wire:click="$set('modalMesaOpen', false)"
                        class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface"
                        aria-label="Cerrar modal de mesa"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form wire:submit="guardarMesa" class="space-y-4">
                    <!-- Número de Mesa -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Identificador / Número de Mesa *</label>
                        <input 
                            type="text" 
                            wire:model="formMesa.numero" 
                            class="w-full h-11 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3.5 text-sm font-mono font-bold text-on-surface focus:border-primary focus:ring-1 focus:ring-primary"
                            placeholder="Ej. 11, B-02, T-05"
                            required
                        />
                        @error('formMesa.numero') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Zona del Local -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Zona del Local *</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (\App\Models\Zona::deSucursal($this->sucursalEnContexto())->activas()->orderBy('orden')->orderBy('nombre')->get() as $zonaOpcion)
                                <button
                                    type="button"
                                    wire:click="$set('formMesa.zona', '{{ $zonaOpcion->slug }}')"
                                    class="h-10 px-3 rounded-xl text-xs font-bold text-left border transition-all flex items-center justify-between
                                           {{ $formMesa['zona'] === $zonaOpcion->slug ? 'bg-primary/10 border-primary text-primary shadow-sm' : 'bg-surface-container-low border-outline-variant/30 text-on-surface-variant hover:bg-surface-container' }}"
                                >
                                    <span>{{ $zonaOpcion->nombre }}</span>
                                    @if($formMesa['zona'] === $zonaOpcion->slug)
                                        <span class="material-symbols-outlined text-[16px]">check</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        @error('formMesa.zona') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Capacidad comensales -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Capacidad de Comensales (Pax) *</label>
                        <div class="flex items-center gap-2">
                            @foreach ([2, 4, 6, 8, 12] as $pax)
                                <button 
                                    type="button"
                                    wire:click="$set('formMesa.capacidad', {{ $pax }})"
                                    class="flex-1 h-10 rounded-xl text-xs font-mono font-extrabold border transition-all
                                           {{ $formMesa['capacidad'] === $pax ? 'bg-secondary text-on-secondary border-secondary shadow-sm' : 'bg-surface-container-low border-outline-variant/30 text-on-surface-variant hover:bg-surface-container' }}"
                                >
                                    {{ $pax }}p
                                </button>
                            @endforeach
                        </div>
                        <input 
                            type="number" 
                            wire:model="formMesa.capacidad" 
                            min="1" 
                            max="30"
                            class="mt-2 w-full h-10 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 text-xs font-mono text-on-surface"
                            placeholder="Capacidad personalizada..."
                        />
                        @error('formMesa.capacidad') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Sucursal -->
                    @if(count($sucursales) > 1)
                        <div>
                            <label class="block text-xs font-bold text-on-surface mb-1">Sede / Sucursal *</label>
                            <select 
                                wire:model="formMesa.sucursal_id"
                                class="w-full h-11 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3.5 text-xs font-bold text-on-surface"
                            >
                                @foreach ($sucursales as $suc)
                                    <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="pt-3 border-t border-outline-variant/20 flex items-center justify-end gap-2">
                        <button 
                            type="button"
                            wire:click="$set('modalMesaOpen', false)"
                            class="h-10 px-4 rounded-xl text-xs font-bold bg-surface-container hover:bg-surface-container-high text-on-surface-variant"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            class="h-10 px-5 rounded-xl text-xs font-extrabold bg-primary hover:bg-primary-container text-on-primary shadow-sm"
                        >
                            {{ $mesaEditandoId ? "Actualizar Mesa" : "Guardar Mesa" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Ver / Imprimir Código QR de Mesa (Aura Gastro Expressive OS) -->
    @if ($modalQrOpen && $mesaQr)
        <div x-data @keydown.escape.window="$wire.set('modalQrOpen', false)" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-scrim/60 backdrop-blur-sm animate-fade-in print:p-0 print:bg-white print:static">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-qr-title" class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/30 space-y-5 print:border-none print:shadow-none print:p-0">
                <!-- Modal Header (hidden when printing) -->
                <div class="flex items-center justify-between border-b border-outline-variant/20 pb-4 print:hidden">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                            <span class="material-symbols-outlined text-[20px]">qr_code_2</span>
                        </div>
                        <div>
                            <h2 id="modal-qr-title" class="text-base font-extrabold text-on-surface">
                                Código QR · Mesa #{{ $mesaQr->numero }}
                            </h2>
                            <p class="text-[11px] text-on-surface-variant">Menú público y auto-pedido online</p>
                        </div>
                    </div>
                    <button 
                        wire:click="$set('modalQrOpen', false)"
                        class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface cursor-pointer"
                        aria-label="Cerrar modal de código QR"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Printable Table Stand Card (Soporte Acrílico de Mesa) -->
                <div id="stand-mesa-imprimible" class="rounded-3xl border-2 border-dashed border-stone-300 p-6 bg-white text-center space-y-4 shadow-sm print:border-solid print:border-stone-800 print:rounded-2xl">
                    <!-- Brand / Restaurant Header -->
                    <div class="space-y-1">
                        <div class="inline-flex items-center justify-center gap-1.5 px-3 py-1 rounded-full bg-stone-900 text-white text-[10px] font-black tracking-widest uppercase">
                            <span>🍽️ RESTOMASTER GOURMET</span>
                        </div>
                        <h3 class="text-3xl font-black text-stone-900 tracking-tight mt-1 font-mono">
                            MESA #{{ $mesaQr->numero }}
                        </h3>
                        <p class="text-[11px] text-stone-500 font-extrabold uppercase tracking-wider">
                            Zona {{ $mesaQr->zona }} · Capacidad {{ $mesaQr->capacidad }} pax
                        </p>
                    </div>

                    <!-- QR Code SVG Container -->
                    <div class="p-3 bg-white rounded-2xl border border-stone-200 inline-block shadow-inner">
                        {!! $qrSvg !!}
                    </div>

                    <!-- Scan Instructions -->
                    <div class="space-y-1 max-w-xs mx-auto">
                        <p class="text-xs font-black text-stone-900 leading-snug">
                            Escanea con tu celular para ver el menú y ordenar
                        </p>
                        <p class="text-[10px] text-stone-500">
                            Sin esperas · Tu comanda se envía directamente a sala y cocina.
                        </p>
                    </div>

                    <!-- Short URL Link -->
                    <div class="pt-2 border-t border-stone-100 font-mono text-[10px] text-stone-600 font-bold">
                        {{ $qrUrl }}
                    </div>
                </div>

                <!-- Modal Actions (hidden when printing) -->
                <div class="space-y-2 pt-1 print:hidden" x-data="{ copiado: false }">
                    <div class="flex items-center gap-2">
                        <button 
                            @click="navigator.clipboard.writeText('{{ $qrUrl }}'); copiado = true; setTimeout(() => copiado = false, 2500)"
                            class="flex-1 py-2.5 px-4 rounded-xl border border-stone-200 bg-surface-container-low text-xs font-extrabold text-on-surface hover:bg-surface-container flex items-center justify-center gap-1.5 transition cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[16px]">content_copy</span>
                            <span x-text="copiado ? '¡Enlace Copiado!' : 'Copiar Enlace Directo'"></span>
                        </button>
                        <a 
                            href="{{ $qrUrl }}" 
                            target="_blank"
                            class="py-2.5 px-3 rounded-xl border border-stone-200 bg-surface-container-low text-xs font-extrabold text-on-surface hover:bg-surface-container flex items-center justify-center transition"
                            title="Probar menú en pestaña nueva"
                        >
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                        </a>
                    </div>

                    <button 
                        onclick="window.print()"
                        class="w-full py-3 rounded-2xl bg-stone-900 text-white text-xs font-black shadow-lg hover:bg-stone-800 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">print</span>
                        <span>Imprimir Soporte de Mesa (Stand Acrílico)</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Transferir / Asignar Mesa -->
    {{-- rol intencional, no permiso: espejo UI del guard de transferencia (sin ability en el catálogo) --}}
    @if($modalTransferirOpen && in_array(Auth::user()?->role?->slug, ['admin', 'gerente', 'cajero'], true))
        @php
            $mesaParaTransferir = $mesas->firstWhere('id', $mesaTransferirId);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="w-full max-w-md bg-surface-container-lowest rounded-3xl p-6 shadow-2xl border border-surface-container-highest space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-surface-container-high">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-container text-on-primary-container">
                            <span class="material-symbols-outlined text-[20px]">swap_horiz</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-on-surface">Transferir / Asignar Mesa</h3>
                            <p class="text-xs text-on-surface-variant">
                                Mesa #{{ $mesaParaTransferir?->numero }} · Zona {{ ucfirst($mesaParaTransferir?->zona ?? '') }}
                            </p>
                        </div>
                    </div>
                    <button 
                        wire:click="$set('modalTransferirOpen', false)"
                        class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                @if($mesaParaTransferir?->mesero)
                    <div class="flex items-center justify-between p-3 rounded-2xl bg-surface-container-low border border-surface-container-high">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">badge</span>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Atendida actualmente por:</p>
                                <p class="text-xs font-black text-on-surface">{{ $mesaParaTransferir->mesero->name }}</p>
                            </div>
                        </div>
                        <button 
                            type="button"
                            wire:click="desasignarMesero({{ $mesaParaTransferir->id }}); $set('modalTransferirOpen', false)"
                            class="px-2.5 py-1 text-[11px] font-bold text-error bg-error-container/40 hover:bg-error-container rounded-xl transition cursor-pointer"
                        >
                            Liberar
                        </button>
                    </div>
                @else
                    <div class="p-3 rounded-2xl bg-secondary-container/30 border border-secondary/20 text-xs text-secondary font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">info</span>
                        <span>Esta mesa actualmente no tiene mesero asignado.</span>
                    </div>
                @endif

                <div class="space-y-2">
                    <label class="block text-xs font-black uppercase tracking-wider text-on-surface-variant">
                        Seleccionar Mesero Receptor:
                    </label>
                    <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                        @forelse($meserosDisponibles as $mesero)
                            <label class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition-all {{ $nuevoMeseroId === $mesero->id ? 'border-primary bg-primary/5 shadow-sm' : 'border-surface-container-high bg-surface-container-low hover:bg-surface-container' }}">
                                <div class="flex items-center gap-3">
                                    <input 
                                        type="radio" 
                                        wire:model.live="nuevoMeseroId" 
                                        value="{{ $mesero->id }}" 
                                        class="text-primary focus:ring-primary h-4 w-4"
                                    />
                                    <div>
                                        <p class="text-xs font-black text-on-surface">{{ $mesero->name }}</p>
                                        <p class="text-[10px] text-on-surface-variant">{{ $mesero->email }}</p>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">person</span>
                            </label>
                        @empty
                            <p class="text-xs text-on-surface-variant italic py-3 text-center">No hay otros meseros activos registrados.</p>
                        @endforelse
                    </div>
                    @error('nuevoMeseroId') 
                        <p class="text-xs text-error font-bold mt-1">{{ $message }}</p> 
                    @enderror
                </div>

                <div class="p-3 rounded-2xl bg-surface-container-low text-[11px] text-on-surface-variant flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-primary shrink-0 mt-0.5">verified_user</span>
                    <span>La transferencia es libre e inmediata. Se registrará la entrega y recepción en el log de auditoría del sistema.</span>
                </div>

                <div class="flex items-center gap-2 pt-2 border-t border-surface-container-high">
                    <button 
                        type="button"
                        wire:click="$set('modalTransferirOpen', false)"
                        class="flex-1 py-2.5 px-4 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-extrabold text-on-surface hover:bg-surface-container transition cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button 
                        type="button"
                        wire:click="ejecutarTransferenciaMesa"
                        class="flex-1 py-2.5 px-4 rounded-xl bg-primary text-on-primary text-xs font-black shadow hover:opacity-90 active:scale-98 transition flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[16px]">check</span>
                        <span>Confirmar</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Cancelar Mesa (Aura Gastro Expressive OS) --}}
    @if($modalCancelarOpen && $mesaCancelarId)
        @php
            $mesaACancelar = $mesas->firstWhere('id', $mesaCancelarId);
            $pedidoACancelar = $mesaACancelar?->pedidos->first();
            $tieneItemsEnCocina = $pedidoACancelar
                ? $pedidoACancelar->items->whereIn('estado_cocina', ['en_preparacion', 'listo'])->count()
                : 0;
        @endphp
        <div x-data @keydown.escape.window="$wire.set('modalCancelarOpen', false)" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-cancelar-title" class="w-full max-w-md bg-surface-container-lowest rounded-3xl p-6 shadow-2xl border border-error/20 space-y-5">
                {{-- Header --}}
                <div class="flex items-center justify-between pb-3 border-b border-outline-variant/20">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-error-container text-error">
                            <span class="material-symbols-outlined text-[22px]">cancel</span>
                        </div>
                        <div>
                            <h3 id="modal-cancelar-title" class="text-base font-black text-on-surface">Cancelar Mesa #{{ $mesaACancelar?->numero }}</h3>
                            <p class="text-[11px] text-on-surface-variant">Zona {{ ucfirst($mesaACancelar?->zona ?? '') }} · {{ $mesaACancelar?->capacidad }} pax</p>
                        </div>
                    </div>
                    <button wire:click="$set('modalCancelarOpen', false)" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Contexto de la mesa --}}
                @if($mesaACancelar?->mesero)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-surface-container-low border border-surface-container-high">
                        <span class="material-symbols-outlined text-[20px] text-on-surface-variant">badge</span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Mesero asignado</p>
                            <p class="text-xs font-black text-on-surface">{{ $mesaACancelar->mesero->name }}</p>
                        </div>
                    </div>
                @endif

                @if($pedidoACancelar)
                    <div class="p-3 rounded-2xl border {{ $tieneItemsEnCocina > 0 ? 'bg-error-container/20 border-error/30' : 'bg-amber-500/10 border-amber-400/30' }}">
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-[18px] {{ $tieneItemsEnCocina > 0 ? 'text-error' : 'text-amber-700' }} mt-0.5">{{ $tieneItemsEnCocina > 0 ? 'dangerous' : 'warning' }}</span>
                            <div>
                                @if($tieneItemsEnCocina > 0)
                                    <p class="text-xs font-black text-error">⛔ No se puede cancelar</p>
                                    <p class="text-[11px] text-error/80 mt-0.5">El pedido {{ $pedidoACancelar->codigo }} tiene <strong>{{ $tieneItemsEnCocina }} ítem(s) en cocina</strong>. Finaliza la preparación primero.</p>
                                @else
                                    <p class="text-xs font-black text-amber-900">Pedido activo sin procesar</p>
                                    <p class="text-[11px] text-amber-800 mt-0.5">El pedido <span class="font-mono font-black">{{ $pedidoACancelar->codigo }}</span> ({{ $pedidoACancelar->items->count() }} items · ${{ number_format($pedidoACancelar->total, 0, ',', '.') }}) será anulado.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="p-3 rounded-2xl bg-secondary-container/30 border border-secondary/20 text-xs text-secondary font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">info</span>
                        <span>No hay comandas activas. Solo se liberará al mesero y la mesa volverá a estado libre.</span>
                    </div>
                @endif

                {{-- Motivo (opcional) --}}
                @if(!$tieneItemsEnCocina)
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1.5">Motivo de cancelación <span class="text-on-surface-variant font-normal">(opcional)</span></label>
                        <textarea
                            wire:model="motivoCancelacion"
                            rows="2"
                            placeholder="Ej. Cliente se fue, error de apertura, mesa duplicada..."
                            class="w-full rounded-xl bg-surface-container-low border border-outline-variant/40 px-3.5 py-2.5 text-xs text-on-surface resize-none focus:border-primary focus:ring-1 focus:ring-primary"
                        ></textarea>
                    </div>

                    <div class="p-3 rounded-2xl bg-surface-container-low text-[11px] text-on-surface-variant flex items-start gap-2">
                        <span class="material-symbols-outlined text-[16px] text-primary shrink-0 mt-0.5">verified_user</span>
                        <span>Esta acción quedará registrada en el log de auditoría del sistema con tu nombre y la hora exacta.</span>
                    </div>

                    {{-- Botones de acción --}}
                    <div class="flex items-center gap-2 pt-2 border-t border-outline-variant/20">
                        <button
                            type="button"
                            wire:click="$set('modalCancelarOpen', false)"
                            class="flex-1 py-2.5 px-4 rounded-xl border border-surface-container-high bg-surface-container-low text-xs font-extrabold text-on-surface hover:bg-surface-container transition cursor-pointer"
                        >
                            No, mantener mesa
                        </button>
                        <button
                            type="button"
                            wire:click="confirmarCancelacion"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-60 cursor-wait"
                            class="flex-1 py-2.5 px-4 rounded-xl bg-error text-on-error text-xs font-black shadow hover:opacity-90 active:scale-98 transition flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[16px]">cancel</span>
                            <span>Sí, cancelar mesa</span>
                        </button>
                    </div>
                @else
                    {{-- Solo botón cerrar si tiene items en cocina --}}
                    <button
                        type="button"
                        wire:click="$set('modalCancelarOpen', false)"
                        class="w-full py-2.5 px-4 rounded-xl bg-surface-container text-xs font-extrabold text-on-surface hover:bg-surface-container-high transition cursor-pointer"
                    >
                        Entendido, cerrar
                    </button>
                @endif
            </div>
        </div>
    @endif

    <!-- Modal Gestionar Zonas (catálogo por sucursal) -->
    @if ($modalZonasOpen)
        <div x-data @keydown.escape.window="$wire.set('modalZonasOpen', false)" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-scrim/60 backdrop-blur-sm animate-fade-in">
            <div role="dialog" aria-modal="true" aria-labelledby="modal-zonas-title" class="w-full max-w-2xl rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/30 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-outline-variant/20 pb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                            <span class="material-symbols-outlined text-[20px]">map</span>
                        </div>
                        <div>
                            <h2 id="modal-zonas-title" class="text-base font-extrabold text-on-surface">
                                {{ $zonaEditandoId ? 'Editar Zona' : 'Gestionar Zonas' }}
                            </h2>
                            <p class="text-[11px] text-on-surface-variant">Catálogo de zonas del salón por sucursal</p>
                        </div>
                    </div>
                    <button
                        wire:click="$set('modalZonasOpen', false)"
                        class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface"
                        aria-label="Cerrar gestión de zonas"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                @php
                    $zonasGestion = \App\Models\Zona::deSucursal($this->sucursalEnContexto())->orderBy('orden')->orderBy('nombre')->get();
                    $conteoMesasPorZona = \App\Models\Mesa::where('sucursal_id', $this->sucursalEnContexto())->selectRaw('zona, count(*) as total')->groupBy('zona')->pluck('total', 'zona');
                @endphp

                <div class="space-y-2">
                    @forelse ($zonasGestion as $z)
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-surface-container-highest bg-surface-container-low/40 p-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="h-3 w-3 shrink-0 rounded-full {{ \App\Models\Zona::PALETA[$z->color]['punto'] ?? 'bg-outline-variant' }}"></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-extrabold text-on-surface truncate">{{ $z->nombre }}</p>
                                    <p class="text-[11px] text-on-surface-variant">{{ (int) ($conteoMesasPorZona[$z->slug] ?? 0) }} mesas · orden {{ $z->orden }}</p>
                                </div>
                                @if ($z->activa)
                                    <span class="shrink-0 rounded-full bg-secondary-container/40 border border-secondary/20 px-2 py-0.5 text-[10px] font-extrabold uppercase text-on-secondary-container">Activa</span>
                                @else
                                    <span class="shrink-0 rounded-full bg-surface-container-high px-2 py-0.5 text-[10px] font-extrabold uppercase text-on-surface-variant">Inactiva</span>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <button
                                    type="button"
                                    wire:click="iniciarEdicionZona({{ $z->id }})"
                                    class="inline-flex items-center gap-1 rounded-xl border border-surface-container-highest bg-surface-container px-2.5 py-1.5 text-xs font-bold text-on-surface hover:bg-surface-container-high transition-all"
                                    title="Editar zona"
                                >
                                    <span class="material-symbols-outlined text-[16px] text-primary">edit</span>
                                    <span>Editar</span>
                                </button>
                                @if ($z->activa)
                                    <button
                                        type="button"
                                        wire:click="alternarZona({{ $z->id }})"
                                        wire:confirm="¿Desactivar esta zona? Las mesas deben estar reasignadas."
                                        class="inline-flex items-center gap-1 rounded-xl px-2.5 py-1.5 text-xs font-bold transition-all bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 border border-amber-500/20"
                                        title="Desactivar zona"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">power_settings_new</span>
                                        <span>Desactivar</span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="alternarZona({{ $z->id }})"
                                        class="inline-flex items-center gap-1 rounded-xl px-2.5 py-1.5 text-xs font-bold transition-all bg-secondary/10 text-secondary hover:bg-secondary/20 border border-secondary/20"
                                        title="Reactivar zona"
                                    >
                                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                        <span>Activar</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-center py-4 text-xs text-on-surface-variant">No hay zonas registradas en esta sucursal.</p>
                    @endforelse
                </div>

                <form wire:submit="guardarZona" class="space-y-4 border-t border-outline-variant/20 pt-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Nombre de la Zona *</label>
                        <input
                            type="text"
                            wire:model="zonaForm.nombre"
                            class="w-full h-11 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3.5 text-sm font-bold text-on-surface focus:border-primary focus:ring-1 focus:ring-primary"
                            placeholder="Ej. Jardín, Primer Piso"
                            required
                        />
                        @error('zonaForm.nombre') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Color *</label>
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach (array_keys(\App\Models\Zona::PALETA) as $colorKey)
                                <button
                                    type="button"
                                    wire:click="$set('zonaForm.color', '{{ $colorKey }}')"
                                    title="{{ $colorKey }}"
                                    aria-label="Color {{ $colorKey }}"
                                    class="flex h-10 w-10 items-center justify-center rounded-xl border transition-all {{ $zonaForm['color'] === $colorKey ? 'border-primary ring-2 ring-primary/40 bg-primary/5' : 'border-outline-variant/30 bg-surface-container-low hover:bg-surface-container' }}"
                                >
                                    <span class="h-4 w-4 rounded-full {{ \App\Models\Zona::PALETA[$colorKey]['punto'] }}"></span>
                                </button>
                            @endforeach
                        </div>
                        @error('zonaForm.color') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-on-surface mb-1">Icono *</label>
                            <select
                                wire:model="zonaForm.icono"
                                class="w-full h-11 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3.5 text-xs font-bold text-on-surface"
                            >
                                @foreach (\App\Models\Zona::ICONOS as $iconoKey => $iconoSimbolo)
                                    <option value="{{ $iconoKey }}">{{ $iconoKey }}</option>
                                @endforeach
                            </select>
                            @error('zonaForm.icono') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-on-surface mb-1">Orden *</label>
                            <input
                                type="number"
                                wire:model="zonaForm.orden"
                                min="0"
                                max="99"
                                class="w-full h-11 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3.5 text-xs font-mono text-on-surface"
                            />
                            @error('zonaForm.orden') <span class="text-xs text-error font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        @if ($zonaEditandoId)
                            <button
                                type="button"
                                wire:click="abrirModalZonas"
                                class="h-10 px-4 rounded-xl text-xs font-bold bg-surface-container hover:bg-surface-container-high text-on-surface-variant"
                            >
                                Cancelar Edición
                            </button>
                        @endif
                        <button
                            type="submit"
                            class="h-10 px-5 rounded-xl text-xs font-extrabold bg-primary hover:bg-primary-container text-on-primary shadow-sm"
                        >
                            {{ $zonaEditandoId ? 'Actualizar Zona' : 'Guardar Zona' }}
                        </button>
                    </div>
                </form>

                <div class="flex items-center justify-end border-t border-outline-variant/20 pt-3">
                    <button
                        type="button"
                        wire:click="$set('modalZonasOpen', false)"
                        class="rounded-xl border border-surface-container-high bg-surface-container px-4 py-2 text-xs font-extrabold text-on-surface-variant hover:text-on-surface"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: GESTIÓN DE ROTACIÓN Y AUTO-ASIGNACIÓN DE MESEROS POR ZONA -->
    @if ($modalRotacionOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 overflow-y-auto">
            <div class="w-full max-w-4xl rounded-3xl bg-surface-container-lowest border border-outline-variant/30 p-6 shadow-2xl space-y-6 my-8">
                <!-- Encabezado Modal -->
                <div class="flex items-center justify-between border-b border-outline-variant/20 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500/15 border border-amber-500/30 text-amber-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">sync_alt</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-on-surface tracking-tight">
                                Rotación & Auto-Asignación de Meseros
                            </h3>
                            <p class="text-xs text-on-surface-variant">
                                Asigna meseros por zona de salón y configura la rotación automática o manual
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="$set('modalRotacionOpen', false)"
                        class="p-2 rounded-xl text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                <!-- Selector de Modo de Asignación / Algoritmo -->
                <div class="bg-surface-container-low rounded-2xl p-4 border border-outline-variant/20">
                    <span class="text-xs font-black uppercase tracking-wider text-on-surface-variant block mb-2.5">
                        Algoritmo de Rotación de Mesas
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <button
                            type="button"
                            wire:click="cambiarModoRotacion('round_robin')"
                            class="p-3.5 rounded-xl border text-left transition-all cursor-pointer {{ $modoRotacion === 'round_robin' ? 'border-primary bg-primary/10 ring-2 ring-primary/30 text-on-surface' : 'border-outline-variant/30 bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container' }}"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <span class="material-symbols-outlined text-lg text-primary">autorenew</span>
                                <span class="text-xs font-black text-on-surface">Round-Robin Equitativo</span>
                            </div>
                            <p class="text-[11px] leading-tight text-on-surface-variant">
                                Rota en orden secuencial garantizando el mismo número de comensales para cada mesero.
                            </p>
                        </button>

                        <button
                            type="button"
                            wire:click="cambiarModoRotacion('menor_carga')"
                            class="p-3.5 rounded-xl border text-left transition-all cursor-pointer {{ $modoRotacion === 'menor_carga' ? 'border-secondary bg-secondary/10 ring-2 ring-secondary/30 text-on-surface' : 'border-outline-variant/30 bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container' }}"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <span class="material-symbols-outlined text-lg text-secondary">balance</span>
                                <span class="text-xs font-black text-on-surface">Menor Carga de Trabajo</span>
                            </div>
                            <p class="text-[11px] leading-tight text-on-surface-variant">
                                Asigna la nueva mesa al mesero que tenga menos comandas y mesas activas en ese momento.
                            </p>
                        </button>

                        <button
                            type="button"
                            wire:click="cambiarModoRotacion('manual')"
                            class="p-3.5 rounded-xl border text-left transition-all cursor-pointer {{ $modoRotacion === 'manual' ? 'border-amber-500 bg-amber-500/10 ring-2 ring-amber-500/30 text-on-surface' : 'border-outline-variant/30 bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container' }}"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <span class="material-symbols-outlined text-lg text-amber-500">pan_tool</span>
                                <span class="text-xs font-black text-on-surface">Asignación Manual</span>
                            </div>
                            <p class="text-[11px] leading-tight text-on-surface-variant">
                                No autoasigna automáticamente; el mesero o capitán toma la mesa desde el POS o plano.
                            </p>
                        </button>
                    </div>
                </div>

                <!-- Formulario Rápido: Asignar Mesero a Zona -->
                <div class="bg-surface-container-low rounded-2xl p-4 border border-outline-variant/20">
                    <span class="text-xs font-black uppercase tracking-wider text-on-surface-variant block mb-2.5">
                        Asignar Mesero a una Zona de Servicio
                    </span>
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <select
                            wire:model="rotacionNuevoMeseroId"
                            class="w-full sm:flex-1 h-11 rounded-xl bg-surface-container-lowest border border-outline-variant/40 px-3 text-xs font-bold text-on-surface"
                        >
                            <option value="">Selecciona Mesero...</option>
                            @foreach ($meserosDisponibles as $mesero)
                                <option value="{{ $mesero->id }}">{{ $mesero->name }}</option>
                            @endforeach
                        </select>

                        <select
                            wire:model="rotacionNuevaZona"
                            class="w-full sm:flex-1 h-11 rounded-xl bg-surface-container-lowest border border-outline-variant/40 px-3 text-xs font-bold text-on-surface"
                        >
                            <option value="">Selecciona Zona...</option>
                            @foreach ($zonasCatalogo as $z)
                                <option value="{{ $z->slug }}">{{ $z->nombre }}</option>
                            @endforeach
                        </select>

                        <button
                            type="button"
                            wire:click="agregarMeseroARotacion"
                            class="w-full sm:w-auto h-11 px-5 rounded-xl bg-primary hover:bg-primary-container text-on-primary font-black text-xs uppercase tracking-wider shadow-sm flex items-center justify-center gap-1.5 shrink-0 transition-all cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-base">person_add</span>
                            <span>Asignar</span>
                        </button>
                    </div>
                </div>

                <!-- Cuadrícula de Zonas y Meseros en Turno -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @forelse ($zonasCatalogo as $slug => $zona)
                        @php
                            $rotacionesZona = $rotacionesPorZona[$slug] ?? collect();
                            $colorInfo = \App\Models\Zona::PALETA[$zona->color] ?? \App\Models\Zona::PALETA['terracota'];
                        @endphp
                        <div class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full {{ $colorInfo['punto'] }}"></span>
                                    <h4 class="font-black text-sm text-on-surface uppercase tracking-wide">
                                        {{ $zona->nombre }}
                                    </h4>
                                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant">
                                        {{ $rotacionesZona->count() }} mesero(s)
                                    </span>
                                </div>
                            </div>

                            <!-- Lista de Meseros en la Zona -->
                            <div class="space-y-1.5 min-h-[60px]">
                                @forelse ($rotacionesZona as $rot)
                                    <div class="flex items-center justify-between p-2.5 rounded-xl border border-outline-variant/20 bg-surface-container-low transition-all">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-2 h-2 rounded-full {{ $rot->activo ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            <div>
                                                <span class="text-xs font-bold text-on-surface block leading-tight">
                                                    {{ $rot->mesero?->name ?? 'Mesero' }}
                                                </span>
                                                <span class="text-[10px] text-on-surface-variant">
                                                    {{ $rot->activo ? 'En rotación activa' : 'En pausa / descanso' }}
                                                    @if ($rot->ultimo_asignado_en)
                                                        · Última: {{ $rot->ultimo_asignado_en->format('H:i') }}
                                                    @endif
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                wire:click="toggleActivoRotacion({{ $rot->id }})"
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer {{ $rot->activo ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500 hover:bg-emerald-500/20' : 'bg-slate-700/10 border-slate-600/30 text-slate-400 hover:bg-slate-700/20' }}"
                                            >
                                                {{ $rot->activo ? 'Pausar' : 'Activar' }}
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="removerMeseroDeRotacion({{ $rot->id }})"
                                                class="p-1 rounded-lg text-error hover:bg-error/10 transition-colors cursor-pointer"
                                                title="Remover de la zona"
                                            >
                                                <span class="material-symbols-outlined text-base">delete</span>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-4 text-xs text-on-surface-variant italic border border-dashed border-outline-variant/30 rounded-xl">
                                        Sin meseros asignados a esta zona.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="col-span-2 text-center py-8 text-on-surface-variant text-xs">
                            No hay zonas configuradas en esta sucursal.
                        </div>
                    @endforelse
                </div>

                <!-- Footer del Modal -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-outline-variant/20 pt-4">
                    <button
                        type="button"
                        wire:click="autoasignarMesasLibres"
                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-secondary/40 bg-secondary/10 hover:bg-secondary/20 text-secondary font-bold text-xs flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-base">auto_mode</span>
                        <span>Auto-asignar mesas sin mesero ahora</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('modalRotacionOpen', false)"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-surface-container hover:bg-surface-container-high border border-outline-variant/30 text-xs font-black text-on-surface transition-all cursor-pointer"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
window.mapaMesas = () => ({
    arrastrando: null,
    fantasma: null,
    temporizador: null,
    zonaDestino: null,

    iniciarArrastre(origen, mesaId) {
        this.cancelarArrastre();
        this.arrastrando = { mesaId, origen };
        origen.style.touchAction = 'none';
        this.temporizador = window.setTimeout(() => this.recoger(origen), 250);
    },

    recoger(origen) {
        this.fantasma = origen.cloneNode(true);
        Object.assign(this.fantasma.style, {
            position: 'fixed',
            zIndex: '9999',
            pointerEvents: 'none',
            opacity: '0.85',
            transform: 'scale(1.05)',
            margin: '0',
            left: '0px',
            top: '0px',
        });
        document.body.appendChild(this.fantasma);
        origen.classList.add('opacity-40');
    },

    moverFantasma(evento) {
        if (!this.fantasma) {
            return;
        }
        const punto = evento.touches && evento.touches[0] ? evento.touches[0] : evento;
        this.fantasma.style.left = (punto.clientX - 40) + 'px';
        this.fantasma.style.top = (punto.clientY - 40) + 'px';
        const bajo = document.elementFromPoint(punto.clientX, punto.clientY);
        const sala = bajo ? bajo.closest('[data-zona-drop]') : null;
        const slug = sala ? sala.getAttribute('data-zona-drop') : null;
        if (slug !== this.zonaDestino) {
            document.querySelectorAll('[data-zona-drop].ring-4').forEach((el) => el.classList.remove('ring-4', 'ring-white'));
            this.zonaDestino = slug;
            if (sala) {
                sala.classList.add('ring-4', 'ring-white');
            }
        }
    },

    soltar() {
        if (this.fantasma && this.zonaDestino && this.arrastrando
            && this.zonaDestino !== this.arrastrando.origen.getAttribute('data-zona-actual')) {
            this.$wire.call('moverMesaAZona', this.arrastrando.mesaId, this.zonaDestino);
        }
        this.cancelarArrastre();
    },

    cancelarArrastre() {
        window.clearTimeout(this.temporizador);
        if (this.arrastrando) {
            this.arrastrando.origen.style.touchAction = '';
            this.arrastrando.origen.classList.remove('opacity-40');
        }
        if (this.fantasma) {
            this.fantasma.remove();
        }
        document.querySelectorAll('[data-zona-drop].ring-4').forEach((el) => el.classList.remove('ring-4', 'ring-white'));
        this.arrastrando = null;
        this.fantasma = null;
        this.zonaDestino = null;
    },
});

let compMapa = null;
document.addEventListener('pointerdown', (e) => {
    const btn = e.target.closest ? e.target.closest('[data-mesa-id]') : null;
    if (!btn || e.button === 2) {
        compMapa = null;
        return;
    }
    const root = btn.closest('[x-data]');
    compMapa = root && window.Alpine && typeof window.Alpine.$data === 'function' ? window.Alpine.$data(root) : null;
    if (compMapa && compMapa.iniciarArrastre) {
        compMapa.iniciarArrastre(btn, Number(btn.getAttribute('data-mesa-id')));
    }
}, { passive: true });
document.addEventListener('pointermove', (e) => {
    if (compMapa && compMapa.moverFantasma) {
        compMapa.moverFantasma(e);
    }
}, { passive: true });
document.addEventListener('pointerup', () => {
    if (compMapa && compMapa.soltar) {
        compMapa.soltar();
    }
    compMapa = null;
});
document.addEventListener('pointercancel', () => {
    if (compMapa && compMapa.cancelarArrastre) {
        compMapa.cancelarArrastre();
    }
    compMapa = null;
});
</script>
@endpush
