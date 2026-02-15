<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ИС учёта материалов — Теплоснабжающая организация</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 antialiased flex flex-col">
    <header class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div>
                <h1 class="font-semibold text-gray-800">ИС учёта материалов</h1>
                <p class="text-sm text-gray-500">Теплоснабжающая организация</p>
            </div>
            <nav>
                @auth
                    <a href="{{ url('/dashboard') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-700 hover:text-gray-900 border border-gray-300 rounded-md hover:bg-gray-50">
                        Панель управления
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-700 hover:text-gray-900 border border-gray-300 rounded-md hover:bg-gray-50">
                        Вход
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1 py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">
                        Система учёта материалов для теплоснабжающей организации
                    </h2>
                    @guest
                        <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 hover:bg-gray-800 border border-transparent rounded-md">
                            Войти в систему
                        </a>
                    @endguest
                </div>
            </div>

            <div class="mt-6 grid sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="font-medium text-gray-800 dark:text-gray-200">Учёт материалов</h3>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="font-medium text-gray-800 dark:text-gray-200">Заявки</h3>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 py-4 mt-auto shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-500 dark:text-gray-400">
            ИС учёта материалов для теплоснабжающей организации
        </div>
    </footer>
</body>
</html>
