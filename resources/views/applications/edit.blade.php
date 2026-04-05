<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('applications.index') }}" class="text-sm text-orange-600 dark:text-orange-400 hover:text-orange-900 dark:hover:text-orange-100 transition-colors">
                ← Заявки
            </a>
            <h2 class="font-semibold text-xl text-orange-800 dark:text-orange-100 leading-tight tracking-tight">
                Изменить заявку
            </h2>
        </div>
    </x-slot>

    @php
        $defaultItems = $application->items
            ->sortBy(fn ($i) => $i->is_checked ? 0 : 1)
            ->values()
            ->map(fn ($i) => [
                'item_id' => $i->id,
                'equipment_type_id' => $i->equipment_type_id ?? '',
                'equipment_name' => $i->equipment_name ?? '',
                'quantity' => $i->quantity,
            ])
            ->all();
        $items = old('items', $defaultItems);
        if (empty($items)) {
            $items = [['item_id' => null, 'equipment_type_id' => '', 'equipment_name' => '', 'quantity' => 1]];
        }
    @endphp

    <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-orange-800 border border-orange-200 dark:border-orange-700 rounded-lg shadow-sm overflow-hidden">
            <div class="p-8">
                <p class="text-sm text-orange-600 dark:text-orange-300 border-l-2 border-orange-300 dark:border-orange-600 pl-4 py-0.5 mb-6">
                    Согласованное оборудование отображается только для просмотра. Неодобренное оборудование можно изменить, удалить или дополнить новыми.
                </p>

                <form method="POST" action="{{ route('applications.update', $application) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="space-y-1.5">
                        <x-input-label for="subdivision_id" value="Подразделение" />
                        <select id="subdivision_id" name="subdivision_id" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required>
                            <option value="">Выберите подразделение</option>
                            @foreach($subdivisions as $sub)
                                <option value="{{ $sub->id }}" @selected(old('subdivision_id', $application->subdivision_id) == $sub->id)>{{ $sub->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subdivision_id')" class="mt-1" />
                    </div>

                    <div class="space-y-1.5">
                        <x-input-label for="responsible_user_id" value="Ответственный" />
                        <select id="responsible_user_id" name="responsible_user_id" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500">
                            <option value="">Не назначен / выбрать автоматически</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" @selected(old('responsible_user_id', $application->responsible_user_id) == $u->id)>{{ $u->surname }} {{ $u->name }} {{ $u->patronymic }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('responsible_user_id')" class="mt-1" />
                    </div>

                    <div class="space-y-1.5">
                        <x-input-label for="transport_option_id" value="Транспорт / способ доставки" />
                        <p class="text-xs text-orange-500 dark:text-orange-400">Машина, маршрутка, грузовик и т.п.</p>
                        <select id="transport_option_id" name="transport_option_id" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500">
                            <option value="">Не указан</option>
                            @foreach($transportOptions as $t)
                                <option value="{{ $t->id }}" @selected(old('transport_option_id', $application->transport_option_id) == $t->id)>
                                    {{ $t->name }}@if($t->code) ({{ $t->code }})@endif
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('transport_option_id')" class="mt-1" />
                    </div>

                    <div class="space-y-3">
                        <div>
                            <x-input-label value="Оборудование" />
                            <p class="text-xs text-orange-500 dark:text-orange-400 mt-0.5">Добавляйте позиции из списка или нажмите «Написать своё оборудование», чтобы ввести название вручную.</p>
                        </div>
                        <div id="equipment-items" class="space-y-3">
                            @foreach($items as $idx => $item)
                                @php
                                    $itemId = $item['item_id'] ?? null;
                                    $dbItem = $itemId !== null && $itemId !== '' ? $application->items->firstWhere('id', (int) $itemId) : null;
                                    $locked = (bool) ($dbItem && $dbItem->is_checked);
                                    $typeId = $item['equipment_type_id'] ?? '';
                                    $eqName = trim($item['equipment_name'] ?? '');
                                    $isCustomRow = ! $locked && (($typeId === '' || $typeId === null) && $eqName !== '');
                                @endphp

                                @if($locked)
                                    <div class="equipment-row equipment-row--locked flex flex-col gap-2 p-3 rounded-lg border border-orange-300 dark:border-orange-500 bg-orange-100/70 dark:bg-orange-900/50">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-orange-800 dark:text-orange-200">Согласовано </span>
                                        </div>
                                        <p class="text-sm font-medium text-orange-950 dark:text-orange-50">
                                            @if($typeId !== '' && $typeId !== null)
                                                {{ $equipmentTypes->firstWhere('id', (int) $typeId)?->name ?? '—' }}
                                            @else
                                                {{ $eqName !== '' ? $eqName : '—' }}
                                            @endif
                                            <span class="text-orange-600 dark:text-orange-300 font-normal">× {{ (int) ($item['quantity'] ?? 1) }}</span>
                                        </p>
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_type_id]" value="{{ $typeId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="{{ $eqName }}" />
                                        <input type="hidden" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" />
                                    </div>
                                @elseif($isCustomRow)
                                    <div class="equipment-row equipment-row--custom equipment-row--editable flex flex-wrap items-end gap-3 p-3 rounded-lg border border-orange-200 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/40">
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_type_id]" value="" />
                                        <div class="flex-1 min-w-[200px]">
                                            <label class="block text-xs text-orange-500 dark:text-orange-400 mb-0.5">Своё оборудование</label>
                                            <input type="text" name="items[{{ $idx }}][equipment_name]" value="{{ $eqName }}" placeholder="Название оборудования" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" />
                                        </div>
                                        <div class="w-20">
                                            <label class="block text-xs text-orange-500 dark:text-orange-400 mb-0.5">Кол-во</label>
                                            <input type="number" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" min="1" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required />
                                        </div>
                                        <button type="button" class="remove-item px-3 py-2 text-sm text-orange-500 dark:text-orange-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-orange-100 dark:hover:bg-orange-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
                                    </div>
                                @else
                                    <div class="equipment-row equipment-row--list equipment-row--editable flex flex-wrap items-end gap-3 p-3 rounded-lg border border-orange-200 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/40">
                                        <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $itemId }}" />
                                        <input type="hidden" name="items[{{ $idx }}][equipment_name]" value="" />
                                        <div class="flex-1 min-w-[200px]">
                                            <label class="block text-xs text-orange-500 dark:text-orange-400 mb-0.5">Из списка</label>
                                            <select name="items[{{ $idx }}][equipment_type_id]" class="equipment-select block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500">
                                                <option value="">— Выберите —</option>
                                                @foreach($equipmentTypes as $et)
                                                    <option value="{{ $et->id }}" @selected((string) ($typeId ?? '') === (string) $et->id)>{{ $et->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-20">
                                            <label class="block text-xs text-orange-500 dark:text-orange-400 mb-0.5">Кол-во</label>
                                            <input type="number" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" min="1" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required />
                                        </div>
                                        <button type="button" class="remove-item px-3 py-2 text-sm text-orange-500 dark:text-orange-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-orange-100 dark:hover:bg-orange-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button type="button" id="add-equipment-from-list" class="text-sm font-medium text-orange-600 dark:text-orange-400 hover:text-orange-900 dark:hover:text-orange-200">
                                + Добавить из списка
                            </button>
                            <button type="button" id="add-equipment-custom" class="text-sm font-medium text-orange-600 dark:text-orange-400 hover:text-orange-900 dark:hover:text-orange-200">
                                Написать своё оборудование
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('equipment')" class="mt-1" />
                    </div>

                    <div class="space-y-1.5">
                        <x-input-label for="desired_delivery_date" value="Желаемая дата поставки" />
                        <input id="desired_delivery_date" type="date" name="desired_delivery_date" value="{{ old('desired_delivery_date', $application->desired_delivery_date?->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}" required class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" />
                        <x-input-error :messages="$errors->get('desired_delivery_date')" class="mt-1" />
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-2 border-t border-orange-200 dark:border-orange-700">
                        <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 dark:bg-orange-600 dark:hover:bg-orange-500 border border-transparent rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                            Сохранить изменения
                        </button>
                        <a href="{{ route('applications.index') }}" class="text-sm text-orange-600 dark:text-orange-400 hover:text-orange-900 dark:hover:text-orange-100">
                            Отмена
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/template" id="equipment-row-from-list-tpl">
        <div class="equipment-row equipment-row--list equipment-row--editable flex flex-wrap items-end gap-3 p-3 rounded-lg border border-orange-200 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/40">
            <input type="hidden" name="items[__INDEX__][item_id]" value="" />
            <input type="hidden" name="items[__INDEX__][equipment_name]" value="" />
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-orange-500 dark:text-orange-400 mb-0.5">Из списка</label>
                <select name="items[__INDEX__][equipment_type_id]" class="equipment-select block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500">
                    <option value="">— Выберите —</option>
                    @foreach($equipmentTypes as $et)
                        <option value="{{ $et->id }}">{{ $et->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-20">
                <label class="block text-xs text-orange-500 dark:text-orange-400 mb-0.5">Кол-во</label>
                <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required />
            </div>
            <button type="button" class="remove-item px-3 py-2 text-sm text-orange-500 dark:text-orange-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-orange-100 dark:hover:bg-orange-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
        </div>
    </script>
    <script type="text/template" id="equipment-row-custom-tpl">
        <div class="equipment-row equipment-row--custom equipment-row--editable flex flex-wrap items-end gap-3 p-3 rounded-lg border border-orange-200 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/40">
            <input type="hidden" name="items[__INDEX__][item_id]" value="" />
            <input type="hidden" name="items[__INDEX__][equipment_type_id]" value="" />
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-orange-500 dark:text-orange-400 mb-0.5">Своё оборудование</label>
                <input type="text" name="items[__INDEX__][equipment_name]" placeholder="Название оборудования" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" />
            </div>
            <div class="w-20">
                <label class="block text-xs text-orange-500 dark:text-orange-400 mb-0.5">Кол-во</label>
                <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-200 text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500" required />
            </div>
            <button type="button" class="remove-item px-3 py-2 text-sm text-orange-500 dark:text-orange-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-orange-100 dark:hover:bg-orange-800 rounded-lg transition-colors" title="Удалить позицию">✕</button>
        </div>
    </script>
    <script>
        (function() {
            var container = document.getElementById('equipment-items');
            var tplList = document.getElementById('equipment-row-from-list-tpl').innerHTML;
            var tplCustom = document.getElementById('equipment-row-custom-tpl').innerHTML;
            var nextIndex = container.querySelectorAll('.equipment-row').length;

            function countEditable() {
                return container.querySelectorAll('.equipment-row--editable').length;
            }
            function countLocked() {
                return container.querySelectorAll('.equipment-row--locked').length;
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
        })();
    </script>
</x-app-layout>
