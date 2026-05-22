<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.show', $application)">Заявка №{{ $application->id }}</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Изменить ответственного
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-6">
                <p class="text-sm text-stone-700 dark:text-stone-300">
                    Текущий ответственный: <span class="font-medium text-stone-900 dark:text-stone-100">{{ $responsible->surname }} {{ $responsible->name }} {{ $responsible->patronymic }}</span>@if($responsible->is_blocked) <span class="text-amber-800 dark:text-amber-200/90">(учётная запись заблокирована)</span>@endif.
                    Подразделение заявки: <span class="font-medium text-stone-900 dark:text-stone-100">{{ $application->subdivision?->name ?? '—' }}</span>.
                </p>
                

                @if($replacementForemen->isEmpty())
                    <div class="rounded-xl border border-amber-200/90 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                        Нет других мастеров, закреплённых за этим подразделением. Добавьте назначение в разделе «Назначение подразделений мастерам».
                    </div>
                    <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--secondary inline-flex">Назад к заявке</a>
                @else
                    <form method="post" action="{{ route('applications.responsible.update', $application) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="responsible_user_id" class="app-form-label">Новый ответственный (мастер участка)</label>
                            <select id="responsible_user_id" name="responsible_user_id" class="app-select" required>
                                <option value="">— выберите мастера —</option>
                                @foreach($replacementForemen as $fu)
                                    <option value="{{ $fu->id }}" @selected((string) old('responsible_user_id') === (string) $fu->id)>
                                        {{ $fu->surname }} {{ $fu->name }} {{ $fu->patronymic }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('responsible_user_id')" class="mt-1.5" />
                        </div>

                        <div class="app-form-actions-mobile">
                            <button type="submit" class="ui-btn ui-btn--primary">Сохранить</button>
                            <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--secondary">Отмена</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
