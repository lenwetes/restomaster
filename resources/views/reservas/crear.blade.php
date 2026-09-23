@component('layouts.publico', ['title' => 'Reserva de Mesa · RestoMaster Provenza Medellín'])
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-14 space-y-10">
        
        <!-- Header Banner -->
        <div class="text-center max-w-2xl mx-auto space-y-3">
            <span class="px-3.5 py-1 rounded-full bg-[#e8a020]/10 text-[#e8a020] text-xs font-black uppercase tracking-wider border border-[#e8a020]/30 font-mono">
                📅 Salón Principal, Terraza & Barra Omakase
            </span>
            <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                Reserva tu Mesa en RestoMaster
            </h1>
            <p class="text-xs sm:text-base text-[#c4a89e] leading-relaxed">
                Vía Provenza, El Poblado, Medellín. Selecciona la fecha, el número de comensales y tu horario preferido para asegurar tu momento gastronómico.
            </p>
        </div>

        @if (session('reservado'))
            <!-- Success Confirmation Card -->
            <div class="p-8 sm:p-12 rounded-3xl bg-[#1e1410] border border-emerald-500/40 text-center space-y-6 shadow-2xl animate-fade-in max-w-2xl mx-auto">
                <div class="w-20 h-20 mx-auto rounded-3xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-xl shadow-emerald-500/10">
                    <span class="material-symbols-outlined text-4xl">task_alt</span>
                </div>
                <div class="space-y-2">
                    <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-[11px] font-black uppercase tracking-wider font-mono border border-emerald-500/20">
                        Solicitud Confirmada
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-black text-white">¡Solicitud de Reserva Registrada!</h2>
                    <p class="text-xs sm:text-sm text-[#c4a89e] max-w-md mx-auto leading-relaxed">
                        Hemos recibido tu solicitud para el día <span class="text-[#e8a020] font-black font-mono">{{ $fecha }}</span>. Nuestro anfitrión te contactará vía WhatsApp para reconfirmar la asignación de tu mesa.
                    </p>
                </div>

                @php
                    $wpReservaMsg = urlencode("¡Hola RestoMaster! Acabo de registrar una solicitud de reserva para el {$fecha} para {$personas} personas. ¿Me confirman disponibilidad de mesa por favor?");
                @endphp

                <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a 
                        href="https://wa.me/573001234567?text={{ $wpReservaMsg }}" 
                        target="_blank" 
                        class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all"
                    >
                        <span class="material-symbols-outlined text-[18px]">chat</span>
                        <span>Confirmar de Inmediato por WhatsApp</span>
                    </a>
                    <a 
                        href="{{ route('carta.publico') }}" 
                        class="w-full sm:w-auto px-5 py-3 rounded-2xl bg-[#261a15] hover:bg-[#38271f] text-[#f5e8e2] text-xs font-bold border border-[#432f26] transition-all"
                    >
                        Explorar la Carta
                    </a>
                </div>
            </div>
        @else
            <!-- Main Booking Experience Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Column (8 cols): Step 1, 2, 3 Form -->
                <div class="lg:col-span-8 bg-[#1e1410] border border-[#432f26] rounded-3xl p-6 sm:p-10 shadow-2xl space-y-8">
                    
                    <!-- PASO 1: Consulta de Fecha y Personas (GET Form) -->
                    <div class="pb-6 border-b border-[#432f26]/60 space-y-4">
                        <div class="flex items-center gap-2.5 text-[#e8a020]">
                            <span class="material-symbols-outlined text-[22px]">calendar_today</span>
                            <h2 class="text-sm font-black uppercase tracking-wider text-white">Paso 1: Fecha y Comensales</h2>
                        </div>

                        <form method="GET" action="{{ route('reservas.publico') }}" id="filtroReservaForm" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                            <div class="sm:col-span-6">
                                <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-2 font-mono">
                                    Fecha de la Reserva
                                </label>
                                <input 
                                    type="date" 
                                    name="fecha" 
                                    value="{{ $fecha }}" 
                                    min="{{ now()->toDateString() }}" 
                                    class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-sm font-bold text-white focus:border-[#e8a020] focus:ring-0 outline-none transition-all font-mono"
                                    onchange="document.getElementById('filtroReservaForm').submit();"
                                />
                            </div>

                            <div class="sm:col-span-6">
                                <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-2 font-mono">
                                    Número de Personas
                                </label>
                                <input 
                                    type="number" 
                                    name="personas" 
                                    value="{{ $personas }}" 
                                    min="1" 
                                    max="20"
                                    class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-sm font-bold text-white focus:border-[#e8a020] focus:ring-0 outline-none transition-all font-mono"
                                    onchange="document.getElementById('filtroReservaForm').submit();"
                                />
                            </div>
                        </form>
                    </div>

                    <!-- PASO 2 & 3: Franjas Horarias y Datos de Contacto (POST Form) -->
                    <form method="POST" action="{{ route('reservas.publico') }}" class="space-y-8">
                        @csrf
                        <input type="hidden" name="fecha" value="{{ $fecha }}" />
                        <input type="hidden" name="personas" value="{{ $personas }}" />
                        
                        <!-- Honeypot anti-spam -->
                        <div class="hidden">
                            <label>No llenar este campo</label>
                            <input type="text" name="empresa" tabindex="-1" autocomplete="off" />
                        </div>

                        <!-- PASO 2: Horarios Disponibles -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5 text-[#e8a020]">
                                    <span class="material-symbols-outlined text-[22px]">schedule</span>
                                    <h2 class="text-sm font-black uppercase tracking-wider text-white">Paso 2: Selecciona la Franja Horaria</h2>
                                </div>
                                <span class="text-[11px] text-[#7a5a52] font-mono">Turnos de 2 horas</span>
                            </div>

                            @php $firstAvailableChecked = false; @endphp
                            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-2.5">
                                @foreach ($franjas as $franja)
                                    @php
                                        $checkMe = false;
                                        if (!$firstAvailableChecked && $franja['disponible']) {
                                            $checkMe = true;
                                            $firstAvailableChecked = true;
                                        }
                                    @endphp
                                    <label class="relative flex flex-col p-3 rounded-2xl border text-center transition-all {{ $franja['disponible'] ? 'border-[#432f26] bg-[#261a15] hover:border-[#e0442e] hover:bg-[#e0442e]/10 cursor-pointer has-[:checked]:bg-[#e0442e] has-[:checked]:border-[#e0442e] has-[:checked]:text-white has-[:checked]:shadow-lg shadow-[#e0442e]/25' : 'border-[#432f26]/30 bg-[#140e0b]/50 opacity-30 cursor-not-allowed' }}">
                                        <input 
                                            type="radio" 
                                            name="hora" 
                                            value="{{ $franja['hora'] }}" 
                                            {{ $franja['disponible'] ? '' : 'disabled' }} 
                                            {{ $checkMe ? 'checked' : '' }}
                                            class="sr-only"
                                        />
                                        <span class="font-mono font-black text-sm text-white">{{ $franja['hora'] }}</span>
                                        <span class="text-[10px] font-bold uppercase tracking-wider mt-1 {{ $franja['disponible'] ? 'text-emerald-400' : 'text-[#7a5a52]' }}">
                                            {{ $franja['disponible'] ? 'Disponible' : 'Agotado' }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @if ($franjas->where('disponible', true)->isEmpty())
                                <div class="p-4 rounded-2xl bg-[#e8a020]/10 border border-[#e8a020]/30 text-[#e8a020] text-xs flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px]">info</span>
                                    <span>No hay franjas con capacidad disponible para este día. Por favor prueba con otra fecha o contáctanos por WhatsApp.</span>
                                </div>
                            @endif
                            @error('hora')
                                <p class="text-xs text-rose-400 font-bold">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- PASO 3: Datos del Cliente & Ocasión -->
                        <div class="space-y-4 pt-6 border-t border-[#432f26]/60">
                            <div class="flex items-center gap-2.5 text-[#e8a020]">
                                <span class="material-symbols-outlined text-[22px]">person</span>
                                <h2 class="text-sm font-black uppercase tracking-wider text-white">Paso 3: Tus Datos de Contacto</h2>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-1.5 font-mono">
                                        Nombre y Apellido *
                                    </label>
                                    <input 
                                        type="text" 
                                        name="nombre" 
                                        placeholder="Ej. Sofía Restrepo" 
                                        required 
                                        class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-sm font-semibold text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none transition-all"
                                    />
                                    @error('nombre')
                                        <p class="text-xs text-rose-400 font-bold mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-1.5 font-mono">
                                        Teléfono / WhatsApp *
                                    </label>
                                    <input 
                                        type="tel" 
                                        name="telefono" 
                                        placeholder="Ej. 300 123 4567" 
                                        required 
                                        class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-sm font-semibold text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none transition-all font-mono"
                                    />
                                    @error('telefono')
                                        <p class="text-xs text-rose-400 font-bold mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-[#c4a89e] uppercase tracking-wider mb-1.5 font-mono">
                                    Ocasión Especial o Preferencia (Opcional)
                                </label>
                                <textarea 
                                    name="notas" 
                                    rows="2" 
                                    placeholder="Ej. Aniversario, cumpleaños, preferencia de terraza al aire libre o alergias alimentarias..."
                                    class="w-full px-4 py-3 rounded-2xl border border-[#432f26] bg-[#261a15] text-sm font-semibold text-white placeholder-[#7a5a52] focus:border-[#e0442e] focus:ring-0 outline-none transition-all"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button 
                                type="submit" 
                                class="w-full py-4 px-6 rounded-2xl bg-[#e0442e] hover:bg-[#b8301d] text-white font-black text-sm shadow-xl shadow-[#e0442e]/30 hover:scale-[1.01] transition-all flex items-center justify-center gap-2 cursor-pointer"
                            >
                                <span>Solicitar Reserva en RestoMaster</span>
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </button>
                        </div>
                    </form>

                </div>

                <!-- Right Column (4 cols): Live Reservation Summary & House Policies -->
                <div class="lg:col-span-4 space-y-6">
                    
                    <!-- Live Summary Card -->
                    <div class="p-6 rounded-3xl bg-[#1e1410] border border-[#432f26] shadow-xl space-y-4">
                        <div class="flex items-center gap-2 pb-3 border-b border-[#432f26]/60">
                            <span class="material-symbols-outlined text-[#e8a020] text-[20px]">receipt_long</span>
                            <h3 class="text-sm font-black text-white uppercase tracking-wider">Tu Reserva</h3>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between items-center text-[#c4a89e]">
                                <span>Fecha:</span>
                                <span class="font-bold text-white font-mono">{{ $fecha }}</span>
                            </div>
                            <div class="flex justify-between items-center text-[#c4a89e]">
                                <span>Comensales:</span>
                                <span class="font-bold text-white font-mono">{{ $personas }} {{ $personas === 1 ? 'persona' : 'personas' }}</span>
                            </div>
                            <div class="flex justify-between items-center text-[#c4a89e]">
                                <span>Zona preferida:</span>
                                <span class="font-bold text-[#e8a020]">Salón o Terraza</span>
                            </div>
                            <div class="flex justify-between items-center text-[#c4a89e]">
                                <span>Tolerancia de mesa:</span>
                                <span class="font-bold text-emerald-400">15 minutos</span>
                            </div>
                        </div>
                    </div>

                    <!-- House Policies & VIP Tips -->
                    <div class="p-6 rounded-3xl bg-[#1e1410] border border-[#432f26] shadow-xl space-y-4 text-xs text-[#c4a89e]">
                        <h4 class="font-black text-white uppercase tracking-wider text-[11px] font-mono">
                            Políticas de Experiencia
                        </h4>
                        <ul class="space-y-2.5 leading-relaxed">
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-[#e0442e] text-[16px] shrink-0 mt-0.5">check_circle</span>
                                <span>Garantizamos 15 minutos de espera antes de liberar la mesa en caso de retraso.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-[#e8a020] text-[16px] shrink-0 mt-0.5">check_circle</span>
                                <span>Los turnos estándar tienen una duración de 2 horas para garantizar la calidad del servicio.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-[#2eb8b4] text-[16px] shrink-0 mt-0.5">check_circle</span>
                                <span>Si deseas decoración especial o torta de cumpleaños, indícalo en las notas de tu reserva.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Direct WhatsApp Card -->
                    <div class="p-6 rounded-3xl bg-gradient-to-br from-[#1e1410] to-[#261a15] border border-emerald-500/30 text-center space-y-3">
                        <span class="material-symbols-outlined text-emerald-400 text-3xl">support_agent</span>
                        <div class="space-y-1">
                            <h4 class="font-bold text-white text-xs">¿Grupos de más de 8 personas?</h4>
                            <p class="text-[11px] text-[#c4a89e]">Diseñamos menús especiales para eventos privados y corporativos.</p>
                        </div>
                        <a 
                            href="https://wa.me/573001234567?text=Hola%20RestoMaster!%20Deseo%20cotizar%20un%20evento%20privado" 
                            target="_blank" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 border border-emerald-500/30 text-xs font-bold transition-all"
                        >
                            <span class="material-symbols-outlined text-[16px]">chat</span>
                            <span>Hablar con el Anfitrión</span>
                        </a>
                    </div>

                </div>

            </div>
        @endif

    </div>
@endcomponent