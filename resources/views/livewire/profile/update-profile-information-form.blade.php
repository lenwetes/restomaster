<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header class="border-b border-surface-container-highest/60 pb-4 mb-6">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-primary text-[22px]">badge</span>
            <h2 class="text-base font-bold text-on-surface">
                Información del Perfil
            </h2>
        </div>

        <p class="mt-1 text-xs text-on-surface-variant font-medium">
            Actualiza los datos personales de tu cuenta y tu dirección de correo electrónico corporativo.
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="space-y-6">
        <div>
            <x-input-label for="name" value="Nombre Completo" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Correo Electrónico" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (Auth::user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! Auth::user()->hasVerifiedEmail())
                <div class="mt-3 p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-200">
                    <p>
                        Tu dirección de correo electrónico no ha sido verificada.

                        <button wire:click.prevent="sendVerification" class="underline font-bold text-amber-400 hover:text-amber-300 ml-1">
                            Haz clic aquí para reenviar el correo de verificación.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-semibold text-xs text-secondary">
                            ✓ Se ha enviado un nuevo enlace de verificación a tu dirección de correo.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-2">
            <x-primary-button>Guardar Cambios</x-primary-button>

            <x-action-message class="me-3 text-secondary font-bold text-xs" on="profile-updated">
                ✓ Información guardada correctamente.
            </x-action-message>
        </div>
    </form>
</section>
