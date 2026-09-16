<!DOCTYPE html>
<html lang="es" class="h-full bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'RestoMaster · Carta & Auto-pedido' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-[#fbf9f6] text-stone-900 min-h-screen flex flex-col selection:bg-primary/20 selection:text-primary pb-24">
    <div class="w-full max-w-lg mx-auto min-h-screen flex flex-col bg-white shadow-xl sm:my-3 sm:rounded-[36px] overflow-hidden border border-stone-100">
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
