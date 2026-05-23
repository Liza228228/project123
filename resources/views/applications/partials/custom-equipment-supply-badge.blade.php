@if($item->isCommercialOfferWarehouseReserved())
    <span class="inline-flex items-center rounded-full border border-emerald-300/90 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-950 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-100">
        Зарезервировано по КП
    </span>
@elseif($item->isOrderedFromCommercialOffer())
    <span class="inline-flex items-center rounded-full border border-orange-300/90 bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-950 dark:border-orange-700/60 dark:bg-orange-950/40 dark:text-orange-100">
        Заказано по КП
    </span>
@endif
@if($item->usesFreeTextEquipment())
    @php
        $code = $item->resolvedCustomSupplyStatus();
        $badgeClass = match ($code) {
            \App\Models\ApplicationItem::CUSTOM_SUPPLY_ON_WAREHOUSE => 'border-emerald-300/90 bg-emerald-50 text-emerald-950 dark:border-emerald-800/80 dark:bg-emerald-950/35 dark:text-emerald-100',
            \App\Models\ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT => 'border-orange-300/90 bg-orange-50 text-orange-950 dark:border-orange-800/80 dark:bg-orange-950/35 dark:text-orange-100',
            \App\Models\ApplicationItem::CUSTOM_SUPPLY_ORDERED => 'border-violet-300/90 bg-violet-50 text-violet-950 dark:border-violet-800/80 dark:bg-violet-950/35 dark:text-violet-100',
            \App\Models\ApplicationItem::CUSTOM_SUPPLY_ACCEPTED => 'border-sky-300/90 bg-sky-50 text-sky-950 dark:border-sky-800/80 dark:bg-sky-950/35 dark:text-sky-100',
            default => 'border-amber-300/90 bg-amber-50 text-amber-950 dark:border-amber-800/80 dark:bg-amber-950/35 dark:text-amber-100',
        };
    @endphp
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium border {{ $badgeClass }}">
        {{ $item->customSupplyStatusLabel() }}
    </span>
@endif

@if($item->deliveryStatusLabel())
    @php
        $deliveryClass = match ($item->resolvedDeliveryStatus()) {
            \App\Models\ApplicationItem::DELIVERY_DELIVERED => 'border-emerald-300/90 bg-emerald-50 text-emerald-950 dark:border-emerald-800/80 dark:bg-emerald-950/35 dark:text-emerald-100',
            default => 'border-orange-300/90 bg-orange-50 text-orange-950 dark:border-orange-800/80 dark:bg-orange-950/35 dark:text-orange-100',
        };
    @endphp
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium border {{ $deliveryClass }}">
        {{ $item->deliveryStatusLabel() }}
    </span>
@endif
