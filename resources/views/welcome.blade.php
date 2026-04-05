<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>КТ-Ресурс — система учета материалов</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 antialiased flex flex-col">
    <header class="bg-orange-50/95 dark:bg-orange-900 border-b border-orange-200 dark:border-orange-700 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div>
                <h1 class="font-semibold tracking-wide text-orange-800 dark:text-orange-100">КТ-Ресурс</h1>

            </div>
            <nav>
                @auth
                    <a href="{{ url('/dashboard') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 border border-orange-600 rounded-lg">
                        Панель управления
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 border border-orange-600 rounded-lg">
                        Вход
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1 py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-orange-800 overflow-hidden shadow-sm border border-orange-200 dark:border-orange-700 sm:rounded-lg">
                <div class="p-8 text-orange-900 dark:text-orange-100">
                    <h2 class="text-2xl font-semibold text-orange-800 dark:text-orange-100 mb-3">
                        Централизованный контроль заявок и материалов
                    </h2>
                    <p class="text-orange-600 dark:text-orange-300 max-w-3xl">
                        КТ-Ресурс помогает подразделениям создавать и согласовывать заявки, контролировать статус позиций
                        и вести прозрачный процесс закупки оборудования в едином интерфейсе.
                    </p>
                    @guest
                        <a href="{{ route('login') }}" class="mt-6 inline-flex items-center px-6 py-3 text-base font-semibold text-white bg-orange-600 hover:bg-orange-700 border border-transparent rounded-lg">
                            Войти в систему
                        </a>
                    @endguest
                </div>
            </div>

            <div class="mt-6 grid sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-orange-800 overflow-hidden shadow-sm border border-orange-200 dark:border-orange-700 sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-orange-800 dark:text-orange-100">Управление заявками</h3>
                        <p class="mt-2 text-sm text-orange-600 dark:text-orange-300">Создание, редактирование и контроль повторных заявок по подразделениям.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-orange-800 overflow-hidden shadow-sm border border-orange-200 dark:border-orange-700 sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-orange-800 dark:text-orange-100">Согласование и контроль</h3>
                        <p class="mt-2 text-sm text-orange-600 dark:text-orange-300">Отметки одобрения, причины отклонений и прозрачный процесс принятия решений.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-orange-800 overflow-hidden shadow-sm border border-orange-200 dark:border-orange-700 sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-orange-800 dark:text-orange-100">Ролевой доступ</h3>
                        <p class="mt-2 text-sm text-orange-600 dark:text-orange-300">Разделение прав по ролям: администратор, директор, мастер участка и другие.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-orange-50/95 dark:bg-orange-900 border-t border-orange-200 dark:border-orange-700 py-4 mt-auto shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-orange-500 dark:text-orange-300">
            КТ-Ресурс —  система учёта  материалов
        </div>
    </footer>
</body>
</html>
