@php // шаблон страницы
@endphp
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
        $equipmentCatalogSearchMax = \App\Models\Equipment::NAME_MAX_LENGTH;
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
                @if ($foremanMayReviseRejectedByBoilerChiefOnly ?? false)
                    <div class="mb-6 rounded-xl border border-amber-200/80 bg-amber-50/70 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100">
                        Начальник котельной не согласовал часть позиций. Можно изменить только эти позиции. Согласованные позиции недоступны для редактирования. После правок отправьте заявку на повторное согласование.
                    </div>
                @endif

                <form id="application-edit-form" method="POST" action="{{ route('applications.update', $application) }}" enctype="multipart/form-data" class="max-sm:pb-40 space-y-8 sm:space-y-10" data-app-initial-subdivision="{{ (int) $application->subdivision_id }}" data-app-initial-delivery="{{ $application->desired_delivery_date?->format('Y-m-d') }}">
                    @csrf
                    @method('PUT')

                    <div id="removed-item-reasons-panel">
                        @if(is_array(old('removed_item_reasons')))
                            @foreach(old('removed_item_reasons') as $rid => $rText)
                                @if((string) $rid !== '' && trim((string) $rText) !== '')
                                    <input type="hidden" name="removed_item_reasons[{{ $rid }}]" value="{{ $rText }}" />
                                @endif
                            @endforeach
                        @endif
                    </div>

                    <section class="space-y-4" aria-labelledby="edit-section-main">
                        <h3 id="edit-section-main" class="app-section-title">Основное</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="subdivision_display" class="app-form-label">Подразделение</label>
                                <input type="hidden" name="subdivision_id" value="{{ (int) $application->subdivision_id }}" />
                                <select id="subdivision_display" class="app-select" disabled aria-readonly="true">
                                    @foreach($subdivisions as $sub)
                                        <option value="{{ $sub->id }}" @selected((int) $application->subdivision_id === (int) $sub->id)>{{ $sub->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('subdivision_id')" class="mt-1.5" />
                            </div>

                            @include('applications.partials.subdivision-warehouses-hint')

                            
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="edit-section-equipment">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h3 id="edit-section-equipment" class="app-section-title">Оборудование</h3>
                                <p class="mt-1 max-w-2xl text-xs text-stone-500 dark:text-stone-400">
                                    Позиции из справочника или своё название. 
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
                                                <input type="text" name="items[{{ $idx }}][equipment_name]" value="{{ $eqName }}" placeholder="" maxlength="{{ $equipmentNameMax }}" class="custom-equipment-input app-input" />
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
                                        @if($itemId && $dbItem && ! $locked)
                                            @include('applications.partials.item-field-change-reasons-inputs', [
                                                'itemId' => $itemId,
                                                'dbItem' => $dbItem,
                                                'locked' => $locked,
                                                'rowMode' => ($dbItem && $dbItem->equipment_id) ? 'list' : 'custom',
                                            ])
                                        @elseif(! $locked && ! $itemId)
                                            @include('applications.partials.item-new-line-change-reasons-inputs', ['idx' => $idx])
                                        @endif
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
                                                        maxlength="{{ $equipmentCatalogSearchMax }}"
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
                                        @if($itemId && $dbItem && ! $locked)
                                            @include('applications.partials.item-field-change-reasons-inputs', [
                                                'itemId' => $itemId,
                                                'dbItem' => $dbItem,
                                                'locked' => $locked,
                                                'rowMode' => ($dbItem && $dbItem->equipment_id) ? 'list' : 'custom',
                                            ])
                                        @elseif(! $locked && ! $itemId)
                                            @include('applications.partials.item-new-line-change-reasons-inputs', ['idx' => $idx])
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @unless($foremanMayReviseRejectedByBoilerChiefOnly ?? false)
                            <div class="flex flex-col gap-2 pt-1 sm:flex-row sm:flex-wrap">
                                <button type="button" id="add-equipment-from-list" class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto">
                                    + Из справочника
                                </button>
                                <button type="button" id="add-equipment-custom" class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto">
                                    + Своё оборудование
                                </button>
                            </div>
                        @endunless
                        <x-input-error :messages="$errors->get('equipment')" class="mt-1.5" />
                        @foreach($errors->getMessages() as $errKey => $errMessages)
                            @if(\Illuminate\Support\Str::startsWith((string) $errKey, 'removed_item_reasons.'))
                                @foreach($errMessages as $msg)
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $msg }}</p>
                                @endforeach
                            @endif
                        @endforeach
                    </section>

                    <section class="space-y-4 border-t border-stone-100 pt-8 dark:border-stone-800" aria-labelledby="edit-section-date">
                        <h3 id="edit-section-date" class="app-section-title">Срок</h3>
                        <div class="w-full max-w-full sm:max-w-xs">
                            <label for="desired_delivery_date" class="app-form-label">Желаемая дата поставки</label>
                            <input id="desired_delivery_date" type="date" name="desired_delivery_date" value="{{ old('desired_delivery_date', $application->desired_delivery_date?->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}" required class="app-input min-h-[3.25rem] sm:min-h-[2.75rem]" />
                            <x-input-error :messages="$errors->get('desired_delivery_date')" class="mt-1.5" />
                            @include('applications.partials.application-level-change-reason-inputs', ['block' => 'delivery'])
                        </div>
                    </section>

                    <div class="app-form-actions-mobile">
                        <a href="{{ route('applications.index') }}" class="min-h-11 content-center text-center text-sm font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-200 sm:text-left">
                            Отмена и к списку заявок
                        </a>
                        @if (($usesDraftSubmitFlow ?? false) && (($isCreatorDraft ?? false) || ($foremanCanResubmitToBoilerChief ?? false)))
                            <button type="submit" name="submit_action" value="save"
                                    class="ui-btn ui-btn--primary ui-btn--lg w-full text-base sm:w-auto">
                                Сохранить
                            </button>
                            @if ($foremanCanResubmitToBoilerChief ?? false)
                                <button type="submit" name="submit_action" value="submit_to_boiler_chief"
                                        class="ui-btn ui-btn--secondary ui-btn--lg w-full text-base sm:w-auto">
                                    Отправить изменённые позиции на согласование
                                </button>
                            @endif
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
                        <input type="text" placeholder="От 2 букв названия…" maxlength="{{ $equipmentCatalogSearchMax }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="equipment-search app-input app-input--with-icon" />
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
            <div class="new-line-field-change-reasons mt-3 space-y-1 border-t border-stone-200/80 pt-3 dark:border-stone-600/80 hidden" data-new-line-change-reasons="__INDEX__" data-server-error="0">
                <label class="app-form-label !text-xs">Комментарий: почему добавляете позицию</label>
                <p class="text-xs text-stone-600 dark:text-stone-400">Увидит мастер участка при просмотре заявки.</p>
                <textarea name="items[__INDEX__][addition_reason]" rows="2" maxlength="500" class="app-input min-h-[4rem] text-sm"></textarea>
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
                    <input type="text" name="items[__INDEX__][equipment_name]" placeholder="" maxlength="{{ $equipmentNameMax }}" class="custom-equipment-input app-input" />
                    <p class="mt-1 text-[11px] text-stone-500 dark:text-stone-400">Не более {{ $equipmentNameMax }} символов. </p>
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
            <div class="new-line-field-change-reasons mt-3 space-y-2 border-t border-stone-200/80 pt-3 dark:border-stone-600/80" data-new-line-change-reasons="__INDEX__">
                <p class="text-xs text-stone-600 dark:text-stone-400">Для новой позиции укажите комментарий по каждому пункту — их увидит мастер участка.</p>
                <div class="new-line-reason-aspect new-line-reason-aspect--equipment hidden space-y-1" data-aspect="equipment" data-server-error="0">
                    <label class="app-form-label !text-xs">Комментарий (новая позиция): оборудованию / наименованию</label>
                    <textarea name="items[__INDEX__][addition_reasons][equipment]" rows="2" maxlength="500" class="app-input min-h-[4rem] text-sm"></textarea>
                </div>
                <div class="new-line-reason-aspect new-line-reason-aspect--quantity hidden space-y-1" data-aspect="quantity" data-server-error="0">
                    <label class="app-form-label !text-xs">Комментарий (новая позиция): количеству</label>
                    <textarea name="items[__INDEX__][addition_reasons][quantity]" rows="2" maxlength="500" class="app-input min-h-[4rem] text-sm"></textarea>
                </div>
                <div class="new-line-reason-aspect new-line-reason-aspect--measurement hidden space-y-1" data-aspect="measurement" data-server-error="0">
                    <label class="app-form-label !text-xs">Комментарий (новая позиция): типу измерения, единице или размеру</label>
                    <textarea name="items[__INDEX__][addition_reasons][measurement]" rows="2" maxlength="500" class="app-input min-h-[4rem] text-sm"></textarea>
                </div>
            </div>
        </div>
    </script>
    <script>
        (function() {
            var form = document.getElementById('application-edit-form');
            var subdivWrap = document.getElementById('field-reason-subdivision-wrap');
            var subdivInput = document.getElementById('field_change_reasons_subdivision_id');
            var subdivSelect = document.getElementById('subdivision_id');
            var initSubdiv = form ? (form.getAttribute('data-app-initial-subdivision') || '') : '';

            var delWrap = document.getElementById('field-reason-delivery-wrap');
            var delInput = document.getElementById('field_change_reasons_desired_delivery_date');
            var delDateEl = document.getElementById('desired_delivery_date');
            var initDel = form ? (form.getAttribute('data-app-initial-delivery') || '') : '';

            var serverSubdivErr = @json($errors->has('field_change_reasons.subdivision_id'));
            var serverDelErr = @json($errors->has('field_change_reasons.desired_delivery_date'));

            function toggleSubdivReason() {
                if (!subdivWrap || !subdivSelect) {
                    return;
                }
                if (serverSubdivErr) {
                    subdivWrap.classList.remove('hidden');
                    if (subdivInput) {
                        subdivInput.required = true;
                    }
                    return;
                }
                var changed = String(subdivSelect.value || '') !== String(initSubdiv);
                subdivWrap.classList.toggle('hidden', !changed);
                if (subdivInput) {
                    subdivInput.required = changed;
                    if (!changed) {
                        subdivInput.value = '';
                    }
                }
            }

            function toggleDeliveryReason() {
                if (!delWrap || !delDateEl) {
                    return;
                }
                if (serverDelErr) {
                    delWrap.classList.remove('hidden');
                    if (delInput) {
                        delInput.required = true;
                    }
                    return;
                }
                var cur = String(delDateEl.value || '');
                var changed = cur !== String(initDel);
                delWrap.classList.toggle('hidden', !changed);
                if (delInput) {
                    delInput.required = changed;
                    if (!changed) {
                        delInput.value = '';
                    }
                }
            }

            function rowHasNewEquipmentContent(row) {
                if (!row.classList.contains('equipment-row--editable')) {
                    return false;
                }
                var idInput = row.querySelector('input[name*="[item_id]"]');
                if (!idInput || String(idInput.value || '').trim() !== '') {
                    return false;
                }
                if (row.classList.contains('equipment-row--list')) {
                    var tid = row.querySelector('.equipment-type-id');
                    return !!(tid && String(tid.value || '').trim() !== '');
                }
                if (row.classList.contains('equipment-row--custom')) {
                    var nameInput = row.querySelector('.custom-equipment-input');
                    return !!(nameInput && String(nameInput.value || '').trim() !== '');
                }
                return false;
            }

            function syncNewLineFieldReasonPanels() {
                Array.prototype.forEach.call(document.querySelectorAll('[data-new-line-change-reasons]'), function(wrap) {
                    var row = wrap.closest('.equipment-row');
                    if (!row) {
                        return;
                    }
                    var hasContent = rowHasNewEquipmentContent(row);
                    var serverFlag = wrap.getAttribute('data-server-error') === '1';
                    var vis = serverFlag || hasContent;
                    wrap.classList.toggle('hidden', !vis);
                    var ta = wrap.querySelector('textarea');
                    if (ta) {
                        ta.required = !!vis;
                        if (!vis && !serverFlag) {
                            ta.value = '';
                        }
                    }
                });
            }

            function readListRowState(row) {
                var tid = row.querySelector('.equipment-type-id');
                var qty = row.querySelector('.list-amount-number');
                var mt = row.querySelector('.list-measurement-type-field');
                var qu = row.querySelector('.list-quantity-unit-field');
                var sv = row.querySelector('.list-size-value-field');
                var sizeSel = row.querySelector('.list-amount-size');
                var svLive = sizeSel && !sizeSel.disabled ? String(sizeSel.value || '').trim() : String((sv && sv.value) || '');
                return {
                    equipmentId: String((tid && tid.value) || ''),
                    equipmentName: '',
                    quantity: qty ? parseInt(qty.value, 10) || 1 : 1,
                    measurementType: String((mt && mt.value) || 'piece'),
                    quantityUnit: String((qu && qu.value) || 'шт'),
                    sizeValue: svLive,
                };
            }

            function readCustomRowState(row) {
                var nameInput = row.querySelector('.custom-equipment-input');
                var mt = row.querySelector('select.measurement-type');
                var qty = row.querySelector('.custom-amount-number');
                var qu = row.querySelector('select.measurement-unit');
                var svField = row.querySelector('.custom-size-value-field');
                var sizeSel = row.querySelector('.custom-amount-size');
                var svLive = '';
                if (sizeSel && !sizeSel.disabled) {
                    svLive = String(sizeSel.value || '').trim();
                } else if (svField) {
                    svLive = String(svField.value || '').trim();
                }
                return {
                    equipmentId: '',
                    equipmentName: String((nameInput && nameInput.value) || '').trim(),
                    quantity: qty && !qty.disabled ? (parseInt(qty.value, 10) || 1) : 1,
                    measurementType: String((mt && mt.value) || ''),
                    quantityUnit: qu && !qu.disabled ? String(qu.value || '').trim() : '',
                    sizeValue: svLive,
                };
            }

            function normalizeReasonState(state) {
                var measurementType = String((state && state.measurementType) || '').trim();
                var quantityUnit = String((state && state.quantityUnit) || '').trim();
                var sizeValue = String((state && state.sizeValue) || '').trim();
                if (measurementType === '') {
                    measurementType = 'piece';
                }
                if (quantityUnit === '') {
                    quantityUnit = measurementType === 'length'
                        ? 'м'
                        : (measurementType === 'mass' ? 'кг' : 'шт');
                }
                if (measurementType !== 'clothing_size') {
                    sizeValue = '';
                }

                return {
                    equipmentId: String((state && state.equipmentId) || '').trim(),
                    equipmentName: String((state && state.equipmentName) || '').trim(),
                    quantity: parseInt((state && state.quantity) || 1, 10) || 1,
                    measurementType: measurementType,
                    quantityUnit: quantityUnit,
                    sizeValue: sizeValue,
                };
            }

            function syncItemFieldReasonPanels() {
                Array.prototype.forEach.call(document.querySelectorAll('[data-item-field-change-reasons]'), function(wrap) {
                    var row = wrap.closest('.equipment-row');
                    if (!row) {
                        return;
                    }
                    var mode = wrap.getAttribute('data-row-mode') || 'list';
                    var init = {
                        equipmentId: String(wrap.getAttribute('data-initial-equipment-id') || ''),
                        equipmentName: String(wrap.getAttribute('data-initial-equipment-name') || '').trim(),
                        quantity: parseInt(wrap.getAttribute('data-initial-quantity') || '1', 10) || 1,
                        measurementType: String(wrap.getAttribute('data-initial-measurement-type') || 'piece'),
                        quantityUnit: String(wrap.getAttribute('data-initial-quantity-unit') || 'шт'),
                        sizeValue: String(wrap.getAttribute('data-initial-size-value') || '').trim(),
                    };
                    var cur = normalizeReasonState(mode === 'list' ? readListRowState(row) : readCustomRowState(row));
                    var wrapInitKey = 'data-ui-initial-state';
                    if (!wrap.hasAttribute(wrapInitKey)) {
                        wrap.setAttribute(wrapInitKey, JSON.stringify(cur));
                    }
                    var initFromUi = null;
                    try {
                        initFromUi = JSON.parse(String(wrap.getAttribute(wrapInitKey) || '{}'));
                    } catch (e) {
                        initFromUi = null;
                    }
                    init = normalizeReasonState(initFromUi || init);
                    var equipmentChanged = mode === 'list'
                        ? cur.equipmentId !== init.equipmentId
                        : cur.equipmentName !== init.equipmentName;
                    var quantityChanged = cur.quantity !== init.quantity;
                    var measurementChanged = cur.measurementType !== init.measurementType
                        || cur.quantityUnit !== init.quantityUnit
                        || cur.sizeValue !== init.sizeValue;
                    var changed = equipmentChanged || quantityChanged || measurementChanged;
                    var serverFlag = wrap.getAttribute('data-server-error') === '1';
                    var vis = serverFlag || changed;
                    wrap.classList.toggle('hidden', !vis);
                    var ta = wrap.querySelector('textarea');
                    if (ta) {
                        ta.required = !!vis;
                        if (!vis && !serverFlag) {
                            ta.value = '';
                        }
                    }
                });
            }

            function syncAllPerFieldReasonUi() {
                toggleSubdivReason();
                toggleDeliveryReason();
                syncNewLineFieldReasonPanels();
                syncItemFieldReasonPanels();
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

            if (form) {
                form.addEventListener('input', syncAllPerFieldReasonUi);
                form.addEventListener('change', syncAllPerFieldReasonUi);
            }
            syncAllPerFieldReasonUi();
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
            var DUPLICATE_EQUIPMENT_GENERIC_MSG = 'Нельзя добавить две строки с одинаковым наименованием и типом измерения.';

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

                var mt = measurementTypeFromRow(row) || 'piece';
                return baseKey + ':mt:' + mt;
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
                scope.querySelectorAll('.custom-equipment-input, .equipment-search, .measurement-type, .list-amount-size, .custom-amount-size, .list-amount-number, .custom-amount-number').forEach(function(input) {
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
                var appEditFormSync = document.getElementById('application-edit-form');
                if (appEditFormSync) {
                    appEditFormSync.dispatchEvent(new Event('input', { bubbles: true }));
                }
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
                var itemIdInput = row.querySelector('input[name*="[item_id]"]');
                var itemId = itemIdInput && itemIdInput.value ? String(parseInt(itemIdInput.value, 10) || 0) : '0';
                if (itemId !== '0' && parseInt(itemId, 10) > 0) {
                    var reason = window.prompt('Укажите причину снятия этой позиции с заявки (будет видна мастеру участка). Не менее 3 символов:', '');
                    if (reason === null) {
                        return;
                    }
                    reason = String(reason).trim();
                    if (reason.length < 3) {
                        window.alert('Причина должна быть не короче 3 символов.');
                        return;
                    }
                    var panel = document.getElementById('removed-item-reasons-panel');
                    if (panel) {
                        var existing = panel.querySelector('input[name="removed_item_reasons[' + itemId + ']"]');
                        if (existing) {
                            existing.value = reason;
                        } else {
                            var hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'removed_item_reasons[' + itemId + ']';
                            hidden.value = reason;
                            panel.appendChild(hidden);
                        }
                    }
                }
                row.remove();
                refreshDuplicateEquipmentErrors();
                var appEditForm = document.getElementById('application-edit-form');
                if (appEditForm) {
                    appEditForm.dispatchEvent(new Event('input', { bubbles: true }));
                }
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
