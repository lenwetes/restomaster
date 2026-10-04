<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Acceso Restringido — {{ config('app.name', 'RestoMaster') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased bg-[#141212] text-[#f5e8e2] flex items-center justify-center p-4 selection:bg-[#e0442e]/30 selection:text-white">
    @php
        $user = auth()->user();
        $roleSlug = $user?->role?->slug;
        $homeRoute = match ($roleSlug) {
            'mesero' => route('pos'),
            'cocina', 'barra' => route('cocina'),
            'delivery', 'repartidor' => route('delivery'),
            'cajero' => route('caja'),
            'admin', 'gerente' => route('dashboard'),
            default => url('/login'),
        };
        $homeLabel = match ($roleSlug) {
            'mesero' => 'Ir a Terminal POS & Comandas',
            'cocina', 'barra' => 'Ir a Pantalla KDS Cocina',
            'delivery', 'repartidor' => 'Ir a Despacho Delivery',
            'cajero' => 'Ir a Control de Caja & Turno',
            'admin', 'gerente' => 'Ir a Panel de Control Ejecutivo',
            default => 'Iniciar Sesión',
        };
        $roleNombre = $user?->role?->nombre ?? ($roleSlug ? ucfirst($roleSlug) : 'Invitado');
    @endphp

    <div class="w-full max-w-lg bg-[#1f1b1a] rounded-3xl border border-[#373230] shadow-2xl p-6 sm:p-8 flex flex-col items-center text-center relative overflow-hidden">
        <!-- Glow ambiental superior -->
        <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-72 h-48 bg-[#e0442e]/15 blur-3xl rounded-full pointer-events-none"></div>

        <!-- Badge & Icono -->
        <div class="w-16 h-16 rounded-2xl bg-[#e0442e]/10 border border-[#e0442e]/25 text-[#e0442e] flex items-center justify-center mb-5 shadow-inner">
            <span class="material-symbols-outlined text-[34px]">shield_lock</span>
        </div>

        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#2a2423] border border-[#3e3634] text-[11px] font-bold text-[#e0442e] uppercase tracking-wider mb-3">
            <span class="w-2 h-2 rounded-full bg-[#e0442e] animate-pulse"></span>
            Código 403 · Restricción de Privilegios
        </div>

        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-2">
            Módulo No Autorizado
        </h1>

        <p class="text-sm text-[#b8a9a2] leading-relaxed mb-6 max-w-md">
            Tu perfil de usuario no cuenta con privilegios para interactuar con esta pantalla o acción en el restaurante.
        </p>

        @if($user)
            <!-- Info Card del Colaborador -->
            <div class="w-full bg-[#26201f] rounded-2xl p-3.5 border border-[#3e3634] flex items-center justify-between gap-3 mb-6 text-left">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-[#e0442e] text-white flex items-center justify-center font-black text-sm shrink-0 uppercase shadow-sm">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-white truncate">{{ $user->name }}</p>
                        <p class="text-[11px] text-[#b8a9a2] font-mono truncate">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="px-2.5 py-1 rounded-lg bg-[#37302e] border border-[#4a403d] text-[10px] font-black uppercase tracking-wider text-[#f5e8e2] shrink-0">
                    {{ $roleNombre }}
                </div>
            </div>
        @endif

        <!-- Botones de Acción -->
        <div class="w-full flex flex-col sm:flex-row items-center gap-3">
            <a 
                id="btn-retorno-home"
                href="{{ $homeRoute }}"
                class="w-full flex-1 inline-flex items-center justify-center gap-2 h-12 px-5 rounded-2xl bg-[#e0442e] hover:bg-[#c93a26] active:scale-98 text-white font-black text-sm shadow-md transition-all cursor-pointer"
            >
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                <span>{{ $homeLabel }}</span>
            </a>

            @if($user)
                <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/login') }}" class="w-full sm:w-auto">
                    @csrf
                    <button 
                        type="submit" 
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 h-12 px-4 rounded-2xl bg-[#2a2423] hover:bg-[#342d2b] text-[#b8a9a2] hover:text-white font-bold text-xs border border-[#3e3634] transition-all cursor-pointer"
                        title="Cambiar a otra cuenta autorizada"
                    >
                        <span class="material-symbols-outlined text-[18px]">switch_account</span>
                        <span>Cambiar Rol</span>
                    </button>
                </form>
            @endif
        </div>

        <!-- Temporizador de Redirección Automática -->
        <div class="mt-5 text-[11px] text-[#8e807a] font-mono flex items-center gap-1.5" id="contador-container">
            <span class="material-symbols-outlined text-[14px] animate-spin">sync</span>
            <span>Regresando a tu espacio de trabajo en <strong id="segundos-restantes" class="text-white">6</strong>s...</span>
        </div>
    </div>

    <script>
        (function() {
            let seg = 6;
            const label = document.getElementById('segundos-restantes');
            const target = "{{ $homeRoute }}";
            const timer = setInterval(() => {
                seg--;
                if (label) label.textContent = seg;
                if (seg <= 0) {
                    clearInterval(timer);
                    window.location.href = target;
                }
            }, 1000);
        })();
    </script>
</body>
</html>
