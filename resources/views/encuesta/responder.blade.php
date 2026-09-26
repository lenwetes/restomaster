<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Encuesta de Satisfacción — RestoMaster</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-slate-100 flex flex-col justify-between selection:bg-rose-500 selection:text-white bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900 via-slate-950 to-slate-950">
    <!-- Header -->
    <header class="w-full border-b border-white/10 bg-slate-900/60 backdrop-blur-md px-6 py-4 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#261a15] to-[#1e1410] border border-[#e0442e]/30 p-1.5 shadow-md shadow-[#e0442e]/10 flex items-center justify-center">
                <x-application-logo class="w-full h-full text-[#e0442e]" />
            </div>
            <span class="text-lg font-black tracking-tight text-white">RESTO<span class="text-[#e0442e]">MASTER</span></span>
        </a>
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30 flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">stars</span>
            +50 pts Recompensa
        </span>
    </header>

    <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 md:p-8 flex items-center justify-center">
        <div class="w-full bg-slate-900/80 border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

            @if (! empty($completada))
                <!-- Estado: Encuesta Completada con Éxito -->
                <div class="text-center py-6 space-y-4">
                    <div class="w-20 h-20 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center mx-auto text-emerald-400">
                        <span class="material-symbols-outlined text-5xl">verified</span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black text-white">¡Muchas gracias por tu opinión!</h1>
                    <p class="text-slate-300 text-sm max-w-md mx-auto">
                        Tus respuestas nos ayudan a seguir ofreciéndote la mejor experiencia gastronómica.
                    </p>

                    <div class="p-4 rounded-2xl bg-gradient-to-r from-amber-950/60 to-rose-950/60 border border-amber-500/30 inline-block my-4">
                        <span class="text-xs font-semibold uppercase tracking-wider text-amber-300 block mb-1">Has ganado</span>
                        <div class="text-3xl font-black text-white flex items-center justify-center gap-1">
                            <span>+{{ $puntosGanados }}</span>
                            <span class="text-amber-400 text-lg">pts</span>
                        </div>
                        <span class="text-[11px] text-slate-400 mt-1 block">Acreditados a tu cuenta RestoMaster Club</span>
                    </div>

                    <div>
                        <a href="{{ route('cliente.perfil') }}"
                           class="inline-flex items-center gap-2 py-3 px-6 rounded-xl bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-500 hover:to-amber-500 text-white font-bold text-sm shadow-lg shadow-rose-950/50 transition-all">
                            <span>Ver mis puntos y perfil</span>
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>

            @elseif (! empty($yaRespondida) || $envio->estado === 'respondida')
                <!-- Estado: Ya Respondida -->
                <div class="text-center py-6 space-y-4">
                    <div class="w-16 h-16 rounded-full bg-blue-500/20 border border-blue-500/40 flex items-center justify-center mx-auto text-blue-400">
                        <span class="material-symbols-outlined text-4xl">task_alt</span>
                    </div>
                    <h2 class="text-xl font-bold text-white">Esta encuesta ya fue respondida</h2>
                    <p class="text-slate-400 text-sm">
                        Ya registramos tus respuestas para esta comanda y tus puntos fueron acumulados. ¡Gracias por preferirnos!
                    </p>
                    <a href="{{ route('cliente.perfil') }}" class="inline-block mt-2 text-xs font-bold text-rose-400 hover:underline">
                        Ir a mi perfil de cliente &rarr;
                    </a>
                </div>

            @elseif ($envio->expira_en && $envio->expira_en->isPast())
                <!-- Estado: Expirada -->
                <div class="text-center py-6 space-y-4">
                    <div class="w-16 h-16 rounded-full bg-rose-500/20 border border-rose-500/40 flex items-center justify-center mx-auto text-rose-400">
                        <span class="material-symbols-outlined text-4xl">schedule</span>
                    </div>
                    <h2 class="text-xl font-bold text-white">El enlace ha expirado</h2>
                    <p class="text-slate-400 text-sm">
                        Esta encuesta estuvo disponible durante 72 horas después de tu visita. Podrás calificar en tu próxima experiencia.
                    </p>
                </div>

            @else
                <!-- Formulario de Encuesta -->
                <div class="mb-6">
                    <span class="text-xs font-bold uppercase tracking-wider text-rose-400">Tu Experiencia</span>
                    <h1 class="text-2xl font-black text-white mt-1">{{ $envio->encuesta->nombre }}</h1>
                    <p class="text-xs text-slate-400 mt-1">
                        Hola, <strong>{{ $envio->cliente->nombre }}</strong>. Cuéntanos qué tal estuvo tu comida y servicio.
                    </p>
                </div>

                <form action="{{ route('encuesta.guardar', $envio->token) }}" method="POST" class="space-y-6">
                    @csrf

                    @php
                        $preguntas = $envio->encuesta->preguntas ?? [];
                    @endphp

                    @foreach ($preguntas as $idx => $preg)
                        <div class="p-4 rounded-2xl bg-slate-950/60 border border-white/5 space-y-3">
                            <label class="block text-sm font-semibold text-white">
                                {{ $idx + 1 }}. {{ $preg['pregunta'] ?? 'Pregunta' }}
                            </label>

                            @if (($preg['tipo'] ?? 'estrellas') === 'estrellas')
                                <div class="flex items-center gap-3">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <label class="cursor-pointer group flex flex-col items-center">
                                            <input type="radio"
                                                   name="respuestas[{{ $idx }}]"
                                                   value="{{ $s }}"
                                                   {{ $s === 5 ? 'checked' : '' }}
                                                   class="sr-only peer" />
                                            <div class="w-10 h-10 rounded-xl bg-slate-900 border border-white/10 peer-checked:bg-amber-500 peer-checked:text-slate-950 peer-checked:border-amber-400 flex items-center justify-center text-slate-400 group-hover:scale-110 transition-all">
                                                <span class="material-symbols-outlined text-xl">star</span>
                                            </div>
                                            <span class="text-[10px] text-slate-500 mt-1 peer-checked:text-amber-300 font-bold">{{ $s }}</span>
                                        </label>
                                    @endfor
                                </div>
                            @elseif (($preg['tipo'] ?? '') === 'si_no')
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                                        <input type="radio" name="respuestas[{{ $idx }}]" value="1" checked class="text-rose-600 focus:ring-rose-500" />
                                        <span>Sí, totalmente</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                                        <input type="radio" name="respuestas[{{ $idx }}]" value="0" class="text-rose-600 focus:ring-rose-500" />
                                        <span>No, hay oportunidad</span>
                                    </label>
                                </div>
                            @else
                                <textarea name="respuestas[{{ $idx }}]"
                                          rows="3"
                                          placeholder="Escribe aquí tus comentarios, sugerencias o felicitaciones..."
                                          class="w-full p-3 bg-slate-900 border border-white/10 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500/50 focus:border-rose-500 transition-colors"></textarea>
                            @endif
                        </div>
                    @endforeach

                    <button type="submit"
                            class="w-full py-3 px-6 rounded-2xl bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-500 hover:to-amber-500 text-white font-bold text-sm shadow-xl shadow-rose-950/50 flex items-center justify-center gap-2 transition-all">
                        <span>Enviar respuestas y ganar puntos</span>
                        <span class="material-symbols-outlined text-lg">celebration</span>
                    </button>
                </form>
            @endif
        </div>
    </main>

    <footer class="py-4 text-center text-xs text-slate-400 border-t border-white/5">
        &copy; {{ date('Y') }} RestoMaster &bull; Control de Calidad y Fidelización
    </footer>
</body>
</html>
