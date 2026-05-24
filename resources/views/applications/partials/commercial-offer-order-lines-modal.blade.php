@php
    $typeOptions = $measurementMeta['typeOptions'] ?? [];
    $unitsByType = $measurementMeta['unitsByType'] ?? ['piece' => ['шт']];
    $clothingSizes = $measurementMeta['clothingSizes'] ?? [];
    $prefillLines = $commercialOfferOrderPrefillLines ?? [];
@endphp

<x-modal name="commercial-offer-order-lines" maxWidth="2xl" focusable>
    <form method="POST" action="{{ route('applications.commercial-offer-order-lines.store', $application) }}" id="co-order-lines-form" class="flex flex-col max-h-[min(92dvh,40rem)]">
        @csrf
        <div class="flex items-center justify-between gap-3 border-b border-stone-200/90 px-4 py-3 dark:border-stone-600 shrink-0">
            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Своё оборудование</span>
            <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl text-stone-500 hover:bg-stone-100 dark:hover:bg-stone-800" onclick="typeof closeAppModal === 'function' && closeAppModal('commercial-offer-order-lines')" aria-label="Закрыть">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-4 py-4 space-y-3 overflow-y-auto flex-1 min-h-0">
            @error('co_order_lines')
                <x-app-alert type="error">{{ $message }}</x-app-alert>
            @enderror
            @if($prefillLines !== [])
                <p class="text-xs text-stone-600 dark:text-stone-400">
                    Данные подставлены из таблицы коммерческого предложения (наименование, единица измерения, количество). При необходимости измените и нажмите «Сохранить».
                </p>
            @else
                <p class="text-xs text-stone-600 dark:text-stone-400">
                    Укажите оборудование по согласованному КП. Если таблица из макета КП не сохранена, заполните позиции вручную или заново сформируйте КП через макет в заявке.
                </p>
            @endif
            <p class="text-xs text-stone-600 dark:text-stone-400">
                Если наименование совпадает с каталогом и на складе администрации достаточно остатка (тип измерения тот же), позиция будет зарезервирована; только недостающее количество уйдёт в заказ у поставщика.
            </p>
            <div id="co-order-lines-rows" class="space-y-4"></div>
            <button type="button" id="co-order-lines-add" class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto">
                + Добавить позицию
            </button>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-stone-200/90 px-4 py-3 dark:border-stone-600 sm:flex-row sm:justify-end shrink-0">
            <button type="button" class="ui-btn ui-btn--secondary w-full sm:w-auto" onclick="typeof closeAppModal === 'function' && closeAppModal('commercial-offer-order-lines')">Отмена</button>
            <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto">Сохранить</button>
        </div>
    </form>
</x-modal>

<script type="text/template" id="co-order-line-row-tpl">
    <div class="co-order-line-row equipment-row equipment-row--custom app-equipment-card rounded-xl border border-stone-200/90 p-4 dark:border-stone-600">
        <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Позиция</span>
            <button type="button" class="co-order-line-remove inline-flex min-h-9 min-w-9 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600" title="Удалить" aria-label="Удалить позицию">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
            <div class="md:col-span-12">
                <label class="app-form-label !normal-case">Наименование</label>
                <input type="text" name="items[__INDEX__][equipment_name]" placeholder="" maxlength="{{ $equipmentNameMax }}" class="custom-equipment-input app-input" required />
                <p class="mt-1 text-[11px] text-stone-500 dark:text-stone-400">Не более {{ $equipmentNameMax }} символов.</p>
            </div>
            <div class="custom-type-wrap md:col-span-4 min-w-0">
                <label class="app-form-label !normal-case">Тип</label>
                <select name="items[__INDEX__][measurement_type]" class="measurement-type app-select" required>
                    <option value="" disabled>Выберите тип</option>
                    @foreach($typeOptions as $typeCode => $typeName)
                        <option value="{{ $typeCode }}">{{ $typeName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="custom-amount-outer md:col-span-4 min-w-0 hidden">
                <div class="custom-amount-block">
                    <label class="custom-amount-label app-form-label !normal-case">Количество, шт</label>
                    <input type="hidden" name="items[__INDEX__][size_value]" value="" class="custom-size-value-field" />
                    <input type="number" value="1" min="1" step="1" class="custom-amount-number app-input" disabled />
                    <div class="custom-size-wrap hidden mt-2">
                        <label class="custom-size-label app-form-label !normal-case">Размер</label>
                        <select class="custom-amount-size app-select" disabled>
                            <option value="">Выберите размер</option>
                            @foreach($clothingSizes as $sz)
                                <option value="{{ $sz }}">{{ $sz }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="custom-unit-wrap md:col-span-4 min-w-0 hidden">
                <label class="app-form-label !normal-case">Ед.</label>
                <select class="measurement-unit app-select" data-current="" disabled></select>
            </div>
        </div>
    </div>
</script>

<script>
(function () {
    var container = document.getElementById('co-order-lines-rows');
    var tpl = document.getElementById('co-order-line-row-tpl');
    var addBtn = document.getElementById('co-order-lines-add');
    if (!container || !tpl || !addBtn) return;

    var measurementUnits = @js($unitsByType);
    var prefillLines = @js($prefillLines);
    var rowIndex = 0;

    function syncMeasurementRow(row) {
        var typeSelect = row.querySelector('.measurement-type');
        var unitSelect = row.querySelector('.measurement-unit');
        var amountOuter = row.querySelector('.custom-amount-outer');
        var unitWrap = row.querySelector('.custom-unit-wrap');
        if (!typeSelect || !unitSelect) return;

        var selectedType = (typeSelect.value || '').trim();
        var idxMatch = /items\[(\d+)\]/.exec(typeSelect.name || '');
        var idx = idxMatch ? idxMatch[1] : null;
        var num = row.querySelector('.custom-amount-number');
        var sel = row.querySelector('.custom-amount-size');
        var sizeHidden = row.querySelector('.custom-size-value-field');

        function hideQtyUnit() {
            amountOuter && amountOuter.classList.add('hidden');
            unitWrap && unitWrap.classList.add('hidden');
            unitSelect.innerHTML = '';
            unitSelect.setAttribute('disabled', 'disabled');
            unitSelect.removeAttribute('name');
            if (num) { num.removeAttribute('name'); num.disabled = true; }
            if (sel) { sel.setAttribute('disabled', 'disabled'); sel.value = ''; }
            if (sizeHidden) sizeHidden.value = '';
        }

        if (!selectedType) {
            hideQtyUnit();
            return;
        }

        amountOuter && amountOuter.classList.remove('hidden');
        unitWrap && unitWrap.classList.remove('hidden');
        unitSelect.removeAttribute('disabled');
        if (idx !== null) unitSelect.setAttribute('name', 'items[' + idx + '][quantity_unit]');

        var options = measurementUnits[selectedType] || measurementUnits.piece || ['шт'];
        var current = unitSelect.dataset.current || unitSelect.value || options[0];
        unitSelect.innerHTML = '';
        options.forEach(function (u) {
            var opt = new Option(u, u);
            if (u === current) opt.selected = true;
            unitSelect.add(opt);
        });
        if (!options.includes(unitSelect.value)) unitSelect.value = options[0];
        unitSelect.dataset.current = unitSelect.value;

        var label = row.querySelector('.custom-amount-label');
        var u = unitSelect.value || 'шт';
        if (label) {
            if (selectedType === 'length') label.textContent = 'Длина, ' + u;
            else if (selectedType === 'mass') label.textContent = 'Масса, ' + u;
            else if (selectedType === 'clothing_size') label.textContent = 'Количество, шт';
            else label.textContent = 'Количество, ' + u;
        }

        var sizeWrap = row.querySelector('.custom-size-wrap');
        if (!num || !sel || !sizeHidden) return;

        if (selectedType === 'clothing_size') {
            num.classList.remove('hidden');
            num.removeAttribute('disabled');
            num.required = true;
            if (idx !== null) num.setAttribute('name', 'items[' + idx + '][quantity]');
            sizeWrap && sizeWrap.classList.remove('hidden');
            sel.removeAttribute('disabled');
            sel.required = true;
            sel.addEventListener('change', function () {
                sizeHidden.value = sel.value || '';
            }, { once: false });
        } else {
            num.classList.remove('hidden');
            num.removeAttribute('disabled');
            num.required = true;
            if (idx !== null) num.setAttribute('name', 'items[' + idx + '][quantity]');
            sizeWrap && sizeWrap.classList.add('hidden');
            sel.setAttribute('disabled', 'disabled');
            sel.value = '';
            sizeHidden.value = '';
        }
    }

    function applyPrefill(row, prefill) {
        if (!prefill) return;
        var nameInput = row.querySelector('.custom-equipment-input');
        if (nameInput && prefill.equipment_name) {
            nameInput.value = prefill.equipment_name;
        }
        var typeSelect = row.querySelector('.measurement-type');
        if (typeSelect && prefill.measurement_type) {
            typeSelect.value = prefill.measurement_type;
        }
        var unitSelect = row.querySelector('.measurement-unit');
        if (unitSelect && prefill.quantity_unit) {
            unitSelect.dataset.current = prefill.quantity_unit;
        }
        syncMeasurementRow(row);
        if (unitSelect && prefill.quantity_unit) {
            unitSelect.value = prefill.quantity_unit;
            unitSelect.dataset.current = prefill.quantity_unit;
        }
        var num = row.querySelector('.custom-amount-number');
        if (num && prefill.quantity) {
            num.value = String(prefill.quantity);
        }
    }

    function bindRow(row) {
        var typeSelect = row.querySelector('.measurement-type');
        typeSelect && typeSelect.addEventListener('change', function () { syncMeasurementRow(row); });
        row.querySelector('.co-order-line-remove')?.addEventListener('click', function () {
            if (container.querySelectorAll('.co-order-line-row').length <= 1) return;
            row.remove();
        });
    }

    function addRow(prefill) {
        var html = tpl.innerHTML.replace(/__INDEX__/g, String(rowIndex++));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var row = wrap.firstElementChild;
        container.appendChild(row);
        bindRow(row);
        applyPrefill(row, prefill);
    }

    addBtn.addEventListener('click', function () { addRow(null); });

    if (prefillLines.length > 0) {
        prefillLines.forEach(function (line) { addRow(line); });
    } else {
        addRow(null);
    }
})();
</script>
