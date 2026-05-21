<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="$layoutFillParentHref ?? route('applications.installation-act.upload')">{{ $layoutFillParentLabel ?? 'Акт установки' }}</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                {{ $layoutFillPageTitle ?? 'Заполнение макета заявки' }}
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-6">
                @unless(Auth::user()?->hasRoleId(3))
                    <p class="rounded-xl border border-stone-200/80 bg-stone-50/60 px-4 py-3 text-sm text-stone-800 dark:border-stone-600 dark:bg-stone-800/35 dark:text-stone-100">
                        Выберите готовый макет и заполните только поля.
                    </p>
                @endunless
                <div class="flex flex-wrap gap-2">
                    <span class="ui-btn ui-btn--primary ui-btn--sm">Заполнение макета</span>
                    <a href="{{ route('applications.installation-act.layout-fill.submissions') }}" class="ui-btn ui-btn--secondary ui-btn--sm">
                        Просмотр созданных отчетов
                    </a>
                </div>

                @if($layouts->isEmpty())
                    <p class="text-sm text-stone-600 dark:text-stone-400">Доступных макетов пока нет.</p>
                @else
                    <ul class="divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
                        @foreach($layouts as $layout)
                            <li class="bg-white px-4 py-3 dark:bg-stone-900/40">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-black dark:text-white break-words">{{ $layout->title }}</p>
                                        @if($layout->approver)
                                            <p class="text-xs text-stone-500 dark:text-stone-400">
                                                Согласующий:
                                                {{ $layout->approver->surname }}
                                                {{ $layout->approver->name }}
                                            </p>
                                        @endif
                                    </div>
                                    <a href="{{ route($layoutFillRouteName ?? 'applications.installation-act.layout-fill.fill', $layout) }}"
                                       class="ui-btn ui-btn--primary ui-btn--sm w-full justify-center whitespace-nowrap sm:w-auto">
                                        Заполнить поля
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
