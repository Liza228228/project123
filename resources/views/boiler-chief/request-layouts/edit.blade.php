<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('boiler-chief.request-layouts.index')">К списку макетов отчетов</x-page-header-nav>
            <h2 class="font-semibold text-xl text-stone-900 dark:text-white">Редактирование макета</h2>
        </div>
    </x-slot>

    <div class="min-h-[70vh] py-6 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @include('boiler-chief.request-layouts._wizard-form', [
                'action' => route('boiler-chief.request-layouts.update', $layout),
                'httpMethod' => 'PUT',
                'layout' => $layout,
                'submitLabel' => 'Сохранить',
            ])
        </div>
    </div>
</x-app-layout>
