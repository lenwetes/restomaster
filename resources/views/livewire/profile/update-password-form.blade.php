<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class () extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <header class="border-b border-surface-container-highest/60 pb-4 mb-6">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-secondary text-[22px]">lock_reset</span>
            <h2 class="text-base font-bold text-on-surface">
                Actualizar Contraseña
            </h2>
        </div>

        <p class="mt-1 text-xs text-on-surface-variant font-medium">
            Asegúrate de que tu cuenta utilice una contraseña segura para proteger tus turnos y registros.
        </p>
    </header>

    <form wire:submit="updatePassword" class="space-y-6">
        <div>
            <x-input-label for="update_password_current_password" value="Contraseña Actual" />
            <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Nueva Contraseña" />
            <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="Mínimo 8 caracteres" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Confirmar Nueva Contraseña" />
            <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="Repite la contraseña" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4 pt-2">
            <x-primary-button>Guardar Cambios</x-primary-button>

            <x-action-message class="me-3 text-secondary font-bold text-xs" on="password-updated">
                ✓ Contraseña actualizada correctamente.
            </x-action-message>
        </div>
    </form>
</section>
