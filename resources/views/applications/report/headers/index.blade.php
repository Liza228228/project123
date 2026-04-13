<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Шаблоны шапок отчёта
            </h2>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <a href="{{ route('applications.report.headers.create') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-orange-600 shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 whitespace-nowrap">
                    Новая шапка
                </a>
                <a href="{{ route('applications.report.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-orange-300 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/30 hover:bg-orange-100 dark:hover:bg-orange-900/50 whitespace-nowrap">
                    К сборке отчёта
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-orange-100 dark:bg-orange-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-orange-950 rounded-lg border border-orange-200 dark:border-orange-800 shadow-sm overflow-hidden">
                <ul class="divide-y divide-orange-200 dark:divide-orange-800">
                    @forelse($headers as $header)
                        <li class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <span class="text-black dark:text-white font-medium">{{ $header->name }}</span>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('applications.report.headers.edit', $header) }}" class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white rounded-lg bg-orange-600 hover:bg-orange-700">Изменить</a>
                                <form method="post" action="{{ route('applications.report.headers.destroy', $header) }}" onsubmit="return confirm('Удалить этот шаблон шапки?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-black dark:text-white rounded-lg border border-orange-300 dark:border-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/40">Удалить</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-black dark:text-white opacity-80">Шаблонов пока нет. Создайте первый по кнопке «Новая шапка».</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
