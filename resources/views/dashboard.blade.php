<x-app-layout>
    <?php $kpis = app(\App\Services\ReporteService::class)->kpisRealtime(); ?>
    <div class="flex flex-col gap-6">
        <!-- Encabezado de Bienvenida y Estado Ejecutivo (DASH-01 Aura Gastro) -->
        <div class="w-full bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-surface-container-highest flex flex-col xl:flex-row items-start xl:items-center justify-between gap-4">
            <div class="flex flex-col gap-1.5">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container text-xs font-bold tracking-wide">
                        SEDE EL POBLADO · MEDELLÍN
                    </span>
                    <span class="inline-flex items-center gap-1 text-xs text-secondary font-bold">
                        <span class="h-2 w-2 rounded-full bg-secondary animate-pulse"></span>
                        Sistema En Línea · Stream Activo
                    </span>
                </div>
                <h1 class="text-2xl lg:text-3xl font-extrabold text-on-surface tracking-tight">
                    Panel Ejecutivo Aura Gastro
                </h1>
                <div class="flex items-center gap-2 text-on-surface-variant text-xs sm:text-sm font-medium">
                    <span class="material-symbols-outlined text-[18px] text-tertiary">wb_sunny</span>
                    <span>Turno Almuerzo / Tarde (Activo)</span>
                    <span class="text-outline-variant">•</span>
                    <span class="font-bold text-on-surface">{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</span>
                </div>
            </div>

            <!-- Botonera de Acciones Rápidas Táctiles (Touch min 48px) -->
            <div class="flex flex-wrap items-center gap-2.5 w-full xl:w-auto">
                <a 
                    href="{{ route('pos') }}" 
                    wire:navigate
                    class="flex-1 sm:flex-none h-12 px-5 rounded-2xl bg-primary hover:bg-primary-container text-on-primary font-bold text-sm flex items-center justify-center gap-2 shadow-md shadow-primary/20 transition-all active:scale-95"
                >
                    <span class="material-symbols-outlined text-[20px]">add_circle</span>
                    <span>+ Nueva Comanda</span>
                    <span class="text-xs opacity-75 font-mono ml-0.5">POS-01</span>
                </a>

                <a 
                    href="{{ route('caja') }}" 
                    wire:navigate
                    class="flex-1 sm:flex-none h-12 px-4 rounded-2xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-bold text-sm flex items-center justify-center gap-2 transition-all active:scale-95 border border-surface-container-highest"
                >
                    <span class="material-symbols-outlined text-secondary text-[20px]">account_balance_wallet</span>
                    <span>Arqueo de Caja</span>
                </a>

                <a 
                    href="{{ route('mesas') }}" 
                    wire:navigate
                    class="flex-1 sm:flex-none h-12 px-4 rounded-2xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-bold text-sm flex items-center justify-center gap-2 transition-all active:scale-95 border border-surface-container-highest"
                >
                    <span class="material-symbols-outlined text-primary text-[20px]">table_restaurant</span>
                    <span>Ver Mesas</span>
                </a>
            </div>
        </div>

        <!-- Tarjetas Bento de KPIs Expresivas (Aura Gastro Expressive OS) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <!-- KPI 1: Ventas Hoy -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
                <div class="flex items-start justify-between">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Ventas Facturadas Hoy</span>
                        <span class="text-2xl lg:text-3xl font-extrabold text-on-surface mt-1 tracking-tight">
                            ${{ number_format($kpis['ventas_dia'], 0, ',', '.') }} <span class="text-xs font-bold text-outline">COP</span>
                        </span>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[22px]">payments</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container flex items-center justify-between text-xs">
                    <span class="text-on-surface-variant">{{ $kpis['transacciones_dia'] }} transacciones</span>
                    <span class="inline-flex items-center gap-1 font-bold text-secondary">
                        <span class="material-symbols-outlined text-[16px]">star</span> {{ $kpis['top_productos_hoy'][0]['producto'] ?? 'Sin ventas' }}
                    </span>
                </div>
            </div>

            <!-- KPI 2: Ticket Promedio -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
                <div class="flex items-start justify-between">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Ticket Promedio</span>
                        <span class="text-2xl lg:text-3xl font-extrabold text-on-surface mt-1 tracking-tight">
                            ${{ number_format($kpis['ticket_promedio'], 0, ',', '.') }} <span class="text-xs font-bold text-outline">COP</span>
                        </span>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-tertiary-container/20 border border-tertiary-container/30 flex items-center justify-center text-tertiary">
                        <span class="material-symbols-outlined text-[22px]">receipt</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container flex items-center justify-between text-xs">
                    <span class="text-on-surface-variant">Food cost de hoy</span>
                    <span class="font-bold text-tertiary">{{ $kpis['food_cost_porcentaje'] }}%</span>
                </div>
            </div>

            <!-- KPI 3: Ocupación de Salón -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
                <div class="flex items-start justify-between">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Ocupación de Salón</span>
                        <span class="text-2xl lg:text-3xl font-extrabold text-secondary mt-1 tracking-tight">
                            {{ $kpis['mesas_ocupadas'] }} <span class="text-xs font-medium text-on-surface-variant">mesas ocupadas</span>
                        </span>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-secondary-container/30 border border-secondary-container/40 flex items-center justify-center text-secondary">
                        <span class="material-symbols-outlined text-[22px]">table_restaurant</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container flex flex-col gap-1 text-xs">
                    <div class="w-full bg-surface-container h-2 rounded-full overflow-hidden">
                        <div class="bg-secondary h-full rounded-full" style="width: {{ min(100, $kpis['mesas_ocupadas'] * 10) }}%"></div>
                    </div>
                    <div class="flex justify-between text-on-surface-variant text-[11px] font-semibold mt-0.5">
                        <span>{{ $kpis['mesas_ocupadas'] }} en servicio</span>
                        <span class="text-secondary font-bold">Ocupación en vivo</span>
                    </div>
                </div>
            </div>

            <!-- KPI 4: Comandas en Cocina -->
            <div class="bg-surface-container-lowest rounded-3xl p-5 shadow-sm border border-surface-container-highest flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
                <div class="flex items-start justify-between">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Comandas KDS Activas</span>
                        <span class="text-2xl lg:text-3xl font-extrabold text-primary mt-1 tracking-tight">
                            {{ $kpis['comandas_cocina_activas'] }} en cocina
                        </span>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[22px]">skillet</span>
                    </div>
                </div>
                <div class="mt-4 pt-2 border-t border-surface-container flex items-center justify-between text-xs">
                    <span class="text-on-surface-variant">Comandas activas ahora</span>
                    <span class="font-bold text-secondary">KDS en vivo</span>
                </div>
            </div>
        </div>

        <!-- Lanzadera de Módulos Operativos (Aura Gastro Expressive Cards) -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-base font-extrabold text-on-surface tracking-tight">
                        Lanzadera de Operaciones Táctiles
                    </h2>
                    <p class="text-xs text-on-surface-variant">Accesos rápidos optimizados para flujo de servicio continuo</p>
                </div>
                <span class="text-xs font-mono font-bold text-secondary bg-secondary-container/40 px-2.5 py-1 rounded-full border border-secondary/20">
                    Aura Gastro Expressive OS
                </span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- POS Terminal Card -->
                <a 
                    href="{{ route('pos') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-2xl group-hover:scale-105 transition-transform border border-primary/20">
                                🍣
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · POS-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Punto de Venta (POS Táctil)
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Terminal táctil con comandas split-view 65/35, notas de preparación, modificadores y cobro multimoneda.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Iniciar Comanda</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Mesas Card -->
                <a 
                    href="{{ route('mesas') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary-container/40 text-2xl group-hover:scale-105 transition-transform border border-secondary/20">
                                🪑
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · MES-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Salón & Mapa de Mesas
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Control visual de zonas (Salón, Barra, Terraza), aforo en tiempo real y asignación rápida de pedidos.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Ver Mapa del Restaurante</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Cocina KDS Card -->
                <a 
                    href="{{ route('cocina') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-2xl group-hover:scale-105 transition-transform border border-primary/20">
                                👨‍🍳
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · COC-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Cocina & Barra (KDS)
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Swimlanes de producción FIFO, control estricto de SLA (<15 min) y deducción automática de stock de recetas al despachar.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Abrir Monitor de Cocina</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Caja & Turnos Card -->
                <a 
                    href="{{ route('caja') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-tertiary-container/20 text-2xl group-hover:scale-105 transition-transform border border-tertiary-container/30">
                                💵
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · CAJ-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Caja, Turnos & Arqueo
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Apertura con fondo inicial, egresos/retiros, arqueo ciego, cuadre de caja y emisión fiscal de Reporte Z.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Gestionar Caja y Turno</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Inventario & Recetas Card -->
                <a 
                    href="{{ route('inventario') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-2xl group-hover:scale-105 transition-transform border border-primary/20">
                                📦
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · INV-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Inventario & Recetas (Kardex)
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Escandallos gastronómicos, costeo ponderado continuo NIIF, alertas de stock crítico y registro de mermas operativas.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Gestionar Kardex e Insumos</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Carta & Menú Card (MEN-01) -->
                <a 
                    href="{{ route('menu') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-2xl group-hover:scale-105 transition-transform border border-primary/20">
                                🍱
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · MEN-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Carta & Menú (Platos y Servicios)
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Creación de nuevos platos, rolls, bebidas y combos, fijación de precios, áreas de preparación y categorización en vivo.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Gestionar Carta y Productos</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Clientes & Fidelización Card (Fase 4) -->
                <a 
                    href="{{ route('clientes') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary-container/30 text-2xl group-hover:scale-105 transition-transform border border-secondary-container/40">
                                ⭐
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · CLI-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Clientes VIP & Fidelización
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Directorio 360°, acumulación y canje de puntos de lealtad ($10.000 = 1 pt), historial de comandas y libretas de direcciones.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Directorio & Puntos</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Despacho Delivery Card (Fase 4) -->
                <a 
                    href="{{ route('delivery') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-2xl group-hover:scale-105 transition-transform border border-primary/20">
                                🛵
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · PED-04
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Despacho Delivery & Flota
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Cola de pedidos para entrega a domicilio, asignación a motorizados, tracking de tiempos en ruta y liquidación de efectivo contra entrega.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Control de Despacho</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Reportes DIAN Card (Fase 5) -->
                <a 
                    href="{{ route('reportes') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-2xl group-hover:scale-105 transition-transform border border-primary/20">
                                📊
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · REP-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Reportes DIAN & Analítica
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Estado de resultados, ventas por canal/producto, clientes, reservas y exportación PDF/CSV.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Ver Reportes</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Reservas Card (Fase 5) -->
                <a 
                    href="{{ route('reservas') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary-container/30 text-2xl group-hover:scale-105 transition-transform border border-secondary-container/40">
                                🗓️
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · RES-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Reservas & Agenda
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Agenda de reservas del día, creación, confirmación de mesas y trazabilidad de estados.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Gestionar Reservas</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>

                <!-- Impresión & Spooler Card (Fase 6) -->
                <a 
                    href="{{ route('impresion') }}" 
                    wire:navigate
                    class="group relative flex flex-col justify-between rounded-3xl border border-surface-container-highest bg-surface-container-lowest p-5 transition-all hover:border-primary hover:shadow-lg active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-2xl group-hover:scale-105 transition-transform border border-primary/20">
                                🖨️
                            </span>
                            <span class="rounded-full bg-secondary-container/50 border border-secondary/30 px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">
                                ✓ Operativo · IMP-01
                            </span>
                        </div>
                        <h3 class="mt-4 text-base font-extrabold text-on-surface group-hover:text-primary transition-colors">
                            Spooler & Impresión en Red
                        </h3>
                        <p class="mt-1.5 text-xs text-on-surface-variant leading-relaxed">
                            Cola asíncrona de impresión térmica (80mm), sockets TCP/IP por área, visor de cinta y reimpresión histórica auditada.
                        </p>
                    </div>
                    <div class="mt-5 pt-3 border-t border-surface-container flex items-center justify-between text-xs font-bold text-primary">
                        <span>Monitor de Spooler</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
