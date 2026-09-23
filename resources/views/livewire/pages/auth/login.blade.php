<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function rellenarCredencial(string $email, ?string $password = null): void
    {
        $this->form->email = $email;
        $this->form->password = $password ?? (config('auth.demo_password') !== null ? (string) config('auth.demo_password') : '');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        if (Auth::user()?->role?->slug === 'mesero') {
            session()->forget('url.intended');
            $this->redirect(route('pos', absolute: false), navigate: true);
            return;
        }

        if (in_array(Auth::user()?->role?->slug, ['cocina', 'barra'], true)) {
            session()->forget('url.intended');
            $this->redirect(route('cocina', absolute: false), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-lg my-6 px-4">
    <!-- Main Harmonic Luxury Gastro POS Terminal Card -->
    <div class="bg-[#1a120e]/95 backdrop-blur-2xl border border-[#432f26] rounded-[36px] shadow-2xl p-7 sm:p-10 relative overflow-hidden text-[#f5e8e2]">
        
        <!-- Decorative subtle top accent line -->
        <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-[#e0442e] via-[#e8a020] to-[#2eb8b4]"></div>

        <!-- Header: Logo & Branding -->
        <div class="text-center space-y-3 pb-6 border-b border-[#432f26]/60">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#261a15] border border-[#e0442e]/30 p-2.5 shadow-lg shadow-black/20 mb-1">
                <x-application-logo class="w-full h-full text-[#e0442e]" />
            </div>
            <div>
                <div class="flex items-center justify-center gap-2">
                    <span class="text-2xl font-black text-white tracking-tight">RESTO<span class="text-[#e0442e]">MASTER</span></span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-[#e0442e]/15 text-[#ff7e67] border border-[#e0442e]/30 font-mono">Gastro POS</span>
                </div>
                <p class="text-xs text-[#c4a89e] font-medium mt-1">Terminal de Servicio, Cocina KDS y Gestión Gastronómica</p>
            </div>
        </div>

        <!-- Status Message Alert -->
        @if (session('status'))
            <div class="my-4 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-xs font-bold text-emerald-400 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-emerald-400">info</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Login Form -->
        <form wire:submit="login" class="mt-6 space-y-4">
            <!-- Email -->
            <div>
                <label for="email" class="block text-xs font-extrabold uppercase tracking-wider text-[#c4a89e] font-mono mb-1.5">
                    Correo Electrónico
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#7a5a52]">
                        <span class="material-symbols-outlined text-[18px]">mail</span>
                    </span>
                    <input 
                        wire:model="form.email" 
                        id="email" 
                        type="email" 
                        required 
                        autofocus 
                        autocomplete="username" 
                        placeholder="tu.usuario@restomaster.com"
                        class="w-full pl-10 pr-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-sm font-semibold text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none transition-all shadow-inner"
                    />
                </div>
                @error('form.email')
                    <p class="mt-1.5 text-xs text-rose-400 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <!-- Password -->
            <div x-data="{ showPassword: false }">
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-extrabold uppercase tracking-wider text-[#c4a89e] font-mono">
                        Contraseña
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate class="text-xs text-[#7a5a52] hover:text-[#e0442e] transition-colors font-medium">
                            ¿Olvidaste tu clave?
                        </a>
                    @endif
                </div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#7a5a52]">
                        <span class="material-symbols-outlined text-[18px]">lock</span>
                    </span>
                    <input 
                        wire:model="form.password" 
                        id="password" 
                        :type="showPassword ? 'text' : 'password'" 
                        required 
                        autocomplete="current-password" 
                        placeholder="••••••••"
                        class="w-full pl-10 pr-11 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-sm font-semibold text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none transition-all font-mono shadow-inner"
                    />
                    <button 
                        type="button" 
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#7a5a52] hover:text-white cursor-pointer"
                        tabindex="-1"
                        aria-label="Mostrar u ocultar contraseña"
                    >
                        <span class="material-symbols-outlined text-[18px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                    </button>
                </div>
                @error('form.password')
                    <p class="mt-1.5 text-xs text-rose-400 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between pt-1">
                <label for="remember" class="inline-flex items-center cursor-pointer select-none">
                    <input 
                        wire:model="form.remember" 
                        id="remember" 
                        type="checkbox" 
                        class="rounded-lg border-[#432f26] bg-[#261a15] text-[#e0442e] shadow-xs focus:ring-[#e0442e] focus:ring-offset-0 w-4 h-4 cursor-pointer"
                    >
                    <span class="ms-2 text-xs font-semibold text-[#c4a89e]">Mantener estación conectada</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="w-full py-3.5 px-4 rounded-2xl bg-[#e0442e] hover:bg-[#b8301d] text-white font-black text-sm shadow-xl shadow-[#e0442e]/30 hover:shadow-[#e0442e]/45 hover:scale-[1.01] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="login" class="flex items-center gap-2">
                        <span>Ingresar a mi Turno</span>
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </span>
                    <span wire:loading wire:target="login" class="flex items-center gap-2">
                        <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <span>Verificando credenciales...</span>
                    </span>
                </button>
            </div>
        </form>

        @if(app()->environment('local', 'testing') || config('auth.demo_password'))
            <!-- 1-Click Role Fillers (Demo / Test) with Vibrant Role Badges -->
            <div class="mt-8 pt-6 border-t border-[#432f26]/60 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-wider text-[#7a5a52] font-mono">Acceso Rápido por Rol</span>
                    <span class="text-[10px] text-[#7a5a52] font-mono">Clave demo prellenada</span>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <button 
                        type="button" 
                        wire:click="rellenarCredencial('admin@restomaster.com')" 
                        class="p-2.5 rounded-2xl bg-[#261a15] hover:bg-[#34241d] border border-[#432f26] hover:border-amber-400 text-left transition-all group cursor-pointer shadow-xs hover:scale-[1.02]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-base">👑</span>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-white group-hover:text-amber-400 transition-colors">Administrador</p>
                                <p class="text-[10px] text-[#7a5a52] truncate">admin@restomaster.com</p>
                            </div>
                        </div>
                    </button>

                    <button 
                        type="button" 
                        wire:click="rellenarCredencial('mesero@restomaster.com')" 
                        class="p-2.5 rounded-2xl bg-[#261a15] hover:bg-[#34241d] border border-[#432f26] hover:border-[#e0442e] text-left transition-all group cursor-pointer shadow-xs hover:scale-[1.02]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-base">🧑‍🍳</span>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-white group-hover:text-[#ff7e67] transition-colors">Mesero / Salón</p>
                                <p class="text-[10px] text-[#7a5a52] truncate">mesero@restomaster.com</p>
                            </div>
                        </div>
                    </button>

                    <button 
                        type="button" 
                        wire:click="rellenarCredencial('cocina@restomaster.com')" 
                        class="p-2.5 rounded-2xl bg-[#261a15] hover:bg-[#34241d] border border-[#432f26] hover:border-emerald-500 text-left transition-all group cursor-pointer shadow-xs hover:scale-[1.02]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-base">🔪</span>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-white group-hover:text-emerald-400 transition-colors">Cocina / Barra</p>
                                <p class="text-[10px] text-[#7a5a52] truncate">cocina@restomaster.com</p>
                            </div>
                        </div>
                    </button>

                    <button 
                        type="button" 
                        wire:click="rellenarCredencial('cajero@restomaster.com')" 
                        class="p-2.5 rounded-2xl bg-[#261a15] hover:bg-[#34241d] border border-[#432f26] hover:border-sky-500 text-left transition-all group cursor-pointer shadow-xs hover:scale-[1.02]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-base">💵</span>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-white group-hover:text-sky-400 transition-colors">Cajero / Caja</p>
                                <p class="text-[10px] text-[#7a5a52] truncate">cajero@restomaster.com</p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        @endif

        <!-- Back to Customer Portal -->
        <div class="mt-6 pt-4 border-t border-[#432f26]/60 flex items-center justify-between text-xs">
            <a href="/" class="text-[#c4a89e] hover:text-white transition-colors flex items-center gap-1 font-bold">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Portal del Restaurante</span>
            </a>
            <a href="{{ route('carta.publico') }}" class="text-[#e0442e] hover:underline font-bold">
                Ver Carta Digital →
            </a>
        </div>

    </div>
</div>
