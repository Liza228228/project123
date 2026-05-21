<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight">
            Аналитика
        </h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-0 py-2 sm:px-6 sm:py-6 lg:px-8">
        @if($applicationAnalytics !== null)
            <section aria-labelledby="dash-applications-heading" class="rounded-2xl border border-orange-200/90 bg-gradient-to-br from-orange-50/95 via-white to-amber-50/40 p-5 shadow-md shadow-orange-950/[0.06] ring-1 ring-orange-100/80 dark:border-orange-900/55 dark:from-orange-950/50 dark:via-stone-950 dark:to-stone-950 dark:shadow-black/30 dark:ring-orange-950/40 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <h2 id="dash-applications-heading" class="text-lg font-semibold text-stone-900 dark:text-white sm:text-xl">
                            Заявки
                        </h2>
                        <p class="max-w-2xl text-sm text-stone-600 dark:text-stone-300">
                            Активные заявки в вашей зоне видимости по статусу в системе. Нажмите на блок или кнопку, чтобы открыть список с фильтром.
                        </p>
                    </div>
                    <div class="flex flex-shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                        <a href="{{ route('applications.index') }}" class="ui-btn ui-btn--primary ui-btn--sm justify-center sm:justify-center">
                            Все заявки
                        </a>
                        <a href="{{ route('applications.archive') }}" class="ui-btn ui-btn--secondary ui-btn--sm justify-center">
                            Архив выполненных
                        </a>
                    </div>
                </div>

                @if(($applicationAnalytics['custom_equipment_pending'] ?? 0) > 0 && Auth::user()?->hasAnyRoleId(\App\Models\User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS))
                    <div class="mt-4 rounded-xl border border-amber-300/70 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/35 dark:text-amber-100">
                        <span class="font-medium">Своё оборудование к заказу:</span>
                        {{ (int) $applicationAnalytics['custom_equipment_pending'] }} позиций.
                        <a href="{{ route('applications.custom-equipment-to-order') }}" class="ms-1 font-medium text-orange-800 underline decoration-orange-400/80 underline-offset-2 hover:text-orange-950 dark:text-orange-200 dark:hover:text-white">Перейти</a>
                    </div>
                @endif

                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <a href="{{ route('applications.index', ['archive' => 'active']) }}" class="dash-stat-card group">
                        <span class="text-2xl font-bold tabular-nums text-orange-900 dark:text-orange-100 sm:text-3xl">{{ (int) $applicationAnalytics['total_active'] }}</span>
                        <span class="mt-1 text-[11px] sm:text-xs font-semibold leading-tight text-stone-800 dark:text-stone-100">Всего активных</span>
                        <p class="mt-1.5 flex-1 text-[10px] sm:text-[11px] leading-snug text-stone-500 dark:text-stone-400">Все заявки которые находятся в работе.</p>
                        <span class="mt-3 text-[11px] font-medium text-orange-800/90 transition group-hover:text-orange-950 dark:text-orange-300 dark:group-hover:text-orange-200">Перейти →</span>
                    </a>
                    <a href="{{ route('applications.index', ['approval_filter' => 'pending', 'archive' => 'active']) }}" class="dash-stat-card group">
                        <span class="text-2xl font-bold tabular-nums text-slate-800 dark:text-slate-100 sm:text-3xl">{{ (int) $applicationAnalytics['pending'] }}</span>
                        <span class="mt-1 text-[11px] sm:text-xs font-semibold leading-tight text-stone-800 dark:text-stone-100">На согласовании</span>
                        <p class="mt-1.5 flex-1 text-[10px] sm:text-[11px] leading-snug text-stone-500 dark:text-stone-400">Статус заявок, которые отправленны на согласование.</p>
                        <span class="mt-3 text-[11px] font-medium text-orange-800/90 transition group-hover:text-orange-950 dark:text-orange-300 dark:group-hover:text-orange-200">Перейти →</span>
                    </a>
                    <a href="{{ route('applications.index', ['approval_filter' => 'approved', 'archive' => 'active']) }}" class="dash-stat-card group">
                        <span class="text-2xl font-bold tabular-nums text-teal-800 dark:text-teal-100 sm:text-3xl">{{ (int) $applicationAnalytics['approved'] }}</span>
                        <span class="mt-1 text-[11px] sm:text-xs font-semibold leading-tight text-stone-800 dark:text-stone-100">Согласованы</span>
                        <p class="mt-1.5 flex-1 text-[10px] sm:text-[11px] leading-snug text-stone-500 dark:text-stone-400">Статус заявки, которые согласованы.</p>
                        <span class="mt-3 text-[11px] font-medium text-orange-800/90 transition group-hover:text-orange-950 dark:text-orange-300 dark:group-hover:text-orange-200">Перейти →</span>
                    </a>
                    <a href="{{ route('applications.index', ['approval_filter' => 'partial', 'archive' => 'active']) }}" class="dash-stat-card group">
                        <span class="text-2xl font-bold tabular-nums text-amber-900 dark:text-amber-100 sm:text-3xl">{{ (int) $applicationAnalytics['partial'] }}</span>
                        <span class="mt-1 text-[11px] sm:text-xs font-semibold leading-tight text-stone-800 dark:text-stone-100">Частично согласованы</span>
                        <p class="mt-1.5 flex-1 text-[10px] sm:text-[11px] leading-snug text-stone-500 dark:text-stone-400">Статус заявки, которые согласованы частично.</p>
                        <span class="mt-3 text-[11px] font-medium text-orange-800/90 transition group-hover:text-orange-950 dark:text-orange-300 dark:group-hover:text-orange-200">Перейти →</span>
                    </a>
                    <a href="{{ route('applications.index', ['approval_filter' => 'rejected', 'archive' => 'active']) }}" class="dash-stat-card group">
                        <span class="text-2xl font-bold tabular-nums text-rose-800 dark:text-rose-100 sm:text-3xl">{{ (int) $applicationAnalytics['rejected'] }}</span>
                        <span class="mt-1 text-[11px] sm:text-xs font-semibold leading-tight text-stone-800 dark:text-stone-100">Не согласованы</span>
                        <p class="mt-1.5 flex-1 text-[10px] sm:text-[11px] leading-snug text-stone-500 dark:text-stone-400">Заявки, которые не прошли согласование.</p>
                        <span class="mt-3 text-[11px] font-medium text-orange-800/90 transition group-hover:text-orange-950 dark:text-orange-300 dark:group-hover:text-orange-200">Перейти →</span>
                    </a>
                    <a href="{{ route('applications.archive') }}" class="dash-stat-card group">
                        <span class="text-2xl font-bold tabular-nums text-zinc-800 dark:text-zinc-100 sm:text-3xl">{{ (int) $applicationAnalytics['archived'] }}</span>
                        <span class="mt-1 text-[11px] sm:text-xs font-semibold leading-tight text-stone-800 dark:text-stone-100">В архиве</span>
                        <p class="mt-1.5 flex-1 text-[10px] sm:text-[11px] leading-snug text-stone-500 dark:text-stone-400">Завершённые заявки.</p>
                        <span class="mt-3 text-[11px] font-medium text-orange-800/90 transition group-hover:text-orange-950 dark:text-orange-300 dark:group-hover:text-orange-200">Перейти →</span>
                    </a>
                </div>
            </section>
        @endif

        @if($userDirectoryStats !== null)
            <section aria-labelledby="dash-users-heading" class="rounded-2xl border border-orange-200/90 bg-gradient-to-br from-orange-50/95 via-white to-amber-50/40 p-5 shadow-md shadow-orange-950/[0.06] ring-1 ring-orange-100/80 dark:border-orange-900/55 dark:from-orange-950/50 dark:via-stone-950 dark:to-stone-950 dark:shadow-black/30 dark:ring-orange-950/40 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <h2 id="dash-users-heading" class="text-lg font-semibold text-stone-900 dark:text-white sm:text-xl">
                            Пользователи
                        </h2>
                        
                    </div>
                    <div class="flex flex-shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                        <a href="{{ route('users.index') }}" class="ui-btn ui-btn--primary ui-btn--sm justify-center sm:justify-center">
                            Список пользователей
                        </a>
                    </div>
                </div>

                <div class="mt-6 grid max-w-2xl grid-cols-1 gap-3 sm:grid-cols-2">
                    <a href="{{ route('users.index') }}" class="dash-stat-card group">
                        <span class="text-2xl font-bold tabular-nums text-orange-900 dark:text-orange-100 sm:text-3xl">{{ (int) $userDirectoryStats['total_users'] }}</span>
                        <span class="mt-1 text-[11px] sm:text-xs font-semibold leading-tight text-stone-800 dark:text-stone-100">Всего пользователей</span>
                        <p class="mt-1.5 flex-1 text-[10px] sm:text-[11px] leading-snug text-stone-500 dark:text-stone-400">Все пользователи системы.</p>
                        <span class="mt-3 text-[11px] font-medium text-orange-800/90 transition group-hover:text-orange-950 dark:text-orange-300 dark:group-hover:text-orange-200">Перейти →</span>
                    </a>
                    <a href="{{ route('users.index', ['status' => 'blocked']) }}" class="dash-stat-card group">
                        <span class="text-2xl font-bold tabular-nums text-orange-900 dark:text-orange-100 sm:text-3xl">{{ (int) $userDirectoryStats['blocked_users'] }}</span>
                        <span class="mt-1 text-[11px] sm:text-xs font-semibold leading-tight text-stone-800 dark:text-stone-100">Заблокировано</span>
                        <p class="mt-1.5 flex-1 text-[10px] sm:text-[11px] leading-snug text-stone-500 dark:text-stone-400">Заблокированные пользователи.</p>
                        <span class="mt-3 text-[11px] font-medium text-orange-800/90 transition group-hover:text-orange-950 dark:text-orange-300 dark:group-hover:text-orange-200">Перейти →</span>
                    </a>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
