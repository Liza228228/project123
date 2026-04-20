<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.index')">Заявки</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Изменить заявку
            </h2>
        </div>
    </x-slot>

    @php
        $defaultItems = $application->items
            ->sortBy(fn ($i) => $application->itemLineIsApproved($i->id) ? 0 : 1)
            ->values()
            ->map(fn ($i) => [
                'item_id' => $i->id,
                'equipment_id' => $i->equipment_id ?? '',
                'equipment_name' => $i->equipment_name ?? '',
                'quantity' => $i->quantity,
                'size_value' => $i->size_value ?? '',
                'measurement_type' => $i->measurement_type ?? 'piece',
                'quantity_unit' => $i->quantity_unit ?? 'шт',
            ])
            ->all();
        $items = old('items', $defaultItems);
        if (empty($items)) {
            $items = [[
                'item_id' => null,
                'equipment_id' => '',
                'equipment_name' => '',
                'quantity' => 1,
                'size_value' => '',
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ]];
        }
        $preserveSubdivisionIdsForFilter = collect([
            $application->subdivision_id,
            old('subdivision_id'),
        ])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();
    @endphp

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8">
                <div class="mb-6 rounded-xl border border-amber-200/80 bg-amber-50/50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100">
                    Позиции с отметкой «согласовано» только для просмотра. Остальные можно менять, удалять или дополнять. После сохранения заявки отметки согласования по позициям сбрасываются.
                </div>

                <form id="application-edit-form" method="POST" action="{{ route('applications.update', $application) }}" class="max-sm:pb-40 space-y-8 sm:space-y-10">
                    @csrf
                    @method('PUT')

                    <section class="space-y-4" aria-labelledby="edit-section-main">
                        <h3 id="edit-section-main" class="app-section-title">Основное</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="subdivision_id" class="app-form-label">Подразделение</label>
                                <select id="subdivision_id" name="subdivision_id" class="app-select" required>
                                    <option value="">Выберите подразделение</option>
                                    @foreach($subdivisions as $sub)
                                        <option value="{{ $sub->id }}" @selected(old('subdivision_id', $application->subdivision_id) == $sub->id)>{{ $sub->name }}</option>
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
                                            <option value="{{ $u->id }}" @selected(old('responsible_user_id', $application->responsible_user_id) == $u->id)>{{ $u->surname }} {{ $u->name }} {{ $u->patronymic }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('responsible_user_id')" class="mt-1.5" />
                                </div>
                            @else
                                <input type="hidden" name="responsible_user_id" value="{{ Auth::id() }}">
                            @endif

                            @if (Auth::user()->hasAnyRoleId([1, 6, 2]))
                                <div id="management-change-reason-block" class="sm:col-span-2 space-y-2 {{ (old('management_change_reason') || $errors->has('management_change_reason')) ? '' : 'hidden' }}">
                                    <label for="management_change_reason" class="app-form-label">Причина изменения</label>
                                    <textarea
                                        id="management_change_reason"
                                        name="management_change_reason"
                                        rows="3"
                                        maxlength="500"
                                        class="app-input min-h-[6rem] text-sm"
                                    >{{ old('management_change_reason') }}</textarea>
                                    <x-input-error :messages="$errors->get('management_change_reason')" class="mt-1.5" />
                                </div>
                            @endif

                            <div class="sm:col-span-2 rounded-xl border border-stone-200/80 bg-stone-50/70 px-4 py-3 text-sm text-stone-700 dark:border-stone-700/70 dark:bg-stone-900/40 dark:text-stone-200">
                                <p class="font-medium">Способ доставки</p>
                                <p class="mt-1">
                                    {{ $application->transportOption?->name ? 'Текущий способ: '.$application->transportOption->name : 'Указывается на этапе «Отметить всё как В пути».' }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="edit-section-equipment">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h3 id="edit-section-equipment" class="app-section-title">Оборудование</h3>
                                <p class="mt-1 max-w-2xl text-xs text-stone-500 dark:text-stone-400">
                                    Позиции из справочника или своё название. Своё: после согласования — «Заказано» → «На складе», приход в «Материалах».
                                </p>
                            </div>
                        </div>
                        <div id="equipment-items" class="space-y-4">
                            @foreach($items as $idx => $item)
                                @php
                                    $itemId = $item['item_id'] ?? null;
                                    $dbItem = $itemId !== null && $itemId !== '' ? $application->items->firstWhere('id', (int) $itemId) : null;
                                    $locked = (bool) ($dbItem && $application->itemLineIsApproved($dbItem->id));
                                    $typeId = $item['equipment_id'] ?? '';
                                    $eqName = trim($item['equipment_name'] ?? '');
                                    $isCustomRow = ! $locked && (($typeId === '' || $typeId === null) && $eqName !== '');
                                @endphp

                                @if($locked)
                                    <div class="equipment-row equipment-row--locked app-equipment-card border-emerald-200/70 dark:border-emerald-800/45">
                                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-emerald-800/90 dark:text-emerald-200/90">Согласовано</span>
                                        </div>
                                        <div class="space-y-2">
                                            <p class="text-sm font-medium text-stone-900 dark:text-stone-100">
                                                @if($typeId !== '' && $typeId !== null)
                                                    {{ $equipment->firstWhere('id', (int) $typeId)?->name ?? '—' }}
                                                @else
                                                    {{ $eqName !== '' ? $eqName : '—' }}
                                                @endif
                                                <span class="font-normal text-stone-600 dark:text-stone-400">× {{ (int) ($item['quantity'] ?? 1) }}</span>
                                            </p>
                                            @if($dbItem && $dbItem->usesFreeTextEquipment())
                                                @include('applications.partials.custom-equipment-supply-badge', ['item' => $dbItem])
                                            @endif
                                        </div>
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="{{ $typeId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="{{ $eqName }}" />
                                        <input type="hidden" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" />
                                        <input type="hidden" name="items[{{ $idx }}][measurement_type]" value="{{ $item['measurement_type'] ?? 'piece' }}" />
                                        <input type="hidden" name="items[{{ $idx }}][quantity_unit]" value="{{ $item['quantity_unit'] ?? 'шт' }}" />
                                        <input type="hidden" name="items[{{ $idx }}][size_value]" value="{{ $item['size_value'] ?? '' }}" />
                                    </div>
                                @elseif($isCustomRow)
                                    <div class="equipment-row equipment-row--custom equipment-row--editable app-equipment-card">
                                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Своё название</span>
                                            <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="" />
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-12 md:items-end">
                                            <div class="sm:col-span-2 md:col-span-6">
                                                <label class="app-form-label !normal-case">Наименование</label>
                                                <input type="text" name="items[{{ $idx }}][equipment_name]" value="{{ $eqName }}" placeholder="Как в заявке у поставщика" class="custom-equipment-input app-input" />
                                                <p class="mt-1 text-[11px] text-stone-500 dark:text-stone-400">Цепочка: согласование → «Заказано» → «На складе».</p>
                                            </div>
                                            <div class="quantity-wrap sm:col-span-1 md:col-span-2">
                                                <label class="app-form-label !normal-case">Количество</label>
                                                <input type="number" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" min="1" class="app-input" required />
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
                                    <div class="equipment-row equipment-row--list equipment-row--editable app-equipment-card">
                                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Из справочника</span>
                                            <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="" />
                                        <input type="hidden" name="items[{{ $idx }}][measurement_type]" value="{{ $item['measurement_type'] ?? 'piece' }}" />
                                        <input type="hidden" name="items[{{ $idx }}][quantity_unit]" value="{{ $item['quantity_unit'] ?? 'шт' }}" />
                                        <input type="hidden" name="items[{{ $idx }}][size_value]" value="{{ $item['size_value'] ?? '' }}" />
                                        @php
                                            $selectedType = ($typeId ?? '') !== '' ? $equipment->firstWhere('id', (int) $typeId) : null;
                                        @endphp
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                                            <div class="md:col-span-9 min-w-0">
                                                <label class="app-form-label !normal-case">Поиск в справочнике</label>
                                                <div class="relative">
                                                    <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                                    </span>
                                                    <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="{{ $typeId ?? '' }}" class="equipment-type-id" />
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
                                            <div class="quantity-wrap md:col-span-3">
                                                <label class="app-form-label !normal-case">Количество</label>
                                                <input type="number" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" min="1" class="app-input" required />
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
                        <x-input-error :messages="$errors->get('equipment')" class="mt-1.5" />
                    </section>

                    <section class="space-y-4 border-t border-stone-100 pt-8 dark:border-stone-800" aria-labelledby="edit-section-date">
                        <h3 id="edit-section-date" class="app-section-title">Срок</h3>
                        <div class="w-full max-w-full sm:max-w-xs">
                            <label for="desired_delivery_date" class="app-form-label">Желаемая дата поставки</label>
                            <input id="desired_delivery_date" type="date" name="desired_delivery_date" value="{{ old('desired_delivery_date', $application->desired_delivery_date?->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}" required class="app-input min-h-[3.25rem] sm:min-h-[2.75rem]" />
                            <x-input-error :messages="$errors->get('desired_delivery_date')" class="mt-1.5" />
                        </div>
                    </section>

                    <div class="app-form-actions-mobile">
                        <a href="{{ route('applications.index') }}" class="min-h-11 content-center text-center text-sm font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-200 sm:text-left">
                            Отмена и к списку заявок
                        </a>
                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--lg w-full text-base sm:w-auto">
                            Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/template" id="equipment-row-from-list-tpl">
        <div class="equipment-row equipment-row--list equipment-row--editable app-equipment-card">
            <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Из справочника</span>
                <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <input type="hidden" name="items[__INDEX__][item_id]" value="" />
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
        <div class="equipment-row equipment-row--custom equipment-row--editable app-equipment-card">
            <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Своё название</span>
                <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <input type="hidden" name="items[__INDEX__][item_id]" value="" />
            <input type="hidden" name="items[__INDEX__][equipment_id]" value="" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-12 md:items-end">
                <div class="sm:col-span-2 md:col-span-6">
                    <label class="app-form-label !normal-case">Наименование</label>
                    <input type="text" name="items[__INDEX__][equipment_name]" placeholder="Как в заявке у поставщика" class="custom-equipment-input app-input" />
                    <p class="mt-1 text-[11px] text-stone-500 dark:text-stone-400">Цепочка: согласование → «Заказано» → «На складе».</p>
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
            var managementReasonBlock = document.getElementById('management-change-reason-block');
            var managementReasonInput = document.getElementById('management_change_reason');
            var form = document.getElementById('application-edit-form');
            var managementReasonServerError = @json($errors->has('management_change_reason'));

            function trackedElements() {
                if (!form) {
                    return [];
                }
                return Array.prototype.slice.call(form.querySelectorAll(
                    'select[name="subdivision_id"], ' +
                    'select[name="responsible_user_id"], ' +
                    'select[name="transport_option_id"], ' +
                    'input[name="desired_delivery_date"], ' +
                    'input[name^="items["][name$="[item_id]"], ' +
                    'input[name^="items["][name$="[equipment_id]"], ' +
                    'input[name^="items["][name$="[equipment_name]"], ' +
                    'input[name^="items["][name$="[quantity]"]'
                ));
            }

            function buildSnapshot() {
                var data = trackedElements().map(function(el) {
                    return [el.name, (el.value || '').trim()];
                });
                data.sort(function(a, b) {
                    if (a[0] < b[0]) return -1;
                    if (a[0] > b[0]) return 1;
                    if (a[1] < b[1]) return -1;
                    if (a[1] > b[1]) return 1;
                    return 0;
                });
                return JSON.stringify(data);
            }

            var initialSnapshot = '';

            function syncManagementReasonVisibility() {
                if (!managementReasonBlock || !managementReasonInput) {
                    return;
                }
                if (managementReasonServerError) {
                    managementReasonBlock.classList.remove('hidden');
                    managementReasonInput.required = true;
                    return;
                }
                var changed = buildSnapshot() !== initialSnapshot;
                managementReasonBlock.classList.toggle('hidden', !changed);
                managementReasonInput.required = changed;
                if (!changed) {
                    managementReasonInput.value = '';
                }
            }

            if (form) {
                form.addEventListener('input', syncManagementReasonVisibility);
                form.addEventListener('change', syncManagementReasonVisibility);
            }

            var responsibleSelect = document.getElementById('responsible_user_id');
            var subdivisionSelect = document.getElementById('subdivision_id');
            var subdivisionIdsByForeman = @json($subdivisionIdsByForeman ?? []);
            var preserveSubdivisionIds = @json($preserveSubdivisionIdsForFilter);

            if (responsibleSelect && subdivisionSelect) {
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
                            if (preserveSubdivisionIds.indexOf(option.value) === -1) {
                                return;
                            }
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
            }

            initialSnapshot = buildSnapshot();

            if (form) {
                form.addEventListener('input', syncManagementReasonVisibility);
                form.addEventListener('change', syncManagementReasonVisibility);
            }
            syncManagementReasonVisibility();
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

            function countEditable() {
                return container.querySelectorAll('.equipment-row--editable').length;
            }
            function countLocked() {
                return container.querySelectorAll('.equipment-row--locked').length;
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
                    qtyLabel.textContent = selectedType === 'length' ? 'Сколько нужно' : 'Количество';
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

            function bindRemoveButtons() {
                container.querySelectorAll('.remove-item').forEach(function(btn) {
                    btn.onclick = removeHandler;
                });
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
                if (!row || !row.classList.contains('equipment-row--editable')) {
                    return;
                }
                var editable = countEditable();
                var locked = countLocked();
                if (editable <= 1 && locked === 0) {
                    return;
                }
                row.remove();
            }

            bindRemoveButtons();
            bindSearchInputs();
            bindMeasurementInputs();
        })();
    </script>
</x-app-layout>
