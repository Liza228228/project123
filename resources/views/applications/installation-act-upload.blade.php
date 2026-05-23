<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.index')">Заявки</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Акт установки
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-8 sm:space-y-10">
              

                @if(Auth::user()->hasAnyRoleId([1, 2, 3, 4, 6, 7]))
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('boiler-chief.layout-applications.index') }}" class="ui-btn ui-btn--secondary ui-btn--sm">
                            Заполнить макет отчета
                        </a>
                    </div>
                @endif

                <form method="POST" action="{{ route('applications.installation-act.upload.store') }}" enctype="multipart/form-data" class="space-y-8 sm:space-y-10">
                    @csrf

                    <section class="space-y-4" aria-labelledby="act-section-app">
                        <h3 id="act-section-app" class="app-section-title">Заявка и файлы</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="application_id" class="app-form-label">Заявка</label>
                                <input
                                    id="application_search"
                                    type="text"
                                    class="app-input mb-2 min-h-0"
                                    placeholder="Поиск по номеру, подразделению или дате"
                                    autocomplete="off"
                                />
                                <select
                                    id="application_id"
                                    name="application_id"
                                    required
                                    class="app-select"
                                    onchange="if (this.value) { window.location = '{{ route('applications.installation-act.upload') }}?application_id=' + this.value; } else { window.location = '{{ route('applications.installation-act.upload') }}'; }"
                                >
                                    <option value="">— Выберите заявку —</option>
                                    @foreach($applications as $app)
                                        <option value="{{ $app->id }}" @selected((int) old('application_id', $preselectedApplicationId) === (int) $app->id)>
                                            №{{ $app->id }}
                                            @if($app->subdivision)
                                                · {{ $app->subdivision->name }}
                                            @endif
                                            · {{ $app->desired_delivery_date?->format('d.m.Y') ?? '—' }}
                                            @if($app->archived_at)
                                                · архив
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('application_id')" class="mt-1.5" />
                                @if($applications->isEmpty())
                                          @endif
                            </div>

                            @if($selectedApplication)
                                <div class="rounded-xl border border-orange-200/85 bg-orange-50/70 p-4 dark:border-orange-900/45 dark:bg-orange-950/20">
                                    <h4 class="text-sm font-semibold text-stone-900 dark:text-stone-100">
                                        Оборудование по заявке №{{ $selectedApplication->id }} и списание со склада получателя
                                    </h4>
                                    <p class="mt-1 text-xs text-stone-600 dark:text-stone-300">
                                        Отметьте позиции и укажите, сколько списать со склада получателя (можно меньше заказанного — остаток останется на складе). Списание выполняется вместе с сохранением акта.
                                    </p>
                                    @if($deliveredWarehouseIssueCandidates->isNotEmpty())
                                        <div class="mt-3 flex md:hidden items-center gap-2 rounded-lg border border-orange-200/80 bg-white/80 px-3 py-2 dark:border-orange-800/50 dark:bg-stone-900/50">
                                            <input
                                                type="checkbox"
                                                id="installation-act-issue-select-all-mobile"
                                                class="js-installation-act-issue-select-all h-5 w-5 shrink-0 rounded border-orange-300 text-orange-600 focus:ring-orange-500"
                                                aria-describedby="installation-act-issue-select-all-hint"
                                            >
                                            <label for="installation-act-issue-select-all-mobile" class="text-xs font-medium text-stone-800 dark:text-stone-200 cursor-pointer select-none">
                                                Выбрать всё доступное к списанию
                                            </label>
                                        </div>
                                        <p id="installation-act-issue-select-all-hint" class="sr-only">Отмечает все позиции, по которым ещё не выполнено списание со склада получателя.</p>
                                    @endif

                                    @error('issue_item_ids')
                                        <x-app-alert type="error" class="mt-3 text-xs">{{ $message }}</x-app-alert>
                                    @enderror

                                    <div class="mt-3 md:hidden app-card-list">
                                        @foreach($selectedApplication->items->where('is_checked', true) as $item)
                                            @php
                                                $canIssueHere = $deliveredWarehouseIssueCandidates->contains(fn ($candidate) => (int) $candidate->id === (int) $item->id);
                                                $checkedIssue = collect(old('issue_item_ids', []))->contains((string) $item->id) || collect(old('issue_item_ids', []))->contains((int) $item->id);
                                                $orderedQty = (int) $item->quantity;
                                                $defaultIssueQty = (int) old('issue_quantities.'.$item->id, $orderedQty);
                                                if ($defaultIssueQty < 1) {
                                                    $defaultIssueQty = $orderedQty;
                                                }
                                                if ($defaultIssueQty > $orderedQty) {
                                                    $defaultIssueQty = $orderedQty;
                                                }
                                            @endphp
                                            <article class="app-card-list__item">
                                                <div class="flex items-start gap-3">
                                                    @if($canIssueHere)
                                                        <label class="inline-flex items-start gap-2 text-sm">
                                                            <input
                                                                type="checkbox"
                                                                name="issue_item_ids[]"
                                                                value="{{ $item->id }}"
                                                                class="js-installation-act-issue-item mt-0.5 h-5 w-5 shrink-0 rounded border-orange-300 text-orange-600 focus:ring-orange-500"
                                                                @checked($checkedIssue)
                                                            >
                                                            <span class="text-xs text-black dark:text-white">Списать</span>
                                                        </label>
                                                    @elseif($item->resolvedDeliveryStatus() === \App\Models\ApplicationItem::DELIVERY_DELIVERED)
                                                        <span class="inline-flex items-center rounded-full border border-emerald-300/80 bg-emerald-50/90 px-2 py-0.5 text-xs font-medium text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                                                            Уже списано
                                                        </span>
                                                    @else
                                                        <span class="text-xs text-stone-500 dark:text-stone-400">Не доступно к списанию</span>
                                                    @endif
                                                </div>
                                                <p class="text-sm font-medium text-black dark:text-white app-equipment-line">{{ $item->equipment_display_name }}</p>
                                                <div class="grid grid-cols-1 gap-1 text-xs">
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/55">Склад получателя</p>
                                                        <p class="text-black dark:text-white app-equipment-line">
                                                            {{ $item->deliveryWarehouse?->name ?? '—' }}
                                                            @if($item->deliveryWarehouse?->subdivision)
                                                                <span class="text-stone-500 dark:text-stone-400">({{ $item->deliveryWarehouse->subdivision->name }})</span>
                                                            @endif
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/55">Кол-во в заявке</p>
                                                        <p class="text-black dark:text-white">{{ $item->quantity_with_unit }}</p>
                                                    </div>
                                                    @if($canIssueHere)
                                                        <input
                                                            type="hidden"
                                                            name="issue_quantities[{{ $item->id }}]"
                                                            value="{{ $defaultIssueQty }}"
                                                            class="js-installation-act-issue-qty-hidden"
                                                            data-item-id="{{ $item->id }}"
                                                            @disabled(! $checkedIssue)
                                                        >
                                                        <div>
                                                            <label for="issue_quantities_{{ $item->id }}_mobile" class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/55">Списать, {{ $item->quantityUnitLabelForDisplay() }}</label>
                                                            <input
                                                                id="issue_quantities_{{ $item->id }}_mobile"
                                                                type="number"
                                                                value="{{ $defaultIssueQty }}"
                                                                min="1"
                                                                max="{{ $orderedQty }}"
                                                                step="1"
                                                                inputmode="numeric"
                                                                data-item-id="{{ $item->id }}"
                                                                data-max-qty="{{ $orderedQty }}"
                                                                class="js-installation-act-issue-qty app-input mt-1 w-full max-w-[8rem] min-h-0 py-1.5 text-sm"
                                                                @disabled(! $checkedIssue)
                                                            >
                                                            <x-input-error :messages="$errors->get('issue_quantities.'.$item->id)" class="mt-1" />
                                                        </div>
                                                    @endif
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                    <div class="mt-3 hidden md:block app-table-shell">
                                        <table class="text-xs sm:text-sm">
                                            <thead class="bg-orange-100/70 dark:bg-orange-900/35">
                                                <tr>
                                                    <th class="px-3 py-2 text-left font-semibold align-top">
                                                        <div class="flex flex-col gap-1.5">
                                                            <span>Списать</span>
                                                            @if($deliveredWarehouseIssueCandidates->isNotEmpty())
                                                                <label for="installation-act-issue-select-all-desktop" class="inline-flex cursor-pointer select-none items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-stone-600 dark:text-stone-300" title="Отметить все позиции, доступные к списанию">
                                                                    <input
                                                                        type="checkbox"
                                                                        id="installation-act-issue-select-all-desktop"
                                                                        class="js-installation-act-issue-select-all rounded border-orange-300 text-orange-600 focus:ring-orange-500"
                                                                        aria-label="Выбрать все доступные к списанию"
                                                                    >
                                                                    Все
                                                                </label>
                                                            @endif
                                                        </div>
                                                    </th>
                                                    <th class="px-3 py-2 text-left font-semibold">Оборудование</th>
                                                    <th class="px-3 py-2 text-left font-semibold">Склад получателя</th>
                                                    <th class="px-3 py-2 text-right font-semibold">Кол-во в заявке</th>
                                                    <th class="px-3 py-2 text-right font-semibold">Списать</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-orange-100/90 dark:divide-orange-900/30">
                                                @foreach($selectedApplication->items->where('is_checked', true) as $item)
                                                    @php
                                                        $canIssueHere = $deliveredWarehouseIssueCandidates->contains(fn ($candidate) => (int) $candidate->id === (int) $item->id);
                                                        $checkedIssue = collect(old('issue_item_ids', []))->contains((string) $item->id) || collect(old('issue_item_ids', []))->contains((int) $item->id);
                                                        $orderedQty = (int) $item->quantity;
                                                        $defaultIssueQty = (int) old('issue_quantities.'.$item->id, $orderedQty);
                                                        if ($defaultIssueQty < 1) {
                                                            $defaultIssueQty = $orderedQty;
                                                        }
                                                        if ($defaultIssueQty > $orderedQty) {
                                                            $defaultIssueQty = $orderedQty;
                                                        }
                                                    @endphp
                                                    <tr class="bg-white/90 dark:bg-stone-900/40">
                                                        <td class="px-3 py-2">
                                                            @if($canIssueHere)
                                                                <label class="inline-flex items-center gap-2 text-xs">
                                                                    <input
                                                                        type="checkbox"
                                                                        name="issue_item_ids[]"
                                                                        value="{{ $item->id }}"
                                                                        class="js-installation-act-issue-item rounded border-orange-300 text-orange-600 focus:ring-orange-500"
                                                                        @checked($checkedIssue)
                                                                    >
                                                                </label>
                                                            @elseif($item->resolvedDeliveryStatus() === \App\Models\ApplicationItem::DELIVERY_DELIVERED)
                                                                <span class="text-emerald-700 dark:text-emerald-200">Уже списано</span>
                                                            @else
                                                                <span class="text-stone-500 dark:text-stone-400">—</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2">{{ $item->equipment_display_name }}</td>
                                                        <td class="px-3 py-2">
                                                            {{ $item->deliveryWarehouse?->name ?? '—' }}
                                                            @if($item->deliveryWarehouse?->subdivision)
                                                                <span class="text-stone-500 dark:text-stone-400">({{ $item->deliveryWarehouse->subdivision->name }})</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-right">{{ $item->quantity_with_unit }}</td>
                                                        <td class="px-3 py-2 text-right">
                                                            @if($canIssueHere)
                                                                <div class="inline-flex flex-col items-end gap-1">
                                                                    <input
                                                                        id="issue_quantities_{{ $item->id }}_desktop"
                                                                        type="number"
                                                                        value="{{ $defaultIssueQty }}"
                                                                        min="1"
                                                                        max="{{ $orderedQty }}"
                                                                        step="1"
                                                                        inputmode="numeric"
                                                                        data-item-id="{{ $item->id }}"
                                                                        data-max-qty="{{ $orderedQty }}"
                                                                        class="js-installation-act-issue-qty app-input w-24 min-h-0 py-1 text-right text-sm"
                                                                        aria-label="Количество к списанию, {{ $item->quantityUnitLabelForDisplay() }}"
                                                                        @disabled(! $checkedIssue)
                                                                    >
                                                                    <span class="text-[10px] text-stone-500 dark:text-stone-400">макс. {{ $orderedQty }} {{ $item->quantityUnitLabelForDisplay() }}</span>
                                                                    <x-input-error :messages="$errors->get('issue_quantities.'.$item->id)" class="text-left" />
                                                                </div>
                                                            @else
                                                                <span class="text-stone-400">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-3">
                                        @if($deliveredWarehouseIssueCandidates->isNotEmpty())
                                            <p class="text-xs text-amber-800 dark:text-amber-200">
                                                Доступно к списанию позиций: {{ $deliveredWarehouseIssueCandidates->count() }}. Выберите позиции, укажите количество (не больше заказанного) и нажмите «Сохранить».
                                            </p>
                                        @else
                                            <p class="text-xs text-emerald-700 dark:text-emerald-200">
                                                Все доставленные позиции уже списаны. Можно просто загрузить акт и фото.
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label for="installation_act" class="app-form-label">Файл акта установки</label>
                                <input
                                    id="installation_act"
                                    type="file"
                                    name="installation_act"
                                    required
                                    accept="application/pdf,.pdf"
                                    class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-orange-100 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-stone-800 hover:file:bg-orange-200/90 dark:text-stone-300 dark:file:bg-orange-950/50 dark:file:text-orange-100 dark:hover:file:bg-orange-900/60"
                                />
                                <p class="mt-1.5 text-xs text-stone-500 dark:text-stone-400">
                                    Только PDF — до 10 МБ.
                                </p>
                                <x-input-error :messages="$errors->get('installation_act')" class="mt-1.5" />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4 border-t border-stone-100 pt-8 dark:border-stone-800" aria-labelledby="act-section-photos">
                        <h3 id="act-section-photos" class="app-section-title">Фотографии</h3>
                        <div>
                            <label for="installation_act_photos" class="app-form-label">Файлы (обязательно)</label>
                            <input
                                id="installation_act_photos"
                                type="file"
                                name="installation_act_photos[]"
                                required
                                multiple
                                accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp"
                                class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-orange-100 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-stone-800 hover:file:bg-orange-200/90 dark:text-stone-300 dark:file:bg-orange-950/50 dark:file:text-orange-100 dark:hover:file:bg-orange-900/60"
                            />
                            <p class="mt-1.5 text-xs text-stone-500 dark:text-stone-400">
                                После акта добавьте хотя бы одно фото; до 30 файлов по 10 МБ (JPG, PNG, GIF, WebP, BMP).
                            </p>
                        
                            <x-input-error :messages="$errors->get('installation_act_photos')" class="mt-1.5" />
                            @foreach ($errors->keys() as $_errKey)
                                @if (str_starts_with($_errKey, 'installation_act_photos.'))
                                    <x-input-error :messages="$errors->get($_errKey)" class="mt-1.5" />
                                @endif
                            @endforeach
                        </div>
                    </section>

                    <div class="app-form-actions-mobile">
                        <a href="{{ route('applications.index') }}" class="min-h-11 content-center text-center text-sm font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-200 sm:text-left">
                            Отмена и к списку заявок
                        </a>
                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--lg w-full text-base disabled:opacity-60 disabled:cursor-not-allowed sm:w-auto" @disabled($applications->isEmpty() || ! $selectedApplication)>
                            Сохранить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const searchInput = document.getElementById('application_search');
            const select = document.getElementById('application_id');
            if (!searchInput || !select) {
                return;
            }

            const initialOptions = Array.from(select.options).map((option) => ({
                value: option.value,
                text: option.textContent || '',
                selected: option.selected,
            }));
            const placeholder = initialOptions[0] ?? { value: '', text: '— Выберите заявку —', selected: true };

            const rebuildOptions = (query) => {
                const q = String(query || '').trim().toLowerCase();
                const currentValue = select.value;
                const matched = initialOptions
                    .slice(1)
                    .filter((opt) => q === '' || opt.text.toLowerCase().includes(q));

                select.innerHTML = '';
                const placeholderOption = document.createElement('option');
                placeholderOption.value = placeholder.value;
                placeholderOption.textContent = placeholder.text;
                select.appendChild(placeholderOption);

                matched.forEach((opt) => {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.text;
                    if (opt.value === currentValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                if (currentValue !== '' && !matched.some((opt) => opt.value === currentValue)) {
                    select.value = '';
                }
            };

            searchInput.addEventListener('input', () => rebuildOptions(searchInput.value));
        })();

        (function () {
            const masters = document.querySelectorAll('.js-installation-act-issue-select-all');
            const items = document.querySelectorAll('.js-installation-act-issue-item');
            if (!items.length) {
                return;
            }

            const qtyInputsForItem = (itemId) => {
                return Array.from(document.querySelectorAll('.js-installation-act-issue-qty[data-item-id="' + itemId + '"]'));
            };

            const hiddenQtyForItem = (itemId) => {
                return document.querySelector('.js-installation-act-issue-qty-hidden[data-item-id="' + itemId + '"]');
            };

            const syncQtyToHidden = (itemId) => {
                const hidden = hiddenQtyForItem(itemId);
                const visible = qtyInputsForItem(itemId)[0];
                if (!hidden || !visible) {
                    return;
                }
                hidden.value = visible.value;
            };

            const setQtyEnabled = (checkbox, enabled) => {
                const itemId = checkbox.value;
                const maxQty = checkbox.closest('article, tr')?.querySelector('.js-installation-act-issue-qty')?.dataset.maxQty;
                qtyInputsForItem(itemId).forEach((input) => {
                    input.disabled = !enabled;
                    if (!enabled && maxQty) {
                        input.value = maxQty;
                    }
                });
                const hidden = hiddenQtyForItem(itemId);
                if (hidden) {
                    hidden.disabled = !enabled;
                    if (!enabled && maxQty) {
                        hidden.value = maxQty;
                    } else if (enabled) {
                        syncQtyToHidden(itemId);
                    }
                }
            };

            document.querySelectorAll('.js-installation-act-issue-qty').forEach((input) => {
                const itemId = input.dataset.itemId;
                const syncAllVisible = () => {
                    qtyInputsForItem(itemId).forEach((peer) => {
                        if (peer !== input) {
                            peer.value = input.value;
                        }
                    });
                    syncQtyToHidden(itemId);
                };
                input.addEventListener('input', syncAllVisible);
                input.addEventListener('change', syncAllVisible);
            });

            const syncMasters = () => {
                if (!masters.length) {
                    return;
                }
                const total = items.length;
                const checked = Array.from(items).filter((c) => c.checked).length;
                masters.forEach((m) => {
                    m.checked = total > 0 && checked === total;
                    m.indeterminate = checked > 0 && checked < total;
                });
            };

            items.forEach((checkbox) => {
                setQtyEnabled(checkbox, checkbox.checked);
                checkbox.addEventListener('change', () => {
                    setQtyEnabled(checkbox, checkbox.checked);
                    syncMasters();
                });
            });

            masters.forEach((master) => {
                master.addEventListener('change', () => {
                    const on = master.checked;
                    items.forEach((c) => {
                        c.checked = on;
                        setQtyEnabled(c, on);
                    });
                    masters.forEach((m) => {
                        m.checked = on;
                        m.indeterminate = false;
                    });
                });
            });

            syncMasters();
        })();
    </script>
</x-app-layout>
