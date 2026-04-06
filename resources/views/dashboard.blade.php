<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
            Панель управления
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm sm:rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-6">
                    <p class="text-black dark:text-white mb-6 text-base">Выберите раздел для работы:</p>

                    <div class="flex flex-wrap gap-3">
                        @if ((int) Auth::user()->role_id === \App\Models\Role::ID_ADMINISTRATOR)
                            <a href="{{ route('users.index') }}" class="inline-flex items-center px-6 py-3 text-base font-semibold rounded-lg border border-orange-700 text-white bg-orange-600 shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:border-orange-500 dark:bg-orange-600 dark:text-white dark:hover:bg-orange-500 dark:focus:ring-offset-orange-950">
                                Управление пользователями
                            </a>
                        @endif

                        @if (in_array((int) Auth::user()->role_id, [\App\Models\Role::ID_DIRECTOR, \App\Models\Role::ID_SITE_FOREMAN, \App\Models\Role::ID_SUPPLY_DEPARTMENT_HEAD], true))
                            <a href="{{ route('applications.index') }}" class="inline-flex items-center px-6 py-3 text-base font-semibold rounded-lg border border-orange-700 text-white bg-orange-600 shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:border-orange-500 dark:bg-orange-600 dark:text-white dark:hover:bg-orange-500 dark:focus:ring-offset-orange-950">
                                Заявки
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
