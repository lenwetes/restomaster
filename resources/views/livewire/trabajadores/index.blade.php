<?php

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\PermisoService;
use App\Services\TrabajadorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $mostrarModalNuevo = false;

    public bool $mostrarModalEditar = false;

    public ?int $enEdicion = null;

    public bool $mostrarClaveTemporal = false;

    public ?string $claveTemporal = null;

    public ?string $clavePara = null;

    public array $nuevo = [
        'nombre' => '',
        'email' => '',
        'telefono' => '',
        'password' => '',
        'role_id' => null,
        'sucursal_id' => null,
        'activo' => true,
    ];

    public array $edicion = [
        'nombre' => '',
        'email' => '',
        'telefono' => '',
        'password' => '',
        'role_id' => null,
        'sucursal_id' => null,
        'activo' => true,
    ];

    private function autorizarAdmin(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user?->role?->slug === 'admin', 403);
    }

    public function abrirModalNuevo(): void
    {
        $this->nuevo = [
            'nombre' => '',
            'email' => '',
            'telefono' => '',
            'password' => '',
            'role_id' => null,
            'sucursal_id' => null,
            'activo' => true,
        ];
        $this->mostrarModalNuevo = true;
    }

    public function guardarNuevo(): void
    {
        $this->autorizarAdmin();

        $this->validate([
            'nuevo.nombre' => 'required|string|min:3',
            'nuevo.email' => 'required|email|unique:users,email',
            'nuevo.telefono' => 'required|string|min:7|max:20',
            'nuevo.password' => 'required|min:6',
            'nuevo.role_id' => 'required|exists:roles,id',
            'nuevo.sucursal_id' => 'nullable|exists:sucursales,id',
        ], [
            'nuevo.telefono.required' => 'El número de móvil o teléfono es obligatorio.',
            'nuevo.email.unique' => 'Ya existe un trabajador registrado con este correo.',
        ]);

        try {
            $nuevoUsuario = app(TrabajadorService::class)->crear($this->nuevo);
            $rolNuevo = Role::find($this->nuevo['role_id']);
            app(PermisoService::class)->aplicarPlantilla($nuevoUsuario, $rolNuevo->slug);
        } catch (InvalidArgumentException $e) {
            $this->addError('nuevo.email', $e->getMessage());

            return;
        }

        $this->mostrarModalNuevo = false;
        $this->dispatch('notificacion', [
            'mensaje' => 'Trabajador creado correctamente.',
            'tipo' => 'success',
        ]);
    }

    public function abrirModalEditar(int $userId): void
    {
        $this->autorizarAdmin();

        $trabajador = User::findOrFail($userId);
        $this->enEdicion = $trabajador->id;
        $this->edicion = [
            'nombre' => $trabajador->name,
            'email' => $trabajador->email,
            'telefono' => $trabajador->telefono ?? '',
            'password' => '',
            'role_id' => $trabajador->role_id,
            'sucursal_id' => $trabajador->sucursal_id,
            'activo' => (bool) $trabajador->activo,
        ];
        $this->mostrarModalEditar = true;
    }

    public function guardarEdicion(): void
    {
        $this->autorizarAdmin();

        // Nadie puede quitarse a sí mismo el rol de administrador.
        abort_if(
            (int) $this->enEdicion === (int) Auth::id() && (int) $this->edicion['role_id'] !== (int) Auth::user()?->role_id,
            422,
            'No puedes cambiar tu propio rol de administrador.'
        );

        $this->validate([
            'edicion.nombre' => 'required|string|min:3',
            'edicion.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->enEdicion)],
            'edicion.telefono' => 'required|string|min:7|max:20',
            'edicion.role_id' => 'required|exists:roles,id',
            'edicion.sucursal_id' => 'nullable|exists:sucursales,id',
        ], [
            'edicion.telefono.required' => 'El número de móvil o teléfono es obligatorio.',
            'edicion.email.unique' => 'Ya existe otro trabajador registrado con este correo.',
        ]);

        try {
            $trabajador = User::findOrFail($this->enEdicion);

            if (! $this->edicion['activo'] && $trabajador->activo) {
                abort_if($trabajador->id === Auth::id(), 422, 'No puedes desactivarte a ti mismo.');
                if ($trabajador->role?->slug === 'admin') {
                    $otrosAdmins = User::where('id', '!=', $trabajador->id)
                        ->where('activo', true)
                        ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
                        ->count();
                    abort_if($otrosAdmins === 0, 422, 'No se puede desactivar al último administrador activo.');
                }
            }

            $trabajador = app(TrabajadorService::class)->actualizar(
                User::findOrFail($this->enEdicion),
                array_merge($this->edicion, [
                    'activo' => $this->edicion['activo'] ? true : false,
                ])
            );

            if (! $this->edicion['activo']) {
                app(TrabajadorService::class)->desactivar($trabajador);
            }
        } catch (InvalidArgumentException $e) {
            $this->addError('edicion.email', $e->getMessage());

            return;
        }

        $this->mostrarModalEditar = false;
        $this->dispatch('notificacion', [
            'mensaje' => 'Trabajador actualizado correctamente.',
            'tipo' => 'success',
        ]);
    }

    public function toggleActivo(int $userId): void
    {
        $this->autorizarAdmin();

        $trabajador = User::findOrFail($userId);

        // Proteger al último administrador activo (propio o ajeno).
        if ($trabajador->activo && $trabajador->role?->slug === 'admin') {
            $otrosAdmins = User::where('id', '!=', $trabajador->id)
                ->where('activo', true)
                ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
                ->count();
            abort_if($otrosAdmins === 0, 422, 'No se puede desactivar al último administrador activo.');
        }
        abort_if($trabajador->id === Auth::id() && $trabajador->activo, 422, 'No puedes desactivarte a ti mismo.');

        if ($trabajador->activo) {
            app(TrabajadorService::class)->desactivar($trabajador);
            $mensaje = 'Trabajador desactivado.';
        } else {
            app(TrabajadorService::class)->reactivar($trabajador);
            $mensaje = 'Trabajador reactivado.';
        }

        $this->dispatch('notificacion', ['mensaje' => $mensaje, 'tipo' => 'success']);
    }

    public function resetearClave(int $userId): void
    {
        $this->autorizarAdmin();

        $trabajador = User::findOrFail($userId);

        $this->claveTemporal = app(TrabajadorService::class)->resetearPassword($trabajador);
        $this->clavePara = $trabajador->name;
        $this->mostrarClaveTemporal = true;
    }

    public function ocultarClaveTemporal(): void
    {
        $this->mostrarClaveTemporal = false;
        $this->claveTemporal = null;
        $this->clavePara = null;
    }

    public ?int $permisosUserId = null;

    public ?int $permisosPlantillaRolId = null;

    public array $permisosChecks = [];

    public bool $mostrarModalPermisos = false;

    public function abrirModalPermisos(int $userId): void
    {
        $this->autorizarAdmin();
        abort_if($userId === Auth::id(), 403, 'No puedes modificar tus propios permisos.');

        $usuario = User::findOrFail($userId);
        $this->permisosUserId = $usuario->id;
        $this->permisosPlantillaRolId = $usuario->role_id;
        $this->permisosChecks = [];

        foreach (DB::table('permission_user')->where('user_id', $usuario->id)->pluck('tipo', 'permission')->all() as $key => $tipo) {
            $this->permisosChecks[$key] = $tipo === 'grant' ? 'otorgar' : 'quitar';
        }

        $this->mostrarModalPermisos = true;
    }

    public function aplicarPlantillaPermisos(): void
    {
        $this->autorizarAdmin();

        $rol = Role::find($this->permisosPlantillaRolId);
        abort_if(! $rol, 404);

        $this->permisosChecks = [];
        foreach (app(PermisoService::class)->plantilla($rol->slug) as $key) {
            $this->permisosChecks[$key] = 'otorgar';
        }
    }

    public function cambiarCheck(string $key, string $estado): void
    {
        $this->autorizarAdmin();

        if (! array_key_exists($key, config('permisos.catalogo', []))) {
            throw ValidationException::withMessages(['permisosChecks' => "Permiso desconocido: {$key}."]);
        }

        if (! in_array($estado, ['otorgar', 'heredar', 'quitar'], true)) {
            throw ValidationException::withMessages(['permisosChecks' => "Estado inválido: {$estado}."]);
        }

        $this->permisosChecks[$key] = $estado;
    }

    public function guardarPermisos(): void
    {
        $this->autorizarAdmin();
        abort_if($this->permisosUserId === Auth::id(), 403, 'No puedes modificar tus propios permisos.');

        $usuario = User::findOrFail($this->permisosUserId);

        $this->validate([
            'permisosChecks' => 'array',
            'permisosChecks.*' => 'in:otorgar,heredar,quitar',
        ]);

        foreach (array_keys($this->permisosChecks) as $key) {
            if (! array_key_exists($key, config('permisos.catalogo', []))) {
                throw ValidationException::withMessages(['permisosChecks' => "Permiso desconocido: {$key}."]);
            }
        }

        $diff = app(PermisoService::class)->guardarChecks($usuario, $this->permisosChecks);

        app(AuditoriaService::class)->registrar(
            accion: 'usuarios.permisos_actualizados',
            entidad: 'usuario',
            entidadId: $usuario->id,
            descripcion: "Permisos actualizados para {$usuario->name}.",
            datos: $diff,
        );

        $this->mostrarModalPermisos = false;
        $this->dispatch('notificacion', ['mensaje' => 'Permisos guardados correctamente.', 'tipo' => 'success']);
    }

    public function with(): array
    {
        return [
            'trabajadores' => User::with('role', 'sucursal')->orderBy('name')->get(),
            'roles' => Role::orderBy('nombre')->get(),
            'sucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary-container">badge</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">
                    Gestión de Trabajadores
                </h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">
                    TRB-01
                </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Alta, roles, activación y perfiles del personal del restaurante
            </p>
        </div>
        <button
            wire:click="abrirModalNuevo"
            class="inline-flex items-center gap-2 rounded-xl bg-primary-container px-4 py-2.5 text-xs font-black text-on-primary shadow-md shadow-amber-500/25 hover:bg-amber-500 active:scale-95 transition-all"
        >
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            <span>Nuevo Trabajador</span>
        </button>
    </header>

    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @if($mostrarClaveTemporal)
        <div class="rounded-3xl border border-secondary/40 bg-secondary-container/30 p-4 flex items-start justify-between gap-3 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 material-symbols-outlined text-[22px] text-secondary">key</span>
                <div>
                    <p class="text-sm font-extrabold text-on-surface">Contraseña temporal de {{ $clavePara }}</p>
                    <p class="text-[11px] text-on-surface-variant mt-0.5">
                        Se muestra una sola vez. Entrégala al trabajador; deberá cambiarla en su próximo inicio de sesión.
                    </p>
                    <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-surface-container-lowest border border-outline-variant/30 px-3 py-2">
                        <span class="font-mono text-sm font-black tracking-widest text-secondary break-all">{{ $claveTemporal }}</span>
                    </div>
                </div>
            </div>
            <button wire:click="ocultarClaveTemporal" class="rounded-xl p-1 text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
    @endif

    <div class="bg-surface-container-lowest rounded-3xl p-6 border border-outline-variant/20 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-extrabold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">groups</span>
                Plantilla del Personal ({{ $trabajadores->count() }})
            </h3>
            <span class="text-[10px] font-bold bg-secondary/15 px-2.5 py-1 rounded-full text-secondary border border-secondary/30">
                Solo Admin · RBAC
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-outline-variant/15 text-on-surface-variant uppercase text-[10px] tracking-wider bg-surface-container-low">
                        <th class="py-3 px-3">Trabajador</th>
                        <th class="py-3 px-3">Rol</th>
                        <th class="py-3 px-3">Sucursal</th>
                        <th class="py-3 px-3">Contacto</th>
                        <th class="py-3 px-3">Estado</th>
                        <th class="py-3 px-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10 font-medium">
                    @forelse($trabajadores as $trabajador)
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td class="py-3.5 px-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-fixed text-on-primary-fixed text-xs font-black uppercase border border-primary-fixed-dim">
                                        {{ substr($trabajador->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <span class="text-on-surface font-bold block">{{ $trabajador->name }}</span>
                                        <span class="text-[10px] font-mono text-on-surface-variant">#{{ $trabajador->id }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="px-2.5 py-1 rounded-full border text-[10px] font-extrabold uppercase {{ $trabajador->role?->slug === 'admin' ? 'bg-primary/10 text-primary border-primary/30' : 'bg-surface-container-high text-on-surface border-outline-variant/20' }}">
                                    {{ $trabajador->role?->nombre ?? 'Sin rol' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3 text-on-surface-variant">
                                <span class="inline-flex items-center gap-1 font-medium text-on-surface">
                                    <span class="material-symbols-outlined text-[14px] text-primary">{{ $trabajador->sucursal ? 'store' : 'storefront' }}</span>
                                    {{ $trabajador->sucursal?->nombre ?? 'Sin sucursal' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3 text-on-surface-variant">
                                <span class="block font-medium text-on-surface">{{ $trabajador->email }}</span>
                                <span class="text-[10px]">{{ $trabajador->telefono ?? '—' }}</span>
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ $trabajador->activo ? 'bg-secondary/15 text-secondary border border-secondary/30' : 'bg-error/15 text-error border border-error/30' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $trabajador->activo ? 'bg-secondary' : 'bg-error' }}"></span>
                                    {{ $trabajador->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        wire:click="abrirModalEditar({{ $trabajador->id }})"
                                        class="inline-flex items-center gap-1 rounded-xl border border-outline-variant/20 bg-surface-container-low px-3 py-1.5 text-[11px] font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">edit</span>
                                        Editar
                                    </button>

                                    @if(Auth::user()?->role?->slug === 'admin')
                                        <button
                                            wire:click="abrirModalPermisos({{ $trabajador->id }})"
                                            class="inline-flex items-center gap-1 rounded-xl border border-outline-variant/20 bg-surface-container-low px-3 py-1.5 text-[11px] font-bold text-on-surface hover:bg-surface-container-high active:scale-95 transition-all"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">key</span>
                                            Permisos
                                        </button>
                                    @endif

                                    @if($trabajador->id !== Auth::id())
                                        <button
                                            wire:click="resetearClave({{ $trabajador->id }})"
                                            class="inline-flex items-center gap-1 rounded-xl border border-outline-variant/20 bg-surface-container-low px-3 py-1.5 text-[11px] font-bold text-primary hover:bg-surface-container-high active:scale-95 transition-all"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">lock_reset</span>
                                            Resetear clave
                                        </button>
                                    @endif

                                    @if($trabajador->role?->slug !== 'admin')
                                        <button
                                            wire:click="toggleActivo({{ $trabajador->id }})"
                                            class="inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-[11px] font-bold active:scale-95 transition-all {{ $trabajador->activo ? 'bg-error/15 text-error border border-error/30 hover:bg-error/25' : 'bg-secondary/15 text-secondary border border-secondary/30 hover:bg-secondary/25' }}"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">
                                                {{ $trabajador->activo ? 'block' : 'check_circle' }}
                                            </span>
                                            {{ $trabajador->activo ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-on-surface-variant">
                                No hay trabajadores registrados todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($mostrarModalNuevo)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[22px] text-primary">person_add</span>
                        <h3 class="text-base font-extrabold text-on-surface">Nuevo Trabajador</h3>
                    </div>
                    <button wire:click="$set('mostrarModalNuevo', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre completo:</label>
                        <input type="text" wire:model="nuevo.nombre" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Ej: Ana María Restrepo" />
                        @error('nuevo.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Email:</label>
                            <input type="email" wire:model="nuevo.email" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="trabajador@restomaster.com" />
                            @error('nuevo.email') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Teléfono / Móvil <span class="text-primary">*</span>:</label>
                            <input type="text" wire:model="nuevo.telefono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="300 000 0000" />
                            @error('nuevo.telefono') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Rol:</label>
                            <select wire:model="nuevo.role_id" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                <option value="">Seleccionar...</option>
                                @foreach($roles as $rol)
                                    <option value="{{ $rol->id }}">{{ $rol->nombre }}</option>
                                @endforeach
                            </select>
                            @error('nuevo.role_id') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Contraseña inicial:</label>
                            <input type="password" wire:model="nuevo.password" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="••••••••" />
                            @error('nuevo.password') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Sucursal:</label>
                        <select wire:model="nuevo.sucursal_id" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                            <option value="">Sin sucursal</option>
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                        @error('nuevo.sucursal_id') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalNuevo', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface">
                        Cancelar
                    </button>
                    <button wire:click="guardarNuevo" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary/90">
                        ✓ Guardar Trabajador
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($mostrarModalPermisos)
        @php
            $gruposPermisos = [];
            foreach (config('permisos.catalogo', []) as $permisoKey => $meta) {
                $gruposPermisos[$meta['modulo'] ?? 'Otros'][$permisoKey] = $meta;
            }
            $filasActuales = $permisosUserId
                ? \Illuminate\Support\Facades\DB::table('permission_user')->where('user_id', $permisosUserId)->pluck('tipo', 'permission')->all()
                : [];
            $seranOtorgados = [];
            $seranQuitados = [];
            foreach ($permisosChecks as $permisoKey => $estado) {
                $tenia = $filasActuales[$permisoKey] ?? null;
                if ($estado === 'otorgar' && $tenia !== 'grant') {
                    $seranOtorgados[] = $permisoKey;
                } elseif ($estado === 'quitar' && $tenia !== 'deny') {
                    $seranQuitados[] = $permisoKey;
                } elseif ($estado === 'heredar' && $tenia !== null) {
                    $seranQuitados[] = $permisoKey;
                }
            }
            $tocaCriticas = count(array_intersect($seranQuitados, app(\App\Services\PermisoService::class)->criticas())) > 0;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[22px] text-primary">key</span>
                        <div>
                            <h3 class="text-base font-extrabold text-on-surface">Permisos del trabajador</h3>
                            <p class="text-[11px] text-on-surface-variant">Usuario #{{ $permisosUserId }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('mostrarModalPermisos', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row gap-2 sm:items-end">
                    <div class="flex-1">
                        <label class="text-xs font-bold text-on-surface-variant">Plantilla de rol:</label>
                        <select wire:model="permisosPlantillaRolId" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                            @foreach($roles as $rol)
                                <option value="{{ $rol->id }}">{{ $rol->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button wire:click="aplicarPlantillaPermisos" class="rounded-2xl border border-primary/30 bg-primary/10 px-4 py-2.5 text-xs font-extrabold text-primary hover:bg-primary/20 active:scale-95 transition-all">
                        Aplicar plantilla
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    @foreach($gruposPermisos as $modulo => $permisos)
                        <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-low p-3">
                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-on-surface-variant mb-2">{{ $modulo }}</h4>
                            <div class="space-y-2">
                                @foreach($permisos as $key => $meta)
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-xl bg-surface-container-lowest border border-outline-variant/15 px-3 py-2">
                                        <span class="text-xs font-bold text-on-surface">
                                            {{ $meta['label'] }}
                                            <span class="block text-[10px] font-mono font-medium text-on-surface-variant">{{ $key }}</span>
                                        </span>
                                        <div class="flex items-center gap-3 text-[11px] font-bold text-on-surface-variant">
                                            @foreach(['otorgar' => 'Otorgar', 'heredar' => 'Heredar', 'quitar' => 'Quitar'] as $valor => $etiqueta)
                                                <label class="inline-flex items-center gap-1 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="permiso-{{ $key }}"
                                                        value="{{ $valor }}"
                                                        @checked(($permisosChecks[$key] ?? 'heredar') === $valor)
                                                        wire:change="cambiarCheck('{{ $key }}', $event.target.value)"
                                                        class="accent-amber-500"
                                                    />
                                                    {{ $etiqueta }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="rounded-2xl border border-secondary/30 bg-secondary/10 p-3">
                        <p class="text-xs font-extrabold text-secondary">Serán otorgados ({{ count($seranOtorgados) }})</p>
                        @forelse($seranOtorgados as $key)
                            <span class="block text-[11px] font-mono text-secondary">{{ $key }}</span>
                        @empty
                            <span class="text-[11px] text-on-surface-variant">Sin cambios.</span>
                        @endforelse
                    </div>
                    <div class="rounded-2xl border border-error/30 bg-error/10 p-3">
                        <p class="text-xs font-extrabold text-error">Serán quitados ({{ count($seranQuitados) }})</p>
                        @forelse($seranQuitados as $key)
                            <span class="block text-[11px] font-mono text-error">{{ $key }}</span>
                        @empty
                            <span class="text-[11px] text-on-surface-variant">Sin cambios.</span>
                        @endforelse
                    </div>
                </div>

                @error('permisosChecks') <span class="text-xs text-error font-bold mt-2 block">{{ $message }}</span> @enderror

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalPermisos', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface">
                        Cancelar
                    </button>
                    <button
                        wire:click="guardarPermisos"
                        @if($tocaCriticas) onclick="return confirm('Vas a quitar permisos críticos (cobro, turnos, eliminaciones). ¿Continuar?')" @endif
                        class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary/90"
                    >
                        ✓ Guardar Permisos
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($mostrarModalEditar && $enEdicion)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/40 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl border border-outline-variant/20">
                <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[22px] text-primary">edit</span>
                        <h3 class="text-base font-extrabold text-on-surface">Editar Trabajador</h3>
                    </div>
                    <button wire:click="$set('mostrarModalEditar', false)" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Nombre completo:</label>
                        <input type="text" wire:model="edicion.nombre" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs text-on-surface focus:border-primary focus:ring-0" />
                        @error('edicion.nombre') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Email:</label>
                            <input type="email" wire:model="edicion.email" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" />
                            @error('edicion.email') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Teléfono / Móvil <span class="text-primary">*</span>:</label>
                            <input type="text" wire:model="edicion.telefono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" />
                            @error('edicion.telefono') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Rol:</label>
                            <select wire:model="edicion.role_id" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                                @foreach($roles as $rol)
                                    <option value="{{ $rol->id }}">{{ $rol->nombre }}</option>
                                @endforeach
                            </select>
                            @error('edicion.role_id') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-on-surface-variant">Contraseña (opcional):</label>
                            <input type="password" wire:model="edicion.password" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs text-on-surface focus:border-primary focus:ring-0" placeholder="Dejar en blanco" />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-on-surface-variant">Sucursal:</label>
                        <select wire:model="edicion.sucursal_id" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-2.5 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
                            <option value="">Sin sucursal</option>
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                        @error('edicion.sucursal_id') <span class="text-xs text-error font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-between rounded-2xl border border-outline-variant/20 bg-surface-container-low p-3">
                        <label class="text-xs font-bold text-on-surface-variant">Trabajador activo:</label>
                        <button wire:click="$set('edicion.activo', !$edicion['activo'])" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $edicion['activo'] ? 'bg-secondary' : 'bg-surface-container-high border border-outline-variant/20' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $edicion['activo'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button wire:click="$set('mostrarModalEditar', false)" class="rounded-2xl border border-outline-variant/30 bg-surface-container-high py-3 text-xs font-extrabold text-on-surface-variant hover:text-on-surface">
                        Cancelar
                    </button>
                    <button wire:click="guardarEdicion" class="rounded-2xl bg-primary py-3 text-xs font-black text-on-primary shadow-md hover:bg-primary/90">
                        ✓ Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>