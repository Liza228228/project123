<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center gap-x-4 gap-y-2 w-full min-w-0">
            <a href="{{ route('foreman-subdivisions.assignments') }}" class="shrink-0 text-sm text-black dark:text-white hover:text-black dark:hover:text-white transition-colors whitespace-nowrap">
                ← Назад к мастерам
            </a>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Назначить подразделения
            </h2>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-4 sm:p-6">
                    <div class="mb-4">
                        <p class="text-sm text-black dark:text-white opacity-80">Мастер участка</p>
                        <p class="text-base font-semibold text-black dark:text-white">
                            {{ $foreman->surname }} {{ $foreman->name }} {{ $foreman->patronymic }}
                        </p>
                        <p class="text-sm text-black dark:text-white opacity-80">{{ $foreman->email }}</p>
                    </div>

                    <form method="POST" action="{{ route('foreman-subdivisions.update', $foreman) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <div class="mb-3">
                                <p class="text-xs text-black dark:text-white opacity-80 mb-1">Сейчас назначено</p>
                                @if($foreman->assignedSubdivisions->isEmpty())
                                    <p class="text-sm text-black dark:text-white opacity-70">Подразделения пока не назначены.</p>
                                @else
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($foreman->assignedSubdivisions as $assigned)
                                            <span class="inline-flex items-center rounded-full bg-orange-100 dark:bg-orange-900/40 px-2.5 py-0.5 text-xs text-black dark:text-white">
                                                {{ $assigned->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <x-input-label value="Список подразделений" />
                            <p class="text-xs text-black dark:text-white opacity-80 mt-1 mb-3">Отметьте подразделения и нажмите «Назначить».</p>
                            <div class="mb-3">
                                <input
                                    id="subdivision-search"
                                    type="text"
                                    placeholder="Поиск по подразделениям..."
                                    class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                />
                            </div>
                            <div class="max-h-80 overflow-y-auto rounded-lg border border-orange-200 dark:border-orange-800 p-3">
                                <div id="subdivision-list" class="grid gap-2 sm:grid-cols-2">
                                    @foreach($subdivisions as $subdivision)
                                        <label class="subdivision-option inline-flex items-center gap-2 text-sm text-black dark:text-white" data-name="{{ mb_strtolower($subdivision->name) }}">
                                            <input
                                                type="checkbox"
                                                name="subdivision_ids[]"
                                                value="{{ $subdivision->id }}"
                                                @checked($foreman->assignedSubdivisions->contains('id', $subdivision->id))
                                                class="rounded border-orange-300 text-orange-600 shadow-sm focus:ring-orange-500"
                                            >
                                            <span>{{ $subdivision->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p id="subdivision-empty" class="hidden text-sm text-black dark:text-white opacity-70 mt-1">Ничего не найдено.</p>
                            </div>
                            <x-input-error :messages="$errors->get('subdivision_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('subdivision_ids.*')" class="mt-2" />
                        </div>

                        <div class="pt-2 border-t border-orange-200 dark:border-orange-800">
                            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                                Назначить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var searchInput = document.getElementById('subdivision-search');
            var options = Array.prototype.slice.call(document.querySelectorAll('.subdivision-option'));
            var emptyMessage = document.getElementById('subdivision-empty');

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
