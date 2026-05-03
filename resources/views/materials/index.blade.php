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
                    Доступен просмотр остатков и журнала по складам. Добавление оборудования и операции прихода/расхода доступны только директору, техническому директору и начальнику отдела снабжения.
                </p>
            </div>
        @endif

        @if($canManage ?? false)
        <div class="rounded-2xl border border-orange-200/80 bg-orange-50/35 shadow-sm ring-1 ring-orange-100/70 dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-black dark:text-white">1) Добавить оборудование в справочник</h3>
            <form method="POST" action="{{ route('materials.store-material') }}" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3" id="equipment-catalog-form">
                @csrf
                <div class="md:col-span-2">
                    <x-input-label for="name" value="Название оборудования" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="value" value="Размер / маркировка" />
                    <x-text-input id="value" name="value" type="text" class="mt-1 block w-full" value="{{ old('value') }}" />
                    <p id="value-format-hint" class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                        Для типа «Длина» разрешены только цифры и знаки разделителя.
                    </p>
                    <x-input-error :messages="$errors->get('value')" class="mt-1" />
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
                <div class="md:col-span-2">
                    <x-input-label for="measurement_unit_id" value="Единица измерения" />
                    <select id="measurement_unit_id" name="measurement_unit_id" class="app-select mt-1" data-selected-id="{{ old('measurement_unit_id') }}" required></select>
                    <x-input-error :messages="$errors->get('measurement_unit_id')" class="mt-1" />
                </div>
                <div class="md:col-span-4">
                    <x-primary-button>Сохранить оборудование</x-primary-button>
                </div>
            </form>
        </div>
        @endif

        @if($canManage ?? false)
        <div class="rounded-2xl border border-orange-200/80 bg-orange-50/35 shadow-sm ring-1 ring-orange-100/70 dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-black dark:text-white">2) Поступление оборудования на основной склад</h3>
            @if(!$mainWarehouse)
                <div class="mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/30 dark:text-red-300">
                    Основной склад не найден. Назначьте складу «Администрация» признак основного (`is_primary = true`).
                </div>
            @endif
            <form method="POST" action="{{ route('materials.store-movement') }}" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <input type="hidden" name="material_stock_movement_type_id" value="{{ (int) ($receiptTypeId ?? 0) }}" />
                @if($mainWarehouse)
                    <input type="hidden" name="warehouse_id" value="{{ $mainWarehouse->id }}" />
                @endif
                <div>
                    <x-input-label for="equipment_id" value="Оборудование" />
                    <select id="equipment_id" name="equipment_id" class="app-select mt-1" required>
                        <option value="">Выберите оборудование</option>
                        @foreach($materials as $material)
                            <option value="{{ $material->id }}" @selected((int) old('equipment_id') === (int) $material->id)>{{ $material->display_name }} ({{ $material->measurementUnit?->code ?? 'шт' }})</option>
                        @endforeach
                    </select>
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
                    <x-input-label for="quantity" value="Количество" />
                    <x-text-input id="quantity" name="quantity" type="number" step="0.001" class="mt-1 block w-full" value="{{ old('quantity') }}" required />
                    <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="unit_price" value="Цена за единицу (опц.)" />
                    <x-text-input id="unit_price" name="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('unit_price') }}" />
                    <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="counterparty" value="Контрагент (опц.)" />
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
            <div class="flex flex-wrap items-end gap-3 justify-between">
                <h3 class="text-lg font-semibold text-black dark:text-white">{{ ($canManage ?? false) ? '3) Остатки оборудования' : '1) Остатки оборудования' }}</h3>
                <form method="GET" action="{{ ($canManage ?? false) ? route('materials.index') : route('materials.overview') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="min-w-0 sm:w-80">
                        <label for="warehouse_filter" class="app-form-label">Склад</label>
                        <select id="warehouse_filter" name="warehouse_id" class="app-select w-full min-w-0 sm:max-w-xs">
                            <option value="">Все склады</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="ui-btn ui-btn--primary shrink-0">Показать</button>
                </form>
            </div>

            <p class="mt-2 text-xs text-black/70 dark:text-white/70">
                Списания со склада (в том числе по заявкам и акту установки) учитываются в колонке «Расход» и в журнале операций ниже.
            </p>
            <div class="mt-4 app-table-shell">
                <table class="min-w-full text-sm text-black dark:text-white">
                    <thead>
                        <tr class="border-b border-stone-200 dark:border-stone-700">
                            <th class="text-left py-2 pr-3">Оборудование</th>
                            <th class="text-right py-2 pr-3">Приход</th>
                            <th class="text-right py-2 pr-3">Расход</th>
                            <th class="text-right py-2">Остаток</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materials as $material)
                            @php
                                $row = $balances[$material->id] ?? ['in' => 0, 'out' => 0, 'balance' => 0];
                            @endphp
                            <tr class="border-b border-stone-100 dark:border-stone-700/60">
                                <td class="py-2 pr-3">{{ $material->name }} ({{ $material->measurementUnit?->code ?? 'шт' }})</td>
                                <td class="py-2 pr-3 text-right">{{ number_format((float) $row['in'], 3, '.', ' ') }}</td>
                                <td class="py-2 pr-3 text-right">{{ number_format((float) $row['out'], 3, '.', ' ') }}</td>
                                <td class="py-2 text-right font-semibold">{{ number_format((float) $row['balance'], 3, '.', ' ') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-black/70 dark:text-white/70">Оборудование пока не добавлено.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-orange-200/80 bg-orange-50/35 shadow-sm ring-1 ring-orange-100/70 dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-black dark:text-white">{{ ($canManage ?? false) ? '4) Журнал операций по оборудованию' : '2) Журнал операций по оборудованию' }}</h3>
            <div class="mt-4 app-table-shell">
                <table class="min-w-full text-sm text-black dark:text-white">
                    <thead>
                        <tr class="border-b border-stone-200 dark:border-stone-700">
                            <th class="text-left py-2 pr-3">Дата</th>
                            <th class="text-left py-2 pr-3">Оборудование</th>
                            <th class="text-left py-2 pr-3">Склад</th>
                            <th class="text-left py-2 pr-3">Тип</th>
                            <th class="text-left py-2 pr-3">Контрагент</th>
                            <th class="text-right py-2 pr-3">Количество</th>
                            <th class="text-left py-2 max-w-[18rem]">Комментарий</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            @php
                                $signed = $movement->signedQuantity();
                            @endphp
                            <tr class="border-b border-stone-100 dark:border-stone-700/60">
                                <td class="py-2 pr-3">{{ $movement->created_at?->format('d.m.Y H:i') }}</td>
                                <td class="py-2 pr-3">{{ $movement->equipment?->name ?? '—' }} @if($movement->equipment) ({{ $movement->equipment->measurementUnit?->code ?? 'шт' }}) @endif</td>
                                <td class="py-2 pr-3">{{ $movement->warehouse?->name }}</td>
                                <td class="py-2 pr-3">{{ $movement->movementType?->name ?? '—' }}</td>
                                <td class="py-2 pr-3 text-xs text-black/80 dark:text-white/80">{{ $movement->counterparty ?: '—' }}</td>
                                <td class="py-2 pr-3 text-right {{ $signed < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400' }}">
                                    {{ number_format($signed, 3, '.', ' ') }}
                                </td>
                                <td class="py-2 max-w-[18rem] text-xs text-black/80 dark:text-white/80 break-words">{{ $movement->comment ? \Illuminate\Support\Str::limit($movement->comment, 160) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-black/70 dark:text-white/70">Операций пока нет.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $movements->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

@if($canManage ?? false)
<script>
    (function () {
        var unitsByType = @json($measurementUnitsByType);
        var typeSelect = document.getElementById('measurement_type');
        var unitSelect = document.getElementById('measurement_unit_id');
        var valueInput = document.getElementById('value');
        var valueHint = document.getElementById('value-format-hint');
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

        function isLengthType() {
            return typeSelect.value === 'length';
        }

        function sanitizeLengthValue(raw) {
            return String(raw || '').replace(/[A-Za-zА-Яа-яЁё]/g, '');
        }

        function syncValueRestrictions() {
            if (!valueInput) {
                return;
            }
            if (isLengthType()) {
                valueInput.setAttribute('inputmode', 'decimal');
                valueInput.setAttribute('pattern', '^[0-9.,\\-\\s/]*$');
                if (valueHint) {
                    valueHint.classList.remove('hidden');
                }
                valueInput.value = sanitizeLengthValue(valueInput.value);
            } else {
                valueInput.removeAttribute('inputmode');
                valueInput.removeAttribute('pattern');
                if (valueHint) {
                    valueHint.classList.add('hidden');
                }
            }
        }

        typeSelect.addEventListener('change', function () {
            fillUnits();
            syncValueRestrictions();
        });
        if (valueInput) {
            valueInput.addEventListener('input', function () {
                if (!isLengthType()) {
                    return;
                }
                var cleaned = sanitizeLengthValue(valueInput.value);
                if (cleaned !== valueInput.value) {
                    valueInput.value = cleaned;
                }
            });
        }
        fillUnits();
        syncValueRestrictions();
    })();
</script>
@endif
