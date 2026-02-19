<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('applications.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 text-sm">← Заявки</a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Создать заявку
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 max-w-md">
                    <form method="POST" action="{{ route('applications.store') }}">
                        @csrf

                        <div>
                            <x-input-label for="subdivision_id" value="Подразделение" />
                            <select id="subdivision_id" name="subdivision_id" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600" required>
                                <option value="">Выберите подразделение</option>
                                @foreach($subdivisions as $sub)
                                    <option value="{{ $sub->id }}" @selected(old('subdivision_id') == $sub->id)>{{ $sub->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('subdivision_id')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="responsible_user_id" value="Ответственный" />
                            <select id="responsible_user_id" name="responsible_user_id" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                <option value="">Не назначен / выбрать автоматически</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" @selected(old('responsible_user_id') == $u->id)>{{ $u->surname }} {{ $u->name }} {{ $u->patronymic }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('responsible_user_id')" class="mt-2" />
                        </div>

                        @php
                            $items = old('items', [['equipment_type_id' => '', 'equipment_name' => '', 'quantity' => 1]]);
                            if (empty($items)) {
                                $items = [['equipment_type_id' => '', 'equipment_name' => '', 'quantity' => 1]];
                            }
                        @endphp
                        <div class="mt-4">
                            <x-input-label value="Оборудование" />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-3">Выберите оборудование из списка и укажите количество либо впишите своё название и количество.</p>
                            <div id="equipment-items" class="space-y-4">
                                @foreach($items as $idx => $item)
                                <div class="equipment-row p-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Выбрать оборудование из списка</label>
                                        <select name="items[{{ $idx }}][equipment_type_id]" class="equipment-select block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                            <option value="">— Выберите оборудование —</option>
                                            @foreach($equipmentTypes as $et)
                                                <option value="{{ $et->id }}" @selected(($item['equipment_type_id'] ?? '') == $et->id)>{{ $et->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-600 pt-3">
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Или вписать своё оборудование</label>
                                        <input type="text" name="items[{{ $idx }}][equipment_name]" value="{{ $item['equipment_name'] ?? '' }}" placeholder="Название оборудования" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm" />
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                                        <div class="w-24">
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Количество</label>
                                            <input type="number" name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm" required />
                                        </div>
                                        <button type="button" class="remove-item self-end px-3 py-1.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md" title="Удалить позицию">Удалить позицию</button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" id="add-equipment-item" class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">+ Добавить позицию</button>
                            <x-input-error :messages="$errors->get('equipment')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="desired_delivery_date" value="Желаемая дата поставки" />
                            <x-text-input id="desired_delivery_date" class="block mt-1 w-full" type="date" name="desired_delivery_date" :value="old('desired_delivery_date')" :min="now()->format('Y-m-d')" required />
                            <x-input-error :messages="$errors->get('desired_delivery_date')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4 mt-6">
                            <x-primary-button>Создать заявку</x-primary-button>
                            <a href="{{ route('applications.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script type="text/template" id="equipment-row-tpl">
        <div class="equipment-row p-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Выбрать оборудование из списка</label>
                <select name="items[__INDEX__][equipment_type_id]" class="equipment-select block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                    <option value="">— Выберите оборудование —</option>
                    @foreach($equipmentTypes as $et)
                        <option value="{{ $et->id }}">{{ $et->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-600 pt-3">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Или вписать своё оборудование</label>
                <input type="text" name="items[__INDEX__][equipment_name]" placeholder="Название оборудования" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <div class="w-24">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Количество</label>
                    <input type="number" name="items[__INDEX__][quantity]" value="1" min="1" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm" required />
                </div>
                <button type="button" class="remove-item self-end px-3 py-1.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md" title="Удалить позицию">Удалить позицию</button>
            </div>
        </div>
    </script>
    <script>
        (function() {
            var container = document.getElementById('equipment-items');
            var tpl = document.getElementById('equipment-row-tpl').innerHTML;
            var nextIndex = container.querySelectorAll('.equipment-row').length;

            document.getElementById('add-equipment-item').addEventListener('click', function() {
                var html = tpl.replace(/__INDEX__/g, nextIndex++);
                container.insertAdjacentHTML('beforeend', html);
                container.querySelectorAll('.remove-item').forEach(function(btn) {
                    btn.onclick = removeHandler;
                });
            });

            function removeHandler() {
                var row = this.closest('.equipment-row');
                if (container.querySelectorAll('.equipment-row').length > 1) row.remove();
            }
            container.querySelectorAll('.remove-item').forEach(function(btn) {
                btn.onclick = removeHandler;
            });
        })();
    </script>
</x-app-layout>
