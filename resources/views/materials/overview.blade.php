<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
            Остатки по складам
        </h2>
    </x-slot>

    @php
        $usingDefaultMainWarehouse = (bool) ($usingDefaultMainWarehouse ?? false);
        $hasFilters = ! $usingDefaultMainWarehouse
            && ((int) ($selectedSubdivision?->id ?? 0) > 0 || (int) ($selectedWarehouse?->id ?? 0) > 0);
        $overviewTabQuery = $overviewTabQuery ?? [];
    @endphp

    <div class="mx-auto max-w-[96rem] px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <a
                href="{{ route('materials.movements', array_filter(['warehouse_id' => $selectedWarehouse?->id])) }}"
                class="ui-btn ui-btn--secondary"
            >
                Посмотреть журнал операций
            </a>
        </div>
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-5 sm:space-y-6">
                <div class="grid grid-cols-1 gap-5 sm:gap-6 lg:grid-cols-12 lg:gap-8 lg:items-start">
                    {{-- Слева на lg: просмотр склада (узкая колонка). --}}
                    <div class="min-w-0 w-full max-w-xl space-y-4 lg:col-span-4 lg:max-w-none">
                        <p class="text-sm text-black/75 dark:text-white/75">
                            @if($selectedWarehouse)
                                Подразделение и склад можно изменить слева. Справа — остатки по выбранному складу.
                            @else
                                Выберите подразделение и склад слева — справа появятся приход, списание и остаток по позициям оборудования на выбранном складе.
                            @endif
                        </p>

                        <div class="space-y-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                                <h3 class="text-base font-semibold text-black dark:text-white">Просмотр склада</h3>
                                @if($hasFilters)
                                    <a
                                        href="{{ route('materials.overview') }}"
                                        class="text-sm font-medium text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white shrink-0"
                                    >
                                        Сбросить выбор
                                    </a>
                                @endif
                            </div>

                          

                            @if($subdivisions->isEmpty())
                                <p class="text-sm text-black/70 dark:text-white/70">Подразделения для вашей учётной записи не найдены.</p>
                            @else
                            <div class="app-filter-panel">
                                <form
                                    id="materials-overview-filters"
                                    method="get"
                                    action="{{ route('materials.overview') }}"
                                    class="grid grid-cols-1 gap-4"
                                    data-auto-submit="filter"
                                >
                                    <div class="min-w-0">
                                        <label for="overview_subdivision_id" class="app-form-label">Подразделение</label>
                                        <input
                                            id="overview_subdivision_search"
                                            type="search"
                                            class="app-input mb-2 min-h-0 mt-1.5 w-full"
                                            placeholder="Поиск по названию подразделения"
                                            autocomplete="off"
                                        />
                                        <select
                                            id="overview_subdivision_id"
                                            name="subdivision_id"
                                            class="app-select w-full"
                                            autocomplete="organization"
                                        >
                                            <option value="">Выберите подразделение…</option>
                                            @foreach($subdivisions as $subdivision)
                                                <option value="{{ $subdivision->id }}" @selected((int) ($selectedSubdivision?->id ?? 0) === (int) $subdivision->id)>
                                                    {{ $subdivision->name }}@if((int) $subdivision->warehouses_count > 0) ({{ (int) $subdivision->warehouses_count }})@endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="min-w-0">
                                        <label for="overview_warehouse_id" class="app-form-label">Склад</label>
                                        <select
                                            id="overview_warehouse_id"
                                            name="warehouse_id"
                                            class="app-select mt-1.5 w-full @if(!$selectedSubdivision || $warehouses->isEmpty()) opacity-60 @endif"
                                            @if(!$selectedSubdivision || $warehouses->isEmpty()) disabled @endif
                                        >
                                            @if(!$selectedSubdivision)
                                                <option value="">Сначала выберите подразделение</option>
                                            @elseif($warehouses->isEmpty())
                                                <option value="">В подразделении нет складов</option>
                                            @else
                                                <option value="">Выберите склад…</option>
                                                @foreach($warehouses as $warehouse)
                                                    <option value="{{ $warehouse->id }}" @selected((int) ($selectedWarehouse?->id ?? 0) === (int) $warehouse->id)>
                                                        {{ $warehouse->name }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                    <div class="min-w-0 sm:max-w-[12rem]">
                                        <label for="overview_per_page" class="app-form-label">На странице</label>
                                        <select
                                            id="overview_per_page"
                                            name="per_page"
                                            class="app-select mt-1.5 w-full"
                                            onchange="document.getElementById('materials-overview-filters').requestSubmit()"
                                        >
                                            @foreach($allowedPerPage as $size)
                                                <option value="{{ $size }}" @selected((int) ($perPage ?? 0) === (int) $size)>{{ $size }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if($selectedWarehouse)
                                        <div class="min-w-0">
                                            <label for="overview_equipment_search" class="app-form-label">Оборудование</label>
                                            <input
                                                id="overview_equipment_search"
                                                type="search"
                                                name="equipment"
                                                class="app-input mt-1.5 w-full"
                                                placeholder="Поиск по названию"
                                                value="{{ $equipmentSearch ?? '' }}"
                                                autocomplete="off"
                                            />
                                        </div>
                                    @endif
                                </form>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Справа на lg: остатки или подсказка (шире). --}}
                    <div class="min-w-0 space-y-4 lg:col-span-8">
                        @if($selectedWarehouse)
                        <div class="rounded-xl border border-stone-200/80 bg-stone-50/40 p-4 shadow-sm ring-1 ring-stone-200/40 dark:border-stone-600 dark:bg-stone-900/35 dark:ring-stone-700/60 sm:p-5 space-y-4">
                            <div class="flex flex-col gap-1 border-b border-stone-200/80 pb-4 dark:border-stone-600/80">
                                <p class="text-xs font-semibold uppercase tracking-wide text-black/50 dark:text-white/50">Остатки</p>
                                <h3 class="text-lg font-semibold text-black dark:text-white leading-snug">
                                    {{ $selectedSubdivision?->name }}
                                    <span class="font-normal text-black/45 dark:text-white/45">·</span>
                                    {{ $selectedWarehouse->name }}
                                </h3>
                            </div>

                            <div class="mt-4">
                                @include('materials.partials.overview-balance-section', [
                                    'balances' => $equipmentBalances,
                                    'heading' => null,
                                    'intro' => null,
                                    'emptyText' => 'По этому складу ещё не было движений оборудования.',
                                    'equipmentSearch' => $equipmentSearch ?? '',
                                ])
                            </div>
                        </div>
                        @elseif($selectedSubdivision && $warehouses->isNotEmpty())
                        <p class="text-sm text-black/70 dark:text-white/70 leading-relaxed pt-1">
                            Выберите склад в списке слева — здесь появится таблица остатков.
                        </p>
                        @else
                        <p class="text-sm text-black/70 dark:text-white/70 leading-relaxed pt-1">
                            Остатки появятся здесь после выбора подразделения и склада в форме слева.
                        </p>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>

    @once
        <script>
            (function () {
                var form = document.getElementById('materials-overview-filters');
                var sub = document.getElementById('overview_subdivision_id');
                var wh = document.getElementById('overview_warehouse_id');
                if (!form || !sub || !wh) {
                    return;
                }
                sub.addEventListener('change', function () {
                    wh.value = '';
                    form.submit();
                });
                wh.addEventListener('change', function () {
                    if (wh.disabled) {
                        return;
                    }
                    form.submit();
                });
            })();
        </script>
        @include('partials.js-filterable-select', [
            'searchInputId' => 'overview_subdivision_search',
            'selectInputId' => 'overview_subdivision_id',
        ])
    @endonce
</x-app-layout>
