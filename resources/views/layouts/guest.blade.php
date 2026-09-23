<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0a0705]">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RestoMaster') }} — Acceso Personal & Gestión Gastro POS</title>

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts & Icons (Aura Gastro Expressive OS) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

        <style>
            html, body {
                background-color: #0a0705 !important;
                color: #f5e8e2 !important;
                font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
                margin: 0;
                padding: 0;
            }

            /* Anti-autofill override for webkit browsers in dark mode */
            input:-webkit-autofill,
            input:-webkit-autofill:hover, 
            input:-webkit-autofill:focus, 
            textarea:-webkit-autofill,
            textarea:-webkit-autofill:hover,
            textarea:-webkit-autofill:focus,
            select:-webkit-autofill,
            select:-webkit-autofill:hover,
            select:-webkit-autofill:focus {
                -webkit-text-fill-color: #f5e8e2 !important;
                -webkit-box-shadow: 0 0 0px 1000px #1e1410 inset !important;
                box-shadow: 0 0 0px 1000px #1e1410 inset !important;
                transition: background-color 5000s ease-in-out 0s;
            }

            .material-symbols-outlined {
                font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                vertical-align: middle;
            }
        </style>

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-[#0a0705] text-[#f5e8e2] min-h-screen flex flex-col justify-center items-center p-4 sm:p-6 selection:bg-[#e0442e] selection:text-white relative overflow-x-hidden">
        
        <!-- Ambient Restaurant Backdrop Image -->
        <img src="{{ asset('images/resto-terrace-night.jpg') }}" alt="RestoMaster Ambient" class="fixed inset-0 -z-30 w-full h-full object-cover opacity-25 filter blur-[3px] pointer-events-none" />
        <div class="fixed inset-0 -z-20 bg-gradient-to-t from-[#0a0705] via-[#0a0705]/85 to-[#0a0705]/95"></div>

        <!-- Ambient Decorative Lighting Elements -->
        <div class="fixed top-[-15%] left-1/2 -translate-x-1/2 w-[700px] h-[400px] rounded-full bg-[#e0442e]/15 blur-[170px] pointer-events-none -z-10"></div>
        <div class="fixed bottom-[-15%] right-[-10%] w-[500px] h-[500px] rounded-full bg-[#e8a020]/12 blur-[180px] pointer-events-none -z-10"></div>

        <div class="w-full flex-1 flex flex-col justify-center items-center py-8">
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
