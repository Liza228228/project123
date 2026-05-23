<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
            {{ ($canManage ?? false) ? 'Учёт оборудования' : 'Остатки оборудования по складам' }}
        </h2>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-10 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(!($canManage ?? false))
            <div class="rounded-2xl border border-orange-200/80 bg-orange-50/35 shadow-sm ring-1 ring-orange-100/70 dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6">
                <p class="text-sm text-black dark:text-white opacity-90">
                    Доступен просмотр остатков по складам. Добавление оборудования и приход/расход доступны только директору и начальнику отдела снабжения.
                </p>
            </div>
        @endif

        @if($canManage ?? false)
        <div class="rounded-2xl border border-orange-200/80 bg-orange-50/35 shadow-sm ring-1 ring-orange-100/70 dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-black dark:text-white"> Добавить оборудование в справочник</h3>
            
            <form method="POST" action="{{ route('materials.store-material') }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3" id="equipment-catalog-form">
                @csrf
                <div class="md:col-span-2">
                    <x-input-label for="name" value="Название оборудования" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="measurement_type" value="Тип измерения" />
                    <select id="measurement_type" name="measurement_type" class="app-select mt-1" required>
                        @foreach(($measurementTypeOptions ?? []) as $typeCode => $typeName)
                            <option value="{{ $typeCode }}" @selected(old('measurement_type') === $typeCode)>{{ $typeName }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('measurement_type')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="measurement_unit_id" value="Единица измерения" />
                    <select id="measurement_unit_id" name="measurement_unit_id" class="app-select mt-1" data-selected-id="{{ old('measurement_unit_id') }}" required></select>
                    <x-input-error :messages="$errors->get('measurement_unit_id')" class="mt-1" />
                </div>
                <div class="md:col-span-2">
                    <x-primary-button>Сохранить оборудование</x-primary-button>
                </div>
            </form>
        </div>
        @endif

        @if($canManage ?? false)
        <div class="rounded-2xl border border-orange-200/80 bg-orange-50/35 shadow-sm ring-1 ring-orange-100/70 dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-black dark:text-white"> Поступление оборудования на основной склад</h3>
            @if(!$mainWarehouse)
                <div class="mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/30 dark:text-red-300">
                    Основной склад не найден. Назначьте складу «Администрация» признак основного (`is_primary = true`).
                </div>
            @endif
            @php
                $receiptEquipmentPickerOptions = $catalogMaterials->map(fn ($m) => [
                    'id' => (int) $m->id,
                    'label' => $m->display_name.' ('.$m->stockQuantityUnitLabel().')',
                    'unit_type_code' => (string) ($m->measurementUnit?->unitType?->code ?? ''),
                ])->values()->all();
            @endphp
            <form
                method="POST"
                action="{{ route('materials.store-movement') }}"
                class="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3"
                x-data="materialsReceiptEquipmentPicker()"
                @submit="validateSubmit($event)"
            >
                @csrf
                @if(request()->filled('per_page'))
                    <input type="hidden" name="per_page" value="{{ (int) request('per_page') }}" />
                @endif
                <input type="hidden" name="material_stock_movement_type_id" value="{{ (int) ($receiptTypeId ?? 0) }}" />
                @if($mainWarehouse)
                    <input type="hidden" name="warehouse_id" value="{{ $mainWarehouse->id }}" />
                @endif
                <div class="relative">
                    <x-input-label for="equipment_search" value="Оборудование" />
                    <input type="hidden" name="equipment_id" x-bind:value="selectedId || ''" />
                    <input
                        id="equipment_search"
                        type="text"
                        class="app-input mt-1 block w-full"
                        placeholder="Начните вводить название или выберите из списка…"
                        autocomplete="off"
                        x-model="search"
                        @focus="onFocus"
                        @blur="onBlur"
                        @input="onSearchInput"
                        @keydown.escape.prevent.stop="open = false"
                        x-bind:disabled="items.length === 0"
                    />
                    <div
                        x-show="open && filteredItems.length > 0"
                        x-cloak
                        class="app-suggestions"
                        role="listbox"
                    >
                        <template x-for="item in filteredItems" x-bind:key="item.id">
                            <button
                                type="button"
                                class="app-suggestion-btn"
                                role="option"
                                @mousedown.prevent="selectItem(item)"
                                x-text="item.label"
                            ></button>
                        </template>
                    </div>
                    <p
                        x-show="open && (search || '').trim() && filteredItems.length === 0"
                        x-cloak
                        class="app-suggestions px-3 py-2 text-sm text-stone-600 dark:text-stone-400"
                    >
                        Ничего не найдено — измените запрос.
                    </p>
                    <p x-show="equipError" x-cloak class="mt-1 text-sm text-red-600 dark:text-red-400">
                        Выберите оборудование из списка.
                    </p>
                    <x-input-error :messages="$errors->get('equipment_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Склад поступления" />
                    <div class="mt-1 flex min-h-[2.75rem] items-center rounded-xl border border-stone-200 bg-stone-50/80 px-3.5 text-sm text-stone-700 dark:border-stone-600 dark:bg-stone-800/50 dark:text-stone-200">
                        {{ $mainWarehouse?->name ?? 'Не определён' }}
                    </div>
                    <x-input-error :messages="$errors->get('warehouse_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Тип операции" />
                    <div class="mt-1 flex min-h-[2.75rem] items-center rounded-xl border border-stone-200 bg-stone-50/80 px-3.5 text-sm text-stone-700 dark:border-stone-600 dark:bg-stone-800/50 dark:text-stone-200">
                        Поступление (приход)
                    </div>
                </div>

                <div>
                    <label
                        class="block font-medium text-sm text-black dark:text-white"
                        x-bind:for="receiptClothingMode ? 'receipt_variant' : 'quantity'"
                        x-text="receiptFieldLabel"
                    ></label>
                    <input
                        type="hidden"
                        name="quantity"
                        value="1"
                        x-bind:disabled="!receiptClothingMode"
                    />
                    <x-text-input
                        id="quantity"
                        type="text"
                        class="mt-1 block w-full"
                        inputmode="decimal"
                        autocomplete="off"
                        value="{{ old('quantity') }}"
                        x-bind:name="receiptClothingMode ? false : 'quantity'"
                        x-bind:disabled="receiptClothingMode"
                        x-bind:required="!receiptClothingMode"
                        @input="onReceiptQuantityInput($event)"
                        x-show="!receiptClothingMode"
                        x-cloak
                    />
                    <select
                        id="receipt_variant"
                        class="app-select mt-1"
                        x-bind:name="receiptClothingMode ? 'receipt_variant' : false"
                        x-bind:disabled="!receiptClothingMode"
                        x-bind:required="receiptClothingMode"
                        x-show="receiptClothingMode"
                        x-cloak
                    >
                        <option value="" @selected(! filled(old('receipt_variant')))>Выберите размер</option>
                        @foreach(($clothingCatalogSizes ?? []) as $size)
                            <option value="{{ $size }}" @selected((string) old('receipt_variant') === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    
                    <p class="mt-1 text-xs text-stone-500 dark:text-stone-400" x-show="receiptClothingMode" x-cloak>
                        Для спецодежды выберите размер. В учёт идёт 1 единица на строку прихода; при нескольких комплектах одного размера оформите несколько поступлений.
                    </p>
                    <p x-show="receiptVariantError" x-cloak class="mt-1 text-sm text-red-600 dark:text-red-400">
                        Выберите размер из списка.
                    </p>
                    <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                    <x-input-error :messages="$errors->get('receipt_variant')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="unit_price" value="Цена за единицу " />
                    <x-text-input id="unit_price" name="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('unit_price') }}" />
                    <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="counterparty" value="Поставщик (опц.)" />
                    <x-text-input id="counterparty" name="counterparty" type="text" maxlength="255" class="mt-1 block w-full" value="{{ old('counterparty') }}" />
                    <x-input-error :messages="$errors->get('counterparty')" class="mt-1" />
                </div>

                <div class="md:col-span-3">
                    <x-input-label for="comment" value="Комментарий (опц.)" />
                    <textarea id="comment" name="comment" rows="2" class="app-input mt-1 min-h-[5rem] py-2.5">{{ old('comment') }}</textarea>
                    <x-input-error :messages="$errors->get('comment')" class="mt-1" />
                </div>

                <div class="md:col-span-3">
                    <x-primary-button :disabled="!$mainWarehouse">Зафиксировать поступление</x-primary-button>
                </div>
            </form>
        </div>
        @endif

        <div class="rounded-2xl border border-orange-200/80 bg-orange-50/35 shadow-sm ring-1 ring-orange-100/70 dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <h3 class="text-lg font-semibold text-black dark:text-white">{{ ($canManage ?? false) ? ' Остатки оборудования' : ' Остатки оборудования' }}</h3>
                <form method="GET" action="{{ ($canManage ?? false) ? route('materials.index') : route('materials.overview') }}" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end w-full sm:w-auto" data-auto-submit="filter">
                    <div class="min-w-0 sm:w-80">
                        <label for="warehouse_filter" class="app-form-label">Склад</label>
                        <input
                            id="warehouse_filter_search"
                            type="search"
                            class="app-input mb-2 min-h-0 w-full min-w-0 sm:max-w-xs"
                            placeholder="Поиск по подразделению или складу"
                            autocomplete="off"
                        />
                        <select id="warehouse_filter" name="warehouse_id" class="app-select w-full min-w-0 sm:max-w-xs">
                            <option value="">Все склады</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>
                                    @if($warehouse->subdivision)
                                        {{ $warehouse->subdivision->name }} — {{ $warehouse->name }}
                                    @else
                                        {{ $warehouse->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0 sm:w-44">
                        <label for="materials-balances-per-page" class="app-form-label">На странице</label>
                        <select id="materials-balances-per-page" name="per_page" class="app-select w-full min-w-0 sm:max-w-xs">
                            @foreach($allowedPerPage as $size)
                                <option value="{{ $size }}" @selected((int) ($perPage ?? 0) === (int) $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            <p class="mt-2 text-xs text-black/70 dark:text-white/70">
                Списания со склада (в том числе по заявкам и акту установки) учитываются в колонке «Расход».
                @if($canManage ?? false)
                    Позиции, пришедшие по заявке как «своё оборудование» (не из справочника), тоже отображаются здесь, если по ним есть движения на выбранном складе.
                @endif
            </p>
            @if($materialsBalancesPaginator->total() === 0)
                <p class="mt-4 rounded-xl border border-dashed border-stone-300 px-4 py-6 text-center text-sm text-black/70 dark:border-stone-600 dark:text-white/70">
                    Оборудование пока не добавлено.
                </p>
            @else
                <div class="mt-4 md:hidden app-card-list">
                    @foreach($materialsBalancesPaginator as $material)
                        @php
                            $aggregate = $balances[$material->id] ?? ['in' => 0, 'out' => 0, 'balance' => 0];
                            $lines = $balanceLines[$material->id] ?? [];
                            if ($lines === []) {
                                $lines = [[
                                    'in' => (float) $aggregate['in'],
                                    'out' => (float) $aggregate['out'],
                                    'balance' => (float) $aggregate['balance'],
                                    'unit_code' => trim((string) ($material->measurementUnit?->code ?? '')) ?: 'шт',
                                    'measurement_type_code' => (string) ($material->measurementUnit?->unitType?->code ?? ''),
                                ]];
                            }
                        @endphp
                        @foreach($lines as $line)
                            @php
                                $unitCode = trim((string) ($line['unit_code'] ?? '')) ?: 'шт';
                                $measurementTypeCode = trim((string) ($line['measurement_type_code'] ?? ''));
                            @endphp
                            <article class="app-card-list__item">
                                <p class="text-sm font-medium text-black dark:text-white app-equipment-line">
                                    @include('materials.partials.balance-equipment-title', [
                                        'equipmentName' => $material->display_name,
                                        'unitCode' => $unitCode,
                                        'measurementTypeCode' => $measurementTypeCode,
                                    ])
                                </p>
                                <dl class="grid grid-cols-3 gap-2 text-center text-xs">
                                    <div class="rounded-lg bg-stone-100/80 px-2 py-2 dark:bg-stone-800/80">
                                        <dt class="font-medium uppercase tracking-wide text-black/55 dark:text-white/55">Приход</dt>
                                        <dd class="mt-1 text-sm font-medium text-black dark:text-white">
                                            @include('materials.partials.balance-quantity-cell', ['quantity' => $line['in'], 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                                        </dd>
                                    </div>
                                    <div class="rounded-lg bg-stone-100/80 px-2 py-2 dark:bg-stone-800/80">
                                        <dt class="font-medium uppercase tracking-wide text-black/55 dark:text-white/55">Расход</dt>
                                        <dd class="mt-1 text-sm font-medium text-red-700 dark:text-red-300/90">
                                            @include('materials.partials.balance-quantity-cell', ['quantity' => $line['out'], 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                                        </dd>
                                    </div>
                                    <div class="rounded-lg bg-emerald-50/90 px-2 py-2 dark:bg-emerald-950/35">
                                        <dt class="font-medium uppercase tracking-wide text-emerald-800/80 dark:text-emerald-200/70">Остаток</dt>
                                        <dd class="mt-1 text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                            @include('materials.partials.balance-quantity-cell', ['quantity' => $line['balance'], 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                        @endforeach
                    @endforeach
                </div>
                <div class="mt-4 hidden md:block app-table-shell">
                    <table class="text-sm text-black dark:text-white">
                        <thead>
                            <tr class="border-b border-stone-200 dark:border-stone-700">
                                <th class="text-left py-2 pr-3">Оборудование</th>
                                <th class="text-right py-2 pr-3">Приход</th>
                                <th class="text-right py-2 pr-3">Расход</th>
                                <th class="text-right py-2">Остаток</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materialsBalancesPaginator as $material)
                                @php
                                    $aggregate = $balances[$material->id] ?? ['in' => 0, 'out' => 0, 'balance' => 0];
                                    $lines = $balanceLines[$material->id] ?? [];
                                    if ($lines === []) {
                                        $lines = [[
                                            'in' => (float) $aggregate['in'],
                                            'out' => (float) $aggregate['out'],
                                            'balance' => (float) $aggregate['balance'],
                                            'unit_code' => trim((string) ($material->measurementUnit?->code ?? '')) ?: 'шт',
                                            'measurement_type_code' => (string) ($material->measurementUnit?->unitType?->code ?? ''),
                                        ]];
                                    }
                                @endphp
                                @foreach($lines as $line)
                                    @php
                                        $unitCode = trim((string) ($line['unit_code'] ?? '')) ?: 'шт';
                                        $measurementTypeCode = trim((string) ($line['measurement_type_code'] ?? ''));
                                    @endphp
                                    <tr class="border-b border-stone-100 dark:border-stone-700/60">
                                        <td class="py-2 pr-3">
                                            @include('materials.partials.balance-equipment-title', [
                                                'equipmentName' => $material->display_name,
                                                'unitCode' => $unitCode,
                                                'measurementTypeCode' => $measurementTypeCode,
                                            ])
                                        </td>
                                        <td class="py-2 pr-3 text-right">
                                            @include('materials.partials.balance-quantity-cell', ['quantity' => $line['in'], 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                                        </td>
                                        <td class="py-2 pr-3 text-right">
                                            @include('materials.partials.balance-quantity-cell', ['quantity' => $line['out'], 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                                        </td>
                                        <td class="py-2 text-right font-semibold">
                                            @include('materials.partials.balance-quantity-cell', ['quantity' => $line['balance'], 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($materialsBalancesPaginator->hasPages())
                    <div class="mt-4 border-t border-orange-200/60 pt-4 dark:border-stone-600/80">
                        {{ $materialsBalancesPaginator->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>

@if($canManage ?? false)
<script>
    window.__materialsReceiptPicker = @json([
        'items' => $receiptEquipmentPickerOptions,
        'initialId' => old('equipment_id') !== null && old('equipment_id') !== '' ? (int) old('equipment_id') : null,
    ]);
    (function () {
        var unitsByType = @json($measurementUnitsByType);
        var typeSelect = document.getElementById('measurement_type');
        var unitSelect = document.getElementById('measurement_unit_id');
        if (!typeSelect || !unitSelect) return;

        function fillUnits() {
            var units = unitsByType[typeSelect.value] || [];
            var selectedUnitId = String(unitSelect.dataset.selectedId || '');
            unitSelect.innerHTML = '';
            units.forEach(function (unit, idx) {
                var shouldSelect = selectedUnitId !== ''
                    ? String(unit.id) === selectedUnitId
                    : idx === 0;
                var option = new Option(unit.code + ' — ' + unit.name, unit.id, shouldSelect, shouldSelect);
                unitSelect.add(option);
            });
            unitSelect.dataset.selectedId = '';
        }

        typeSelect.addEventListener('change', function () {
            fillUnits();
        });
        fillUnits();
    })();
</script>
@endif
@include('partials.js-filterable-select', [
    'searchInputId' => 'warehouse_filter_search',
    'selectInputId' => 'warehouse_filter',
    'preserveSelection' => false,
])
