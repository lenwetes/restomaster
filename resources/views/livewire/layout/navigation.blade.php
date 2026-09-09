<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div x-data="{ mobileMenuOpen: false }">
    <!-- Top Fixed App Bar (Aura Gastro Expressive OS) -->
    <header class="fixed top-0 left-0 right-0 h-16 bg-surface-container-lowest/90 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)] border-b border-surface-container-highest z-40 flex items-center justify-between px-4 lg:px-6 lg:left-64">
        <div class="flex items-center gap-3 min-w-0">
            <!-- Mobile Hamburger Toggle -->
            <button 
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="flex lg:hidden h-10 w-10 items-center justify-center rounded-xl bg-surface-container text-on-surface-variant hover:bg-surface-container-high focus:outline-none"
                aria-label="Abrir menú"
            >
                <span class="material-symbols-outlined text-[24px]">menu</span>
            </button>

            <!-- Branch Selector Pill -->
            <div class="flex items-center gap-1.5 bg-surface-container px-3 py-1.5 rounded-full cursor-pointer hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-primary text-[18px]">store</span>
                <span class="font-bold text-xs sm:text-sm text-on-surface">El Poblado MDE-01</span>
                <span class="material-symbols-outlined text-on-surface-variant text-[16px]">expand_more</span>
            </div>

            <!-- Shift / Cash Status Pill -->
            <div class="hidden sm:inline-flex items-center gap-1.5 bg-secondary-container/40 px-3 py-1 rounded-full border border-secondary/20">
                <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                <span class="text-xs font-bold text-on-secondary-container">Caja #01: Abierta</span>
            </div>

            <!-- DIAN Sync Badge -->
            <div class="hidden md:inline-flex items-center gap-1 bg-surface-container px-2.5 py-1 rounded-full">
                <span class="material-symbols-outlined text-secondary text-[16px]">verified</span>
                <span class="text-xs font-semibold text-on-surface-variant">Sincronizado DIAN</span>
            </div>
        </div>

        <!-- Right Side: Clock & Profile Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Clock display -->
            <div class="hidden sm:flex items-center gap-1.5 rounded-lg bg-surface-container-low px-3 py-1.5 text-xs text-on-surface border border-surface-container-highest font-mono">
                <span class="material-symbols-outlined text-on-surface-variant text-[16px]">schedule</span>
                <span x-data="{ time: new Date().toLocaleTimeString('es-CO', { hour12: false }) }" x-init="setInterval(() => time = new Date().toLocaleTimeString('es-CO', { hour12: false }), 1000)" x-text="time"></span>
                <span class="text-on-surface-variant text-[10px]">COT</span>
            </div>

            <!-- Notification bell -->
            <button class="relative p-2 rounded-full hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-colors" type="button">
                <span class="material-symbols-outlined text-[22px]">notifications</span>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-primary rounded-full"></span>
            </button>

            @auth
                <!-- User Profile Badge & Dropdown -->
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="inline-flex h-10 items-center gap-2 rounded-full bg-surface-container-low pl-2 pr-3 py-1 border border-surface-container-highest hover:bg-surface-container transition-colors">
                            <div class="w-7 h-7 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <div class="flex flex-col text-left hidden sm:flex">
                                <span class="text-xs font-bold text-on-surface leading-tight truncate max-w-[110px]">{{ auth()->user()->name }}</span>
                                <span class="text-[10px] text-primary font-semibold uppercase">{{ auth()->user()->role?->nombre ?? 'Chef Master' }}</span>
                            </div>
                            <span class="material-symbols-outlined text-on-surface-variant text-[16px]">expand_more</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs border-b border-surface-container-highest bg-surface-container-low">
                            <p class="font-bold text-on-surface">{{ auth()->user()->name }}</p>
                            <p class="text-on-surface-variant truncate">{{ auth()->user()->email }}</p>
                            <span class="mt-1 inline-block rounded bg-primary-container px-1.5 py-0.5 text-[10px] font-bold text-on-primary uppercase">
                                {{ auth()->user()->role?->nombre ?? 'Usuario' }}
                            </span>
                        </div>

                        <x-dropdown-link :href="route('profile')" wire:navigate class="flex items-center gap-2 text-xs py-2 text-on-surface">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">badge</span>
                            <span>Mi Perfil</span>
                        </x-dropdown-link>

                        <div class="border-t border-surface-container-highest"></div>

                        <button wire:click="logout" class="w-full text-start flex items-center gap-2 px-4 py-2 text-xs text-error hover:bg-error-container/20 transition">
                            <span class="material-symbols-outlined text-[18px]">logout</span>
                            <span>Cerrar Sesión</span>
                        </button>
                    </x-slot>
                </x-dropdown>
            @endauth
        </div>
    </header>

    <!-- Desktop Sidebar (Aura Gastro Expressive OS) -->
    <aside class="fixed inset-y-0 left-0 z-50 hidden w-64 flex-col justify-between bg-surface-container-lowest border-r border-surface-container-highest shadow-[0_1px_8px_rgba(0,0,0,0.04)] lg:flex">
        <div class="flex flex-col flex-1 overflow-y-auto">
            <!-- Brand Logo Header -->
            <div class="h-16 px-4 flex items-center gap-2.5 bg-surface-container-lowest border-b border-surface-container-highest/60">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-on-primary shadow-sm font-bold text-lg">
                    🍣
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-base text-primary leading-none tracking-tight">AURA GASTRO</span>
                    <span class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold">Colombia POS Enterprise</span>
                </div>
            </div>

            <!-- Subtitle Section -->
            <div class="px-4 py-2.5">
                <span class="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">Módulos de Servicio</span>
            </div>

            <!-- Navigation Links -->
            <nav class="flex flex-col gap-1 px-3">
                <!-- Dashboard (DASH-01) -->
                <a 
                    href="{{ route('dashboard') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">dashboard</span>
                        <span>Panel de Control</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">DASH</span>
                </a>

                <!-- Salón & Mesas (MES-01) -->
                <a 
                    href="{{ route('mesas') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('mesas') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">table_restaurant</span>
                        <span>Salón & Mesas</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">MES</span>
                </a>

                <!-- Terminal POS (POS-01) -->
                <a 
                    href="{{ route('pos') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('pos') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">point_of_sale</span>
                        <span>Terminal POS</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">POS</span>
                </a>

                <!-- Cocina KDS (COC-01) -->
                <a 
                    href="{{ route('cocina') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('cocina') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">skillet</span>
                        <span>Cocina (KDS)</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">COC</span>
                </a>

                <!-- Caja & Turnos (CAJ-01) -->
                <a 
                    href="{{ route('caja') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('caja') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">payments</span>
                        <span>Caja & Turno</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">CAJ</span>
                </a>

                <!-- Inventario (INV-01) -->
                <a 
                    href="{{ route('inventario') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('inventario') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">inventory_2</span>
                        <span>Inventario</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">INV</span>
                </a>

                <!-- Clientes VIP (CLI-01) -->
                <a 
                    href="{{ route('clientes') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('clientes') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">stars</span>
                        <span>Clientes VIP</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">CLI</span>
                </a>

                <!-- Despacho Delivery (PED-04) -->
                <a 
                    href="{{ route('delivery') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('delivery') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                        <span>Despacho Delivery</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">DLV</span>
                </a>

                <!-- Reservas (RES-01) -->
                <a 
                    href="{{ route('reservas') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('reservas') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">event</span>
                        <span>Reservas</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">RES</span>
                </a>

                <!-- Reportes DIAN (REP-01) -->
                <a 
                    href="{{ route('reportes') }}" 
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('reportes*') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">monitoring</span>
                        <span>Reportes DIAN</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">REP</span>
                </a>

                @if (auth()->user()?->role?->slug === 'admin')
                    <!-- Configuración (CFG-01) -->
                    <a 
                        href="{{ route('configuracion') }}" 
                        wire:navigate
                        class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('configuracion') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]">settings</span>
                            <span>Configuración</span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">CFG</span>
                    </a>
                @endif
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-3 bg-surface-container-low m-2 rounded-xl border border-surface-container-highest">
            <div class="flex items-center gap-2">
                <div class="w-2.5 h-2.5 rounded-full bg-secondary animate-pulse"></div>
                <div class="flex flex-col">
                    <span class="text-xs text-on-surface font-bold">Servidor Central MDE</span>
                    <span class="text-[10px] text-on-surface-variant">Ping 14ms • En Línea</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Mobile Navigation Drawer -->
    <div 
        x-show="mobileMenuOpen" 
        x-cloak
        class="fixed inset-0 z-50 flex lg:hidden"
        role="dialog" 
        aria-modal="true"
    >
        <div 
            x-show="mobileMenuOpen"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileMenuOpen = false" 
            class="fixed inset-0 bg-black/40 backdrop-blur-sm"
        ></div>

        <div 
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative flex w-full max-w-xs flex-1 flex-col bg-surface-container-lowest pb-4 pt-5 shadow-2xl"
        >
            <div class="flex items-center justify-between px-4 border-b border-surface-container-highest pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-on-primary font-bold shadow-sm">🍣</div>
                    <span class="font-bold text-base text-primary">AURA GASTRO</span>
                </div>
                <button 
                    @click="mobileMenuOpen = false"
                    class="rounded-xl p-1.5 text-on-surface-variant hover:bg-surface-container"
                >
                    <span class="material-symbols-outlined text-[22px]">close</span>
                </button>
            </div>

            <!-- Mobile Drawer Links -->
            <div class="mt-3 flex-1 space-y-1 overflow-y-auto px-3">
                <a 
                    href="{{ route('dashboard') }}" 
                    @click="mobileMenuOpen = false"
                    wire:navigate
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('dashboard') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">dashboard</span>
                        <span>Panel de Control</span>
                    </div>
                    <span class="text-[10px] font-bold">DASH</span>
                </a>

                <a 
                    href="{{ route('mesas') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('mesas') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">table_restaurant</span>
                        <span>Salón & Mesas</span>
                    </div>
                    <span class="text-[10px] font-bold">MES</span>
                </a>

                <a 
                    href="{{ route('pos') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('pos') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">point_of_sale</span>
                        <span>Terminal POS</span>
                    </div>
                    <span class="text-[10px] font-bold">POS</span>
                </a>

                <a 
                    href="{{ route('cocina') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('cocina') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">skillet</span>
                        <span>Cocina (KDS)</span>
                    </div>
                    <span class="text-[10px] font-bold">COC</span>
                </a>

                <a 
                    href="{{ route('caja') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('caja') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">payments</span>
                        <span>Caja & Turno</span>
                    </div>
                    <span class="text-[10px] font-bold">CAJ</span>
                </a>

                <a 
                    href="{{ route('inventario') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('inventario') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">inventory_2</span>
                        <span>Inventario & Recetas</span>
                    </div>
                    <span class="text-[10px] font-bold">INV</span>
                </a>

                <a 
                    href="{{ route('clientes') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('clientes') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">stars</span>
                        <span>Clientes VIP</span>
                    </div>
                    <span class="text-[10px] font-bold">CLI</span>
                </a>

                <a 
                    href="{{ route('delivery') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('delivery') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                        <span>Despacho Delivery</span>
                    </div>
                    <span class="text-[10px] font-bold">DLV</span>
                </a>

                <a 
                    href="{{ route('reservas') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('reservas') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">event</span>
                        <span>Reservas</span>
                    </div>
                    <span class="text-[10px] font-bold">RES</span>
                </a>

                <a 
                    href="{{ route('reportes') }}" 
                    @click="mobileMenuOpen = false" 
                    wire:navigate 
                    class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('reportes*') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">monitoring</span>
                        <span>Reportes DIAN</span>
                    </div>
                    <span class="text-[10px] font-bold">REP</span>
                </a>

                @if (auth()->user()?->role?->slug === 'admin')
                    <a 
                        href="{{ route('configuracion') }}" 
                        @click="mobileMenuOpen = false" 
                        wire:navigate 
                        class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('configuracion') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]">settings</span>
                            <span>Configuración</span>
                        </div>
                        <span class="text-[10px] font-bold">CFG</span>
                    </a>
                @endif
            </div>

            <!-- Drawer Logout -->
            @auth
                <div class="border-t border-surface-container-highest px-4 pt-3">
                    <button 
                        wire:click="logout" 
                        class="flex w-full items-center gap-2 rounded-xl py-2 text-sm font-bold text-error hover:bg-error-container/20 transition"
                    >
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        <span>Cerrar Sesión</span>
                    </button>
                </div>
            @endauth
        </div>
    </div>
</div>
