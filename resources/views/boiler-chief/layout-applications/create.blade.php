@php
    $layoutOptions = $layouts->map(fn ($l) => ['id' => $l->id, 'title' => $l->title])->values();
    $userOptions = $users->map(fn ($u) => [
        'id' => $u->id,
        'label' => $u->fullName().' (id '.$u->id.')',
        'role_id' => (int) ($u->role_id ?? 0),
        'role_name' => (string) ($u->role?->name ?? ''),
    ])->values();
    $applicationOptions = ($applications ?? collect())->map(function ($a) {
        $approvedItems = $a->items->where('is_checked', true)->values();
        $lineItems = ($approvedItems->isNotEmpty() ? $approvedItems : $a->items)
            ->map(fn ($item) => [
                'name' => (string) $item->equipment_display_name,
                'quantity' => (string) $item->quantity_with_unit,
                'line' => trim($item->equipment_display_name.' x '.$item->quantity_with_unit),
            ])
            ->filter(fn (array $item) => $item['line'] !== '')
            ->values();
        return [
            'id' => $a->id,
            'label' => '#'.$a->id.' - '.($a->subdivision?->name ?? 'Без подразделения'),
            'equipment' => $lineItems,
        ];
    })->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('boiler-chief.layout-applications.index')">Заявки по макетам</x-page-header-nav>
            <h2 class="font-semibold text-xl text-stone-900 dark:text-white">Новая заявка по макету</h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10"
         x-data="layoutApplicationCreate({
            layouts: {{ \Illuminate\Support\Js::from($layoutOptions) }},
            users: {{ \Illuminate\Support\Js::from($userOptions) }},
            applications: {{ \Illuminate\Support\Js::from($applicationOptions) }},
            schemaBase: @js(url('/boiler-chief/request-layouts')),
            storeUrl: @js(route('boiler-chief.layout-applications.store')),
            token: @js(csrf_token()),
            preselectLayoutId: @js((int) request('layout', 0)),
         })">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 px-3">
            @if($layouts->isEmpty())
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                    Нет доступных макетов. Сначала создайте макет в разделе «Макеты заявок (PDF)».
                </div>
            @else
                <form method="POST" :action="storeUrl" @submit.prevent="submit" class="overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35">
                    @csrf

                    <div class="px-5 sm:px-8 py-6 space-y-6 border-b border-orange-100/90 dark:border-orange-900/40">
                        <div>
                            <label for="layout_structure_id" class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-1">Макет</label>
                            <select id="layout_structure_id" name="layout_structure_id" required
                                    x-model.number="layoutId" @change="loadFields()"
                                    class="app-select">
                                <option value="">— Выберите макет —</option>
                                <template x-for="l in layouts" :key="l.id">
                                    <option :value="l.id" x-text="l.title"></option>
                                </template>
                            </select>
                        </div>

                        <div class="space-y-2 rounded-xl border border-orange-200/70 bg-orange-50/50 px-4 py-4 dark:border-orange-900/45 dark:bg-orange-950/20">
                            <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Оборудование из заявки</p>
                            <div>
                                <label class="block text-xs text-stone-600 dark:text-stone-300 mb-1">Выберите заявку</label>
                                <select class="app-select" x-model.number="selectedApplicationId">
                                    <option value="">— Выберите заявку —</option>
                                    <template x-for="app in applications" :key="'app_' + app.id">
                                        <option :value="app.id" x-text="app.label"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-stone-600 dark:text-stone-300 mb-1">Оборудование из выбранной заявки</label>
                                <select class="app-select" x-model="selectedApplicationEquipment">
                                    <option value="">— Выберите оборудование —</option>
                                    <option value="__ALL__">Все позиции заявки</option>
                                    <template x-for="(eq, idx) in selectedApplicationEquipmentOptions()" :key="'eq_' + idx + '_' + (eq.line || '')">
                                        <option :value="JSON.stringify(eq)" x-text="eq.line"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-stone-600 dark:text-stone-300 mb-1">Вставить как</label>
                                <select class="app-select" x-model="insertEquipmentFormat">
                                    <option value="list">Список</option>
                                    <option value="table">Таблица</option>
                                </select>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" @click="insertSelectedEquipmentIntoActiveField()">
                                    Вставить в активное поле текста
                                </button>
                            </div>
                        </div>

                        <template x-if="signerSlotCount > 0">
                            <div class="space-y-3 rounded-xl border border-orange-200/70 bg-orange-50/50 px-4 py-4 dark:border-orange-900/45 dark:bg-orange-950/20">
                                <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Подписанты в нижнем блоке PDF</p>
                                <p class="text-xs text-stone-600 dark:text-stone-400">Это подписи. В документ подставляются их ФИО в формате подписи (линия + ФИО) в плейсхолдеры <code class="text-[11px]">signer_1_fio</code>, <code class="text-[11px]">signer_2_fio</code>, <code class="text-[11px]">signer_3_fio</code>.</p>
                                <template x-for="n in signerIndices()" :key="n">
                                    <div>
                                        <label class="block text-sm font-medium text-stone-800 dark:text-stone-200 mb-1" x-text="signerRoleLabel(n)"></label>
                                        <select class="app-select" :name="'signer_' + n + '_user_id'">
                                            <option value="">— Выберите ФИО —</option>
                                            <template x-for="u in usersForSignerSlot(n)" :key="u.id">
                                                <option :value="u.id" x-text="u.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div x-show="loading" class="text-sm text-stone-500 dark:text-stone-400">Загрузка полей…</div>
                    </div>

                    <template x-if="fields.length > 0">
                        <div class="px-5 sm:px-8 py-4 border-b border-orange-100/90 dark:border-orange-900/40 bg-orange-50/30 dark:bg-orange-950/15">
                            <p class="text-xs text-stone-600 dark:text-stone-400 mb-2">К выделению в активном поле: выберите шрифт и размер, кликните в поле, выделите фрагмент и нажмите «К выделению» у этого поля.</p>
                            <div class="overflow-hidden rounded-xl border border-orange-200/80 bg-white dark:border-orange-900/40 dark:bg-stone-900/50">
                                <div class="flex flex-wrap items-end gap-2 px-3 py-2.5">
                                    <div>
                                        <label class="block text-[11px] font-medium text-stone-600 dark:text-stone-400 mb-0.5">Шрифт</label>
                                        <select x-model="fontFamily" class="app-select !min-h-0 py-2 text-sm">
                                            <option>Times New Roman</option>
                                            <option>Arial</option>
                                            <option>DejaVu Sans</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-stone-600 dark:text-stone-400 mb-0.5">Размер (pt)</label>
                                        <select x-model.number="fontSizePt" class="app-select w-24 !min-h-0 py-2 text-sm">
                                            @foreach(range(8, 18) as $sz)
                                                <option value="{{ $sz }}">{{ $sz }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="px-5 sm:px-8 py-6 space-y-6">
                        <template x-for="field in fields" :key="field.key">
                            <div class="space-y-2">
                                <template x-if="field.type === 'number'">
                                    <div class="rounded-xl border border-orange-200/75 bg-white p-4 dark:border-orange-900/40 dark:bg-stone-900/40">
                                        <label class="block text-sm text-stone-600 dark:text-stone-400 mb-2" x-text="'(' + (field.label || field.key) + ')'"></label>
                                        <input type="number" step="any" class="app-input"
                                               :name="'values[' + field.key + ']'" />
                                    </div>
                                </template>

                                <template x-if="field.type !== 'number'">
                                    <div>
                                        <p class="text-sm text-stone-600 dark:text-stone-400 mb-1.5" x-text="'(' + (field.label || field.key) + ')'"></p>
                                        <div class="overflow-hidden rounded-xl border-2 border-orange-400/90 shadow-sm dark:border-orange-600/70">
                                            <div class="layout-field-toolbar flex flex-wrap gap-1.5 items-center border-b border-orange-200/80 bg-orange-50/80 px-2.5 py-2 dark:border-orange-900/50 dark:bg-orange-950/30">
                                                <button type="button"
                                                        class="rounded-md border border-orange-500/70 bg-orange-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-orange-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/50 dark:border-orange-600 dark:bg-orange-700 dark:hover:bg-orange-600"
                                                        @click="applySelectionStyle(field.key)">К выделению</button>
                                                <button type="button"
                                                        class="rounded-md border border-stone-200 bg-white px-2.5 py-1 text-xs font-bold text-stone-800 shadow-sm hover:bg-orange-50/80 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800"
                                                        @click="execOn(field.key, 'bold')">B</button>
                                                <button type="button"
                                                        class="rounded-md border border-stone-200 bg-white px-2.5 py-1 text-xs text-stone-800 shadow-sm hover:bg-orange-50/80 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800"
                                                        @click="execOn(field.key, 'justifyLeft')">Слева</button>
                                                <button type="button"
                                                        class="rounded-md border border-stone-200 bg-white px-2.5 py-1 text-xs text-stone-800 shadow-sm hover:bg-orange-50/80 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800"
                                                        @click="execOn(field.key, 'justifyCenter')">Центр</button>
                                                <button type="button"
                                                        class="rounded-md border border-stone-200 bg-white px-2.5 py-1 text-xs text-stone-800 shadow-sm hover:bg-orange-50/80 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800"
                                                        @click="execOn(field.key, 'justifyRight')">Справа</button>
                                                <button type="button"
                                                        class="rounded-md border border-stone-200 bg-white px-2.5 py-1 text-xs text-stone-800 shadow-sm hover:bg-orange-50/80 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800"
                                                        @click="execOn(field.key, 'insertHorizontalRule')">Линия</button>
                                                <button type="button"
                                                        class="rounded-md border border-stone-200 bg-white px-2.5 py-1 text-xs text-stone-800 shadow-sm hover:bg-orange-50/80 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800"
                                                        @click="execOn(field.key, 'removeFormat')">Базовый стиль</button>
                                            </div>
                                            <div contenteditable="true" spellcheck="false"
                                                 class="layout-field-editor min-h-[6rem] w-full bg-white px-3 py-2.5 text-sm text-stone-900 outline-none focus:ring-2 focus:ring-orange-400/30 focus:ring-inset dark:bg-stone-950 dark:text-stone-100"
                                                 :id="'editor-' + field.slug"
                                                 @focus="setActiveEditorField(field.key)"
                                                 @input="syncRich(field.key, $event.target)"></div>
                                            <input type="hidden" :name="'values[' + field.key + ']'" :id="'hidden-' + field.slug"/>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div class="rounded-xl border border-orange-200/75 bg-orange-50/40 px-4 py-3 space-y-2 dark:border-orange-900/45 dark:bg-orange-950/20">
                            <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Дата формирования</p>
                            <input type="hidden" name="use_current_date" value="0"/>
                            <label class="inline-flex items-center gap-2 text-sm cursor-pointer text-stone-800 dark:text-stone-200">
                                <input id="use-current-date-checkbox" type="checkbox" name="use_current_date" value="1" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40 dark:border-stone-600 dark:bg-stone-900" checked/>
                                <span>Использовать текущую дату</span>
                            </label>
                            <div>
                                <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Или укажите дату</label>
                                <input id="form-document-date-input" type="date" name="form_document_date" class="app-input"/>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end pt-2">
                            <a href="{{ route('boiler-chief.layout-applications.index') }}" class="ui-btn ui-btn--secondary inline-flex justify-center">Отмена</a>
                            <button type="submit" class="ui-btn ui-btn--primary inline-flex justify-center disabled:opacity-50" :disabled="!layoutId || loading">
                                Сохранить и скачать PDF
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
    <script>
        (function () {
            var checkbox = document.getElementById('use-current-date-checkbox');
            var dateInput = document.getElementById('form-document-date-input');
            if (!checkbox || !dateInput) {
                return;
            }
            function syncDateRequirement() {
                var useCurrent = !!checkbox.checked;
                dateInput.required = !useCurrent;
            }
            checkbox.addEventListener('change', syncDateRequirement);
            syncDateRequirement();
        })();
    </script>
</x-app-layout>
