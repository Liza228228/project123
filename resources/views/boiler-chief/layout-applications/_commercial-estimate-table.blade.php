{{-- Таблица сметы КП: ед. измер — выбор, кол-во/цена — числа, сумма — авто. --}}
<div class="rounded-xl border border-orange-200/75 bg-white p-4 dark:border-orange-900/40 dark:bg-stone-900/40">
    <p class="text-sm font-medium text-stone-800 dark:text-stone-200 mb-2" x-text="field.label || field.key"></p>
    <div class="mb-3 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Число строк</label>
            <input type="number" class="app-input w-28 min-h-0" min="1" max="30"
                   :value="getTableRowCount(field.key)"
                   @input="setCommercialEstimateRowCount(field.key, $event.target.value)" />
        </div>
        <p class="text-xs text-stone-500 dark:text-stone-400 pb-2">от 1 до 30</p>
    </div>
    <div class="overflow-x-auto rounded-lg border border-orange-200/80 dark:border-orange-900/50">
        <table class="min-w-full text-sm border-collapse">
            <thead>
                <tr class="bg-orange-50/80 dark:bg-orange-950/30">
                    <template x-for="colIdx in tableColumnIndices(field)" :key="field.key + '_th_' + colIdx">
                        <th class="border border-orange-200/80 dark:border-orange-900/40 px-2 py-2 text-left text-xs font-semibold"
                            x-text="field.table_columns[colIdx]"></th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template x-for="rowIdx in tableRowIndicesForField(field)" :key="field.key + '_row_' + rowIdx">
                    <tr>
                        <template x-for="colIdx in tableColumnIndices(field)" :key="field.key + '_cell_' + rowIdx + '_' + colIdx">
                            <td class="border border-orange-100/90 dark:border-orange-900/35 p-1">
                                <template x-if="commercialEstimateColRole(colIdx) === 'name'">
                                    <input type="text"
                                           class="app-input min-h-0 w-full text-sm py-1.5"
                                           maxlength="500"
                                           :name="commercialEstimateInputName(field.key, rowIdx, colIdx)" />
                                </template>
                                <template x-if="commercialEstimateColRole(colIdx) === 'unit'">
                                    <div class="flex flex-col gap-1 min-w-[6.5rem]">
                                        <select class="app-select min-h-0 w-full text-xs py-1"
                                                :value="commercialEstimateUnitType(field.key, rowIdx)"
                                                @change="onCommercialEstimateUnitTypeChange(field.key, rowIdx, $event.target.value)">
                                            <template x-for="entry in measurementTypeOptionEntries()" :key="field.key + '_mt_' + rowIdx + '_' + entry.code">
                                                <option :value="entry.code" x-text="entry.label" :selected="commercialEstimateUnitType(field.key, rowIdx) === entry.code"></option>
                                            </template>
                                        </select>
                                        <select class="app-select min-h-0 w-full text-sm py-1.5"
                                                :name="commercialEstimateInputName(field.key, rowIdx, colIdx)"
                                                :value="getCommercialEstimateCellValue(field.key, rowIdx, colIdx)"
                                                @change="setCommercialEstimateCellValue(field.key, rowIdx, colIdx, $event.target.value)">
                                            <template x-for="unit in unitsForMeasurementType(commercialEstimateUnitType(field.key, rowIdx))" :key="field.key + '_u_' + rowIdx + '_' + unit">
                                                <option :value="unit" x-text="unit"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>
                                <template x-if="commercialEstimateColRole(colIdx) === 'qty' || commercialEstimateColRole(colIdx) === 'price'">
                                    <input type="text"
                                           inputmode="decimal"
                                           autocomplete="off"
                                           class="app-input min-h-0 w-full text-sm py-1.5 text-right"
                                           maxlength="24"
                                           :name="commercialEstimateInputName(field.key, rowIdx, colIdx)"
                                           @input="onCommercialEstimateNumericInput(field.key, rowIdx, colIdx, $event)" />
                                </template>
                                <template x-if="commercialEstimateColRole(colIdx) === 'sum'">
                                    <input type="text"
                                           readonly
                                           tabindex="-1"
                                           class="app-input min-h-0 w-full text-sm py-1.5 text-right bg-stone-50 dark:bg-stone-900/60"
                                           :name="commercialEstimateInputName(field.key, rowIdx, colIdx)" />
                                </template>
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
