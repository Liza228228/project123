@php
    use Illuminate\Support\Js;

    /** Пример подстановки в тексте макета PDF (не синтаксис Blade). */
    $tpl = static fn (string $key): string => '{{'.$key.'}}';

    $users = $users ?? collect();
    $departments = $departments ?? collect();
    $schema = $layout?->schema ?? [];
    $flags = is_array($schema['flags'] ?? null) ? $schema['flags'] : [];

    $defaultHeader = "Заместителю директора\n{{coordinator_name}}\n\n{{representative_prefix}}\n{{representative_name}}";
    $defaultBody = "{{текст}}";
    $defaultFooter = "{{текст}}\n\nДата: {{document_date}}";
    $defaultSignature = "________________ / {{signatory_print_name}}";

    if (old('fields')) {
        $initialFields = collect(old('fields'))->values()->map(function ($row, $i) {
            $row = is_array($row) ? $row : [];

            return [
                'uid' => 'old_'.$i,
                'key' => (string) ($row['key'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'type' => (string) ($row['type'] ?? 'text'),
            ];
        })->all();
    } elseif ($layout) {
        $initialFields = collect($layout->schema['fields'] ?? [])->values()->map(function ($row, $i) {
            return [
                'uid' => 'db_'.$i,
                'key' => (string) ($row['key'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'type' => (string) ($row['type'] ?? 'text'),
            ];
        })->all();
    } else {
        $initialFields = [
            ['uid' => 'seed_fio', 'key' => 'фио', 'label' => 'ФИО', 'type' => 'text'],
            ['uid' => 'seed_tekst', 'key' => 'текст', 'label' => 'Текст', 'type' => 'textarea'],
        ];
    }

    $initialBody = old('body_template', $layout?->schema['body_template'] ?? $defaultBody);
    $initialTitle = old('title', $layout?->title ?? '');
    $initialHeader = old('header_template', $schema['header_template'] ?? $defaultHeader);
    $initialFooter = old('footer_left_template', $schema['footer_left_template'] ?? $defaultFooter);
    $initialSignature = old('signature_template', $schema['signature_template'] ?? $defaultSignature);
    $initialDocTitle = old('document_title', $schema['document_title'] ?? '');
    $initialHeading = old('heading_template', $schema['heading_template'] ?? '');
    $pdfHeaderAlign = old('pdf_header_align', $schema['pdf_header_align'] ?? 'right');
    $pdfBodyAlign = old('pdf_body_align', $schema['pdf_body_align'] ?? 'center');
    $pdfFooterLeftAlign = old('pdf_footer_left_align', $schema['pdf_footer_left_align'] ?? 'left');
    $pdfFooterRightAlign = old('pdf_footer_right_align', $schema['pdf_footer_right_align'] ?? 'right');
    $initialTokenFieldKey = $initialFields[0]['key'] ?? '';
    $initialCategory = old('category', $schema['category'] ?? '');
    $initialExecutorMode = old('executor_mode', $schema['executor_mode'] ?? (($layout?->division_assigner_id) ? 'department' : 'user'));
    $initialExecutorUserId = old('executor_user_id', $schema['executor_user_id'] ?? '');
    $needsCoordinator = old('needs_coordinator', ($flags['needs_coordinator'] ?? null) !== null
        ? (bool) ($flags['needs_coordinator'] ?? false)
        : (bool) ($layout?->has_header ?? false));
    $requiresPrint = old('requires_print', (bool) ($flags['requires_print'] ?? false));
@endphp

<div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35"
     x-init="ensureSelectedTokenField(); $watch('fields', () => { ensureSelectedTokenField(); refreshTokenChipLabels(); }, { deep: true }); $nextTick(() => initAllTokenEditors())"
     x-data="Object.assign({}, typeof layoutTokenEditorMixin === 'function' ? layoutTokenEditorMixin() : {}, {
        tab: 'fields',
        fields: {{ Js::from($initialFields) }},
        selectedTokenField: @js($initialTokenFieldKey),
        ensureSelectedTokenField() {
            if (this.fields.length === 0) {
                this.selectedTokenField = '';
                return;
            }
            const ok = this.fields.some(f => f.key === this.selectedTokenField);
            if (!this.selectedTokenField || !ok) {
                this.selectedTokenField = this.fields[0].key || '';
            }
        },
        bodyTemplate: @js($initialBody),
        headingTemplate: @js($initialHeading),
        headerTemplate: @js($initialHeader),
        footerTemplate: @js($initialFooter),
        signatureTemplate: @js($initialSignature),
        documentTitle: @js($initialDocTitle),
        title: @js($initialTitle),
        documentHeaderLayoutId: @js((string) (old('document_header_layout_id', $layout?->document_header_layout_id ?? '') ?: '')),
        executorMode: @js($initialExecutorMode),
        addField() {
            const n = Date.now();
            this.fields.push({ uid: 'n_'+n, key: 'поле_'+n, label: 'Новое поле', type: 'text' });
        },
        removeField(index) {
            this.fields.splice(index, 1);
            if (this.fields.length === 0) {
                this.addField();
            }
            this.ensureSelectedTokenField();
        },
        moveFieldUp(index) {
            if (index <= 0) {
                return;
            }
            const row = this.fields.splice(index, 1)[0];
            this.fields.splice(index - 1, 0, row);
        },
        moveFieldDown(index) {
            if (index >= this.fields.length - 1) {
                return;
            }
            const row = this.fields.splice(index, 1)[0];
            this.fields.splice(index + 1, 0, row);
        }
     })">
    <div class="flex items-start justify-between gap-3 border-b border-orange-100/90 px-5 pb-3 pt-5 dark:border-orange-900/40">
        <h2 class="text-lg font-semibold text-stone-900 dark:text-white">{{ $modalTitle ?? 'Новый макет' }}</h2>
        <a href="{{ route('boiler-chief.request-layouts.index') }}" class="shrink-0 rounded-lg p-1.5 text-stone-500 hover:bg-stone-100 dark:hover:bg-stone-800" title="Закрыть">
            <span class="sr-only">Закрыть</span>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </a>
    </div>

    <form method="POST" action="{{ $action }}" class="px-5 py-5 space-y-5 max-h-[calc(100vh-8rem)] overflow-y-auto" novalidate @submit="syncAllTokenEditors()">
        @csrf
        @if (strtoupper($httpMethod) === 'PUT')
            @method('PUT')
        @endif

        <x-validation-errors title="Не удалось сохранить. Исправьте ошибки в форме" />

        <div class="rounded-xl border border-rose-200/80 bg-rose-50/70 dark:bg-rose-950/25 dark:border-rose-900/40 px-4 py-3 space-y-2">
            <label for="approver_id" class="block text-sm font-medium text-stone-800 dark:text-stone-100">Утверждающий</label>
            <p class="text-xs text-stone-600 dark:text-stone-300">В тексте макета для ФИО согласующего укажите подстановку <code class="text-[11px]">{{ $tpl('coordinator_name') }}</code> (имя латиницей, вокруг — двойные фигурные скобки).</p>
            <input id="approver_id_filter" type="search" autocomplete="off" placeholder="Начните вводить ФИО или e-mail..."
                   class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm"
                   x-data="{ q: '' }" x-model="q" @input="const s = q.toLowerCase(); document.querySelectorAll('[data-approver-option]').forEach(o => { o.hidden = s !== '' && !o.dataset.approverSearch.includes(s); });"/>
            <select id="approver_id" name="approver_id" size="6" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                <option value="">— не выбран —</option>
                @foreach($users as $u)
                    @php
                        $uLabel = trim($u->surname.' '.$u->name.' '.($u->patronymic ?? '')).' · '.$u->email;
                        $uSearch = mb_strtolower($uLabel.' '.$u->email);
                    @endphp
                    <option value="{{ $u->id }}" data-approver-option data-approver-search="{{ e($uSearch) }}" @selected((string) old('approver_id', $layout?->approver_id) === (string) $u->id)>
                        {{ $uLabel }}
                    </option>
                @endforeach
            </select>
            @error('approver_id')
                <p class="text-sm text-rose-700 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-3 rounded-xl border border-orange-200/80 bg-orange-50/60 px-4 py-3 dark:border-orange-900/40 dark:bg-orange-950/20">
            <div>
                <label class="block text-sm font-medium text-stone-800 dark:text-stone-100">Ответственный исполнитель</label>
                <p class="text-xs text-stone-600 dark:text-stone-300 mt-1">Если в макете есть <code class="text-[11px]">{{ $tpl('representative_prefix') }}</code> и <code class="text-[11px]">{{ $tpl('representative_name') }}</code>, выберите ниже либо сотрудника, либо подразделение (только один вариант).</p>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="executor_mode" value="user" class="border-stone-300 text-orange-600 focus:ring-orange-500/40" x-model="executorMode"/>
                    <span>Сотрудник</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="executor_mode" value="department" class="border-stone-300 text-orange-600 focus:ring-orange-500/40" x-model="executorMode"/>
                    <span>Подразделение</span>
                </label>
            </div>
            <div x-show="executorMode === 'user'" x-cloak>
                <input type="search" autocomplete="off" placeholder="Поиск сотрудника по ФИО или e-mail..."
                       class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm mb-2"
                       x-data="{ q: '' }" x-model="q" @input="const s = q.toLowerCase(); document.querySelectorAll('[data-exec-option]').forEach(o => { o.hidden = s !== '' && !o.dataset.execSearch.includes(s); });"/>
                <select name="executor_user_id" size="6" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                    <option value="">— не выбран —</option>
                    @foreach($users as $u)
                        @php
                            $uLabel = trim($u->surname.' '.$u->name.' '.($u->patronymic ?? '')).' · '.$u->email;
                            $uSearch = mb_strtolower($uLabel.' '.$u->email);
                        @endphp
                        <option value="{{ $u->id }}" data-exec-option data-exec-search="{{ e($uSearch) }}" @selected((string) old('executor_user_id', (string) $initialExecutorUserId) === (string) $u->id)>
                            {{ $uLabel }}
                        </option>
                    @endforeach
                </select>
                @error('executor_user_id')
                    <p class="text-sm text-rose-700 dark:text-rose-300 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div x-show="executorMode === 'department'" x-cloak>
                <label class="block text-xs font-medium text-stone-700 dark:text-stone-200 mb-1">Подразделение (участок)</label>
                <select name="division_assigner_id" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                    <option value="">— не выбрано —</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected((string) old('division_assigner_id', $layout?->division_assigner_id) === (string) $d->id)>
                            {{ $d->name }}
                        </option>
                    @endforeach
                </select>
                @if($departments->isEmpty())
                    <p class="text-xs text-amber-800 dark:text-amber-200/90 mt-1">Справочник пуст: добавьте участки в «Подразделения».</p>
                @endif
                @error('division_assigner_id')
                    <p class="text-sm text-rose-700 dark:text-rose-300 mt-1">{{ $message }}</p>
                @enderror
            </div>
            @error('executor_mode')
                <p class="text-sm text-rose-700 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="title" class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Название макета</label>
                <input id="title" name="title" type="text" required maxlength="255" placeholder="Название"
                       x-model="title"
                       class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm"/>
                @error('title')
                    <p class="mt-1 text-sm text-rose-700 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="category" class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Категория</label>
                <select id="category" name="category" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                    @php $cat = old('category', $initialCategory); @endphp
                    <option value="" @selected($cat === '' || $cat === null)>Без категории (не попадёт в «Новый отчёт»)</option>
                    <option value="installation-act" @selected($cat === 'installation-act')>Акт установки — отчёт по заявке</option>
                    <option value="commercial-proposal" @selected($cat === 'commercial-proposal')>Коммерческое предложение</option>
                    <option value="lab" @selected($cat === 'lab')>Технический контроль</option>
                    <option value="boiler" @selected($cat === 'boiler')>Котельная</option>
                    <option value="safety" @selected($cat === 'safety')>Охрана труда</option>
                </select>
                <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                    Категория «Акт установки» или «Коммерческое предложение» включает особый режим заполнения (заявки, смета). Любой сохранённый макет доступен в «Новый отчёт».
                </p>
            </div>
        </div>

        <div class="space-y-2">
            <p class="text-sm font-medium text-stone-800 dark:text-stone-100">Параметры заявления</p>
            <div class="flex flex-col gap-2 text-sm">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="needs_coordinator" value="0"/>
                    <input type="checkbox" name="needs_coordinator" value="1" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40"
                           @checked($needsCoordinator)/>
                    <span>Нужен согласовывающий</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="requires_print" value="0"/>
                    <input type="checkbox" name="requires_print" value="1" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40"
                           @checked($requiresPrint)/>
                    <span>Обязательно нужна печатная версия заявления</span>
                </label>
            </div>
        </div>

        <input type="hidden" name="layout_type" value="{{ old('layout_type', $layout?->type ?? 'pdf') }}"/>
        <input type="hidden" name="layout_version" value="{{ old('layout_version', $layout?->version ?? 1) }}"/>

        <div class="rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
            <div class="flex overflow-x-auto text-sm font-medium [-webkit-overflow-scrolling:touch]">
                <button type="button" @click="tab = 'fields'"
                        :class="tab === 'fields' ? 'bg-orange-600 text-black dark:text-black' : 'bg-stone-100 dark:bg-stone-900 text-stone-700 dark:text-stone-200'"
                        class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-2.5 transition-colors sm:flex-1">
                    Поля заявки
                </button>
                <button type="button" @click="tab = 'header'"
                        :class="tab === 'header' ? 'bg-orange-600 text-black dark:text-black' : 'bg-stone-100 dark:bg-stone-900 text-stone-700 dark:text-stone-200'"
                        class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-2.5 transition-colors sm:flex-1">
                    Шапка
                </button>
                <button type="button" @click="tab = 'text'"
                        :class="tab === 'text' ? 'bg-orange-600 text-black dark:text-black' : 'bg-stone-100 dark:bg-stone-900 text-stone-700 dark:text-stone-200'"
                        class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-2.5 transition-colors sm:flex-1">
                    Текст заявки
                </button>
                <button type="button" @click="tab = 'footer'"
                        :class="tab === 'footer' ? 'bg-orange-600 text-black dark:text-black' : 'bg-stone-100 dark:bg-stone-900 text-stone-700 dark:text-stone-200'"
                        class="shrink-0 whitespace-nowrap px-3 sm:px-4 py-2.5 transition-colors sm:flex-1">
                    Подписи
                </button>
            </div>

            <div class="p-4 space-y-4 bg-white dark:bg-stone-950" x-show="tab === 'fields'">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Поля макета</h3>
                    <button type="button" @click="addField()" class="text-sm font-medium text-orange-800 hover:underline dark:text-orange-200/90">
                        + Добавить поле
                    </button>
                </div>
                <p class="text-xs text-stone-500 dark:text-stone-400 leading-relaxed">Ключ поля — по-русски или латиницей, без пробелов (например <code class="text-[10px]">номер_договора</code>, <code class="text-[10px]">дата_договора</code>). В шаблоне — те же символы в двойных фигурных скобках, например <code class="text-[10px]">{{ $tpl('номер_договора') }}</code>. Системные имена (<code class="text-[10px]">document_date</code>, <code class="text-[10px]">document_number</code> и др.) — на вкладках «Шапка» и «Подписи», в основном тексте — поля макета. В редакторах текста вставленные подстановки показываются цветными блоками с подписью поля; при наведении на блок в подсказке виден тот же ключ, что уйдёт в PDF. Порядок полей в списке меняется кнопками со стрелками; при сохранении макета этот же порядок используется в формах заполнения.</p>

                <template x-for="(field, index) in fields" :key="field.uid">
                    <div class="rounded-xl border border-stone-200 dark:border-stone-700 p-3 flex flex-wrap items-end gap-3">
                        <div class="flex flex-col gap-0.5 shrink-0 self-end pb-0.5" title="Порядок в списке полей">
                            <button type="button"
                                    class="rounded-md border border-stone-200 bg-stone-50 p-1.5 text-stone-600 hover:bg-stone-100 disabled:opacity-30 disabled:pointer-events-none dark:border-stone-600 dark:bg-stone-800 dark:text-stone-300 dark:hover:bg-stone-700"
                                    :disabled="index === 0"
                                    @click="moveFieldUp(index)"
                                    aria-label="Переместить поле выше">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                            </button>
                            <button type="button"
                                    class="rounded-md border border-stone-200 bg-stone-50 p-1.5 text-stone-600 hover:bg-stone-100 disabled:opacity-30 disabled:pointer-events-none dark:border-stone-600 dark:bg-stone-800 dark:text-stone-300 dark:hover:bg-stone-700"
                                    :disabled="index === fields.length - 1"
                                    @click="moveFieldDown(index)"
                                    aria-label="Переместить поле ниже">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </div>
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Ключ</label>
                            <input type="text" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm"
                                   :name="'fields['+index+'][key]'" x-model="field.key" required maxlength="64"
                                   title="Буквы на русском или латинице, цифры и «_», без пробелов. Проверка при сохранении."/>
                        </div>
                        <div class="flex-[2] min-w-[160px]">
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Подпись</label>
                            <input type="text" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm"
                                   :name="'fields['+index+'][label]'" x-model="field.label" maxlength="255"/>
                        </div>
                        <div class="w-full sm:w-36">
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Тип</label>
                            <select class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm"
                                    :name="'fields['+index+'][type]'" x-model="field.type">
                                <option value="text">Строка</option>
                                <option value="textarea">Многострочный</option>
                                <option value="address">Адрес (DaData)</option>
                                <option value="date">Дата</option>
                            </select>
                        </div>
                        <button type="button" class="p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg shrink-0" title="Удалить" @click="removeField(index)">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </template>
                @error('fields')
                    <p class="text-sm text-rose-700 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="p-4 space-y-4 bg-white dark:bg-stone-950" x-show="tab === 'header'" x-cloak>
                <div>
                    <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Шапка документа</h3>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1 leading-relaxed">Краткое название, центральный заголовок в PDF и верхний служебный блок. Подстановки полей макета — ключ в двойных фигурных скобках, как на вкладке «Поля заявки».</p>
                </div>

                <div class="space-y-3 rounded-xl border border-orange-200/80 bg-orange-50/50 px-4 py-3 dark:border-orange-900/40 dark:bg-orange-950/20">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <label for="document_header_layout_id" class="text-sm font-medium text-stone-800 dark:text-stone-100">Макет шапки (отдельная страница)</label>
                        <a href="{{ route('boiler-chief.document-header-layouts.index') }}" class="shrink-0 text-xs font-medium text-orange-800 hover:underline dark:text-orange-200/90">Макеты шапок документов →</a>
                    </div>
                    <select id="document_header_layout_id" name="document_header_layout_id" x-model="documentHeaderLayoutId"
                            class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                        <option value="">— Не использовать отдельный макет (текст служебного блока ниже) —</option>
                        @foreach(($documentHeaderLayouts ?? collect()) as $h)
                            <option value="{{ $h->id }}">{{ $h->title }}</option>
                        @endforeach
                    </select>
                    @error('document_header_layout_id')
                        <p class="text-sm text-rose-700 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-stone-600 dark:text-stone-400">Создайте и настройте блоки на странице «Макеты шапок документов», затем выберите сохранённый вариант здесь — он подставится в PDF вместо текстового поля ниже.</p>
                </div>

                <div x-show="documentHeaderLayoutId" x-cloak class="rounded-lg border border-amber-200/90 bg-amber-50/70 dark:bg-amber-950/25 dark:border-amber-900/40 px-3 py-2 text-xs text-amber-950 dark:text-amber-100">
                    Активен отдельный макет шапки: поле «Верхний служебный блок» в этой форме не попадает в PDF (оно будет очищено при сохранении).
                </div>

                <div>
                    <label for="document_title" class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Краткое название (PDF и списки)</label>
                    <p class="text-xs text-stone-500 mb-1">Если ниже задан «блок заголовка», он показывается в PDF по центру вместо этой строки как крупного заголовка.</p>
                    <input id="document_title" name="document_title" type="text" maxlength="255" x-model="documentTitle"
                           class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm"/>
                </div>
                <div>
                    <label for="heading_template" class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Блок заголовка в PDF (необязательно, по центру)</label>
                    <p class="text-xs text-stone-500 mb-1">Несколько строк: например полное название акта и строка про договор. Подстановки из полей — в том же виде, что в основном тексте (двойные фигурные скобки вокруг ключа). Блоки полей в редакторе можно перетаскивать внутри текста.</p>
                    <div id="heading_template"
                         x-ref="headingEditor"
                         role="textbox"
                         aria-multiline="true"
                         contenteditable="true"
                         spellcheck="false"
                         class="block w-full min-h-[7.5rem] max-h-[28rem] overflow-y-auto rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-900 px-3 py-2 text-sm text-stone-900 dark:text-stone-100 shadow-sm outline-none ring-inset focus:border-orange-400 focus:ring-2 focus:ring-orange-400/25 whitespace-pre-wrap break-words"
                         @input.debounce.200ms="syncTargetTemplate('heading')"
                         @blur="syncTargetTemplate('heading')"
                         @paste="onTokenEditorPaste($event)"
                         @keydown="onTokenEditorKeydown($event)"
                         @dragenter.prevent="onTokenEditorDragOver($event)"
                         @dragover.prevent="onTokenEditorDragOver($event)"
                         @drop.prevent="onTokenEditorDrop($event, 'heading')"></div>
                    <input type="hidden" name="heading_template" x-bind:value="headingTemplate"/>
                    <div class="mt-2 flex flex-col gap-2 rounded-lg border border-orange-100/90 bg-orange-50/40 px-3 py-2 sm:flex-row sm:flex-wrap sm:items-end dark:border-orange-900/35 dark:bg-orange-950/20">
                        <div class="min-w-0 flex-1 sm:max-w-xs">
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Вставить поле в блок заголовка</label>
                            <select x-model="selectedTokenField" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-xs sm:text-sm">
                                <template x-for="field in fields" :key="'hdr_heading_'+field.uid">
                                    <option :value="field.key" :title="field.key" x-text="(field.label && String(field.label).trim()) ? field.label : (field.key || '…')"></option>
                                </template>
                            </select>
                        </div>
                        <button type="button" class="ui-btn ui-btn--primary ui-btn--sm shrink-0 sm:self-end"
                                @click="insertToken(selectedTokenField, 'heading')">Вставить в блок заголовка</button>
                    </div>
                </div>
                <div class="rounded-xl border border-stone-200 dark:border-stone-700 p-3" x-show="!documentHeaderLayoutId" x-cloak>
                    <label class="block text-xs font-medium text-stone-700 dark:text-stone-200 mb-1">Выравнивание в PDF</label>
                    <label class="block text-xs text-stone-600 dark:text-stone-300 mb-1">Верхний служебный блок (если заполнен)</label>
                    <select name="pdf_header_align" class="block w-full max-w-md rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                        <option value="right" @selected($pdfHeaderAlign === 'right')>Справа</option>
                        <option value="center" @selected($pdfHeaderAlign === 'center')>По центру</option>
                        <option value="left" @selected($pdfHeaderAlign === 'left')>Слева</option>
                    </select>
                </div>
                <div x-show="!documentHeaderLayoutId" x-cloak>
                    <label class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Верхний служебный блок (адресат / реквизиты)</label>
                    <div class="text-xs text-stone-500 mb-2 space-y-1">
                        <p class="font-medium text-stone-600 dark:text-stone-300">Системные подстановки (в шаблоне — двойные фигурные скобки вокруг латинского имени):</p>
                        <p><code class="text-[11px]">{{ $tpl('coordinator_name') }}</code>, <code class="text-[11px]">{{ $tpl('representative_prefix') }}</code>, <code class="text-[11px]">{{ $tpl('representative_name') }}</code>, <code class="text-[11px]">{{ $tpl('subdivision_name') }}</code></p>
                        <p><code class="text-[11px]">{{ $tpl('document_date') }}</code>, <code class="text-[11px]">{{ $tpl('document_number') }}</code>, <code class="text-[11px]">{{ $tpl('report_date') }}</code>, <code class="text-[11px]">{{ $tpl('report_number') }}</code></p>
                        <p class="text-stone-400 dark:text-stone-500">У старых макетов имена вроде <code class="text-[11px]">{{ $tpl('approver_fio') }}</code>, <code class="text-[11px]">{{ $tpl('executor_line1') }}</code> по-прежнему обрабатываются.</p>
                    </div>
                    <div id="header_template"
                         x-ref="headerEditor"
                         role="textbox"
                         aria-multiline="true"
                         contenteditable="true"
                         spellcheck="false"
                         class="block w-full min-h-[9rem] max-h-[28rem] overflow-y-auto rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-900 px-3 py-2 text-sm text-stone-900 dark:text-stone-100 shadow-sm outline-none ring-inset focus:border-orange-400 focus:ring-2 focus:ring-orange-400/25 whitespace-pre-wrap break-words"
                         @input.debounce.200ms="syncTargetTemplate('header')"
                         @blur="syncTargetTemplate('header')"
                         @paste="onTokenEditorPaste($event)"
                         @keydown="onTokenEditorKeydown($event)"
                         @dragenter.prevent="onTokenEditorDragOver($event)"
                         @dragover.prevent="onTokenEditorDragOver($event)"
                         @drop.prevent="onTokenEditorDrop($event, 'header')"></div>
                    <input type="hidden" name="header_template" x-bind:value="headerTemplate"/>
                    <div class="mt-2 flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-2 rounded-lg border border-stone-200 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/40 px-3 py-2">
                        <div class="min-w-0 flex-1 sm:max-w-xs">
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Вставить поле в служебный блок</label>
                            <select x-model="selectedTokenField" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-xs sm:text-sm">
                                <template x-for="field in fields" :key="'hdr_svc_'+field.uid">
                                    <option :value="field.key" :title="field.key" x-text="(field.label && String(field.label).trim()) ? field.label : (field.key || '…')"></option>
                                </template>
                            </select>
                        </div>
                        <button type="button" class="shrink-0 rounded-lg border border-orange-200/90 bg-white px-3 py-2 text-xs font-medium text-orange-950 hover:bg-orange-50 dark:border-orange-800/80 dark:bg-stone-900 dark:text-orange-100 dark:hover:bg-orange-950/40 sm:self-end"
                                @click="insertToken(selectedTokenField, 'header')">Вставить в служебный блок</button>
                    </div>
                </div>
                </div>
            </div>

            <div class="p-4 space-y-4 bg-white dark:bg-stone-950" x-show="tab === 'text'" x-cloak>
                <div>
                    <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Основной текст</h3>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1 leading-relaxed">Центральная часть заявки в PDF. Подстановки полей — ключ в двойных фигурных скобках. Шапку и подписи снизу настраиваются на соседних вкладках.</p>
                </div>
                <div class="rounded-xl border border-stone-200 dark:border-stone-700 p-3 max-w-md">
                    <label class="block text-xs font-medium text-stone-700 dark:text-stone-200 mb-1">Выравнивание основного текста в PDF</label>
                    <select name="pdf_body_align" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                        <option value="center" @selected($pdfBodyAlign === 'center')>По центру</option>
                        <option value="justify" @selected($pdfBodyAlign === 'justify')>По ширине</option>
                        <option value="right" @selected($pdfBodyAlign === 'right')>Справа</option>
                        <option value="left" @selected($pdfBodyAlign === 'left')>Слева</option>
                    </select>
                </div>
                <div>
                    <label for="body_template" class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Основной текст документа</label>
                    <p class="text-xs text-stone-500 mb-1">Блоки полей в редакторе можно перетаскивать мышью, чтобы поменять их порядок в тексте.</p>
                    <div id="body_template"
                         x-ref="bodyEditor"
                         role="textbox"
                         aria-multiline="true"
                         contenteditable="true"
                         spellcheck="false"
                         class="block w-full min-h-[12rem] max-h-[32rem] overflow-y-auto rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-900 px-3 py-2 text-sm text-stone-900 dark:text-stone-100 shadow-sm outline-none ring-inset focus:border-orange-400 focus:ring-2 focus:ring-orange-400/25 whitespace-pre-wrap break-words"
                         @input.debounce.200ms="syncTargetTemplate('body')"
                         @blur="syncTargetTemplate('body')"
                         @paste="onTokenEditorPaste($event)"
                         @keydown="onTokenEditorKeydown($event)"
                         @dragenter.prevent="onTokenEditorDragOver($event)"
                         @dragover.prevent="onTokenEditorDragOver($event)"
                         @drop.prevent="onTokenEditorDrop($event, 'body')"></div>
                    <input type="hidden" name="body_template" x-bind:value="bodyTemplate"/>
                    @error('body_template')
                        <p class="mt-1 text-sm text-rose-700 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                    <div class="mt-2 flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-2 rounded-lg border border-stone-200 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/40 px-3 py-2">
                        <div class="min-w-0 flex-1 sm:max-w-xs">
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Вставить поле в основной текст</label>
                            <select x-model="selectedTokenField" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-xs sm:text-sm">
                                <template x-for="field in fields" :key="'body_tok_'+field.uid">
                                    <option :value="field.key" :title="field.key" x-text="(field.label && String(field.label).trim()) ? field.label : (field.key || '…')"></option>
                                </template>
                            </select>
                        </div>
                        <button type="button" class="shrink-0 rounded-lg border border-orange-200/90 bg-white px-3 py-2 text-xs font-medium text-orange-950 hover:bg-orange-50 dark:border-orange-800/80 dark:bg-stone-900 dark:text-orange-100 dark:hover:bg-orange-950/40 sm:self-end"
                                @click="insertToken(selectedTokenField, 'body')">Вставить в основной текст</button>
                    </div>
                </div>
            </div>

            <div class="p-4 space-y-4 bg-white dark:bg-stone-950" x-show="tab === 'footer'" x-cloak>
                <div>
                    <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Низ страницы (подписи)</h3>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1 leading-relaxed">Нижний блок слева и строка подписи справа. Поля макета — те же подстановки, что в основном тексте. Часто используют <code class="text-[11px]">{{ $tpl('фио') }}</code>, <code class="text-[11px]">{{ $tpl('document_date') }}</code>, <code class="text-[11px]">{{ $tpl('signatory_print_name') }}</code>.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border border-stone-200 dark:border-stone-700 p-3">
                    <p class="sm:col-span-2 text-xs font-medium text-stone-700 dark:text-stone-200">Выравнивание в PDF</p>
                    <div>
                        <label class="block text-xs text-stone-600 dark:text-stone-300 mb-1">Нижний блок слева</label>
                        <select name="pdf_footer_left_align" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                            <option value="left" @selected($pdfFooterLeftAlign === 'left')>Слева</option>
                            <option value="center" @selected($pdfFooterLeftAlign === 'center')>По центру</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-stone-600 dark:text-stone-300 mb-1">Нижний блок справа (подпись)</label>
                        <select name="pdf_footer_right_align" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-sm">
                            <option value="right" @selected($pdfFooterRightAlign === 'right')>Справа</option>
                            <option value="center" @selected($pdfFooterRightAlign === 'center')>По центру</option>
                            <option value="left" @selected($pdfFooterRightAlign === 'left')>Слева</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Нижний блок слева</label>
                    <p class="text-xs text-stone-500 mb-1">Текст под основным блоком слева (например ФИО, должность, дата).</p>
                    <div x-ref="footerEditor"
                         role="textbox"
                         aria-multiline="true"
                         contenteditable="true"
                         spellcheck="false"
                         class="block w-full min-h-[7.5rem] max-h-[28rem] overflow-y-auto rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-900 px-3 py-2 text-sm text-stone-900 dark:text-stone-100 shadow-sm outline-none ring-inset focus:border-orange-400 focus:ring-2 focus:ring-orange-400/25 whitespace-pre-wrap break-words"
                         @input.debounce.200ms="syncTargetTemplate('footer')"
                         @blur="syncTargetTemplate('footer')"
                         @paste="onTokenEditorPaste($event)"
                         @keydown="onTokenEditorKeydown($event)"
                         @dragenter.prevent="onTokenEditorDragOver($event)"
                         @dragover.prevent="onTokenEditorDragOver($event)"
                         @drop.prevent="onTokenEditorDrop($event, 'footer')"></div>
                    <input type="hidden" name="footer_left_template" x-bind:value="footerTemplate"/>
                    <div class="mt-2 flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-2 rounded-lg border border-stone-200 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/40 px-3 py-2">
                        <div class="min-w-0 flex-1 sm:max-w-xs">
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Вставить поле в нижний блок слева</label>
                            <select x-model="selectedTokenField" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-xs sm:text-sm">
                                <template x-for="field in fields" :key="'ftr_left_'+field.uid">
                                    <option :value="field.key" :title="field.key" x-text="(field.label && String(field.label).trim()) ? field.label : (field.key || '…')"></option>
                                </template>
                            </select>
                        </div>
                        <button type="button" class="shrink-0 rounded-lg border border-orange-200/90 bg-white px-3 py-2 text-xs font-medium text-orange-950 hover:bg-orange-50 dark:border-orange-800/80 dark:bg-stone-900 dark:text-orange-100 dark:hover:bg-orange-950/40 sm:self-end"
                                @click="insertToken(selectedTokenField, 'footer')">Вставить в блок слева</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Подпись справа</label>
                    <p class="text-xs text-stone-500 mb-1">Обычно линия подписи и расшифровка; можно использовать системную подстановку <code class="text-[11px]">{{ $tpl('signatory_print_name') }}</code>.</p>
                    <div x-ref="signatureEditor"
                         role="textbox"
                         aria-multiline="true"
                         contenteditable="true"
                         spellcheck="false"
                         class="block w-full min-h-[5rem] max-h-[24rem] overflow-y-auto rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-900 px-3 py-2 text-sm text-stone-900 dark:text-stone-100 shadow-sm outline-none ring-inset focus:border-orange-400 focus:ring-2 focus:ring-orange-400/25 whitespace-pre-wrap break-words"
                         @input.debounce.200ms="syncTargetTemplate('signature')"
                         @blur="syncTargetTemplate('signature')"
                         @paste="onTokenEditorPaste($event)"
                         @keydown="onTokenEditorKeydown($event)"
                         @dragenter.prevent="onTokenEditorDragOver($event)"
                         @dragover.prevent="onTokenEditorDragOver($event)"
                         @drop.prevent="onTokenEditorDrop($event, 'signature')"></div>
                    <input type="hidden" name="signature_template" x-bind:value="signatureTemplate"/>
                    <div class="mt-2 flex flex-col gap-2 rounded-lg border border-orange-100/90 bg-orange-50/40 px-3 py-2 sm:flex-row sm:flex-wrap sm:items-end dark:border-orange-900/35 dark:bg-orange-950/20">
                        <div class="min-w-0 flex-1 sm:max-w-xs">
                            <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Вставить поле в блок подписи</label>
                            <select x-model="selectedTokenField" class="block w-full rounded-lg border-stone-200 dark:border-stone-600 dark:bg-stone-900 dark:text-white shadow-sm text-xs sm:text-sm">
                                <template x-for="field in fields" :key="'ftr_sig_'+field.uid">
                                    <option :value="field.key" :title="field.key" x-text="(field.label && String(field.label).trim()) ? field.label : (field.key || '…')"></option>
                                </template>
                            </select>
                        </div>
                        <button type="button" class="ui-btn ui-btn--primary ui-btn--sm shrink-0 sm:self-end"
                                @click="insertToken(selectedTokenField, 'signature')">Вставить в подпись справа</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-orange-100/90 pt-2 sm:flex-row sm:justify-end dark:border-orange-900/40">
            <a href="{{ route('boiler-chief.request-layouts.index') }}" class="ui-btn ui-btn--secondary w-full sm:w-auto text-center">Отмена</a>
            <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto">{{ $submitLabel }}</button>
        </div>
    </form>
</div>
