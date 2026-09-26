<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi Perfil — RestoMaster Club</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-slate-100 flex flex-col justify-between selection:bg-rose-500 selection:text-white bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900 via-slate-950 to-slate-950">
    <!-- Navbar del Portal de Cliente -->
    <header class="w-full border-b border-white/10 bg-slate-900/60 backdrop-blur-md px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <a href="/" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#261a15] to-[#1e1410] border border-[#e0442e]/30 p-1.5 shadow-md shadow-[#e0442e]/10 flex items-center justify-center">
                <x-application-logo class="w-full h-full text-[#e0442e]" />
            </div>
            <span class="text-lg font-black tracking-tight text-white">RESTO<span class="text-[#e0442e]">MASTER</span> <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-[#e0442e]/20 text-[#e0442e] ml-1">Club</span></span>
        </a>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2.5 bg-slate-800/80 border border-white/10 rounded-full py-1 pl-1.5 pr-3">
                @if ($cliente->avatar_url)
                    <img src="{{ $cliente->avatar_url }}" alt="{{ $cliente->nombre }}" class="w-7 h-7 rounded-full object-cover border border-white/20" />
                @else
                    <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-rose-500 to-amber-500 flex items-center justify-center text-xs font-bold text-white">
                        {{ strtoupper(substr($cliente->nombre, 0, 1)) }}
                    </div>
                @endif
                <span class="text-xs font-semibold text-white max-w-[120px] truncate">{{ $cliente->nombre }}</span>
            </div>

            <form action="{{ route('cliente.logout') }}" method="POST">
                @csrf
                <button type="submit"
                        title="Cerrar sesión"
                        class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-white/5 transition-colors flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">logout</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Contenido Dashboard -->
    <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Notificaciones Flash -->
        @if (session('success'))
            <div class="p-4 rounded-2xl bg-emerald-950/80 border border-emerald-500/40 text-emerald-200 text-sm flex items-center gap-3 shadow-lg">
                <span class="material-symbols-outlined text-xl text-emerald-400 shrink-0">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Card de Fidelización y Nivel -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Puntos Acumulados -->
            <div class="md:col-span-2 relative overflow-hidden rounded-3xl bg-gradient-to-br from-rose-950/40 via-slate-900 to-amber-950/30 border border-white/10 p-6 sm:p-8 shadow-xl">
                <div class="relative z-10 flex flex-col justify-between h-full space-y-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-rose-400">Puntos Disponibles</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-4xl sm:text-5xl font-black text-white tracking-tight">{{ number_format($cliente->puntos_fidelidad) }}</span>
                                <span class="text-sm font-semibold text-slate-400">pts</span>
                            </div>
                        </div>
                        @php
                            $badge = $cliente->badgeTier();
                        @endphp
                        <div class="px-3.5 py-1.5 rounded-full {{ $badge['color'] }} flex items-center gap-1.5 text-xs font-bold">
                            <span class="material-symbols-outlined text-base">{{ $badge['icono'] }}</span>
                            <span>{{ $badge['label'] }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-4 border-t border-white/10">
                        <div>
                            <span class="text-[11px] text-slate-400 block">Total Visitas</span>
                            <span class="text-base font-bold text-white">{{ $cliente->visitas_count }}</span>
                        </div>
                        <div>
                            <span class="text-[11px] text-slate-400 block">Total Consumido</span>
                            <span class="text-base font-bold text-white">$ {{ number_format($cliente->total_gastado, 0, ',', '.') }}</span>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <span class="text-[11px] text-slate-400 block">Valoración de Cliente</span>
                            <span class="text-base font-bold text-amber-400 flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">star</span>
                                {{ $cliente->rating_promedio ? number_format($cliente->rating_promedio, 1) : 'Nuevo' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-rose-500/10 rounded-full blur-2xl pointer-events-none"></div>
            </div>

            <!-- Código QR / Identificador rápido -->
            <div class="rounded-3xl bg-slate-900/80 border border-white/10 p-6 flex flex-col items-center justify-center text-center shadow-xl">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Tu ID de Cliente</span>
                <div class="bg-white p-3 rounded-2xl shadow-inner mb-3">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=CLIENTE-{{ $cliente->id }}"
                         alt="QR de Cliente"
                         class="w-32 h-32 rounded-lg" />
                </div>
                <span class="font-mono text-xs font-bold text-slate-300">#CLI-{{ str_pad($cliente->id, 5, '0', STR_PAD_LEFT) }}</span>
                <p class="text-[11px] text-slate-400 mt-1">Muéstralo al cajero para sumar puntos en tu comanda.</p>
            </div>
        </div>

        <!-- Encuestas de Satisfacción Pendientes (F7-07) -->
        @if ($encuestasPendientes->isNotEmpty())
            <div class="rounded-3xl bg-gradient-to-r from-amber-950/40 to-rose-950/40 border border-amber-500/30 p-6 shadow-xl space-y-4">
                <div class="flex items-center gap-2.5 text-amber-300">
                    <span class="material-symbols-outlined text-2xl">rate_review</span>
                    <h2 class="text-lg font-bold text-white">¡Danos tu opinión y gana +50 puntos de regalo!</h2>
                </div>

                <div class="space-y-3">
                    @foreach ($encuestasPendientes as $envio)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-slate-900/90 border border-white/10">
                            <div>
                                <h3 class="text-sm font-bold text-white">{{ $envio->encuesta->nombre }}</h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Responde unas breves preguntas sobre tu experiencia en RestoMaster.
                                </p>
                            </div>
                            <a href="{{ route('encuesta.responder', $envio->token) }}"
                               class="py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-rose-600 hover:from-amber-400 hover:to-rose-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition-all shrink-0 shadow-md">
                                <span>Responder Encuesta (+50 pts)</span>
                                <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Historial Reciente de Pedidos y Puntos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Pedidos Recientes -->
            <div class="rounded-3xl bg-slate-900/70 border border-white/10 p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-rose-400">receipt_long</span>
                        Tus Últimos Pedidos
                    </h2>
                </div>

                @if ($pedidosRecientes->isEmpty())
                    <p class="text-xs text-slate-400 py-6 text-center">Aún no registras pedidos en tu historial.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($pedidosRecientes as $pedido)
                            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-white/5 flex items-center justify-between text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-white font-mono">{{ $pedido->codigo }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400">
                                            {{ ucfirst($pedido->estado) }}
                                        </span>
                                    </div>
                                    <span class="text-slate-400 text-[11px] block mt-0.5">
                                        {{ $pedido->created_at->format('d/m/Y H:i') }} &bull; {{ $pedido->items->count() }} ítems
                                    </span>
                                </div>
                                <span class="font-bold text-white text-sm">
                                    $ {{ number_format($pedido->total, 0, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Movimientos de Puntos -->
            <div class="rounded-3xl bg-slate-900/70 border border-white/10 p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-400">award_star</span>
                        Actividad de Puntos
                    </h2>
                </div>

                @if ($movimientosPuntos->isEmpty())
                    <p class="text-xs text-slate-400 py-6 text-center">Aún no hay movimientos de puntos registrados.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($movimientosPuntos as $mov)
                            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-white/5 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-semibold text-white block">{{ $mov->concepto ?? 'Acumulación de puntos' }}</span>
                                    <span class="text-slate-400 text-[11px] block mt-0.5">
                                        {{ $mov->created_at->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                                <span class="font-bold font-mono {{ $mov->puntos >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $mov->puntos > 0 ? '+'.$mov->puntos : $mov->puntos }} pts
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 text-center text-xs text-slate-400 border-t border-white/5">
        &copy; {{ date('Y') }} RestoMaster &bull; Programa de Recompensas
    </footer>
</body>
</html>
