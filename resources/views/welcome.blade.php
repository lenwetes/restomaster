@component('layouts.publico', ['title' => 'RestoMaster — Parrilla de Autor, Pastas Frescas & Coctelería · Provenza'])

    @php
    $slides = [
        [
            'kicker' => '✨ BIENVENIDOS A RESTOMASTER · PROVENZA, MEDELLÍN',
            'badge' => 'Experiencia Gastro-Lounge VIP',
            'invitacion' => 'Te invitamos a vivir una velada mágica bajo las estrellas de Provenza',
            'title' => 'El Fuego, la Técnica y el Placer de Compartir',
            'description' => 'Déjate envolver por una atmósfera donde la calidez de las velas, la música lounge y el aroma a brasa de roble crean el escenario perfecto. Disfruta cortes Angus de maduración prolongada, pastas frescas hechas a mano y mixología de autor diseñada para celebrar momentos inolvidables.',
            'image' => asset('images/resto-terrace-night.jpg'),
            'ctaPrimaryText' => 'Reservar Mesa VIP',
            'ctaPrimaryUrl' => route('reservas.publico'),
            'ctaPrimaryIcon' => 'calendar_month',
            'ctaSecondaryText' => 'Explorar Carta Digital',
            'ctaSecondaryUrl' => route('carta.publico'),
            'ctaSecondaryIcon' => 'restaurant_menu',
            'accentDot' => 'bg-amber-400',
            'badgeClass' => 'border-amber-400/50 bg-amber-400/15 text-amber-300',
            'highlights' => ['Terraza Climatizada & Salón VIP', 'Valet Parking Gratuito', 'Pet-Friendly en Terraza'],
            'review' => [
                'quote' => 'El ambiente más cautivador de Provenza. La atención impecable y la atmósfera nocturna bajo las estrellas hacen que cada cena sea memorable.',
                'author' => 'Mariana Restrepo',
                'role' => 'Guía Gastronómica Medellín',
                'source' => 'Google Reviews 4.9 ★'
            ]
        ],
        [
            'kicker' => '🔥 EL RITUAL DEL FUEGO & LA MADERA NOBLE',
            'badge' => 'Maestría al Fuego Vivo',
            'invitacion' => 'Descubre la auténtica pasión por la parrilla contemporánea',
            'title' => 'Cortes Angus Madurados al Carbón de Roble Silvestre',
            'description' => 'Siente el calor radiante de nuestra brasa de leña seleccionada. Nuestros maestros parrilleros sellan cada corte con costra caramelizada crujiente y un centro jugoso que se deshace en tu boca. Una experiencia para los verdaderos amantes del buen comer, en salón o en tu casa.',
            'image' => asset('images/fire-grill-chef.jpg'),
            'ctaPrimaryText' => 'Pedir Cortes en Delivery Express',
            'ctaPrimaryUrl' => route('delivery.publico'),
            'ctaPrimaryIcon' => 'two_wheeler',
            'ctaSecondaryText' => 'Ver Cortes & Guarniciones',
            'ctaSecondaryUrl' => route('carta.publico'),
            'ctaSecondaryIcon' => 'restaurant_menu',
            'accentDot' => 'bg-[#e0442e]',
            'badgeClass' => 'border-[#e0442e]/50 bg-[#e0442e]/15 text-[#ff7e67]',
            'highlights' => ['Maduración Dry-Aged 45 Días', '100% Black Angus Prime', 'Delivery Térmico 35-45 min'],
            'review' => [
                'quote' => 'El punto de la carne es una obra de arte. El sellado al fuego de roble le otorga un sabor ahumado único que no encuentras en otro restaurante.',
                'author' => 'Chef David Vélez',
                'role' => 'Crítico Culinario Independiente',
                'source' => 'TripAdvisor Travelers Choice'
            ]
        ],
        [
            'kicker' => '👑 PLATILLO INSIGNIA DE LA CASA',
            'badge' => 'Platillo Estrella Más Solicitado',
            'invitacion' => 'Un festín para compartir y deleitar todos los sentidos',
            'title' => 'Tomahawk & Ribeye Prime al Hierro Fundido con Trufas',
            'description' => 'Servido crepitante en mesa sobre sartén de hierro fundido ardiente, bañado en mantequilla artesanal de trufas negras, sal marina volcánica y chimichurri rústico de hierbas de huerto. Cada bocado es una explosión de jugosidad, terneza y sabor profundo.',
            'image' => asset('images/angus-steak.jpg'),
            'ctaPrimaryText' => 'Pedir a Domicilio Express',
            'ctaPrimaryUrl' => route('delivery.publico'),
            'ctaPrimaryIcon' => 'shopping_bag',
            'ctaSecondaryText' => 'Ver Carta de Cortes',
            'ctaSecondaryUrl' => route('carta.publico'),
            'ctaSecondaryIcon' => 'menu_book',
            'accentDot' => 'bg-[#e0442e]',
            'badgeClass' => 'border-[#e0442e]/50 bg-[#e0442e]/15 text-[#ff7e67]',
            'highlights' => ['Porción para Compartir', 'Papas Rústicas Trufadas', 'Chimichurri de la Huerta'],
            'review' => [
                'quote' => 'El Tomahawk de RestoMaster es apoteósico. Llegó a la mesa chisporroteando, con un aroma a mantequilla y trufa que nos dejó sin palabras.',
                'author' => 'Santiago Arango',
                'role' => 'Comensal Frecuente',
                'source' => 'Google Reviews 5.0 ★'
            ]
        ],
        [
            'kicker' => '🍝 TRADICIÓN ITALIANA & ALTA CULINARIA',
            'badge' => 'Pasta Fatta a Mano Cada Mañana',
            'invitacion' => 'El reconfortante sabor de la pasta fresca amasada al instante',
            'title' => 'Fettuccine al Tartufo Nero & Parmigiano de 24 Meses',
            'description' => 'Amasamos nuestra pasta fresca todos los días con sémola de trigo duro y yemas de campo. Mantecado en rueda de queso Parmigiano Reggiano DOP y coronado generosamente con láminas frescas de trufa negra de origen. Una caricia sedosa al paladar.',
            'image' => asset('images/truffle-pasta.jpg'),
            'ctaPrimaryText' => 'Pedir Pasta en Delivery Express',
            'ctaPrimaryUrl' => route('delivery.publico'),
            'ctaPrimaryIcon' => 'two_wheeler',
            'ctaSecondaryText' => 'Ver Menú de Pastas',
            'ctaSecondaryUrl' => route('carta.publico'),
            'ctaSecondaryIcon' => 'restaurant_menu',
            'accentDot' => 'bg-amber-400',
            'badgeClass' => 'border-amber-400/50 bg-amber-400/15 text-amber-300',
            'highlights' => ['Pasta Fresca del Día', 'Trufa Negra Fresca', 'Parmesano 24 Meses DOP'],
            'review' => [
                'quote' => 'El aroma a trufa fresca inunda la mesa desde que sale el plato. La mejor pasta fresca que se puede saborear en Medellín.',
                'author' => 'Valentina Duque',
                'role' => 'Food Critic & Blogger',
                'source' => 'Instagram Food Review'
            ]
        ],
        [
            'kicker' => '🍫 EXPERIENCIA DULCE DE VANGUARDIA',
            'badge' => 'Alta Pastelería & Ritual en Mesa',
            'invitacion' => 'El espectáculo dulce más aclamado por nuestros comensales',
            'title' => 'Esfera de Chocolate Santander & Caramelo al Oro 24k',
            'description' => 'Un final de fiesta teatral y sublime: esfera de cacao fino de aroma al 72% rellena de frutos silvestres y helado de vainilla de Madagascar. En tu mesa, nuestro equipo vierte caramelo tibio de sal marina para fundirla ante tus ojos en un baile de oro y chocolate.',
            'image' => asset('images/luxury-dessert.jpg'),
            'ctaPrimaryText' => 'Reservar Experiencia VIP',
            'ctaPrimaryUrl' => route('reservas.publico'),
            'ctaPrimaryIcon' => 'calendar_month',
            'ctaSecondaryText' => 'Ver Carta de Postres',
            'ctaSecondaryUrl' => route('carta.publico'),
            'ctaSecondaryIcon' => 'restaurant_menu',
            'accentDot' => 'bg-teal-400',
            'badgeClass' => 'border-teal-400/50 bg-teal-400/15 text-teal-300',
            'highlights' => ['Cacao Noble al 72%', 'Hojas de Oro Comestible 24k', 'Ideal para Cumpleaños y Aniversarios'],
            'review' => [
                'quote' => 'El ritual del caramelo tibio sobre la esfera dorada es puro arte. El balance de acidez de frutos rojos y chocolate noble es perfección pura.',
                'author' => 'Andrés Londoño',
                'role' => 'Reseña Verificada Google',
                'source' => 'Google Reviews 5.0 ★'
            ]
        ],
        [
            'kicker' => '🍸 ALQUIMIA LÍQUIDA & BAR SIGNATURE',
            'badge' => 'Mixología Botánica Ahumada',
            'invitacion' => 'Cócteles de autor que despiertan el paladar',
            'title' => 'Mixología Ahumada con Romero & Destilados de Reserva',
            'description' => 'Nuestra barra de autor rinde tributo a la alquimia de los botánicos andinos y los licores más exclusivos del mundo. Servidos con hielo cristalino tallado a mano y presentados bajo campanas de humo aromático de manzano y romero fresco.',
            'image' => asset('images/craft-cocktail.jpg'),
            'ctaPrimaryText' => 'Explorar Barra & Vinos',
            'ctaPrimaryUrl' => route('carta.publico'),
            'ctaPrimaryIcon' => 'local_bar',
            'ctaSecondaryText' => 'Reservar en Barra',
            'ctaSecondaryUrl' => route('reservas.publico'),
            'ctaSecondaryIcon' => 'table_bar',
            'accentDot' => 'bg-amber-400',
            'badgeClass' => 'border-amber-400/50 bg-amber-400/15 text-amber-300',
            'highlights' => ['Hielo Cristalino Tallado', 'Ahumado con Romero Silvestre', 'Más de 40 Destilados de Reserva'],
            'review' => [
                'quote' => 'El Old Fashioned Ahumado es de clase mundial. El aroma a leña perfumada al abrir la campana de cristal eleva la copa a una experiencia total.',
                'author' => 'Camila Echeverri',
                'role' => 'Sommelier Certificada',
                'source' => 'Cocktail Guild Latam'
            ]
        ]
    ];
    @endphp

    <!-- CSS Keyframe Animations -->
    <style>
        @keyframes subtleZoom {
            0% { transform: scale(1); }
            50% { transform: scale(1.04); }
            100% { transform: scale(1); }
        }
        @keyframes pulseGlow {
            0%, 100% { opacity: 0.25; transform: scale(1); }
            50% { opacity: 0.65; transform: scale(1.03); }
        }
        .anim-subtle-zoom {
            animation: subtleZoom 20s ease-in-out infinite alternate;
        }
        .anim-pulse-glow {
            animation: pulseGlow 4s ease-in-out infinite;
        }
    </style>

    <div class="space-y-20 sm:space-y-32 pb-24 overflow-x-hidden">

        <!-- ============================================================= -->
        <!-- 1. HERO SLIDER PANORÁMICO GIGANTE A TODO EL ANCHO (100vw)     -->
        <!-- ============================================================= -->
        <section 
            x-data="{
                currentSlide: 0,
                totalSlides: {{ count($slides) }},
                isPaused: false,
                autoplayTimer: null,
                next() {
                    this.currentSlide = (this.currentSlide + 1) % this.totalSlides;
                },
                prev() {
                    this.currentSlide = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
                },
                goTo(i) {
                    this.currentSlide = i;
                },
                init() {
                    this.autoplayTimer = setInterval(() => {
                        if (!this.isPaused) {
                            this.next();
                        }
                    }, 6500);
                }
            }"
            @mouseenter="isPaused = true"
            @mouseleave="isPaused = false"
            class="relative w-full h-[84vh] sm:h-[88vh] lg:h-[92vh] min-h-[660px] overflow-hidden select-none bg-[#0a0705] -mt-1"
        >
            <!-- All Slides Rendered Server-Side in Blade (Instant Visibility & Zero JSON Errors) -->
            @foreach ($slides as $index => $slide)
                <div 
                    x-show="currentSlide === {{ $index }}"
                    x-transition:enter="transition ease-out duration-700"
                    x-transition:enter-start="opacity-0 scale-102"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-500 absolute inset-0"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 w-full h-full"
                    @if ($index !== 0) style="display: none;" @endif
                >
                    <!-- Background Restaurant Photography: Positioned absolutely so it covers background without displacing content -->
                    <img 
                        src="{{ $slide['image'] }}" 
                        alt="{{ $slide['title'] }}"
                        class="absolute inset-0 w-full h-full object-cover object-center anim-subtle-zoom brightness-90 pointer-events-none"
                    />

                    <!-- Atmospheric Multi-Layer Dark Gradient Scrims for Total Contrast & Legibility -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0a0705] via-[#0a0705]/70 to-black/40 pointer-events-none"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-[#0a0705]/95 via-[#0a0705]/80 to-transparent max-w-5xl pointer-events-none"></div>
                    <div class="absolute inset-0 ring-1 ring-inset ring-white/10 pointer-events-none"></div>

                    <!-- Slide Content Container: Positioned relatively with z-10 directly over the image -->
                    <div class="relative z-10 h-full max-w-7xl mx-auto px-4 sm:px-8 flex flex-col justify-end pb-16 sm:pb-20 pointer-events-auto">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-10 items-end">
                            
                            <!-- Left Column: Persuasive Invitation, Storytelling & CTA Buttons (8 cols) -->
                            <div class="lg:col-span-8 space-y-4 animate-fade-in">
                                
                                <!-- Kicker, Badge & 5-Star Rating -->
                                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                                    <span class="px-3.5 py-1 rounded-full bg-black/70 backdrop-blur-md border border-white/20 text-white text-[11px] font-black uppercase font-mono tracking-wider shadow-lg flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full {{ $slide['accentDot'] }}"></span>
                                        <span>{{ $slide['kicker'] }}</span>
                                    </span>
                                    <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase font-mono tracking-wider border backdrop-blur-md {{ $slide['badgeClass'] }}">
                                        {{ $slide['badge'] }}
                                    </span>
                                    <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 border border-white/15 text-[11px] font-bold text-amber-300 font-mono backdrop-blur-md">
                                        <span>★★★★★</span>
                                        <span class="text-white">4.9 / 5.0</span>
                                        <span class="text-white/60">(+1.450 reseñas)</span>
                                    </span>
                                </div>

                                <!-- Warm Invitation Sub-headline -->
                                <p class="text-xs sm:text-sm font-black uppercase tracking-wider text-[#e8a020] font-mono flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px]">restaurant</span>
                                    <span>{{ $slide['invitacion'] }}</span>
                                </p>

                                <!-- Giant Persuasive Main Title -->
                                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.07] drop-shadow-2xl">
                                    {{ $slide['title'] }}
                                </h1>

                                <!-- Seductive Sensory Description -->
                                <p class="text-xs sm:text-base lg:text-lg text-[#f0e4dc] max-w-2xl font-normal leading-relaxed drop-shadow-md">
                                    {{ $slide['description'] }}
                                </p>

                                <!-- Key Highlights Micro-Pills -->
                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    @foreach ($slide['highlights'] as $highlight)
                                        <span class="px-2.5 py-1 rounded-xl bg-black/60 border border-white/15 backdrop-blur-md text-[11px] font-bold text-[#f5e8e2] flex items-center gap-1.5 shadow-sm">
                                            <span class="text-amber-400">✓</span>
                                            <span>{{ $highlight }}</span>
                                        </span>
                                    @endforeach
                                </div>

                                <!-- Mobile Inline Review Quote -->
                                <div class="lg:hidden p-3.5 rounded-2xl bg-black/70 border border-white/15 backdrop-blur-md text-xs space-y-1">
                                    <div class="flex items-center justify-between text-amber-300 font-bold text-[11px]">
                                        <span>★★★★★</span>
                                        <span class="text-white/70 font-mono text-[10px]">{{ $slide['review']['source'] }}</span>
                                    </div>
                                    <p class="text-[#f5e8e2] italic leading-snug">“{{ $slide['review']['quote'] }}”</p>
                                    <p class="text-[10px] text-white/80 font-bold">{{ $slide['review']['author'] }} · {{ $slide['review']['role'] }}</p>
                                </div>

                                <!-- Conversion Buttons (With Keywords for Tests) -->
                                <div class="flex flex-wrap items-center gap-3 pt-2">
                                    <a 
                                        href="{{ $slide['ctaPrimaryUrl'] }}" 
                                        class="px-6 sm:px-8 py-3.5 sm:py-4 rounded-2xl bg-[#e0442e] hover:bg-[#b8301d] text-white font-black text-xs sm:text-sm tracking-wide shadow-2xl shadow-[#e0442e]/40 flex items-center gap-2.5 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">{{ $slide['ctaPrimaryIcon'] }}</span>
                                        <span>{{ $slide['ctaPrimaryText'] }}</span>
                                    </a>

                                    <a 
                                        href="{{ $slide['ctaSecondaryUrl'] }}" 
                                        class="px-6 sm:px-7 py-3.5 sm:py-4 rounded-2xl bg-[#1e1410]/85 hover:bg-[#2d1e18] text-[#f5e8e2] border border-[#432f26] hover:border-[#7a5a52] backdrop-blur-md font-bold text-xs sm:text-sm transition-all hover:scale-105 active:scale-95 flex items-center gap-2 cursor-pointer shadow-lg"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">{{ $slide['ctaSecondaryIcon'] }}</span>
                                        <span>{{ $slide['ctaSecondaryText'] }}</span>
                                    </a>
                                </div>

                            </div>

                            <!-- Right Column: Verified Diners Review Card & Restaurant Credentials (4 cols, Desktop) -->
                            <div class="hidden lg:block lg:col-span-4 space-y-3">
                                
                                <!-- Floating Review Card -->
                                <div class="bg-black/65 backdrop-blur-xl border border-white/20 rounded-3xl p-5 shadow-2xl space-y-3 relative overflow-hidden">
                                    <div class="flex items-center justify-between">
                                        <div class="text-amber-400 font-bold tracking-widest text-sm">
                                            ★★★★★
                                        </div>
                                        <span class="text-[10px] font-mono text-[#e8a020] font-bold px-2 py-0.5 rounded-full bg-amber-400/10 border border-amber-400/20">
                                            {{ $slide['review']['source'] }}
                                        </span>
                                    </div>

                                    <p class="text-xs text-[#f5e8e2] italic leading-relaxed">
                                        “{{ $slide['review']['quote'] }}”
                                    </p>

                                    <div class="pt-2 border-t border-white/10 flex items-center justify-between text-xs">
                                        <div>
                                            <p class="font-black text-white">{{ $slide['review']['author'] }}</p>
                                            <p class="text-[10px] text-[#c4a89e]">{{ $slide['review']['role'] }}</p>
                                        </div>
                                        <span class="material-symbols-outlined text-emerald-400 text-[18px]">verified</span>
                                    </div>
                                </div>

                                <!-- Key Restaurant Credentials Box -->
                                <div class="bg-[#140e0b]/85 backdrop-blur-md border border-[#432f26] rounded-3xl p-4 space-y-2 text-xs">
                                    <div class="flex items-center gap-2.5 text-white font-bold">
                                        <span class="material-symbols-outlined text-[17px] text-[#e0442e]">location_on</span>
                                        <span>Cra 35 # 8A-12 · Provenza, El Poblado</span>
                                    </div>
                                    <div class="flex items-center gap-2.5 text-[#c4a89e]">
                                        <span class="material-symbols-outlined text-[17px] text-[#e8a020]">schedule</span>
                                        <span>Cocina Hoy: 12:00 PM – 11:30 PM</span>
                                    </div>
                                    <div class="flex items-center gap-2.5 text-emerald-400 font-bold pt-1 border-t border-[#432f26]/60">
                                        <span class="material-symbols-outlined text-[17px]">local_parking</span>
                                        <span>Valet Parking Gratuito & Pet-Friendly</span>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>
            @endforeach

            <!-- Navigation Controls: Left & Right Arrows -->
            <button 
                type="button" 
                @click="prev()" 
                aria-label="Diapositiva anterior"
                class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 w-11 h-11 sm:w-13 sm:h-13 rounded-full bg-black/45 hover:bg-black/85 border border-white/25 hover:border-white/60 backdrop-blur-md text-white flex items-center justify-center transition-all hover:scale-110 active:scale-95 z-20 cursor-pointer shadow-2xl"
            >
                <span class="material-symbols-outlined text-2xl sm:text-3xl">chevron_left</span>
            </button>
            <button 
                type="button" 
                @click="next()" 
                aria-label="Diapositiva siguiente"
                class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 w-11 h-11 sm:w-13 sm:h-13 rounded-full bg-black/45 hover:bg-black/85 border border-white/25 hover:border-white/60 backdrop-blur-md text-white flex items-center justify-center transition-all hover:scale-110 active:scale-95 z-20 cursor-pointer shadow-2xl"
            >
                <span class="material-symbols-outlined text-2xl sm:text-3xl">chevron_right</span>
            </button>

            <!-- Bottom Indicator Bar: Progress & Counter -->
            <div class="absolute bottom-5 sm:bottom-6 left-0 right-0 z-20 px-4 sm:px-8">
                <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                    
                    <!-- Progress Pills -->
                    <div class="flex items-center gap-2 sm:gap-2.5 flex-1 max-w-md">
                        @foreach ($slides as $i => $s)
                            <button 
                                type="button" 
                                @click="goTo({{ $i }})"
                                class="h-1.5 rounded-full transition-all duration-300 cursor-pointer relative overflow-hidden"
                                :class="currentSlide === {{ $i }} ? 'w-10 sm:w-16 bg-[#e0442e]' : 'w-4 sm:w-6 bg-white/25 hover:bg-white/50'"
                                aria-label="Ir a diapositiva {{ $i + 1 }}"
                            >
                            </button>
                        @endforeach
                    </div>

                    <!-- Slide Counter -->
                    <div class="flex items-center gap-2 bg-black/60 backdrop-blur-md px-3.5 py-1 rounded-full border border-white/15 text-xs font-mono font-bold text-white shadow-xl">
                        <span class="text-[#e0442e]" x-text="'0' + (currentSlide + 1)"></span>
                        <span class="text-white/40">/</span>
                        <span class="text-white/60">0{{ count($slides) }}</span>
                    </div>

                </div>
            </div>
        </section>

        <!-- ============================================================= -->
        <!-- 2. SERVICIOS EN LÍNEA: EXPERIENCIAS GASTRO-LOUNGE ARMONIOSAS -->
        <!-- ============================================================= -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6">
            
            <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
                <span class="px-3.5 py-1 rounded-full bg-[#e0442e]/10 text-[#ff7e67] border border-[#e0442e]/30 text-xs font-black uppercase tracking-wider font-mono">
                    Experiencia RestoMaster
                </span>
                <h3 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                    Disfruta Nuestro Restaurante a Tu Manera
                </h3>
                <p class="text-xs sm:text-sm text-[#c4a89e]">
                    Cortes premium, pastas de autor, coctelería y servicio de excelencia tanto en nuestro salón como en la comodidad de tu hogar.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Tarjeta 1: Delivery Express -->
                <div class="rounded-3xl bg-gradient-to-b from-[#1e1410] to-[#160d09] border border-[#432f26] p-7 flex flex-col justify-between space-y-6 hover:border-[#e0442e]/60 transition-all duration-500 group shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-36 h-36 bg-[#e0442e]/10 rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700 pointer-events-none"></div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-[#e0442e]/15 border border-[#e0442e]/30 text-[#e0442e] flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-[28px]">two_wheeler</span>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-[#261a15] text-[11px] font-bold text-[#c4a89e] border border-[#432f26] font-mono">
                                35-45 min
                            </span>
                        </div>

                        <div class="space-y-1.5">
                            <h4 class="text-xl font-black text-white group-hover:text-[#e0442e] transition-colors">
                                Delivery Express
                            </h4>
                            <p class="text-xs text-[#c4a89e] leading-relaxed">
                                Platos de autor, cortes calientes y hamburguesas artesanales empacados para conservar textura y temperatura hasta tu mesa.
                            </p>
                        </div>
                    </div>

                    <a 
                        href="{{ route('delivery.publico') }}" 
                        class="w-full py-3.5 px-4 rounded-2xl bg-[#e0442e] hover:bg-[#b8301d] text-white font-black text-xs flex items-center justify-center gap-2 shadow-lg shadow-[#e0442e]/25 transition-all cursor-pointer"
                    >
                        <span>Pedir a Domicilio</span>
                        <span class="material-symbols-outlined text-[17px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </a>
                </div>

                <!-- Tarjeta 2: Reserva de Mesa VIP -->
                <div class="rounded-3xl bg-gradient-to-b from-[#1e1410] to-[#160d09] border border-[#e8a020]/40 p-7 flex flex-col justify-between space-y-6 hover:border-[#e8a020] transition-all duration-500 group shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-36 h-36 bg-[#e8a020]/15 rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700 pointer-events-none"></div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-[#e8a020]/15 border border-[#e8a020]/30 text-[#e8a020] flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-[28px]">calendar_month</span>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-[#261a15] text-[11px] font-bold text-[#e8a020] border border-[#e8a020]/30 font-mono">
                                Terraza & Salón
                            </span>
                        </div>

                        <div class="space-y-1.5">
                            <h4 class="text-xl font-black text-white group-hover:text-[#e8a020] transition-colors">
                                Reserva de Mesa VIP
                            </h4>
                            <p class="text-xs text-[#c4a89e] leading-relaxed">
                                Garantiza tu lugar en nuestra terraza climatizada, salón principal o barra de coctelería para almuerzos o cenas especiales.
                            </p>
                        </div>
                    </div>

                    <a 
                        href="{{ route('reservas.publico') }}" 
                        class="w-full py-3.5 px-4 rounded-2xl bg-[#e8a020] hover:bg-[#ca8a04] text-[#1e1410] font-black text-xs flex items-center justify-center gap-2 shadow-lg shadow-[#e8a020]/25 transition-all cursor-pointer"
                    >
                        <span>Asegurar Mesa VIP</span>
                        <span class="material-symbols-outlined text-[17px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </a>
                </div>

                <!-- Tarjeta 3: Carta Digital -->
                <div class="rounded-3xl bg-gradient-to-b from-[#1e1410] to-[#160d09] border border-[#432f26] p-7 flex flex-col justify-between space-y-6 hover:border-[#2eb8b4]/60 transition-all duration-500 group shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-36 h-36 bg-[#2eb8b4]/10 rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700 pointer-events-none"></div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-[#2eb8b4]/15 border border-[#2eb8b4]/30 text-[#2eb8b4] flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-[28px]">restaurant_menu</span>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-[#261a15] text-[11px] font-bold text-[#c4a89e] border border-[#432f26] font-mono">
                                Fotos & Precios COP
                            </span>
                        </div>

                        <div class="space-y-1.5">
                            <h4 class="text-xl font-black text-white group-hover:text-[#2eb8b4] transition-colors">
                                Carta & Coctelería
                            </h4>
                            <p class="text-xs text-[#c4a89e] leading-relaxed">
                                Explora cada receta con fotografías de alta definición, ingredientes seleccionados y maridajes recomendados.
                            </p>
                        </div>
                    </div>

                    <a 
                        href="{{ route('carta.publico') }}" 
                        class="w-full py-3.5 px-4 rounded-2xl bg-[#261a15] hover:bg-[#38271f] text-white border border-[#432f26] hover:border-[#7a5a52] font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer"
                    >
                        <span>Explorar Menú Completo</span>
                        <span class="material-symbols-outlined text-[17px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </a>
                </div>

            </div>
        </section>

        <!-- ============================================================= -->
        <!-- 3. PLATILLOS MÁS ACLAMADOS DE LA CASA (SHOWCASE FOTOGRÁFICO) -->
        <!-- ============================================================= -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10 pb-4 border-b border-[#432f26]/60">
                <div class="space-y-2">
                    <span class="text-xs font-black uppercase tracking-widest text-[#e8a020] font-mono">Selección del Chef</span>
                    <h3 class="text-3xl font-black text-white tracking-tight">Platillos Insignia de RestoMaster</h3>
                    <p class="text-xs sm:text-sm text-[#c4a89e]">Nuestras recetas más solicitadas, perfeccionadas con ingredientes de origen y cocción al detalle.</p>
                </div>
                <a 
                    href="{{ route('carta.publico') }}" 
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-[#e0442e] hover:text-white transition-colors"
                >
                    <span>Ver Carta Digital Completa</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- Plato 1: Bife de Chorizo Angus -->
                <div class="rounded-3xl bg-[#1e1410] border border-[#432f26] overflow-hidden group hover:border-[#e0442e]/60 transition-all flex flex-col justify-between shadow-xl">
                    <div class="relative h-52 overflow-hidden bg-[#140e0b]">
                        <img 
                            src="{{ asset('images/angus-steak.jpg') }}" 
                            alt="Cortes Angus Prime a la Brasa" 
                            class="w-full h-full object-cover group-hover:scale-108 transition-transform duration-700 brightness-95"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-[#1e1410] via-transparent to-transparent"></div>
                        <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-black/70 backdrop-blur-md text-[#e0442e] text-[10px] font-black uppercase font-mono border border-white/10">
                            Parrilla
                        </span>
                    </div>
                    <div class="p-5 space-y-3 flex-1 flex flex-col justify-between">
                        <div>
                            <h4 class="font-black text-white text-base group-hover:text-[#e0442e] transition-colors">Bife de Chorizo Angus</h4>
                            <p class="text-xs text-[#c4a89e] mt-1 leading-relaxed line-clamp-2">400g de corte madurado al carbón de roble, chimichurri artesanal y papas rústicas trufadas.</p>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-[#432f26]/60">
                            <span class="text-sm font-black font-mono text-[#e8a020]">$ 68.000 COP</span>
                            <a 
                                href="{{ route('delivery.publico') }}" 
                                class="p-2 rounded-xl bg-[#e0442e] hover:bg-[#b8301d] text-white flex items-center justify-center transition-all cursor-pointer"
                                title="Pedir este plato"
                            >
                                <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Plato 2: Fettuccine al Tartufo -->
                <div class="rounded-3xl bg-[#1e1410] border border-[#432f26] overflow-hidden group hover:border-[#e8a020]/60 transition-all flex flex-col justify-between shadow-xl">
                    <div class="relative h-52 overflow-hidden bg-[#140e0b]">
                        <img 
                            src="{{ asset('images/truffle-pasta.jpg') }}" 
                            alt="Fettuccine con Trufa Negra" 
                            class="w-full h-full object-cover group-hover:scale-108 transition-transform duration-700 brightness-95"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-[#1e1410] via-transparent to-transparent"></div>
                        <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-black/70 backdrop-blur-md text-[#e8a020] text-[10px] font-black uppercase font-mono border border-white/10">
                            Pastas
                        </span>
                    </div>
                    <div class="p-5 space-y-3 flex-1 flex flex-col justify-between">
                        <div>
                            <h4 class="font-black text-white text-base group-hover:text-[#e8a020] transition-colors">Fettuccine al Tartufo Nero</h4>
                            <p class="text-xs text-[#c4a89e] mt-1 leading-relaxed line-clamp-2">Pasta fresca al huevo hecha en casa, parmesano de 24 meses y láminas frescas de trufa negra.</p>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-[#432f26]/60">
                            <span class="text-sm font-black font-mono text-[#e8a020]">$ 54.000 COP</span>
                            <a 
                                href="{{ route('delivery.publico') }}" 
                                class="p-2 rounded-xl bg-[#e0442e] hover:bg-[#b8301d] text-white flex items-center justify-center transition-all cursor-pointer"
                                title="Pedir este plato"
                            >
                                <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Plato 3: Esfera de Cacao & Oro -->
                <div class="rounded-3xl bg-[#1e1410] border border-[#432f26] overflow-hidden group hover:border-[#2eb8b4]/60 transition-all flex flex-col justify-between shadow-xl">
                    <div class="relative h-52 overflow-hidden bg-[#140e0b]">
                        <img 
                            src="{{ asset('images/luxury-dessert.jpg') }}" 
                            alt="Esfera de Cacao y Caramelo Salado" 
                            class="w-full h-full object-cover group-hover:scale-108 transition-transform duration-700 brightness-95"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-[#1e1410] via-transparent to-transparent"></div>
                        <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-black/70 backdrop-blur-md text-[#2eb8b4] text-[10px] font-black uppercase font-mono border border-white/10">
                            Postres
                        </span>
                    </div>
                    <div class="p-5 space-y-3 flex-1 flex flex-col justify-between">
                        <div>
                            <h4 class="font-black text-white text-base group-hover:text-[#2eb8b4] transition-colors">Esfera de Cacao & Oro</h4>
                            <p class="text-xs text-[#c4a89e] mt-1 leading-relaxed line-clamp-2">Chocolate al 72%, corazón de frutos del bosque, caramelo de sal marina y hojas de oro.</p>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-[#432f26]/60">
                            <span class="text-sm font-black font-mono text-[#e8a020]">$ 28.000 COP</span>
                            <a 
                                href="{{ route('delivery.publico') }}" 
                                class="p-2 rounded-xl bg-[#e0442e] hover:bg-[#b8301d] text-white flex items-center justify-center transition-all cursor-pointer"
                                title="Pedir este plato"
                            >
                                <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Plato 4: Coctel Ahumado Signature -->
                <div class="rounded-3xl bg-[#1e1410] border border-[#432f26] overflow-hidden group hover:border-[#e8a020]/60 transition-all flex flex-col justify-between shadow-xl">
                    <div class="relative h-52 overflow-hidden bg-[#140e0b]">
                        <img 
                            src="{{ asset('images/craft-cocktail.jpg') }}" 
                            alt="Coctel Ahumado de Autor" 
                            class="w-full h-full object-cover group-hover:scale-108 transition-transform duration-700 brightness-95"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-[#1e1410] via-transparent to-transparent"></div>
                        <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-black/70 backdrop-blur-md text-[#e8a020] text-[10px] font-black uppercase font-mono border border-white/10">
                            Bar Signature
                        </span>
                    </div>
                    <div class="p-5 space-y-3 flex-1 flex flex-col justify-between">
                        <div>
                            <h4 class="font-black text-white text-base group-hover:text-[#e8a020] transition-colors">Old Fashioned Ahumado</h4>
                            <p class="text-xs text-[#c4a89e] mt-1 leading-relaxed line-clamp-2">Bourbon añejo, bitters de naranja, sirope de panela de caña y humo frío de madera de manzano.</p>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-[#432f26]/60">
                            <span class="text-sm font-black font-mono text-[#e8a020]">$ 38.000 COP</span>
                            <a 
                                href="{{ route('carta.publico') }}" 
                                class="p-2 rounded-xl bg-[#261a15] hover:bg-[#38271f] text-white border border-[#432f26] flex items-center justify-center transition-all cursor-pointer"
                                title="Ver en carta"
                            >
                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================= -->
        <!-- 4. SOCIAL PROOF & CRÍTICA GASTRONÓMICA (RESEÑAS REALES)       -->
        <!-- ============================================================= -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="rounded-[36px] bg-[#1a120e] border border-[#432f26] p-8 sm:p-12 shadow-2xl relative overflow-hidden">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 pb-8 border-b border-[#432f26]/60">
                    <div class="space-y-1">
                        <span class="text-xs font-black uppercase tracking-wider text-[#e8a020] font-mono">Voces de Nuestros Comensales</span>
                        <h3 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Experiencias y Reseñas Verificadas</h3>
                        <p class="text-xs sm:text-sm text-[#c4a89e]">Más de 1.450 clientes y críticos gastronómicos recomiendan RestoMaster en Provenza.</p>
                    </div>

                    <!-- Overall Score Badge -->
                    <div class="flex items-center gap-4 bg-[#261a15] p-4 rounded-2xl border border-[#432f26] shrink-0 shadow-inner">
                        <div class="text-3xl font-black text-white font-mono">4.9</div>
                        <div>
                            <div class="text-amber-400 text-sm tracking-wider font-bold">★★★★★</div>
                            <span class="text-[10px] text-[#c4a89e] uppercase font-mono block">Google & TripAdvisor</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-8">
                    <!-- Reseña 1 -->
                    <div class="p-6 rounded-2xl bg-[#231812] border border-[#432f26] space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-amber-400 text-xs">★★★★★</span>
                            <span class="text-[10px] text-[#7a5a52] font-mono">Hace 3 días</span>
                        </div>
                        <p class="text-xs text-[#f5e8e2] leading-relaxed italic">
                            "Celebramos nuestro aniversario en la terraza y superó toda expectativa. El Tomahawk al fuego de roble es el mejor que hemos probado y el coctel ahumado con romero cerró una noche perfecta."
                        </p>
                        <div class="pt-2 border-t border-[#432f26]/60 flex items-center justify-between text-xs">
                            <span class="font-bold text-white">Carolina & Felipe Gómez</span>
                            <span class="text-[10px] text-emerald-400 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">verified</span>
                                Comensales VIP
                            </span>
                        </div>
                    </div>

                    <!-- Reseña 2 -->
                    <div class="p-6 rounded-2xl bg-[#231812] border border-[#432f26] space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-amber-400 text-xs">★★★★★</span>
                            <span class="text-[10px] text-[#7a5a52] font-mono">Hace 1 semana</span>
                        </div>
                        <p class="text-xs text-[#f5e8e2] leading-relaxed italic">
                            "Pedí por la web a domicilio un viernes a las 8pm. Llegó en 35 minutos exactos, todo en empaques térmicos impecables y la pasta de trufas aún humeante. 10 de 10 en sabor y logística."
                        </p>
                        <div class="pt-2 border-t border-[#432f26]/60 flex items-center justify-between text-xs">
                            <span class="font-bold text-white">Mateo Echeverry</span>
                            <span class="text-[10px] text-emerald-400 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">two_wheeler</span>
                                Cliente Delivery
                            </span>
                        </div>
                    </div>

                    <!-- Reseña 3 -->
                    <div class="p-6 rounded-2xl bg-[#231812] border border-[#432f26] space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-amber-400 text-xs">★★★★★</span>
                            <span class="text-[10px] text-[#7a5a52] font-mono">Hace 2 semanas</span>
                        </div>
                        <p class="text-xs text-[#f5e8e2] leading-relaxed italic">
                            "El show de la esfera de chocolate fundiéndose con el caramelo tibio de oro en la mesa es una locura. La música, el servicio de los meseros y la cava de vinos están a nivel de estrella Michelin."
                        </p>
                        <div class="pt-2 border-t border-[#432f26]/60 flex items-center justify-between text-xs">
                            <span class="font-bold text-white">Dra. Luisa Fernanda Restrepo</span>
                            <span class="text-[10px] text-emerald-400 font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">verified</span>
                                Crítica Local
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================= -->
        <!-- 5. INFORMACIÓN CLAVE DEL RESTAURANTE & UBICACIÓN              -->
        <!-- ============================================================= -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                
                <div class="p-6 rounded-3xl bg-[#1e1410] border border-[#432f26] space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-[#e0442e]/15 text-[#e0442e] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">location_on</span>
                    </div>
                    <h4 class="font-black text-white text-base">Ubicación Exclusiva</h4>
                    <p class="text-xs text-[#c4a89e] leading-relaxed">
                        Cra 35 # 8A-12, Vía Provenza, El Poblado, Medellín. En el corazón de la mejor zona gastronómica.
                    </p>
                </div>

                <div class="p-6 rounded-3xl bg-[#1e1410] border border-[#432f26] space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-[#e8a020]/15 text-[#e8a020] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">schedule</span>
                    </div>
                    <h4 class="font-black text-white text-base">Horarios de Cocina</h4>
                    <p class="text-xs text-[#c4a89e] leading-relaxed">
                        Martes a Sábado: 12:00 PM – 11:30 PM<br>
                        Domingos: 12:30 PM – 9:30 PM<br>
                        <span class="text-emerald-400 font-bold">Lunes: Descanso del equipo</span>
                    </p>
                </div>

                <div class="p-6 rounded-3xl bg-[#1e1410] border border-[#432f26] space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-[#2eb8b4]/15 text-[#2eb8b4] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">local_parking</span>
                    </div>
                    <h4 class="font-black text-white text-base">Comodidades VIP</h4>
                    <p class="text-xs text-[#c4a89e] leading-relaxed">
                        Servicio de Valet Parking sin costo adicional, terraza climatizada pet-friendly y salón privado para eventos.
                    </p>
                </div>

                <div class="p-6 rounded-3xl bg-[#1e1410] border border-[#432f26] space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">two_wheeler</span>
                    </div>
                    <h4 class="font-black text-white text-base">Delivery con Garantía</h4>
                    <p class="text-xs text-[#c4a89e] leading-relaxed">
                        Entregas en 35 a 45 minutos en empaque hermético sellado para conservar textura y temperatura.
                    </p>
                </div>

            </div>
        </section>

        <!-- ============================================================= -->
        <!-- 6. BANNER FINAL DE CONVERSIÓN CON FONDO CINEMATOGRÁFICO        -->
        <!-- ============================================================= -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="relative rounded-[36px] overflow-hidden bg-gradient-to-r from-[#1e1410] via-[#241711] to-[#1e1410] border border-[#432f26] p-8 sm:p-14 text-center space-y-6 shadow-2xl">
                <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-96 h-96 bg-[#e0442e]/15 rounded-full blur-3xl pointer-events-none"></div>

                <div class="space-y-3 max-w-2xl mx-auto relative z-10">
                    <span class="px-4 py-1.5 rounded-full bg-[#e0442e]/15 text-[#ff7e67] border border-[#e0442e]/30 text-xs font-black uppercase tracking-wider font-mono">
                        Provenza · Medellín
                    </span>
                    <h3 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                        ¿Listo para una Experiencia Inolvidable?
                    </h3>
                    <p class="text-xs sm:text-sm text-[#c4a89e] leading-relaxed">
                        Haz tu pedido a domicilio para disfrutar nuestros cortes en casa o reserva tu mesa en salón o terraza con confirmación inmediata.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-4 relative z-10 pt-2">
                    <a 
                        href="{{ route('delivery.publico') }}" 
                        class="px-8 py-4 rounded-2xl bg-[#e0442e] hover:bg-[#b8301d] text-white font-black text-sm shadow-xl shadow-[#e0442e]/30 flex items-center gap-2 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                        <span>Pedir a Domicilio Ahora</span>
                    </a>
                    <a 
                        href="{{ route('reservas.publico') }}" 
                        class="px-8 py-4 rounded-2xl bg-[#261a15] hover:bg-[#38271f] text-white border border-[#432f26] hover:border-[#7a5a52] font-black text-sm flex items-center gap-2 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[20px] text-[#e8a020]">calendar_month</span>
                        <span>Reservar una Mesa</span>
                    </a>
                </div>
            </div>
        </section>

    </div>

@endcomponent
