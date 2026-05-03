<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('boiler-chief.request-layouts.index')">Макеты заявок (PDF)</x-page-header-nav>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4">
                <h2 class="font-semibold text-xl text-stone-900 dark:text-white leading-tight min-w-0 break-words">
                    Заявки по макетам
                </h2>
                <a href="{{ route('boiler-chief.layout-applications.create') }}"
                   class="ui-btn ui-btn--primary ui-btn--sm inline-flex min-h-[2.75rem] items-center justify-center px-4 py-2.5 sm:min-h-0 sm:py-2 whitespace-nowrap">
                    Новая заявка
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 min-h-[50vh]">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-orange-50/35 shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35">
                <div class="p-4 sm:p-6">
                    @if($submissions->isEmpty())
                        <p class="py-12 text-center text-sm text-stone-500 dark:text-stone-400">Заявок нет.</p>
                    @else
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-orange-200/80 bg-orange-50/90 dark:border-orange-800/50 dark:bg-orange-950/35">
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">№</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Макет</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Автор</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Действия</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-orange-100/90 dark:divide-orange-900/35">
                                    @foreach($submissions as $row)
                                        <tr class="bg-orange-50/20 hover:bg-orange-50/55 dark:bg-stone-950 dark:hover:bg-orange-950/25">
                                            <td class="px-4 py-3 font-medium text-stone-900 dark:text-stone-100">{{ $row->id }}</td>
                                            <td class="px-4 py-3 text-stone-700 dark:text-stone-200">{{ $row->requestLayout?->title ?? '—' }}</td>
                                            <td class="px-4 py-3 text-stone-600 dark:text-stone-300">{{ $row->creator?->fullName() ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <a href="{{ route('boiler-chief.layout-applications.pdf', $row) }}"
                                                   class="ui-btn ui-btn--primary ui-btn--sm inline-flex rounded-lg px-3 py-1.5 text-xs">
                                                    PDF
                                                </a>
                                                <form method="POST"
                                                      action="{{ route('boiler-chief.layout-applications.destroy', $row) }}"
                                                      class="inline-block ml-2"
                                                      onsubmit="return confirm('Удалить эту заявку по макету?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="ui-btn ui-btn--danger ui-btn--sm inline-flex rounded-lg px-3 py-1.5 text-xs">
                                                        Удалить
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="md:hidden space-y-3">
                            @foreach($submissions as $row)
                                <article class="rounded-xl border border-orange-200/80 bg-orange-50/30 p-4 shadow-sm dark:border-orange-900/45 dark:bg-stone-950 space-y-2">
                                    <p class="text-sm font-medium text-stone-900 dark:text-white">№ {{ $row->id }} · {{ $row->requestLayout?->title ?? '—' }}</p>
                                    <p class="text-xs text-stone-500 dark:text-stone-400">Автор: {{ $row->creator?->fullName() ?? '—' }}</p>
                                    <a href="{{ route('boiler-chief.layout-applications.pdf', $row) }}"
                                       class="ui-btn ui-btn--primary inline-flex w-full justify-center rounded-lg px-3 py-2 text-sm">PDF</a>
                                    <form method="POST"
                                          action="{{ route('boiler-chief.layout-applications.destroy', $row) }}"
                                          onsubmit="return confirm('Удалить эту заявку по макету?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn--danger inline-flex w-full justify-center rounded-lg px-3 py-2 text-sm">Удалить</button>
                                    </form>
                                </article>
                            @endforeach
                        </div>
                        <div class="mt-4">{{ $submissions->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
