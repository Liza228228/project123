@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Warehouse>|\App\Models\Warehouse[] $warehouses */
    $warehouses = $warehouses ?? collect();
    $subdivisionInactive = (bool) ($subdivisionInactive ?? false);
@endphp

@if($warehouses->isEmpty())
    <span class="inline-flex items-center rounded-full border border-stone-200/90 bg-stone-100/90 px-2.5 py-1 text-xs font-medium text-stone-600 dark:border-stone-600 dark:bg-stone-900/50 dark:text-stone-400">
        Складов нет
    </span>
@else
    <ul class="subdivisions-warehouse-list space-y-2">
        @foreach($warehouses as $warehouse)
            @php
                $addressLine = $warehouse->formatted_address;
                $postal = trim((string) ($warehouse->address_postal_code ?? ''));
                if ($postal !== '' && $addressLine !== '') {
                    $prefix = $postal.',';
                    if (str_starts_with($addressLine, $prefix)) {
                        $addressLine = trim(mb_substr($addressLine, mb_strlen($prefix)));
                    } elseif (str_starts_with($addressLine, $postal.' ')) {
                        $addressLine = trim(mb_substr($addressLine, mb_strlen($postal)));
                    }
                }
            @endphp
            <li class="subdivisions-warehouse-card">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="text-sm font-semibold text-stone-900 dark:text-stone-100">{{ $warehouse->name }}</span>
                    @if($warehouse->is_primary ?? false)
                        <span class="inline-flex items-center rounded-full bg-orange-200/90 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-950 dark:bg-orange-900/50 dark:text-orange-100">
                            Основной
                        </span>
                    @endif
                    @if($subdivisionInactive)
                        <span class="inline-flex items-center rounded-full bg-stone-200/90 px-2 py-0.5 text-[10px] font-semibold text-stone-700 dark:bg-stone-700/60 dark:text-stone-200">
                            Недоступен
                        </span>
                    @endif
                </div>
                @if($addressLine !== '' || $postal !== '')
                    <div class="mt-1.5 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-start sm:gap-2">
                        @if($postal !== '')
                            <span class="inline-flex w-fit shrink-0 items-center rounded-md border border-orange-200/80 bg-orange-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-orange-950 dark:border-orange-800/60 dark:bg-orange-950/40 dark:text-orange-100">
                                {{ $postal }}
                            </span>
                        @endif
                        @if($addressLine !== '')
                            <p class="min-w-0 text-xs leading-relaxed text-stone-600 dark:text-stone-400">{{ $addressLine }}</p>
                        @endif
                    </div>
                @else
                    <p class="mt-1 text-xs italic text-stone-400 dark:text-stone-500">Адрес не указан</p>
                @endif
            </li>
        @endforeach
    </ul>
@endif
