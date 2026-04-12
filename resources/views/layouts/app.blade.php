<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.theme-init-script')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-black dark:text-white">
        @include('partials.ambient-background')
        <div class="relative z-10 flex min-h-screen flex-col text-black dark:text-white">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-orange-200/80 dark:border-orange-800/80 bg-white/70 dark:bg-orange-950/70 backdrop-blur-md shadow-sm shadow-orange-950/[0.03] dark:shadow-black/20 text-black dark:text-white">
                    <div class="max-w-7xl mx-auto py-4 sm:py-6 px-4 sm:px-6 lg:px-8">
                        <div class="w-full min-w-0">
                            {{ $header }}
                        </div>
                        <div class="mt-4 h-px w-full max-w-lg bg-gradient-to-r from-orange-400/50 to-transparent dark:from-orange-500/40 rounded-full" aria-hidden="true"></div>
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1 py-6 sm:py-8 px-4 sm:px-0 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                {{ $slot }}
            </main>

            <footer class="mt-auto border-t border-orange-200/60 dark:border-orange-800/60 bg-white/40 dark:bg-orange-950/40 backdrop-blur-sm py-3 px-4 text-center text-xs text-black/55 dark:text-white/50 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                КТ-Ресурс — система учёта материалов
            </footer>
        </div>
    </body>
</html>
