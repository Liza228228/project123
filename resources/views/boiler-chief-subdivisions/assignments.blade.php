<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('foreman-subdivisions.index')">Подразделения и склады</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Начальники котельных — подразделения
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-xl border border-stone-200/90 bg-stone-50/90 px-4 py-3 text-sm text-stone-800 dark:border-stone-600 dark:bg-stone-800/40 dark:text-stone-100 sm:mb-6">
                {{ session('status') }}
            </div>
        @endif

        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8">
                <div class="mb-6 border-b border-stone-100 pb-4 dark:border-stone-800 sm:pb-6">
                    <h3 class="app-section-title">Начальники котельных</h3>
                    <p class="mt-2 text-xs text-stone-500 dark:text-stone-400 max-w-2xl">
                        Назначьте подразделения для согласования заявок на уровне котельной — в том же духе, что и выбор подразделения в заявке.
                    </p>
                </div>

                <form method="get" action="{{ route('boiler-chief-subdivisions.assignments') }}" class="mb-6 space-y-3">
                    <div class="app-filter-panel">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                            <div class="min-w-0">
                                <label for="boiler-chief-assignments-q" class="app-form-label">Поиск</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    </span>
                                    <input
                                        type="search"
                                        name="q"
                                        id="boiler-chief-assignments-q"
                                        value="{{ $search }}"
                                        placeholder="ФИО или e-mail…"
                                        autocomplete="off"
                                        class="app-input app-input--with-icon"
                                    >
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end sm:pb-0.5">
                                <button type="submit" class="ui-btn ui-btn--primary w-full min-h-[3rem] sm:min-h-0 sm:w-auto">Найти</button>
                                @if($search !== '')
                                    <a href="{{ route('boiler-chief-subdivisions.assignments') }}" class="ui-btn ui-btn--secondary w-full min-h-[3rem] content-center text-center sm:min-h-0 sm:w-auto">Сбросить</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>

                @if($chiefs->isEmpty())
                    @if($search !== '')
                        <p class="py-8 text-center text-sm text-stone-600 dark:text-stone-400">
                            По запросу «{{ $search }}» начальники не найдены.
                            <a href="{{ route('boiler-chief-subdivisions.assignments') }}" class="font-medium text-orange-700 underline decoration-orange-300 underline-offset-2 hover:text-orange-800 dark:text-orange-300 dark:hover:text-orange-200">Показать всех</a>
                        </p>
                    @else
                        <p class="py-8 text-center text-sm text-stone-600 dark:text-stone-400">
                            Пользователей с ролью «Начальник котельной» пока нет. Создайте пользователя в разделе «Пользователи».
                        </p>
                    @endif
                @else
                    <div class="md:hidden space-y-4">
                        @foreach($chiefs as $chief)
                            <article class="app-equipment-card space-y-3">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Начальник котельной</p>
                                    <p class="mt-1 text-sm font-medium text-stone-900 dark:text-stone-100 break-words">{{ $chief->surname }} {{ $chief->name }} {{ $chief->patronymic }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">Почта</p>
                                    <p class="mt-0.5 text-sm text-stone-700 dark:text-stone-300 break-all">{{ $chief->email }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">Подразделения</p>
                                    @if($chief->boilerChiefSubdivisions->isEmpty())
                                        <p class="mt-0.5 text-sm text-stone-600 dark:text-stone-400">—</p>
                                    @else
                                        <ul class="mt-1 space-y-1">
                                            @foreach($chief->boilerChiefSubdivisions as $subdivision)
                                                <li class="text-sm text-stone-800 dark:text-stone-200 break-words">{{ $subdivision->name }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <a href="{{ route('boiler-chief-subdivisions.edit', $chief) }}" class="ui-btn ui-btn--primary ui-btn--lg flex w-full justify-center min-h-[3rem] py-3 [touch-action:manipulation]">
                                    Назначить подразделения
                                </a>
                            </article>
                        @endforeach
                    </div>

                    <div class="hidden md:block app-table-shell">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400">Начальник котельной</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400">Почта</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400">Подразделения</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($chiefs as $chief)
                                    <tr class="align-top border-t border-stone-100 dark:border-stone-800">
                                        <td class="px-4 py-3 text-sm text-stone-900 dark:text-stone-100 align-top">
                                            <div class="font-medium">{{ $chief->surname }} {{ $chief->name }} {{ $chief->patronymic }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-stone-600 dark:text-stone-400 align-top">
                                            {{ $chief->email }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-stone-800 dark:text-stone-200 align-top">
                                            @if($chief->boilerChiefSubdivisions->isEmpty())
                                                —
                                            @else
                                                <ul class="space-y-1">
                                                    @foreach($chief->boilerChiefSubdivisions as $subdivision)
                                                        <li>{{ $subdivision->name }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right align-top whitespace-nowrap w-[1%]">
                                            <a href="{{ route('boiler-chief-subdivisions.edit', $chief) }}" class="ui-btn ui-btn--primary [touch-action:manipulation]">
                                                Назначить
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
