<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Панель управления
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (Auth::user()->role === \App\Models\User::ROLE_SITE_FOREMAN)
                <div class="mb-6">
                    <a href="{{ route('applications.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 text-sm font-medium rounded-md hover:bg-gray-700 dark:hover:bg-gray-300">
                        Перейти к заявкам
                    </a>
                </div>
            @endif
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    Вы вошли в систему.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
