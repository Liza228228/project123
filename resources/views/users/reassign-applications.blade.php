<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('users.edit', $user)">Редактирование пользователя</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Переназначение заявок мастера
            </h2>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-app-alert type="info">
                Мастер: <span class="font-semibold">{{ $user->surname }} {{ $user->name }} {{ $user->patronymic }}</span>.
                Для каждой заявки выберите другого активного мастера из того же подразделения, что и заявка.
            </x-app-alert>

            @if($applicationRows->isEmpty())
                <x-app-alert type="warning">
                    У мастера нет активных заявок (автор или ответственный), требующих переназначения.
                </x-app-alert>
                <a href="{{ route('users.edit', $user) }}" class="ui-btn ui-btn--secondary">Назад</a>
            @else
                <form method="post" action="{{ route('users.reassign-applications.store', $user) }}" class="app-form-card">
                    @csrf
                    <div class="px-4 py-5 sm:p-8 space-y-6">
                        <div class="app-table-shell">
                            <table class="text-sm">
                                <thead class="bg-orange-100/70 dark:bg-orange-900/35">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold">Заявка</th>
                                        <th class="px-3 py-2 text-left font-semibold">Подразделение</th>
                                        <th class="px-3 py-2 text-left font-semibold">Роль мастера</th>
                                        <th class="px-3 py-2 text-left font-semibold">Новый мастер</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-orange-100/90 dark:divide-orange-900/30">
                                    @foreach($applicationRows as $row)
                                        @php
                                            /** @var \App\Models\Application $application */
                                            $application = $row['application'];
                                            $foremen = $row['foremen'];
                                        @endphp
                                        <tr class="bg-white/90 dark:bg-stone-900/40 align-top">
                                            <td class="px-3 py-3 whitespace-nowrap">
                                                <span class="font-medium text-stone-900 dark:text-stone-100">
                                                    №{{ $application->id }}
                                                </span>
                                                @if($application->desired_delivery_date)
                                                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-0.5">{{ $application->desired_delivery_date->format('d.m.Y') }}</p>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3">{{ $application->subdivision?->name ?? '—' }}</td>
                                            <td class="px-3 py-3 text-xs">
                                                @if((int) $application->user_id === (int) $user->id)
                                                    <span class="block">автор</span>
                                                @endif
                                                @if((int) $application->responsible_user_id === (int) $user->id)
                                                    <span class="block">ответственный</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 min-w-[12rem]">
                                                @if($foremen === [])
                                                    <p class="text-xs text-amber-800 dark:text-amber-200">
                                                        Нет другого мастера в подразделении. Добавьте назначение в «Назначение подразделений мастерам».
                                                    </p>
                                                @else
                                                    <select
                                                        name="reassignments[{{ $application->id }}]"
                                                        class="app-select text-sm"
                                                        required
                                                    >
                                                        <option value="">— выберите мастера —</option>
                                                        @foreach($foremen as $foreman)
                                                            <option value="{{ $foreman['id'] }}" @selected((string) old('reassignments.'.$application->id) === (string) $foreman['id'])>
                                                                {{ $foreman['label'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('reassignments.'.$application->id)
                                                        <p class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ $message }}</p>
                                                    @enderror
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @error('reassignments')
                            <x-app-alert type="error">{{ $message }}</x-app-alert>
                        @enderror

                        <div class="app-form-actions-mobile">
                            <button type="submit" class="ui-btn ui-btn--primary" @disabled($applicationRows->contains(fn ($row) => $row['foremen'] === []))>
                                Сохранить переназначение
                            </button>
                            <a href="{{ route('users.edit', $user) }}" class="ui-btn ui-btn--secondary">Отмена</a>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
