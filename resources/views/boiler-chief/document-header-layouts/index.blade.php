<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4">
                <h2 class="font-semibold text-xl text-stone-900 dark:text-white leading-tight min-w-0 break-words">
                    Макеты шапок документов
                </h2>
                <a href="{{ route('boiler-chief.document-header-layouts.create') }}"
                   class="ui-btn ui-btn--primary ui-btn--sm inline-flex min-h-[2.75rem] items-center justify-center sm:min-h-0 sm:py-2">
                    Новый макет шапки
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 min-h-[60vh]">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-orange-50/35 shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35">
                @if($layouts->isEmpty())
                    <div class="py-16 px-6 text-center text-stone-400 dark:text-stone-500 text-sm leading-relaxed">
                        Макетов шапок пока нет. Создайте первый — затем выберите его в макете отчета.
                    </div>
                @else
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-orange-200/80 bg-orange-50/90 dark:border-orange-800/50 dark:bg-orange-950/35">
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Название</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orange-100/90 dark:divide-orange-900/35">
                                @foreach($layouts as $layout)
                                    <tr class="bg-orange-50/20 hover:bg-orange-50/55 dark:bg-stone-950 dark:hover:bg-orange-950/25">
                                        <td class="px-6 py-4 font-medium text-stone-900 dark:text-stone-100">{{ $layout->title }}</td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap space-x-2">
                                            <a href="{{ route('boiler-chief.document-header-layouts.edit', $layout) }}"
                                               class="ui-btn ui-btn--secondary ui-btn--sm inline-flex items-center">
                                                Изменить
                                            </a>
                                            <form class="inline-block" method="POST" action="{{ route('boiler-chief.document-header-layouts.destroy', $layout) }}"
                                                data-app-confirm="Удалить этот макет шапки?"
                                                data-app-confirm-variant="danger"
                                                data-app-confirm-label="Да, удалить">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ui-btn ui-btn--danger ui-btn--sm inline-flex items-center">
                                                    Удалить
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="md:hidden divide-y divide-orange-100/90 dark:divide-orange-900/35">
                        @foreach($layouts as $layout)
                            <div class="px-4 py-4 space-y-3">
                                <p class="font-medium text-stone-900 dark:text-stone-100">{{ $layout->title }}</p>
                                <div class="flex flex-col gap-2">
                                    <a href="{{ route('boiler-chief.document-header-layouts.edit', $layout) }}"
                                       class="ui-btn ui-btn--secondary inline-flex justify-center">
                                        Изменить
                                    </a>
                                    <form method="POST" action="{{ route('boiler-chief.document-header-layouts.destroy', $layout) }}"
                                        data-app-confirm="Удалить этот макет шапки?"
                                        data-app-confirm-variant="danger"
                                        data-app-confirm-label="Да, удалить">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn--danger w-full inline-flex justify-center">
                                            Удалить
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
