@php // операции брака и списания на складе
    $stockOptions = collect($warehouseStockOptions ?? $mainWarehouseStockOptions ?? []);
    $overviewQuery = $overviewTabQuery ?? [];
    $initialEquipmentKey = old('equipment_key', '');
    $stockWarehouse = $selectedWarehouse ?? $mainWarehouse ?? null;
@endphp

@if(($canManageWarehouseStock ?? $canManageMainWarehouseStock ?? false) && $stockOptions->isNotEmpty())
    <div
        class="rounded-xl border border-amber-200/80 bg-amber-50/40 p-4 dark:border-amber-800/50 dark:bg-amber-950/25 space-y-4"
        x-data="mainWarehouseStockManagement(@js($stockOptions->values()->all()), @js($initialEquipmentKey))"
    >
        <div class="space-y-1">
            <h4 class="text-sm font-semibold text-amber-950 dark:text-amber-100">Брак и списание на складе</h4>
            <p class="text-xs text-black/75 dark:text-white/75">
                Перевод в брак и утилизация брака доступны по складу «{{ $stockWarehouse?->name ?? '—' }}».
            </p>
        </div>

        <div class="rounded-lg border border-amber-200/70 bg-white/70 p-3 dark:border-amber-900/40 dark:bg-stone-950/40 space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-3">
                <div class="w-full sm:w-44 shrink-0">
                    <label for="main_wh_stock_filter" class="app-form-label !normal-case text-xs">Показать</label>
                    <select
                        id="main_wh_stock_filter"
                        class="app-select mt-1.5 w-full text-sm"
                        x-model="equipmentFilter"
                        @change="onEquipmentFilterChange()"
                    >
                        <option value="all">Все позиции</option>
                        <option value="defective">Только с браком</option>
                    </select>
                </div>
                <div class="min-w-0 flex-1">
                    <label for="main_wh_stock_option" class="app-form-label !normal-case text-xs">
                        Оборудование <span class="text-red-600 dark:text-red-400">*</span>
                    </label>
                    <select
                        id="main_wh_stock_option"
                        class="app-select mt-1.5 w-full text-sm @error('equipment_key') border-red-400 ring-red-200 dark:border-red-600 @enderror"
                        @change="syncSelectionFromSelect()"
                    >
                        <option value="">Выберите позицию…</option>
                    </select>
                    <p class="mt-1.5 text-[11px] text-black/60 dark:text-white/60">
                        Сначала выберите позицию здесь, затем укажите количество и нажмите «Отметить брак».
                    </p>
                    <p
                        class="mt-1.5 text-[11px] text-black/60 dark:text-white/60"
                        x-show="equipmentFilter === 'defective' && filteredItems.length === 0"
                        x-cloak
                    >
                        Нет позиций с браком на основном складе.
                    </p>
                </div>
            </div>

            <dl class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2" x-show="selectedKey" x-cloak>
                <div class="rounded-lg border border-emerald-200/80 bg-emerald-50/70 px-2 py-1.5 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                    <dt class="font-medium text-emerald-900/80 dark:text-emerald-100/80"> Остаток</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-emerald-950 dark:text-emerald-50" x-text="formatQty(goodBalance) + ' ' + unitCode"></dd>
                </div>
                <div class="rounded-lg border border-amber-200/80 bg-amber-50/70 px-2 py-1.5 dark:border-amber-900/50 dark:bg-amber-950/30">
                    <dt class="font-medium text-amber-900/80 dark:text-amber-100/80">Брак</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-amber-950 dark:text-amber-50" x-text="formatQty(defectiveBalance) + ' ' + unitCode"></dd>
                </div>
            </dl>
        </div>

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50/80 px-3 py-2 text-xs text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200">
                @foreach($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <div class="space-y-4" x-show="selectedKey" x-cloak>
        @foreach(['transfer' => ['title' => 'Перевести в брак', 'route' => 'materials.overview-transfer-defective', 'btn' => 'Отметить брак', 'btnClass' => 'ui-btn--secondary'], 'dispose' => ['title' => 'Утилизировать брак', 'route' => 'materials.overview-dispose-defective', 'btn' => 'Утилизировать', 'btnClass' => 'ui-btn--primary']] as $formKey => $formMeta)
            <form
                method="POST"
                action="{{ route($formMeta['route']) }}"
                class="space-y-2 rounded-lg border border-stone-200/80 bg-white/80 p-3 dark:border-stone-700 dark:bg-stone-950/50"
                data-main-wh-stock-form="{{ $formKey }}"
                data-qty-field="{{ $formKey === 'transfer' ? 'good' : 'defective' }}"
                id="main-wh-stock-form-{{ $formKey }}"
            >
                @csrf
                @foreach($overviewQuery as $queryKey => $queryValue)
                    <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                @endforeach
                <input type="hidden" name="equipment_key" value="{{ old('equipment_key') }}">

                <p class="text-xs font-medium text-black dark:text-white">{{ $formMeta['title'] }}</p>

                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="w-full sm:w-28">
                        <label class="app-form-label !normal-case text-xs" for="main-wh-qty-{{ $formKey }}">Кол-во</label>
                        <input
                            type="text"
                            name="quantity"
                            id="main-wh-qty-{{ $formKey }}"
                            class="app-input text-sm w-full"
                            value="{{ old('quantity') }}"
                            required
                            autocomplete="off"
                            inputmode="decimal"
                            data-main-wh-qty-input
                            @input="clampQtyInput('{{ $formKey }}', $event); qtyRevision++"
                            @blur="clampQtyInput('{{ $formKey }}', $event); qtyRevision++"
                        >
                    </div>

                    @if($formKey === 'transfer')
                        <div class="min-w-0 flex-1">
                            <label class="app-form-label !normal-case text-xs" for="main-wh-reason">Причина брака</label>
                            <input
                                type="text"
                                name="defect_reason"
                                id="main-wh-reason"
                                maxlength="1000"
                                value="{{ old('defect_reason') }}"
                                placeholder="Например: повреждение при хранении"
                                class="app-input text-sm w-full"
                                required
                            >
                        </div>
                    @else
                        <div class="min-w-0 flex-1">
                            <label class="app-form-label !normal-case text-xs" for="main-wh-dispose-comment">Комментарий</label>
                            <input
                                type="text"
                                name="comment"
                                id="main-wh-dispose-comment"
                                maxlength="2000"
                                placeholder="Необязательно"
                                class="app-input text-sm w-full"
                            >
                        </div>
                    @endif

                    <button
                        type="button"
                        class="ui-btn {{ $formMeta['btnClass'] }} ui-btn--sm w-full sm:w-auto whitespace-nowrap"
                        x-bind:disabled="!canSubmit('{{ $formKey }}')"
                        @click="submitStockForm('{{ $formKey }}', $event)"
                    >
                        {{ $formMeta['btn'] }}
                    </button>
                </div>

                <p class="text-[11px] text-black/60 dark:text-white/60" x-show="selectedKey" x-cloak x-text="hintText('{{ $formKey }}')"></p>
            </form>
        @endforeach
        </div>

        @error('equipment_key')
            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error('quantity')
            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error('defect_reason')
            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    @once
        <script>
            document.addEventListener('alpine:init', function () {
                Alpine.data('mainWarehouseStockManagement', function (items, initialKey) {
                    return {
                        items: Array.isArray(items) ? items : [],
                        equipmentFilter: 'all',
                        selectedKey: initialKey ? String(initialKey) : '',
                        equipmentId: '',
                        receiptVariant: '',
                        goodBalance: 0,
                        defectiveBalance: 0,
                        unitCode: 'шт',
                        measurementType: '',
                        qtyRevision: 0,

                        init() {
                            this.refreshSelectOptions();
                            this.syncSelectionFromSelect();
                            this.syncEquipmentKeyToForms();
                            this.clampAllQtyInputs();
                        },

                        get filteredItems() {
                            if (this.equipmentFilter === 'defective') {
                                return this.items.filter(function (item) {
                                    return parseFloat(item.defective_balance || 0) > 0.0005;
                                });
                            }

                            return this.items;
                        },

                        refreshSelectOptions() {
                            var select = document.getElementById('main_wh_stock_option');
                            if (! select) {
                                return;
                            }

                            var previous = this.selectedKey || select.value || '';
                            while (select.options.length > 1) {
                                select.remove(1);
                            }

                            this.filteredItems.forEach(function (item) {
                                var option = document.createElement('option');
                                option.value = String(item.key);
                                option.textContent = this.optionLabel(item);
                                select.appendChild(option);
                            }.bind(this));

                            if (previous && this.filteredItems.some(function (item) {
                                return String(item.key) === String(previous);
                            })) {
                                select.value = previous;
                                this.selectedKey = previous;
                            } else {
                                select.value = '';
                                this.selectedKey = '';
                            }
                        },

                        onEquipmentFilterChange() {
                            this.refreshSelectOptions();
                            this.syncSelectionFromSelect();
                        },

                        syncSelectionFromSelect() {
                            var select = document.getElementById('main_wh_stock_option');
                            this.selectedKey = select && select.value ? String(select.value) : '';
                            this.syncSelection();
                            this.syncEquipmentKeyToForms();
                        },

                        syncEquipmentKeyToForms() {
                            var key = this.currentEquipmentKey();
                            document.querySelectorAll('[data-main-wh-stock-form]').forEach(function (form) {
                                var input = form.querySelector('input[name="equipment_key"]');
                                if (input) {
                                    input.value = key;
                                }
                            });
                        },

                        currentEquipmentKey() {
                            var select = document.getElementById('main_wh_stock_option');
                            if (select && select.value) {
                                return String(select.value);
                            }

                            return this.selectedKey ? String(this.selectedKey) : '';
                        },

                        applySelectionToForm(form) {
                            this.syncSelectionFromSelect();
                            var keyInput = form.querySelector('input[name="equipment_key"]');
                            if (keyInput) {
                                keyInput.value = this.currentEquipmentKey();
                            }
                        },

                        submitStockForm(formKey, event) {
                            var form = event.target.closest('form');
                            if (! form) {
                                return;
                            }

                            this.applySelectionToForm(form);

                            if (! this.validateFormFields(form, formKey)) {
                                return;
                            }

                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                            } else {
                                form.submit();
                            }
                        },

                        findSelectedItem() {
                            if (! this.selectedKey) {
                                return null;
                            }

                            return this.items.find(function (item) {
                                return String(item.key) === String(this.selectedKey);
                            }.bind(this)) || null;
                        },

                        optionLabel(item) {
                            var good = this.formatQty(item.good_balance || 0);
                            var defect = this.formatQty(item.defective_balance || 0);
                            return (item.label || '') + ' — годный: ' + good + ' / брак: ' + defect;
                        },

                        syncSelection() {
                            var item = this.findSelectedItem();
                            if (! item) {
                                this.equipmentId = '';
                                this.receiptVariant = '';
                                this.goodBalance = 0;
                                this.defectiveBalance = 0;
                                this.unitCode = 'шт';
                                this.measurementType = '';
                                this.clampAllQtyInputs();
                                this.qtyRevision++;
                                return;
                            }

                            this.equipmentId = String(item.equipment_id || '');
                            this.receiptVariant = item.receipt_variant ? String(item.receipt_variant) : '';
                            this.goodBalance = parseFloat(item.good_balance || 0) || 0;
                            this.defectiveBalance = parseFloat(item.defective_balance || 0) || 0;
                            this.unitCode = item.unit_code || 'шт';
                            this.measurementType = item.measurement_type_code || '';
                            this.clampAllQtyInputs();
                            this.qtyRevision++;
                        },

                        clampAllQtyInputs() {
                            var self = this;
                            ['transfer', 'dispose'].forEach(function (formKey) {
                                var form = document.getElementById('main-wh-stock-form-' + formKey);
                                if (! form) {
                                    return;
                                }
                                var input = form.querySelector('[data-main-wh-qty-input]');
                                if (input && String(input.value || '').trim() !== '') {
                                    self.clampQtyInput(formKey, { target: input });
                                }
                            });
                        },

                        clampQtyInput(formKey, event) {
                            var input = event.target;
                            var maxQty = this.maxQty(formKey);
                            var wholeOnly = this.measurementType === 'piece' || this.measurementType === 'clothing_size';
                            var raw = String(input.value || '');

                            if (maxQty <= 0.0005) {
                                input.value = '';
                                return;
                            }

                            if (wholeOnly) {
                                raw = raw.replace(/[^\d]/g, '');
                                if (raw === '') {
                                    input.value = '';
                                    return;
                                }
                                var intValue = parseInt(raw, 10);
                                if (! Number.isFinite(intValue) || intValue <= 0) {
                                    input.value = '';
                                    return;
                                }
                                if (intValue > maxQty) {
                                    intValue = Math.floor(maxQty);
                                }
                                input.value = String(intValue);
                                return;
                            }

                            raw = raw.replace(/[^\d.,]/g, '').replace(/(\..*)\./g, '$1');
                            if (raw === '' || raw === '.' || raw === ',') {
                                input.value = raw === '' ? '' : raw;
                                return;
                            }

                            var value = parseFloat(raw.replace(',', '.'));
                            if (! Number.isFinite(value) || value <= 0) {
                                input.value = '';
                                return;
                            }
                            if (value > maxQty) {
                                value = maxQty;
                            }
                            input.value = this.formatQty(value);
                        },

                        isQtyInputValid(formKey, input) {
                            if (! input) {
                                return false;
                            }

                            this.clampQtyInput(formKey, { target: input });

                            var raw = String(input.value || '').trim();
                            if (raw === '' || raw === '.' || raw === ',') {
                                return false;
                            }

                            var maxQty = this.maxQty(formKey);
                            var value = parseFloat(raw.replace(',', '.'));
                            return Number.isFinite(value) && value > 0 && value <= maxQty + 0.000001;
                        },

                        formatQty(value) {
                            var num = parseFloat(value);
                            if (! Number.isFinite(num)) {
                                return '0';
                            }
                            return String(num).replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1');
                        },

                        maxQty(formKey) {
                            if (formKey === 'dispose') {
                                return this.defectiveBalance;
                            }
                            return this.goodBalance;
                        },

                        canSubmit(formKey) {
                            void this.qtyRevision;

                            var equipmentKey = this.currentEquipmentKey();
                            this.selectedKey = equipmentKey;
                            this.syncSelection();

                            if (! equipmentKey || this.maxQty(formKey) <= 0.0005) {
                                return false;
                            }

                            var form = document.getElementById('main-wh-stock-form-' + formKey);
                            if (! form) {
                                return false;
                            }

                            return this.isQtyInputValid(formKey, form.querySelector('[data-main-wh-qty-input]'));
                        },

                        hintText(formKey) {
                            var maxQty = this.maxQty(formKey);
                            if (maxQty <= 0.0005) {
                                if (formKey === 'dispose') {
                                    return 'По выбранной позиции нет брака для утилизации.';
                                }
                                return 'По выбранной позиции нет годного остатка.';
                            }
                            return 'Не больше ' + this.formatQty(maxQty) + ' ' + this.unitCode + '.';
                        },

                        validateFormFields(form, formKey) {
                            var equipmentKey = this.currentEquipmentKey();
                            this.selectedKey = equipmentKey;
                            this.syncSelection();
                            this.applySelectionToForm(form);

                            if (! equipmentKey) {
                                window.alert('Сначала выберите оборудование в списке «Оборудование».');
                                return false;
                            }

                            var input = form.querySelector('[data-main-wh-qty-input]');
                            var maxQty = this.maxQty(formKey);
                            if (maxQty <= 0.0005) {
                                window.alert(formKey === 'dispose'
                                    ? 'По выбранной позиции нет брака для утилизации.'
                                    : 'По выбранной позиции нет годного остатка.');
                                return false;
                            }

                            if (! this.isQtyInputValid(formKey, input)) {
                                var raw = String(input?.value || '').trim();
                                if (raw === '' || raw === '.' || raw === ',') {
                                    window.alert('Укажите количество больше нуля.');
                                } else {
                                    window.alert('Количество не может превышать ' + this.formatQty(maxQty) + ' ' + this.unitCode + '.');
                                }
                                return false;
                            }

                            return true;
                        },
                    };
                });
            });
        </script>
    @endonce
@endif
