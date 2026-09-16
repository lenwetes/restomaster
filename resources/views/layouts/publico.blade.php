<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'RestoMaster' }} — Restaurante & Gastro Experience</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

    <style>
        html, body {
            background-color: #fafaf9 !important;
            color: #1c1917 !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Anti-autofill override for webkit browsers */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        textarea:-webkit-autofill,
        textarea:-webkit-autofill:hover,
        textarea:-webkit-autofill:focus,
        select:-webkit-autofill,
        select:-webkit-autofill:hover,
        select:-webkit-autofill:focus {
            -webkit-text-fill-color: #1c1917 !important;
            -webkit-box-shadow: 0 0 0px 1000px #f5f5f4 inset !important;
            box-shadow: 0 0 0px 1000px #f5f5f4 inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .material-symbols-outlined.fill {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#fafaf9] text-stone-900 flex flex-col selection:bg-[#ff5436] selection:text-white antialiased relative">

    <!-- Subtle warm ambient glows -->
    <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-[#ff5436]/6 blur-[140px] pointer-events-none -z-10"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[500px] h-[500px] rounded-full bg-amber-400/6 blur-[150px] pointer-events-none -z-10"></div>

    <!-- Navigation Bar Exclusiva para Clientes -->
    <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-stone-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 sm:h-20 flex items-center justify-between gap-4">
            <!-- Brand Logo & Name -->
            <a href="/" class="flex items-center gap-3 group shrink-0">
                <div class="w-10 h-10 rounded-2xl bg-stone-100 border border-[#ff5436]/30 p-1.5 shadow-sm group-hover:scale-105 transition-transform">
                    <x-application-logo class="w-full h-full" />
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-base sm:text-lg font-black tracking-tight text-stone-900">RESTO<span class="text-[#ff5436]">MASTER</span></span>
                    </div>
                    <p class="text-[10px] text-stone-400 font-bold uppercase tracking-wider hidden sm:block">Restaurante & Gastro Experience</p>
                </div>
            </a>

            <!-- Center Navigation Links -->
            <nav class="hidden md:flex items-center gap-1.5 bg-stone-100 p-1.5 rounded-2xl border border-stone-200">
                <a 
                    href="{{ route('delivery.publico') }}" 
                    class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 {{ request()->routeIs('delivery.publico') ? 'bg-[#ff5436] text-white shadow-md' : 'text-stone-500 hover:text-stone-900 hover:bg-white' }}"
                >
                    <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                    <span>Pedir Delivery</span>
                </a>
                <a 
                    href="{{ route('carta.publico') }}" 
                    class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 {{ request()->routeIs('carta.publico') ? 'bg-[#ff5436] text-white shadow-md' : 'text-stone-500 hover:text-stone-900 hover:bg-white' }}"
                >
                    <span class="material-symbols-outlined text-[18px]">restaurant_menu</span>
                    <span>Menú en Línea</span>
                </a>
                <a 
                    href="{{ route('reservas.publico') }}" 
                    class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 {{ request()->routeIs('reservas.publico') ? 'bg-[#ff5436] text-white shadow-md' : 'text-stone-500 hover:text-stone-900 hover:bg-white' }}"
                >
                    <span class="material-symbols-outlined text-[18px] {{ request()->routeIs('reservas.publico') ? 'text-white' : 'text-amber-500' }}">calendar_month</span>
                    <span>Reservar Mesa</span>
                </a>
            </nav>

            <!-- Right Actions: Staff Login & Direct Contact -->
            <div class="flex items-center gap-2 sm:gap-3">
                <a 
                    href="https://wa.me/573001234567" 
                    target="_blank" 
                    class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 text-xs font-bold transition-all"
                >
                    <span class="material-symbols-outlined text-[16px]">chat</span>
                    <span>WhatsApp</span>
                </a>

                <a 
                    href="{{ route('login') }}" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 border border-stone-200 text-xs font-bold text-stone-500 hover:text-stone-800 transition-all"
                    title="Acceso restringido para personal del restaurante"
                >
                    <span class="material-symbols-outlined text-[16px] text-stone-400">lock</span>
                    <span class="hidden sm:inline">Acceso Personal</span>
                </a>
            </div>
        </div>

        <!-- Mobile Nav Bar (Below 768px) -->
        <div class="flex md:hidden items-center justify-around px-2 py-2 border-t border-stone-200 bg-white">
            <a 
                href="{{ route('delivery.publico') }}" 
                class="flex flex-col items-center py-1 px-3 rounded-xl text-[11px] font-bold {{ request()->routeIs('delivery.publico') ? 'text-[#ff5436]' : 'text-stone-400' }}"
            >
                <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                <span>Delivery</span>
            </a>
            <a 
                href="{{ route('carta.publico') }}" 
                class="flex flex-col items-center py-1 px-3 rounded-xl text-[11px] font-bold {{ request()->routeIs('carta.publico') ? 'text-[#ff5436]' : 'text-stone-400' }}"
            >
                <span class="material-symbols-outlined text-[20px]">restaurant_menu</span>
                <span>Menú</span>
            </a>
            <a 
                href="{{ route('reservas.publico') }}" 
                class="flex flex-col items-center py-1 px-3 rounded-xl text-[11px] font-bold {{ request()->routeIs('reservas.publico') ? 'text-amber-500' : 'text-stone-400' }}"
            >
                <span class="material-symbols-outlined text-[20px]">calendar_month</span>
                <span>Reservas</span>
            </a>
            <a 
                href="/" 
                class="flex flex-col items-center py-1 px-3 rounded-xl text-[11px] font-bold text-stone-400"
            >
                <span class="material-symbols-outlined text-[20px]">storefront</span>
                <span>Inicio</span>
            </a>
        </div>
    </header>

    <!-- Main Public Content Slot -->
    <main class="flex-1 w-full">
        {{ $slot }}
    </main>

    <!-- Public Footer -->
    <footer class="border-t border-stone-200 bg-white py-10 px-4 text-stone-500 text-xs">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-6 text-center sm:text-left">
            <div class="space-y-1">
                <div class="flex items-center justify-center sm:justify-start gap-2">
                    <span class="text-sm font-black text-stone-900">RestoMaster</span>
                    <span class="text-stone-300">·</span>
                    <span class="text-amber-500 font-bold">Gastronomía Artesanal & Parrilla</span>
                </div>
                <p class="text-stone-400">Cra 35 # 8A-12, Vía Provenza, El Poblado · Tel: +57 300 123 4567</p>
            </div>

            <div class="flex items-center gap-4 text-stone-400 text-[11px]">
                <span>Mar - Sáb: 12:00 PM – 11:00 PM</span>
                <span>·</span>
                <span>Dom: 12:30 PM – 9:30 PM</span>
            </div>

            <div>
                <p class="text-[11px] text-stone-400">© {{ date('Y') }} RestoMaster. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
