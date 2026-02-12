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
<body class="min-h-screen bg-gray-100 text-gray-800 antialiased">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
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

    <main class="max-w-4xl mx-auto px-4 py-12">
        <div class="bg-white border border-gray-200 rounded-lg p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-2">
                Система учёта материалов для теплоснабжающей организации
            </h2>
            <p class="text-gray-600 mb-6">
                Учёт материалов, складских остатков и заявок. Войдите в систему для работы.
            </p>
            @guest
                <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 hover:bg-gray-800 border border-transparent rounded-md">
                    Войти в систему
                </a>
            @endguest
        </div>

        <div class="mt-8 grid sm:grid-cols-3 gap-4">
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <h3 class="font-medium text-gray-800 mb-1">Учёт материалов</h3>
                <p class="text-sm text-gray-500">Ведение складского учёта и номенклатуры</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <h3 class="font-medium text-gray-800 mb-1">Заявки и списание</h3>
                <p class="text-sm text-gray-500">Оформление заявок и списание материалов</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <h3 class="font-medium text-gray-800 mb-1">Отчётность</h3>
                <p class="text-sm text-gray-500">Отчёты и аналитика по материалам</p>
            </div>
        </div>
    </main>

    <footer class="max-w-4xl mx-auto px-4 py-4 mt-12 border-t border-gray-200 text-center text-sm text-gray-500">
        ИС учёта материалов для теплоснабжающей организации
    </footer>
</body>
</html>
