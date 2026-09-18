<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Bienvenidos a RestoMaster — Restaurante & Bar</title>
    <meta name="description" content="Restaurante gourmet y cocina artesanal en Provenza, Medellín. Haz tu pedido a domicilio, consulta nuestro menú o reserva tu mesa en línea.">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <!-- Google Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            background-color: #fafaf9;
            color: #1c1917;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between selection:bg-[#ff5436] selection:text-white relative overflow-x-hidden">

    <!-- Subtle warm ambient top glow -->
    <div class="fixed -top-32 left-1/2 -translate-x-1/2 w-[600px] h-[280px] bg-[#ff5436]/8 blur-[140px] pointer-events-none -z-10"></div>
    <div class="fixed bottom-0 right-0 w-[400px] h-[250px] bg-amber-400/8 blur-[130px] pointer-events-none -z-10"></div>

    <!-- ================================================================= -->
    <!-- TOPBAR                                                            -->
    <!-- ================================================================= -->
    <header class="w-full border-b border-stone-200 bg-white/90 backdrop-blur-md sticky top-0 z-40 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center gap-3 group">
                <div class="w-11 h-11 rounded-2xl bg-stone-100 border border-[#ff5436]/30 p-1 shadow-sm group-hover:scale-105 transition-transform">
                    <x-application-logo class="w-full h-full" />
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-xl font-black tracking-tight text-stone-900">RESTO<span class="text-[#ff5436]">MASTER</span></span>
                    </div>
                    <p class="text-[10px] text-stone-400 font-mono tracking-wider">PROVENZA · MEDELLÍN</p>
                </div>
            </a>

            <!-- Access Link for Staff -->
            <div class="flex items-center gap-3">
                @auth
                    <a 
                        href="{{ route('dashboard') }}" 
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-stone-100 hover:bg-stone-200 border border-stone-200 text-xs font-bold text-stone-700 transition-all"
                    >
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Mi Estación ({{ Auth::user()->name }})</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                @else
                    <a 
                        href="{{ route('login') }}" 
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-500 hover:text-stone-800 border border-stone-200 text-xs font-bold transition-all"
                    >
                        <span class="material-symbols-outlined text-[16px] text-[#ff5436]">login</span>
                        <span>Acceso Personal</span>
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- ================================================================= -->
    <!-- MAIN HERO: BIENVENIDA AL NEGOCIO                                  -->
    <!-- ================================================================= -->
    <main class="flex-1 flex flex-col items-center justify-center px-4 sm:px-6 py-10 sm:py-16">
        <div class="max-w-4xl w-full text-center space-y-8">
            
            <!-- Restaurant Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white border border-stone-200 shadow-sm text-xs font-bold text-stone-600">
                <span class="text-base">🍽️</span>
                <span>Restaurante & Bar · Cocina Artesanal y Parrilla</span>
                <span class="text-stone-300">•</span>
                <span class="text-emerald-600 font-bold">Abierto Hoy</span>
            </div>

            <!-- Title & Welcome Message -->
            <div class="space-y-4">
                <h1 class="text-4xl sm:text-6xl font-black text-stone-900 tracking-tight leading-tight">
                    Bienvenidos a <span class="text-[#ff5436]">RestoMaster</span>
                </h1>
                <p class="text-base sm:text-lg text-stone-500 max-w-2xl mx-auto leading-relaxed">
                    Cortes a la parrilla, pastas artesanales, hamburguesas gourmet y coctelería de autor en el corazón de Provenza. Elige cómo deseas disfrutar tu experiencia hoy:
                </p>
            </div>

            <!-- ============================================================= -->
            <!-- THE 3 MAIN ACTION BUTTONS                                     -->
            <!-- ============================================================= -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 pt-2 max-w-3xl mx-auto">
                
                <!-- 1. Botón Delivery a Domicilio -->
                <a 
                    href="{{ route('delivery.publico') }}" 
                    class="p-6 rounded-3xl bg-gradient-to-b from-[#ff5436] to-[#e0381d] text-white shadow-xl shadow-[#ff5436]/20 hover:shadow-[#ff5436]/35 hover:-translate-y-1 transition-all group flex flex-col items-center justify-between text-center min-h-[170px] cursor-pointer"
                >
                    <div class="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[28px] text-white">two_wheeler</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-black tracking-tight leading-none mb-1">Delivery</h3>
                        <p class="text-xs text-white/80 font-medium">Pedir a domicilio con seguimiento en vivo</p>
                    </div>
                    <span class="mt-3 text-[11px] font-black uppercase tracking-wider bg-black/15 px-3 py-1 rounded-full text-white/90">
                        Ordenar Ahora →
                    </span>
                </a>

                <!-- 2. Botón Menú en Línea -->
                <a 
                    href="{{ route('carta.publico') }}" 
                    class="p-6 rounded-3xl bg-white border border-stone-200 hover:border-[#ff5436]/50 text-stone-800 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all group flex flex-col items-center justify-between text-center min-h-[170px] cursor-pointer"
                >
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[28px] text-amber-500">restaurant_menu</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-black tracking-tight leading-none mb-1 text-stone-900">Menú en Línea</h3>
                        <p class="text-xs text-stone-500 font-medium">Explora la carta de parrilla, pastas, burgers y cócteles</p>
                    </div>
                    <span class="mt-3 text-[11px] font-black uppercase tracking-wider bg-stone-100 text-stone-500 px-3 py-1 rounded-full group-hover:text-[#ff5436] group-hover:bg-[#ff5436]/10 transition-all">
                        Ver Carta Digital →
                    </span>
                </a>

                <!-- 3. Botón Reserva en Línea -->
                <a 
                    href="{{ route('reservas.publico') }}" 
                    class="p-6 rounded-3xl bg-white border border-stone-200 hover:border-amber-400/60 text-stone-800 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all group flex flex-col items-center justify-between text-center min-h-[170px] cursor-pointer"
                >
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[28px] text-amber-500">calendar_month</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-black tracking-tight leading-none mb-1 text-stone-900">Reserva en Línea</h3>
                        <p class="text-xs text-stone-500 font-medium">Asegura tu mesa en salón principal o terraza</p>
                    </div>
                    <span class="mt-3 text-[11px] font-black uppercase tracking-wider bg-stone-100 text-stone-500 px-3 py-1 rounded-full group-hover:text-amber-600 group-hover:bg-amber-50 transition-all">
                        Reservar Mesa →
                    </span>
                </a>

            </div>

            <!-- Featured Image Preview Card -->
            <div class="max-w-xl mx-auto rounded-3xl overflow-hidden border border-stone-200 shadow-xl relative group mt-8">
                <img 
                    src="{{ asset('images/restomaster-hero.jpg') }}" 
                    alt="RestoMaster Selección Gastronómica" 
                    class="w-full h-56 sm:h-64 object-cover group-hover:scale-105 transition-transform duration-700"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-stone-900/80 via-stone-900/20 to-transparent"></div>
                <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between text-left">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-300 font-mono">Gastronomía de Autor</span>
                        <h4 class="text-sm font-extrabold text-white">Cortes Angus, Pastas & Platos de la Casa</h4>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-[11px] font-bold text-white border border-white/20">
                        El Poblado
                    </span>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- INFORMACIÓN PARA NUESTRO CLIENTE                              -->
            <!-- ============================================================= -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-6 max-w-3xl mx-auto text-left">
                
                <!-- Ubicación -->
                <div class="p-4 rounded-2xl bg-white border border-stone-200 shadow-sm flex items-start gap-3">
                    <span class="material-symbols-outlined text-[#ff5436] text-[22px] shrink-0 mt-0.5">pin_drop</span>
                    <div class="space-y-0.5">
                        <h4 class="text-xs font-black uppercase tracking-wider text-stone-700">Ubicación</h4>
                        <p class="text-xs text-stone-600 font-medium">Cra 35 # 8A-12, Vía Provenza</p>
                        <p class="text-[11px] text-stone-400">El Poblado, Medellín</p>
                    </div>
                </div>

                <!-- Horarios -->
                <div class="p-4 rounded-2xl bg-white border border-stone-200 shadow-sm flex items-start gap-3">
                    <span class="material-symbols-outlined text-amber-500 text-[22px] shrink-0 mt-0.5">schedule</span>
                    <div class="space-y-0.5">
                        <h4 class="text-xs font-black uppercase tracking-wider text-stone-700">Horarios de Cocina</h4>
                        <p class="text-xs text-stone-600 font-medium">Mar - Sáb: 12:00 PM – 11:00 PM</p>
                        <p class="text-[11px] text-stone-400">Dom: 12:30 PM – 9:30 PM</p>
                    </div>
                </div>

                <!-- WhatsApp -->
                <a 
                    href="https://wa.me/573001234567?text=Hola%20RestoMaster!%20Deseo%20informaci%C3%B3n%20sobre%20el%20restaurante" 
                    target="_blank" 
                    class="p-4 rounded-2xl bg-white border border-stone-200 hover:border-emerald-300 shadow-sm hover:shadow-md transition-all flex items-start gap-3 group"
                >
                    <span class="material-symbols-outlined text-emerald-500 text-[22px] shrink-0 mt-0.5 group-hover:scale-110 transition-transform">call</span>
                    <div class="space-y-0.5">
                        <h4 class="text-xs font-black uppercase tracking-wider text-stone-700 group-hover:text-emerald-600 transition-colors">WhatsApp Directo</h4>
                        <p class="text-xs text-stone-600 font-medium">+57 300 123 4567</p>
                        <p class="text-[11px] text-emerald-600 font-bold">Chatear con el Asesor →</p>
                    </div>
                </a>

            </div>

        </div>
    </main>

    <!-- ================================================================= -->
    <!-- FOOTER                                                            -->
    <!-- ================================================================= -->
    <footer class="w-full border-t border-stone-200 bg-white py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-stone-400">
            <p>© {{ date('Y') }} RestoMaster S.A.S. • NIT 901.458.789-3 • Todos los derechos reservados.</p>
            <div class="flex items-center gap-4">
                <a href="{{ route('login') }}" class="hover:text-stone-700 transition-colors text-stone-400 font-bold">
                    Terminal POS & Personal →
                </a>
            </div>
        </div>
    </footer>

</body>
</html>
