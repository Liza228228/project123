@if($application->items->isEmpty())
    <span class="opacity-50">—</span>
@else
    @php
        $idxUnchecked = $application->items->filter(fn ($i) => ! $application->itemLineIsApproved($i->id))->sortBy('id');
        $idxChecked = $application->items->filter(fn ($i) => $application->itemLineIsApproved($i->id))->sortBy('id');
        $equipmentCount = (int) $application->items->count();
        $chiefOwnDraftList = $application->isBoilerChiefCreatedApplication()
            && $application->isBoilerChiefDraftBeforeManagement();
        $uncheckedSectionTitle = $chiefOwnDraftList ? 'Позиции заявки' : 'Не согласовано';
    @endphp
    <details class="application-index-equipment-details rounded-lg border border-stone-200/90 bg-white/60 dark:border-stone-700 dark:bg-stone-900/20">
        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-2 gap-y-0.5 px-2 py-1.5 text-xs font-medium text-stone-800 outline-none hover:bg-stone-100/80 dark:text-stone-100 dark:hover:bg-stone-800/50 [&::-webkit-details-marker]:hidden">
            <span class="font-normal text-stone-600 dark:text-stone-400">{{ $equipmentCount }} {{ $equipmentCount === 1 ? 'позиция' : ($equipmentCount < 5 ? 'позиции' : 'позиций') }}</span>
            <span class="application-index-equipment-expand-label application-index-equipment-expand-label--collapsed shrink-0 text-[10px] font-semibold uppercase tracking-wide text-orange-700 dark:text-orange-300">Развернуть</span>
            <span class="application-index-equipment-expand-label application-index-equipment-expand-label--expanded shrink-0 text-[10px] font-semibold uppercase tracking-wide text-orange-700 dark:text-orange-300">Свернуть</span>
        </summary>
        <div class="space-y-4 border-t border-stone-200/80 px-3 py-3 dark:border-stone-700">
            @if($idxUnchecked->isNotEmpty())
                <div>
                    <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">{{ $uncheckedSectionTitle }}</h4>
                    <ul class="divide-y divide-stone-200 dark:divide-stone-800 rounded-lg border border-stone-300 dark:border-stone-700 overflow-hidden">
                        @foreach($idxUnchecked as $item)
                            <li class="px-4 py-3 bg-stone-50/80 dark:bg-stone-900/25 space-y-1">
                                <span class="text-sm font-medium text-black dark:text-white">
                                    {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                </span>
                                @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                                @if($application->itemLineRejectionReason($item->id))
                                    <p class="mt-1 text-sm text-black dark:text-white"><span class="font-medium text-black dark:text-white">Причина:</span> {{ $application->itemLineRejectionReason($item->id) }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($idxChecked->isNotEmpty())
                <div>
                    <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Согласовано</h4>
                    <ul class="divide-y divide-stone-200 dark:divide-stone-800 rounded-lg border border-stone-300 dark:border-stone-700 overflow-hidden">
                        @foreach($idxChecked as $item)
                            <li class="px-4 py-3 bg-stone-100/60 dark:bg-stone-900/30 space-y-1">
                                <span class="text-sm font-medium text-black dark:text-white">
                                    {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                </span>
                                @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </details>
@endif
