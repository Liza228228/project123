<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            @if($canManage ?? false)
                <x-page-header-nav :href="route('foreman-subdivisions.assignments')">Назначения мастерам</x-page-header-nav>
            @endif
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                {{ ($archived ?? false) ? 'Архив подразделений' : 'Подразделения и склады' }}
            </h2>
            <div class="flex flex-wrap gap-2 text-sm">
                @if($archived ?? false)
                    <a href="{{ route('foreman-subdivisions.index') }}" class="ui-btn ui-btn--secondary ui-btn--sm">Активные подразделения</a>
                @else
                    <a href="{{ route('foreman-subdivisions.archive') }}" class="ui-btn ui-btn--secondary ui-btn--sm">Архив подразделений</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if($canManage ?? false)
                <div class="grid gap-4 lg:grid-cols-2">
                    <section class="app-form-card p-4 sm:p-5 space-y-4">
                        <h3 class="app-section-title">Добавить подразделение</h3>
                        <form method="POST" action="{{ route('foreman-subdivisions.subdivisions.store') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="subdivision_name" class="app-form-label">Название</label>
                                <input
                                    id="subdivision_name"
                                    type="text"
                                    name="subdivision_name"
                                    value="{{ old('subdivision_name') }}"
                                    placeholder="Например: Игирма КР т/с СЭЛ"
                                    class="app-input"
                                />
                                <x-input-error :messages="$errors->get('subdivision_name')" class="mt-1.5" />
                            </div>
                            <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto">
                                Добавить подразделение
                            </button>
                        </form>
                    </section>

                    <section class="app-form-card p-4 sm:p-5 space-y-4">
                        <h3 class="app-section-title">Добавить склад</h3>
                        <form method="POST"
                              action="{{ route('foreman-subdivisions.warehouses.store') }}"
                              class="space-y-4"
                              id="warehouse-create-form"
                              data-dadata-suggest-url="{{ route('api.dadata.address.suggest', [], false) }}"
                              data-dadata-clean-url="{{ route('api.dadata.address.clean', [], false) }}">
                            @csrf
                            <div>
                                <label for="warehouse_subdivision_search" class="app-form-label">Подразделение</label>
                                <div class="filterable-select-combo">
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                        </span>
                                        <input
                                            id="warehouse_subdivision_search"
                                            type="search"
                                            class="app-input app-input--with-icon"
                                            placeholder="Начните вводить название…"
                                            autocomplete="off"
                                            aria-controls="warehouse_subdivision_id"
                                            aria-autocomplete="list"
                                        />
                                    </div>
                                    <select
                                        id="warehouse_subdivision_id"
                                        name="subdivision_id"
                                        class="app-select filterable-select-target"
                                        aria-label="Выбор подразделения из списка"
                                    >
                                        <option value="">Выберите подразделение…</option>
                                        @foreach(($subdivisionOptions ?? collect()) as $subdivision)
                                            <option value="{{ $subdivision->id }}" @selected((string) old('subdivision_id') === (string) $subdivision->id)>
                                                {{ $subdivision->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-input-error :messages="$errors->get('subdivision_id')" class="mt-1.5" />
                            </div>
                            <div>
                                <label for="warehouse_name" class="app-form-label">Название склада</label>
                                <input
                                    id="warehouse_name"
                                    type="text"
                                    name="warehouse_name"
                                    value="{{ old('warehouse_name') }}"
                                    placeholder="Например: Склад №1"
                                    class="app-input"
                                />
                                <x-input-error :messages="$errors->get('warehouse_name')" class="mt-1.5" />
                            </div>
                            <div>
                                <label for="warehouse_address" class="app-form-label">Адрес склада</label>
                                <div class="relative" data-dadata-address-field>
                                    <input
                                        id="warehouse_address"
                                        type="text"
                                        name="address"
                                        value="{{ old('address') }}"
                                        placeholder=""
                                        autocomplete="off"
                                        class="app-input"
                                        data-dadata-address-input
                                    />
                                    <div class="absolute z-30 mt-1 hidden max-h-56 w-full overflow-y-auto rounded-xl border border-stone-200 bg-white shadow-lg dark:border-stone-700 dark:bg-stone-900"
                                         data-dadata-suggestions role="listbox"></div>
                                </div>
                                <x-input-error :messages="$errors->get('address')" class="mt-1.5" />
                            </div>
                            <div>
                                <label for="warehouse_comment" class="app-form-label">Комментарий</label>
                                <textarea
                                    id="warehouse_comment"
                                    name="comment"
                                    rows="2"
                                    placeholder="Необязательно"
                                    class="app-input min-h-[5rem] py-2.5"
                                >{{ old('comment') }}</textarea>
                                <x-input-error :messages="$errors->get('comment')" class="mt-1.5" />
                            </div>
                            <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto">
                                Добавить склад
                            </button>
                        </form>
                    </section>
                </div>
            @endif

            <section class="app-form-card overflow-hidden">
                <div class="app-filter-panel border-b border-orange-200/75 dark:border-orange-900/45">
                    <h3 class="app-section-title mb-4">Справочник</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                        <div class="sm:col-span-2">
                            <label for="subdivision-search" class="app-form-label">Поиск в таблице</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </span>
                                <input
                                    id="subdivision-search"
                                    type="search"
                                    placeholder="Подразделение, склад или адрес…"
                                    autocomplete="off"
                                    class="app-input app-input--with-icon"
                                />
                            </div>
                        </div>
                        <div>
                            <label for="warehouse-filter" class="app-form-label">Склады</label>
                            <select id="warehouse-filter" class="app-select">
                                <option value="all">Все подразделения</option>
                                <option value="with">Только со складами</option>
                                <option value="without">Только без складов</option>
                            </select>
                        </div>
                        <form method="GET" action="{{ route('foreman-subdivisions.index') }}" data-auto-submit="filter">
                            <label for="subdivisions-per-page" class="app-form-label">На странице</label>
                            <div class="flex items-center gap-2">
                                <select id="subdivisions-per-page" name="per_page" class="app-select min-w-0 flex-1">
                                    @foreach(($allowedPerPage ?? [10, 15, 20, 30, 50]) as $size)
                                        <option value="{{ $size }}" @selected(($perPage ?? ($defaultPerPage ?? 10)) === $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                                @if(($perPage ?? ($defaultPerPage ?? 10)) !== ($defaultPerPage ?? 10))
                                    <a href="{{ route('foreman-subdivisions.index') }}" class="ui-btn ui-btn--secondary ui-btn--sm shrink-0">
                                        Сбросить
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                <div class="p-4 sm:p-6 space-y-6">

                    @if(session('status'))
                        <x-app-alert type="success">{{ session('status') }}</x-app-alert>
                    @endif

                    @if($errors->any())
                        <x-app-alert type="error">
                            @foreach($errors->all() as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </x-app-alert>
                    @endif

                    <div id="subdivision-filter-empty" class="hidden mb-4 rounded-lg border border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-900/20 px-4 py-3 text-sm text-black dark:text-white">
                        По выбранным фильтрам ничего не найдено.
                    </div>

                    @if(($canViewAdministration ?? false) && ($administrationSubdivision ?? null))
                        <div class="rounded-xl border border-orange-300/90 bg-orange-50/70 p-4 dark:border-orange-800/60 dark:bg-orange-950/30">
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
                                        @include('foreman-subdivisions.partials.warehouse-list', [
                                            'warehouses' => $administrationSubdivision->warehouses,
                                            'subdivisionInactive' => false,
                                        ])
                                    @endif
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-black/75 dark:text-white/75">
                                Просмотр — у директора, технического директора и начальника отдела снабжения.
                                @if($canManage ?? false)
                                    Дополнительные склады добавляются формой выше.
                                @else
                                    Добавление складов — у директора, начальника отдела снабжения или администратора.
                                @endif
                            </p>
                        </div>
                    @endif

                    <div class="md:hidden space-y-4" id="subdivision-cards-mobile">
                        @forelse($subdivisions as $subdivision)
                            @php
                                $warehouseSearchBlob = $subdivision->warehouses
                                    ->map(fn ($warehouse) => mb_strtolower(trim($warehouse->name.' '.$warehouse->formatted_address)))
                                    ->implode(' ');
                                $subdivisionDeactivateDetail = [
                                    'id' => $subdivision->id,
                                    'name' => $subdivision->name,
                                    'previewUrl' => route('foreman-subdivisions.subdivisions.deactivate-preview', $subdivision),
                                    'deactivateUrl' => route('foreman-subdivisions.subdivisions.deactivate', $subdivision),
                                ];
                            @endphp
                            <article
                                class="subdivision-row app-equipment-card space-y-3 {{ $subdivision->isArchived() ? 'opacity-75' : '' }}"
                                data-subdivision-name="{{ mb_strtolower($subdivision->name) }}"
                                data-warehouse-blob="{{ $warehouseSearchBlob }}"
                                data-has-warehouses="{{ $subdivision->warehouses->isEmpty() ? '0' : '1' }}"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="subdivisions-directory-subdivision min-w-0 flex-1">{{ $subdivision->name }}</h4>
                                    <span class="inline-flex shrink-0 items-center rounded-full bg-orange-100/90 px-2.5 py-0.5 text-[11px] font-semibold tabular-nums text-orange-950 dark:bg-orange-950/50 dark:text-orange-100">
                                        {{ $subdivision->warehouses->count() }} {{ $subdivision->warehouses->count() === 1 ? 'склад' : 'складов' }}
                                    </span>
                                    @if($subdivision->isArchived())
                                        <span class="inline-flex shrink-0 items-center rounded-full bg-stone-200/90 px-2.5 py-0.5 text-[11px] font-semibold text-stone-700 dark:bg-stone-700/60 dark:text-stone-200">
                                            Недоступно
                                        </span>
                                    @endif
                                    @if(($canDeleteInfrastructure ?? false) && $subdivision->isActive())
                                        <button
                                            type="button"
                                            class="ui-btn ui-btn--danger ui-btn--sm shrink-0"
                                            data-subdivision-deactivate-trigger
                                            data-subdivision-deactivate-payload='@json($subdivisionDeactivateDetail)'
                                        >
                                            Сделать недоступным
                                        </button>
                                    @endif
                                </div>
                                @include('foreman-subdivisions.partials.warehouse-list', [
                                    'warehouses' => $subdivision->warehouses,
                                    'subdivisionInactive' => $subdivision->isArchived(),
                                ])
                            </article>
                        @empty
                            <p class="text-center text-sm text-stone-600 dark:text-stone-400 py-6">Подразделения не найдены.</p>
                        @endforelse
                    </div>

                    <div class="hidden md:block app-table-shell subdivisions-directory-table-shell">
                        <table class="min-w-full subdivisions-directory-table">
                            <thead>
                                <tr>
                                    <th class="w-[34%] px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400">Подразделение</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400">Склады</th>
                                </tr>
                            </thead>
                            <tbody id="subdivision-table-body">
                                @forelse($subdivisions as $subdivision)
                                    @php
                                        $warehouseSearchBlob = $subdivision->warehouses
                                            ->map(fn ($warehouse) => mb_strtolower(trim($warehouse->name.' '.$warehouse->formatted_address)))
                                            ->implode(' ');
                                        $subdivisionDeactivateDetail = [
                                            'id' => $subdivision->id,
                                            'name' => $subdivision->name,
                                            'previewUrl' => route('foreman-subdivisions.subdivisions.deactivate-preview', $subdivision),
                                            'deactivateUrl' => route('foreman-subdivisions.subdivisions.deactivate', $subdivision),
                                        ];
                                    @endphp
                                    <tr
                                        class="subdivision-row align-top {{ $subdivision->isArchived() ? 'opacity-75' : '' }}"
                                        data-subdivision-name="{{ mb_strtolower($subdivision->name) }}"
                                        data-warehouse-blob="{{ $warehouseSearchBlob }}"
                                        data-has-warehouses="{{ $subdivision->warehouses->isEmpty() ? '0' : '1' }}"
                                    >
                                        <td class="px-4 py-4 align-top">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <div class="subdivisions-directory-subdivision">{{ $subdivision->name }}</div>
                                                    <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                                                        {{ $subdivision->warehouses->count() }} {{ $subdivision->warehouses->count() === 1 ? 'склад' : 'складов' }}
                                                        @if($subdivision->isArchived())
                                                            · <span class="font-medium text-stone-600 dark:text-stone-300">недоступно</span>
                                                        @endif
                                                    </p>
                                                </div>
                                                @if(($canDeleteInfrastructure ?? false) && $subdivision->isActive())
                                                    <button
                                                        type="button"
                                                        class="ui-btn ui-btn--danger ui-btn--sm shrink-0"
                                                        data-subdivision-deactivate-trigger
                                                        data-subdivision-deactivate-payload='@json($subdivisionDeactivateDetail)'
                                                    >
                                                        Сделать недоступным
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 align-top min-w-0">
                                        @include('foreman-subdivisions.partials.warehouse-list', [
                                            'warehouses' => $subdivision->warehouses,
                                            'subdivisionInactive' => $subdivision->isArchived(),
                                        ])
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-4 py-8 text-center text-sm text-stone-600 dark:text-stone-400">Подразделения не найдены.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($subdivisions->hasPages())
                        <div>
                            {{ $subdivisions->links() }}
                        </div>
                    @endif
                </div>
            </section>
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
                var skipBlurClean = false;

                function closeSuggestions() {
                    if (!suggestionsBox) return;
                    suggestionsBox.innerHTML = '';
                    suggestionsBox.classList.add('hidden');
                }

                function dadataPostalCode(item) {
                    if (!item) {
                        return '';
                    }
                    if (item.postal_code) {
                        return String(item.postal_code).trim();
                    }
                    if (item.data && item.data.postal_code) {
                        return String(item.data.postal_code).trim();
                    }

                    return '';
                }

                function formatAddressInputValue(item) {
                    var value = item && item.value ? String(item.value).trim() : '';
                    if (value === '') {
                        return '';
                    }
                    var postal = dadataPostalCode(item);
                    if (postal !== '') {
                        return postal + ' ' + value;
                    }

                    return value;
                }

                function selectSuggestion(item) {
                    if (!input) {
                        return;
                    }
                    skipBlurClean = true;
                    input.value = formatAddressInputValue(item);
                    closeSuggestions();
                    input.focus();
                }

                function appendDadataSuggestionButton(container, item, onSelect) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'block w-full px-3 py-2 text-left text-sm text-stone-800 hover:bg-orange-50 dark:text-stone-100 dark:hover:bg-stone-800';
                    var value = item && item.value ? item.value : '';
                    var postal = dadataPostalCode(item);
                    if (postal) {
                        var wrap = document.createElement('div');
                        var line = document.createElement('span');
                        line.className = 'block leading-snug';
                        line.textContent = value;
                        var postalLine = document.createElement('span');
                        postalLine.className = 'block text-xs text-stone-500 dark:text-stone-400 mt-0.5 tabular-nums';
                        postalLine.textContent = 'Индекс ' + postal;
                        wrap.appendChild(line);
                        wrap.appendChild(postalLine);
                        button.appendChild(wrap);
                    } else {
                        button.textContent = value;
                    }
                    button.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                    });
                    button.addEventListener('click', function () {
                        onSelect(item || {});
                    });
                    container.appendChild(button);
                }

                function renderSuggestions(items) {
                    if (!suggestionsBox) return;
                    suggestionsBox.innerHTML = '';
                    if (!Array.isArray(items) || items.length === 0) {
                        closeSuggestions();
                        return;
                    }
                    items.forEach(function (item) {
                        appendDadataSuggestionButton(suggestionsBox, item, selectSuggestion);
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
                            var line = typeof result.result === 'string' ? result.result.trim() : '';
                            var postal = result.postal_code ? String(result.postal_code).trim() : '';
                            if (line !== '') {
                                input.value = postal !== '' ? (postal + ' ' + line) : line;
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
                            if (skipBlurClean) {
                                skipBlurClean = false;
                                return;
                            }
                        }, 120);
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

            if (!searchInput || !filterSelect || !emptyBox) {
                return;
            }

            if (rows.length === 0) {
                emptyBox.classList.remove('hidden');
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

    @include('foreman-subdivisions.partials.deactivate-subdivision-modal', [
        'canDeleteInfrastructure' => $canDeleteInfrastructure ?? false,
    ])

    @if($canManage ?? false)
        @include('partials.js-filterable-select', [
            'searchInputId' => 'warehouse_subdivision_search',
            'selectInputId' => 'warehouse_subdivision_id',
            'expandOnSearch' => true,
            'comboMode' => true,
        ])
    @endif
</x-app-layout>
