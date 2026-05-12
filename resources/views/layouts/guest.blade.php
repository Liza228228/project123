<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>ИС учёта материалов</title>

    @include('partials.theme-init-script')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative font-sans text-stone-900 dark:text-stone-100 antialiased">
    @include('partials.ambient-background')
    <div class="absolute end-4 top-4 z-20 flex flex-col items-end gap-1 sm:end-6 sm:top-6">
        <span class="text-[10px] font-semibold uppercase tracking-wider text-black/45 dark:text-white/45">Тема</span>
        <x-theme-toggle />
    </div>
    <div class="relative z-10 min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4 pb-[max(2.5rem,env(safe-area-inset-bottom))] text-stone-900 dark:text-stone-100">
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex flex-col items-center gap-2 rounded-2xl px-5 py-3 bg-white/65 dark:bg-stone-900/55 backdrop-blur-md border border-stone-200/75 dark:border-orange-900/25 shadow-lg shadow-orange-500/10 ring-1 ring-orange-100/40 dark:ring-orange-950/30 hover:border-orange-200/80 dark:hover:border-orange-800/40 transition-colors">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-orange-600 to-orange-800 text-white shadow-md shadow-orange-700/20">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </span>
                <span class="font-semibold tracking-wide text-lg">КТ-Ресурс</span>
                <span class="text-xs font-medium text-black/55 dark:text-white/50">Вход в систему</span>
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-8 relative overflow-hidden rounded-2xl border border-stone-200/80 dark:border-orange-900/30 bg-white/88 dark:bg-stone-900/85 backdrop-blur-md shadow-2xl shadow-orange-500/10 ring-1 ring-orange-100/35 dark:ring-orange-950/25 text-stone-900 dark:text-stone-100">
            <div class="h-1.5 bg-gradient-to-r from-orange-700 via-orange-800 to-stone-800" aria-hidden="true"></div>
            <div class="absolute -right-16 -top-16 h-40 w-40 rounded-full bg-stone-400/10 dark:bg-stone-500/10 blur-2xl pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-6 py-8">
                {{ $slot }}
            </div>
        </div>

      
    </div>
</body>
</html>
