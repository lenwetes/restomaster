@component('layouts.publico', ['title' => 'Reserva de Mesa · RestoMaster Provenza'])
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-8">
        
        <!-- Header Banner -->
        <div class="text-center max-w-xl mx-auto space-y-3">
            <span class="px-3.5 py-1 rounded-full bg-amber-50 text-amber-600 text-xs font-black uppercase tracking-wider border border-amber-200">
                📅 Salón Principal, Terraza & Barra
            </span>
            <h1 class="text-3xl sm:text-4xl font-black text-stone-900 tracking-tight">
                Reserva tu Mesa en RestoMaster
            </h1>
            <p class="text-xs sm:text-sm text-stone-500 leading-relaxed">
                Vía Provenza, El Poblado, Medellín. Selecciona la fecha, el número de comensales y tu horario preferido.
            </p>
        </div>

        @if (session('reservado'))
            <!-- Success Confirmation Card -->
            <div class="p-6 sm:p-10 rounded-3xl bg-white border border-emerald-200 text-center space-y-4 shadow-lg animate-fade-in">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-500 shadow-md">
                    <span class="material-symbols-outlined text-3xl">task_alt</span>
                </div>
                <div class="space-y-1">
                    <h2 class="text-xl sm:text-2xl font-black text-stone-900">¡Solicitud de Reserva Registrada!</h2>
                    <p class="text-xs sm:text-sm text-stone-500 max-w-md mx-auto">
                        Hemos recibido tu solicitud para el día <span class="text-amber-500 font-bold font-mono">{{ $fecha }}</span>. Nuestro anfitrión te contactará vía WhatsApp para confirmar tu mesa.
                    </p>
                </div>
                <div class="pt-4 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('carta.publico') }}" class="px-4 py-2 rounded-xl bg-[#ff5436] hover:bg-[#e0381d] text-white text-xs font-black transition-all">
                        Explorar la Carta
                    </a>
                    <a href="{{ route('reservas.publico') }}" class="px-4 py-2 rounded-xl bg-white hover:bg-stone-50 text-stone-700 text-xs font-bold border border-stone-200 transition-all">
                        Hacer otra reserva
                    </a>
                </div>
            </div>
        @else
            <!-- Main Booking Box -->
            <div class="bg-white border border-stone-200 rounded-3xl p-6 sm:p-10 shadow-lg space-y-8">
                
                <!-- PASO 1: Consulta de Fecha y Personas (GET Form) -->
                <div class="pb-6 border-b border-stone-100 space-y-4">
                    <div class="flex items-center gap-2 text-amber-500">
                        <span class="material-symbols-outlined text-[20px]">calendar_today</span>
                        <h2 class="text-sm font-black uppercase tracking-wider text-stone-900">Paso 1: Fecha y Comensales</h2>
                    </div>

                    <form method="GET" action="{{ route('reservas.publico') }}" id="filtroReservaForm" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                        <div class="sm:col-span-5">
                            <label class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1.5">
                                Fecha de la Reserva
                            </label>
                            <input 
                                type="date" 
                                name="fecha" 
                                value="{{ $fecha }}" 
                                min="{{ now()->toDateString() }}" 
                                class="w-full px-4 py-2.5 rounded-2xl border border-stone-200 bg-stone-50 text-sm font-semibold text-stone-900 focus:border-amber-400 focus:ring-0 outline-none transition-all font-mono"
                                onchange="document.getElementById('filtroReservaForm').submit();"
                            />
                        </div>

                        <div class="sm:col-span-4">
                            <label class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1.5">
                                Número de Personas
                            </label>
                            <input 
                                type="number" 
                                name="personas" 
                                value="{{ $personas }}" 
                                min="1" 
                                max="20"
                                class="w-full px-4 py-2.5 rounded-2xl border border-stone-200 bg-stone-50 text-sm font-semibold text-stone-900 focus:border-amber-400 focus:ring-0 outline-none transition-all font-mono"
                                onchange="document.getElementById('filtroReservaForm').submit();"
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <button type="submit" class="w-full py-2.5 px-4 rounded-2xl bg-amber-50 hover:bg-amber-100 text-amber-600 font-black text-xs border border-amber-200 hover:border-amber-400 flex items-center justify-center gap-1.5 transition-all">
                                <span class="material-symbols-outlined text-[16px]">search</span>
                                <span>Ver Franjas</span>
                            </button>
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
                        <label>No llenar</label>
                        <input type="text" name="empresa" tabindex="-1" autocomplete="off" />
                    </div>

                    <!-- Horarios Disponibles -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-amber-500">
                                <span class="material-symbols-outlined text-[20px]">schedule</span>
                                <h2 class="text-sm font-black uppercase tracking-wider text-stone-900">Paso 2: Selecciona la Franja Horaria</h2>
                            </div>
                            <span class="text-[11px] text-stone-400 font-mono">Turnos de 2 horas</span>
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
                                <label class="relative flex flex-col p-3 rounded-2xl border text-center transition-all {{ $franja['disponible'] ? 'border-stone-200 bg-stone-50 hover:border-[#ff5436] hover:bg-[#ff5436]/5 cursor-pointer has-[:checked]:bg-[#ff5436]/10 has-[:checked]:border-[#ff5436] has-[:checked]:shadow-md' : 'border-stone-100 bg-stone-50/50 opacity-40 cursor-not-allowed' }}">
                                    <input 
                                        type="radio" 
                                        name="hora" 
                                        value="{{ $franja['hora'] }}" 
                                        {{ $franja['disponible'] ? '' : 'disabled' }} 
                                        {{ $checkMe ? 'checked' : '' }}
                                        class="sr-only"
                                    />
                                    <span class="font-mono font-black text-sm text-stone-900">{{ $franja['hora'] }}</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider mt-1 {{ $franja['disponible'] ? 'text-emerald-500' : 'text-stone-400' }}">
                                        {{ $franja['disponible'] ? 'Disponible' : 'Ocupada' }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @if ($franjas->where('disponible', true)->isEmpty())
                            <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-600 text-base">info</span>
                                <span>No hay franjas con capacidad disponible para este día. Por favor prueba con otra fecha u horario.</span>
                            </div>
                        @endif
                        @error('hora')
                            <p class="text-xs text-rose-500 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Datos del Cliente -->
                    <div class="space-y-4 pt-4 border-t border-stone-100">
                        <div class="flex items-center gap-2 text-amber-500">
                            <span class="material-symbols-outlined text-[20px]">person</span>
                            <h2 class="text-sm font-black uppercase tracking-wider text-stone-900">Paso 3: Tus Datos de Contacto</h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">
                                    Nombre y Apellido *
                                </label>
                                <input 
                                    type="text" 
                                    name="nombre" 
                                    placeholder="Ej. Sofía Restrepo" 
                                    required 
                                    class="w-full px-4 py-2.5 rounded-2xl border border-stone-200 bg-stone-50 text-sm font-semibold text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none transition-all"
                                />
                                @error('nombre')
                                    <p class="text-xs text-rose-500 font-bold mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">
                                    Teléfono / WhatsApp *
                                </label>
                                <input 
                                    type="tel" 
                                    name="telefono" 
                                    placeholder="Ej. 300 123 4567" 
                                    required 
                                    class="w-full px-4 py-2.5 rounded-2xl border border-stone-200 bg-stone-50 text-sm font-semibold text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none transition-all font-mono"
                                />
                                @error('telefono')
                                    <p class="text-xs text-rose-500 font-bold mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">
                                Ocasión Especial o Notas (Opcional)
                            </label>
                            <textarea 
                                name="notas" 
                                rows="2" 
                                placeholder="Ej. Aniversario, cumpleaños, preferencia de barra omakase o alergia a mariscos..."
                                class="w-full px-4 py-2.5 rounded-2xl border border-stone-200 bg-stone-50 text-sm font-semibold text-stone-900 placeholder-stone-400 focus:border-[#ff5436] focus:ring-0 outline-none transition-all"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button 
                            type="submit" 
                            class="w-full py-3.5 px-6 rounded-2xl bg-[#ff5436] hover:bg-[#e0381d] text-white font-black text-sm shadow-xl shadow-[#ff5436]/25 hover:shadow-[#ff5436]/40 transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>Solicitar Reserva en RestoMaster</span>
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif

    </div>
@endcomponent