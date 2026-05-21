<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 w-full min-w-0">
            <h2 class="font-semibold text-xl text-stone-900 dark:text-white leading-tight min-w-0 break-words">
                Макеты отчетов (PDF)
            </h2>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto sm:justify-end shrink-0">
                @if ($canDesignReportLayouts)
                    <a href="{{ route('boiler-chief.document-header-layouts.index') }}"
                       class="ui-btn ui-btn--secondary min-h-[2.75rem] sm:min-h-0 whitespace-nowrap">
                        Макеты шапок
                    </a>
                    <a href="{{ route('boiler-chief.request-layouts.create') }}"
                       class="ui-btn ui-btn--primary min-h-[2.75rem] sm:min-h-0 whitespace-nowrap">
                        Новый макет
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 min-h-[60vh]">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-xl border border-orange-200/80 bg-orange-50 dark:bg-orange-950/35 dark:border-orange-900/50 text-orange-950 dark:text-orange-100 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-orange-50/35 shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35">
                <div class="p-4 sm:p-6">
                    @if($layouts->isEmpty())
                        <p class="py-12 text-center text-sm text-stone-500 dark:text-stone-400 max-w-lg mx-auto leading-relaxed">
                            Макетов пока нет. Создайте первый или выполните сидер демо-данных.
                        </p>
                    @else
                        <div class="md:hidden space-y-4">
                            @foreach($layouts as $layout)
                                <article class="rounded-xl border border-orange-100/90 dark:border-orange-900/35 bg-orange-50/20 dark:bg-orange-950/10 p-4 space-y-3 shadow-sm">
                                    <div>
                                        <p class="text-sm font-medium text-stone-900 dark:text-white break-words">{{ $layout->title }}</p>
                                        <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">
                                            Шапка: {{ ($layout->has_header || $layout->document_header_layout_id) ? 'да' : 'нет' }}
                                            · Макет шапки: {{ $layout->documentHeaderLayout?->title ?? '—' }}
                                        </p>
                                        <p class="text-xs text-stone-500 dark:text-stone-400">{{ $layout->updated_at?->format('d.m.Y H:i') }}</p>
                                    </div>
                                    @if ($canDesignReportLayouts)
                                        <div class="flex flex-col gap-2">
                                            <a href="{{ route('boiler-chief.request-layouts.edit', $layout) }}" class="ui-btn ui-btn--secondary justify-center">Изменить</a>
                                            <form method="POST" action="{{ route('boiler-chief.request-layouts.destroy', $layout) }}"
                                                data-app-confirm="Удалить макет?"
                                                data-app-confirm-variant="danger"
                                                data-app-confirm-label="Да, удалить">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ui-btn ui-btn--danger w-full justify-center">Удалить</button>
                                            </form>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>

                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-orange-200/80 bg-orange-50/90 dark:border-orange-800/50 dark:bg-orange-950/35">
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Название</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Шапка</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Макет шапки</th>
                                        @if ($canDesignReportLayouts)
                                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Конструктор</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-orange-100/90 dark:divide-orange-900/35">
                                    @foreach($layouts as $layout)
                                        <tr class="bg-orange-50/20 hover:bg-orange-50/55 dark:bg-stone-950 dark:hover:bg-orange-950/25">
                                            <td class="px-4 py-3 font-medium text-stone-900 dark:text-stone-100">{{ $layout->title }}</td>
                                            <td class="px-4 py-3 text-stone-600 dark:text-stone-300">
                                                {{ ($layout->has_header || $layout->document_header_layout_id) ? 'Да' : 'Нет' }}
                                            </td>
                                            <td class="px-4 py-3 text-stone-600 dark:text-stone-300">
                                                {{ $layout->documentHeaderLayout?->title ?? '—' }}
                                            </td>
                                            @if ($canDesignReportLayouts)
                                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                                    <a href="{{ route('boiler-chief.request-layouts.edit', $layout) }}" class="ui-btn ui-btn--secondary ui-btn--sm">Изменить</a>
                                                    <form class="inline-block" method="POST" action="{{ route('boiler-chief.request-layouts.destroy', $layout) }}"
                                                        data-app-confirm="Удалить макет?"
                                                        data-app-confirm-variant="danger"
                                                        data-app-confirm-label="Да, удалить">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="ui-btn ui-btn--danger ui-btn--sm">Удалить</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
