<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Aura Gastro') }} — Colombia POS Enterprise</title>

        <!-- Stitch Design System Typography & Icons (Aura Gastro Expressive OS) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

        <style>
            @layer base {
                html, body {
                    margin: 0;
                    padding: 0;
                }
                body {
                    overscroll-behavior: none;
                }
            }
            ::-webkit-scrollbar {
                display: none;
            }
            .material-symbols-outlined {
                font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                vertical-align: middle;
            }
            .material-symbols-outlined.fill {
                font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            }
        </style>

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased bg-background text-on-surface selection:bg-primary-container selection:text-on-primary">
        <div class="min-h-screen bg-background">
            <!-- Navigation (Aura Gastro Top Bar + Sidebar + Mobile Drawer) -->
            <livewire:layout.navigation />

            <!-- Main Application Content Area -->
            <div class="lg:pl-64 flex flex-col flex-1 min-h-screen bg-background pt-16">
                <!-- Page Heading (Optional) -->
                @if (isset($header))
                    <header class="bg-surface-container-lowest border-b border-surface-container-highest px-4 py-4 sm:px-6 lg:px-8">
                        <div class="max-w-7xl mx-auto">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main class="flex-1 p-4 sm:p-6 lg:p-8 bg-background">
                    <div class="max-w-[1680px] mx-auto">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
