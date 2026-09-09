@component('layouts.guest')
    <div class="w-full px-4 py-10">
        <div class="rounded-3xl border border-outline-variant/20 bg-white p-6 shadow-sm">
            <span class="material-symbols-outlined text-[28px] text-primary">event_available</span>
            <h1 class="mt-2 text-xl font-extrabold text-on-surface">Reserva en SushiXpress</h1>
            <p class="mt-1 text-xs text-on-surface-variant">Elige fecha y franja; nuestro equipo confirmará tu reserva.</p>

            <form method="GET" action="{{ route('reservas.publico') }}" class="mt-5 flex flex-wrap items-end gap-3">
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Fecha</label>
                    <input type="date" name="fecha" value="{{ $fecha }}" min="{{ now()->toDateString() }}" class="mt-1 rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Personas</label>
                    <input type="number" name="personas" value="{{ $personas }}" min="1" class="mt-1 rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <button class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary">Ver disponibilidad</button>
            </form>

            <form method="POST" action="{{ route('reservas.publico') }}" class="mt-6 space-y-3">
                @csrf
                <input type="hidden" name="fecha" value="{{ $fecha }}" />
                <input type="hidden" name="personas" value="{{ $personas }}" />
                <div class="hidden">
                    <label>No llenar</label>
                    <input type="text" name="empresa" tabindex="-1" autocomplete="off" />
                </div>

                <div class="max-h-40 overflow-y-auto rounded-xl border border-outline-variant/20 p-2">
                    @foreach ($franjas as $franja)
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm {{ $franja['disponible'] ? 'hover:bg-surface-container-low' : 'opacity-40' }}">
                            <input type="radio" name="hora" value="{{ $franja['hora'] }}" {{ $franja['disponible'] ? '' : 'disabled' }} {{ $loop->first ? 'checked' : '' }} />
                            <span class="font-bold">{{ $franja['hora'] }}</span>
                            <span class="text-[11px] text-on-surface-variant">{{ $franja['disponible'] ? 'Disponible' : 'Ocupada' }}</span>
                        </label>
                    @endforeach
                </div>

                <input type="text" name="nombre" placeholder="Nombre completo" required class="w-full rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                <input type="tel" name="telefono" placeholder="Teléfono" required class="w-full rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                <textarea name="notas" placeholder="Notas (opcional)" rows="2" class="w-full rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0"></textarea>

                <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary">Solicitar reserva</button>
            </form>

            @if (session('reservado'))
                <p class="mt-4 rounded-xl bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary">¡Reserva solicitada! Te confirmaremos por teléfono.</p>
            @endif
        </div>
    </div>
@endcomponent