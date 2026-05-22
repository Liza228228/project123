<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.show', $application)">Просмотр заявки</x-page-header-nav>
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
        $equipmentNameMax = \App\Models\ApplicationItem::EQUIPMENT_NAME_MAX_LENGTH;
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
                                    $locked = (bool) ($dbItem && $application->itemLineIsApproved($dbItem->id) && ! ($managementMayEditBoilerApprovedEquipment ?? false));
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
                                    @php
                                        $cmt = trim((string) old('items.'.$idx.'.measurement_type', $item['measurement_type'] ?? ''));
                                        $typeChosen = $cmt !== '';
                                        $cqu = old('items.'.$idx.'.quantity_unit', $item['quantity_unit'] ?? 'шт');
                                        $csv = old('items.'.$idx.'.size_value', $item['size_value'] ?? '');
                                        $cClothing = $typeChosen && $cmt === 'clothing_size';
                                        $customAmtLabel = match ($cmt) {
                                            'length' => 'Длина, '.$cqu,
                                            'mass' => 'Масса, '.$cqu,
                                            'clothing_size' => 'Количество, шт',
                                            default => $typeChosen ? 'Количество, '.$cqu : 'Количество',
                                        };
                                    @endphp
                                    <div class="equipment-row equipment-row--custom equipment-row--editable app-equipment-card">
                                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Своё название</span>
                                            <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="" />
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                                            <div class="md:col-span-12">
                                                <label class="app-form-label !normal-case">Наименование</label>
                                                <input type="text" name="items[{{ $idx }}][equipment_name]" value="{{ $eqName }}" placeholder="Как в заявке у поставщика" maxlength="{{ $equipmentNameMax }}" class="custom-equipment-input app-input" />
                                                <p class="mt-1 text-[11px] text-stone-500 dark:text-stone-400">Не более {{ $equipmentNameMax }} символов. Цепочка: согласование → «Заказано» → «На складе».</p>
                                                <x-input-error :messages="$errors->get('items.'.$idx.'.equipment_name')" class="mt-1.5" />
                                            </div>
                                            <div class="custom-type-wrap md:col-span-4 min-w-0">
                                                <label class="app-form-label !normal-case">Тип</label>
                                                <select name="items[{{ $idx }}][measurement_type]" class="measurement-type app-select">
                                                    <option value="" @selected(! $typeChosen)>Выберите тип</option>
                                                    @foreach(($measurementMeta['typeOptions'] ?? []) as $typeCode => $typeName)
                                                        <option value="{{ $typeCode }}" @selected($cmt === $typeCode)>{{ $typeName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="custom-amount-outer md:col-span-4 min-w-0 {{ $typeChosen ? '' : 'hidden' }}">
                                                <div class="custom-amount-block">
                                                    <label class="custom-amount-label app-form-label !normal-case">{{ $customAmtLabel }}</label>
                                                    <input type="hidden" name="items[{{ $idx }}][size_value]" value="{{ $cClothing ? $csv : '' }}" class="custom-size-value-field" />
                                                    <input type="number" value="{{ (int) ($item['quantity'] ?? 1) }}" min="1" step="1" class="custom-amount-number app-input {{ $typeChosen && ! $cClothing ? '' : ($cClothing ? '' : 'hidden') }}" @if($typeChosen) name="items[{{ $idx }}][quantity]" required @else disabled @endif />
                                                    <div class="custom-size-wrap {{ $cClothing ? 'mt-2' : 'hidden' }}">
                                                        <label class="custom-size-label app-form-label !normal-case">Размер</label>
                                                        <select class="custom-amount-size app-select" @if($typeChosen && $cClothing) required @else disabled @endif>
                                                            <option value="" @selected($csv === '')>Выберите размер</option>
                                                            @foreach(($measurementMeta['clothingSizes'] ?? []) as $sz)
                                                                <option value="{{ $sz }}" @selected($csv === $sz)>{{ $sz }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="custom-unit-wrap md:col-span-4 min-w-0 {{ $typeChosen ? '' : 'hidden' }}">
                                                <label class="app-form-label !normal-case">Ед.</label>
                                                <select @if($typeChosen) name="items[{{ $idx }}][quantity_unit]" @endif class="measurement-unit app-select" data-current="{{ $typeChosen ? $cqu : '' }}" @if(! $typeChosen) disabled @endif></select>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    @php
                                        $selectedEq = ($typeId ?? '') !== '' ? $equipment->firstWhere('id', (int) $typeId) : null;
                                        $mt = old('items.'.$idx.'.measurement_type', $item['measurement_type'] ?? ($selectedEq?->measurementUnit?->unitType?->code ?? 'piece'));
                                        $qu = old('items.'.$idx.'.quantity_unit', $item['quantity_unit'] ?? ($selectedEq?->measurementUnit?->code ?? 'шт'));
                                        $sv = old('items.'.$idx.'.size_value', $item['size_value'] ?? '');
                                        $isClothing = $mt === 'clothing_size';
                                        $listAmtLabel = match ($mt) {
                                            'length' => 'Длина, '.$qu,
                                            'mass' => 'Масса, '.$qu,
                                            'clothing_size' => 'Количество, шт',
                                            default => 'Количество, '.$qu,
                                        };
                                    @endphp
                                    <div class="equipment-row equipment-row--list equipment-row--editable app-equipment-card">
                                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-stone-200/80 pb-2 dark:border-stone-600/80">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">Из справочника</span>
                                            <button type="button" class="remove-item inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-stone-200/80 text-stone-500 transition active:scale-95 hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-stone-600 dark:text-stone-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="Удалить позицию" aria-label="Удалить позицию">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="" />
                                        <input type="hidden" name="items[{{ $idx }}][measurement_type]" value="{{ $mt }}" class="list-measurement-type-field" />
                                        <input type="hidden" name="items[{{ $idx }}][quantity_unit]" value="{{ $qu }}" class="list-quantity-unit-field" />
                                        <input type="hidden" name="items[{{ $idx }}][size_value]" value="{{ $isClothing ? $sv : '' }}" class="list-size-value-field" />
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
                                                        value="{{ $selectedEq?->name ?? '' }}"
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
                                            <div class="list-amount-block md:col-span-3">
                                                <label class="list-amount-label app-form-label !normal-case">{{ $listAmtLabel }}</label>
                                                <input type="number" value="{{ (int) ($item['quantity'] ?? 1) }}" min="1" step="1" name="items[{{ $idx }}][quantity]" required class="list-amount-number app-input" />
                                                <div class="list-size-wrap {{ $isClothing ? 'mt-2' : 'hidden' }}">
                                                    <label class="list-size-label app-form-label !normal-case">Размер</label>
                                                    <select class="list-amount-size app-select" @if($isClothing) required @else disabled @endif>
                                                        <option value="" @selected($sv === '')>Выберите размер</option>
                                                        @foreach(($measurementMeta['clothingSizes'] ?? []) as $sz)
                                                            <option value="{{ $sz }}" @selected($sv === $sz)>{{ $sz }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
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
                        @if (($usesDraftSubmitFlow ?? false) && ($isCreatorDraft ?? false))
                            <button type="submit" name="submit_action" value="save"
                                    class="ui-btn ui-btn--primary ui-btn--lg w-full text-base sm:w-auto">
                                Сохранить
                            </button>
                        @else
                            <button type="submit" class="ui-btn ui-btn--primary ui-btn--lg w-full text-base sm:w-auto">
                                Сохранить изменения
                            </button>
                        @endif
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
            <input type="hidden" name="items[__INDEX__][measurement_type]" value="piece" class="list-measurement-type-field" />
            <input type="hidden" name="items[__INDEX__][quantity_unit]" value="шт" class="list-quantity-unit-field" />
            <input type="hidden" name="items[__INDEX__][size_value]" value="" class="list-size-value-field" />
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
                <div class="list-amount-block md:col-span-3">
                    <label class="list-amount-label app-form-label !normal-case">Количество, шт</label>
                    <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" step="1" class="list-amount-number app-input" required />
                    <div class="list-size-wrap hidden mt-2">
                        <label class="list-size-label app-form-label !normal-case">Размер</label>
                        <select class="list-amount-size app-select" disabled>
                        <option value="">Выберите размер</option>
                        @foreach(($measurementMeta['clothingSizes'] ?? []) as $sz)
                            <option value="{{ $sz }}">{{ $sz }}</option>
                        @endforeach
                        </select>
                    </div>
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
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                <div class="md:col-span-12">
                    <label class="app-form-label !normal-case">Наименование</label>
                    <input type="text" name="items[__INDEX__][equipment_name]" placeholder="Как в заявке у поставщика" maxlength="{{ $equipmentNameMax }}" class="custom-equipment-input app-input" />
                    <p class="mt-1 text-[11px] text-stone-500 dark:text-stone-400">Не более {{ $equipmentNameMax }} символов. Цепочка: согласование → «Заказано» → «На складе».</p>
                </div>
                <div class="custom-type-wrap md:col-span-4 min-w-0">
                    <label class="app-form-label !normal-case">Тип</label>
                    <select name="items[__INDEX__][measurement_type]" class="measurement-type app-select">
                        <option value="" selected>Выберите тип</option>
                        @foreach(($measurementMeta['typeOptions'] ?? []) as $typeCode => $typeName)
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
                                @foreach(($measurementMeta['clothingSizes'] ?? []) as $sz)
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
            @php
                $eqUt = (string) ($eq->measurementUnit?->unitType?->code ?? 'piece');
                $eqUc = (string) ($eq->measurementUnit?->code ?? 'шт');
            @endphp
            equipmentMap[@json(mb_strtolower($eq->name))] = @json((string) $eq->id);
            equipmentList.push({
                id: @json((string) $eq->id),
                name: @json($eq->name),
                key: @json(mb_strtolower($eq->name)),
                unitType: @json($eqUt),
                unitCode: @json($eqUc)
            });
            @endforeach
            var measurementUnits = @json($measurementMeta['unitsByType'] ?? ['piece' => ['шт']]);
            var catalogById = @json($measurementMeta['catalogById'] ?? []);
            var DUPLICATE_EQUIPMENT_MSG = 'Нельзя добавить две строки с одним и тем же наименованием и размером.';
            var DUPLICATE_EQUIPMENT_GENERIC_MSG = 'Нельзя добавить одно и то же оборудование дважды.';

            function catalogIdForFreeTextLabel(raw) {
                var text = String(raw || '').trim();
                if (!text) {
                    return '';
                }
                var lower = text.toLowerCase();
                var hit = equipmentList.find(function(item) {
                    return item.name && String(item.name).toLowerCase() === lower;
                });
                if (hit) {
                    return String(hit.id);
                }
                var codeTail = text.match(/^\s*[\w\d.-]+\s*[—–-]\s*(.+)$/u);
                if (codeTail) {
                    var tail = codeTail[1].trim().toLowerCase();
                    hit = equipmentList.find(function(item) {
                        return item.name && String(item.name).toLowerCase() === tail;
                    });
                    if (hit) {
                        return String(hit.id);
                    }
                }
                return '';
            }

            function equipmentIdInputForRow(row) {
                return row.querySelector('.equipment-type-id') || row.querySelector('input[name*="[equipment_id]"]');
            }

            function clothingSizeFromRow(row) {
                if (!isSizeMeasurementType(measurementTypeFromRow(row))) {
                    return '';
                }
                var sizeHidden = row.querySelector('.list-size-value-field') || row.querySelector('.custom-size-value-field');
                if (sizeHidden && sizeHidden.value) {
                    return String(sizeHidden.value).trim();
                }
                var sel = row.querySelector('.list-amount-size') || row.querySelector('.custom-amount-size');
                return sel && !sel.disabled ? String(sel.value || '').trim() : '';
            }

            function duplicateKeyForEquipmentRow(row) {
                var hiddenId = equipmentIdInputForRow(row);
                var catalogId = hiddenId ? String(hiddenId.value || '').trim() : '';
                var baseKey = null;
                if (catalogId) {
                    baseKey = 'catalog:' + catalogId;
                } else {
                    var nameInput = row.querySelector('.custom-equipment-input');
                    var searchInput = row.querySelector('.equipment-search');
                    var raw = nameInput
                        ? String(nameInput.value || '').trim()
                        : (searchInput ? String(searchInput.value || '').trim() : '');
                    if (!raw) {
                        return null;
                    }
                    var matchedCatalogId = catalogIdForFreeTextLabel(raw);
                    baseKey = matchedCatalogId ? 'catalog:' + matchedCatalogId : 'custom:' + raw.toLowerCase();
                }
                if (isSizeMeasurementType(measurementTypeFromRow(row))) {
                    var size = clothingSizeFromRow(row);
                    if (size) {
                        return baseKey + ':size:' + size.toUpperCase();
                    }
                }

                return baseKey;
            }

            function nameInputForEquipmentRow(row) {
                return row.querySelector('.custom-equipment-input') || row.querySelector('.equipment-search');
            }

            function errorHostForNameInput(input) {
                if (!input) {
                    return null;
                }
                if (input.classList.contains('equipment-search')) {
                    return input.closest('.md\\:col-span-9') || (input.parentElement && input.parentElement.parentElement);
                }
                return input.closest('.md\\:col-span-12') || input.parentElement;
            }

            function setDuplicateErrorOnInput(input, message) {
                if (!input) {
                    return;
                }
                var host = errorHostForNameInput(input);
                if (!host) {
                    return;
                }
                var err = host.querySelector('.equipment-line-duplicate-error');
                if (!message) {
                    if (err) {
                        err.remove();
                    }
                    input.classList.remove('border-red-400', 'dark:border-red-500');
                    input.removeAttribute('aria-invalid');
                    input.removeAttribute('aria-describedby');
                    return;
                }
                if (!err) {
                    err = document.createElement('p');
                    err.className = 'equipment-line-duplicate-error mt-1.5 text-sm text-red-600 dark:text-red-400';
                    err.setAttribute('role', 'alert');
                    err.id = 'eq-dup-err-' + Math.random().toString(36).slice(2, 9);
                    host.appendChild(err);
                }
                err.textContent = message;
                input.classList.add('border-red-400', 'dark:border-red-500');
                input.setAttribute('aria-invalid', 'true');
                input.setAttribute('aria-describedby', err.id);
            }

            function refreshDuplicateEquipmentErrors() {
                var rows = container.querySelectorAll('.equipment-row');
                var keyToInputs = {};
                rows.forEach(function(row) {
                    var input = nameInputForEquipmentRow(row);
                    setDuplicateErrorOnInput(input, '');
                    var key = duplicateKeyForEquipmentRow(row);
                    if (!key || !input) {
                        return;
                    }
                    if (!keyToInputs[key]) {
                        keyToInputs[key] = [];
                    }
                    keyToInputs[key].push(input);
                });
                Object.keys(keyToInputs).forEach(function(key) {
                    var inputs = keyToInputs[key];
                    if (inputs.length >= 2) {
                        var msg = key.indexOf(':size:') !== -1 ? DUPLICATE_EQUIPMENT_MSG : DUPLICATE_EQUIPMENT_GENERIC_MSG;
                        inputs.forEach(function(inp) {
                            setDuplicateErrorOnInput(inp, msg);
                        });
                    }
                });
            }

            function bindDuplicateEquipmentChecks(root) {
                var scope = root || container;
                scope.querySelectorAll('.custom-equipment-input, .equipment-search, .list-amount-size, .custom-amount-size, .list-amount-number, .custom-amount-number').forEach(function(input) {
                    if (input.dataset.duplicateBound === '1') {
                        return;
                    }
                    input.dataset.duplicateBound = '1';
                    input.addEventListener('input', refreshDuplicateEquipmentErrors);
                    input.addEventListener('change', refreshDuplicateEquipmentErrors);
                });
            }

            function syncCatalogSearchHiddenId(input) {
                var row = input.closest('.equipment-row');
                if (!row) {
                    return;
                }
                var hidden = row.querySelector('.equipment-type-id');
                if (!hidden) {
                    return;
                }
                var raw = String(input.value || '').trim();
                if (raw === '') {
                    hidden.value = '';
                    return;
                }
                var key = raw.toLowerCase();
                hidden.value = equipmentMap[key] || '';
                if (!hidden.value) {
                    var matched = catalogIdForFreeTextLabel(raw);
                    if (matched) {
                        hidden.value = matched;
                    }
                }
            }

            function syncAllCatalogSearchHiddenIds() {
                container.querySelectorAll('.equipment-search').forEach(function(input) {
                    syncCatalogSearchHiddenId(input);
                    var row = input.closest('.equipment-row');
                    if (row && row.classList.contains('equipment-row--list')) {
                        syncListRowFromEquipmentId(row);
                    }
                });
            }

            function isSizeMeasurementType(type) {
                return type === 'clothing_size';
            }

            function measurementTypeFromRow(row) {
                var custom = row.querySelector('.measurement-type');
                if (custom && custom.value) {
                    return (custom.value || '').trim();
                }
                var hidden = row.querySelector('.list-measurement-type-field');
                if (hidden && hidden.value) {
                    return (hidden.value || '').trim();
                }
                var idInput = equipmentIdInputForRow(row);
                if (idInput && idInput.value) {
                    var meta = metaForEquipmentId(idInput.value);
                    if (meta && meta.unitType) {
                        return meta.unitType;
                    }
                }
                return 'piece';
            }

            function pieceQtyDigitsOnly(raw) {
                return String(raw || '').replace(/[^\d]/g, '');
            }

            function attachPieceQuantityGuards(input, row) {
                if (!input) {
                    return;
                }
                function rowType() {
                    return measurementTypeFromRow(row);
                }
                function guardValue() {
                    if (rowType() !== 'piece') {
                        return;
                    }
                    var digits = pieceQtyDigitsOnly(input.value);
                    if (digits !== String(input.value)) {
                        input.value = digits;
                    }
                }
                if (input.dataset.pieceQtyGuard !== '1') {
                    input.dataset.pieceQtyGuard = '1';
                    input.addEventListener('keydown', function(e) {
                        if (rowType() !== 'piece') {
                            return;
                        }
                        if (e.key === '.' || e.key === ',' || e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-') {
                            e.preventDefault();
                        }
                    });
                    input.addEventListener('paste', function(e) {
                        if (rowType() !== 'piece') {
                            return;
                        }
                        e.preventDefault();
                        var t = (e.clipboardData || window.clipboardData).getData('text') || '';
                        input.value = pieceQtyDigitsOnly(t);
                    });
                    input.addEventListener('input', guardValue);
                    input.addEventListener('blur', function() {
                        if (rowType() !== 'piece') {
                            return;
                        }
                        var v = pieceQtyDigitsOnly(input.value);
                        input.value = v === '' ? '1' : v;
                    });
                }
                guardValue();
            }

            function syncQuantityInputsForRow(row, measurementType) {
                var type = measurementType || measurementTypeFromRow(row);
                row.querySelectorAll('.custom-amount-number, .list-amount-number').forEach(function(num) {
                    if (type === 'piece') {
                        num.type = 'text';
                        num.setAttribute('inputmode', 'numeric');
                        num.setAttribute('pattern', '[0-9]*');
                        num.setAttribute('autocomplete', 'off');
                        num.removeAttribute('step');
                        num.removeAttribute('min');
                        attachPieceQuantityGuards(num, row);
                    } else {
                        num.type = 'number';
                        num.setAttribute('inputmode', 'decimal');
                        num.step = '0.001';
                        num.min = '0.001';
                        num.removeAttribute('pattern');
                    }
                });
            }

            function itemsIndexFromRow(row) {
                var el = row.querySelector('.list-measurement-type-field');
                if (!el || !el.name) {
                    return null;
                }
                var m = /items\[(\d+)\]/.exec(el.name);
                return m ? m[1] : null;
            }

            function listAmountLabel(type, unit) {
                var u = unit || 'шт';
                if (type === 'length') {
                    return 'Длина, ' + u;
                }
                if (type === 'mass') {
                    return 'Масса, ' + u;
                }
                if (type === 'clothing_size') {
                    return 'Количество, шт';
                }
                return 'Количество, ' + u;
            }

            function metaForEquipmentId(id) {
                if (!id) {
                    return null;
                }
                var fromServer = catalogById[id];
                if (fromServer) {
                    return { unitType: fromServer.unitType || 'piece', unitCode: fromServer.unitCode || 'шт' };
                }
                var found = equipmentList.find(function(x) {
                    return x.id === id;
                });
                if (found) {
                    return { unitType: found.unitType || 'piece', unitCode: found.unitCode || 'шт' };
                }
                return null;
            }

            function applyListCatalogMeta(row, meta) {
                var typeField = row.querySelector('.list-measurement-type-field');
                var unitField = row.querySelector('.list-quantity-unit-field');
                var label = row.querySelector('.list-amount-label');
                var num = row.querySelector('.list-amount-number');
                var sel = row.querySelector('.list-amount-size');
                var sizeWrap = row.querySelector('.list-size-wrap');
                var sizeHidden = row.querySelector('.list-size-value-field');
                if (!typeField || !unitField || !label || !num || !sel || !sizeHidden) {
                    return;
                }

                var type = 'piece';
                var unit = 'шт';
                if (meta && meta.unitType) {
                    type = meta.unitType;
                    unit = meta.unitCode || 'шт';
                }
                typeField.value = type;
                unitField.value = unit;
                label.textContent = listAmountLabel(type, unit);

                var idx = itemsIndexFromRow(row);

                if (type === 'clothing_size') {
                    num.classList.remove('hidden');
                    num.removeAttribute('disabled');
                    num.required = true;
                    if (idx !== null) {
                        num.setAttribute('name', 'items[' + idx + '][quantity]');
                    }
                    if (sizeWrap) {
                        sizeWrap.classList.remove('hidden');
                    }
                    sel.removeAttribute('disabled');
                    sel.required = true;
                    if (sel.value) {
                        sizeHidden.value = sel.value;
                    } else if (sizeHidden.value) {
                        sel.value = sizeHidden.value;
                    } else {
                        sizeHidden.value = '';
                    }
                } else {
                    if (sizeWrap) {
                        sizeWrap.classList.add('hidden');
                    }
                    sel.required = false;
                    sel.setAttribute('disabled', 'disabled');
                    sel.value = '';
                    num.classList.remove('hidden');
                    num.removeAttribute('disabled');
                    num.required = true;
                    if (idx !== null) {
                        num.setAttribute('name', 'items[' + idx + '][quantity]');
                    }
                    sizeHidden.value = '';
                }
                syncQuantityInputsForRow(row, type);
            }

            function syncListRowFromEquipmentId(row) {
                var hiddenId = row.querySelector('.equipment-type-id');
                var id = hiddenId ? String(hiddenId.value || '').trim() : '';
                applyListCatalogMeta(row, metaForEquipmentId(id));
            }

            if (container.dataset.listSizeBound !== '1') {
                container.addEventListener('change', function(e) {
                    var t = e.target;
                    if (!t || !t.classList) {
                        return;
                    }
                    if (t.classList.contains('list-amount-size')) {
                        var rowList = t.closest('.equipment-row--list');
                        if (!rowList) {
                            return;
                        }
                        var sizeHiddenList = rowList.querySelector('.list-size-value-field');
                        if (sizeHiddenList) {
                            sizeHiddenList.value = t.value || '';
                        }
                        refreshDuplicateEquipmentErrors();
                        return;
                    }
                    if (t.classList.contains('custom-amount-size')) {
                        var rowCustom = t.closest('.equipment-row--custom');
                        if (!rowCustom) {
                            return;
                        }
                        var sizeHiddenCustom = rowCustom.querySelector('.custom-size-value-field');
                        if (sizeHiddenCustom) {
                            sizeHiddenCustom.value = t.value || '';
                        }
                        refreshDuplicateEquipmentErrors();
                    }
                });
                container.dataset.listSizeBound = '1';
            }

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
                        syncCatalogSearchHiddenId(input);
                        if (row.classList.contains('equipment-row--list')) {
                            syncListRowFromEquipmentId(row);
                        }
                        refreshDuplicateEquipmentErrors();
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
                                refreshDuplicateEquipmentErrors();
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

            function itemsIndexFromFormRow(row) {
                var el = row.querySelector('select.measurement-type');
                if (!el || !el.name) {
                    return null;
                }
                var m = /items\[(\d+)\]/.exec(el.name);
                return m ? m[1] : null;
            }

            function syncMeasurementRow(row) {
                var typeSelect = row.querySelector('.measurement-type');
                var unitSelect = row.querySelector('.measurement-unit');
                var amountOuter = row.querySelector('.custom-amount-outer');
                var unitWrap = row.querySelector('.custom-unit-wrap');
                if (!typeSelect || !unitSelect) {
                    return;
                }
                if (!row.classList.contains('equipment-row--custom')) {
                    return;
                }
                var selectedType = (typeSelect.value || '').trim();
                var idx = itemsIndexFromFormRow(row);

                var num = row.querySelector('.custom-amount-number');
                var hid = row.querySelector('.custom-amount-qty-clothing');
                var sel = row.querySelector('.custom-amount-size');
                var sizeHidden = row.querySelector('.custom-size-value-field');

                function disableCustomQuantityAndUnit() {
                    if (amountOuter) {
                        amountOuter.classList.add('hidden');
                    }
                    if (unitWrap) {
                        unitWrap.classList.add('hidden');
                    }
                    unitSelect.innerHTML = '';
                    unitSelect.setAttribute('disabled', 'disabled');
                    unitSelect.removeAttribute('name');
                    unitSelect.required = false;
                    unitSelect.dataset.current = '';
                    if (!num || !hid || !sel || !sizeHidden) {
                        return;
                    }
                    num.removeAttribute('name');
                    num.required = false;
                    num.disabled = true;
                    hid.removeAttribute('name');
                    hid.setAttribute('disabled', 'disabled');
                    hid.classList.add('hidden');
                    sel.classList.add('hidden');
                    sel.required = false;
                    sel.setAttribute('disabled', 'disabled');
                    sel.value = '';
                    sizeHidden.value = '';
                }

                if (!selectedType) {
                    disableCustomQuantityAndUnit();
                    return;
                }

                if (amountOuter) {
                    amountOuter.classList.remove('hidden');
                }
                if (unitWrap) {
                    unitWrap.classList.remove('hidden');
                }
                unitSelect.removeAttribute('disabled');
                if (idx !== null) {
                    unitSelect.setAttribute('name', 'items[' + idx + '][quantity_unit]');
                }

                var options = measurementUnits[selectedType] || measurementUnits.piece;
                var current = unitSelect.dataset.current || unitSelect.value || options[0];
                unitSelect.innerHTML = '';
                options.forEach(function(u) {
                    var opt = new Option(u, u);
                    if (u === current) {
                        opt.selected = true;
                    }
                    unitSelect.add(opt);
                });
                if (!options.includes(current)) {
                    unitSelect.value = options[0];
                }
                unitSelect.dataset.current = unitSelect.value;
                var unitCode = unitSelect.value || options[0];

                var label = row.querySelector('.custom-amount-label');
                if (label) {
                    var u = unitCode || 'шт';
                    if (selectedType === 'length') {
                        label.textContent = 'Длина, ' + u;
                    } else if (selectedType === 'mass') {
                        label.textContent = 'Масса, ' + u;
                    } else if (selectedType === 'clothing_size') {
                        label.textContent = 'Количество, шт';
                    } else {
                        label.textContent = 'Количество, ' + u;
                    }
                }

                var sizeWrap = row.querySelector('.custom-size-wrap');
                if (!num || !sel || !sizeHidden) {
                    return;
                }

                if (selectedType === 'clothing_size') {
                    num.classList.remove('hidden');
                    num.removeAttribute('disabled');
                    num.required = true;
                    if (idx !== null) {
                        num.setAttribute('name', 'items[' + idx + '][quantity]');
                    }
                    if (sizeWrap) {
                        sizeWrap.classList.remove('hidden');
                    }
                    sel.removeAttribute('disabled');
                    sel.required = true;
                    if (sel.value) {
                        sizeHidden.value = sel.value;
                    } else if (sizeHidden.value) {
                        sel.value = sizeHidden.value;
                    } else {
                        sizeHidden.value = '';
                    }
                } else {
                    if (sizeWrap) {
                        sizeWrap.classList.add('hidden');
                    }
                    sel.required = false;
                    sel.setAttribute('disabled', 'disabled');
                    sel.value = '';
                    num.classList.remove('hidden');
                    num.removeAttribute('disabled');
                    num.required = true;
                    if (idx !== null) {
                        num.setAttribute('name', 'items[' + idx + '][quantity]');
                    }
                    sizeHidden.value = '';
                }
                syncQuantityInputsForRow(row, selectedType);
                refreshDuplicateEquipmentErrors();
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
                        var row = select.closest('.equipment-row');
                        if (row) {
                            syncMeasurementRow(row);
                        }
                    });
                    select.dataset.bound = '1';
                });
                container.querySelectorAll('.equipment-row--custom').forEach(syncMeasurementRow);
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
                var rows = container.querySelectorAll('.equipment-row');
                var lastRow = rows.length ? rows[rows.length - 1] : null;
                if (lastRow && lastRow.classList.contains('equipment-row--list')) {
                    applyListCatalogMeta(lastRow, { unitType: 'piece', unitCode: 'шт' });
                }
                bindDuplicateEquipmentChecks(container);
                refreshDuplicateEquipmentErrors();
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
                refreshDuplicateEquipmentErrors();
            }

            bindRemoveButtons();
            bindSearchInputs();
            bindMeasurementInputs();

            container.querySelectorAll('.equipment-row--list').forEach(function(row) {
                syncListRowFromEquipmentId(row);
                syncQuantityInputsForRow(row, measurementTypeFromRow(row));
            });
            container.querySelectorAll('.equipment-row--custom').forEach(function(row) {
                syncQuantityInputsForRow(row, measurementTypeFromRow(row));
            });

            bindDuplicateEquipmentChecks(container);
            syncAllCatalogSearchHiddenIds();
            refreshDuplicateEquipmentErrors();

            var editForm = document.getElementById('application-edit-form');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    syncAllCatalogSearchHiddenIds();
                    refreshDuplicateEquipmentErrors();
                    var firstErr = container.querySelector('.equipment-line-duplicate-error');
                    if (!firstErr) {
                        return;
                    }
                    e.preventDefault();
                    firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    var firstInvalid = container.querySelector('.custom-equipment-input[aria-invalid="true"], .equipment-search[aria-invalid="true"]');
                    if (firstInvalid) {
                        firstInvalid.focus({ preventScroll: true });
                    }
                });
            }
        })();
    </script>
</x-app-layout>
