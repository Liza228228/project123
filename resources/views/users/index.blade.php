<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Управление пользователями
            </h2>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <a href="{{ route('users.create') }}" class="ui-btn ui-btn--primary gap-2 shrink-0 whitespace-nowrap w-full sm:w-auto [touch-action:manipulation]">
                    Добавить пользователя
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12"
         x-data="{ blockFormId: null, blockUserName: '' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-stone-100 dark:bg-stone-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <form method="get" action="{{ route('users.index') }}" class="flex flex-col gap-4">
                        <div class="app-filter-panel">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-end">
                            <div class="min-w-0 lg:col-span-2">
                                <label for="users-q" class="app-form-label">Поиск</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    </span>
                                    <input type="search" name="q" id="users-q" value="{{ $search }}"
                                        placeholder="ФИО или e-mail…"
                                        class="app-input app-input--with-icon">
                                </div>
                            </div>
                            <div class="min-w-0">
                                <label for="users-role-filter" class="app-form-label">Роль</label>
                                <select name="role_id" id="users-role-filter"
                                    class="app-select">
                                    <option value="">Все роли</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected($selectedRoleId === (int) $role->id)>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-0">
                                <label for="users-status-filter" class="app-form-label">Статус</label>
                                <select name="status" id="users-status-filter"
                                    class="app-select">
                                    <option value="all" @selected($statusFilter === 'all')>Все</option>
                                    <option value="active" @selected($statusFilter === 'active')>Активен</option>
                                    <option value="blocked" @selected($statusFilter === 'blocked')>Заблокирован</option>
                                </select>
                            </div>
                            <div class="min-w-0">
                                <label for="users-per-page" class="app-form-label">На странице</label>
                                <select name="per_page" id="users-per-page"
                                    class="app-select">
                                    @foreach([10, 25, 50] as $size)
                                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-0 lg:col-span-3">
                                <p class="app-form-label">Сортировка</p>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="sort_primary_field"
                                            class="app-select">
                                            <option value="surname" @selected(($sortState['primary_field'] ?? '') === 'surname')>Фамилия</option>
                                            <option value="name" @selected(($sortState['primary_field'] ?? '') === 'name')>Имя</option>
                                            <option value="patronymic" @selected(($sortState['primary_field'] ?? '') === 'patronymic')>Отчество</option>
                                            <option value="email" @selected(($sortState['primary_field'] ?? '') === 'email')>E-mail</option>
                                            <option value="role" @selected(($sortState['primary_field'] ?? '') === 'role')>Роль</option>
                                            <option value="status" @selected(($sortState['primary_field'] ?? '') === 'status')>Статус</option>
                                            <option value="created_at" @selected(($sortState['primary_field'] ?? '') === 'created_at')>Дата создания</option>
                                        </select>
                                        <select name="sort_primary_direction"
                                            class="app-select">
                                            <option value="asc" @selected(($sortState['primary_direction'] ?? '') === 'asc')>По возрастанию</option>
                                            <option value="desc" @selected(($sortState['primary_direction'] ?? '') === 'desc')>По убыванию</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="sort_secondary_field"
                                            class="app-select">
                                            <option value="" @selected(empty($sortState['secondary_field']))>Без второго поля</option>
                                            <option value="surname" @selected(($sortState['secondary_field'] ?? '') === 'surname')>Фамилия</option>
                                            <option value="name" @selected(($sortState['secondary_field'] ?? '') === 'name')>Имя</option>
                                            <option value="patronymic" @selected(($sortState['secondary_field'] ?? '') === 'patronymic')>Отчество</option>
                                            <option value="email" @selected(($sortState['secondary_field'] ?? '') === 'email')>E-mail</option>
                                            <option value="role" @selected(($sortState['secondary_field'] ?? '') === 'role')>Роль</option>
                                            <option value="status" @selected(($sortState['secondary_field'] ?? '') === 'status')>Статус</option>
                                            <option value="created_at" @selected(($sortState['secondary_field'] ?? '') === 'created_at')>Дата создания</option>
                                        </select>
                                        <select name="sort_secondary_direction"
                                            class="app-select">
                                            <option value="asc" @selected(($sortState['secondary_direction'] ?? '') === 'asc')>По возрастанию</option>
                                            <option value="desc" @selected(($sortState['secondary_direction'] ?? '') === 'desc')>По убыванию</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2 pt-1 sm:justify-end">
                            <button type="submit" class="ui-btn ui-btn--primary w-full min-h-[44px] py-3 sm:min-h-0 sm:w-auto sm:py-2 whitespace-nowrap shrink-0 [touch-action:manipulation]">
                                Применить
                            </button>
                            @if(
                                $search !== ''
                                || $selectedRoleId !== null
                                || $statusFilter !== 'all'
                                || (($sortState['primary_field'] ?? 'surname') !== 'surname')
                                || (($sortState['primary_direction'] ?? 'asc') !== 'asc')
                                || !empty($sortState['secondary_field'])
                                || (($sortState['secondary_direction'] ?? 'asc') !== 'asc')
                            )
                                <a href="{{ route('users.index') }}" class="ui-btn ui-btn--secondary w-full shrink-0 whitespace-nowrap sm:w-auto [touch-action:manipulation]">
                                    Сбросить
                                </a>
                            @endif
                        </div>
                    </form>

                    @if($users->isEmpty())
                        <p class="md:hidden py-6 text-center text-sm text-black dark:text-white">
                            @if($search !== '' || $selectedRoleId !== null || $statusFilter !== 'all')
                                По заданным условиям пользователей не найдено.
                            @else
                                Пользователей пока нет.
                            @endif
                        </p>
                    @else
                        <div class="md:hidden space-y-4">
                            @foreach($users as $user)
                                <article class="rounded-xl border border-stone-200 dark:border-stone-800 bg-stone-50/30 dark:bg-stone-900/20 p-4 space-y-3 shadow-sm">
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">ФИО</p>
                                        <p class="text-sm font-medium text-black dark:text-white break-words">{{ $user->surname }} {{ $user->name }} {{ $user->patronymic }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Почта</p>
                                        <p class="text-sm text-black dark:text-white break-all">{{ $user->email }}</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2 items-center">
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Роль</p>
                                            <p class="text-sm text-black dark:text-white">{{ $user->role?->name ?? '—' }}</p>
                                        </div>
                                        <div class="ms-auto">
                                            @if($user->is_blocked)
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">Заблокирован</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-stone-100 text-black dark:bg-stone-900/50 dark:text-white">Активен</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2 pt-1">
                                        <a href="{{ route('users.edit', $user) }}" class="ui-btn ui-btn--primary flex w-full min-h-[44px] py-3 sm:min-h-0 sm:py-2 [touch-action:manipulation]">
                                            Изменить
                                        </a>
                                        @unless($user->is(Auth::user()))
                                            @if($user->is_blocked)
                                                <form action="{{ route('users.unblock', $user) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="ui-btn ui-btn--secondary w-full [touch-action:manipulation]">
                                                        Разблокировать
                                                    </button>
                                                </form>
                                            @else
                                                <form id="block-user-mobile-{{ $user->id }}" action="{{ route('users.block', $user) }}" method="POST">
                                                    @csrf
                                                    <button type="button"
                                                        x-on:click="
                                                            blockFormId = 'block-user-mobile-{{ $user->id }}';
                                                            blockUserName = '{{ trim($user->surname.' '.$user->name.' '.$user->patronymic) }}';
                                                            $dispatch('open-modal', 'confirm-user-block');
                                                        "
                                                        class="ui-btn ui-btn--danger w-full [touch-action:manipulation]">
                                                        Заблокировать
                                                    </button>
                                                </form>
                                            @endif
                                        @endunless
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    <div class="hidden md:block app-table-shell">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">ФИО</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Почта</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Роль</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Статус</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-black dark:text-white uppercase w-[1%] whitespace-nowrap"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                    <tr class="align-top">
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top break-words max-w-[12rem]">
                                            {{ $user->surname }} {{ $user->name }} {{ $user->patronymic }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top break-all max-w-[14rem]">{{ $user->email }}</td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">{{ $user->role?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm align-top">
                                            @if($user->is_blocked)
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">
                                                    Заблокирован
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-stone-100 text-black dark:bg-stone-900/50 dark:text-white">
                                                    Активен
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right align-top whitespace-nowrap">
                                            <div class="inline-flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                                <a href="{{ route('users.edit', $user) }}" class="ui-btn ui-btn--primary [touch-action:manipulation]">
                                                    Изменить
                                                </a>
                                                @unless($user->is(Auth::user()))
                                                    @if($user->is_blocked)
                                                        <form action="{{ route('users.unblock', $user) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="submit" class="ui-btn ui-btn--secondary w-full sm:w-auto [touch-action:manipulation]">
                                                                Разблокировать
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form id="block-user-desktop-{{ $user->id }}" action="{{ route('users.block', $user) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="button"
                                                                x-on:click="
                                                                    blockFormId = 'block-user-desktop-{{ $user->id }}';
                                                                    blockUserName = '{{ trim($user->surname.' '.$user->name.' '.$user->patronymic) }}';
                                                                    $dispatch('open-modal', 'confirm-user-block');
                                                                "
                                                                class="ui-btn ui-btn--danger w-full sm:w-auto [touch-action:manipulation]">
                                                                Заблокировать
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endunless
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-black dark:text-white">
                                            @if($search !== '' || $selectedRoleId !== null || $statusFilter !== 'all')
                                                По заданным условиям пользователей не найдено.
                                            @else
                                                Пользователей пока нет.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($users->hasPages())
                        <div class="pt-2">
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <x-modal name="confirm-user-block" :show="false" maxWidth="md" focusable>
            <div class="p-5 sm:p-6 space-y-4">
                <h3 class="text-base sm:text-lg font-semibold text-black dark:text-white">
                    Подтверждение блокировки
                </h3>
                <p class="text-sm text-black dark:text-white/85">
                    Вы действительно хотите заблокировать пользователя
                    <span class="font-semibold" x-text="blockUserName || '—'"></span>?
                </p>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
                    <button type="button"
                        x-on:click="$dispatch('close-modal', 'confirm-user-block')"
                        class="ui-btn ui-btn--secondary w-full sm:w-auto">
                        Отмена
                    </button>
                    <button type="button"
                        x-on:click="
                            if (blockFormId) {
                                document.getElementById(blockFormId)?.submit();
                            }
                        "
                        class="ui-btn ui-btn--danger w-full sm:w-auto">
                        Да, заблокировать
                    </button>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
