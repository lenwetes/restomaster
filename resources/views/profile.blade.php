<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[24px] text-primary">account_circle</span>
            <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Perfil de Usuario</h1>
            <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">USR-01</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-secondary text-[22px]">admin_panel_settings</span>
                        <h3 class="text-sm font-extrabold text-on-surface">Baja y Gestión de Cuentas</h3>
                    </div>
                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                        Para preservar la integridad de los turnos de caja, la trazabilidad fiscal y los registros de auditoría de SushiXpress, la auto-eliminación de usuarios está deshabilitada. Las solicitudes de baja o desactivación deben ser procesadas por el Administrador desde la configuración de trabajadores.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
