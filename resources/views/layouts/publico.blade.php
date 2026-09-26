<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-background">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'RestoMaster' }} — Restaurante, Parrilla & Coctelería de Autor · Provenza</title>
    <meta name="description" content="Restaurante de autor en Provenza, Medellín. Cortes a la parrilla, cocina fría, sushi de especialidad y coctelería. Haz tu pedido a domicilio o reserva tu mesa.">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <!-- Typography & Icons (Aura Gastro Expressive OS) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

    <style>
        html, body {
            background-color: #0e0907 !important;
            color: #f5e8e2 !important;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }

        /* Anti-autofill override for webkit browsers in dark mode */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        textarea:-webkit-autofill,
        textarea:-webkit-autofill:hover,
        textarea:-webkit-autofill:focus,
        select:-webkit-autofill,
        select:-webkit-autofill:hover,
        select:-webkit-autofill:focus {
            -webkit-text-fill-color: #f5e8e2 !important;
            -webkit-box-shadow: 0 0 0px 1000px #1e1410 inset !important;
            box-shadow: 0 0 0px 1000px #1e1410 inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .material-symbols-outlined.fill {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        /* Hide scrollbars elegantly */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#0e0907] text-[#f5e8e2] flex flex-col selection:bg-[#e0442e] selection:text-white antialiased relative overflow-x-hidden">

    <!-- Atmospheric Ember & Amber Glows -->
    <div class="fixed top-[-120px] left-1/2 -translate-x-1/2 w-[700px] h-[350px] rounded-full bg-[#e0442e]/10 blur-[160px] pointer-events-none -z-10"></div>
    <div class="fixed bottom-0 right-[-100px] w-[500px] h-[350px] rounded-full bg-[#e8a020]/8 blur-[180px] pointer-events-none -z-10"></div>
    <div class="fixed top-[40%] left-[-150px] w-[400px] h-[400px] rounded-full bg-[#2eb8b4]/5 blur-[160px] pointer-events-none -z-10"></div>

    <!-- ================================================================= -->
    <!-- TOPBAR GASTRO LOUNGE (DESKTOP & TABLET)                           -->
    <!-- ================================================================= -->
    <header class="sticky top-0 z-50 bg-[#140e0b]/90 backdrop-blur-xl border-b border-[#432f26]/40 shadow-2xl transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between gap-4">
            
            <!-- Brand Logo & Identity -->
            <a href="/" class="flex items-center gap-3.5 group shrink-0">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#261a15] to-[#1e1410] border border-[#e0442e]/30 p-2 shadow-lg shadow-[#e0442e]/10 group-hover:border-[#e0442e]/60 group-hover:scale-105 transition-all">
                    <x-application-logo class="w-full h-full text-[#e0442e]" />
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-xl font-black tracking-tight text-white">RESTO<span class="text-[#e0442e]">MASTER</span></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-[10px] text-[#c4a89e] font-mono tracking-wider uppercase font-semibold">Provenza · Medellín</span>
                    </div>
                </div>
            </a>

            <!-- Center Navigation Links (Pill Nav) -->
            <nav class="hidden md:flex items-center gap-1 bg-[#1e1410]/80 p-1.5 rounded-2xl border border-[#432f26]/50 shadow-inner">
                <a 
                    href="/" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ request()->is('/') ? 'bg-[#e0442e] text-white shadow-lg shadow-[#e0442e]/25' : 'text-[#c4a89e] hover:text-white hover:bg-[#261a15]' }}"
                >
                    <span class="material-symbols-outlined text-[18px]">home</span>
                    <span>Inicio</span>
                </a>
                <a 
                    href="{{ route('carta.publico') }}" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ request()->routeIs('carta.publico') ? 'bg-[#e0442e] text-white shadow-lg shadow-[#e0442e]/25' : 'text-[#c4a89e] hover:text-white hover:bg-[#261a15]' }}"
                >
                    <span class="material-symbols-outlined text-[18px]">restaurant_menu</span>
                    <span>Carta Digital</span>
                </a>
                <a 
                    href="{{ route('reservas.publico') }}" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ request()->routeIs('reservas.publico') ? 'bg-[#e0442e] text-white shadow-lg shadow-[#e0442e]/25' : 'text-[#c4a89e] hover:text-white hover:bg-[#261a15]' }}"
                >
                    <span class="material-symbols-outlined text-[18px] text-[#e8a020]">calendar_month</span>
                    <span>Reservar Mesa</span>
                </a>
                <a 
                    href="{{ route('delivery.publico') }}" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ request()->routeIs('delivery.publico') ? 'bg-[#e0442e] text-white shadow-lg shadow-[#e0442e]/25' : 'text-[#c4a89e] hover:text-white hover:bg-[#261a15]' }}"
                >
                    <span class="material-symbols-outlined text-[18px] text-[#e0442e]">two_wheeler</span>
                    <span>Pedir Delivery</span>
                </a>
            </nav>

            <!-- Right Actions: Direct WhatsApp & Staff Access -->
            <div class="flex items-center gap-2.5 sm:gap-3">
                <a 
                    href="https://wa.me/573001234567?text=Hola%20RestoMaster!%20Deseo%20informaci%C3%B3n%20sobre%20el%20restaurante" 
                    target="_blank" 
                    class="hidden sm:inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold transition-all shadow-sm group"
                >
                    <span class="material-symbols-outlined text-[17px] group-hover:scale-110 transition-transform">chat</span>
                    <span>WhatsApp</span>
                </a>

                <a 
                    href="{{ route('cliente.login') }}" 
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gradient-to-r from-rose-600/20 to-amber-500/20 hover:from-rose-600/30 hover:to-amber-500/30 text-amber-300 border border-amber-500/30 text-xs font-bold transition-all shadow-sm"
                    title="Accede a tus puntos y beneficios VIP"
                >
                    <span class="material-symbols-outlined text-[16px] text-amber-400">loyalty</span>
                    <span class="hidden sm:inline">Club Clientes</span>
                </a>

                @auth
                    <a 
                        href="{{ route('dashboard') }}" 
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-[#261a15] hover:bg-[#38271f] border border-[#432f26] text-xs font-bold text-[#f5e8e2] transition-all"
                    >
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="hidden sm:inline">Mi Estación ({{ Auth::user()->name }})</span>
                        <span class="sm:hidden">Staff</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                @else
                    <a 
                        href="{{ route('login') }}" 
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-[#1e1410] hover:bg-[#261a15] text-[#c4a89e] hover:text-white border border-[#432f26]/60 text-xs font-bold transition-all"
                        title="Acceso exclusivo para equipo de servicio y cocina"
                    >
                        <span class="material-symbols-outlined text-[16px] text-[#7a5a52]">lock</span>
                        <span class="hidden sm:inline">Acceso Personal</span>
                    </a>
                @endauth
            </div>
        </div>

        <!-- Mobile Bottom Nav Bar (Quick Touch Ergonomics) -->
        <div class="flex md:hidden items-center justify-around px-2 py-2.5 border-t border-[#432f26]/40 bg-[#140e0b]/95 backdrop-blur-xl">
            <a 
                href="/" 
                class="flex flex-col items-center py-1 px-3 rounded-xl text-[10px] font-bold transition-colors {{ request()->is('/') ? 'text-[#e0442e]' : 'text-[#c4a89e] hover:text-white' }}"
            >
                <span class="material-symbols-outlined text-[20px]">home</span>
                <span>Inicio</span>
            </a>
            <a 
                href="{{ route('carta.publico') }}" 
                class="flex flex-col items-center py-1 px-3 rounded-xl text-[10px] font-bold transition-colors {{ request()->routeIs('carta.publico') ? 'text-[#e0442e]' : 'text-[#c4a89e] hover:text-white' }}"
            >
                <span class="material-symbols-outlined text-[20px]">restaurant_menu</span>
                <span>Carta</span>
            </a>
            <a 
                href="{{ route('reservas.publico') }}" 
                class="flex flex-col items-center py-1 px-3 rounded-xl text-[10px] font-bold transition-colors {{ request()->routeIs('reservas.publico') ? 'text-[#e8a020]' : 'text-[#c4a89e] hover:text-white' }}"
            >
                <span class="material-symbols-outlined text-[20px]">calendar_month</span>
                <span>Reservas</span>
            </a>
            <a 
                href="{{ route('delivery.publico') }}" 
                class="flex flex-col items-center py-1 px-3 rounded-xl text-[10px] font-bold transition-colors {{ request()->routeIs('delivery.publico') ? 'text-[#e0442e]' : 'text-[#c4a89e] hover:text-white' }}"
            >
                <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                <span>Delivery</span>
            </a>
        </div>
    </header>

    <!-- ================================================================= -->
    <!-- MAIN CONTENT SLOT                                                 -->
    <!-- ================================================================= -->
    <main class="flex-1 w-full">
        {{ $slot }}
    </main>

    <!-- ================================================================= -->
    <!-- FOOTER COMERCIAL & IDENTIDAD                                      -->
    <!-- ================================================================= -->
    <footer class="border-t border-[#432f26]/40 bg-[#0a0705] py-14 px-4 sm:px-6 text-[#c4a89e] text-xs">
        <div class="max-w-7xl mx-auto space-y-12">
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Brand Manifesto -->
                <div class="space-y-4 md:col-span-1">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-[#1e1410] border border-[#e0442e]/40 p-1.5">
                            <x-application-logo class="w-full h-full text-[#e0442e]" />
                        </div>
                        <span class="text-base font-black tracking-tight text-white">RESTO<span class="text-[#e0442e]">MASTER</span></span>
                    </div>
                    <p class="text-xs text-[#c4a89e] leading-relaxed">
                        Cortes madurados a la brasa, pastas frescas artesanales, barra fría & sushi de autor, coctelería molecular en Provenza, Medellín.
                    </p>
                    <div class="flex items-center gap-2 pt-1 text-emerald-400 font-mono text-[11px]">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Cocina Activa de Martes a Domingo</span>
                    </div>
                </div>

                <!-- Enlaces Rápidos de Servicio -->
                <div class="space-y-3">
                    <h4 class="text-xs font-black uppercase tracking-wider text-white">Servicios en Línea</h4>
                    <ul class="space-y-2 text-xs">
                        <li>
                            <a href="{{ route('delivery.publico') }}" class="hover:text-white transition-colors flex items-center gap-2">
                                <span class="material-symbols-outlined text-[15px] text-[#e0442e]">two_wheeler</span>
                                <span>Pedidos a Domicilio Express</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('carta.publico') }}" class="hover:text-white transition-colors flex items-center gap-2">
                                <span class="material-symbols-outlined text-[15px] text-[#e8a020]">restaurant_menu</span>
                                <span>Carta Digital de Platos</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('reservas.publico') }}" class="hover:text-white transition-colors flex items-center gap-2">
                                <span class="material-symbols-outlined text-[15px] text-[#2eb8b4]">calendar_today</span>
                                <span>Reserva de Mesas & Eventos</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Ubicación & Horarios -->
                <div class="space-y-3">
                    <h4 class="text-xs font-black uppercase tracking-wider text-white">Provenza · Medellín</h4>
                    <div class="space-y-1.5 text-xs text-[#c4a89e]">
                        <p class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-[16px] text-[#e0442e] shrink-0 mt-0.5">location_on</span>
                            <span>Cra 35 # 8A-12, Vía Provenza, El Poblado</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-[16px] text-[#e8a020] shrink-0 mt-0.5">schedule</span>
                            <span>Mar – Sáb: 12:00 PM – 11:00 PM<br>Dom: 12:30 PM – 9:30 PM</span>
                        </p>
                    </div>
                </div>

                <!-- Contacto & Reservas Especiales -->
                <div class="space-y-3">
                    <h4 class="text-xs font-black uppercase tracking-wider text-white">Atención & WhatsApp</h4>
                    <p class="text-xs text-[#c4a89e]">
                        Para celebraciones, eventos corporativos o asesoría gastronómica directa:
                    </p>
                    <a 
                        href="https://wa.me/573001234567?text=Hola%20RestoMaster!%20Deseo%20informaci%C3%B3n" 
                        target="_blank" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 border border-emerald-500/30 text-xs font-bold transition-all"
                    >
                        <span class="material-symbols-outlined text-[18px]">chat</span>
                        <span>+57 300 123 4567</span>
                    </a>
                </div>
            </div>

            <div class="pt-8 border-t border-[#432f26]/40 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-[#7a5a52]">
                <p>© {{ date('Y') }} RestoMaster S.A.S. • NIT 901.458.789-3 • Todos los derechos reservados.</p>
                <div class="flex items-center gap-6">
                    <a href="{{ route('carta.publico') }}" class="hover:text-[#c4a89e] transition-colors">Menú</a>
                    <a href="{{ route('reservas.publico') }}" class="hover:text-[#c4a89e] transition-colors">Reservas</a>
                    <a href="{{ route('delivery.publico') }}" class="hover:text-[#c4a89e] transition-colors">Delivery</a>
                    <a href="{{ route('login') }}" class="hover:text-[#c4a89e] transition-colors font-mono">Terminal POS & Staff →</a>
                </div>
            </div>

        </div>
    </footer>

    @livewireScripts
</body>
</html>
