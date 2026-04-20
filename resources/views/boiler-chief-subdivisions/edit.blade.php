<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('boiler-chief-subdivisions.assignments')">Назад к начальникам котельных</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Подразделения начальника котельной
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8">
                <div class="mb-6 rounded-xl border border-stone-200/90 bg-stone-50/80 px-4 py-4 dark:border-stone-600 dark:bg-stone-800/35 sm:px-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400">Начальник котельной</p>
                    <p class="mt-1 text-base font-semibold text-stone-900 dark:text-stone-100">
                        {{ $chief->surname }} {{ $chief->name }} {{ $chief->patronymic }}
                    </p>
                    <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">{{ $chief->email }}</p>
                </div>

                <form method="POST" action="{{ route('boiler-chief-subdivisions.update', $chief) }}" class="space-y-8 sm:space-y-10">
                    @csrf
                    @method('PUT')

                    <section class="space-y-4" aria-labelledby="bc-subdivisions-section">
                        <h3 id="bc-subdivisions-section" class="app-section-title">Подразделения</h3>
                        <p class="text-xs text-stone-500 dark:text-stone-400 -mt-1 max-w-2xl">
                            Отметьте подразделения, за которые отвечает начальник котельной (заявки мастеров этих участков сначала попадут к нему).
                        </p>

                        <div class="rounded-xl border border-stone-200/80 bg-white px-4 py-3 dark:border-stone-600/80 dark:bg-stone-900/30 sm:px-5 sm:py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400 mb-2">Сейчас назначено</p>
                            @if($chief->boilerChiefSubdivisions->isEmpty())
                                <p class="text-sm text-stone-500 dark:text-stone-400">Подразделения пока не назначены.</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach($chief->boilerChiefSubdivisions as $assigned)
                                        <span class="inline-flex items-center rounded-full border border-orange-200/80 bg-orange-50/90 px-3 py-1 text-xs font-medium text-stone-800 dark:border-orange-900/50 dark:bg-orange-950/40 dark:text-stone-100">
                                            {{ $assigned->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div>
                            <label for="bc-subdivision-search" class="app-form-label">Поиск по списку</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </span>
                                <input
                                    id="bc-subdivision-search"
                                    type="search"
                                    autocomplete="off"
                                    placeholder="Начните вводить название…"
                                    class="app-input app-input--with-icon"
                                />
                            </div>
                        </div>

                        <div class="max-h-80 overflow-y-auto rounded-xl border border-dashed border-orange-300/80 bg-orange-50/40 p-4 dark:border-orange-800/50 dark:bg-orange-950/20 sm:p-5">
                            <div id="bc-subdivision-list" class="grid gap-3 sm:grid-cols-2">
                                @foreach($subdivisions as $subdivision)
                                    <label class="bc-subdivision-option flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200/90 bg-white px-3 py-3 text-sm text-stone-800 shadow-sm transition hover:border-orange-200/90 hover:bg-orange-50/30 dark:border-stone-600 dark:bg-stone-900/50 dark:text-stone-100 dark:hover:border-orange-900/40 dark:hover:bg-orange-950/25" data-name="{{ mb_strtolower($subdivision->name) }}">
                                        <input
                                            type="checkbox"
                                            name="subdivision_ids[]"
                                            value="{{ $subdivision->id }}"
                                            @checked($chief->boilerChiefSubdivisions->contains('id', $subdivision->id))
                                            class="mt-0.5 shrink-0 rounded-md border-stone-300 text-orange-600 shadow-sm focus:ring-2 focus:ring-orange-400/30 dark:border-stone-500 dark:bg-stone-900 dark:text-orange-500 dark:focus:ring-orange-500/30"
                                        >
                                        <span class="min-w-0 leading-snug">{{ $subdivision->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p id="bc-subdivision-empty" class="hidden text-sm text-stone-500 dark:text-stone-400 mt-2">Ничего не найдено.</p>
                        </div>
                        <x-input-error :messages="$errors->get('subdivision_ids')" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('subdivision_ids.*')" class="mt-1.5" />
                    </section>

                    <div class="app-form-actions-mobile">
                        <a href="{{ route('boiler-chief-subdivisions.assignments') }}" class="min-h-11 content-center text-center text-sm font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-200 sm:text-left">
                            Отмена и к списку начальников
                        </a>
                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--lg w-full text-base sm:w-auto">
                            Сохранить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var searchInput = document.getElementById('bc-subdivision-search');
            var options = Array.prototype.slice.call(document.querySelectorAll('.bc-subdivision-option'));
            var emptyMessage = document.getElementById('bc-subdivision-empty');

            if (!searchInput || options.length === 0 || !emptyMessage) {
                return;
            }

            function normalize(value) {
                return (value || '').toString().trim().toLowerCase();
            }

            function applyFilter() {
                var query = normalize(searchInput.value);
                var visibleCount = 0;

                options.forEach(function (option) {
                    var name = option.getAttribute('data-name') || '';
                    var isVisible = query === '' || name.indexOf(query) !== -1;
                    option.style.display = isVisible ? '' : 'none';
                    if (isVisible) {
                        visibleCount++;
                    }
                });

                emptyMessage.classList.toggle('hidden', visibleCount > 0);
            }

            searchInput.addEventListener('input', applyFilter);
            applyFilter();
        })();
    </script>
</x-app-layout>
