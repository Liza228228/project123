<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-orange-950 dark:text-orange-50 leading-tight">
                Управление пользователями
            </h2>
            <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                Добавить пользователя
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-orange-100 dark:bg-orange-900/40 text-orange-900 dark:text-orange-100 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm sm:rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-orange-200 dark:divide-orange-800">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">ФИО</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Почта</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Роль</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Статус</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-orange-800 dark:text-orange-300 uppercase"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orange-200 dark:divide-orange-800">
                                @forelse($users as $user)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-orange-950 dark:text-orange-50">
                                            {{ $user->surname }} {{ $user->name }} {{ $user->patronymic }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-orange-900 dark:text-orange-200">{{ $user->email }}</td>
                                        <td class="px-4 py-3 text-sm text-orange-900 dark:text-orange-200">{{ $user->role?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($user->is_blocked)
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">
                                                    Заблокирован
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-900 dark:bg-orange-900/50 dark:text-orange-100">
                                                    Активен
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                                                    Изменить
                                                </a>
                                                @if($user->is_blocked)
                                                    <form action="{{ route('users.unblock', $user) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-orange-900 rounded-lg bg-orange-100 border border-orange-300 transition hover:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:bg-orange-900/40 dark:text-orange-100 dark:border-orange-700 dark:hover:bg-orange-800/50 dark:focus:ring-offset-orange-950">
                                                            Разблокировать
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('users.block', $user) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-700 rounded-lg bg-red-50 border border-red-200 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800 dark:hover:bg-red-900/40 dark:focus:ring-offset-orange-950">
                                                            Заблокировать
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-orange-700 dark:text-orange-300">Пользователей пока нет.</td>
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
