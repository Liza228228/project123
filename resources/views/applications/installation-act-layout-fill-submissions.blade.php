@php // шаблон страницы
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="$layoutFillParentHref ?? route('applications.installation-act.upload')">{{ $layoutFillParentLabel ?? 'Акт установки' }}</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Просмотр созданных отчетов
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-6">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('applications.installation-act.layout-fill.index') }}" class="ui-btn ui-btn--secondary ui-btn--sm">
                        Заполнение макета
                    </a>
                    <span class="ui-btn ui-btn--primary ui-btn--sm">Просмотр созданных отчетов</span>
                </div>

                @if(($submissions ?? collect())->isEmpty())
                    <p class="text-sm text-stone-600 dark:text-stone-400">Пока нет сохраненных отчетов.</p>
                @else
                    <form method="GET" action="{{ route('applications.installation-act.layout-fill.submissions') }}" class="mb-4 flex flex-wrap items-end gap-3" data-auto-submit="filter">
                        <div class="min-w-0">
                            <label for="layout-fill-submissions-per-page" class="app-form-label">На странице</label>
                            <select id="layout-fill-submissions-per-page" name="per_page" class="app-select min-w-[10rem]">
                                @foreach($allowedPerPage as $size)
                                    <option value="{{ $size }}" @selected((int) ($perPage ?? 0) === (int) $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                    <ul class="divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
                        @foreach($submissions as $submission)
                            <li class="bg-white px-4 py-3 dark:bg-stone-900/40">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-black dark:text-white break-words">
                                            №{{ $submission->id }} · {{ $submission->requestLayout?->title ?? 'Макет удален' }}
                                        </p>
                                        <p class="text-xs text-stone-500 dark:text-stone-400">
                                            {{ $submission->created_at?->format('d.m.Y H:i') ?? '—' }}
                                        </p>
                                    </div>
                                    <a href="{{ route('applications.installation-act.layout-fill.submission-pdf', $submission) }}"
                                       class="ui-btn ui-btn--secondary ui-btn--sm w-full justify-center whitespace-nowrap sm:w-auto">
                                        Открыть PDF
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-3">
                        {{ $submissions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

