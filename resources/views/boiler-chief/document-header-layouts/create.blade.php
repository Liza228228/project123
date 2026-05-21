<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="$backHref">{{ $backLabel }}</x-page-header-nav>
            <h2 class="font-semibold text-xl text-stone-900 dark:text-white">Новый макет шапки</h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @include('boiler-chief.document-header-layouts._form', [
                'action' => route('boiler-chief.document-header-layouts.store'),
                'httpMethod' => 'POST',
                'layout' => null,
                'submitLabel' => 'Сохранить',
                'returnTo' => $returnTo ?? null,
                'backHref' => $backHref,
            ])
        </div>
    </div>
</x-app-layout>
