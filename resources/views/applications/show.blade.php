<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('applications.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 text-sm">← Заявки</a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Просмотр заявки
            </h2>
            @if (Auth::user()->role === \App\Models\User::ROLE_SITE_FOREMAN)
                <a href="{{ route('applications.edit', $application) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white rounded-lg bg-indigo-600 shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Изменить
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm">
                    {{ session('status') }}
                </div>
            @endif
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Данные заявки</h3>
                        <dl class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Подразделение</dt>
                                <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $application->subdivision->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Ответственный</dt>
                                <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    @if($application->responsibleUser)
                                        {{ $application->responsibleUser->surname }} {{ $application->responsibleUser->name }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Желаемая дата поставки</dt>
                                <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $application->desired_delivery_date->format('d.m.Y') }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Оборудование</h3>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                            @foreach($application->items as $item)
                                <li class="px-4 py-3 bg-gray-50/50 dark:bg-gray-900/30 {{ !$item->is_checked ? 'space-y-2' : '' }}">
                                    <div class="flex items-center gap-4">
                                        <form action="{{ route('applications.items.toggle', $item) }}" method="POST" class="flex items-center gap-4 flex-1">
                                            @csrf
                                            <label class="flex items-center gap-2 cursor-pointer flex-1 min-w-0">
                                                <input type="checkbox"
                                                    {{ $item->is_checked ? 'checked' : '' }}
                                                    onchange="this.form.submit()"
                                                    class="h-5 w-5 shrink-0 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:checked:bg-indigo-600"
                                                />
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $item->equipment_display_name }} × {{ $item->quantity }}
                                                </span>
                                            </label>
                                        </form>
                                    </div>
                                    @if(!$item->is_checked)
                                        <div class="pl-7">
                                            <form action="{{ route('applications.items.reason', $item) }}" method="POST" class="flex flex-col sm:flex-row gap-2">
                                                @csrf
                                                @method('PUT')
                                                <label class="sr-only" for="reason-{{ $item->id }}">Причина, почему не выбран (обязательно)</label>
                                                <input type="text"
                                                    id="reason-{{ $item->id }}"
                                                    name="reason_not_selected"
                                                    value="{{ old('reason_not_selected', $item->reason_not_selected) }}"
                                                    placeholder="Обязательно укажите причину, почему не выбран"
                                                    maxlength="500"
                                                    required
                                                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 @error('reason_not_selected') border-red-500 dark:border-red-400 @enderror"
                                                />
                                                <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium text-white rounded-lg bg-indigo-600 shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 shrink-0">
                                                    Сохранить
                                                </button>
                                            </form>
                                            @if($errors->has('reason_not_selected') && session('reason_error_item_id') == $item->id)
                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $errors->first('reason_not_selected') }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if($application->items->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400 py-3">Позиций нет.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
