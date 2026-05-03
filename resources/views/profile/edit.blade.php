<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('dashboard')">Главная</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
                Профиль
            </h2>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4 sm:space-y-6">
            <div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-gradient-to-br from-orange-50/80 via-orange-50/40 to-amber-50/30 shadow-sm ring-1 ring-orange-100/80 dark:border-orange-900/40 dark:bg-stone-950 dark:ring-orange-950/30">
                <div class="px-5 py-5 sm:px-8 sm:py-7">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-stone-900 dark:text-white break-words">
                                {{ trim($user->surname.' '.$user->name.' '.$user->patronymic) }}
                            </h3>
                            <p class="mt-1 text-sm text-stone-600 dark:text-stone-300 break-all">{{ $user->email }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full border border-orange-300/80 bg-orange-100/90 px-3 py-1 text-xs font-semibold text-orange-950 dark:border-orange-700 dark:bg-orange-950/45 dark:text-orange-100">
                                {{ $user->role?->name ?? 'Роль не назначена' }}
                            </span>
                            <span class="inline-flex items-center rounded-full border border-stone-200/90 bg-white/85 px-3 py-1 text-xs font-medium text-stone-700 dark:border-stone-700 dark:bg-stone-900/50 dark:text-stone-200">
                                Личный кабинет
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-orange-50/35 dark:bg-stone-950 shadow-sm border border-orange-200/80 dark:border-stone-700 rounded-2xl ring-1 ring-orange-100/70">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-orange-50/35 dark:bg-stone-950 shadow-sm border border-orange-200/80 dark:border-stone-700 rounded-2xl ring-1 ring-orange-100/70">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
