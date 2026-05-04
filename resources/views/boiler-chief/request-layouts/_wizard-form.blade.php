@php
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
                'type' => in_array($t, ['text', 'number', 'textarea'], true) ? $t : 'text',
            ];
        })->all();
    } elseif ($layout) {
        $initialFields = collect($schema['fields'] ?? [])->values()->map(function ($row, $i) {
            $row = is_array($row) ? $row : [];
            $t = (string) ($row['type'] ?? 'text');

            return [
                'uid' => 'db_'.$i,
                'key' => (string) ($row['key'] ?? ''),
                'type' => in_array($t, ['text', 'number', 'textarea'], true) ? $t : 'text',
            ];
        })->all();
    } else {
        $initialFields = [
            ['uid' => 'f1', 'key' => '', 'type' => 'text'],
        ];
    }

    $initialBody = old('body_template', $schema['body_template'] ?? "На плановую/внеплановую проверку было представлено:\n\n");
    $initialDocTitle = old('document_title', $schema['document_title'] ?? 'ЗАЯВКА');
    $initialHeading = old('heading_template', $schema['heading_template'] ?? '');
    $initialHeader = old('header_template', $schema['header_template'] ?? '');
    $pdfBodyAlign = old('pdf_body_align', $schema['pdf_body_align'] ?? 'center');
    $initialFooterStamp = old('footer_stamp', ($schema['footer_stamp'] ?? true) ? '1' : '0');
    $initialFooterStampBool = filter_var($initialFooterStamp, FILTER_VALIDATE_BOOLEAN);
    $initialPresHeadingPt = (int) old('presentation_heading_size_pt', $schema['presentation_heading_size_pt'] ?? 18);
    $initialPresSubtitlePt = (int) old('presentation_subtitle_size_pt', $schema['presentation_subtitle_size_pt'] ?? 12);
    $needsStatementHeader = old('needs_statement_header', ($schema['needs_statement_header'] ?? false) || ($layout?->document_header_layout_id ? true : false));
    $needsStatementHeader = filter_var($needsStatementHeader, FILTER_VALIDATE_BOOLEAN);
    $signatureSlotsCount = \App\Models\RequestLayout::resolvedSignatureSlotsCount($schema);
    if (old('signature_slots_count') !== null && old('signature_slots_count') !== '') {
        $signatureSlotsCount = max(0, min(3, (int) old('signature_slots_count')));
    }
    $rawSignatureRoles = old('signature_roles', $schema['signature_roles'] ?? []);
    $initialSignatureRoles = [];
    foreach ([1, 2, 3] as $slot) {
        $initialSignatureRoles[$slot] = (string) ($rawSignatureRoles[$slot] ?? $rawSignatureRoles[(string) $slot] ?? '');
    }
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
        documentHeaderLayoutId: @js((string) (old('document_header_layout_id', $layout?->document_header_layout_id ?? '') ?: '')),
        presentationHeadingSizePt: {{ $initialPresHeadingPt }},
        presentationSubtitleSizePt: {{ $initialPresSubtitlePt }},
        signatureSlotsCount: {{ $signatureSlotsCount }},
        signatureRoles: {{ Js::from($initialSignatureRoles) }},
        footerStamp: @js($initialFooterStampBool),
        pdfBodyAlign: @js($pdfBodyAlign),
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
            if (this.fields.length === 0) this.addField();
            this.ensureSelectedTokenField();
        },
        execCmd(cmd, arg = null) {
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

        @if ($errors->any())
            <div class="mx-5 mt-5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/30 px-4 py-3 text-sm text-rose-900 dark:text-rose-100" role="alert">
                <p class="font-medium mb-1">Исправьте ошибки:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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
                <span>Нужна шапка заявления (выберите макет шапки в форме ниже)</span>
            </label>

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
                        <p class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Роли подписантов</p>
                        <div class="space-y-2 rounded-lg border border-stone-200 dark:border-stone-700 p-2.5">
                            <template x-for="slot in signatureSlotIndices()" :key="'slot_role_' + slot">
                                <div>
                                    <label class="block text-[11px] text-stone-600 dark:text-stone-300 mb-1" x-text="'Подпись ' + slot + ' — роль'"></label>
                                    <select class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm"
                                            :name="'signature_roles[' + slot + ']'" x-model="signatureRoles[slot]">
                                        <option value="">— выберите роль —</option>
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
                <p class="text-[11px] text-stone-500 dark:text-stone-400 leading-relaxed">
                    Количество подписей и роли задаются ниже; при генерации PDF подставляются выбранные ФИО и системные поля макета.
                </p>
            </div>

            <div class="space-y-3 rounded-2xl border border-orange-200/80 bg-orange-50/40 p-4 dark:border-orange-900/40 dark:bg-orange-950/25" x-show="needsStatementHeader" x-cloak>
                <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Шапка документа</h3>
                <p class="text-xs text-stone-600 dark:text-stone-300 leading-relaxed">
                    Текст шапки настраивается в разделе <a href="{{ route('boiler-chief.document-header-layouts.index') }}" class="font-medium text-orange-800 underline dark:text-orange-200/90">Макеты шапок</a>.
                    Здесь вы только выбираете готовый макет шапки для этого PDF-макета.
                </p>
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
                <a href="{{ route('boiler-chief.document-header-layouts.create') }}" target="_blank" rel="noopener noreferrer"
                   class="text-sm font-medium text-orange-800 hover:underline dark:text-orange-200/90">Создать новый макет шапки (откроется в новой вкладке)</a>
                @error('document_header_layout_id')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror
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
                Текст заявки
            </button>
        </div>

        <div class="px-5 sm:px-8 py-6 space-y-4" x-show="tab === 'fields'" x-cloak>
            <div class="flex justify-end">
                <button type="button" @click="addField()" class="ui-btn ui-btn--primary inline-flex items-center">
                    + Добавить поле
                </button>
            </div>
            <template x-for="(field, index) in fields" :key="field.uid">
                <div class="rounded-2xl border border-stone-200 dark:border-stone-700 p-4 space-y-3 bg-white dark:bg-stone-950/40">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-stone-900 dark:text-white" x-text="'Поле ' + (index + 1)"></span>
                        <button type="button" class="text-stone-400 hover:text-rose-600 p-1" @click="removeField(index)" title="Удалить">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Название</label>
                            <input type="text" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm"
                                   :name="'fields['+index+'][key]'" x-model="field.key" required maxlength="64" />
                            <p class="mt-1 text-[11px] text-stone-500 dark:text-stone-400">В тексте заявки вставляйте поле тем же именем в двойных фигурных скобках. Допустимы буквы, цифры, пробелы и «_»; ключ может начинаться с буквы, цифры или «_».</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Тип</label>
                            <select class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm"
                                    :name="'fields['+index+'][type]'" x-model="field.type">
                                <option value="text">Текст</option>
                                <option value="number">Число</option>
                                <option value="textarea">Многострочный текст</option>
                            </select>
                        </div>
                    </div>
                </div>
            </template>
            @error('fields')
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="px-5 sm:px-8 py-6 space-y-4" x-show="tab === 'text'" x-cloak>
           
            <div class="flex flex-wrap gap-1.5 rounded-xl border border-stone-200 dark:border-stone-600 bg-stone-50 dark:bg-stone-900/50 p-2">
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs font-semibold dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('bold')">B</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs italic dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('italic')">I</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs underline dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('underline')">U</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs line-through dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('strikeThrough')">S</button>
                <span class="w-px h-6 bg-stone-200 dark:bg-stone-600 self-center"></span>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('insertUnorderedList')">• Список</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('insertOrderedList')">1. Список</button>
                <span class="w-px h-6 bg-stone-200 dark:bg-stone-600 self-center"></span>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('justifyLeft')">Слева</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('justifyCenter')">По центру</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('justifyRight')">Справа</button>
                <button type="button" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs dark:border-stone-600 dark:bg-stone-800" @click.prevent="execCmd('insertHorizontalRule')">— Линия</button>
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
                            <option :value="field.key" x-text="field.key"></option>
                        </template>
                    </select>
                    <button type="button" class="ui-btn ui-btn--primary ui-btn--sm" @click="insertToken(selectedTokenField, 'body')">Вставить в текст</button>
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
