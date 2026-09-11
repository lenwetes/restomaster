<?php

use App\Livewire\Actions\Logout;
use App\Services\NotificacionService;
use App\Services\PedidoService;
use Livewire\Volt\Component;

new class extends Component
{
    public ?string $notificacionFlash = null;
    public ?string $tipoNotificacionFlash = 'info';

    /**
     * Tomar / Asignarse un pedido QR de mesa con control de concurrencia.
     */
    public function atenderPedidoQr(int $pedidoId): void
    {
        try {
            $pedidoService = app(PedidoService::class);
            $pedido = $pedidoService->asignarMeseroAPedidoQr($pedidoId, auth()->user());
            $this->notificacionFlash = "¡Has tomado la comanda de la Mesa #{$pedido->mesa?->numero}! Pedido en preparación.";
            $this->tipoNotificacionFlash = 'success';
            $this->dispatch('notificacion', [
                'mensaje' => $this->notificacionFlash,
                'tipo' => 'success',
            ]);
        } catch (\DomainException $e) {
            $this->notificacionFlash = $e->getMessage();
            $this->tipoNotificacionFlash = 'warning';
            $this->dispatch('notificacion', [
                'mensaje' => $this->notificacionFlash,
                'tipo' => 'warning',
            ]);
        } catch (\Throwable $e) {
            $this->notificacionFlash = "Error al atender pedido: " . $e->getMessage();
            $this->tipoNotificacionFlash = 'error';
        }
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function with(): array
    {
        $notificaciones = app(NotificacionService::class)->obtenerResumen(auth()->user());

        return [
            'notificaciones' => $notificaciones,
        ];
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
            <div class="flex items-center gap-1 sm:gap-1.5 bg-surface-container px-2.5 sm:px-3 py-1.5 rounded-full cursor-pointer hover:bg-surface-container-high transition-colors shrink-0">
                <span class="material-symbols-outlined text-primary text-[18px]">store</span>
                <span class="font-bold text-xs sm:text-sm text-on-surface truncate max-w-[85px] sm:max-w-none">El Poblado</span>
                <span class="font-bold text-xs text-on-surface hidden sm:inline">MDE-01</span>
                <span class="material-symbols-outlined text-on-surface-variant text-[16px]">expand_more</span>
            </div>

            @if(auth()->user()?->role?->slug === 'mesero')
                <!-- Mesero Active Badge -->
                <div class="inline-flex items-center gap-1.5 bg-primary/10 text-primary px-2 sm:px-3 py-1 rounded-full border border-primary/20 shrink-0">
                    <span class="material-symbols-outlined text-[16px]">room_service</span>
                    <span class="text-xs font-black hidden sm:inline">Mesero: {{ auth()->user()->name }}</span>
                    <span class="text-xs font-black sm:hidden">Mesero</span>
                    <span class="w-2 h-2 rounded-full bg-secondary animate-pulse ml-0.5" title="En servicio"></span>
                </div>
            @elseif(in_array(auth()->user()?->role?->slug, ['cocina', 'barra'], true))
                <!-- Cocina Active Badge -->
                <div class="inline-flex items-center gap-1.5 bg-secondary/15 text-secondary px-2 sm:px-3 py-1 rounded-full border border-secondary/30 shrink-0">
                    <span class="material-symbols-outlined text-[16px]">restaurant</span>
                    <span class="text-xs font-black hidden sm:inline">Cocina KDS: {{ auth()->user()->name }}</span>
                    <span class="text-xs font-black sm:hidden">Cocina</span>
                    <span class="w-2 h-2 rounded-full bg-secondary animate-pulse ml-0.5" title="En preparación"></span>
                </div>
            @else
                <!-- Shift / Cash Status Pill -->
                <div class="hidden sm:inline-flex items-center gap-1.5 bg-secondary-container/40 px-3 py-1 rounded-full border border-secondary/20 shrink-0">
                    <span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
                    <span class="text-xs font-bold text-on-secondary-container">Caja #01: Abierta</span>
                </div>

                <!-- DIAN Sync Badge -->
                <div class="hidden md:inline-flex items-center gap-1 bg-surface-container px-2.5 py-1 rounded-full shrink-0">
                    <span class="material-symbols-outlined text-secondary text-[16px]">verified</span>
                    <span class="text-xs font-semibold text-on-surface-variant">Sincronizado DIAN</span>
                </div>
            @endif
        </div>

        <!-- Right Side: Clock & Profile Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Clock display (12-hour format) -->
            <div class="hidden sm:flex items-center gap-1.5 rounded-lg bg-surface-container-low px-3 py-1.5 text-xs text-on-surface border border-surface-container-highest font-mono">
                <span class="material-symbols-outlined text-on-surface-variant text-[16px]">schedule</span>
                <span x-data="{ time: new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }) }" 
                      x-init="setInterval(() => time = new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }), 1000)" 
                      x-text="time"></span>
                <span class="text-on-surface-variant text-[10px]">COT</span>
            </div>

            <!-- Notification Bell & Interactive Dropdown (Aura Gastro Expressive OS) -->
            <div class="relative" x-data="{ openNotif: false }" wire:poll.10s>
                <button 
                    @click="openNotif = !openNotif" 
                    class="relative p-2 rounded-full hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer shrink-0" 
                    type="button"
                    title="Notificaciones operativas"
                    aria-label="Campana de notificaciones"
                >
                    <span class="material-symbols-outlined text-[22px]">notifications</span>
                    @if(($notificaciones['total'] ?? 0) > 0)
                        <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 bg-primary text-on-primary text-[10px] font-black rounded-full flex items-center justify-center shadow-sm animate-pulse">
                            {{ $notificaciones['total'] }}
                        </span>
                    @endif
                </button>

                <!-- Mobile Backdrop -->
                <div 
                    x-show="openNotif" 
                    @click="openNotif = false"
                    x-transition:enter="transition-opacity ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-stone-900/30 backdrop-blur-xs z-40 sm:hidden"
                    style="display: none;"
                ></div>

                <!-- Notifications Dropdown Panel -->
                <div 
                    x-show="openNotif" 
                    @click.outside="openNotif = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                    style="display: none;"
                    class="fixed sm:absolute inset-x-3 sm:inset-x-auto top-18 sm:top-auto sm:right-0 sm:mt-2 w-auto sm:w-96 rounded-3xl bg-surface-container-lowest p-4 shadow-2xl border border-surface-container-highest z-50 space-y-3 max-h-[calc(100vh-5.5rem)] sm:max-h-[550px] flex flex-col"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-2 border-b border-surface-container-high shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">notifications_active</span>
                            <span class="text-xs font-black uppercase tracking-wider text-on-surface">Notificaciones</span>
                        </div>
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-extrabold text-primary">
                            {{ $notificaciones['total'] ?? 0 }} activas
                        </span>
                    </div>

                    @if($notificacionFlash)
                        <div class="p-2.5 rounded-xl text-xs font-bold shrink-0 {{ $tipoNotificacionFlash === 'success' ? 'bg-secondary-container text-on-secondary-container' : 'bg-primary-container/30 text-primary' }}">
                            {{ $notificacionFlash }}
                        </div>
                    @endif

                    <div class="flex-1 overflow-y-auto space-y-2.5 pr-1 text-xs overscroll-contain">
                        <!-- 1. Pedidos QR por Asignar -->
                        @if(($notificaciones['pedidos_qr'] ?? collect())->isNotEmpty())
                            <div class="space-y-1.5">
                                <span class="text-[10px] font-black uppercase tracking-wider text-primary flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">qr_code_2</span>
                                    Pedidos QR de Mesa (Sin Asignar)
                                </span>
                                @foreach($notificaciones['pedidos_qr'] as $pqr)
                                    <div class="p-2.5 rounded-2xl bg-primary-container/15 border border-primary/25 flex items-center justify-between gap-2 shadow-sm">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-extrabold text-on-surface text-xs">
                                                    {{ $pqr->mesa?->numero ? 'Mesa #'.$pqr->mesa->numero : 'Autoservicio' }}
                                                </span>
                                                <span class="text-[10px] text-on-surface-variant truncate max-w-[120px]">({{ $pqr->nombre_cliente ?? 'Comensal' }})</span>
                                            </div>
                                            <p class="text-[10px] text-on-surface-variant font-mono mt-0.5">
                                                {{ $pqr->items->count() }} platos · ${{ number_format($pqr->total, 0, ',', '.') }} COP
                                            </p>
                                        </div>
                                        <button 
                                            wire:click="atenderPedidoQr({{ $pqr->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-primary text-on-primary text-[11px] font-black shadow-sm hover:bg-primary/90 active:scale-95 transition cursor-pointer shrink-0"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">handshake</span>
                                            <span>Atender</span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- 2. Platos Listos para Servir -->
                        @if(($notificaciones['platos_listos'] ?? collect())->isNotEmpty())
                            <div class="space-y-1.5 pt-1">
                                <span class="text-[10px] font-black uppercase tracking-wider text-secondary flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">room_service</span>
                                    Listos en Cocina / Barra
                                </span>
                                @foreach($notificaciones['platos_listos'] as $pl)
                                    <div class="p-2.5 rounded-2xl bg-secondary-container/20 border border-secondary/20 flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="material-symbols-outlined text-secondary text-[18px] shrink-0">check_circle</span>
                                            <div class="min-w-0">
                                                <div class="font-bold text-on-surface text-xs truncate">
                                                    @if($pl->pedido?->mesa?->numero)
                                                        Mesa #{{ $pl->pedido->mesa->numero }}
                                                    @elseif($pl->pedido?->tipo === 'barra')
                                                        Barra
                                                    @elseif($pl->pedido?->tipo === 'delivery')
                                                        Delivery
                                                    @else
                                                        Pedido #{{ $pl->pedido?->codigo ?? $pl->pedido_id }}
                                                    @endif
                                                </div>
                                                <p class="text-[11px] text-on-surface-variant truncate">
                                                    {{ $pl->cantidad }}x {{ $pl->nombre_producto }}
                                                </p>
                                            </div>
                                        </div>
                                        <span class="text-[10px] text-on-surface-variant font-mono whitespace-nowrap shrink-0">
                                            {{ $pl->listo_en ? \Carbon\Carbon::parse($pl->listo_en)->diffForHumans(null, true) : 'Listo' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- 3. Stock Crítico -->
                        @if(($notificaciones['stock_critico'] ?? collect())->isNotEmpty())
                            <div class="space-y-1.5 pt-1">
                                <span class="text-[10px] font-black uppercase tracking-wider text-tertiary flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">inventory_2</span>
                                    Insumos en Stock Bajo
                                </span>
                                @foreach($notificaciones['stock_critico'] as $st)
                                    <div class="p-2 rounded-2xl bg-tertiary-container/15 border border-tertiary/20 flex items-center justify-between gap-2">
                                        <span class="font-bold text-on-surface truncate min-w-0">{{ $st->nombre }}</span>
                                        <span class="font-mono text-[10px] text-tertiary font-extrabold shrink-0">{{ $st->stock_actual }} {{ $st->unidad_medida }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- 4. Reservas de Hoy -->
                        @if(($notificaciones['reservas_hoy'] ?? collect())->isNotEmpty())
                            <div class="space-y-1.5 pt-1">
                                <span class="text-[10px] font-black uppercase tracking-wider text-on-surface-variant flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">event_seat</span>
                                    Reservas de Hoy
                                </span>
                                @foreach($notificaciones['reservas_hoy'] as $res)
                                    <div class="p-2 rounded-2xl bg-surface-container-low border border-surface-container-high flex items-center justify-between gap-2">
                                        <span class="font-bold text-on-surface truncate min-w-0 text-[11px]">
                                            {{ substr($res->hora_llegada, 0, 5) }}: {{ $res->nombre_contacto }} ({{ $res->personas }}p)
                                        </span>
                                        <span class="text-[10px] font-extrabold capitalize text-on-surface-variant shrink-0">{{ $res->estado }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Estado vacío -->
                        @if(($notificaciones['total'] ?? 0) === 0)
                            <div class="py-6 text-center text-on-surface-variant space-y-1">
                                <span class="material-symbols-outlined text-[32px] text-secondary">task_alt</span>
                                <p class="font-bold">Todo al día</p>
                                <p class="text-[10px]">No hay pedidos ni alertas pendientes.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Footer -->
                    <div class="pt-2 border-t border-surface-container-high flex items-center justify-between shrink-0">
                        <a href="{{ route('mesas') }}" wire:navigate class="text-[11px] font-extrabold text-primary hover:underline">
                            Ver Salón & Mesas
                        </a>
                        <button @click="openNotif = false" class="text-[11px] font-bold text-on-surface-variant hover:text-on-surface cursor-pointer px-2 py-1 rounded-lg hover:bg-surface-container">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>

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

                        @if (auth()->user()?->role?->slug === 'admin')
                            <x-dropdown-link :href="route('trabajadores')" wire:navigate class="flex items-center gap-2 text-xs py-2 text-on-surface">
                                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">manage_accounts</span>
                                <span>Configuración de Perfiles</span>
                            </x-dropdown-link>
                        @endif

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
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container-high border border-outline-variant/30 shadow-xs p-1">
                    <x-application-logo class="w-full h-full" />
                </div>
                <div class="flex flex-col">
                    <span class="font-extrabold text-base text-primary leading-none tracking-tight">SUSHI XPRESS</span>
                    <span class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold">Japanese Craft & Gastro POS</span>
                </div>
            </div>

            <!-- Subtitle Section -->
            <div class="px-4 py-2.5">
                <span class="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">
                    @if(auth()->user()?->role?->slug === 'mesero')
                        Servicio en Salón
                    @elseif(in_array(auth()->user()?->role?->slug, ['cocina', 'barra'], true))
                        Producción & KDS
                    @else
                        Módulos de Servicio
                    @endif
                </span>
            </div>

            <!-- Navigation Links -->
            <nav class="flex flex-col gap-1 px-3">
                @if(auth()->user()?->role?->slug === 'mesero')
                    <!-- Card de Terminal Mesero -->
                    <div class="mb-2 p-3 rounded-2xl bg-primary-container/20 border border-primary/20 shadow-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary text-on-primary flex items-center justify-center font-bold text-sm shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">room_service</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-on-surface leading-tight">Terminal Mesero</span>
                                <span class="text-[10px] text-primary font-bold uppercase tracking-wider">Comandas & Cobro</span>
                            </div>
                        </div>
                    </div>

                    <!-- Terminal POS (POS-01) -->
                    <a 
                        href="{{ route('pos') }}" 
                        wire:navigate
                        class="flex items-center justify-between rounded-xl px-3 h-12 text-sm font-bold transition-all duration-150 bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[22px]">point_of_sale</span>
                            <span class="font-extrabold">Terminal POS</span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider opacity-90 px-1.5 py-0.5 rounded bg-white/20">ACTIVO</span>
                    </a>
                @elseif(in_array(auth()->user()?->role?->slug, ['cocina', 'barra'], true))
                    <!-- Card de Cocina KDS -->
                    <div class="mb-2 p-3 rounded-2xl bg-secondary/15 border border-secondary/30 shadow-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">skillet</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-on-surface leading-tight">Cocina KDS</span>
                                <span class="text-[10px] text-secondary font-bold uppercase tracking-wider">Control de Comandas</span>
                            </div>
                        </div>
                    </div>

                    <!-- Cocina KDS (COC-01) -->
                    <a 
                        href="{{ route('cocina') }}" 
                        wire:navigate
                        class="flex items-center justify-between rounded-xl px-3 h-12 text-sm font-bold transition-all duration-150 bg-secondary text-white shadow-[0_2px_8px_rgba(30,140,80,0.25)]"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[22px]">restaurant</span>
                            <span class="font-extrabold">Pantalla KDS Cocina</span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider opacity-90 px-1.5 py-0.5 rounded bg-white/20">ACTIVO</span>
                    </a>
                @else
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

                @if (in_array(auth()->user()?->role?->slug, ['admin', 'gerente']))
                    <!-- Carta & Menú (MEN-01) -->
                    <a 
                        href="{{ route('menu') }}" 
                        wire:navigate
                        class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('menu*') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]">restaurant_menu</span>
                            <span>Carta & Menú</span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">MEN</span>
                    </a>
                @endif

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

                @if (in_array(auth()->user()?->role?->slug, ['admin', 'gerente']))
                    <!-- Impresión & Spooler (IMP-01) -->
                    <a 
                        href="{{ route('impresion') }}" 
                        wire:navigate
                        class="flex items-center justify-between rounded-xl px-3 h-11 text-sm font-bold transition-all duration-150 {{ request()->routeIs('impresion') ? 'bg-primary-container text-on-primary shadow-[0_2px_8px_rgba(205,70,48,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]">print</span>
                            <span>Impresión & Spooler</span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider opacity-70">IMP</span>
                    </a>
                @endif

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
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface-container-high border border-outline-variant/30 shadow-xs p-1">
                        <x-application-logo class="w-full h-full" />
                    </div>
                    <span class="font-extrabold text-base text-primary">SUSHI XPRESS</span>
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
                @if(auth()->user()?->role?->slug === 'mesero')
                    <div class="mb-2 p-3 rounded-2xl bg-primary-container/20 border border-primary/20">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary text-on-primary flex items-center justify-center font-bold text-sm shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">room_service</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-on-surface leading-tight">Terminal Mesero</span>
                                <span class="text-[10px] text-primary font-bold uppercase tracking-wider">Comandas & Cobro</span>
                            </div>
                        </div>
                    </div>

                    <a 
                        href="{{ route('pos') }}" 
                        @click="mobileMenuOpen = false" 
                        wire:navigate 
                        class="flex items-center justify-between rounded-xl px-3.5 py-3 text-sm font-extrabold bg-primary-container text-on-primary shadow-sm"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[22px]">point_of_sale</span>
                            <span>Terminal POS</span>
                        </div>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-white/20">ACTIVO</span>
                    </a>
                @elseif(in_array(auth()->user()?->role?->slug, ['cocina', 'barra'], true))
                    <div class="mb-2 p-3 rounded-2xl bg-secondary/15 border border-secondary/30">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">skillet</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-on-surface leading-tight">Cocina KDS</span>
                                <span class="text-[10px] text-secondary font-bold uppercase tracking-wider">Control de Comandas</span>
                            </div>
                        </div>
                    </div>

                    <a 
                        href="{{ route('cocina') }}" 
                        @click="mobileMenuOpen = false" 
                        wire:navigate 
                        class="flex items-center justify-between rounded-xl px-3.5 py-3 text-sm font-extrabold bg-secondary text-white shadow-sm"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[22px]">restaurant</span>
                            <span>Pantalla KDS Cocina</span>
                        </div>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-white/20">ACTIVO</span>
                    </a>
                @else
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

                @if (in_array(auth()->user()?->role?->slug, ['admin', 'gerente']))
                    <a 
                        href="{{ route('menu') }}" 
                        @click="mobileMenuOpen = false" 
                        wire:navigate 
                        class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('menu*') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]">restaurant_menu</span>
                            <span>Carta & Menú</span>
                        </div>
                        <span class="text-[10px] font-bold">MEN</span>
                    </a>
                @endif

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

                @if (in_array(auth()->user()?->role?->slug, ['admin', 'gerente']))
                    <a 
                        href="{{ route('impresion') }}" 
                        @click="mobileMenuOpen = false" 
                        wire:navigate 
                        class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-bold {{ request()->routeIs('impresion') ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}"
                    >
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]">print</span>
                            <span>Impresión & Spooler</span>
                        </div>
                        <span class="text-[10px] font-bold">IMP</span>
                    </a>
                @endif

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
