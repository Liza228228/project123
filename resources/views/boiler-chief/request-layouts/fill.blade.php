<x-app-layout>
    @php
        $minimalFillFieldsOnly = (bool) ($minimalFillFieldsOnly ?? false);
        $schema = is_array($layout->schema ?? null) ? $layout->schema : [];
        $applicationOptions = array_values($applicationOptions ?? []);
        $warehouseOptions = ($warehouseBalances ?? collect())
            ->map(function ($w) {
                $equipment = collect($w['equipment'] ?? [])->map(function ($item) {
                    $name = (string) ($item['name'] ?? '');
                    $quantity = (string) ($item['quantity'] ?? '');
                    $line = trim((string) ($item['line'] ?? ''));

                    return [
                        'name' => $name,
                        'quantity' => $quantity,
                        'line' => $line,
                    ];
                })->filter(fn (array $item) => $item['line'] !== '')->values()->all();

                return [
                    'id' => (int) ($w['id'] ?? 0),
                    'label' => (string) ($w['label'] ?? 'Склад'),
                    'equipment' => $equipment,
                ];
            })
            ->values()
            ->all();
        $signatureSlotsCount = \App\Models\RequestLayout::resolvedSignatureSlotsCount($schema);
        $signatureRoles = is_array($schema['signature_roles'] ?? null) ? $schema['signature_roles'] : [];
        $allowApplicationEquipmentInsert = \App\Models\RequestLayout::allowsApplicationEquipmentInsert($schema);
        $singleApplicationSelection = trim((string) ($schema['category'] ?? '')) === 'installation-act';
        $signerUserOptions = ($users ?? collect())->isNotEmpty()
            ? \App\Models\User::layoutReportSignerOptions($users)
            : [];
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="$backRoute ?? route('boiler-chief.request-layouts.index')">{{ $backLabel ?? 'К списку макетов отчетов' }}</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white">Новый отчет</h2>
        </div>
    </x-slot>

    <div class="py-4 sm:py-8 flex justify-center px-3">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-xl shadow-orange-950/[0.08] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:ring-orange-950/35">
            <div class="flex items-center justify-between border-b border-orange-100/90 px-5 py-4 dark:border-orange-900/40">
                <h3 class="text-base font-semibold text-stone-900 dark:text-white">Новый отчет</h3>
                <a href="{{ $closeRoute ?? route('boiler-chief.request-layouts.index') }}" class="text-stone-400 hover:text-stone-600 dark:hover:text-stone-200" title="Закрыть">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
            </div>
            @if(($allowEditLayout ?? true) === true)
                <div class="px-5 py-2">
                    <a href="{{ route('boiler-chief.request-layouts.edit', $layout) }}" class="text-sm font-medium text-orange-800 hover:underline dark:text-orange-200/90">Изменить макет</a>
                </div>
            @endif

            <form method="POST"
                  action="{{ $formAction ?? route('boiler-chief.request-layouts.filled-pdf', $layout) }}"
                  class="px-5 pb-6 space-y-5"
                  id="fill-report-form"
                  data-dadata-suggest-url="{{ route('api.dadata.address.suggest', [], false) }}"
                  data-dadata-clean-url="{{ route('api.dadata.address.clean', [], false) }}">
                @csrf
                @if($minimalFillFieldsOnly)
                    <input type="hidden" name="use_current_date" value="1"/>
                @endif

                <div>
                    <label class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-1">Макет отчета</label>
                    <select class="app-select opacity-90" disabled>
                        <option selected>{{ $layout->title }}</option>
                    </select>
                </div>

                @foreach($layout->schema['fields'] ?? [] as $field)
                    @php
                        $key = (string) ($field['key'] ?? '');
                        $label = (string) ($field['label'] ?? $key);
                        $type = (string) ($field['type'] ?? 'text');
                        $fieldId = $key !== '' ? 'f_'.md5($key) : '';
                    @endphp
                    @if($key !== '')
                        <div>
                            <label class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-0.5" for="{{ $fieldId }}">
                                {{ $label }}
                                <span class="block text-stone-400 font-normal text-xs mt-0.5">В PDF это значение подставится вместо поля с ключом «{{ $key }}».</span>
                            </label>
                            @if($type === 'textarea')
                                <textarea id="{{ $fieldId }}" name="values[{{ $key }}]" rows="4" maxlength="20000"
                                          class="app-input min-h-0"></textarea>
                            @elseif($type === 'number')
                                <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="number" step="any"
                                       class="app-input min-h-0"/>
                            @elseif($type === 'date')
                                <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="date"
                                       class="app-input min-h-0"/>
                            @elseif($type === 'table')
                                @include('boiler-chief.request-layouts._fill-field-table', [
                                    'field' => $field,
                                    'measurementMeta' => $measurementMeta ?? [],
                                ])
                            @elseif($type === 'text' && ! empty($field['readonly']))
                                <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="text" readonly
                                       class="app-input min-h-0 bg-stone-50 dark:bg-stone-900/60 text-right font-medium"
                                       value="{{ old('values.'.$key, '') }}" />
                            @elseif($type === 'address')
                                <div class="relative" data-dadata-address-field>
                                    <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="text" maxlength="255"
                                           autocomplete="off"
                                           class="app-input min-h-0"
                                           data-dadata-address-input
                                           data-field-key="{{ $key }}"/>
                                    <input type="hidden" name="values_meta[{{ $key }}]" value="" data-dadata-meta-input/>
                                    <div class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-y-auto rounded-lg border border-stone-200 bg-white shadow-lg dark:border-stone-700 dark:bg-stone-900"
                                         data-dadata-suggestions></div>
                                </div>
                            @else
                                <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="text" maxlength="20000"
                                       class="app-input min-h-0"/>
                            @endif
                            @error('values.'.$key)
                                <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                @endforeach

                @if(! $minimalFillFieldsOnly && $allowApplicationEquipmentInsert)
                    <div class="space-y-2 rounded-xl border border-orange-100/90 bg-orange-50/30 px-4 py-3 dark:border-orange-900/35 dark:bg-orange-950/20">
                        <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Оборудование из заявки</p>
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                <span class="block text-xs text-stone-500 dark:text-stone-400">{{ $singleApplicationSelection ? 'Заявка' : 'Заявки (можно несколько или все)' }}</span>
                                <div class="flex flex-wrap gap-2">
                                    @unless($singleApplicationSelection)
                                        <button type="button" id="report-select-all-apps" class="text-xs font-medium text-orange-800 hover:underline dark:text-orange-200/90">Все заявки</button>
                                    @endunless
                                    <button type="button" id="report-clear-apps" class="text-xs font-medium text-stone-600 hover:underline dark:text-stone-400">Снять</button>
                                </div>
                            </div>
                            <div id="report-source-applications" class="max-h-48 overflow-y-auto rounded-lg border border-orange-200/80 bg-white px-3 py-2 space-y-1.5 dark:border-orange-900/50 dark:bg-stone-900/40">
                                @unless($singleApplicationSelection)
                                    <label class="flex items-center gap-2 text-xs font-medium text-stone-700 dark:text-stone-200 cursor-pointer border-b border-stone-100 pb-1.5 mb-0.5 dark:border-stone-700">
                                        <input type="checkbox" id="report-source-application-all" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40 dark:border-stone-600 dark:bg-stone-900"/>
                                        <span>Выбрать все заявки</span>
                                    </label>
                                @endunless
                                @foreach($applicationOptions as $app)
                                    <label class="flex items-center gap-2 text-sm text-stone-800 dark:text-stone-100 cursor-pointer">
                                        @if($singleApplicationSelection)
                                            <input type="radio" name="report_application_id" class="report-app-radio border-stone-300 text-orange-600 focus:ring-orange-500/40 dark:border-stone-600 dark:bg-stone-900" value="{{ $app['id'] }}"/>
                                        @else
                                            <input type="checkbox" class="report-app-cb rounded border-stone-300 text-orange-600 focus:ring-orange-500/40 dark:border-stone-600 dark:bg-stone-900" value="{{ $app['id'] }}"/>
                                        @endif
                                        <span class="truncate">{{ $app['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">{{ $singleApplicationSelection ? 'Оборудование из заявки' : 'Оборудование из выбранных заявок' }}</label>
                            <select id="report-source-equipment" class="app-select min-h-0" disabled>
                                <option value="">— Выберите оборудование —</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Вставить как</label>
                            <select id="report-insert-format" class="app-select min-h-0">
                                <option value="list">Список</option>
                                <option value="table">Таблица</option>
                            </select>
                            <p class="text-[11px] text-stone-500 dark:text-stone-400 mt-1 leading-relaxed">
                                В режиме «Таблица» в поле вставляется HTML-таблица — так она отображается в PDF. Режим «Список» вставляет обычный текст.
                            </p>
                        </div>
                        <div class="flex sm:justify-end">
                            <button type="button" id="insert-equipment-to-focused-field" class="ui-btn ui-btn--secondary ui-btn--sm w-full justify-center sm:w-auto">
                                Вставить в активное поле
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2 rounded-xl border border-orange-100/90 bg-orange-50/30 px-4 py-3 dark:border-orange-900/35 dark:bg-orange-950/20">
                        <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Остатки по складам</p>
                        <div>
                            <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Выберите склад</label>
                            <select id="report-source-warehouse" class="app-select min-h-0">
                                <option value="">— Выберите склад —</option>
                                @foreach($warehouseOptions as $warehouse)
                                    <option value="{{ $warehouse['id'] }}">{{ $warehouse['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Выберите позицию со склада</label>
                            <select id="report-source-warehouse-equipment" class="app-select min-h-0">
                                <option value="">— Выберите оборудование —</option>
                            </select>
                        </div>
                        <div class="flex sm:justify-end">
                            <button type="button" id="insert-warehouse-balance-to-focused-field" class="ui-btn ui-btn--secondary ui-btn--sm w-full justify-center sm:w-auto">
                                Вставить остатки в активное поле
                            </button>
                        </div>
                    </div>

                    @if(!empty($users ?? null) && $signatureSlotsCount > 0)
                        <div class="space-y-3 rounded-xl border border-orange-100/90 bg-orange-50/30 px-4 py-3 dark:border-orange-900/35 dark:bg-orange-950/20">
                            <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Подписи в отчете</p>
                            @for($slot = 1; $slot <= $signatureSlotsCount; $slot++)
                                @php
                                    $roleId = (int) ($signatureRoles[$slot] ?? $signatureRoles[(string) $slot] ?? 0);
                                    $roleName = $roleId > 0
                                        ? (string) (collect($signerUserOptions)->firstWhere('role_id', $roleId)['role_name'] ?? '')
                                        : '';
                                @endphp
                                <div>
                                    <label class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-1" for="signer_{{ $slot }}_user_id">
                                        Подпись {{ $slot }}@if($roleName !== '') (сотрудник: {{ $roleName }}) @endif
                                    </label>
                                    <select id="signer_{{ $slot }}_user_id" name="signer_{{ $slot }}_user_id" class="app-select min-h-0 js-report-signer-select" data-signer-slot="{{ $slot }}" data-initial-value="{{ old('signer_'.$slot.'_user_id') }}">
                                        <option value="">— Выберите ФИО —</option>
                                    </select>
                                    @error('signer_'.$slot.'_user_id')
                                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endfor
                        </div>
                    @endif

                    <div class="space-y-2 rounded-xl border border-orange-100/90 bg-orange-50/30 px-4 py-3 dark:border-orange-900/35 dark:bg-orange-950/20">
                        <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Дата формирования</p>
                        <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                            <input type="hidden" name="use_current_date" value="0"/>
                            <input type="checkbox" name="use_current_date" value="1" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40" checked/>
                            <span>Использовать текущую дату</span>
                        </label>
                        <div class="pt-1">
                            <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Или укажите дату</label>
                            <input type="date" name="form_document_date" class="app-input min-h-0"/>
                        </div>
                    </div>

                    <div class="space-y-2 rounded-xl border border-orange-100/90 bg-orange-50/30 px-4 py-3 dark:border-orange-900/35 dark:bg-orange-950/20">
                        <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Номер документа</p>
                        <label for="form_document_number" class="block text-xs text-stone-500 dark:text-stone-400">
                            Заполните, если в макете используются подстановки <code class="text-[11px]">{{ '{' }}{{ '{' }}document_number{{ '}' }}{{ '}' }}</code> или <code class="text-[11px]">{{ '{' }}{{ '{' }}report_number{{ '}' }}{{ '}' }}</code>.
                        </label>
                        <input id="form_document_number" type="text" name="form_document_number" maxlength="120" class="app-input min-h-0"/>
                    </div>
                @endif

                @error('values')
                    <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end pt-2">
                    <a href="{{ $cancelRoute ?? route('boiler-chief.request-layouts.index') }}" class="ui-btn ui-btn--secondary w-full sm:w-auto text-center">Отмена</a>
                    <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto">Скачать PDF</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('fill-report-form');
            if (!form) return;
            const minimalFillFieldsOnly = @json($minimalFillFieldsOnly);
            if (minimalFillFieldsOnly) {
                // Нужна только логика DaData + отправка формы; всё остальное не инициализируем.
            }
            const applications = @json($applicationOptions);
            const singleApplicationSelection = @json($singleApplicationSelection ?? false);
            const signerUserOptions = @json($signerUserOptions);
            const signatureRolesBySlot = @json($signatureRoles);
            const FOREMAN_ROLE_ID = 4;
            const BOILER_CHIEF_ROLE_ID = 7;
            const warehouseBalances = @json($warehouseOptions);
            let activeTextField = null;
            const appContainer = document.getElementById('report-source-applications');
            const equipmentSelect = document.getElementById('report-source-equipment');
            const warehouseSelect = document.getElementById('report-source-warehouse');
            const warehouseEquipmentSelect = document.getElementById('report-source-warehouse-equipment');
            const warehouseInsertButton = document.getElementById('insert-warehouse-balance-to-focused-field');
            const formatSelect = document.getElementById('report-insert-format');
            const insertButton = document.getElementById('insert-equipment-to-focused-field');
            const textFields = Array.from(form.querySelectorAll(
                'textarea[name^="values["], input[name^="values["]:not([type="hidden"])'
            ));
            textFields.forEach((el) => {
                el.addEventListener('focus', () => {
                    activeTextField = el;
                });
            });
            const escapeHtmlForPdf = (s) =>
                String(s ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;');
            const stripEquipmentMeta = (row) => {
                if (!row || typeof row !== 'object') {
                    return row;
                }
                const { __sourceAppId, __optionLabel, ...rest } = row;

                return rest;
            };
            const normalizeEquipmentRow = (row) => {
                if (!row || typeof row !== 'object') {
                    return null;
                }
                const name = String(row.name || '').trim();
                const quantity = String(row.quantity || '').trim();
                const line = String(row.line || '').trim() || (name || quantity ? `${name} x ${quantity}`.trim() : '');
                if (line === '') {
                    return null;
                }
                return { name, quantity, line };
            };
            const getSelectedApplicationIds = () => {
                if (singleApplicationSelection) {
                    const radio = document.querySelector('.report-app-radio:checked');
                    const id = radio ? Number(radio.value) : 0;

                    return id > 0 ? [id] : [];
                }

                return Array.from(document.querySelectorAll('.report-app-cb:checked'))
                    .map((cb) => Number(cb.value))
                    .filter((id) => id > 0);
            };
            const getSelectedApplicationSubdivisionId = () => {
                const ids = getSelectedApplicationIds();
                if (ids.length !== 1) {
                    return 0;
                }
                const app = applications.find((x) => Number(x.id || 0) === ids[0]);

                return app ? Number(app.subdivision_id || 0) : 0;
            };
            const usersForSignerSlotJs = (slot) => {
                const roleId = Number(signatureRolesBySlot[slot] || signatureRolesBySlot[String(slot)] || 0);
                let list = signerUserOptions.filter((u) => !roleId || Number(u.role_id) === roleId);
                const subdivisionId = getSelectedApplicationSubdivisionId();
                if (subdivisionId > 0 && (roleId === FOREMAN_ROLE_ID || roleId === BOILER_CHIEF_ROLE_ID)) {
                    list = list.filter((u) =>
                        (Array.isArray(u.subdivision_ids) ? u.subdivision_ids : []).some(
                            (sid) => Number(sid) === subdivisionId
                        )
                    );
                }

                return list;
            };
            const renderSignerSelects = () => {
                document.querySelectorAll('.js-report-signer-select').forEach((select) => {
                    const slot = select.dataset.signerSlot;
                    if (!slot) {
                        return;
                    }
                    const keep =
                        select.value ||
                        String(select.dataset.initialValue || '').trim();
                    const list = usersForSignerSlotJs(slot);
                    select.innerHTML = '<option value="">— Выберите ФИО —</option>';
                    for (const u of list) {
                        const opt = document.createElement('option');
                        opt.value = String(u.id);
                        opt.textContent = String(u.label || '');
                        select.appendChild(opt);
                    }
                    if (keep !== '' && list.some((u) => String(u.id) === String(keep))) {
                        select.value = String(keep);
                    } else {
                        select.value = '';
                    }
                });
            };
            const syncApplicationMasterCheckbox = () => {
                const master = document.getElementById('report-source-application-all');
                if (!master) {
                    return;
                }
                const cbs = document.querySelectorAll('.report-app-cb');
                const n = cbs.length;
                const checked = Array.from(cbs).filter((cb) => cb.checked).length;
                master.checked = n > 0 && checked === n;
            };
            const buildEquipmentInsertion = (rows, mode) => {
                const hasBreak = Array.isArray(rows) && rows.some((r) => r && r.__sourceAppId != null);
                if (!hasBreak) {
                    const list = rows.map(stripEquipmentMeta).map(normalizeEquipmentRow).filter(Boolean);
                    if (list.length === 0) {
                        return '';
                    }
                    if (mode === 'table') {
                        const bodyRows = list
                            .map(
                                (r) =>
                                    '<tr><td>' +
                                    escapeHtmlForPdf(r.name) +
                                    '</td><td>' +
                                    escapeHtmlForPdf(r.quantity) +
                                    '</td></tr>'
                            )
                            .join('');
                        return (
                            '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">' +
                            '<thead><tr><th>Наименование</th><th>Количество</th></tr></thead>' +
                            '<tbody>' +
                            bodyRows +
                            '</tbody></table>'
                        );
                    }
                    return list.map((r) => '- ' + r.line).join('\n');
                }
                const groups = [];
                let curId = null;
                let bucket = [];
                const flush = () => {
                    if (bucket.length === 0) {
                        return;
                    }
                    groups.push({ appId: curId, rows: bucket.slice() });
                    bucket = [];
                };
                for (const r of rows) {
                    const id = r.__sourceAppId;
                    const n = normalizeEquipmentRow(stripEquipmentMeta(r));
                    if (!n) {
                        continue;
                    }
                    if (id !== curId) {
                        flush();
                        curId = id;
                    }
                    bucket.push(n);
                }
                flush();
                if (groups.length === 0) {
                    return '';
                }
                if (mode === 'table') {
                    return groups
                        .map((g) => {
                            const head = 'Заявка №' + String(g.appId) + '\n';
                            const bodyRows = g.rows
                                .map(
                                    (r) =>
                                        '<tr><td>' +
                                        escapeHtmlForPdf(r.name) +
                                        '</td><td>' +
                                        escapeHtmlForPdf(r.quantity) +
                                        '</td></tr>'
                                )
                                .join('');
                            return (
                                head +
                                '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">' +
                                '<thead><tr><th>Наименование</th><th>Количество</th></tr></thead><tbody>' +
                                bodyRows +
                                '</tbody></table>'
                            );
                        })
                        .join('\n\n');
                }
                return groups
                    .map((g) => 'Заявка №' + String(g.appId) + '\n' + g.rows.map((r) => '- ' + r.line).join('\n'))
                    .join('\n\n');
            };
            const renderEquipmentOptions = () => {
                if (!equipmentSelect) {
                    return;
                }
                const ids = getSelectedApplicationIds();
                equipmentSelect.innerHTML = '<option value="">— Выберите оборудование —</option>';
                equipmentSelect.disabled = ids.length === 0;
                if (ids.length === 0) {
                    return;
                }
                const multi = ids.length > 1;
                const optAll = document.createElement('option');
                optAll.value = '__ALL__';
                optAll.textContent = multi ? 'Все позиции выбранных заявок' : 'Все позиции заявки';
                equipmentSelect.appendChild(optAll);
                for (const appId of ids) {
                    const app = applications.find((x) => Number(x.id || 0) === appId);
                    if (!app || !Array.isArray(app.equipment)) {
                        continue;
                    }
                    app.equipment.forEach((row) => {
                        const line = String(row?.line || '').trim();
                        if (line === '') {
                            return;
                        }
                        const payload = {
                            name: String(row?.name || ''),
                            quantity: String(row?.quantity || ''),
                            line,
                        };
                        if (multi) {
                            payload.__sourceAppId = appId;
                            payload.__optionLabel = '#' + String(appId) + ' ' + line;
                        }
                        const option = document.createElement('option');
                        option.value = JSON.stringify(payload);
                        option.textContent = multi ? payload.__optionLabel : line;
                        equipmentSelect.appendChild(option);
                    });
                }
            };
            const renderWarehouseEquipmentOptions = () => {
                if (!warehouseEquipmentSelect || !warehouseSelect) return;
                const warehouseId = Number(warehouseSelect.value || 0);
                const warehouse = warehouseBalances.find((x) => Number(x.id || 0) === warehouseId);
                warehouseEquipmentSelect.innerHTML = '<option value="">— Выберите оборудование —</option>';
                if (!warehouse || !Array.isArray(warehouse.equipment)) {
                    return;
                }
                const optAll = document.createElement('option');
                optAll.value = '__ALL__';
                optAll.textContent = 'Все остатки склада';
                warehouseEquipmentSelect.appendChild(optAll);
                warehouse.equipment.forEach((row) => {
                    const option = document.createElement('option');
                    const payload = {
                        name: String(row?.name || ''),
                        quantity: String(row?.quantity || ''),
                        line: String(row?.line || ''),
                    };
                    option.value = JSON.stringify(payload);
                    option.textContent = payload.line;
                    warehouseEquipmentSelect.appendChild(option);
                });
            };
            if (!minimalFillFieldsOnly && appContainer) {
                appContainer.addEventListener('change', (e) => {
                    const t = e.target;
                    if (!singleApplicationSelection && t && t.id === 'report-source-application-all') {
                        const on = Boolean(t.checked);
                        appContainer.querySelectorAll('.report-app-cb').forEach((cb) => {
                            cb.checked = on;
                        });
                    } else if (!singleApplicationSelection && t && t.classList && t.classList.contains('report-app-cb')) {
                        syncApplicationMasterCheckbox();
                    }
                    renderEquipmentOptions();
                    renderSignerSelects();
                });
            }
            if (!minimalFillFieldsOnly && signerUserOptions.length > 0) {
                renderSignerSelects();
            }
            if (!minimalFillFieldsOnly && !singleApplicationSelection) {
                document.getElementById('report-select-all-apps')?.addEventListener('click', () => {
                    appContainer?.querySelectorAll('.report-app-cb').forEach((cb) => {
                        cb.checked = true;
                    });
                    syncApplicationMasterCheckbox();
                    renderEquipmentOptions();
                    renderSignerSelects();
                });
            }
            if (!minimalFillFieldsOnly) {
                document.getElementById('report-clear-apps')?.addEventListener('click', () => {
                    if (singleApplicationSelection) {
                        appContainer?.querySelectorAll('.report-app-radio').forEach((radio) => {
                            radio.checked = false;
                        });
                    } else {
                        appContainer?.querySelectorAll('.report-app-cb').forEach((cb) => {
                            cb.checked = false;
                        });
                        syncApplicationMasterCheckbox();
                    }
                    renderEquipmentOptions();
                    renderSignerSelects();
                });
            }
            if (!minimalFillFieldsOnly && warehouseSelect) {
                warehouseSelect.addEventListener('change', renderWarehouseEquipmentOptions);
            }
            if (!minimalFillFieldsOnly && insertButton) {
                insertButton.addEventListener('click', () => {
                    if (!activeTextField) {
                        window.alert('Сначала кликните в поле текста, куда нужно вставить оборудование.');
                        return;
                    }
                    const raw = String(equipmentSelect?.value || '').trim();
                    if (!raw) {
                        window.alert('Сначала выберите оборудование.');
                        return;
                    }
                    const selectedIds = getSelectedApplicationIds();
                    let rows = [];
                    if (raw === '__ALL__') {
                        if (selectedIds.length === 0) {
                            window.alert('Отметьте одну или несколько заявок (или «Все заявки»).');
                            return;
                        }
                        const multi = selectedIds.length > 1;
                        for (const appId of selectedIds) {
                            const app = applications.find((x) => Number(x.id || 0) === appId);
                            if (!app || !Array.isArray(app.equipment)) {
                                continue;
                            }
                            for (const row of app.equipment) {
                                const line = String(row?.line || '').trim();
                                if (line === '') {
                                    continue;
                                }
                                const payload = {
                                    name: String(row?.name || ''),
                                    quantity: String(row?.quantity || ''),
                                    line,
                                };
                                if (multi) {
                                    payload.__sourceAppId = appId;
                                }
                                rows.push(payload);
                            }
                        }
                        if (rows.length === 0) {
                            window.alert('В выбранных заявках нет строк оборудования.');
                            return;
                        }
                    } else {
                        let selected = null;
                        try {
                            selected = JSON.parse(raw);
                        } catch (_) {
                            selected = null;
                        }
                        const one = normalizeEquipmentRow(stripEquipmentMeta(selected));
                        if (!one) {
                            window.alert('Не удалось прочитать выбранную позицию.');
                            return;
                        }
                        rows = [selected];
                    }
                    const mode = String(formatSelect?.value || 'list') === 'table' ? 'table' : 'list';
                    const insertedText = buildEquipmentInsertion(rows, mode);
                    if (!insertedText) {
                        window.alert('Не удалось сформировать текст для вставки.');
                        return;
                    }
                    const current = String(activeTextField.value || '');
                    const suffix = current.trim() === '' ? insertedText : '\n' + insertedText;
                    activeTextField.value = current + suffix;
                    activeTextField.dispatchEvent(new Event('input', { bubbles: true }));
                    activeTextField.focus();
                });
            }
            if (!minimalFillFieldsOnly && warehouseInsertButton) {
                warehouseInsertButton.addEventListener('click', () => {
                    if (!activeTextField) {
                        window.alert('Сначала кликните в поле текста, куда нужно вставить остатки.');
                        return;
                    }
                    const raw = String(warehouseEquipmentSelect?.value || '').trim();
                    if (!raw) {
                        window.alert('Сначала выберите склад и оборудование.');
                        return;
                    }
                    const warehouseId = Number(warehouseSelect?.value || 0);
                    const warehouse = warehouseBalances.find((x) => Number(x.id || 0) === warehouseId);
                    let rows = [];
                    if (raw === '__ALL__') {
                        rows = Array.isArray(warehouse?.equipment) ? warehouse.equipment : [];
                        if (rows.length === 0) {
                            window.alert('По этому складу нет остатков.');
                            return;
                        }
                    } else {
                        let selected = null;
                        try {
                            selected = JSON.parse(raw);
                        } catch (_) {
                            selected = null;
                        }
                        const one = normalizeEquipmentRow(selected);
                        if (!one) {
                            window.alert('Не удалось прочитать выбранную позицию.');
                            return;
                        }
                        rows = [one];
                    }
                    const mode = String(formatSelect?.value || 'list') === 'table' ? 'table' : 'list';
                    const insertedText = buildEquipmentInsertion(rows, mode);
                    if (!insertedText) {
                        window.alert('Не удалось сформировать текст для вставки.');
                        return;
                    }
                    const current = String(activeTextField.value || '');
                    const suffix = current.trim() === '' ? insertedText : '\n' + insertedText;
                    activeTextField.value = current + suffix;
                    activeTextField.dispatchEvent(new Event('input', { bubbles: true }));
                    activeTextField.focus();
                });
            }
            const suggestUrl = form.dataset.dadataSuggestUrl || '';
            const cleanUrl = form.dataset.dadataCleanUrl || '';
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
            const debounceTimers = new WeakMap();

            const addressFields = Array.from(form.querySelectorAll('[data-dadata-address-field]'));
            let openSuggestions = null;
            const skipBlurCleanByInput = new WeakMap();

            const closeSuggestions = () => {
                if (!openSuggestions) return;
                openSuggestions.innerHTML = '';
                openSuggestions.classList.add('hidden');
                openSuggestions = null;
            };

            const dadataPostalCode = (item) => {
                if (!item) {
                    return '';
                }
                if (item.postal_code) {
                    return String(item.postal_code).trim();
                }
                if (item.data?.postal_code) {
                    return String(item.data.postal_code).trim();
                }

                return '';
            };

            const formatAddressInputValue = (item) => {
                const value = item?.value ? String(item.value).trim() : '';
                if (value === '') {
                    return '';
                }
                const postal = dadataPostalCode(item);

                return postal !== '' ? `${postal} ${value}` : value;
            };

            const appendDadataSuggestionButton = (container, item, onSelect) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'block w-full px-3 py-2 text-left text-sm text-stone-800 hover:bg-orange-50 dark:text-stone-100 dark:hover:bg-stone-800';
                const value = item?.value || '';
                const postal = dadataPostalCode(item);
                if (postal) {
                    const wrap = document.createElement('div');
                    const line = document.createElement('span');
                    line.className = 'block leading-snug';
                    line.textContent = value;
                    const postalLine = document.createElement('span');
                    postalLine.className = 'block text-xs text-stone-500 dark:text-stone-400 mt-0.5 tabular-nums';
                    postalLine.textContent = `Индекс ${postal}`;
                    wrap.append(line, postalLine);
                    button.appendChild(wrap);
                } else {
                    button.textContent = value;
                }
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                });
                button.addEventListener('click', () => onSelect(item || {}));
                container.appendChild(button);
            };

            const renderSuggestions = (container, input, metaInput, suggestions) => {
                container.innerHTML = '';
                if (!Array.isArray(suggestions) || suggestions.length === 0) {
                    closeSuggestions();
                    return;
                }

                suggestions.forEach((item) => {
                    appendDadataSuggestionButton(container, item, (selected) => {
                        skipBlurCleanByInput.set(input, true);
                        input.value = formatAddressInputValue(selected) || input.value;
                        metaInput.value = JSON.stringify({
                            ...(selected?.data || {}),
                            postal_code: dadataPostalCode(selected) || selected?.data?.postal_code || null,
                        });
                        closeSuggestions();
                        input.focus();
                    });
                });

                container.classList.remove('hidden');
                openSuggestions = container;
            };

            const fetchSuggestions = async (query, input, metaInput, container) => {
                if (!suggestUrl || query.length < 3) {
                    closeSuggestions();
                    return;
                }
                const url = new URL(suggestUrl, window.location.origin);
                url.searchParams.set('query', query);
                url.searchParams.set('count', '7');

                try {
                    const res = await fetch(url.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        closeSuggestions();
                        return;
                    }
                    const json = await res.json();
                    renderSuggestions(container, input, metaInput, json?.suggestions || []);
                } catch (_) {
                    closeSuggestions();
                }
            };

            const cleanAddress = async (input, metaInput) => {
                const value = String(input.value || '').trim();
                if (!value || !cleanUrl) {
                    return;
                }

                try {
                    const res = await fetch(cleanUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ address: value }),
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        return;
                    }
                    const json = await res.json();
                    const normalized = json?.result || null;
                    if (!normalized || typeof normalized !== 'object') {
                        return;
                    }
                    const line = typeof normalized.result === 'string' ? normalized.result.trim() : '';
                    const postal = normalized.postal_code ? String(normalized.postal_code).trim() : '';
                    if (line !== '') {
                        input.value = postal !== '' ? `${postal} ${line}` : line;
                    }
                    metaInput.value = JSON.stringify(normalized);
                } catch (_) {
                    // ignore transient network issues
                }
            };

            addressFields.forEach((fieldBlock) => {
                const input = fieldBlock.querySelector('[data-dadata-address-input]');
                const metaInput = fieldBlock.querySelector('[data-dadata-meta-input]');
                const suggestions = fieldBlock.querySelector('[data-dadata-suggestions]');
                if (!input || !metaInput || !suggestions) return;

                input.addEventListener('input', () => {
                    closeSuggestions();
                    const existingTimer = debounceTimers.get(input);
                    if (existingTimer) {
                        clearTimeout(existingTimer);
                    }
                    const timerId = window.setTimeout(() => {
                        fetchSuggestions(String(input.value || '').trim(), input, metaInput, suggestions);
                    }, 260);
                    debounceTimers.set(input, timerId);
                });

                input.addEventListener('blur', () => {
                    setTimeout(() => {
                        if (!suggestions.matches(':hover')) {
                            closeSuggestions();
                        }
                        if (skipBlurCleanByInput.get(input)) {
                            skipBlurCleanByInput.delete(input);
                            return;
                        }
                    }, 120);
                });

                input.addEventListener('focus', () => {
                    const value = String(input.value || '').trim();
                    if (value.length >= 3) {
                        fetchSuggestions(value, input, metaInput, suggestions);
                    }
                });
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-dadata-address-field]')) {
                    closeSuggestions();
                }
            });

            const isCommercialProposalLayout = @json(($schema['category'] ?? '') === \App\Support\ReportLayoutCommercialProposal::CATEGORY);
            const syncCommercialEstimateTotalsForFillForm = () => {
                const tableKey = 'таблица_оборудование';
                const parseNum = (raw) => {
                    const s = String(raw ?? '')
                        .replace(/\s+/g, '')
                        .replace(',', '.')
                        .trim();
                    if (s === '') return 0;
                    const n = Number(s);
                    return Number.isFinite(n) && n >= 0 ? n : 0;
                };
                const formatAmount = (n) => {
                    const v = Math.round(Number(n) * 100) / 100;
                    if (!Number.isFinite(v)) return '0';
                    return String(v)
                        .replace(/(\.\d*?[1-9])0+$/u, '$1')
                        .replace(/\.0+$/u, '');
                };
                let total = 0;
                for (let rowIdx = 0; rowIdx < 30; rowIdx += 1) {
                    const qty = form.querySelector(`[name="values[${tableKey}][${rowIdx}][2]"]`);
                    const price = form.querySelector(`[name="values[${tableKey}][${rowIdx}][3]"]`);
                    const sumInput = form.querySelector(`[name="values[${tableKey}][${rowIdx}][4]"]`);
                    if (!qty && !price) {
                        break;
                    }
                    const rowSum = parseNum(qty?.value) * parseNum(price?.value);
                    if (sumInput) {
                        sumInput.value = formatAmount(rowSum);
                    }
                    total += rowSum;
                }
                const formatted = formatAmount(total);
                for (const totalKey of ['итого_оборудование', 'итого_вся_смета']) {
                    const el = form.querySelector(`[name="values[${totalKey}]"]`);
                    if (el) {
                        el.value = formatted;
                    }
                }
            };

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                if (isCommercialProposalLayout) {
                    syncCommercialEstimateTotalsForFillForm();
                }
                for (const fieldBlock of addressFields) {
                    const input = fieldBlock.querySelector('[data-dadata-address-input]');
                    const metaInput = fieldBlock.querySelector('[data-dadata-meta-input]');
                    if (input && metaInput) {
                        await cleanAddress(input, metaInput);
                    }
                }
                const fd = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json, application/pdf',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: fd,
                    credentials: 'same-origin',
                });
                const ct = (res.headers.get('content-type') || '').toLowerCase();
                if (res.status === 422) {
                    window.alert('Проверьте заполнение полей.');
                    window.location.reload();
                    return;
                }
                if (res.ok && ct.includes('application/pdf')) {
                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    window.location.assign(url);
                    return;
                }
                window.alert('Не удалось сформировать PDF. Обновите страницу или войдите снова.');
            });
        })();
    </script>
</x-app-layout>
