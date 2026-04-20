<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="$backRoute ?? route('boiler-chief.request-layouts.index')">{{ $backLabel ?? 'К списку макетов заявок' }}</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white">Новая заявка</h2>
        </div>
    </x-slot>

    <div class="py-4 sm:py-8 flex justify-center px-3">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-xl shadow-orange-950/[0.08] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:ring-orange-950/35">
            <div class="flex items-center justify-between border-b border-orange-100/90 px-5 py-4 dark:border-orange-900/40">
                <h3 class="text-base font-semibold text-stone-900 dark:text-white">Новая заявка</h3>
                <a href="{{ $closeRoute ?? route('boiler-chief.request-layouts.index') }}" class="text-stone-400 hover:text-stone-600 dark:hover:text-stone-200" title="Закрыть">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
            </div>
            @if(($allowEditLayout ?? true) === true)
                <div class="px-5 py-2">
                    <a href="{{ route('boiler-chief.request-layouts.edit', $layout) }}" class="text-sm font-medium text-orange-800 hover:underline dark:text-orange-200/90">Изменить макет</a>
                </div>
            @endif

            <form method="POST"
                  action="{{ $formAction ?? route('boiler-chief.request-layouts.filled-pdf', $layout) }}"
                  class="px-5 pb-6 space-y-5"
                  id="fill-report-form"
                  data-dadata-suggest-url="{{ route('api.dadata.address.suggest', [], false) }}"
                  data-dadata-clean-url="{{ route('api.dadata.address.clean', [], false) }}">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-1">Макет заявки</label>
                    <select class="app-select opacity-90" disabled>
                        <option selected>{{ $layout->title }}</option>
                    </select>
                </div>

                @foreach($layout->schema['fields'] ?? [] as $field)
                    @php
                        $key = (string) ($field['key'] ?? '');
                        $label = (string) ($field['label'] ?? $key);
                        $type = (string) ($field['type'] ?? 'text');
                        $fieldId = $key !== '' ? 'f_'.md5($key) : '';
                    @endphp
                    @if($key !== '')
                        <div>
                            <label class="block text-sm font-medium text-stone-900 dark:text-stone-100 mb-0.5" for="{{ $fieldId }}">
                                {{ $label }}
                                <span class="block text-stone-400 font-normal text-xs mt-0.5">В PDF это значение подставится вместо поля с ключом «{{ $key }}».</span>
                            </label>
                            @if($type === 'textarea')
                                <textarea id="{{ $fieldId }}" name="values[{{ $key }}]" rows="4" maxlength="20000"
                                          class="app-input min-h-0"></textarea>
                            @elseif($type === 'number')
                                <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="number" step="any"
                                       class="app-input min-h-0"/>
                            @elseif($type === 'date')
                                <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="date"
                                       class="app-input min-h-0"/>
                            @elseif($type === 'address')
                                <div class="relative" data-dadata-address-field>
                                    <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="text" maxlength="255"
                                           autocomplete="off"
                                           class="app-input min-h-0"
                                           data-dadata-address-input
                                           data-field-key="{{ $key }}"/>
                                    <input type="hidden" name="values_meta[{{ $key }}]" value="" data-dadata-meta-input/>
                                    <div class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-y-auto rounded-lg border border-stone-200 bg-white shadow-lg dark:border-stone-700 dark:bg-stone-900"
                                         data-dadata-suggestions></div>
                                </div>
                            @else
                                <input id="{{ $fieldId }}" name="values[{{ $key }}]" type="text" maxlength="20000"
                                       class="app-input min-h-0"/>
                            @endif
                            @error('values.'.$key)
                                <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                @endforeach

                <div class="space-y-2 rounded-xl border border-orange-100/90 bg-orange-50/30 px-4 py-3 dark:border-orange-900/35 dark:bg-orange-950/20">
                    <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Дата формирования</p>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="hidden" name="use_current_date" value="0"/>
                        <input type="checkbox" name="use_current_date" value="1" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40" checked/>
                        <span>Использовать текущую дату</span>
                    </label>
                    <div class="pt-1">
                        <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Или укажите дату</label>
                        <input type="date" name="form_document_date" class="app-input min-h-0"/>
                    </div>
                </div>

                <div class="space-y-2 rounded-xl border border-orange-100/90 bg-orange-50/30 px-4 py-3 dark:border-orange-900/35 dark:bg-orange-950/20">
                    <p class="text-sm font-medium text-stone-900 dark:text-stone-100">Номер документа</p>
                    <label for="form_document_number" class="block text-xs text-stone-500 dark:text-stone-400">
                        Заполните, если в макете используются подстановки <code class="text-[11px]">{{ '{' }}{{ '{' }}document_number{{ '}' }}{{ '}' }}</code> или <code class="text-[11px]">{{ '{' }}{{ '{' }}report_number{{ '}' }}{{ '}' }}</code>.
                    </label>
                    <input id="form_document_number" type="text" name="form_document_number" maxlength="120" class="app-input min-h-0"/>
                </div>

                @error('values')
                    <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end pt-2">
                    <a href="{{ $cancelRoute ?? route('boiler-chief.request-layouts.index') }}" class="ui-btn ui-btn--secondary w-full sm:w-auto text-center">Отмена</a>
                    <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto">Скачать PDF</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('fill-report-form');
            if (!form) return;
            const suggestUrl = form.dataset.dadataSuggestUrl || '';
            const cleanUrl = form.dataset.dadataCleanUrl || '';
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
            const debounceTimers = new WeakMap();

            const addressFields = Array.from(form.querySelectorAll('[data-dadata-address-field]'));
            let openSuggestions = null;

            const closeSuggestions = () => {
                if (!openSuggestions) return;
                openSuggestions.innerHTML = '';
                openSuggestions.classList.add('hidden');
                openSuggestions = null;
            };

            const renderSuggestions = (container, input, metaInput, suggestions) => {
                container.innerHTML = '';
                if (!Array.isArray(suggestions) || suggestions.length === 0) {
                    closeSuggestions();
                    return;
                }

                suggestions.forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'block w-full px-3 py-2 text-left text-sm text-stone-800 hover:bg-orange-50 dark:text-stone-100 dark:hover:bg-stone-800';
                    button.textContent = item?.value || '';
                    button.addEventListener('click', () => {
                        input.value = item?.value || input.value;
                        metaInput.value = JSON.stringify(item?.data || {});
                        closeSuggestions();
                    });
                    container.appendChild(button);
                });

                container.classList.remove('hidden');
                openSuggestions = container;
            };

            const fetchSuggestions = async (query, input, metaInput, container) => {
                if (!suggestUrl || query.length < 3) {
                    closeSuggestions();
                    return;
                }
                const url = new URL(suggestUrl, window.location.origin);
                url.searchParams.set('query', query);
                url.searchParams.set('count', '7');

                try {
                    const res = await fetch(url.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        closeSuggestions();
                        return;
                    }
                    const json = await res.json();
                    renderSuggestions(container, input, metaInput, json?.suggestions || []);
                } catch (_) {
                    closeSuggestions();
                }
            };

            const cleanAddress = async (input, metaInput) => {
                const value = String(input.value || '').trim();
                if (!value || !cleanUrl) {
                    return;
                }

                try {
                    const res = await fetch(cleanUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ address: value }),
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        return;
                    }
                    const json = await res.json();
                    const normalized = json?.result || null;
                    if (!normalized || typeof normalized !== 'object') {
                        return;
                    }
                    if (typeof normalized.result === 'string' && normalized.result.trim() !== '') {
                        input.value = normalized.result;
                    }
                    metaInput.value = JSON.stringify(normalized);
                } catch (_) {
                    // ignore transient network issues
                }
            };

            addressFields.forEach((fieldBlock) => {
                const input = fieldBlock.querySelector('[data-dadata-address-input]');
                const metaInput = fieldBlock.querySelector('[data-dadata-meta-input]');
                const suggestions = fieldBlock.querySelector('[data-dadata-suggestions]');
                if (!input || !metaInput || !suggestions) return;

                input.addEventListener('input', () => {
                    closeSuggestions();
                    const existingTimer = debounceTimers.get(input);
                    if (existingTimer) {
                        clearTimeout(existingTimer);
                    }
                    const timerId = window.setTimeout(() => {
                        fetchSuggestions(String(input.value || '').trim(), input, metaInput, suggestions);
                    }, 260);
                    debounceTimers.set(input, timerId);
                });

                input.addEventListener('blur', () => {
                    setTimeout(() => {
                        if (!suggestions.matches(':hover')) {
                            closeSuggestions();
                        }
                    }, 120);
                    cleanAddress(input, metaInput);
                });

                input.addEventListener('focus', () => {
                    const value = String(input.value || '').trim();
                    if (value.length >= 3) {
                        fetchSuggestions(value, input, metaInput, suggestions);
                    }
                });
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-dadata-address-field]')) {
                    closeSuggestions();
                }
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                for (const fieldBlock of addressFields) {
                    const input = fieldBlock.querySelector('[data-dadata-address-input]');
                    const metaInput = fieldBlock.querySelector('[data-dadata-meta-input]');
                    if (input && metaInput) {
                        await cleanAddress(input, metaInput);
                    }
                }
                const fd = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json, application/pdf',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: fd,
                    credentials: 'same-origin',
                });
                const ct = (res.headers.get('content-type') || '').toLowerCase();
                if (res.status === 422) {
                    window.alert('Проверьте заполнение полей.');
                    window.location.reload();
                    return;
                }
                if (res.ok && ct.includes('application/pdf')) {
                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    window.location.assign(url);
                    return;
                }
                window.alert('Не удалось сформировать PDF. Обновите страницу или войдите снова.');
            });
        })();
    </script>
</x-app-layout>
