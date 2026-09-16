<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RestoMaster') }} — Terminal & Gestión Gastro POS</title>

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

        <style>
            html, body {
                background-color: #fafaf9 !important;
                color: #1c1917 !important;
                margin: 0;
                padding: 0;
            }

            /* Anti-autofill override for webkit browsers */
            input:-webkit-autofill,
            input:-webkit-autofill:hover, 
            input:-webkit-autofill:focus, 
            textarea:-webkit-autofill,
            textarea:-webkit-autofill:hover,
            textarea:-webkit-autofill:focus,
            select:-webkit-autofill,
            select:-webkit-autofill:hover,
            select:-webkit-autofill:focus {
                -webkit-text-fill-color: #1c1917 !important;
                -webkit-box-shadow: 0 0 0px 1000px #f5f5f4 inset !important;
                box-shadow: 0 0 0px 1000px #f5f5f4 inset !important;
                transition: background-color 5000s ease-in-out 0s;
            }

            .material-symbols-outlined {
                font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                vertical-align: middle;
            }
        </style>

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#fafaf9] text-stone-900 min-h-screen flex flex-col justify-center items-center p-3 sm:p-6 selection:bg-[#ff5436] selection:text-white relative overflow-x-hidden">
        <!-- Ambient Decorative Lighting Elements (subtle warm) -->
        <div class="fixed top-[-15%] left-[-10%] w-[500px] h-[500px] rounded-full bg-[#ff5436]/6 blur-[140px] pointer-events-none -z-10"></div>
        <div class="fixed bottom-[-15%] right-[-10%] w-[500px] h-[500px] rounded-full bg-amber-400/6 blur-[150px] pointer-events-none -z-10"></div>

        <div class="w-full flex-1 flex flex-col justify-center items-center">
            {{ $slot }}
        </div>
    </body>
</html>
