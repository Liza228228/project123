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

                        <div class="mt-4">
                            <x-input-label value="Оборудование" />
                            <select id="equipment_type_id" name="equipment_type_id" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                <option value="">Выберите из списка</option>
                                @foreach($equipmentTypes as $et)
                                    <option value="{{ $et->id }}" @selected(old('equipment_type_id') == $et->id)>{{ $et->name }}</option>
                                @endforeach
                            </select>
                            <x-text-input id="equipment_name" class="block mt-2 w-full" type="text" name="equipment_name" :value="old('equipment_name')" placeholder="или введите вручную" />
                            <x-input-error :messages="$errors->get('equipment')" class="mt-2" />
                            <x-input-error :messages="$errors->get('equipment_name')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="quantity" value="Количество оборудования" />
                            <x-text-input id="quantity" class="block mt-1 w-full" type="number" name="quantity" :value="old('quantity', 1)" min="1" required />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="desired_delivery_date" value="Желаемая дата поставки" />
                            <x-text-input id="desired_delivery_date" class="block mt-1 w-full" type="date" name="desired_delivery_date" :value="old('desired_delivery_date')" required />
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
</x-app-layout>
