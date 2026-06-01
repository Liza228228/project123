@php // шаблон страницы
    $fmtQty = static function (float $v): string {
        if (abs($v - round($v)) < 0.0005) {
            return (string) (int) round($v);
        }

        return rtrim(rtrim(number_format($v, 3, ',', ' '), '0'), ',');
    };
    $pendingOverflow = ($application->items ?? collect())
        ->filter(fn ($i) => $i->isCatalogOverflowPendingOrderLine() && $i->is_checked)
        ->sortBy('id');
@endphp
<div class="mb-6 rounded-xl border border-violet-200/90 bg-violet-50/60 p-4 dark:border-violet-800/50 dark:bg-violet-950/25">
    <h4 class="app-form-label !normal-case !mb-1 text-violet-950 dark:text-violet-100">Что нужно заказать</h4>
    

    @if(($customEquipmentProcurementLines ?? collect())->isNotEmpty())
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-violet-900/90 dark:text-violet-200/90">Своё название</p>
        <ul class="mb-4 divide-y divide-violet-200/80 overflow-hidden rounded-lg border border-violet-200/70 dark:divide-violet-900/40 dark:border-violet-900/40">
            @foreach($customEquipmentProcurementLines as $item)
                @php
                    $customHint = match ($item->resolvedCustomSupplyStatus()) {
                        \App\Models\ApplicationItem::CUSTOM_SUPPLY_ACCEPTED => 'Нужно оформить заказ и оприходовать на основной склад',
                        \App\Models\ApplicationItem::CUSTOM_SUPPLY_ORDERED => 'Заказано — отметьте поступление на основной склад',
                        \App\Models\ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT => 'В пути на основной склад',
                        default => 'В работе у снабжения',
                    };
                @endphp
                <li class="space-y-1 bg-white/90 px-3 py-2.5 dark:bg-stone-900/50">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <span class="text-sm font-medium text-black dark:text-white">
                            {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                        </span>
                        @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                    </div>
                    <p class="text-xs text-black/80 dark:text-white/75">{{ $customHint }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    @if($pendingOverflow->isNotEmpty())
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-violet-900/90 dark:text-violet-200/90">Дозаказ по каталогу (согласование)</p>
        <ul class="mb-4 divide-y divide-violet-200/80 overflow-hidden rounded-lg border border-violet-200/70 dark:divide-violet-900/40 dark:border-violet-900/40">
            @foreach($pendingOverflow as $item)
                <li class="space-y-1 bg-white/90 px-3 py-2.5 dark:bg-stone-900/50">
                    <span class="text-sm font-medium text-black dark:text-white">
                        {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                    </span>
                    <p class="text-xs text-black/80 dark:text-white/75">Ожидает согласования и заказа сверх остатка на основном складе</p>
                </li>
            @endforeach
        </ul>
    @endif

    @if(($catalogProcurementLines ?? collect())->isNotEmpty())
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-violet-900/90 dark:text-violet-200/90">Каталог — нехватка на основном складе</p>
        <ul class="divide-y divide-violet-200/80 overflow-hidden rounded-lg border border-violet-200/70 dark:divide-violet-900/40 dark:border-violet-900/40">
            @foreach($catalogProcurementLines as $item)
                @php
                    $physical = (float) (($catalogPhysicalBalanceByItemId ?? [])[(int) $item->id] ?? 0.0);
                    $shortageQty = (int) (($catalogShortageQtyByItem ?? [])[(int) $item->id]
                        ?? $item->catalogShortageQtyForMainWarehouseDelivery($physical));
                    $unitLabel = $item->quantityUnitLabelForDisplay();
                @endphp
                <li class="space-y-1 bg-white/90 px-3 py-2.5 dark:bg-stone-900/50">
                    <span class="text-sm font-medium text-black dark:text-white">
                        {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                    </span>
                    <p class="text-xs text-black/80 dark:text-white/75">
                        На основном складе «{{ $mainWarehouse->name ?? 'основной' }}»: {{ $fmtQty($physical) }} {{ $unitLabel }},
                        к дозаказу: {{ $shortageQty }} {{ $unitLabel }}.
                    </p>
                </li>
            @endforeach
        </ul>
    @endif

    @if(($canOrderCustomEquipment ?? false) && ($hasCustomEquipmentOrderForm ?? false) && ! ($supplyAwaitingPostBoilerManagementSave ?? false))
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('applications.custom-equipment-order', $application) }}" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap">
                Форма: своё оборудование к заказу
            </a>
        </div>
    @endif
</div>
