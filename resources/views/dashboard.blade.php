<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Панель управления
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <p class="text-gray-700 dark:text-gray-200 mb-6 text-base">Выберите раздел для работы:</p>

                    <div class="flex flex-wrap gap-3">
                        @if ((int) Auth::user()->role_id === \App\Models\Role::ID_ADMINISTRATOR)
                            <a href="{{ route('users.index') }}" class="inline-flex items-center px-6 py-3 text-base font-semibold rounded-lg border border-slate-700 text-white bg-slate-700 shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 dark:border-slate-500 dark:bg-slate-200 dark:text-slate-900 dark:hover:bg-white dark:focus:ring-offset-slate-800">
                                Управление пользователями
                            </a>
                        @endif

                        @if (in_array((int) Auth::user()->role_id, [\App\Models\Role::ID_DIRECTOR, \App\Models\Role::ID_SITE_FOREMAN, \App\Models\Role::ID_SUPPLY_DEPARTMENT_HEAD], true))
                            <a href="{{ route('applications.index') }}" class="inline-flex items-center px-6 py-3 text-base font-semibold rounded-lg border border-slate-700 text-white bg-slate-700 shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 dark:border-slate-500 dark:bg-slate-200 dark:text-slate-900 dark:hover:bg-white dark:focus:ring-offset-slate-800">
                                Заявки
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
