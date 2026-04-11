<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>ИС учёта материалов</title>

    @include('partials.theme-init-script')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative font-sans text-black dark:text-white antialiased">
    <div class="absolute end-4 top-4 z-20 flex flex-col items-end gap-1 sm:end-6 sm:top-6">
        <span class="text-[10px] font-semibold uppercase tracking-wider text-black/45 dark:text-white/45">Тема</span>
        <x-theme-toggle />
    </div>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-orange-100 dark:bg-orange-900 text-black dark:text-white">
        <div>
            <a href="/" class="inline-flex items-center gap-2 text-black dark:text-white hover:opacity-80">
                <span class="font-semibold tracking-wide">КТ Ресурс</span>
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-7 bg-white dark:bg-orange-800 shadow-md rounded-xl overflow-hidden border border-orange-200 dark:border-orange-700 text-black dark:text-white">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
