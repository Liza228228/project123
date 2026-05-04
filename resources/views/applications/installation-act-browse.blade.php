@php
    $actBasename = $selectedApplication && filled(trim((string) ($selectedApplication->act_of_installation ?? '')))
        ? basename($selectedApplication->act_of_installation)
        : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.index')">Заявки</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Акт установки по заявке
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-8 sm:space-y-10">
                <p class="rounded-xl border border-stone-200/80 bg-stone-50/60 px-4 py-3 text-sm text-stone-800 dark:border-stone-600 dark:bg-stone-800/35 dark:text-stone-100">
                    Выберите заявку.
                </p>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('applications.installation-act.layout-fill.index') }}" class="ui-btn ui-btn--secondary ui-btn--sm">
                        Заполнить макет заявки (только поля)
                    </a>
                </div>

                <form method="get" action="{{ route('applications.installation-act.browse') }}" class="space-y-4">
                    <section class="space-y-4" aria-labelledby="browse-act-select">
                        <h3 id="browse-act-select" class="app-section-title">Заявка</h3>
                        <div>
                            <label for="browse-application-id" class="app-form-label">Выберите заявку</label>
                            <select
                                id="browse-application-id"
                                name="application_id"
                                class="app-select"
                                onchange="this.form.submit()"
                            >
                                <option value="">— Заявка не выбрана —</option>
                                @foreach($applications as $app)
                                    <option value="{{ $app->id }}" @selected((int) $selectedId === (int) $app->id)>
                                        №{{ $app->id }}
                                        @if($app->subdivision)
                                            · {{ $app->subdivision->name }}
                                        @endif
                                        · {{ $app->desired_delivery_date?->format('d.m.Y') ?? '—' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </section>
                </form>

                @if($applications->isEmpty())
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100">
                        Нет заявок с загруженным актом или фотографиями.
                    </div>
                @endif

                @if($selectedId > 0 && ! $selectedApplication)
                    <div class="rounded-xl border border-red-200/80 bg-red-50/60 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                        Заявка не найдена в списке или у неё нет акта/фото.
                    </div>
                @endif

                @if($selectedApplication)
                    <section class="space-y-4 border-t border-stone-100 pt-8 dark:border-stone-800" aria-labelledby="browse-act-file">
                        <h3 id="browse-act-file" class="app-section-title">Файл акта</h3>
                        @if($actBasename)
                            <p class="text-sm text-stone-600 dark:text-stone-400">
                                <span class="font-mono text-xs">{{ $actBasename }}</span>
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    href="{{ route('applications.installation-act.view', $selectedApplication) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="ui-btn ui-btn--primary ui-btn--sm"
                                >
                                    Открыть в браузере
                                </a>
                                <a
                                    href="{{ route('applications.installation-act.download', $selectedApplication) }}"
                                    class="ui-btn ui-btn--secondary ui-btn--sm"
                                >
                                    Скачать
                                </a>
                            </div>
                        @else
                            <p class="text-sm text-stone-600 dark:text-stone-400">Файл акта не приложён.</p>
                        @endif
                    </section>

                    @if($selectedApplication->installationActPhotos->isNotEmpty())
                        <section class="space-y-4 border-t border-stone-100 pt-8 dark:border-stone-800" aria-labelledby="browse-act-photos">
                            <h3 id="browse-act-photos" class="app-section-title">Фото к акту</h3>
                            <p class="text-xs text-stone-500 dark:text-stone-400">Нажмите миниатюру — фото откроется на этой же странице. Стрелки или кнопки «Назад»/«Вперёд» — между снимками, Esc — закрыть.</p>
                            <x-installation-act-photo-gallery :application="$selectedApplication" thumb-size="md" />
                        </section>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
