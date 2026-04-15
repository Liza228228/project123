<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Восстановление базы данных
            </h2>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50 whitespace-nowrap">
                В панель администратора
            </a>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12" x-data="{ restoreModalOpen: false }">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-stone-100 dark:bg-stone-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 px-4 py-3 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-100 text-sm">
                    <ul class="list-disc ml-4 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-6 space-y-5">
                    <div class="rounded-lg border border-orange-300 dark:border-orange-700 bg-orange-50/80 dark:bg-orange-900/20 px-4 py-3 text-sm text-black dark:text-white">
                        <p class="font-semibold">Внимание: критическая операция</p>
                        <p class="mt-1">Восстановление перезапишет текущие данные базы. Перед запуском убедитесь, что у вас есть актуальный резервный дамп.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                        <form method="POST" action="{{ route('admin.database.backup.store') }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                Сделать backup
                            </button>
                        </form>
                    </div>

                    <form id="database-restore-form" method="POST" action="{{ route('admin.database.restore.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div>
                            <label for="dump_file" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">
                                SQL-дамп
                            </label>
                            <input id="dump_file" name="dump_file" type="file" accept=".sql" required
                                   class="block w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500" />
                            <p class="mt-1 text-xs text-black/60 dark:text-white/60">Поддерживается формат: .sql. Максимум 50 МБ.</p>
                        </div>

                        <div>
                            <label for="confirm_phrase" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">
                                Подтверждение
                            </label>
                            <input id="confirm_phrase" name="confirm_phrase" type="text" value="{{ old('confirm_phrase') }}" required
                                   placeholder="Введите: ВОССТАНОВИТЬ"
                                   class="block w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500" />
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2 pt-1 sm:justify-end">
                            <button type="button" x-on:click="$dispatch('open-modal', 'confirm-database-restore')" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-2.5 text-sm font-medium text-red-700 rounded-lg bg-red-50 border border-red-200 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800 dark:hover:bg-red-900/40 dark:focus:ring-offset-stone-950">
                                Запустить восстановление
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-6 bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-6 space-y-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-semibold text-black dark:text-white">Журнал операций backup / restore</h3>
                        <p class="mt-1 text-xs text-black/60 dark:text-white/60">Отслеживание: кто выполнил операцию, когда и с каким файлом.</p>
                    </div>

                    <div class="app-table-shell">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Дата/время</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Операция</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Статус</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Файл</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Пользователь</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr class="align-top">
                                        <td class="px-4 py-3 text-sm text-black dark:text-white whitespace-nowrap">{{ $log->executed_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white">{{ $log->operation_type === 'backup' ? 'Backup' : 'Restore' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($log->status === 'success')
                                                <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white">Успешно</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-200">Ошибка</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white break-all max-w-[18rem]">
                                            <div>{{ $log->file_name }}</div>
                                            @if($log->error_message)
                                                <div class="mt-1 text-xs text-red-700 dark:text-red-200 break-words">{{ $log->error_message }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white">
                                            @if($log->user)
                                                {{ trim($log->user->surname.' '.$log->user->name.' '.$log->user->patronymic) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-black dark:text-white">Операций пока не было.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($logs->hasPages())
                        <div class="pt-1">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <x-modal name="confirm-database-restore" :show="false" maxWidth="md" focusable>
        <div class="p-5 sm:p-6 space-y-4">
            <h3 class="text-base sm:text-lg font-semibold text-black dark:text-white">
                Подтверждение восстановления БД
            </h3>
            <p class="text-sm text-black dark:text-white/85">
                Восстановление перезапишет текущие данные. Продолжить выполнение операции?
            </p>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
                <button type="button"
                    x-on:click="$dispatch('close-modal', 'confirm-database-restore')"
                    class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                    Отмена
                </button>
                <button type="button"
                    x-on:click="document.getElementById('database-restore-form')?.submit()"
                    class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-2.5 text-sm font-medium text-red-700 rounded-lg bg-red-50 border border-red-200 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800 dark:hover:bg-red-900/40 dark:focus:ring-offset-stone-950">
                    Да, восстановить
                </button>
            </div>
        </div>
    </x-modal>
</x-app-layout>
