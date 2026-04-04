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
<body class="min-h-screen bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200 antialiased flex flex-col">
    <header class="bg-slate-50/95 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div>
                <h1 class="font-semibold tracking-wide text-slate-800 dark:text-slate-100">КТ-Ресурс</h1>

            </div>
            <nav>
                @auth
                    <a href="{{ url('/dashboard') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-slate-700 hover:bg-slate-800 border border-slate-700 rounded-lg">
                        Панель управления
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-slate-700 hover:bg-slate-800 border border-slate-700 rounded-lg">
                        Вход
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1 py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 sm:rounded-lg">
                <div class="p-8 text-slate-900 dark:text-slate-100">
                    <h2 class="text-2xl font-semibold text-slate-800 dark:text-slate-100 mb-3">
                        Централизованный контроль заявок и материалов
                    </h2>
                    <p class="text-slate-600 dark:text-slate-300 max-w-3xl">
                        КТ-Ресурс помогает подразделениям создавать и согласовывать заявки, контролировать статус позиций
                        и вести прозрачный процесс закупки оборудования в едином интерфейсе.
                    </p>
                    @guest
                        <a href="{{ route('login') }}" class="mt-6 inline-flex items-center px-6 py-3 text-base font-semibold text-white bg-slate-700 hover:bg-slate-800 border border-transparent rounded-lg">
                            Войти в систему
                        </a>
                    @endguest
                </div>
            </div>

            <div class="mt-6 grid sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-slate-800 dark:text-slate-100">Управление заявками</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Создание, редактирование и контроль повторных заявок по подразделениям.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-slate-800 dark:text-slate-100">Согласование и контроль</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Отметки одобрения, причины отклонений и прозрачный процесс принятия решений.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-slate-800 dark:text-slate-100">Ролевой доступ</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Разделение прав по ролям: администратор, директор, мастер участка и другие.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-slate-50/95 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-700 py-4 mt-auto shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-slate-500 dark:text-slate-300">
            КТ-Ресурс —  система учёта  материалов
        </div>
    </footer>
</body>
</html>
