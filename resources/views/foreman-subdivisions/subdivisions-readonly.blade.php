<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Подразделения и склады
            </h2>
            @if($canManage ?? false)
                <a href="{{ route('foreman-subdivisions.assignments') }}" class="ui-btn ui-btn--primary whitespace-nowrap shrink-0 w-full sm:w-auto">
                    Назначения мастерам
                </a>
            @endif
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

                            <form method="POST" action="{{ route('foreman-subdivisions.warehouses.store') }}" class="rounded-lg border border-stone-200 dark:border-stone-800 bg-stone-50/50 dark:bg-stone-900/20 p-3 space-y-2">
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
                                    <input
                                        type="text"
                                        name="code"
                                        value="{{ old('code') }}"
                                        placeholder="Код склада"
                                        class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                    />
                                    <label class="inline-flex items-center gap-2 text-sm text-black dark:text-white">
                                        <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary') === '1') class="rounded border-stone-300 text-stone-600 shadow-sm focus:ring-stone-500">
                                        Основной склад
                                    </label>
                                </div>
                                <textarea
                                    name="comment"
                                    rows="2"
                                    placeholder="Комментарий (необязательно)"
                                    class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                >{{ old('comment') }}</textarea>
                                <x-input-error :messages="$errors->get('subdivision_id')" />
                                <x-input-error :messages="$errors->get('warehouse_name')" />
                                <x-input-error :messages="$errors->get('code')" />
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
                                    placeholder="Подразделение, склад или код склада..."
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
                        </div>
                        <p class="mt-2 text-xs text-black dark:text-white opacity-80">
                            Можно искать по названию подразделения, коду склада и названию склада.
                        </p>
                    </div>

                    <div id="subdivision-filter-empty" class="hidden mb-4 rounded-lg border border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-900/20 px-4 py-3 text-sm text-black dark:text-white">
                        По выбранным фильтрам ничего не найдено.
                    </div>

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
                                            ->map(fn ($warehouse) => mb_strtolower(trim(($warehouse->code ?? '').' '.$warehouse->name)))
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
                                                            <span class="font-mono text-xs opacity-80">{{ $warehouse->code }}</span>
                                                            <span class="opacity-70">—</span>
                                                            {{ $warehouse->name }}
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
