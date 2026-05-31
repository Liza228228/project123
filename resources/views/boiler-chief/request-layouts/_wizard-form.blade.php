@php
    // шаблон страницы
    use Illuminate\Support\Js;

    $schema = $layout?->schema ?? [];
    $flags = is_array($schema['flags'] ?? null) ? $schema['flags'] : [];

    if (old('fields')) {
        $initialFields = collect(old('fields'))->values()->map(function ($row, $i) {
            $row = is_array($row) ? $row : [];
            $t = (string) ($row['type'] ?? 'text');

            return [
                'uid' => 'old_'.$i,
                'key' => (string) ($row['key'] ?? ''),
                'type' => in_array($t, ['text', 'number', 'textarea', 'date', 'table'], true) ? $t : 'text',
                'label' => (string) ($row['label'] ?? $row['key'] ?? ''),
                'table_columns' => is_array($row['table_columns'] ?? null) ? array_values($row['table_columns']) : ['Столбец 1', 'Столбец 2'],
            ];
        })->all();
    } elseif ($layout) {
        $initialFields = collect($schema['fields'] ?? [])->values()->map(function ($row, $i) {
            $row = is_array($row) ? $row : [];
            $t = (string) ($row['type'] ?? 'text');

            return [
                'uid' => 'db_'.$i,
                'key' => (string) ($row['key'] ?? ''),
                'type' => in_array($t, ['text', 'number', 'textarea', 'date', 'table'], true) ? $t : 'text',
                'label' => (string) ($row['label'] ?? $row['key'] ?? ''),
                'table_columns' => is_array($row['table_columns'] ?? null) ? array_values($row['table_columns']) : ['Столбец 1', 'Столбец 2'],
            ];
        })->all();
    } else {
        $initialFields = [];
    }

    $initialBody = old('body_template', $schema['body_template'] ?? "");
    $initialDocTitle = old('document_title', $schema['document_title'] ?? '');
    $initialHeading = old('heading_template', $schema['heading_template'] ?? '');
    $initialHeader = old('header_template', $schema['header_template'] ?? '');
    $pdfBodyAlign = old('pdf_body_align', $schema['pdf_body_align'] ?? 'center');
    $initialFooterStamp = old('footer_stamp', ($schema['footer_stamp'] ?? true) ? '1' : '0');
    $initialFooterStampBool = filter_var($initialFooterStamp, FILTER_VALIDATE_BOOLEAN);
    $initialPresHeadingPt = (int) old('presentation_heading_size_pt', $schema['presentation_heading_size_pt'] ?? 18);
    $initialPresSubtitlePt = (int) old('presentation_subtitle_size_pt', $schema['presentation_subtitle_size_pt'] ?? 12);
    $returnDocumentHeaderLayoutId = (int) request('document_header_layout_id', 0);
    $needsStatementHeader = old('needs_statement_header', ($schema['needs_statement_header'] ?? false) || ($layout?->document_header_layout_id ? true : false) || $returnDocumentHeaderLayoutId > 0);
    $needsStatementHeader = filter_var($needsStatementHeader, FILTER_VALIDATE_BOOLEAN);
    $selectedDocumentHeaderLayoutId = (string) (old('document_header_layout_id', $layout?->document_header_layout_id ?? '') ?: ($returnDocumentHeaderLayoutId > 0 ? $returnDocumentHeaderLayoutId : ''));
    $signatureSlotsCount = \App\Models\RequestLayout::resolvedSignatureSlotsCount($schema);
    if (old('signature_slots_count') !== null && old('signature_slots_count') !== '') {
        $signatureSlotsCount = max(0, min(3, (int) old('signature_slots_count')));
    }
    $rawSignatureRoles = old('signature_roles', $schema['signature_roles'] ?? []);
    $initialSignatureRoles = [];
    foreach ([1, 2, 3] as $slot) {
        $initialSignatureRoles[$slot] = (string) ($rawSignatureRoles[$slot] ?? $rawSignatureRoles[(string) $slot] ?? '');
    }
    $roleNamesByIdForPreview = [];
    foreach (($roles ?? collect()) as $role) {
        $roleNamesByIdForPreview[(string) $role->id] = (string) $role->name;
    }
    $signatureLineMark = \App\Support\RequestLayoutSignatureLine::mark();
@endphp

<div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35"
     x-init="$watch('fields', () => { ensureSelectedTokenField(); refreshTokenChipLabels(); }, { deep: true }); $nextTick(() => initAllTokenEditors())"
     x-data="Object.assign({}, typeof layoutTokenEditorMixin === 'function' ? layoutTokenEditorMixin() : {}, {
        tab: 'fields',
        fields: {{ Js::from($initialFields) }},
        selectedTokenField: @js($initialFields[0]['key'] ?? ''),
        bodyTemplate: @js($initialBody),
        headingTemplate: @js($initialHeading),
        headerTemplate: @js($initialHeader),
        footerTemplate: '',
        signatureTemplate: '',
        documentTitle: @js($initialDocTitle),
        needsStatementHeader: @js($needsStatementHeader),
        documentHeaderLayoutId: @js($selectedDocumentHeaderLayoutId),
        presentationHeadingSizePt: {{ $initialPresHeadingPt }},
        presentationSubtitleSizePt: {{ $initialPresSubtitlePt }},
        signatureSlotsCount: {{ $signatureSlotsCount }},
        signatureRoles: {{ Js::from($initialSignatureRoles) }},
        footerStamp: @js($initialFooterStampBool),
        pdfBodyAlign: @js($pdfBodyAlign),
        headerLayoutPreviewHtmlById: {{ Js::from($documentHeaderLayoutPreviewHtmlById ?? []) }},
        roleNamesById: {{ Js::from($roleNamesByIdForPreview) }},
        escapePreviewHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');
        },
        tokenLabelByKey(key) {
            const needle = String(key || '').trim();
            if (!needle) return '';
            const hit = this.fields.find((f) => String(f.key || '').trim() === needle);
            if (!hit) return needle;
            const label = String(hit.key || '').trim();
            return label || needle;
        },
        templateToPreviewHtml(template) {
            const raw = String(template ?? '');
            if (raw.trim() === '') {
                return '<span class=\'text-stone-400\'>(пусто)</span>';
            }
            const escaped = this.escapePreviewHtml(raw);
            const withTokens = escaped.replace(/\{\{\s*([^}]+?)\s*\}\}/g, (_m, key) => {
                const token = this.escapePreviewHtml(this.tokenLabelByKey(key));
                return '<span class=\'inline-flex items-center rounded-md border border-orange-300/90 bg-orange-100/90 px-1.5 py-0.5 text-[11px] font-medium text-orange-900 dark:border-orange-800/80 dark:bg-orange-900/35 dark:text-orange-100\'>' + '{' + '{' + token + '}' + '}' + '</span>';
            });
            return withTokens.replace(/\r\n|\r|\n/g, '<br>');
        },
        previewBodyAlignClass() {
            const align = String(this.pdfBodyAlign || 'center');
            if (align === 'left') return 'text-left';
            if (align === 'right') return 'text-right';
            if (align === 'justify') return 'text-justify';
            return 'text-center';
        },
        headerPreviewBlockHtml() {
            const id = String(this.documentHeaderLayoutId || '').trim();
            if (!this.needsStatementHeader || !id) {
                return '';
            }
            const map = this.headerLayoutPreviewHtmlById || {};
            const html = map[id] ?? '';
            return typeof html === 'string' ? html : '';
        },
        signatureRoleLabel(slot) {
            const s = Number(slot);
            const rid = String(this.signatureRoles?.[s] ?? this.signatureRoles?.[String(s)] ?? '').trim();
            if (!rid || !this.roleNamesById) {
                return '— сотрудник не выбран';
            }
            const map = this.roleNamesById;

            return map[rid] ?? map[String(Number(rid))] ?? '—';
        },
        signaturePreviewLine(slot) {
            const label = this.signatureRoleLabel(slot);
            const nb = '\u00A0\u00A0\u00A0';

            return @js($signatureLineMark) + nb + label;
        },
        signatureSlotIndices() {
            const n = Number(this.signatureSlotsCount ?? 0);
            const c = Math.max(0, Math.min(3, Number.isFinite(n) ? n : 0));
            return Array.from({ length: c }, (_, i) => i + 1);
        },
        ensureSelectedTokenField() {
            if (this.fields.length === 0) { this.selectedTokenField = ''; return; }
            const ok = this.fields.some(f => f.key === this.selectedTokenField);
            if (!this.selectedTokenField || !ok) { this.selectedTokenField = this.fields[0].key || ''; }
        },
        addField() {
            const n = Date.now();
            this.fields.push({ uid: 'n_'+n, key: '', type: 'text' });
        },
        removeField(index) {
            this.fields.splice(index, 1);
            this.ensureSelectedTokenField();
        },
        tableModalOpen: false,
        tableEditIndex: null,
        tableDraft: { key: '', table_columns: ['Столбец 1', 'Столбец 2'] },
        openTableModal(editIndex = null) {
            if (editIndex !== null && this.fields[editIndex]) {
                const f = this.fields[editIndex];
                this.tableEditIndex = editIndex;
                this.tableDraft = {
                    key: String(f.key || ''),
                    table_columns: Array.isArray(f.table_columns) && f.table_columns.length
                        ? [...f.table_columns]
                        : ['Столбец 1', 'Столбец 2'],
                };
            } else {
                this.tableEditIndex = null;
                const n = this.fields.filter((x) => x.type === 'table').length + 1;
                this.tableDraft = {
                    key: 'таблица_' + n,
                    table_columns: ['Столбец 1', 'Столбец 2'],
                };
            }
            this.tableModalOpen = true;
        },
        closeTableModal() {
            this.tableModalOpen = false;
            this.tableEditIndex = null;
        },
        addTableColumn() {
            if (this.tableDraft.table_columns.length >= 12) return;
            this.tableDraft.table_columns.push('Столбец ' + (this.tableDraft.table_columns.length + 1));
        },
        removeTableColumn(index) {
            if (this.tableDraft.table_columns.length <= 1) return;
            this.tableDraft.table_columns.splice(index, 1);
        },
        saveTableField() {
            const key = String(this.tableDraft.key || '').trim();
            if (!key) {
                window.alert('Укажите ключ таблицы (название для подстановки в текст).');
                return;
            }
            const cols = this.tableDraft.table_columns.map((c) => String(c || '').trim()).filter((c) => c !== '');
            if (cols.length === 0) {
                window.alert('Добавьте хотя бы один столбец с подписью.');
                return;
            }
            const payload = {
                uid: 'tbl_' + Date.now(),
                key,
                label: key,
                type: 'table',
                table_columns: cols,
            };
            if (this.tableEditIndex !== null) {
                payload.uid = this.fields[this.tableEditIndex].uid;
                this.fields.splice(this.tableEditIndex, 1, payload);
            } else {
                this.fields.push(payload);
            }
            this.ensureSelectedTokenField();
            this.selectedTokenField = key;
            this.closeTableModal();
        },
        execCmd(cmd, arg = null) {
            if (cmd === 'justifyLeft') {
                this.pdfBodyAlign = 'left';
            } else if (cmd === 'justifyCenter') {
                this.pdfBodyAlign = 'center';
            } else if (cmd === 'justifyRight') {
                this.pdfBodyAlign = 'right';
            }
            const el = this.$refs.bodyEditor;
            if (el) { el.focus(); }
            try { document.execCommand(cmd, false, arg); } catch (e) {}
            this.syncTargetTemplate('body');
        },
     })">
    <form method="POST" action="{{ $action }}" class="space-y-0" novalidate @submit="syncAllTokenEditors()">
        @csrf
        @if (strtoupper($httpMethod ?? 'POST') === 'PUT')
            @method('PUT')
        @endif

        <input type="hidden" name="executor_mode" value="user"/>
        <input type="hidden" name="executor_user_id" value="{{ auth()->id() }}"/>
        <input type="hidden" name="needs_coordinator" value="0"/>
        <input type="hidden" name="requires_print" value="0"/>
        <input type="hidden" name="category" value=""/>
        <input type="hidden" name="layout_type" value="pdf"/>
        <input type="hidden" name="layout_version" value="{{ old('layout_version', $layout?->version ?? 1) }}"/>
        <input type="hidden" name="pdf_body_align" :value="pdfBodyAlign"/>
        <input type="hidden" name="header_template" x-bind:value="documentHeaderLayoutId ? '' : headerTemplate"/>
        <input type="hidden" name="heading_template" x-bind:value="headingTemplate"/>
        <input type="hidden" name="body_template" x-bind:value="bodyTemplate"/>
        <input type="hidden" name="needs_statement_header" :value="needsStatementHeader ? 1 : 0"/>
        <input type="hidden" name="footer_stamp" :value="footerStamp ? 1 : 0"/>
        <input type="hidden" name="pdf_footer_preset" value="{{ old('pdf_footer_preset', (string) ($schema['pdf_footer_preset'] ?? 'one_signer_author')) }}"/>

        <x-validation-errors class="mx-5 mt-5" />

        <div class="space-y-5 border-b border-orange-100/90 px-5 pb-4 pt-6 sm:px-8 dark:border-orange-900/40">
            <div>
                <label for="wizard_title" class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Название макета (внутреннее)</label>
                <input id="wizard_title" name="title" type="text" required maxlength="255"
                       class="app-input bg-orange-50/40 dark:bg-orange-950/20"
                       value="{{ old('title', $layout->title ?? '') }}"
                       placeholder="Например: АКТ_контроля"/>
                @error('title')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="inline-flex items-start gap-3 cursor-pointer text-sm text-stone-800 dark:text-stone-100">
                <input type="checkbox" class="mt-1 rounded border-stone-300 text-orange-600 focus:ring-orange-500/40"
                       x-model="needsStatementHeader" @checked($needsStatementHeader)/>
                <span>Нужна шапка отчета </span>
            </label>

            <div class="space-y-3 rounded-2xl border border-orange-200/80 bg-orange-50/40 p-4 dark:border-orange-900/40 dark:bg-orange-950/25" x-show="needsStatementHeader" x-cloak>
                <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Шапка отчета</h3>
                
                <div>
                    <label for="document_header_layout_id" class="block text-xs font-medium text-stone-700 dark:text-stone-200 mb-1">Макет шапки</label>
                    <select id="document_header_layout_id" name="document_header_layout_id" x-model="documentHeaderLayoutId"
                            class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm">
                        <option value="">— Не выбрано —</option>
                        @foreach(($documentHeaderLayouts ?? collect()) as $h)
                            <option value="{{ $h->id }}">{{ $h->title }}</option>
                        @endforeach
                    </select>
                </div>
                <a href="{{ route('boiler-chief.document-header-layouts.create', ['return' => url()->current()]) }}"
                   class="text-sm font-medium text-orange-800 hover:underline dark:text-orange-200/90">Создать новый макет шапки</a>
                @error('document_header_layout_id')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-4 rounded-2xl border border-orange-100/90 bg-orange-50/25 p-4 sm:p-5 dark:border-orange-900/35 dark:bg-orange-950/15">
                <p class="text-xs font-semibold uppercase tracking-wider text-stone-500 dark:text-stone-400">Оформление документа</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="document_title" class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Заголовок в документе</label>
                        <input id="document_title" name="document_title" type="text" maxlength="255" x-model="documentTitle"
                               class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm"
                               placeholder="Например: АКТ"/>
                    </div>
                    <div>
                        <label for="presentation_heading_size_pt" class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Размер заголовка (pt)</label>
                        <select id="presentation_heading_size_pt" name="presentation_heading_size_pt" x-model.number="presentationHeadingSizePt"
                                class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm">
                            @foreach(range(10, 28) as $sz)
                                <option value="{{ $sz }}">{{ $sz }} pt</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-1">
                        <label for="heading_sub" class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Подзаголовок</label>
                        <input id="heading_sub" type="text" maxlength="50000" x-model="headingTemplate"
                               class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm"
                               placeholder="Строки под заголовком"/>
                    </div>
                    <div>
                        <label for="presentation_subtitle_size_pt" class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Размер подзаголовка (pt)</label>
                        <select id="presentation_subtitle_size_pt" name="presentation_subtitle_size_pt" x-model.number="presentationSubtitleSizePt"
                                class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm">
                            @foreach(range(8, 18) as $sz)
                                <option value="{{ $sz }}">{{ $sz }} pt</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="signature_slots_count" class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Сколько подписей в отчете</label>
                        <select id="signature_slots_count" name="signature_slots_count" x-model.number="signatureSlotsCount"
                                class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm">
                            <option value="0">Без подписей</option>
                            <option value="1">1 подпись</option>
                            <option value="2">2 подписи</option>
                            <option value="3">3 подписи</option>
                        </select>
                    </div>
                    <div class="sm:col-span-1" x-show="signatureSlotsCount > 0" x-cloak>
                        <p class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Сотрудники для подписи</p>
                        <div class="space-y-2 rounded-lg border border-stone-200 dark:border-stone-700 p-2.5">
                            <template x-for="slot in signatureSlotIndices()" :key="'slot_role_' + slot">
                                <div>
                                    <label class="block text-[11px] text-stone-600 dark:text-stone-300 mb-1" x-text="'Подпись ' + slot + ' — сотрудник'"></label>
                                    <select class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm"
                                            :name="'signature_roles[' + slot + ']'" x-model="signatureRoles[slot]">
                                        <option value="">— выберите сотрудника —</option>
                                        @foreach(($roles ?? collect()) as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-stone-700 dark:text-stone-200 cursor-pointer">
                    <input type="checkbox" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40" x-model="footerStamp"/>
                    <span>Резерв места под печать (М.П.)</span>
                </label>
                
            </div>

        </div>

        <div class="flex flex-wrap gap-2 border-b border-orange-100/90 px-5 py-4 sm:px-8 dark:border-orange-900/40">
            <button type="button" @click="tab = 'fields'"
                    :class="tab === 'fields' ? 'bg-orange-600 text-black dark:bg-orange-600 dark:text-black' : 'bg-stone-100 text-stone-800 dark:bg-stone-800 dark:text-stone-200'"
                    class="flex-1 sm:flex-none min-w-[140px] rounded-xl px-4 py-3 text-sm font-medium transition-colors">
                Поля заявки
            </button>
            <button type="button" @click="tab = 'text'"
                    :class="tab === 'text' ? 'bg-orange-600 text-black dark:bg-orange-600 dark:text-black' : 'bg-stone-100 text-stone-800 dark:bg-stone-800 dark:text-stone-200'"
                    class="flex-1 sm:flex-none min-w-[140px] rounded-xl px-4 py-3 text-sm font-medium transition-colors">
                Текст отчёта
            </button>
        </div>

        <div class="px-5 sm:px-8 py-6 space-y-4" x-show="tab === 'fields'" x-cloak>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" @click="openTableModal()" class="ui-btn ui-btn--secondary inline-flex items-center">
                    + Добавить таблицу
                </button>
                <button type="button" @click="addField()" class="ui-btn ui-btn--primary inline-flex items-center">
                    + Добавить поле
                </button>
            </div>
            <template x-for="(field, index) in fields" :key="field.uid">
                <div class="rounded-2xl border border-stone-200 dark:border-stone-700 p-4 space-y-3 bg-white dark:bg-stone-950/40"
                     :class="field.type === 'table' ? 'border-orange-300/80 dark:border-orange-800/50' : ''">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-stone-900 dark:text-white"
                              x-text="field.type === 'table' ? ('Таблица ' + (index + 1)) : ('Поле ' + (index + 1))"></span>
                        <div class="flex items-center gap-1">
                            <template x-if="field.type === 'table'">
                                <button type="button" class="text-xs font-medium text-orange-800 hover:underline dark:text-orange-200/90 px-2"
                                        @click="openTableModal(index)">Изменить</button>
                            </template>
                            <button type="button" class="text-stone-400 hover:text-rose-600 p-1" @click="removeField(index)" title="Удалить">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    <template x-if="field.type === 'table'">
                        <div class="space-y-2">
                            <input type="hidden" :name="'fields['+index+'][type]'" value="table" />
                            <input type="hidden" :name="'fields['+index+'][key]'" :value="field.key" />
                            <input type="hidden" :name="'fields['+index+'][label]'" :value="field.label || field.key" />
                            <template x-for="(col, colIdx) in field.table_columns" :key="field.uid + '_col_' + colIdx">
                                <input type="hidden" :name="'fields['+index+'][table_columns]['+colIdx+']'" :value="col" />
                            </template>
                            
                            <ul class="text-xs text-stone-500 dark:text-stone-400 list-disc list-inside">
                                <template x-for="(col, colIdx) in field.table_columns" :key="'lbl_'+colIdx">
                                    <li x-text="(colIdx + 1) + '. ' + col"></li>
                                </template>
                            </ul>
                                </div>
                    </template>
                    <template x-if="field.type !== 'table'">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Название</label>
                                <input type="text" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm"
                                       :name="'fields['+index+'][key]'" x-model="field.key" required maxlength="64" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Тип</label>
                                <select class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm"
                                        :name="'fields['+index+'][type]'" x-model="field.type">
                                    <option value="text">Текст</option>
                                    <option value="number">Число</option>
                                    <option value="textarea">Многострочный текст</option>
                                    <option value="date">Дата</option>
                                </select>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <p x-show="fields.length === 0" class="text-sm text-stone-500 dark:text-stone-400 text-center py-4">
                Добавьте поле или таблицу — без них макет не сохранится.
            </p>

            <div class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/50"
                 x-show="tableModalOpen"
                 x-cloak
                 @keydown.escape.window="closeTableModal()">
                <div class="absolute inset-0" @click="closeTableModal()"></div>
                <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl border border-orange-200/85 bg-white shadow-2xl dark:border-orange-900/50 dark:bg-stone-950 p-5 space-y-4"
                     @click.stop>
                    <h3 class="text-base font-semibold text-stone-900 dark:text-white"
                        x-text="tableEditIndex !== null ? 'Редактирование таблицы' : 'Новая таблица'"></h3>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Задайте подписи столбцов. Число строк пользователь выберет при заполнении отчёта по макету.</p>
                    <div>
                        <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Ключ (для подстановки в текст)</label>
                        <input type="text" class="app-input w-full" x-model="tableDraft.key" maxlength="64"
                               placeholder="например: сводная_таблица" />
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <label class="text-xs font-medium text-stone-600 dark:text-stone-300">Столбцы (заголовки)</label>
                            <button type="button" class="text-xs font-medium text-orange-800 hover:underline dark:text-orange-200/90"
                                    @click="addTableColumn()" :disabled="tableDraft.table_columns.length >= 12">+ столбец</button>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(col, colIdx) in tableDraft.table_columns" :key="'draft_col_'+colIdx">
                                <div class="flex gap-2 items-center">
                                    <input type="text" class="app-input flex-1 min-h-0" x-model="tableDraft.table_columns[colIdx]"
                                           maxlength="120" :placeholder="'Столбец ' + (colIdx + 1)" />
                                    <button type="button" class="text-stone-400 hover:text-rose-600 shrink-0 p-1"
                                            @click="removeTableColumn(colIdx)" title="Удалить столбец"
                                            :disabled="tableDraft.table_columns.length <= 1">×</button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                        <button type="button" class="ui-btn ui-btn--secondary justify-center" @click="closeTableModal()">Отмена</button>
                        <button type="button" class="ui-btn ui-btn--primary justify-center" @click="saveTableField()">Сохранить таблицу</button>
                    </div>
                </div>
            </div>
            @error('fields')
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="px-5 sm:px-8 py-6 space-y-4" x-show="tab === 'text'" x-cloak>
            <div class="flex flex-wrap gap-1.5 rounded-xl border border-stone-200 dark:border-stone-600 bg-stone-50 dark:bg-stone-900/50 p-2">
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs font-semibold dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('bold')">B</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs italic dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('italic')">I</button>
                <span class="w-px h-6 bg-stone-200 dark:bg-stone-600 self-center"></span>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('justifyLeft')">Слева</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('justifyCenter')">По центру</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('justifyRight')">Справа</button>
            </div>
            <div id="body_template"
                 x-ref="bodyEditor"
                 role="textbox"
                 aria-multiline="true"
                 contenteditable="true"
                 spellcheck="false"
                 class="block w-full min-h-[14rem] max-h-[32rem] overflow-y-auto rounded-xl border border-orange-200/90 bg-white px-3 py-3 text-sm text-stone-900 shadow-sm outline-none ring-inset focus:border-orange-400 focus:ring-2 focus:ring-orange-400/25 dark:border-orange-900/40 dark:bg-stone-900 dark:text-stone-100 whitespace-pre-wrap break-words"
                 @input.debounce.200ms="syncTargetTemplate('body')"
                 @blur="syncTargetTemplate('body')"
                 @paste="onTokenEditorPaste($event)"
                 @keydown="onTokenEditorKeydown($event)"
                 @dragenter.prevent="onTokenEditorDragOver($event)"
                 @dragover.prevent="onTokenEditorDragOver($event)"
                 @drop.prevent="onTokenEditorDrop($event, 'body')"></div>

            <div class="space-y-3 rounded-xl border border-orange-100/90 bg-orange-50/35 p-4 dark:border-orange-900/35 dark:bg-orange-950/20">
                <p class="text-xs font-medium text-stone-800 dark:text-stone-100">Вставить поле</p>
                <p class="text-[11px] text-stone-500 leading-relaxed">Выберите поле и нажмите кнопку — в текст добавится подстановка с тем же ключом, что в списке полей.</p>
                <div class="flex flex-wrap gap-2">
                    <select x-model="selectedTokenField" class="flex-1 min-w-[12rem] rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 text-sm">
                        <template x-for="field in fields" :key="'opt_'+field.uid">
                            <option :value="field.key" x-text="(field.label && field.label !== field.key ? field.label + ' (' + field.key + ')' : field.key) + (field.type === 'table' ? ' — таблица' : '')"></option>
                        </template>
                    </select>
                    <button type="button" class="ui-btn ui-btn--primary ui-btn--sm" @click="insertToken(selectedTokenField, 'body')">Вставить в текст</button>
                </div>
            </div>
            <div class="rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900/40 p-4 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-stone-900 dark:text-white">Предпросмотр документа</h4>
                    <span class="text-[11px] text-stone-500 dark:text-stone-400">черновик</span>
                </div>
                <div class="rounded-lg border border-orange-200/80 dark:border-orange-900/40 bg-orange-50/30 dark:bg-orange-950/15 p-4 space-y-3">
                    <template x-if="needsStatementHeader && documentHeaderLayoutId && headerPreviewBlockHtml()">
                        <div class="space-y-2 border-b border-orange-200/70 dark:border-orange-900/35 pb-3 text-stone-900 dark:text-stone-100 [&_div]:text-stone-900 dark:[&_div]:text-stone-100"
                             x-html="headerPreviewBlockHtml()"></div>
                    </template>
                    <div class="text-center">
                        <div class="font-semibold tracking-wide uppercase"
                             :style="'font-size:' + Number(presentationHeadingSizePt || 12) + 'pt;'"
                             x-html="templateToPreviewHtml(documentTitle)"></div>
                    </div>
                    <div class="text-center"
                         :style="'font-size:' + Number(presentationSubtitleSizePt || 10) + 'pt;'"
                         x-html="templateToPreviewHtml(headingTemplate)"></div>
                    <div :class="previewBodyAlignClass()"
                         class="text-sm leading-6 border-t border-orange-200/70 dark:border-orange-900/35 pt-3"
                         x-html="templateToPreviewHtml(bodyTemplate)"></div>
                    <div class="border-t border-orange-200/70 dark:border-orange-900/35 pt-3 mt-3 space-y-2"
                         x-show="Number(signatureSlotsCount) > 0 || footerStamp"
                         x-cloak>
                        <template x-if="Number(signatureSlotsCount) > 0">
                            <div class="space-y-1.5">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400 text-right">Подписи</p>
                                <div class="text-sm text-right leading-relaxed text-stone-800 dark:text-stone-200 space-y-1">
                                    <template x-for="slot in signatureSlotIndices()" :key="'sigprev_' + slot">
                                        <div class="whitespace-nowrap font-sans" x-text="signaturePreviewLine(slot)"></div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <div class="text-right text-xs font-medium text-stone-600 dark:text-stone-400 tracking-wide"
                             x-show="footerStamp"
                             x-cloak
                             :class="Number(signatureSlotsCount) > 0 ? 'pt-1' : ''">М.П.</div>
                    </div>
                </div>
            </div>
            @error('body_template')
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="hidden" aria-hidden="true">
            <div x-ref="headingEditor" contenteditable="true"></div>
            <div x-ref="headerEditor" contenteditable="true"></div>
            <div x-ref="footerEditor" contenteditable="true"></div>
            <div x-ref="signatureEditor" contenteditable="true"></div>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-orange-100/90 bg-orange-50/20 px-5 py-5 sm:flex-row sm:justify-end sm:px-8 dark:border-orange-900/40 dark:bg-orange-950/15">
            <a href="{{ route('boiler-chief.request-layouts.index') }}" class="ui-btn ui-btn--secondary justify-center">Отмена</a>
            <button type="submit" class="ui-btn ui-btn--primary justify-center">
                {{ $submitLabel ?? 'Сохранить' }}
            </button>
        </div>
    </form>
</div>
