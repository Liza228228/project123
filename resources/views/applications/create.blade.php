<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center gap-x-4 gap-y-2 w-full min-w-0">
            <a href="{{ route('applications.index') }}" class="shrink-0 text-sm text-black dark:text-white hover:text-black dark:hover:text-white transition-colors whitespace-nowrap">
                ← Заявки
            </a>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Создать заявку
            </h2>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-10 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-orange-800 border border-orange-200 dark:border-orange-700 rounded-lg shadow-sm overflow-hidden">
            <div class="p-4 sm:p-8">
                <form method="POST" action="{{ route('applications.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <input type="hidden" name="source_application_id" value="{{ old('source_application_id', $prefill['source_application_id'] ?? '') }}">

                    @if($prefill)
                        <p class="text-sm text-black dark:text-white border-l-2 border-orange-300 dark:border-orange-600 pl-4 py-0.5">
                            Повторная заявка на основе заявки №{{ $prefill['source_application_id'] }}.
                        </p>
                    @endif

                    @if($subdivisions->isEmpty())
                        <div class="rounded-lg border border-orange-300 dark:border-orange-600 bg-orange-50 dark:bg-orange-900/30 px-4 py-3 text-sm text-black dark:text-white">
                            Для вас не назначены подразделения. Обратитесь к начальнику отдела снабжения.
                        </div>
                    @endif

                    <div class="space-y-1.5">
                        <x-input-label for="subdivision_id" value="Подразделение" />
                        <select id="subdivision_id" name="subdivision_id" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required @disabled($subdivisions->isEmpty())>
                            <option value="">Выберите подразделение</option>
                            @foreach($subdivisions as $sub)
                                <option value="{{ $sub->id }}" @selected(old('subdivision_id', $prefill['subdivision_id'] ?? null) == $sub->id)>{{ $sub->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subdivision_id')" class="mt-1" />
                    </div>

                    @include('applications.partials.subdivision-warehouses-hint')

                    @if (! Auth::user()->hasRoleId(4))
                        <div class="space-y-1.5">
                            <x-input-label for="responsible_user_id" value="Ответственный" />
                            <select id="responsible_user_id" name="responsible_user_id" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Не назначен / выбрать автоматически</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" @selected(old('responsible_user_id', $prefill['responsible_user_id'] ?? null) == $u->id)>{{ $u->surname }} {{ $u->name }} {{ $u->patronymic }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('responsible_user_id')" class="mt-1" />
                        </div>
                    @else
                        <input type="hidden" name="responsible_user_id" value="{{ Auth::id() }}">
                    @endif

                    <div class="space-y-1.5">
                        <x-input-label for="transport_option_id" value="Способ доставки" />
                        <select id="transport_option_id" name="transport_option_id" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500">
                            <option value="">Не указан</option>
                            @foreach($transportOptions as $t)
                                <option value="{{ $t->id }}" @selected(old('transport_option_id', data_get($prefill, 'transport_option_id')) == $t->id)>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('transport_option_id')" class="mt-1" />
                    </div>

                    @php
                        $showCommercialOffer = old('attach_commercial_offer') === '1' || $errors->has('commercial_offer');
                    @endphp
                    <div class="space-y-2">
                        <input type="hidden" name="attach_commercial_offer" id="attach-commercial-offer-input" value="{{ $showCommercialOffer ? '1' : '0' }}">
                        <button
                            type="button"
                            id="add-commercial-offer-btn"
                            class="text-sm font-medium text-black dark:text-white hover:opacity-80 dark:hover:text-white"
                        >
                            + Добавить КП (не обязательно)
                        </button>

                        <div id="commercial-offer-block" class="{{ $showCommercialOffer ? '' : 'hidden' }} space-y-1.5 rounded-lg border border-orange-200 dark:border-orange-700 bg-orange-50/60 dark:bg-orange-900/30 p-3">
                            <x-input-label for="commercial_offer" value="Коммерческое предложение " />
                            <input
                                id="commercial_offer"
                                type="file"
                                name="commercial_offer"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500"
                            />
                            <p class="text-xs text-black dark:text-white opacity-80">
                                Поддерживаемые форматы: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG. Максимальный размер: 10 МБ.
                            </p>
                            <x-input-error :messages="$errors->get('commercial_offer')" class="mt-1" />
                        </div>
                    </div>

                    @php
                        $items = old('items', $prefill['items'] ?? [['equipment_id' => '', 'equipment_name' => '', 'quantity' => 1]]);
                        if (empty($items)) {
                            $items = [['equipment_id' => '', 'equipment_name' => '', 'quantity' => 1]];
                        }
                    @endphp
                    <div class="space-y-3">
                        <div>
                            <x-input-label value="Оборудование" />
                            <p class="text-xs text-black dark:text-white mt-0.5">Добавляйте позиции из списка или нажмите «Написать своё оборудование», чтобы ввести название вручную.</p>
                        </div>
                        <div id="equipment-items" class="space-y-3">
                            @foreach($items as $idx => $item)
                                @php
                                    $typeId = $item['equipment_id'] ?? '';
                                    $eqName = trim($item['equipment_name'] ?? '');
                                    $isCustomRow = ($typeId === '' || $typeId === null) && $eqName !== '';
                                @endphp
                                @if($isCustomRow)
                                    <div class="equipment-row equipment-row--custom flex flex-wrap items-end gap-3 p-3 rounded-lg border border-orange-200 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/40">
                                        <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="" />
                                        <div class="flex-1 min-w-[200px]">
                                            <label class="block text-xs text-black dark:text-white mb-0.5">Своё оборудование</label>
                                            <input type="text" name="items[{{ $idx }}][equipment_name]" value="{{ $item['equipment_name'] ?? '' }}" placeholder="Название оборудования" class="custom-equipment-input block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" />
                                            <p class="custom-equipment-error hidden mt-1 text-xs text-red-600 dark:text-red-400">Такое оборудование уже есть в списке.</p>
                                        </div>
                                        <div class="w-20">
                                            <label class="block text-xs text-black dark:text-white mb-0.5">Кол-во</label>
                                            <input type="number" name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required />
                                        </div>
                                        <button type="button" class="remove-item px-3 py-2 text-sm text-black dark:text-white hover:text-red-600 dark:hover:text-red-400 hover:bg-orange-100 dark:hover:bg-orange-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
                                    </div>
                                @else
                                    <div class="equipment-row equipment-row--list flex flex-wrap items-end gap-3 p-3 rounded-lg border border-orange-200 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/40">
                                        @php
                                            $selectedType = ($item['equipment_id'] ?? '') !== '' ? $equipment->firstWhere('id', (int) $item['equipment_id']) : null;
                                        @endphp
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="" />
                                        <div class="flex-1 min-w-[200px]">
                                            <label class="block text-xs text-black dark:text-white mb-0.5">Из списка</label>
                                            <input type="hidden" name="items[{{ $idx }}][equipment_id]" value="{{ $item['equipment_id'] ?? '' }}" class="equipment-type-id" />
                                            <input
                                                type="text"
                                                value="{{ $selectedType?->name ?? '' }}"
                                                placeholder="Начните вводить оборудование"
                                                autocomplete="off"
                                                autocorrect="off"
                                                autocapitalize="off"
                                                spellcheck="false"
                                                class="equipment-search block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                            />
                                            <div class="equipment-suggestions hidden mt-1 max-h-44 overflow-y-auto rounded-lg border border-orange-200 dark:border-orange-700 bg-white dark:bg-orange-950 shadow-sm"></div>
                                        </div>
                                        <div class="w-20">
                                            <label class="block text-xs text-black dark:text-white mb-0.5">Кол-во</label>
                                            <input type="number" name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required />
                                        </div>
                                        <button type="button" class="remove-item px-3 py-2 text-sm text-black dark:text-white hover:text-red-600 dark:hover:text-red-400 hover:bg-orange-100 dark:hover:bg-orange-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
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
                        <input id="desired_delivery_date" type="date" name="desired_delivery_date" value="{{ old('desired_delivery_date', $prefill['desired_delivery_date'] ?? '') }}" min="{{ now()->format('Y-m-d') }}" required class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" />
                        <x-input-error :messages="$errors->get('desired_delivery_date')" class="mt-1" />
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-2 border-t border-orange-200 dark:border-orange-700">
                        <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 dark:bg-orange-600 dark:hover:bg-orange-500 border border-transparent rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 disabled:opacity-60 disabled:cursor-not-allowed" @disabled($subdivisions->isEmpty())>
                            Создать заявку
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
        <div class="equipment-row equipment-row--list flex flex-wrap items-end gap-3 p-3 rounded-lg border border-orange-200 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/40">
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
                    class="equipment-search block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500"
                />
                <div class="equipment-suggestions hidden mt-1 max-h-44 overflow-y-auto rounded-lg border border-orange-200 dark:border-orange-700 bg-white dark:bg-orange-950 shadow-sm"></div>
            </div>
            <div class="w-20">
                <label class="block text-xs text-black dark:text-white mb-0.5">Кол-во</label>
                <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required />
            </div>
            <button type="button" class="remove-item px-3 py-2 text-sm text-black dark:text-white hover:text-red-600 dark:hover:text-red-400 hover:bg-orange-100 dark:hover:bg-orange-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
        </div>
    </script>
    <script type="text/template" id="equipment-row-custom-tpl">
        <div class="equipment-row equipment-row--custom flex flex-wrap items-end gap-3 p-3 rounded-lg border border-orange-200 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/40">
            <input type="hidden" name="items[__INDEX__][equipment_id]" value="" />
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-black dark:text-white mb-0.5">Своё оборудование</label>
                <input type="text" name="items[__INDEX__][equipment_name]" placeholder="Название оборудования" class="custom-equipment-input block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" />
                <p class="custom-equipment-error hidden mt-1 text-xs text-red-600 dark:text-red-400">Такое оборудование уже есть в списке.</p>
            </div>
            <div class="w-20">
                <label class="block text-xs text-black dark:text-white mb-0.5">Кол-во</label>
                <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required />
            </div>
            <button type="button" class="remove-item px-3 py-2 text-sm text-black dark:text-white hover:text-red-600 dark:hover:text-red-400 hover:bg-orange-100 dark:hover:bg-orange-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
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
                            return '<button type="button" class="equipment-suggestion-item block w-full px-3 py-2 text-left text-sm text-black dark:text-white hover:bg-orange-100 dark:hover:bg-orange-900/40" data-id="' + item.id + '" data-name="' + item.name.replace(/"/g, '&quot;') + '">' + item.name + '</button>';
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
                if (container.querySelectorAll('.equipment-row').length > 1) {
                    row.remove();
                }
            }

            bindRemoveButtons();
            bindSearchInputs();
            bindCustomInputs();

            var addCommercialOfferBtn = document.getElementById('add-commercial-offer-btn');
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
