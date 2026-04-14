<x-app-layout>
    @php
        $previewSettings = array_replace_recursive(\App\Models\ApplicationReportHeader::defaultSettings(), old('settings', $settings));
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">Новая шапка</h2>
            <a href="{{ route('applications.report.headers.index') }}" class="text-sm text-black dark:text-white hover:underline">К списку шапок</a>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="post" action="{{ route('applications.report.headers.store') }}" class="bg-white dark:bg-stone-950 rounded-lg border border-stone-200 dark:border-stone-800 shadow-sm p-4 sm:p-6 space-y-6">
                @csrf
                @include('applications.report.headers._fields', ['settings' => $settings, 'fontOptions' => $fontOptions, 'nameValue' => old('name', '')])
                <div class="report-form-preview border border-stone-200 dark:border-stone-800 rounded-lg p-4 bg-stone-50/70 dark:bg-stone-900/40">
                    <h3 class="text-sm font-semibold text-black dark:text-white mb-3">Предпросмотр шапки</h3>
                    @include('applications.report.partials.form_preview_styles')
                    @include('applications.report.partials.act_header', ['s' => $previewSettings])
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="ui-btn ui-btn--primary">Сохранить</button>
                    <a href="{{ route('applications.report.headers.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
