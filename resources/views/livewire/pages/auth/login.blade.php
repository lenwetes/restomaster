<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function rellenarCredencial(string $email, ?string $password = null): void
    {
        $this->form->email = $email;
        $this->form->password = $password ?? (string) config('auth.demo_password', 'sushixpress2026');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        if (auth()->user()?->role?->slug === 'mesero') {
            session()->forget('url.intended');
            $this->redirect(route('pos', absolute: false), navigate: true);
            return;
        }

        if (in_array(auth()->user()?->role?->slug, ['cocina', 'barra'], true)) {
            session()->forget('url.intended');
            $this->redirect(route('cocina', absolute: false), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-lg my-6 px-4">
    <!-- Main Login Card -->
    <div class="bg-white border border-stone-200 rounded-3xl shadow-xl p-6 sm:p-10 relative overflow-hidden">
        
        <!-- Decorative subtle top accent line -->
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[#ff5436] to-transparent opacity-80"></div>

        <!-- Header: Logo & Branding -->
        <div class="text-center space-y-3 pb-6 border-b border-stone-100">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-stone-100 border border-[#ff5436]/30 p-2 shadow-md shadow-[#ff5436]/8 mb-1">
                <x-application-logo class="w-full h-full" />
            </div>
            <div>
                <div class="flex items-center justify-center gap-2">
                    <span class="text-xl sm:text-2xl font-black text-stone-900 tracking-tight">SUSHI<span class="text-[#ff5436]">XPRESS</span></span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-[#ff5436]/20 text-[#ff5436] border border-[#ff5436]/30">Gastro POS</span>
                </div>
                <p class="text-xs text-stone-500 font-medium mt-1">Terminal de Servicio, Cocina KDS y Gestión Operativa</p>
            </div>
        </div>

        <!-- Status Message Alert -->
                @if (session('status'))
            <div class="my-4 p-3 rounded-2xl bg-[#ff5436]/10 border border-[#ff5436]/30 text-xs font-bold text-[#ff5436] flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">info</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Login Form -->
        <form wire:submit="login" class="mt-6 space-y-4">
            <!-- Email -->
            <div>
                <label for="email" class="block text-xs font-extrabold uppercase tracking-wider text-stone-600">
                    Correo Electrónico
                </label>
                <div class="relative mt-1.5">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-500">
                        <span class="material-symbols-outlined text-[18px]">mail</span>
                    </span>
                    <input 
                        wire:model="form.email" 
                        id="email" 
                        type="email" 
                        required 
                        autofocus 
                        autocomplete="username" 
                        placeholder="tu.usuario@sushixpress.com"
                        class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-stone-200 bg-stone-50 text-sm font-semibold text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-1 focus:ring-[#ff5436] outline-none transition-all"
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
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-xs font-extrabold uppercase tracking-wider text-stone-600">
                        Contraseña
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate class="text-xs text-stone-400 hover:text-[#ff5436] transition-colors">
                            ¿Olvidaste tu clave?
                        </a>
                    @endif
                </div>
                <div class="relative mt-1.5">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-500">
                        <span class="material-symbols-outlined text-[18px]">lock</span>
                    </span>
                    <input 
                        wire:model="form.password" 
                        id="password" 
                        :type="showPassword ? 'text' : 'password'" 
                        required 
                        autocomplete="current-password" 
                        placeholder="••••••••"
                        class="w-full pl-10 pr-11 py-2.5 rounded-2xl border border-stone-200 bg-stone-50 text-sm font-semibold text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-1 focus:ring-[#ff5436] outline-none transition-all font-mono"
                    />
                    <button 
                        type="button" 
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-stone-500 hover:text-stone-300 cursor-pointer"
                        tabindex="-1"
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
                        class="rounded-lg border-stone-300 bg-white text-[#ff5436] shadow-sm focus:ring-[#ff5436] focus:ring-offset-0 w-4 h-4 cursor-pointer"
                    >
                    <span class="ms-2 text-xs font-medium text-stone-600">Mantener estación conectada</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="w-full py-3 px-4 rounded-2xl bg-[#ff5436] hover:bg-[#e0381d] text-white font-black text-sm shadow-lg shadow-[#ff5436]/25 hover:shadow-[#ff5436]/40 transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
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

        <!-- 1-Click Role Fillers (Demo / Test) -->
        <div class="mt-8 pt-6 border-t border-stone-100 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-black uppercase tracking-wider text-stone-400">Acceso Rápido por Rol</span>
                <span class="text-[10px] text-stone-500 font-mono">Clave: {{ config('auth.demo_password', 'sushixpress2026') }}</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <button 
                    type="button" 
                    wire:click="rellenarCredencial('admin@sushixpress.com')" 
                    class="p-2.5 rounded-xl bg-stone-50 hover:bg-amber-50 border border-stone-200 hover:border-amber-300 text-left transition-all group cursor-pointer"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm">👑</span>
                        <div>
                            <p class="text-xs font-black text-stone-800 group-hover:text-amber-600 transition-colors">Administrador</p>
                            <p class="text-[10px] text-stone-500 truncate">admin@sushixpress.com</p>
                        </div>
                    </div>
                </button>

                <button 
                    type="button" 
                    wire:click="rellenarCredencial('mesero@sushixpress.com')" 
                    class="p-2.5 rounded-xl bg-stone-50 hover:bg-red-50 border border-stone-200 hover:border-[#ff5436]/50 text-left transition-all group cursor-pointer"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm">🧑‍🍳</span>
                        <div>
                            <p class="text-xs font-black text-stone-800 group-hover:text-[#ff5436] transition-colors">Mesero / Salón</p>
                            <p class="text-[10px] text-stone-500 truncate">mesero@sushixpress.com</p>
                        </div>
                    </div>
                </button>

                <button 
                    type="button" 
                    wire:click="rellenarCredencial('cocina@sushixpress.com')" 
                    class="p-2.5 rounded-xl bg-stone-50 hover:bg-emerald-50 border border-stone-200 hover:border-emerald-400/50 text-left transition-all group cursor-pointer"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm">🔪</span>
                        <div>
                            <p class="text-xs font-black text-stone-800 group-hover:text-emerald-600 transition-colors">Cocina / Barra</p>
                            <p class="text-[10px] text-stone-500 truncate">cocina@sushixpress.com</p>
                        </div>
                    </div>
                </button>

                <button 
                    type="button" 
                    wire:click="rellenarCredencial('caja@sushixpress.com')" 
                    class="p-2.5 rounded-xl bg-stone-50 hover:bg-sky-50 border border-stone-200 hover:border-sky-400/50 text-left transition-all group cursor-pointer"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm">💵</span>
                        <div>
                            <p class="text-xs font-black text-stone-800 group-hover:text-sky-600 transition-colors">Cajero / POS</p>
                            <p class="text-[10px] text-stone-500 truncate">caja@sushixpress.com</p>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Back to Customer Portal -->
        <div class="mt-6 pt-4 border-t border-stone-100 flex items-center justify-between text-xs">
            <a href="/" class="text-stone-500 hover:text-stone-800 transition-colors flex items-center gap-1 font-bold">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Portal de Clientes</span>
            </a>
            <a href="{{ route('carta.publico') }}" class="text-[#ff5436] hover:underline font-bold">
                Ver Menú Nikkei →
            </a>
        </div>

    </div>
</div>
