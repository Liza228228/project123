@php
    // шаблон страницы
    use App\Support\RequestLayoutTableField;

    $field = is_array($field ?? null) ? $field : [];
    $key = (string) ($field['key'] ?? '');
    $label = (string) ($field['label'] ?? $key);
    $def = RequestLayoutTableField::definitionFromField($field);
    $columns = $def['columns'];
    $colCount = count($columns);
    $oldTable = old('values.'.$key);
    $initialRowCount = RequestLayoutTableField::rowCountFromRaw($oldTable);
    $rows = RequestLayoutTableField::decodeValues($oldTable, $initialRowCount, $colCount);
@endphp
@if($key !== '')
    <div
        x-data="requestLayoutTableFill({
            columns: {{ \Illuminate\Support\Js::from($columns) }},
            initialRowCount: {{ (int) $initialRowCount }},
            savedRows: {{ \Illuminate\Support\Js::from($rows) }},
        })"
    >
        <label class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-1">
            {{ $label }}
            <span class="block text-stone-400 font-normal text-xs mt-0.5">Таблица: укажите число строк и заполните ячейки. В PDF вставьте в текст макета подстановку «{{ $key }}».</span>
        </label>
        <div class="mb-3 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Число строк</label>
                <input
                    type="number"
                    class="app-input w-28 min-h-0"
                    min="1"
                    max="30"
                    x-model.number="rowCount"
                    @change="clampRowCount()"
                />
            </div>
            <p class="text-xs text-stone-500 dark:text-stone-400 pb-2">от 1 до 30 строк</p>
        </div>
        <div class="overflow-x-auto rounded-xl border border-orange-200/80 dark:border-orange-900/50">
            <table class="min-w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-orange-50/80 dark:bg-orange-950/30">
                        <template x-for="colIdx in colIndices()" :key="'th_{{ $key }}_' + colIdx">
                            <th
                                class="border border-orange-200/80 dark:border-orange-900/40 px-2 py-2 text-left text-xs font-semibold text-stone-800 dark:text-stone-100"
                                x-text="columns[colIdx]"
                            ></th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="rowIdx in rowIndices()" :key="'row_{{ $key }}_' + rowIdx">
                        <tr>
                            <template x-for="colIdx in colIndices()" :key="'cell_{{ $key }}_' + rowIdx + '_' + colIdx">
                                <td class="border border-orange-100/90 dark:border-orange-900/35 p-1">
                                    <input
                                        type="text"
                                        class="app-input min-h-0 w-full text-sm py-1.5"
                                        maxlength="500"
                                        :name="'values[{{ $key }}][' + rowIdx + '][' + colIdx + ']'"
                                        :value="cellValue(rowIdx, colIdx)"
                                    />
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        @error('values.'.$key)
            <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    </div>
@endif
