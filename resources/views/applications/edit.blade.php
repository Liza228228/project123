<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center gap-x-4 gap-y-2 w-full min-w-0">
            <a href="{{ route('applications.index') }}" class="shrink-0 text-sm text-black dark:text-white hover:text-black dark:hover:text-white transition-colors whitespace-nowrap">
                ← Заявки
            </a>
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
            ])
            ->all();
        $items = old('items', $defaultItems);
        if (empty($items)) {
            $items = [['item_id' => null, 'equipment_id' => '', 'equipment_name' => '', 'quantity' => 1]];
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

    <div class="py-2 sm:py-8 md:py-10 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-stone-800 border border-stone-200 dark:border-stone-700 rounded-lg shadow-sm overflow-hidden">
            <div class="p-4 sm:p-8">
                <p class="text-sm text-black dark:text-white border-l-2 border-stone-300 dark:border-stone-600 pl-4 py-0.5 mb-6">
                    Позиции с отметкой «согласовано» только для просмотра. Остальные можно менять, удалять или дополнять. После сохранения заявки отметки согласования по позициям сбрасываются.
                </p>

                <form id="application-edit-form" method="POST" action="{{ route('applications.update', $application) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="space-y-1.5">
                        <x-input-label for="subdivision_id" value="Подразделение" />
                        <select id="subdivision_id" name="subdivision_id" class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500" required>
                            <option value="">Выберите подразделение</option>
                            @foreach($subdivisions as $sub)
                                <option value="{{ $sub->id }}" @selected(old('subdivision_id', $application->subdivision_id) == $sub->id)>{{ $sub->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subdivision_id')" class="mt-1" />
                    </div>

                    @include('applications.partials.subdivision-warehouses-hint')

                    @if (! Auth::user()->hasRoleId(4))
                        <div class="space-y-1.5">
                            <x-input-label for="responsible_user_id" value="Ответственный" />
                            <select id="responsible_user_id" name="responsible_user_id" class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500">
                                <option value="">Не назначен / выбрать автоматически</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" @selected(old('responsible_user_id', $application->responsible_user_id) == $u->id)>{{ $u->surname }} {{ $u->name }} {{ $u->patronymic }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('responsible_user_id')" class="mt-1" />
                        </div>
                    @else
                        <input type="hidden" name="responsible_user_id" value="{{ Auth::id() }}">
                    @endif

                    @if (Auth::user()->hasAnyRoleId([1, 6, 2]))
                        <div id="management-change-reason-block" class="space-y-1.5 {{ (old('management_change_reason') || $errors->has('management_change_reason')) ? '' : 'hidden' }}">
                            <x-input-label for="management_change_reason" value="Причина изменения " />
                            <textarea
                                id="management_change_reason"
                                name="management_change_reason"
                                rows="3"
                                maxlength="500"
                                class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                            >{{ old('management_change_reason') }}</textarea>

                            <x-input-error :messages="$errors->get('management_change_reason')" class="mt-1" />
                        </div>
                    @endif

                    <div class="space-y-1.5">
                        <x-input-label for="transport_option_id" value="Способ доставки" />
                        <select id="transport_option_id" name="transport_option_id" class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500">
                            <option value="">Не указан</option>
                            @foreach($transportOptions as $t)
                                <option value="{{ $t->id }}" @selected(old('transport_option_id', $application->transport_option_id) == $t->id)>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('transport_option_id')" class="mt-1" />
                    </div>

                    <div class="space-y-3">
                        <div>
                            <x-input-label value="Оборудование" />
                            <p class="text-xs text-black dark:text-white mt-0.5">Добавляйте позиции из списка или нажмите «Написать своё оборудование», чтобы ввести название вручную.</p>
                        </div>
                        <div id="equipment-items" class="space-y-3">
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
                                    <div class="equipment-row equipment-row--locked flex flex-col gap-2 p-3 rounded-lg border border-stone-300 dark:border-stone-500 bg-stone-100/70 dark:bg-stone-900/50">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-black dark:text-white">Согласовано </span>
                                        </div>
                                        <p class="text-sm font-medium text-black dark:text-white">
                                            @if($typeId !== '' && $typeId !== null)
                                                {{ $equipment->firstWhere('id', (int) $typeId)?->name ?? '—' }}
                                            @else
                                                {{ $eqName !== '' ? $eqName : '—' }}
                                            @endif
                                            <span class="text-black dark:text-white font-normal">× {{ (int) ($item['quantity'] ?? 1) }}</span>
                                        </p>
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="{{ $typeId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="{{ $eqName }}" />
                                        <input type="hidden" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" />
                                    </div>
                                @elseif($isCustomRow)
                                    <div class="equipment-row equipment-row--custom equipment-row--editable flex flex-wrap items-end gap-3 p-3 rounded-lg border border-stone-200 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/40">
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="" />
                                        <div class="flex-1 min-w-[200px]">
                                            <label class="block text-xs text-black dark:text-white mb-0.5">Своё оборудование</label>
                                            <input type="text" name="items[{{ $idx }}][equipment_name]" value="{{ $eqName }}" placeholder="Название оборудования" class="custom-equipment-input block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500" />
                                            <p class="custom-equipment-error hidden mt-1 text-xs text-red-600 dark:text-red-400">Такое оборудование уже есть в списке.</p>
                                        </div>
                                        <div class="w-20">
                                            <label class="block text-xs text-black dark:text-white mb-0.5">Кол-во</label>
                                            <input type="number" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" min="1" class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500" required />
                                        </div>
                                        <button type="button" class="remove-item px-3 py-2 text-sm text-black dark:text-white hover:text-red-600 dark:hover:text-red-400 hover:bg-stone-100 dark:hover:bg-stone-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
                                    </div>
                                @else
                                    <div class="equipment-row equipment-row--list equipment-row--editable flex flex-wrap items-end gap-3 p-3 rounded-lg border border-stone-200 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/40">
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="" />
                                        <div class="flex-1 min-w-[200px]">
                                            <label class="block text-xs text-black dark:text-white mb-0.5">Из списка</label>
                                            @php
                                                $selectedType = ($typeId ?? '') !== '' ? $equipment->firstWhere('id', (int) $typeId) : null;
                                            @endphp
                                            <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="{{ $typeId ?? '' }}" class="equipment-type-id" />
                                            <input
                                                type="text"
                                                value="{{ $selectedType?->name ?? '' }}"
                                                placeholder="Начните вводить оборудование"
                                                autocomplete="off"
                                                autocorrect="off"
                                                autocapitalize="off"
                                                spellcheck="false"
                                                class="equipment-search block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                            />
                                            <div class="equipment-suggestions hidden mt-1 max-h-44 overflow-y-auto rounded-lg border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-950 shadow-sm"></div>
                                        </div>
                                        <div class="w-20">
                                            <label class="block text-xs text-black dark:text-white mb-0.5">Кол-во</label>
                                            <input type="number" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" min="1" class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500" required />
                                        </div>
                                        <button type="button" class="remove-item px-3 py-2 text-sm text-black dark:text-white hover:text-red-600 dark:hover:text-red-400 hover:bg-stone-100 dark:hover:bg-stone-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button type="button" id="add-equipment-from-list" class="text-sm font-medium text-black dark:text-white hover:opacity-80 dark:hover:text-white">
                                + Добавить из списка
                            </button>
                            <button type="button" id="add-equipment-custom" class="text-sm font-medium text-black dark:text-white hover:opacity-80 dark:hover:text-white">
                                Написать своё оборудование
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('equipment')" class="mt-1" />
                    </div>

                    <div class="space-y-1.5">
                        <x-input-label for="desired_delivery_date" value="Желаемая дата поставки" />
                        <input id="desired_delivery_date" type="date" name="desired_delivery_date" value="{{ old('desired_delivery_date', $application->desired_delivery_date?->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}" required class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500" />
                        <x-input-error :messages="$errors->get('desired_delivery_date')" class="mt-1" />
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-2 border-t border-stone-200 dark:border-stone-700">
                        <button type="submit" class="ui-btn ui-btn--primary">
                            Сохранить изменения
                        </button>
                        <a href="{{ route('applications.index') }}" class="text-sm text-black dark:text-white hover:text-black dark:hover:text-white">
                            Отмена
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/template" id="equipment-row-from-list-tpl">
        <div class="equipment-row equipment-row--list equipment-row--editable flex flex-wrap items-end gap-3 p-3 rounded-lg border border-stone-200 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/40">
            <input type="hidden" name="items[__INDEX__][item_id]" value="" />
            <input type="hidden" name="items[__INDEX__][equipment_name]" value="" />
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-black dark:text-white mb-0.5">Из списка</label>
                <input type="hidden" name="items[__INDEX__][equipment_id]" value="" class="equipment-type-id" />
                <input
                    type="text"
                    placeholder="Начните вводить оборудование"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    class="equipment-search block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                />
                <div class="equipment-suggestions hidden mt-1 max-h-44 overflow-y-auto rounded-lg border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-950 shadow-sm"></div>
            </div>
            <div class="w-20">
                <label class="block text-xs text-black dark:text-white mb-0.5">Кол-во</label>
                <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500" required />
            </div>
            <button type="button" class="remove-item px-3 py-2 text-sm text-black dark:text-white hover:text-red-600 dark:hover:text-red-400 hover:bg-stone-100 dark:hover:bg-stone-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
        </div>
    </script>
    <script type="text/template" id="equipment-row-custom-tpl">
        <div class="equipment-row equipment-row--custom equipment-row--editable flex flex-wrap items-end gap-3 p-3 rounded-lg border border-stone-200 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/40">
            <input type="hidden" name="items[__INDEX__][item_id]" value="" />
            <input type="hidden" name="items[__INDEX__][equipment_id]" value="" />
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-black dark:text-white mb-0.5">Своё оборудование</label>
                <input type="text" name="items[__INDEX__][equipment_name]" placeholder="Название оборудования" class="custom-equipment-input block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500" />
                <p class="custom-equipment-error hidden mt-1 text-xs text-red-600 dark:text-red-400">Такое оборудование уже есть в списке.</p>
            </div>
            <div class="w-20">
                <label class="block text-xs text-black dark:text-white mb-0.5">Кол-во</label>
                <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500" required />
            </div>
            <button type="button" class="remove-item px-3 py-2 text-sm text-black dark:text-white hover:text-red-600 dark:hover:text-red-400 hover:bg-stone-100 dark:hover:bg-stone-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
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
            var equipmentNameSet = {};
            equipmentList.forEach(function(item) {
                equipmentNameSet[item.key] = true;
            });

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
                            return '<button type="button" class="equipment-suggestion-item block w-full px-3 py-2 text-left text-sm text-black dark:text-white hover:bg-stone-100 dark:hover:bg-stone-900/40" data-id="' + item.id + '" data-name="' + item.name.replace(/"/g, '&quot;') + '">' + item.name + '</button>';
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

            function validateCustomInput(input) {
                var row = input.closest('.equipment-row');
                if (!row) {
                    return true;
                }
                var error = row.querySelector('.custom-equipment-error');
                var value = (input.value || '').trim().toLowerCase();
                var isDuplicate = value !== '' && !!equipmentNameSet[value];

                if (isDuplicate) {
                    input.setCustomValidity('Такое оборудование уже есть в списке.');
                    input.classList.add('border-red-500');
                    if (error) {
                        error.classList.remove('hidden');
                    }
                    return false;
                }

                input.setCustomValidity('');
                input.classList.remove('border-red-500');
                if (error) {
                    error.classList.add('hidden');
                }
                return true;
            }

            function bindCustomInputs() {
                container.querySelectorAll('.custom-equipment-input').forEach(function(input) {
                    if (input.dataset.bound === '1') {
                        return;
                    }
                    var run = function() { validateCustomInput(input); };
                    input.addEventListener('input', run);
                    input.addEventListener('change', run);
                    input.dataset.bound = '1';
                    run();
                });
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
                bindCustomInputs();
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
            bindCustomInputs();

            var form = container.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    var ok = true;
                    container.querySelectorAll('.custom-equipment-input').forEach(function(input) {
                        if (!validateCustomInput(input)) {
                            ok = false;
                        }
                    });
                    if (!ok) {
                        e.preventDefault();
                    }
                });
            }
        })();
    </script>
</x-app-layout>
