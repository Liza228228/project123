<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.index')">Заявки</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Создать заявку
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
           
            <div class="px-4 py-5 sm:p-8">
                <form method="POST" action="{{ route('applications.store') }}" enctype="multipart/form-data" class="max-sm:pb-40 space-y-8 sm:space-y-10">
                    @csrf
                    <input type="hidden" name="source_application_id" value="{{ old('source_application_id', $prefill['source_application_id'] ?? '') }}">

                    @if($prefill)
                        <div class="flex items-start gap-3 rounded-xl border border-sky-200/80 bg-sky-50/60 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/25 dark:text-sky-100">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-200/80 text-xs font-bold dark:bg-sky-800/80">↻</span>
                            <span>Повторная заявка на основе заявки №{{ $prefill['source_application_id'] }}.</span>
                        </div>
                    @endif

                    @if($subdivisions->isEmpty())
                        <div class="rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-100">
                            Для вас не назначены подразделения. Обратитесь к начальнику отдела снабжения.
                        </div>
                    @endif

                    <section class="space-y-4" aria-labelledby="create-section-main">
                        <h3 id="create-section-main" class="app-section-title">Основное</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="subdivision_id" class="app-form-label">Подразделение</label>
                                <select id="subdivision_id" name="subdivision_id" class="app-select" required @disabled($subdivisions->isEmpty())>
                                    <option value="">Выберите подразделение</option>
                                    @foreach($subdivisions as $sub)
                                        <option value="{{ $sub->id }}" @selected(old('subdivision_id', $prefill['subdivision_id'] ?? null) == $sub->id)>{{ $sub->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('subdivision_id')" class="mt-1.5" />
                            </div>

                            @include('applications.partials.subdivision-warehouses-hint')

                            @if (! Auth::user()->hasRoleId(4))
                                <div class="sm:col-span-2">
                                    <label for="responsible_user_id" class="app-form-label">Ответственный</label>
                                    <select id="responsible_user_id" name="responsible_user_id" class="app-select">
                                        <option value="">Не назначен / выбрать автоматически</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" @selected(old('responsible_user_id', $prefill['responsible_user_id'] ?? null) == $u->id)>{{ $u->surname }} {{ $u->name }} {{ $u->patronymic }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('responsible_user_id')" class="mt-1.5" />
                                </div>
                            @else
                                <input type="hidden" name="responsible_user_id" value="{{ Auth::id() }}">
                            @endif

                            <div class="sm:col-span-2 rounded-xl border border-stone-200/80 bg-stone-50/70 px-4 py-3 text-sm text-stone-700 dark:border-stone-700/70 dark:bg-stone-900/40 dark:text-stone-200">
                                <p class="font-medium">Способ доставки</p>
                                <p class="mt-1">Указывается на этапе «Отметить всё как В пути».</p>
                            </div>
                        </div>
                    </section>

                    @php
                        $showCommercialOffer = old('attach_commercial_offer') === '1' || $errors->has('commercial_offer');
                    @endphp
                    <section class="space-y-3" aria-labelledby="create-section-files">
                        <h3 id="create-section-files" class="app-section-title">Документы</h3>
                        <input type="hidden" name="attach_commercial_offer" id="attach-commercial-offer-input" value="{{ $showCommercialOffer ? '1' : '0' }}">
                        <button
                            type="button"
                            id="add-commercial-offer-btn"
                            class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto {{ $showCommercialOffer ? 'hidden' : '' }}"
                        >
                            + Прикрепить коммерческое предложение
                        </button>

                        <div id="commercial-offer-block" class="{{ $showCommercialOffer ? '' : 'hidden' }} space-y-3 rounded-xl border border-dashed border-orange-300/80 bg-orange-50/40 p-4 dark:border-orange-800/50 dark:bg-orange-950/20">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label for="commercial_offer" class="app-form-label !mb-0">Коммерческое предложение</label>
                                <button type="button" id="remove-commercial-offer-btn" class="min-h-10 shrink-0 rounded-lg px-2 text-xs font-medium text-stone-500 underline decoration-stone-300 hover:bg-stone-100 hover:text-stone-800 dark:text-stone-400 dark:hover:bg-stone-800/60 dark:hover:text-stone-200">
                                    Убрать КП
                                </button>
                            </div>
                            <input
                                id="commercial_offer"
                                type="file"
                                name="commercial_offer"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-orange-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-stone-800 hover:file:bg-orange-200/90 dark:text-stone-300 dark:file:bg-orange-950/50 dark:file:text-orange-100 dark:hover:file:bg-orange-900/60"
                            />
                            <p class="text-xs text-stone-500 dark:text-stone-400">
                                PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG · до 10 МБ
                            </p>
                            <x-input-error :messages="$errors->get('commercial_offer')" class="mt-1" />
                        </div>
                    </section>

                    @php
                        $items = old('items', $prefill['items'] ?? [[
                            'equipment_id' => '',
                            'equipment_name' => '',
                            'quantity' => 1,
                            'size_value' => '',
                            'measurement_type' => 'piece',
                            'quantity_unit' => 'шт',
                        ]]);
                        if (empty($items)) {
                            $items = [[
                                'equipment_id' => '',
                                'equipment_name' => '',
                                'quantity' => 1,
                                'size_value' => '',
                                'measurement_type' => 'piece',
                                'quantity_unit' => 'шт',
                            ]];
                        }
                    @endphp
                    <section class="space-y-4" aria-labelledby="create-section-equipment">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h3 id="create-section-equipment" class="app-section-title">Оборудование</h3>
                                <p class="mt-1 max-w-2xl text-xs text-stone-500 dark:text-stone-400">
                                    Позиции из справочника или своё название (после согласования — «Заказано» → «На складе», приход в «Материалах»).
                                </p>
                            </div>
                        </div>
                        <div id="equipment-items" class="space-y-4">
                            @foreach($items as $idx => $item)
                                @php
                                    $typeId = $item['equipment_id'] ?? '';
                                    $eqName = trim($item['equipment_name'] ?? '');
                                    $isCustomRow = ($typeId === '' || $typeId === null) && $eqName !== '';
                                @endphp
                                @if($isCustomRow)
                                    <div class="equipment-row equipment-row--custom app-equipment-card">
                                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Своё название</span>
                                            <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="" />
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-12 md:items-end">
                                            <div class="sm:col-span-2 md:col-span-6">
                                                <label class="app-form-label !normal-case">Наименование</label>
                                                <input type="text" name="items[{{ $idx }}][equipment_name]" value="{{ $item['equipment_name'] ?? '' }}" placeholder="Как в заявке у поставщика" class="custom-equipment-input app-input" />
                                            </div>
                                            <div class="quantity-wrap sm:col-span-1 md:col-span-2">
                                                <label class="app-form-label !normal-case">Количество</label>
                                                <input type="number" name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" class="app-input" required />
                                            </div>
                                            <div class="sm:col-span-1 md:col-span-2">
                                                <label class="app-form-label !normal-case">Тип</label>
                                                <select name="items[{{ $idx }}][measurement_type]" class="measurement-type app-select">
                                                    @foreach(($measurementMeta['typeOptions'] ?? []) as $typeCode => $typeName)
                                                        <option value="{{ $typeCode }}" @selected(($item['measurement_type'] ?? 'piece') === $typeCode)>{{ $typeName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="sm:col-span-1 md:col-span-2">
                                                <label class="app-form-label !normal-case">Ед.</label>
                                                <select name="items[{{ $idx }}][quantity_unit]" class="measurement-unit app-select" data-current="{{ $item['quantity_unit'] ?? 'шт' }}"></select>
                                            </div>
                                            <div class="size-value-wrap sm:col-span-2 md:col-span-4">
                                                <label class="app-form-label !normal-case">Размер одежды</label>
                                                <input type="text" name="items[{{ $idx }}][size_value]" value="{{ $item['size_value'] ?? '' }}" placeholder="M, 48…" class="app-input" />
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="equipment-row equipment-row--list app-equipment-card">
                                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Из справочника</span>
                                            <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        @php
                                            $selectedType = ($item['equipment_id'] ?? '') !== '' ? $equipment->firstWhere('id', (int) $item['equipment_id']) : null;
                                        @endphp
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="" />
                                        <input type="hidden" name="items[{{ $idx }}][measurement_type]" value="{{ $item['measurement_type'] ?? 'piece' }}" />
                                        <input type="hidden" name="items[{{ $idx }}][quantity_unit]" value="{{ $item['quantity_unit'] ?? 'шт' }}" />
                                        <input type="hidden" name="items[{{ $idx }}][size_value]" value="{{ $item['size_value'] ?? '' }}" />
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                                            <div class="md:col-span-9 min-w-0">
                                                <label class="app-form-label !normal-case">Поиск в справочнике</label>
                                                <div class="relative">
                                                    <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                                    </span>
                                                    <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="{{ $item['equipment_id'] ?? '' }}" class="equipment-type-id" />
                                                    <input
                                                        type="text"
                                                        value="{{ $selectedType?->name ?? '' }}"
                                                        placeholder="От 2 букв названия…"
                                                        autocomplete="off"
                                                        autocorrect="off"
                                                        autocapitalize="off"
                                                        spellcheck="false"
                                                        class="equipment-search app-input app-input--with-icon"
                                                    />
                                                    <div class="equipment-suggestions app-suggestions hidden"></div>
                                                </div>
                                            </div>
                                            <div class="md:col-span-3">
                                                <label class="app-form-label !normal-case">Количество</label>
                                                <input type="number" name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" class="app-input" required />
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div class="flex flex-col gap-2 pt-1 sm:flex-row sm:flex-wrap">
                            <button type="button" id="add-equipment-from-list" class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto">
                                + Из справочника
                            </button>
                            <button type="button" id="add-equipment-custom" class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto">
                                + Своё оборудование
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('equipment')" class="mt-1" />
                    </section>

                    <section class="space-y-4 border-t border-stone-100 pt-8 dark:border-stone-800" aria-labelledby="create-section-date">
                        <h3 id="create-section-date" class="app-section-title">Срок</h3>
                        <div class="w-full max-w-full sm:max-w-xs">
                            <label for="desired_delivery_date" class="app-form-label">Желаемая дата поставки</label>
                            <input id="desired_delivery_date" type="date" name="desired_delivery_date" value="{{ old('desired_delivery_date', $prefill['desired_delivery_date'] ?? now()->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}" required class="app-input min-h-[3.25rem] sm:min-h-[2.75rem]" />
                            <x-input-error :messages="$errors->get('desired_delivery_date')" class="mt-1.5" />
                        </div>
                    </section>

                    <div class="app-form-actions-mobile">
                        <a href="{{ route('applications.index') }}" class="min-h-11 content-center text-center text-sm font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-200 sm:text-left">
                            Отмена и к списку заявок
                        </a>
                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--lg w-full text-base disabled:opacity-60 disabled:cursor-not-allowed sm:w-auto" @disabled($subdivisions->isEmpty())>
                            Создать заявку
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/template" id="equipment-row-from-list-tpl">
        <div class="equipment-row equipment-row--list app-equipment-card">
            <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Из справочника</span>
                <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <input type="hidden" name="items[__INDEX__][equipment_name]" value="" />
            <input type="hidden" name="items[__INDEX__][measurement_type]" value="piece" />
            <input type="hidden" name="items[__INDEX__][quantity_unit]" value="шт" />
            <input type="hidden" name="items[__INDEX__][size_value]" value="" />
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                <div class="md:col-span-9 min-w-0">
                    <label class="app-form-label !normal-case">Поиск в справочнике</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="hidden" name="items[__INDEX__][equipment_id]" value="" class="equipment-type-id" />
                        <input type="text" placeholder="От 2 букв названия…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="equipment-search app-input app-input--with-icon" />
                        <div class="equipment-suggestions app-suggestions hidden"></div>
                    </div>
                </div>
                <div class="quantity-wrap md:col-span-3">
                    <label class="app-form-label !normal-case">Количество</label>
                    <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="app-input" required />
                </div>
            </div>
        </div>
    </script>
    <script type="text/template" id="equipment-row-custom-tpl">
        <div class="equipment-row equipment-row--custom app-equipment-card">
            <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Своё название</span>
                <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <input type="hidden" name="items[__INDEX__][equipment_id]" value="" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-12 md:items-end">
                <div class="sm:col-span-2 md:col-span-6">
                    <label class="app-form-label !normal-case">Наименование</label>
                    <input type="text" name="items[__INDEX__][equipment_name]" placeholder="Как в заявке у поставщика" class="custom-equipment-input app-input" />
                </div>
                <div class="quantity-wrap sm:col-span-1 md:col-span-2">
                    <label class="app-form-label !normal-case">Количество</label>
                    <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="app-input" required />
                </div>
                <div class="sm:col-span-1 md:col-span-2">
                    <label class="app-form-label !normal-case">Тип</label>
                    <select name="items[__INDEX__][measurement_type]" class="measurement-type app-select">
                        @foreach(($measurementMeta['typeOptions'] ?? []) as $typeCode => $typeName)
                            <option value="{{ $typeCode }}" @selected($typeCode === 'piece')>{{ $typeName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-1 md:col-span-2">
                    <label class="app-form-label !normal-case">Ед.</label>
                    <select name="items[__INDEX__][quantity_unit]" class="measurement-unit app-select" data-current="шт"></select>
                </div>
                <div class="size-value-wrap sm:col-span-2 md:col-span-4">
                    <label class="app-form-label !normal-case">Размер одежды</label>
                    <input type="text" name="items[__INDEX__][size_value]" value="" placeholder="M, 48…" class="app-input" />
                </div>
            </div>
        </div>
    </script>
    <script>
        (function() {
            var responsibleSelect = document.getElementById('responsible_user_id');
            var subdivisionSelect = document.getElementById('subdivision_id');
            var subdivisionIdsByForeman = @json($subdivisionIdsByForeman ?? []);

            if (!responsibleSelect || !subdivisionSelect) {
                return;
            }

            var originalOptions = Array.prototype.slice.call(subdivisionSelect.options).map(function(option) {
                return {
                    value: option.value,
                    text: option.text,
                    selected: option.selected
                };
            });

            function renderSubdivisionOptions() {
                var selectedResponsible = responsibleSelect.value || '';
                var allowedIds = subdivisionIdsByForeman[selectedResponsible] || null;
                var currentValue = subdivisionSelect.value;
                var nextValue = currentValue;

                subdivisionSelect.innerHTML = '';

                originalOptions.forEach(function(option) {
                    if (option.value === '') {
                        subdivisionSelect.add(new Option(option.text, option.value));
                        return;
                    }
                    if (Array.isArray(allowedIds) && allowedIds.indexOf(option.value) === -1) {
                        return;
                    }
                    subdivisionSelect.add(new Option(option.text, option.value));
                });

                var hasCurrentValue = Array.prototype.some.call(subdivisionSelect.options, function(option) {
                    return option.value === currentValue;
                });
                if (!hasCurrentValue) {
                    nextValue = '';
                }
                subdivisionSelect.value = nextValue;
                subdivisionSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }

            responsibleSelect.addEventListener('change', renderSubdivisionOptions);
            renderSubdivisionOptions();
        })();
    </script>

    <script>
        (function() {
            var container = document.getElementById('equipment-items');
            var tplList = document.getElementById('equipment-row-from-list-tpl').innerHTML;
            var tplCustom = document.getElementById('equipment-row-custom-tpl').innerHTML;
            var nextIndex = container.querySelectorAll('.equipment-row').length;
            var equipmentMap = {};
            var equipmentList = [];
            @foreach($equipment as $eq)
            equipmentMap[@json(mb_strtolower($eq->name))] = @json((string) $eq->id);
            equipmentList.push({ id: @json((string) $eq->id), name: @json($eq->name), key: @json(mb_strtolower($eq->name)) });
            @endforeach
            var measurementUnits = @json($measurementMeta['unitsByType'] ?? ['piece' => ['шт']]);

            function bindSearchInputs() {
                container.querySelectorAll('.equipment-search').forEach(function(input) {
                    if (input.dataset.bound === '1') {
                        return;
                    }
                    var sync = function() {
                        var row = input.closest('.equipment-row');
                        if (!row) {
                            return;
                        }
                        var hidden = row.querySelector('.equipment-type-id');
                        if (!hidden) {
                            return;
                        }
                        var key = (input.value || '').trim().toLowerCase();
                        hidden.value = equipmentMap[key] || '';
                    };
                    var renderSuggestions = function() {
                        var row = input.closest('.equipment-row');
                        if (!row) {
                            return;
                        }
                        var box = row.querySelector('.equipment-suggestions');
                        if (!box) {
                            return;
                        }
                        var query = (input.value || '').trim().toLowerCase();
                        if (query.length < 2) {
                            box.innerHTML = '';
                            box.classList.add('hidden');
                            return;
                        }

                        var matches = equipmentList.filter(function(item) {
                            return item.key.indexOf(query) !== -1;
                        }).slice(0, 8);

                        if (matches.length === 0) {
                            box.innerHTML = '';
                            box.classList.add('hidden');
                            return;
                        }

                        box.innerHTML = matches.map(function(item) {
                            return '<button type="button" class="equipment-suggestion-item app-suggestion-btn" data-id="' + item.id + '" data-name="' + item.name.replace(/"/g, '&quot;') + '">' + item.name + '</button>';
                        }).join('');
                        box.classList.remove('hidden');

                        box.querySelectorAll('.equipment-suggestion-item').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                input.value = btn.dataset.name || '';
                                sync();
                                box.innerHTML = '';
                                box.classList.add('hidden');
                            });
                        });
                    };
                    input.addEventListener('input', sync);
                    input.addEventListener('input', renderSuggestions);
                    input.addEventListener('change', sync);
                    input.addEventListener('focus', renderSuggestions);
                    input.addEventListener('blur', function() {
                        setTimeout(function() {
                            var row = input.closest('.equipment-row');
                            var box = row ? row.querySelector('.equipment-suggestions') : null;
                            if (!box) {
                                return;
                            }
                            box.innerHTML = '';
                            box.classList.add('hidden');
                        }, 120);
                    });
                    input.dataset.bound = '1';
                });
            }

            function bindRemoveButtons() {
                container.querySelectorAll('.remove-item').forEach(function(btn) {
                    btn.onclick = removeHandler;
                });
            }

            function syncMeasurementRow(row) {
                var typeSelect = row.querySelector('.measurement-type');
                var unitSelect = row.querySelector('.measurement-unit');
                if (!typeSelect || !unitSelect) return;
                var selectedType = typeSelect.value || 'piece';
                var options = measurementUnits[selectedType] || measurementUnits.piece;
                var current = unitSelect.dataset.current || unitSelect.value || options[0];
                var qtyLabel = row.querySelector('.quantity-wrap label');
                var sizeWrap = row.querySelector('.size-value-wrap');
                unitSelect.innerHTML = '';
                options.forEach(function(u) {
                    var opt = new Option(u, u);
                    if (u === current) opt.selected = true;
                    unitSelect.add(opt);
                });
                if (!options.includes(current)) {
                    unitSelect.value = options[0];
                }
                unitSelect.dataset.current = unitSelect.value;

                if (qtyLabel) {
                    qtyLabel.textContent = selectedType === 'length' ? 'Сколько нужно' : 'Кол-во';
                }
                if (sizeWrap) {
                    sizeWrap.classList.toggle('hidden', selectedType !== 'clothing_size');
                }
            }

            function bindMeasurementInputs() {
                container.querySelectorAll('.measurement-type').forEach(function(select) {
                    if (select.dataset.bound === '1') return;
                    select.addEventListener('change', function() {
                        var row = select.closest('.equipment-row');
                        if (!row) return;
                        var unitSelect = row.querySelector('.measurement-unit');
                        if (unitSelect) unitSelect.dataset.current = '';
                        syncMeasurementRow(row);
                    });
                    select.dataset.bound = '1';
                });
                container.querySelectorAll('.measurement-unit').forEach(function(select) {
                    if (select.dataset.bound === '1') return;
                    select.addEventListener('change', function() {
                        select.dataset.current = select.value;
                    });
                    select.dataset.bound = '1';
                });
                container.querySelectorAll('.equipment-row').forEach(syncMeasurementRow);
            }

            function appendFromTemplate(tpl) {
                var html = tpl.replace(/__INDEX__/g, nextIndex++);
                container.insertAdjacentHTML('beforeend', html);
                bindRemoveButtons();
                bindSearchInputs();
                bindMeasurementInputs();
            }

            document.getElementById('add-equipment-from-list').addEventListener('click', function() {
                appendFromTemplate(tplList);
            });

            document.getElementById('add-equipment-custom').addEventListener('click', function() {
                appendFromTemplate(tplCustom);
            });

            function removeHandler() {
                var row = this.closest('.equipment-row');
                if (container.querySelectorAll('.equipment-row').length > 1) {
                    row.remove();
                }
            }

            bindRemoveButtons();
            bindSearchInputs();
            bindMeasurementInputs();

            var addCommercialOfferBtn = document.getElementById('add-commercial-offer-btn');
            var removeCommercialOfferBtn = document.getElementById('remove-commercial-offer-btn');
            var commercialOfferBlock = document.getElementById('commercial-offer-block');
            var attachCommercialOfferInput = document.getElementById('attach-commercial-offer-input');
            if (addCommercialOfferBtn && commercialOfferBlock && attachCommercialOfferInput) {
                addCommercialOfferBtn.addEventListener('click', function() {
                    commercialOfferBlock.classList.remove('hidden');
                    attachCommercialOfferInput.value = '1';
                    addCommercialOfferBtn.classList.add('hidden');
                });
                if (!commercialOfferBlock.classList.contains('hidden')) {
                    addCommercialOfferBtn.classList.add('hidden');
                }
            }
            if (removeCommercialOfferBtn && commercialOfferBlock && attachCommercialOfferInput && addCommercialOfferBtn) {
                removeCommercialOfferBtn.addEventListener('click', function() {
                    commercialOfferBlock.classList.add('hidden');
                    attachCommercialOfferInput.value = '0';
                    addCommercialOfferBtn.classList.remove('hidden');
                    var fileInput = document.getElementById('commercial_offer');
                    if (fileInput) {
                        fileInput.value = '';
                    }
                });
            }

        })();
    </script>
</x-app-layout>
