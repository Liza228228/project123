<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            @if($canManage ?? false)
                <x-page-header-nav :href="route('foreman-subdivisions.assignments')">Назначения мастерам</x-page-header-nav>
            @endif
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Подразделения и склады
            </h2>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-stone-100 dark:bg-stone-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif
            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-6">
                    @if($canManage ?? false)
                        <div class="mb-5 grid gap-4 lg:grid-cols-2">
                            <form method="POST" action="{{ route('foreman-subdivisions.subdivisions.store') }}" class="rounded-lg border border-stone-200 dark:border-stone-800 bg-stone-50/50 dark:bg-stone-900/20 p-3 space-y-2">
                                @csrf
                                <h3 class="text-sm font-semibold text-black dark:text-white">Добавить подразделение</h3>
                                <input
                                    type="text"
                                    name="subdivision_name"
                                    value="{{ old('subdivision_name') }}"
                                    placeholder="Название подразделения"
                                    class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                />
                                <x-input-error :messages="$errors->get('subdivision_name')" />
                                <button type="submit" class="ui-btn ui-btn--primary">
                                    Добавить подразделение
                                </button>
                            </form>

                            <form method="POST"
                                  action="{{ route('foreman-subdivisions.warehouses.store') }}"
                                  class="rounded-lg border border-stone-200 dark:border-stone-800 bg-stone-50/50 dark:bg-stone-900/20 p-3 space-y-2"
                                  id="warehouse-create-form"
                                  data-dadata-suggest-url="{{ route('api.dadata.address.suggest', [], false) }}"
                                  data-dadata-clean-url="{{ route('api.dadata.address.clean', [], false) }}">
                                @csrf
                                <h3 class="text-sm font-semibold text-black dark:text-white">Добавить склад</h3>
                              
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <select
                                        name="subdivision_id"
                                        class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                    >
                                        <option value="">Подразделение</option>
                                        @foreach(($subdivisionOptions ?? collect()) as $subdivision)
                                            <option value="{{ $subdivision->id }}" @selected((string) old('subdivision_id') === (string) $subdivision->id)>
                                                {{ $subdivision->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input
                                        type="text"
                                        name="warehouse_name"
                                        value="{{ old('warehouse_name') }}"
                                        placeholder="Название склада"
                                        class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                    />
                                </div>
                                <div class="relative" data-dadata-address-field>
                                    <input
                                        type="text"
                                        name="address"
                                        value="{{ old('address') }}"
                                        placeholder="Адрес склада"
                                        autocomplete="off"
                                        class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                        data-dadata-address-input
                                    />
                                    <div class="absolute z-30 mt-1 hidden max-h-56 w-full overflow-y-auto rounded-lg border border-stone-200 bg-white shadow-lg dark:border-stone-700 dark:bg-stone-900"
                                         data-dadata-suggestions></div>
                                </div>
                                <textarea
                                    name="comment"
                                    rows="2"
                                    placeholder="Комментарий (необязательно)"
                                    class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                >{{ old('comment') }}</textarea>
                                <x-input-error :messages="$errors->get('subdivision_id')" />
                                <x-input-error :messages="$errors->get('warehouse_name')" />
                                <x-input-error :messages="$errors->get('address')" />
                                <x-input-error :messages="$errors->get('comment')" />
                                <button type="submit" class="ui-btn ui-btn--primary">
                                    Добавить склад
                                </button>
                            </form>
                        </div>
                    @endif

                    <div class="mb-4 rounded-lg border border-stone-200 dark:border-stone-800 bg-stone-50/60 dark:bg-stone-900/20 p-3">
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-[220px] flex-1">
                                <label for="subdivision-search" class="block text-xs font-medium text-black dark:text-white mb-1">Поиск</label>
                                <input
                                    id="subdivision-search"
                                    type="text"
                                    placeholder="Подразделение или склад..."
                                    class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                />
                            </div>
                            <div class="w-full sm:w-auto">
                                <label for="warehouse-filter" class="block text-xs font-medium text-black dark:text-white mb-1">Фильтр</label>
                                <select
                                    id="warehouse-filter"
                                    class="block w-full sm:w-56 rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                >
                                    <option value="all">Все подразделения</option>
                                    <option value="with">Только со складами</option>
                                    <option value="without">Только без складов</option>
                                </select>
                            </div>
                            <form method="GET" action="{{ route('foreman-subdivisions.index') }}" class="w-full sm:w-auto sm:ml-auto" data-auto-submit="filter">
                                <label for="subdivisions-per-page" class="block text-xs font-medium text-black dark:text-white mb-1">На странице</label>
                                <div class="flex items-center gap-2">
                                    <select
                                        id="subdivisions-per-page"
                                        name="per_page"
                                        class="block w-full sm:w-32 rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                    >
                                        @foreach(($allowedPerPage ?? [10, 15, 20, 30, 50]) as $size)
                                            <option value="{{ $size }}" @selected(($perPage ?? ($defaultPerPage ?? 10)) === $size)>{{ $size }}</option>
                                        @endforeach
                                    </select>
                                    @if(($perPage ?? ($defaultPerPage ?? 10)) !== ($defaultPerPage ?? 10))
                                        <a
                                            href="{{ route('foreman-subdivisions.index') }}"
                                            class="ui-btn ui-btn--secondary ui-btn--sm whitespace-nowrap"
                                        >
                                            Сбросить
                                        </a>
                                    @endif
                                </div>
                            </form>
                        </div>

                    </div>

                    <div id="subdivision-filter-empty" class="hidden mb-4 rounded-lg border border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-900/20 px-4 py-3 text-sm text-black dark:text-white">
                        По выбранным фильтрам ничего не найдено.
                    </div>

                    @if(($canViewAdministration ?? false) && ($administrationSubdivision ?? null))
                        <div class="mb-6 rounded-xl border border-orange-300/90 bg-orange-50/70 p-4 dark:border-orange-800/60 dark:bg-orange-950/30">
                            <p class="text-xs font-semibold uppercase tracking-wide text-orange-900/90 dark:text-orange-100/90 mb-3">
                                Главный склад
                            </p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs text-black/70 dark:text-white/70 mb-0.5">Подразделение</p>
                                    <p class="text-sm font-semibold text-black dark:text-white">{{ $administrationSubdivision->name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-black/70 dark:text-white/70 mb-0.5">Склады</p>
                                    @if($administrationSubdivision->warehouses->isEmpty())
                                        <p class="text-sm text-black dark:text-white">Складов нет</p>
                                    @else
                                        <ul class="space-y-1">
                                            @foreach($administrationSubdivision->warehouses as $warehouse)
                                                <li class="text-sm text-black dark:text-white rounded-md bg-white/80 dark:bg-stone-950/50 px-2 py-1.5">
                                                    <span class="font-medium">{{ $warehouse->name }}</span>
                                                    @if($warehouse->is_primary)
                                                        <span class="ms-2 inline-flex items-center rounded-full bg-orange-200/90 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-950 dark:bg-orange-900/50 dark:text-orange-100">
                                                            Основной
                                                        </span>
                                                    @endif
                                                    @if($warehouse->formatted_address !== '')
                                                        <div class="mt-1 text-xs opacity-75">{{ $warehouse->formatted_address }}</div>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-black/75 dark:text-white/75">
                                Доступ к складам этого подразделения — только у директора, технического директора и начальника отдела снабжения. Дополнительные склады добавляются формой выше.
                            </p>
                        </div>
                    @endif

                    <div class="app-table-shell">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Подразделение</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase"> Склады</th>
                                </tr>
                            </thead>
                            <tbody id="subdivision-table-body">
                                @forelse($subdivisions as $subdivision)
                                    @php
                                        $warehouseSearchBlob = $subdivision->warehouses
                                            ->map(fn ($warehouse) => mb_strtolower(trim($warehouse->name.' '.$warehouse->formatted_address)))
                                            ->implode(' ');
                                    @endphp
                                    <tr
                                        class="subdivision-row"
                                        data-subdivision-name="{{ mb_strtolower($subdivision->name) }}"
                                        data-warehouse-blob="{{ $warehouseSearchBlob }}"
                                        data-has-warehouses="{{ $subdivision->warehouses->isEmpty() ? '0' : '1' }}"
                                    >
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            <div class="font-medium">{{ $subdivision->name }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            @if($subdivision->warehouses->isEmpty())
                                                <span class="inline-flex items-center rounded-full bg-stone-100 dark:bg-stone-900/35 px-2.5 py-0.5 text-xs text-black dark:text-white">
                                                    Складов нет
                                                </span>
                                            @else
                                                <ul class="space-y-1">
                                                    @foreach($subdivision->warehouses as $warehouse)
                                                        <li class="text-sm text-black dark:text-white rounded-md bg-stone-50 dark:bg-stone-900/20 px-2 py-1">
                                                            {{ $warehouse->name }}
                                                            @if($warehouse->formatted_address !== '')
                                                                <div class="mt-1 text-xs opacity-75">{{ $warehouse->formatted_address }}</div>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-4 py-6 text-center text-sm text-black dark:text-white">Подразделения не найдены.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($subdivisions->hasPages())
                        <div class="mt-4">
                            {{ $subdivisions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var warehouseForm = document.getElementById('warehouse-create-form');
            if (warehouseForm) {
                var suggestUrl = warehouseForm.getAttribute('data-dadata-suggest-url') || '';
                var cleanUrl = warehouseForm.getAttribute('data-dadata-clean-url') || '';
                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
                var field = warehouseForm.querySelector('[data-dadata-address-field]');
                var input = field ? field.querySelector('[data-dadata-address-input]') : null;
                var suggestionsBox = field ? field.querySelector('[data-dadata-suggestions]') : null;
                var timerId = null;

                function closeSuggestions() {
                    if (!suggestionsBox) return;
                    suggestionsBox.innerHTML = '';
                    suggestionsBox.classList.add('hidden');
                }

                function selectSuggestion(item) {
                    if (!input) return;
                    input.value = item && item.value ? item.value : input.value;
                    closeSuggestions();
                }

                function renderSuggestions(items) {
                    if (!suggestionsBox) return;
                    suggestionsBox.innerHTML = '';
                    if (!Array.isArray(items) || items.length === 0) {
                        closeSuggestions();
                        return;
                    }
                    items.forEach(function (item) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'block w-full px-3 py-2 text-left text-sm text-stone-800 hover:bg-orange-50 dark:text-stone-100 dark:hover:bg-stone-800';
                        button.textContent = item && item.value ? item.value : '';
                        button.addEventListener('click', function () {
                            selectSuggestion(item || {});
                        });
                        suggestionsBox.appendChild(button);
                    });
                    suggestionsBox.classList.remove('hidden');
                }

                async function fetchSuggestions(query) {
                    if (!query || query.length < 3 || !suggestUrl) {
                        closeSuggestions();
                        return;
                    }
                    try {
                        var url = new URL(suggestUrl, window.location.origin);
                        url.searchParams.set('query', query);
                        url.searchParams.set('count', '7');
                        var res = await fetch(url.toString(), {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        });
                        if (!res.ok) {
                            closeSuggestions();
                            return;
                        }
                        var data = await res.json();
                        renderSuggestions(data && data.suggestions ? data.suggestions : []);
                    } catch (_) {
                        closeSuggestions();
                    }
                }

                async function cleanAddress() {
                    if (!input || !cleanUrl) return;
                    var value = (input.value || '').toString().trim();
                    if (value.length < 3) return;
                    try {
                        var res = await fetch(cleanUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({ address: value }),
                            credentials: 'same-origin'
                        });
                        if (!res.ok) return;
                        var data = await res.json();
                        var result = data && data.result ? data.result : null;
                        if (result && typeof result === 'object') {
                            if (typeof result.result === 'string' && result.result.trim() !== '') {
                                input.value = result.result;
                            }
                        }
                    } catch (_) {
                        // ignore network issues and keep manual input
                    }
                }

                if (input && suggestionsBox) {
                    input.addEventListener('input', function () {
                        if (timerId) {
                            clearTimeout(timerId);
                        }
                        timerId = setTimeout(function () {
                            fetchSuggestions((input.value || '').toString().trim());
                        }, 260);
                    });

                    input.addEventListener('blur', function () {
                        setTimeout(function () {
                            if (!suggestionsBox.matches(':hover')) {
                                closeSuggestions();
                            }
                        }, 120);
                        cleanAddress();
                    });

                    document.addEventListener('click', function (event) {
                        if (!event.target.closest('[data-dadata-address-field]')) {
                            closeSuggestions();
                        }
                    });
                }

                warehouseForm.addEventListener('submit', async function () {
                    await cleanAddress();
                });
            }

            var searchInput = document.getElementById('subdivision-search');
            var filterSelect = document.getElementById('warehouse-filter');
            var rows = Array.prototype.slice.call(document.querySelectorAll('.subdivision-row'));
            var emptyBox = document.getElementById('subdivision-filter-empty');

            if (!searchInput || !filterSelect || rows.length === 0 || !emptyBox) {
                return;
            }

            function normalize(value) {
                return (value || '').toString().trim().toLowerCase();
            }

            function applyFilters() {
                var query = normalize(searchInput.value);
                var mode = filterSelect.value || 'all';
                var visibleCount = 0;

                rows.forEach(function (row) {
                    var subName = normalize(row.getAttribute('data-subdivision-name'));
                    var whBlob = normalize(row.getAttribute('data-warehouse-blob'));
                    var hasWarehouses = row.getAttribute('data-has-warehouses') === '1';
                    var queryOk = query === '' || subName.indexOf(query) !== -1 || whBlob.indexOf(query) !== -1;
                    var modeOk = mode === 'all'
                        || (mode === 'with' && hasWarehouses)
                        || (mode === 'without' && !hasWarehouses);
                    var show = queryOk && modeOk;
                    row.classList.toggle('hidden', !show);
                    if (show) {
                        visibleCount++;
                    }
                });

                emptyBox.classList.toggle('hidden', visibleCount > 0);
            }

            searchInput.addEventListener('input', applyFilters);
            filterSelect.addEventListener('change', applyFilters);
            applyFilters();
        })();
    </script>
</x-app-layout>
