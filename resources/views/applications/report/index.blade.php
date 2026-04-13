<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Сборка отчёта по заявкам
            </h2>
            <a href="{{ route('applications.index') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-orange-300 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/30 hover:bg-orange-100 dark:hover:bg-orange-900/50 shrink-0 whitespace-nowrap w-full sm:w-auto">
                К списку заявок
            </a>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 rounded-md bg-orange-100 dark:bg-orange-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex flex-col sm:flex-row flex-wrap gap-2">
                <a href="{{ route('applications.report.headers.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-orange-600 hover:bg-orange-700 shadow-sm">Шаблоны шапок</a>
                <a href="{{ route('applications.report.footers.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-orange-600 hover:bg-orange-700 shadow-sm">Шаблоны подвалов</a>
            </div>

            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-4 sm:p-6 space-y-8">
                    <p class="text-sm text-black dark:text-white opacity-90">
                        Сначала создайте и сохраните нужные <strong class="font-semibold">шапки</strong> и <strong class="font-semibold">подвалы</strong> (как в типовом акте: организация, «Утверждаю», заголовок, дата и город, блоки подписей).
                        Здесь выберите шаблоны, задайте шрифты для вводного текста и таблицы, напишите основной текст, отметьте заявки и откройте предпросмотр для печати или сохранения в PDF.
                    </p>

                    <form method="get" action="{{ route('applications.report.index') }}" class="flex flex-col gap-4 border-b border-orange-200 dark:border-orange-800 pb-6">
                        <h3 class="text-sm font-semibold text-black dark:text-white">Фильтр списка заявок</h3>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(14rem,20rem)] lg:items-end">
                            <div class="min-w-0">
                                <label for="report-q" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Поиск</label>
                                <input type="search" name="q" id="report-q" value="{{ $search }}"
                                    placeholder="Как в списке заявок…"
                                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                            </div>
                            <div class="min-w-0">
                                <label for="report-equipment-filter" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Статус согласования</label>
                                <select name="equipment_filter" id="report-equipment-filter"
                                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <option value="all" @selected($equipmentFilter === 'all')>Все заявки</option>
                                    <option value="has_approved" @selected($equipmentFilter === 'has_approved')>Есть согласованные позиции</option>
                                    <option value="has_not_approved" @selected($equipmentFilter === 'has_not_approved')>Есть несогласованные позиции</option>
                                    <option value="fully_approved" @selected($equipmentFilter === 'fully_approved')>Все позиции согласованы</option>
                                    <option value="on_approval" @selected($equipmentFilter === 'on_approval')>Заявка на согласовании</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:justify-end">
                            <button type="submit" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-3 sm:py-2.5 text-sm font-semibold text-white rounded-lg bg-orange-600 shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 whitespace-nowrap shrink-0 [touch-action:manipulation]">
                                Применить фильтр
                            </button>
                            @if($search !== '' || $equipmentFilter !== 'all')
                                <a href="{{ route('applications.report.index') }}" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-3 sm:py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-orange-300 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/30 hover:bg-orange-100 dark:hover:bg-orange-900/50 whitespace-nowrap shrink-0 [touch-action:manipulation]">
                                    Сбросить
                                </a>
                            @endif
                        </div>
                    </form>

                    <form method="post" action="{{ route('applications.report.layout') }}" class="space-y-5 border-b border-orange-200 dark:border-orange-800 pb-8" id="report-layout-form">
                        @csrf
                        <input type="hidden" name="q" value="{{ $search }}">
                        <input type="hidden" name="equipment_filter" value="{{ $equipmentFilter }}">

                        <h3 class="text-sm font-semibold text-black dark:text-white">Состав документа</h3>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <label for="sel_report_header_id" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шапка</label>
                                <select name="report_header_id" id="sel_report_header_id"
                                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
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
                                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
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

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="sel_main_font_family" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт основного текста (вводный абзац)</label>
                                <select name="main_font_family" id="sel_main_font_family"
                                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    @foreach($fontOptions as $val => $label)
                                        <option value="{{ $val }}" @selected(old('main_font_family', $template->main_font_family) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="sel_table_font_family" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт таблицы (позиции заявок)</label>
                                <select name="table_font_family" id="sel_table_font_family"
                                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    @foreach($fontOptions as $val => $label)
                                        <option value="{{ $val }}" @selected(old('table_font_family', $template->table_font_family) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="ta_main_body_text" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Основной текст (перед таблицей; состав комиссии, цель акта и т.д.)</label>
                            <textarea name="main_body_text" id="ta_main_body_text" rows="8"
                                class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                placeholder="Текст до таблицы с позициями из заявок…">{{ old('main_body_text', $template->main_body_text) }}</textarea>
                        </div>

                        <button type="submit" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-3 sm:py-2.5 text-sm font-semibold text-white rounded-lg bg-orange-600 shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 [touch-action:manipulation]">
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

                            <h3 class="text-sm font-semibold text-black dark:text-white">Заявки в отчёт</h3>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p class="text-sm text-black dark:text-white opacity-90">Отметьте заявки. В таблицу попадут все позиции оборудования из выбранных заявок (формат как в акте: № п/п, наименование, ед., кол-во, примечание).</p>
                                <label class="inline-flex items-center gap-2 text-sm text-black dark:text-white cursor-pointer shrink-0">
                                    <input type="checkbox" id="report-select-all" class="rounded border-orange-300 text-orange-600 focus:ring-orange-500">
                                    <span>Отметить все</span>
                                </label>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-orange-200 dark:border-orange-800 -mx-1 px-1 sm:mx-0 sm:px-0">
                                <table class="min-w-full divide-y divide-orange-200 dark:divide-orange-800">
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
                                    <tbody class="divide-y divide-orange-200 dark:divide-orange-800">
                                        @foreach($applications as $application)
                                            <tr class="align-top">
                                                <td class="px-3 py-2">
                                                    <input type="checkbox" name="application_ids[]" value="{{ $application->id }}" class="report-app-check rounded border-orange-300 text-orange-600 focus:ring-orange-500">
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

                            <button type="submit" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-3 sm:py-2.5 text-sm font-semibold text-white rounded-lg bg-orange-600 shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 [touch-action:manipulation]">
                                Сформировать отчёт (предпросмотр)
                            </button>
                        </form>

                        <script>
                            (function () {
                                const selHeader = document.getElementById('sel_report_header_id');
                                const selFooter = document.getElementById('sel_report_footer_id');
                                const selMainFont = document.getElementById('sel_main_font_family');
                                const selTableFont = document.getElementById('sel_table_font_family');
                                const taMain = document.getElementById('ta_main_body_text');
                                const previewForm = document.getElementById('report-preview-form');
                                const pvHeader = document.getElementById('pv_report_header_id');
                                const pvFooter = document.getElementById('pv_report_footer_id');
                                const pvMainFont = document.getElementById('pv_main_font_family');
                                const pvTableFont = document.getElementById('pv_table_font_family');
                                const pvMain = document.getElementById('pv_main_body_text');
                                const selectAll = document.getElementById('report-select-all');
                                const checks = function () { return Array.from(document.querySelectorAll('.report-app-check')); };

                                previewForm.addEventListener('submit', function (e) {
                                    if (!checks().some(function (c) { return c.checked; })) {
                                        e.preventDefault();
                                        alert('Отметьте хотя бы одну заявку.');
                                        return;
                                    }
                                    pvHeader.value = selHeader.value || '';
                                    pvFooter.value = selFooter.value || '';
                                    pvMainFont.value = selMainFont.value || '';
                                    pvTableFont.value = selTableFont.value || '';
                                    pvMain.value = taMain.value || '';
                                });

                                if (selectAll) {
                                    selectAll.addEventListener('change', function () {
                                        checks().forEach(function (c) { c.checked = selectAll.checked; });
                                    });
                                }
                            })();
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
