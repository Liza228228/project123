<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">Новый подвал</h2>
            <a href="{{ route('applications.report.footers.index') }}" class="text-sm text-black dark:text-white hover:underline">К списку подвалов</a>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="post" action="{{ route('applications.report.footers.store') }}" class="bg-white dark:bg-orange-950 rounded-lg border border-orange-200 dark:border-orange-800 shadow-sm p-4 sm:p-6 space-y-6">
                @csrf
                @include('applications.report.footers._fields', ['settings' => $settings, 'fontOptions' => $fontOptions, 'nameValue' => old('name', '')])
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-orange-600 hover:bg-orange-700">Сохранить</button>
                    <a href="{{ route('applications.report.footers.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-orange-300 dark:border-orange-600">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
