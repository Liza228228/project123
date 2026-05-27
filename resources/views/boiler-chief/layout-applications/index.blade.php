<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            @if (Auth::user()->hasAnyRoleId(\App\Models\User::REPORT_LAYOUT_DESIGNER_ROLE_IDS))
                <x-page-header-nav :href="route('boiler-chief.request-layouts.index')">
                    Макеты отчетов (PDF)
                </x-page-header-nav>
            @endif
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4">
                <h2 class="font-semibold text-xl text-stone-900 dark:text-white leading-tight min-w-0 break-words">
                    Отчеты по макетам
                </h2>
                <a href="{{ route('boiler-chief.layout-applications.create') }}"
                   class="ui-btn ui-btn--primary ui-btn--sm inline-flex min-h-[2.75rem] items-center justify-center px-4 py-2.5 sm:min-h-0 sm:py-2 whitespace-nowrap">
                    Новый отчет
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 min-h-[50vh]">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-orange-50/35 shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35">
                <div class="p-4 sm:p-6 space-y-6">
                    @if(($layouts ?? collect())->isNotEmpty())
                        <section class="space-y-3" aria-labelledby="layout-fill-catalog-heading">
                            <h3 id="layout-fill-catalog-heading" class="text-sm font-semibold text-stone-900 dark:text-white">
                                Макеты для заполнения
                            </h3>
                            <p class="text-xs text-stone-600 dark:text-stone-400">
                                Выберите макет и заполните поля — после сохранения отчёт появится в списке ниже.
                            </p>
                            <ul class="divide-y divide-orange-100/90 overflow-hidden rounded-xl border border-orange-200/80 dark:divide-orange-900/35 dark:border-orange-900/45">
                                @foreach($layouts as $layout)
                                    <li class="flex flex-col gap-2 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:bg-stone-950/40">
                                        <p class="text-sm font-medium text-stone-900 dark:text-white break-words">{{ $layout->title }}</p>
                                        <a href="{{ route('boiler-chief.layout-applications.create', ['layout' => $layout->id]) }}"
                                           class="ui-btn ui-btn--primary ui-btn--sm w-full justify-center whitespace-nowrap sm:w-auto">
                                            Заполнить
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @elseif($submissions->isEmpty())
                        <p class="rounded-xl border border-amber-200/80 bg-amber-50/60 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/45 dark:bg-amber-950/25 dark:text-amber-100">
                            Доступных макетов пока нет. Запустите сидер акта установки или создайте макет в разделе «Макеты отчетов (PDF)».
                        </p>
                    @endif

                    @if($submissions->isEmpty())
                        <p class="py-6 text-center text-sm text-stone-500 dark:text-stone-400 border-t border-orange-100/90 dark:border-orange-900/35">
                            Сохранённых отчётов пока нет.
                        </p>
                    @else
                        <form method="GET" action="{{ route('boiler-chief.layout-applications.index') }}" class="mb-4 flex flex-wrap items-end gap-3" data-auto-submit="filter">
                            <div class="min-w-0">
                                <label for="layout-apps-per-page" class="app-form-label">На странице</label>
                                <select id="layout-apps-per-page" name="per_page" class="app-select min-w-[10rem]">
                                    @foreach($allowedPerPage as $size)
                                        <option value="{{ $size }}" @selected((int) ($perPage ?? 0) === (int) $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-orange-200/80 bg-orange-50/90 dark:border-orange-800/50 dark:bg-orange-950/35">
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Макет</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Автор</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Действия</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-orange-100/90 dark:divide-orange-900/35">
                                    @foreach($submissions as $row)
                                        <tr class="bg-orange-50/20 hover:bg-orange-50/55 dark:bg-stone-950 dark:hover:bg-orange-950/25">
                                            <td class="px-4 py-3 text-stone-700 dark:text-stone-200">
                                                @if ($row->requestLayout)
                                                    {{ $row->requestLayout->title }}
                                                    @if ($row->requestLayout->trashed())
                                                        <span class="text-xs text-stone-500 dark:text-stone-400">(макет удалён)</span>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-stone-600 dark:text-stone-300">{{ $row->creator?->fullName() ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <a href="{{ route('boiler-chief.layout-applications.pdf', $row) }}"
                                                   class="ui-btn ui-btn--primary ui-btn--sm inline-flex rounded-lg px-3 py-1.5 text-xs">
                                                    PDF
                                                </a>
                                                <a href="{{ route('boiler-chief.layout-applications.edit', $row) }}"
                                                   class="ui-btn ui-btn--secondary ui-btn--sm inline-flex rounded-lg px-3 py-1.5 text-xs ml-2">
                                                    Редактировать
                                                </a>
                                                <form method="POST"
                                                      action="{{ route('boiler-chief.layout-applications.destroy', $row) }}"
                                                      class="inline-block ml-2"
                                                      data-app-confirm="Отчет будет удалён без возможности восстановления. Продолжить?"
                                                      data-app-confirm-title="Удалить отчет по макету?"
                                                      data-app-confirm-variant="danger"
                                                      data-app-confirm-label="Да, удалить">
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
                                    <p class="text-sm font-medium text-stone-900 dark:text-white">
                                        @if ($row->requestLayout)
                                            {{ $row->requestLayout->title }}
                                            @if ($row->requestLayout->trashed())
                                                <span class="text-xs font-normal text-stone-500 dark:text-stone-400">(макет удалён)</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </p>
                                    <p class="text-xs text-stone-500 dark:text-stone-400">Автор: {{ $row->creator?->fullName() ?? '—' }}</p>
                                    <a href="{{ route('boiler-chief.layout-applications.pdf', $row) }}"
                                       class="ui-btn ui-btn--primary inline-flex w-full justify-center rounded-lg px-3 py-2 text-sm">PDF</a>
                                    <a href="{{ route('boiler-chief.layout-applications.edit', $row) }}"
                                       class="ui-btn ui-btn--secondary inline-flex w-full justify-center rounded-lg px-3 py-2 text-sm">Редактировать</a>
                                    <form method="POST"
                                          action="{{ route('boiler-chief.layout-applications.destroy', $row) }}"
                                          data-app-confirm="Отчет будет удалён без возможности восстановления. Продолжить?"
                                          data-app-confirm-title="Удалить отчет по макету?"
                                          data-app-confirm-variant="danger"
                                          data-app-confirm-label="Да, удалить">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn--danger inline-flex w-full justify-center rounded-lg px-3 py-2 text-sm">
                                            Удалить
                                        </button>
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
