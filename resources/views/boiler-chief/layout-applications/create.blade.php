@php
    $cancelUrl = $cancelUrl ?? route('boiler-chief.layout-applications.index');
    $layoutOptions = $layouts->map(fn ($l) => ['id' => $l->id, 'title' => $l->title])->values();
    $userOptions = \App\Models\User::layoutReportSignerOptions($users);
    $applicationOptions = collect($applicationOptions ?? [])->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="$cancelUrl">Отчеты по макетам</x-page-header-nav>
            <h2 class="font-semibold text-xl text-stone-900 dark:text-white">
                {{ isset($editingSubmission) ? 'Редактирование отчета' : 'Новый отчет по макету' }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 w-full min-w-0"
         x-data="layoutApplicationCreate({
            layouts: {{ \Illuminate\Support\Js::from($layoutOptions) }},
            users: {{ \Illuminate\Support\Js::from($userOptions) }},
            applications: {{ \Illuminate\Support\Js::from($applicationOptions) }},
            installationActApplications: {{ \Illuminate\Support\Js::from($installationActApplicationOptions ?? $applicationOptions) }},
            layoutSchemasById: {{ \Illuminate\Support\Js::from($layoutSchemasById ?? []) }},
            schemaJsonBase: @js(url('/applications/installation-act/layout-schema')),
            storeUrl: @js($storeUrl ?? (isset($editingSubmission) ? route('boiler-chief.layout-applications.update', $editingSubmission) : route('boiler-chief.layout-applications.store'))),
            token: @js(csrf_token()),
            preselectLayoutId: @js((int) (isset($editingSubmission) ? $editingSubmission->layout_structure_id : request('layout', 0))),
            layoutLocked: @js((bool) isset($editingSubmission)),
            submitRedirectsOnSuccess: @js(false),
            initialSubmissionPayload: {{ \Illuminate\Support\Js::from($initialSubmissionPayload ?? []) }},
            layoutViewerContext: {{ \Illuminate\Support\Js::from($layoutViewerContext ?? ['isBoilerChief' => false, 'foremanRoleId' => 4, 'chiefSubdivisionIds' => []]) }},
            measurementMeta: {{ \Illuminate\Support\Js::from($measurementMeta ?? []) }},
            subdivisionWarehouseOptions: {{ \Illuminate\Support\Js::from($subdivisionWarehouseOptions ?? []) }},
         })">
        <div class="max-w-4xl mx-auto w-full min-w-0 sm:px-6 lg:px-8 px-3">
            @if($layouts->isEmpty())
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                    Нет доступных макетов. Сначала создайте макет в разделе «Макеты отчетов (PDF)».
                </div>
            @else
                <form method="POST" :action="storeUrl" @submit.prevent="submit" class="overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35">
                    @csrf
                    @isset($editingSubmission)
                        @method('PUT')
                    @endisset

                    <div class="px-5 sm:px-8 py-6 space-y-6 border-b border-orange-100/90 dark:border-orange-900/40">
                        <div>
                            <label for="layout_structure_id" class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-1">Макет</label>
                            @if (isset($editingSubmission))
                                <input type="hidden" name="layout_structure_id" value="{{ $editingSubmission->layout_structure_id }}"/>
                                <p class="text-sm text-stone-800 dark:text-stone-200 py-2 px-3 rounded-lg border border-orange-200/80 bg-orange-50/40 dark:border-orange-900/50 dark:bg-stone-900/50">{{ $layouts->firstWhere('id', $editingSubmission->layout_structure_id)?->title ?? '—' }}</p>
                            @else
                            <select id="layout_structure_id" name="layout_structure_id" required
                                    x-model.number="layoutId" @change="loadFields()"
                                    class="app-select">
                                <option value="">— Выберите макет —</option>
                                <template x-for="l in layouts" :key="l.id">
                                    <option :value="l.id" x-text="l.title"></option>
                                </template>
                            </select>
                            @endisset
                        </div>

                        <div x-show="allowApplicationEquipmentInsert" class="space-y-2 rounded-xl border border-orange-200/70 bg-orange-50/50 px-4 py-4 dark:border-orange-900/45 dark:bg-orange-950/20">
                            <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Оборудование из заявки</p>
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                    <label class="block text-xs text-stone-600 dark:text-stone-300"
                                           x-text="isSingleApplicationSelection ? 'Заявка' : 'Заявки (можно несколько или все)'"></label>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" x-show="!isSingleApplicationSelection" class="text-xs font-medium text-orange-800 hover:underline dark:text-orange-200/90" @click="selectAllApplications()">Все заявки</button>
                                        <button type="button" class="text-xs font-medium text-stone-600 hover:underline dark:text-stone-400" @click="clearApplicationSelection()">Снять</button>
                                    </div>
                                </div>
                            <template x-if="!reportApplications || reportApplications.length === 0">
                                    <p class="text-xs text-stone-500 dark:text-stone-400">Нет заявок для подстановки.</p>
                                </template>
                            <div x-show="reportApplications && reportApplications.length > 0" class="mb-2">
                                <label for="report-application-search" class="sr-only">Поиск заявки</label>
                                <input
                                    id="report-application-search"
                                    type="search"
                                    x-model.trim="applicationSearch"
                                    placeholder="Поиск заявки: номер или подразделение"
                                    class="app-input"
                                />
                            </div>
                                <div x-show="reportApplications && reportApplications.length > 0" class="max-h-48 overflow-y-auto rounded-lg border border-orange-200/80 bg-white px-3 py-2 space-y-1.5 dark:border-orange-900/50 dark:bg-stone-900/40">
                                    <template x-if="!isSingleApplicationSelection">
                                        <label class="flex items-center gap-2 text-xs font-medium text-stone-700 dark:text-stone-200 cursor-pointer border-b border-stone-100 pb-1.5 mb-0.5 dark:border-stone-700">
                                            <input type="checkbox" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40 dark:border-stone-600 dark:bg-stone-900"
                                                   :checked="allApplicationsSelected"
                                                   @change="toggleSelectAllApplications()"/>
                                            <span>Выбрать все заявки</span>
                                        </label>
                                    </template>
                                    <template x-for="app in reportApplications" :key="'app_pick_' + app.id">
                                    <label x-show="!applicationSearch || String(app.label || '').toLowerCase().includes(applicationSearch.toLowerCase())"
                                           class="flex items-center gap-2 text-sm text-stone-800 dark:text-stone-100 cursor-pointer">
                                            <input x-show="isSingleApplicationSelection" type="radio" name="report_application_id" class="border-stone-300 text-orange-600 focus:ring-orange-500/40 dark:border-stone-600 dark:bg-stone-900"
                                                   :value="app.id"
                                                   :checked="isApplicationSelected(app.id)"
                                                   @change="selectSingleApplication(app.id)"/>
                                            <input x-show="!isSingleApplicationSelection" type="checkbox" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40 dark:border-stone-600 dark:bg-stone-900"
                                                   :value="app.id"
                                                   x-model="selectedApplicationIds"/>
                                            <span class="truncate" x-text="app.label"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-stone-600 dark:text-stone-300 mb-1"
                                       x-text="isSingleApplicationSelection ? 'Оборудование из заявки' : 'Оборудование из выбранных заявок'"></label>
                                <select class="app-select" x-model="selectedApplicationEquipment" :disabled="!selectedApplicationIds || selectedApplicationIds.length === 0">
                                    <option value="">— Выберите оборудование —</option>
                                    <option value="__ALL__" x-show="selectedApplicationIds && selectedApplicationIds.length > 0"
                                            x-text="(!isSingleApplicationSelection && selectedApplicationIds && selectedApplicationIds.length > 1) ? 'Все позиции выбранных заявок' : 'Все позиции заявки'"></option>
                                    <template x-for="(eq, idx) in selectedApplicationEquipmentOptions()" :key="'eq_' + idx + '_' + (eq.line || '') + '_' + (eq.__sourceAppId || '')">
                                        <option :value="JSON.stringify(eq)" x-text="eq.__optionLabel || eq.line"></option>
                                    </template>
                                </select>
                            </div>
                            <div x-show="!isActiveFieldTable()">
                                <label class="block text-xs text-stone-600 dark:text-stone-300 mb-1">Вставить как</label>
                                <select class="app-select" x-model="insertEquipmentFormat">
                                    <option value="list">Список</option>
                                    <option value="table">Таблица</option>
                                </select>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" @click="insertSelectedEquipmentIntoActiveField()" x-text="activeFieldInsertButtonLabel()">
                                </button>
                            </div>
                        </div>

                        <template x-if="signerSlotCount > 0">
                            <div class="space-y-3 rounded-xl border-2 border-orange-400/80 bg-orange-50/60 px-4 py-4 dark:border-orange-600/70 dark:bg-orange-950/25">
                                <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Подпись в PDF <span class="text-orange-700 dark:text-orange-300">(обязательно)</span></p>
                                 <template x-for="n in signerIndices()" :key="n">
                                    <div>
                                        <label class="block text-sm font-medium text-stone-800 dark:text-stone-200 mb-1" x-text="signerRoleLabel(n)"></label>
                                        <select class="app-select" :name="'signer_' + n + '_user_id'" x-model="signerSelections[n]">
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

                                <template x-if="field.type === 'date'">
                                    <div class="rounded-xl border border-orange-200/75 bg-white p-4 dark:border-orange-900/40 dark:bg-stone-900/40">
                                        <label class="block text-sm text-stone-600 dark:text-stone-400 mb-2" x-text="'(' + (field.label || field.key) + ')'"></label>
                                        <input type="date" class="app-input"
                                               :name="'values[' + field.key + ']'" />
                                    </div>
                                </template>

                                <template x-if="field.type === 'table'">
                                    <div class="rounded-xl border border-orange-200/75 bg-white p-4 dark:border-orange-900/40 dark:bg-stone-900/40 cursor-pointer transition-shadow"
                                         :class="activeEditorFieldKey === field.key ? 'ring-2 ring-orange-500/70 shadow-sm' : ''"
                                         @click="setActiveEditorField(field.key)">
                                        <p class="text-sm font-medium text-stone-800 dark:text-stone-200 mb-2" x-text="field.label || field.key"></p>
                                        <div class="mb-3 flex flex-wrap items-end gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-stone-600 dark:text-stone-300 mb-1">Число строк</label>
                                                <input type="number" class="app-input w-28 min-h-0" min="1" max="30"
                                                       :value="getTableRowCount(field.key)"
                                                       @input="setTableRowCount(field.key, $event.target.value)" />
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
                                                                    <input type="text"
                                                                           class="app-input min-h-0 w-full text-sm py-1.5"
                                                                           maxlength="500"
                                                                           :name="'values[' + field.key + '][' + rowIdx + '][' + colIdx + ']'"
                                                                           @focus="setActiveEditorField(field.key)" />
                                                                </td>
                                                            </template>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="field.type === 'text' && field.readonly">
                                    <div class="rounded-xl border border-orange-200/75 bg-orange-50/30 p-4 dark:border-orange-900/40 dark:bg-stone-900/40">
                                        <label class="block text-sm font-medium text-stone-800 dark:text-stone-200 mb-1.5" x-text="field.label || field.key"></label>
                                        <input type="text" readonly
                                               class="app-input bg-stone-50 dark:bg-stone-900/60"
                                               :name="'values[' + field.key + ']'" />
                                    </div>
                                </template>

                                <template x-if="field.type === 'subdivision_warehouse'">
                                    @include('boiler-chief.layout-applications._subdivision-warehouse-field')
                                </template>

                                <template x-if="(field.type === 'text' || field.type === 'textarea') && !field.readonly">
                                    <div>
                                        <p class="text-sm text-stone-600 dark:text-stone-400 mb-1.5" x-text="'(' + (field.label || field.key) + ')'"></p>
                                        <div class="overflow-hidden rounded-xl border-2 border-orange-400/90 shadow-sm dark:border-orange-600/70">
                                            @include('boiler-chief.layout-applications._rich-field-toolbar')
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
                                <input id="use-current-date-checkbox" type="checkbox" name="use_current_date" value="1" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40 dark:border-stone-600 dark:bg-stone-900" @checked(! isset($editingSubmission))/>
                                <span>Использовать текущую дату</span>
                            </label>
                            <div>
                                <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Или укажите дату</label>
                                <input id="form-document-date-input" type="date" name="form_document_date" value="{{ old('form_document_date', $formDocumentDate ?? '') }}" class="app-input"/>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end pt-2">
                            <a href="{{ $cancelUrl }}" class="ui-btn ui-btn--secondary inline-flex justify-center">Отмена</a>
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
