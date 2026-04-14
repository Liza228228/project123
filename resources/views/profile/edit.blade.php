<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
            Профиль
        </h2>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4 sm:space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-stone-950 shadow-sm border border-stone-200 dark:border-stone-700 rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-stone-950 shadow-sm border border-stone-200 dark:border-stone-700 rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
