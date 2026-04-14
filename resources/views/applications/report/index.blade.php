<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Сборка отчёта по заявкам
            </h2>
            <a href="{{ route('applications.index') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50 shrink-0 whitespace-nowrap w-full sm:w-auto">
                К списку заявок
            </a>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 rounded-md bg-stone-100 dark:bg-stone-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex flex-col sm:flex-row flex-wrap gap-2">
                <a href="{{ route('applications.report.headers.index') }}" class="ui-btn ui-btn--primary">Шаблоны шапок</a>
                <a href="{{ route('applications.report.footers.index') }}" class="ui-btn ui-btn--primary">Шаблоны подвалов</a>
            </div>

            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-6 space-y-8">
                    @php
                        $includeApplicationsTable = old('include_applications_table') === '1';
                    @endphp
                    <p class="text-sm text-black dark:text-white opacity-90">
                        Сначала создайте и сохраните нужные <strong class="font-semibold">шапки</strong> и <strong class="font-semibold">подвалы</strong> (как в типовом акте: организация, «Утверждаю», заголовок, дата и город, блоки подписей).
                        Здесь выберите шаблоны, задайте шрифты для вводного текста и таблицы, напишите основной текст, отметьте заявки и откройте предпросмотр для печати или сохранения в PDF.
                    </p>

                    <form method="get" action="{{ route('applications.report.index') }}" class="flex flex-col gap-4 border-b border-stone-200 dark:border-stone-800 pb-6">
                        <h3 class="text-sm font-semibold text-black dark:text-white">Фильтр списка заявок</h3>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(14rem,20rem)] lg:items-end">
                            <div class="min-w-0">
                                <label for="report-q" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Поиск</label>
                                <input type="search" name="q" id="report-q" value="{{ $search }}"
                                    placeholder="Как в списке заявок…"
                                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                            </div>
                            <div class="min-w-0">
                                <label for="report-equipment-filter" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Статус согласования</label>
                                <select name="equipment_filter" id="report-equipment-filter"
                                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                                    <option value="all" @selected($equipmentFilter === 'all')>Все заявки</option>
                                    <option value="has_approved" @selected($equipmentFilter === 'has_approved')>Есть согласованные позиции</option>
                                    <option value="has_not_approved" @selected($equipmentFilter === 'has_not_approved')>Есть несогласованные позиции</option>
                                    <option value="fully_approved" @selected($equipmentFilter === 'fully_approved')>Все позиции согласованы</option>
                                    <option value="on_approval" @selected($equipmentFilter === 'on_approval')>Заявка на согласовании</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:justify-end">
                            <button type="submit" class="ui-btn ui-btn--primary w-full min-h-[44px] py-3 sm:min-h-0 sm:w-auto sm:py-2 whitespace-nowrap shrink-0 [touch-action:manipulation]">
                                Применить фильтр
                            </button>
                            @if($search !== '' || $equipmentFilter !== 'all')
                                <a href="{{ route('applications.report.index') }}" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-3 sm:py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50 whitespace-nowrap shrink-0 [touch-action:manipulation]">
                                    Сбросить
                                </a>
                            @endif
                        </div>
                    </form>

                    <form method="post" action="{{ route('applications.report.layout') }}" class="space-y-5 border-b border-stone-200 dark:border-stone-800 pb-8" id="report-layout-form">
                        @csrf
                        <input type="hidden" name="q" value="{{ $search }}">
                        <input type="hidden" name="equipment_filter" value="{{ $equipmentFilter }}">
                        <input type="hidden" name="include_applications_table" id="layout_include_applications_table" value="{{ $includeApplicationsTable ? '1' : '0' }}">

                        <h3 class="text-sm font-semibold text-black dark:text-white">Состав документа</h3>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <label for="sel_report_header_id" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шапка</label>
                                <select name="report_header_id" id="sel_report_header_id"
                                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                                    <option value="">— не использовать —</option>
                                    @foreach($headers as $h)
                                        <option value="{{ $h->id }}" @selected(old('report_header_id', $template->report_header_id) == $h->id)>{{ $h->name }}</option>
                                    @endforeach
                                </select>
                                @if($headers->isEmpty())
                                    <p class="mt-1 text-xs text-black/70 dark:text-white/60">Нет сохранённых шапок — <a href="{{ route('applications.report.headers.create') }}" class="underline font-medium">создать</a>.</p>
                                @endif
                            </div>
                            <div>
                                <label for="sel_report_footer_id" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подвал</label>
                                <select name="report_footer_id" id="sel_report_footer_id"
                                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                                    <option value="">— не использовать —</option>
                                    @foreach($footers as $f)
                                        <option value="{{ $f->id }}" @selected(old('report_footer_id', $template->report_footer_id) == $f->id)>{{ $f->name }}</option>
                                    @endforeach
                                </select>
                                @if($footers->isEmpty())
                                    <p class="mt-1 text-xs text-black/70 dark:text-white/60">Нет сохранённых подвалов — <a href="{{ route('applications.report.footers.create') }}" class="underline font-medium">создать</a>.</p>
                                @endif
                            </div>
                        </div>

                        <div>
                            <label for="sel_director_user_id" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Директор для подписи</label>
                            <select name="director_user_id" id="sel_director_user_id"
                                class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                                <option value="">— не выбран —</option>
                                @foreach($directors as $director)
                                    <option value="{{ $director->id }}" @selected(old('director_user_id') == $director->id)>
                                        {{ trim($director->surname.' '.$director->name.' '.$director->patronymic) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-black/60 dark:text-white/60">
                                ФИО выбранного директора автоматически подставляется в подпись и в текст по шаблону <code>&#123;&#123;director_fio&#125;&#125;</code>.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="sel_main_font_family" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт основного текста (вводный абзац)</label>
                                <select name="main_font_family" id="sel_main_font_family"
                                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                                    @foreach($fontOptions as $val => $label)
                                        <option value="{{ $val }}" @selected(old('main_font_family', $template->main_font_family) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="sel_table_font_family" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт таблицы (позиции заявок)</label>
                                <select name="table_font_family" id="sel_table_font_family"
                                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                                    @foreach($fontOptions as $val => $label)
                                        <option value="{{ $val }}" @selected(old('table_font_family', $template->table_font_family) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Текст перед таблицей</label>
                            @php
                                $mainBlocksRaw = preg_split("/\r\n\r\n|\n\n|\r\r/", (string) old('main_body_text', $template->main_body_text ?? '')) ?: [];
                                $mainBlocks = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $mainBlocksRaw), static fn ($v) => $v !== ''));
                            @endphp
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <button type="button" id="add-main-text-block"
                                    class="inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Добавить текстовое поле
                                </button>
                                <button type="button" id="insert-main-short-line"
                                    class="hidden inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Вставить короткую линию
                                </button>
                                <button type="button" id="insert-main-long-line"
                                    class="hidden inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Вставить длинную линию
                                </button>
                                <button type="button" id="add-main-table-block"
                                    class="inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Добавить таблицу
                                </button>
                            </div>
                            <input type="hidden" name="main_body_text" id="ta_main_body_text_compiled" value="">
                            <div id="main-text-blocks" class="space-y-3">
                                @foreach($mainBlocks as $block)
                                    <div class="text-block-item">
                                        <textarea rows="4"
                                            class="main-text-block w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500"
                                            placeholder="Текст до таблицы с позициями из заявок…">{{ $block }}</textarea>
                                        <div class="mt-1">
                                            <button type="button" class="remove-text-block inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                                Удалить поле
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label for="ta_footer_text" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Текст после таблицы</label>
                            @php
                                $footerBlocksRaw = preg_split("/\r\n\r\n|\n\n|\r\r/", (string) old('footer_text', $template->footer_text ?? '')) ?: [];
                                $footerBlocks = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $footerBlocksRaw), static fn ($v) => $v !== ''));
                            @endphp
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <button type="button" id="add-footer-text-block"
                                    class="inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Добавить текстовое поле
                                </button>
                                <button type="button" id="insert-footer-short-line"
                                    class="hidden inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Вставить короткую линию
                                </button>
                                <button type="button" id="insert-footer-long-line"
                                    class="hidden inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Вставить длинную линию
                                </button>
                                <button type="button" id="add-footer-table-block"
                                    class="inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Добавить таблицу
                                </button>
                                <button type="button" id="remove-applications-table"
                                    class="inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1.5 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                    Удалить таблицу
                                </button>
                            </div>
                            <p id="applications-table-state" class="text-xs {{ $includeApplicationsTable ? 'text-emerald-700 dark:text-emerald-400' : 'text-black/60 dark:text-white/60' }}">
                                {{ $includeApplicationsTable ? 'Таблица с заявками будет добавлена в отчёт.' : 'Таблица с заявками отключена (нажмите «Добавить таблицу»).' }}
                            </p>
                            <input type="hidden" name="footer_text" id="ta_footer_text_compiled" value="">
                            <div id="footer-text-blocks" class="space-y-3">
                                @foreach($footerBlocks as $block)
                                    <div class="text-block-item">
                                        <textarea rows="4"
                                            class="footer-text-block w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500"
                                            placeholder="Дополнительный текст после таблицы…">{{ $block }}</textarea>
                                        <div class="mt-1">
                                            <button type="button" class="remove-text-block inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">
                                                Удалить поле
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <button type="submit" class="ui-btn ui-btn--primary w-full min-h-[44px] py-3 sm:min-h-0 sm:w-auto sm:py-2 [touch-action:manipulation]">
                            Сохранить настройки сборки
                        </button>
                    </form>

                    @if($applications->isEmpty())
                        <p class="text-sm text-black dark:text-white py-4">По текущему фильтру заявок нет — измените поиск или выберите другой фильтр.</p>
                    @else
                        <form method="post" action="{{ route('applications.report.preview') }}" target="_blank" class="space-y-4" id="report-preview-form">
                            @csrf
                            <input type="hidden" name="report_header_id" id="pv_report_header_id" value="">
                            <input type="hidden" name="report_footer_id" id="pv_report_footer_id" value="">
                            <input type="hidden" name="main_font_family" id="pv_main_font_family" value="">
                            <input type="hidden" name="table_font_family" id="pv_table_font_family" value="">
                            <input type="hidden" name="main_body_text" id="pv_main_body_text" value="">
                            <input type="hidden" name="footer_text" id="pv_footer_text" value="">
                            <input type="hidden" name="include_applications_table" id="pv_include_applications_table" value="{{ $includeApplicationsTable ? '1' : '0' }}">
                            <input type="hidden" name="director_user_id" id="pv_director_user_id" value="">

                            <h3 class="text-sm font-semibold text-black dark:text-white">Заявки в отчёт</h3>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p class="text-sm text-black dark:text-white opacity-90">Отметьте заявки. В таблицу попадут все позиции оборудования из выбранных заявок (формат как в акте: № п/п, наименование, ед., кол-во, примечание).</p>
                                <label class="inline-flex items-center gap-2 text-sm text-black dark:text-white cursor-pointer shrink-0">
                                    <input type="checkbox" id="report-select-all" class="rounded border-stone-300 text-stone-600 focus:ring-stone-500">
                                    <span>Отметить все</span>
                                </label>
                            </div>

                            <div class="app-table-shell -mx-1 px-1 sm:mx-0 sm:px-0">
                                <table class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left w-10"><span class="sr-only">Включить</span></th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-black dark:text-white uppercase">№</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-black dark:text-white uppercase">Подразделение</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-black dark:text-white uppercase">Создана</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-black dark:text-white uppercase">Статус</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-black dark:text-white uppercase min-w-[12rem]">Оборудование</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($applications as $application)
                                            <tr class="align-top">
                                                <td class="px-3 py-2">
                                                    <input type="checkbox" name="application_ids[]" value="{{ $application->id }}" class="report-app-check rounded border-stone-300 text-stone-600 focus:ring-stone-500">
                                                </td>
                                                <td class="px-3 py-2 text-sm text-black dark:text-white whitespace-nowrap">{{ $application->id }}</td>
                                                <td class="px-3 py-2 text-sm text-black dark:text-white">{{ $application->subdivision->name }}</td>
                                                <td class="px-3 py-2 text-sm text-black dark:text-white whitespace-nowrap">{{ $application->created_at->format('d.m.Y') }}</td>
                                                <td class="px-3 py-2 text-sm text-black dark:text-white">
                                                    @if($application->items->isEmpty())
                                                        —
                                                    @elseif($application->isStatusApproved())
                                                        Согласована
                                                    @elseif($application->isStatusPartial())
                                                        Частично согласована
                                                    @elseif($application->isStatusRejected())
                                                        Не согласована
                                                    @else
                                                        На согласовании
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm text-black dark:text-white">{{ \Illuminate\Support\Str::limit($application->equipment_summary, 120) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($applications->hasPages())
                                <div>
                                    {{ $applications->links() }}
                                </div>
                            @endif

                            <div class="flex flex-col sm:flex-row gap-2">
                                <button type="submit" class="ui-btn ui-btn--primary w-full min-h-[44px] py-3 sm:min-h-0 sm:w-auto sm:py-2 [touch-action:manipulation]">
                                    Сформировать отчёт (предпросмотр)
                                </button>
                                <button type="submit" formaction="{{ route('applications.report.pdf') }}" formtarget="_blank" class="inline-flex items-center justify-center px-4 py-3 sm:py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-600 bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50 w-full sm:w-auto min-h-[44px] sm:min-h-0 [touch-action:manipulation]">
                                    Экспорт в PDF
                                </button>
                            </div>
                        </form>

                        <script>
                            (function () {
                                const selHeader = document.getElementById('sel_report_header_id');
                                const selFooter = document.getElementById('sel_report_footer_id');
                                const selMainFont = document.getElementById('sel_main_font_family');
                                const selTableFont = document.getElementById('sel_table_font_family');
                                const selDirector = document.getElementById('sel_director_user_id');
                                const mainBlocksContainer = document.getElementById('main-text-blocks');
                                const mainTextCompiled = document.getElementById('ta_main_body_text_compiled');
                                const footerBlocksContainer = document.getElementById('footer-text-blocks');
                                const footerTextCompiled = document.getElementById('ta_footer_text_compiled');
                                const addMainTextBlock = document.getElementById('add-main-text-block');
                                const insertMainShortLine = document.getElementById('insert-main-short-line');
                                const insertMainLongLine = document.getElementById('insert-main-long-line');
                                const addMainTableBlock = document.getElementById('add-main-table-block');
                                const addFooterTextBlock = document.getElementById('add-footer-text-block');
                                const insertFooterShortLine = document.getElementById('insert-footer-short-line');
                                const insertFooterLongLine = document.getElementById('insert-footer-long-line');
                                const addFooterTableBlock = document.getElementById('add-footer-table-block');
                                const removeApplicationsTable = document.getElementById('remove-applications-table');
                                const tableState = document.getElementById('applications-table-state');
                                const pvIncludeTable = document.getElementById('pv_include_applications_table');
                                const layoutIncludeTable = document.getElementById('layout_include_applications_table');
                                const previewForm = document.getElementById('report-preview-form');
                                const pvHeader = document.getElementById('pv_report_header_id');
                                const pvFooter = document.getElementById('pv_report_footer_id');
                                const pvMainFont = document.getElementById('pv_main_font_family');
                                const pvTableFont = document.getElementById('pv_table_font_family');
                                const pvMain = document.getElementById('pv_main_body_text');
                                const pvFooterText = document.getElementById('pv_footer_text');
                                const pvDirector = document.getElementById('pv_director_user_id');
                                const selectAll = document.getElementById('report-select-all');
                                const checks = function () { return Array.from(document.querySelectorAll('.report-app-check')); };
                                const textBlockTemplate = function (kind) {
                                    const cls = kind === 'main' ? 'main-text-block' : 'footer-text-block';
                                    const placeholder = kind === 'main'
                                        ? 'Текст до таблицы с позициями из заявок…'
                                        : 'Дополнительный текст после таблицы…';
                                    return '<div class="text-block-item">'
                                        + '<textarea rows="4" class="' + cls + ' w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500" placeholder="' + placeholder + '"></textarea>'
                                        + '<div class="mt-1"><button type="button" class="remove-text-block inline-flex items-center justify-center rounded-md border border-stone-300 dark:border-stone-600 px-2.5 py-1 text-xs font-medium text-black dark:text-white bg-stone-50/80 dark:bg-stone-900/30 hover:bg-stone-100 dark:hover:bg-stone-900/50">Удалить поле</button></div>'
                                        + '</div>';
                                };
                                let lastFocusedMain = null;
                                let lastFocusedFooter = null;
                                const shortLine = '____________________';
                                const longLine = '_______________________________';
                                let includeApplicationsTable = (pvIncludeTable && pvIncludeTable.value === '1');

                                const syncIncludeApplicationsTable = function () {
                                    const value = includeApplicationsTable ? '1' : '0';
                                    if (pvIncludeTable) {
                                        pvIncludeTable.value = value;
                                    }
                                    if (layoutIncludeTable) {
                                        layoutIncludeTable.value = value;
                                    }
                                    if (tableState) {
                                        if (includeApplicationsTable) {
                                            tableState.textContent = 'Таблица с заявками будет добавлена в отчёт.';
                                            tableState.classList.remove('text-black/60', 'dark:text-white/60');
                                            tableState.classList.add('text-emerald-700', 'dark:text-emerald-400');
                                        } else {
                                            tableState.textContent = 'Таблица с заявками отключена (нажмите «Добавить таблицу»).';
                                            tableState.classList.remove('text-emerald-700', 'dark:text-emerald-400');
                                            tableState.classList.add('text-black/60', 'dark:text-white/60');
                                        }
                                    }
                                    if (removeApplicationsTable) {
                                        removeApplicationsTable.classList.toggle('hidden', !includeApplicationsTable);
                                    }
                                };
                                const readMainBlocks = function () {
                                    if (!mainBlocksContainer) {
                                        return '';
                                    }
                                    return Array.from(mainBlocksContainer.querySelectorAll('.main-text-block'))
                                        .map(function (el) { return (el.value || '').trim(); })
                                        .filter(function (v) { return v !== ''; })
                                        .join('\n\n');
                                };
                                const readFooterBlocks = function () {
                                    if (!footerBlocksContainer) {
                                        return '';
                                    }
                                    return Array.from(footerBlocksContainer.querySelectorAll('.footer-text-block'))
                                        .map(function (el) { return (el.value || '').trim(); })
                                        .filter(function (v) { return v !== ''; })
                                        .join('\n\n');
                                };

                                const syncMainText = function () {
                                    if (mainTextCompiled) {
                                        mainTextCompiled.value = readMainBlocks();
                                    }
                                    const hasMainBlocks = !!(mainBlocksContainer && mainBlocksContainer.querySelector('.main-text-block'));
                                    if (insertMainShortLine) {
                                        insertMainShortLine.classList.toggle('hidden', !hasMainBlocks);
                                    }
                                    if (insertMainLongLine) {
                                        insertMainLongLine.classList.toggle('hidden', !hasMainBlocks);
                                    }
                                };

                                const syncFooterText = function () {
                                    if (footerTextCompiled) {
                                        footerTextCompiled.value = readFooterBlocks();
                                    }
                                    const hasFooterBlocks = !!(footerBlocksContainer && footerBlocksContainer.querySelector('.footer-text-block'));
                                    if (insertFooterShortLine) {
                                        insertFooterShortLine.classList.toggle('hidden', !hasFooterBlocks);
                                    }
                                    if (insertFooterLongLine) {
                                        insertFooterLongLine.classList.toggle('hidden', !hasFooterBlocks);
                                    }
                                };

                                if (mainBlocksContainer) {
                                    mainBlocksContainer.addEventListener('input', syncMainText);
                                    mainBlocksContainer.addEventListener('focusin', function (e) {
                                        const ta = e.target.closest('.main-text-block');
                                        if (ta) {
                                            lastFocusedMain = ta;
                                        }
                                    });
                                    mainBlocksContainer.addEventListener('click', function (e) {
                                        const btn = e.target.closest('.remove-text-block');
                                        if (!btn) {
                                            return;
                                        }
                                        const block = btn.closest('.text-block-item');
                                        if (block) {
                                            block.remove();
                                            syncMainText();
                                        }
                                    });
                                }

                                if (footerBlocksContainer) {
                                    footerBlocksContainer.addEventListener('input', syncFooterText);
                                    footerBlocksContainer.addEventListener('focusin', function (e) {
                                        const ta = e.target.closest('.footer-text-block');
                                        if (ta) {
                                            lastFocusedFooter = ta;
                                        }
                                    });
                                    footerBlocksContainer.addEventListener('click', function (e) {
                                        const btn = e.target.closest('.remove-text-block');
                                        if (!btn) {
                                            return;
                                        }
                                        const block = btn.closest('.text-block-item');
                                        if (block) {
                                            block.remove();
                                            syncFooterText();
                                        }
                                    });
                                }

                                if (addMainTextBlock) {
                                    addMainTextBlock.addEventListener('click', function () {
                                        if (!mainBlocksContainer) {
                                            return;
                                        }
                                        mainBlocksContainer.insertAdjacentHTML('beforeend', textBlockTemplate('main'));
                                        syncMainText();
                                    });
                                }
                                function insertAtCursor(input, text) {
                                    if (!input) {
                                        return;
                                    }
                                    const start = input.selectionStart || 0;
                                    const end = input.selectionEnd || 0;
                                    const value = input.value || '';
                                    input.value = value.slice(0, start) + text + value.slice(end);
                                    const pos = start + text.length;
                                    input.selectionStart = pos;
                                    input.selectionEnd = pos;
                                    input.focus();
                                }

                                function ensureMainTarget() {
                                    if (lastFocusedMain && document.body.contains(lastFocusedMain)) {
                                        return lastFocusedMain;
                                    }
                                    if (mainBlocksContainer) {
                                        const first = mainBlocksContainer.querySelector('.main-text-block');
                                        if (first) {
                                            return first;
                                        }
                                        mainBlocksContainer.insertAdjacentHTML('beforeend', textBlockTemplate('main'));
                                        const all = mainBlocksContainer.querySelectorAll('.main-text-block');
                                        const created = all.length ? all[all.length - 1] : null;
                                        return created;
                                    }
                                    return null;
                                }

                                function ensureFooterTarget() {
                                    if (lastFocusedFooter && document.body.contains(lastFocusedFooter)) {
                                        return lastFocusedFooter;
                                    }
                                    if (footerBlocksContainer) {
                                        const first = footerBlocksContainer.querySelector('.footer-text-block');
                                        if (first) {
                                            return first;
                                        }
                                        footerBlocksContainer.insertAdjacentHTML('beforeend', textBlockTemplate('footer'));
                                        const all = footerBlocksContainer.querySelectorAll('.footer-text-block');
                                        const created = all.length ? all[all.length - 1] : null;
                                        return created;
                                    }
                                    return null;
                                }

                                if (insertMainShortLine) {
                                    insertMainShortLine.addEventListener('click', function () {
                                        const target = ensureMainTarget();
                                        insertAtCursor(target, shortLine);
                                        syncMainText();
                                    });
                                }
                                if (insertMainLongLine) {
                                    insertMainLongLine.addEventListener('click', function () {
                                        const target = ensureMainTarget();
                                        insertAtCursor(target, longLine);
                                        syncMainText();
                                    });
                                }

                                if (addMainTableBlock) {
                                    addMainTableBlock.addEventListener('click', function () {
                                        includeApplicationsTable = true;
                                        syncIncludeApplicationsTable();
                                    });
                                }

                                if (addFooterTextBlock) {
                                    addFooterTextBlock.addEventListener('click', function () {
                                        if (!footerBlocksContainer) {
                                            return;
                                        }
                                        footerBlocksContainer.insertAdjacentHTML('beforeend', textBlockTemplate('footer'));
                                        syncFooterText();
                                    });
                                }
                                if (insertFooterShortLine) {
                                    insertFooterShortLine.addEventListener('click', function () {
                                        const target = ensureFooterTarget();
                                        insertAtCursor(target, shortLine);
                                        syncFooterText();
                                    });
                                }
                                if (insertFooterLongLine) {
                                    insertFooterLongLine.addEventListener('click', function () {
                                        const target = ensureFooterTarget();
                                        insertAtCursor(target, longLine);
                                        syncFooterText();
                                    });
                                }

                                if (addFooterTableBlock) {
                                    addFooterTableBlock.addEventListener('click', function () {
                                        includeApplicationsTable = true;
                                        syncIncludeApplicationsTable();
                                    });
                                }

                                if (removeApplicationsTable) {
                                    removeApplicationsTable.addEventListener('click', function () {
                                        includeApplicationsTable = false;
                                        syncIncludeApplicationsTable();
                                    });
                                }

                                previewForm.addEventListener('submit', function (e) {
                                    if (includeApplicationsTable && !checks().some(function (c) { return c.checked; })) {
                                        e.preventDefault();
                                        alert('Отметьте хотя бы одну заявку.');
                                        return;
                                    }
                                    pvHeader.value = selHeader.value || '';
                                    pvFooter.value = selFooter.value || '';
                                    pvMainFont.value = selMainFont.value || '';
                                    pvTableFont.value = selTableFont.value || '';
                                    pvMain.value = readMainBlocks();
                                    pvFooterText.value = readFooterBlocks();
                                    if (pvDirector) {
                                        pvDirector.value = selDirector ? (selDirector.value || '') : '';
                                    }
                                });

                                if (selectAll) {
                                    selectAll.addEventListener('change', function () {
                                        checks().forEach(function (c) { c.checked = selectAll.checked; });
                                    });
                                }

                                var layoutForm = document.getElementById('report-layout-form');
                                if (layoutForm) {
                                    layoutForm.addEventListener('submit', function () {
                                        syncMainText();
                                        syncFooterText();
                                        syncIncludeApplicationsTable();
                                    });
                                }

                                syncMainText();
                                syncFooterText();
                                syncIncludeApplicationsTable();
                            })();
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
