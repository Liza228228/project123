@php // шаблон страницы
    use App\Models\ApplicationItem;
    use App\Support\WarehouseStockBucket;
@endphp
@if($chiefCanManageDeliveryDefect && $deliveryDefectItems->isNotEmpty())
    <div class="mt-4 space-y-3 rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 dark:border-amber-800/50 dark:bg-amber-950/25">
        <div class="space-y-1">
            <h4 class="text-sm font-semibold text-amber-950 dark:text-amber-100">Брак на складе получателя</h4>
            <p class="text-xs text-black/80 dark:text-white/80">
                Оборудование уже принято на склад. Если при осмотре выявлен брак — переведите нужное количество в бракованный остаток. Бракованное оборудование остаётся на том же складе отдельным остатком и может быть утилизировано.
            </p>
        </div>
        <ul class="space-y-3">
            @foreach($deliveryDefectItems->sortBy('id') as $defectItem)
                @php
                    $appId = (int) $application->id;
                    $itemId = (int) $defectItem->id;
                    $markedDefective = WarehouseStockBucket::markedDefectiveQuantityForApplicationItem($appId, $itemId);
                    $remainingDefective = WarehouseStockBucket::remainingDefectiveQuantityForApplicationItem($appId, $itemId);
                    $disposedDefective = max(0.0, $markedDefective - $remainingDefective);
                    $maxMarkDefect = WarehouseStockBucket::maxMarkableDefectQuantity(
                        (float) $defectItem->quantity,
                        $appId,
                        $itemId,
                        (int) $defectItem->equipment_id,
                        (int) ($defectItem->delivery_warehouse_id ?? 0),
                    );
                @endphp
                <li class="rounded-md border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-950/60 px-3 py-3 space-y-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-medium text-black dark:text-white">
                                {{ $defectItem->equipment_display_name }} × {{ $defectItem->quantity_with_unit }}
                            </p>
                            @if($defectItem->deliveryWarehouse)
                                <p class="text-xs text-black/70 dark:text-white/70">
                                    Склад: {{ $defectItem->deliveryWarehouse->name }}
                                </p>
                            @endif
                        </div>
                        @include('applications.partials.custom-equipment-supply-badge', ['item' => $defectItem])
                    </div>

                    @if($markedDefective > 0.0005 || $remainingDefective > 0.0005)
                        <dl class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-3">
                            <div class="rounded-lg border border-amber-200/80 bg-amber-50/70 px-2 py-1.5 dark:border-amber-900/50 dark:bg-amber-950/30">
                                <dt class="font-medium text-amber-900/80 dark:text-amber-100/80">В браке</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-amber-950 dark:text-amber-50">{{ rtrim(rtrim(number_format($markedDefective, 3, '.', ''), '0'), '.') }} {{ $defectItem->quantityUnitLabelForDisplay() }}</dd>
                            </div>
                            <div class="rounded-lg border border-stone-200/80 bg-stone-50/70 px-2 py-1.5 dark:border-stone-700 dark:bg-stone-900/40">
                                <dt class="font-medium text-black/70 dark:text-white/70">К утилизации</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-black dark:text-white">{{ rtrim(rtrim(number_format($remainingDefective, 3, '.', ''), '0'), '.') }} {{ $defectItem->quantityUnitLabelForDisplay() }}</dd>
                            </div>
                            <div class="rounded-lg border border-stone-200/80 bg-stone-50/70 px-2 py-1.5 dark:border-stone-700 dark:bg-stone-900/40">
                                <dt class="font-medium text-black/70 dark:text-white/70">Утилизировано</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-black dark:text-white">{{ rtrim(rtrim(number_format($disposedDefective, 3, '.', ''), '0'), '.') }} {{ $defectItem->quantityUnitLabelForDisplay() }}</dd>
                            </div>
                        </dl>
                    @endif

                    @if($maxMarkDefect > 0.0005)
                        @php
                            $deliveredQty = (float) $defectItem->quantity;
                            $isWholeQty = abs($deliveredQty - round($deliveredQty)) < 0.0005;
                            $defectQtyMin = $isWholeQty ? 1 : 0.001;
                            $defectQtyMaxDisplay = rtrim(rtrim(number_format($deliveredQty, 3, '.', ''), '0'), '.');
                            $defectQtyMaxLength = $isWholeQty ? max(1, strlen((string) (int) ceil($deliveredQty))) : 12;
                        @endphp
                        <form method="POST" action="{{ route('applications.delivery-defective', [$application, $defectItem]) }}" class="space-y-2 rounded-lg border border-amber-200/70 bg-amber-50/40 p-3 dark:border-amber-900/40 dark:bg-amber-950/20" data-defect-mark-form data-qty-min="{{ $defectQtyMin }}" data-qty-max="{{ $deliveredQty }}" data-qty-whole="{{ $isWholeQty ? '1' : '0' }}">
                            @csrf
                            <p class="text-xs font-medium text-amber-950 dark:text-amber-100">Перевести в брак</p>
                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                                <div class="w-full sm:w-28">
                                    <label class="app-form-label !normal-case text-xs" for="defect-qty-{{ $defectItem->id }}">Кол-во</label>
                                    <input
                                        type="text"
                                        name="defect_quantity"
                                        id="defect-qty-{{ $defectItem->id }}"
                                        value="{{ old('defect_quantity') }}"
                                        maxlength="{{ $defectQtyMaxLength }}"
                                        class="app-input text-sm w-full"
                                        required
                                        autocomplete="off"
                                        inputmode="{{ $isWholeQty ? 'numeric' : 'decimal' }}"
                                        data-defect-qty-input
                                    >
                                </div>
                                <div class="min-w-0 flex-1">
                                    <label class="app-form-label !normal-case text-xs" for="defect-reason-{{ $defectItem->id }}">Причина брака</label>
                                    <input
                                        type="text"
                                        name="defect_reason"
                                        id="defect-reason-{{ $defectItem->id }}"
                                        maxlength="1000"
                                        value="{{ old('defect_reason') }}"
                                        placeholder="Например: механическое повреждение при транспортировке"
                                        class="app-input text-sm w-full"
                                        required
                                    >
                                </div>
                                <button type="submit" class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto whitespace-nowrap">
                                    Отметить брак
                                </button>
                            </div>
                            <p class="text-[11px] text-black/60 dark:text-white/60">От 1 до {{ $defectQtyMaxDisplay }} {{ $defectItem->quantityUnitLabelForDisplay() }} — не больше, чем доставлено по позиции.@if($maxMarkDefect + 0.0005 < $deliveredQty) Сейчас доступно не более {{ rtrim(rtrim(number_format($maxMarkDefect, 3, '.', ''), '0'), '.') }} {{ $defectItem->quantityUnitLabelForDisplay() }}.@endif</p>
                        </form>
                    @endif

                    @if($remainingDefective > 0.0005)
                        @php
                            $disposeIsWholeQty = abs($remainingDefective - round($remainingDefective)) < 0.0005;
                            $disposeQtyMin = $disposeIsWholeQty ? 1 : 0.001;
                            $disposeQtyMaxDisplay = rtrim(rtrim(number_format($remainingDefective, 3, '.', ''), '0'), '.');
                            $disposeQtyMaxLength = $disposeIsWholeQty ? max(1, strlen((string) (int) ceil($remainingDefective))) : 12;
                        @endphp
                        <form method="POST" action="{{ route('applications.delivery-defective-dispose', [$application, $defectItem]) }}" class="space-y-2 rounded-lg border border-stone-200/80 bg-stone-50/50 p-3 dark:border-stone-700 dark:bg-stone-900/30" data-defect-dispose-form data-qty-min="{{ $disposeQtyMin }}" data-qty-max="{{ $remainingDefective }}" data-qty-whole="{{ $disposeIsWholeQty ? '1' : '0' }}">
                            @csrf
                            <p class="text-xs font-medium text-black dark:text-white">Утилизировать брак</p>
                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                                <div class="w-full sm:w-28">
                                    <label class="app-form-label !normal-case text-xs" for="dispose-qty-{{ $defectItem->id }}">Кол-во</label>
                                    <input
                                        type="text"
                                        name="dispose_quantity"
                                        id="dispose-qty-{{ $defectItem->id }}"
                                        value="{{ old('dispose_quantity') }}"
                                        maxlength="{{ $disposeQtyMaxLength }}"
                                        class="app-input text-sm w-full"
                                        required
                                        autocomplete="off"
                                        inputmode="{{ $disposeIsWholeQty ? 'numeric' : 'decimal' }}"
                                        data-defect-qty-input
                                    >
                                </div>
                                <div class="min-w-0 flex-1">
                                    <label class="app-form-label !normal-case text-xs" for="dispose-comment-{{ $defectItem->id }}">Комментарий</label>
                                    <input
                                        type="text"
                                        name="dispose_comment"
                                        id="dispose-comment-{{ $defectItem->id }}"
                                        maxlength="1000"
                                        value="{{ old('dispose_comment') }}"
                                        placeholder="Необязательно"
                                        class="app-input text-sm w-full"
                                    >
                                </div>
                                <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm w-full sm:w-auto whitespace-nowrap">
                                    Утилизировать
                                </button>
                            </div>
                            <p class="text-[11px] text-black/60 dark:text-white/60">От 1 до {{ $disposeQtyMaxDisplay }} {{ $defectItem->quantityUnitLabelForDisplay() }} — не больше, чем к утилизации по позиции.</p>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        @error('defect')
            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error('defect_reason')
            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error('dispose_quantity')
            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>
    @once
        <script>
            (function () {
                function clampDefectQtyInput(input, form) {
                    var maxQty = parseFloat(form.getAttribute('data-qty-max') || '0');
                    var minQty = parseFloat(form.getAttribute('data-qty-min') || '1');
                    var wholeOnly = form.getAttribute('data-qty-whole') === '1';
                    var raw = String(input.value || '');

                    if (wholeOnly) {
                        raw = raw.replace(/[^\d]/g, '');
                        if (raw === '') {
                            input.value = '';
                            return;
                        }
                        var intValue = parseInt(raw, 10);
                        if (! Number.isFinite(intValue) || intValue <= 0) {
                            input.value = '';
                            return;
                        }
                        if (intValue > maxQty) {
                            intValue = Math.floor(maxQty);
                        }
                        input.value = String(intValue);
                        return;
                    }

                    raw = raw.replace(/[^\d.,]/g, '').replace(/(\..*)\./g, '$1');
                    if (raw === '' || raw === '.' || raw === ',') {
                        input.value = raw === '' ? '' : raw;
                        return;
                    }

                    var value = parseFloat(raw.replace(',', '.'));
                    if (! Number.isFinite(value) || value <= 0) {
                        input.value = '';
                        return;
                    }
                    if (value > maxQty) {
                        value = maxQty;
                    }
                    if (value < minQty) {
                        input.value = '';
                        return;
                    }
                    input.value = String(value);
                }

                function isDefectQtyInputValid(input, form) {
                    clampDefectQtyInput(input, form);

                    var raw = String(input.value || '').trim();
                    if (raw === '' || raw === '.' || raw === ',') {
                        return false;
                    }

                    var maxQty = parseFloat(form.getAttribute('data-qty-max') || '0');
                    var minQty = parseFloat(form.getAttribute('data-qty-min') || '1');
                    var value = parseFloat(raw.replace(',', '.'));

                    return Number.isFinite(value) && value >= minQty - 0.000001 && value <= maxQty + 0.000001;
                }

                function bindDefectQtyForm(form) {
                    var input = form.querySelector('[data-defect-qty-input]');
                    if (! input) {
                        return;
                    }

                    input.addEventListener('input', function () {
                        clampDefectQtyInput(input, form);
                    });

                    input.addEventListener('blur', function () {
                        clampDefectQtyInput(input, form);
                    });

                    form.addEventListener('submit', function (event) {
                        if (! isDefectQtyInputValid(input, form)) {
                            event.preventDefault();
                            clampDefectQtyInput(input, form);
                        }
                    });

                    clampDefectQtyInput(input, form);
                }

                function initDefectQtyForms() {
                    document.querySelectorAll('[data-defect-mark-form], [data-defect-dispose-form]').forEach(bindDefectQtyForm);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initDefectQtyForms);
                } else {
                    initDefectQtyForms();
                }
            })();
        </script>
    @endonce
@endif
