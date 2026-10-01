<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-primary/10 border border-primary/25 flex items-center justify-center text-primary shadow-inner">
                <span class="material-symbols-outlined text-[24px]">account_circle</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Perfil de Usuario</h1>
                    <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">
                        {{ strtoupper(auth()->user()->role ?? 'USUARIO') }}
                    </span>
                </div>
                <p class="text-xs text-on-surface-variant font-medium mt-0.5">Gestión de credenciales, seguridad y datos personales</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Tarjeta: Información del Perfil -->
            <div class="p-6 sm:p-8 bg-surface-container-low border border-surface-container-highest rounded-2xl shadow-xl">
                <livewire:profile.update-profile-information-form />
            </div>

            <!-- Tarjeta: Actualizar Contraseña -->
            <div class="p-6 sm:p-8 bg-surface-container-low border border-surface-container-highest rounded-2xl shadow-xl">
                <livewire:profile.update-password-form />
            </div>

            <!-- Tarjeta: Trazabilidad y Gestión de Cuentas -->
            <div class="p-6 sm:p-8 bg-surface-container-low/70 border border-surface-container-highest rounded-2xl shadow-lg">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-secondary/10 border border-secondary/25 flex items-center justify-center text-secondary shrink-0 mt-0.5">
                        <span class="material-symbols-outlined text-[22px]">admin_panel_settings</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-on-surface">Baja y Gestión de Cuentas</h3>
                        <p class="mt-1 text-xs text-on-surface-variant leading-relaxed">
                            Para preservar la integridad de los turnos de caja, la trazabilidad fiscal y los registros de auditoría de RestoMaster, la auto-eliminación de usuarios está deshabilitada. Las solicitudes de baja o cambio de rol deben ser procesadas por el Administrador desde el módulo de configuración y equipo de trabajo.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

