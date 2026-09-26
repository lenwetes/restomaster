<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso Clientes — RestoMaster</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-slate-100 flex flex-col justify-between selection:bg-rose-500 selection:text-white">
    <!-- Barra superior simple -->
    <header class="w-full border-b border-white/10 bg-slate-900/60 backdrop-blur-md px-6 py-4 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3 group">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#261a15] to-[#1e1410] border border-[#e0442e]/30 p-1.5 shadow-md shadow-[#e0442e]/10 group-hover:border-[#e0442e]/60 transition-all flex items-center justify-center">
                <x-application-logo class="w-full h-full text-[#e0442e]" />
            </div>
            <span class="text-lg font-black tracking-tight text-white group-hover:text-[#e0442e] transition-colors">RESTO<span class="text-[#e0442e]">MASTER</span></span>
        </a>
        <a href="/" class="text-xs font-semibold text-slate-400 hover:text-white flex items-center gap-1 transition-colors">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
            Volver al inicio
        </a>
    </header>

    <!-- Contenido principal -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 md:p-10 relative overflow-hidden">
        <!-- Glows decorativos de fondo -->
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-rose-600/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-600/15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="w-full max-w-md bg-slate-900/80 border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl shadow-2xl relative z-10">
            <!-- Icono y Título -->
            <div class="text-center mb-8">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-rose-500/20 to-amber-500/20 border border-white/10 flex items-center justify-center mx-auto mb-3 text-rose-400 shadow-inner">
                    <span class="material-symbols-outlined text-3xl">loyalty</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-white">Club de Clientes</h1>
                <p class="text-sm text-slate-400 mt-1">Acumula puntos en cada visita, canjea beneficios y accede a promociones exclusivas.</p>
            </div>

            <!-- Alertas / Flashes -->
            @if (session('error'))
                <div class="mb-5 p-3.5 rounded-xl bg-rose-950/80 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-base shrink-0 text-rose-400">error</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if (session('info'))
                <div class="mb-5 p-3.5 rounded-xl bg-blue-950/80 border border-blue-500/30 text-blue-300 text-xs flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-base shrink-0 text-blue-400">info</span>
                    <span>{{ session('info') }}</span>
                </div>
            @endif

            @if (session('magic_sent'))
                <div class="mb-6 p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/40 text-emerald-200 text-xs">
                    <div class="flex items-center gap-2 font-bold text-sm mb-1 text-emerald-300">
                        <span class="material-symbols-outlined text-base">mark_email_read</span>
                        ¡Enlace mágico enviado!
                    </div>
                    <p class="text-slate-300 leading-relaxed">
                        Revisa la bandeja de <strong>{{ session('magic_email') }}</strong> y haz clic en el enlace para entrar sin contraseña (válido por 20 minutos).
                    </p>
                    @if (session('magic_link_debug') && app()->environment('local', 'testing'))
                        <div class="mt-3 pt-2.5 border-t border-emerald-500/20 text-[11px] break-all">
                            <span class="font-semibold text-emerald-400 block mb-0.5">Enlace de acceso directo (modo dev):</span>
                            <a href="{{ session('magic_link_debug') }}" class="underline text-emerald-300 hover:text-white">
                                Acceder directamente aquí &rarr;
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Botón Social Google -->
            <div class="space-y-3">
                <a href="{{ route('cliente.auth.provider', 'google') }}"
                   class="w-full py-3 px-4 rounded-xl border border-white/15 bg-white hover:bg-slate-100 text-slate-900 font-semibold text-sm flex items-center justify-center gap-3 transition-all duration-200 shadow-md hover:shadow-lg hover:scale-[1.01] active:scale-[0.99]">
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Continuar con Google</span>
                </a>
            </div>

            <!-- Separador -->
            <div class="relative my-6 flex items-center justify-center">
                <div class="w-full border-t border-white/10"></div>
                <span class="bg-slate-900 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 absolute">o con enlace a tu correo</span>
            </div>

            <!-- Formulario Magic Link -->
            <form action="{{ route('cliente.magic_send') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Correo Electrónico
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg">mail</span>
                        <input type="email"
                               id="email"
                               name="email"
                               required
                               placeholder="tu-correo@ejemplo.com"
                               value="{{ old('email') }}"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-950/70 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500/50 focus:border-rose-500 transition-colors" />
                    </div>
                    @error('email')
                        <p class="text-rose-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-500 hover:to-amber-500 text-white font-semibold text-sm flex items-center justify-center gap-2 transition-all shadow-md shadow-rose-950/50">
                    <span class="material-symbols-outlined text-lg">send</span>
                    <span>Enviar enlace de acceso</span>
                </button>
            </form>

            <!-- Términos -->
            <p class="text-[11px] text-center text-slate-400 mt-6 leading-relaxed">
                Al continuar aceptas nuestra política de tratamiento de datos personales (Ley 1581 de 2012) y el programa de fidelización RestoMaster Club.
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 text-center text-xs text-slate-400 border-t border-white/5">
        &copy; {{ date('Y') }} RestoMaster. Todos los derechos reservados.
    </footer>
</body>
</html>
