<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Управление пользователями
            </h2>
            <a href="{{ route('users.create') }}" class="ui-btn ui-btn--primary gap-2 shrink-0 whitespace-nowrap w-full sm:w-auto [touch-action:manipulation]">
                Добавить пользователя
            </a>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-stone-100 dark:bg-stone-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-6">
                    @if($users->isEmpty())
                        <p class="md:hidden py-6 text-center text-sm text-black dark:text-white">Пользователей пока нет.</p>
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
                                                    <button type="submit" class="inline-flex w-full items-center justify-center px-4 py-3 text-sm font-medium text-black rounded-lg bg-stone-100 border border-stone-300 transition hover:bg-stone-200 focus:outline-none focus:ring-2 focus:ring-stone-500 focus:ring-offset-2 dark:bg-stone-900/40 dark:text-white dark:border-stone-700 dark:hover:bg-stone-800/50 dark:focus:ring-offset-stone-950 [touch-action:manipulation]">
                                                        Разблокировать
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('users.block', $user) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="inline-flex w-full items-center justify-center px-4 py-3 text-sm font-medium text-red-700 rounded-lg bg-red-50 border border-red-200 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800 dark:hover:bg-red-900/40 dark:focus:ring-offset-stone-950 [touch-action:manipulation]">
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
                                                            <button type="submit" class="inline-flex w-full sm:w-auto items-center justify-center px-3 py-2 text-sm font-medium text-black rounded-lg bg-stone-100 border border-stone-300 transition hover:bg-stone-200 focus:outline-none focus:ring-2 focus:ring-stone-500 focus:ring-offset-2 dark:bg-stone-900/40 dark:text-white dark:border-stone-700 dark:hover:bg-stone-800/50 dark:focus:ring-offset-stone-950 [touch-action:manipulation]">
                                                                Разблокировать
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form action="{{ route('users.block', $user) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="submit" class="inline-flex w-full sm:w-auto items-center justify-center px-3 py-2 text-sm font-medium text-red-700 rounded-lg bg-red-50 border border-red-200 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800 dark:hover:bg-red-900/40 dark:focus:ring-offset-stone-950 [touch-action:manipulation]">
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
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-black dark:text-white">Пользователей пока нет.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
