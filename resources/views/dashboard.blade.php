<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
            Панель управления
        </h2>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4 sm:space-y-6">
            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-8">
                    <h2 class="text-xl sm:text-2xl font-semibold text-black dark:text-white mb-3">
                        Централизованный контроль заявок и материалов
                    </h2>
                    <p class="text-black dark:text-white max-w-3xl opacity-90">
                        КТ-Ресурс помогает подразделениям создавать и согласовывать заявки, контролировать статус позиций
                        и вести прозрачный процесс закупки оборудования в едином интерфейсе.
                    </p>
                </div>
            </div>

            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-6">
                    <p class="text-black dark:text-white mb-4 sm:mb-6 text-base">Выберите раздел для работы:</p>

                    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3">
                        @if ((int) Auth::user()->role_id === 5)
                            <a href="{{ route('users.index') }}" class="ui-btn ui-btn--primary ui-btn--lg w-full sm:w-auto [touch-action:manipulation]">
                                Управление пользователями
                            </a>
                            <a href="{{ route('admin.database.restore.index') }}" class="ui-btn ui-btn--primary ui-btn--lg w-full sm:w-auto [touch-action:manipulation]">
                                Backup / Восстановление БД
                            </a>
                        @endif

                        @if (Auth::user()->hasAnyRoleId([1, 6, 4, 2, 3]))
                            <a href="{{ route('applications.index') }}" class="ui-btn ui-btn--primary ui-btn--lg w-full sm:w-auto [touch-action:manipulation]">
                                Заявки
                            </a>
                        @endif

                        @if (Auth::user()->hasAnyRoleId([1, 6, 2]))
                            <a href="{{ route('applications.report.index') }}" class="ui-btn ui-btn--primary ui-btn--lg w-full sm:w-auto [touch-action:manipulation]">
                                Отчёт по заявкам
                            </a>
                        @endif

                        @if (Auth::user()->hasAnyRoleId([1, 2]))
                            <a href="{{ route('materials.index') }}" class="ui-btn ui-btn--primary ui-btn--lg w-full sm:w-auto [touch-action:manipulation]">
                                Учёт оборудования
                            </a>
                        @endif

                        @if (Auth::user()->hasAnyRoleId([1, 6, 2, 3]))
                            <a href="{{ route('foreman-subdivisions.index') }}" class="ui-btn ui-btn--primary ui-btn--lg w-full sm:w-auto [touch-action:manipulation]">
                               Подразделения
                            </a>
                        @endif

                        @if (Auth::user()->hasAnyRoleId([1, 6, 2]))
                            <a href="{{ route('foreman-subdivisions.assignments') }}" class="ui-btn ui-btn--primary ui-btn--lg w-full sm:w-auto [touch-action:manipulation]">
                               Назначения мастерам
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <h3 class="font-semibold text-black dark:text-white">Управление заявками</h3>
                        <p class="mt-2 text-sm text-black dark:text-white opacity-90">Создание, редактирование и контроль повторных заявок по подразделениям.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <h3 class="font-semibold text-black dark:text-white">Согласование и контроль</h3>
                        <p class="mt-2 text-sm text-black dark:text-white opacity-90">Отметки одобрения, причины отклонений и прозрачный процесс принятия решений.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <h3 class="font-semibold text-black dark:text-white">Ролевой доступ</h3>
                        <p class="mt-2 text-sm text-black dark:text-white opacity-90">Разделение прав по ролям: администратор, директор, мастер участка и другие.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
