<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Шаблоны подвалов отчёта
            </h2>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <a href="{{ route('applications.report.footers.create') }}" class="ui-btn ui-btn--primary whitespace-nowrap">
                    Новый подвал
                </a>
                <a href="{{ route('applications.report.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50 whitespace-nowrap">
                    К сборке отчёта
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-stone-100 dark:bg-stone-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-stone-950 rounded-lg border border-stone-200 dark:border-stone-800 shadow-sm overflow-hidden">
                <ul class="divide-y divide-stone-200 dark:divide-stone-800">
                    @forelse($footers as $footer)
                        <li class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <span class="text-black dark:text-white font-medium">{{ $footer->name }}</span>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('applications.report.footers.edit', $footer) }}" class="ui-btn ui-btn--primary">Изменить</a>
                                <form method="post" action="{{ route('applications.report.footers.destroy', $footer) }}" onsubmit="return confirm('Удалить этот шаблон подвала?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600 hover:bg-stone-50 dark:hover:bg-stone-900/40">Удалить</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-black dark:text-white opacity-80">Шаблонов пока нет. Создайте первый по кнопке «Новый подвал».</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
