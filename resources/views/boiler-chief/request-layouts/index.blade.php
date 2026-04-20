@php
    $layoutOptions = $layouts->map(fn ($l) => ['id' => $l->id, 'title' => $l->title])->values();
    $firstLayoutId = $layouts->first()?->id;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 w-full min-w-0">
            <h2 class="font-semibold text-xl text-stone-900 dark:text-white leading-tight min-w-0 break-words">
                Макеты заявок (PDF)
            </h2>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto sm:justify-end shrink-0">
                <a href="{{ route('boiler-chief.document-header-layouts.index') }}"
                   class="ui-btn ui-btn--secondary min-h-[2.75rem] sm:min-h-0 whitespace-nowrap">
                    Макеты шапок
                </a>
                <a href="{{ route('boiler-chief.request-layouts.create') }}"
                   class="ui-btn ui-btn--primary min-h-[2.75rem] sm:min-h-0 whitespace-nowrap">
                    Новый макет
                </a>
            </div>
        </div>
    </x-slot>

    <style>[x-cloak]{display:none!important}</style>

    <div class="py-6 sm:py-10 min-h-[60vh]"
         x-data="{
            reportOpen: false,
            layouts: {{ \Illuminate\Support\Js::from($layoutOptions) }},
            layoutId: {{ \Illuminate\Support\Js::from($firstLayoutId) }},
            fields: [],
            loading: false,
            baseUrl: @js(url('/boiler-chief/request-layouts')),
            token: document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '',
            async loadFields() {
                if (!this.layoutId) { this.fields = []; return; }
                this.loading = true;
                try {
                    const r = await fetch(this.baseUrl + '/' + this.layoutId + '/schema-json', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const d = await r.json();
                    this.fields = Array.isArray(d.fields) ? d.fields : [];
                } catch (e) {
                    this.fields = [];
                }
                this.loading = false;
            },
            async submitReport(ev) {
                ev.preventDefault();
                if (!this.layoutId) return;
                const form = this.$refs.reportForm;
                const fd = new FormData(form);
                const res = await fetch(this.baseUrl + '/' + this.layoutId + '/filled-pdf', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json, application/pdf',
                        'X-CSRF-TOKEN': this.token,
                    },
                    body: fd,
                    credentials: 'same-origin',
                });
                const ct = (res.headers.get('content-type') || '').toLowerCase();
                if (res.status === 422) {
                    window.alert('Проверьте заполнение полей.');
                    return;
                }
                if (res.ok && ct.includes('application/pdf')) {
                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    window.location.assign(url);
                    this.reportOpen = false;
                    return;
                }
                window.alert('Не удалось сформировать PDF. Обновите страницу или войдите снова.');
            },
         }"
         @open-new-report.window="reportOpen = true; $nextTick(() => loadFields())">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-xl border border-orange-200/80 bg-orange-50 dark:bg-orange-950/35 dark:border-orange-900/50 text-orange-950 dark:text-orange-100 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35">
                <div class="p-4 sm:p-6">
                    @if($layouts->isEmpty())
                        <p class="py-12 text-center text-sm text-stone-500 dark:text-stone-400 max-w-lg mx-auto leading-relaxed">
                            Макетов пока нет. Создайте первый или выполните сидер демо-данных.
                        </p>
                    @else
                        <div class="md:hidden space-y-4">
                            @foreach($layouts as $layout)
                                <article class="rounded-xl border border-orange-100/90 dark:border-orange-900/35 bg-orange-50/20 dark:bg-orange-950/10 p-4 space-y-3 shadow-sm">
                                    <div>
                                        <p class="text-sm font-medium text-stone-900 dark:text-white break-words">{{ $layout->title }}</p>
                                        <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">
                                            Шапка: {{ ($layout->has_header || $layout->document_header_layout_id) ? 'да' : 'нет' }}
                                            · Макет шапки: {{ $layout->documentHeaderLayout?->title ?? '—' }}
                                        </p>
                                        <p class="text-xs text-stone-500 dark:text-stone-400">{{ $layout->updated_at?->format('d.m.Y H:i') }}</p>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <a href="{{ route('boiler-chief.request-layouts.edit', $layout) }}" class="ui-btn ui-btn--secondary justify-center">Изменить</a>
                                        <form method="POST" action="{{ route('boiler-chief.request-layouts.destroy', $layout) }}" onsubmit="return confirm('Удалить макет?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-btn ui-btn--danger w-full justify-center">Удалить</button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-orange-200/80 bg-orange-50/90 dark:border-orange-800/50 dark:bg-orange-950/35">
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Название</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Шапка</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Макет шапки</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-orange-950 dark:text-orange-100/90">Действия</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-orange-100/90 dark:divide-orange-900/35">
                                    @foreach($layouts as $layout)
                                        <tr class="bg-white hover:bg-orange-50/40 dark:bg-stone-950 dark:hover:bg-orange-950/25">
                                            <td class="px-4 py-3 font-medium text-stone-900 dark:text-stone-100">{{ $layout->title }}</td>
                                            <td class="px-4 py-3 text-stone-600 dark:text-stone-300">
                                                {{ ($layout->has_header || $layout->document_header_layout_id) ? 'Да' : 'Нет' }}
                                            </td>
                                            <td class="px-4 py-3 text-stone-600 dark:text-stone-300">
                                                {{ $layout->documentHeaderLayout?->title ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                                <a href="{{ route('boiler-chief.request-layouts.edit', $layout) }}" class="ui-btn ui-btn--secondary ui-btn--sm">Изменить</a>
                                                <form class="inline-block" method="POST" action="{{ route('boiler-chief.request-layouts.destroy', $layout) }}" onsubmit="return confirm('Удалить макет?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="ui-btn ui-btn--danger ui-btn--sm">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto bg-black/50 px-3 py-10"
             x-show="reportOpen"
             x-cloak
             x-transition.opacity
             @keydown.escape.window="reportOpen = false">
            <div class="absolute inset-0" @click="reportOpen = false"></div>
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-2xl ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:ring-orange-950/35"
                 @click.stop>
                <div class="flex items-center justify-between border-b border-orange-100/90 px-5 py-4 dark:border-orange-900/40">
                    <h3 class="text-base font-semibold text-stone-900 dark:text-white">Новая заявка</h3>
                    <button type="button" class="text-stone-400 hover:text-stone-700 dark:hover:text-stone-200" @click="reportOpen = false" title="Закрыть">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form x-ref="reportForm" class="space-y-4 px-5 pb-6" @submit="submitReport($event)">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-1">Макет заявки</label>
                        <select x-model="layoutId" @change="loadFields()"
                                class="app-select">
                            <template x-for="l in layouts" :key="l.id">
                                <option :value="l.id" x-text="l.title"></option>
                            </template>
                        </select>
                    </div>
                    <p x-show="loading" class="text-sm text-stone-500">Загрузка полей…</p>
                    <template x-for="field in fields" :key="field.key">
                        <div>
                            <label class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-0.5">
                                <span x-text="field.label || 'Поле'"></span>
                            </label>
                            <template x-if="field.type === 'textarea'">
                                <textarea :name="'values[' + field.key + ']'" rows="4" maxlength="20000"
                                          class="app-input min-h-0"></textarea>
                            </template>
                            <template x-if="field.type === 'number'">
                                <input :name="'values[' + field.key + ']'" type="number" step="any"
                                       class="app-input min-h-0"/>
                            </template>
                            <template x-if="field.type !== 'textarea' && field.type !== 'number'">
                                <input :name="'values[' + field.key + ']'" type="text" maxlength="20000"
                                       class="app-input min-h-0"/>
                            </template>
                        </div>
                    </template>
                    <div class="space-y-2 rounded-xl border border-orange-100/90 bg-orange-50/30 px-4 py-3 dark:border-orange-900/35 dark:bg-orange-950/20">
                        <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Дата формирования</p>
                        <input type="hidden" name="use_current_date" value="0"/>
                        <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="use_current_date" value="1" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40" checked/>
                            <span>Использовать текущую дату</span>
                        </label>
                        <div>
                            <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Или укажите дату</label>
                            <input type="date" name="form_document_date" class="app-input min-h-0"/>
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <button type="button" class="ui-btn ui-btn--secondary w-full sm:w-auto justify-center" @click="reportOpen = false">Отмена</button>
                        <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto justify-center" :disabled="!layoutId || loading">Скачать PDF</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
