@php
    $quantityColumnHeader = 'Количество / маркировка';
    $journalSubdivisionScoped = (bool) ($materialsJournalSubdivisionScoped ?? false);
    $mainWhName = $mainWarehouseForJournalContext?->name;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
                Журнал операций по оборудованию
            </h2>
            <a
                href="{{ $materialsJournalBackUrl }}"
                class="ui-btn ui-btn--secondary shrink-0"
            >
                @if(auth()->user()?->hasAnyRoleId([1, 2, 3]))
                    Вернуться к учёту оборудования
                @else
                    Вернуться к остаткам по складам
                @endif
            </a>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-10 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="rounded-2xl border border-orange-200/80 bg-orange-50/35 shadow-sm ring-1 ring-orange-100/70 dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <h3 class="text-lg font-semibold text-black dark:text-white">Фильтры</h3>
                <form
                    method="GET"
                    action="{{ route('materials.movements') }}"
                    class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end w-full sm:w-auto"
                    data-auto-submit="filter"
                >
                    <div class="min-w-0 sm:w-80">
                        <label for="warehouse_filter_journal" class="app-form-label">Склад</label>
                        <input
                            id="warehouse_filter_journal_search"
                            type="search"
                            class="app-input mb-2 min-h-0 w-full min-w-0 sm:max-w-xs"
                            placeholder="Поиск по подразделению или складу"
                            autocomplete="off"
                        />
                        <select id="warehouse_filter_journal" name="warehouse_id" class="app-select w-full min-w-0 sm:max-w-xs">
                            <option value="">Все склады</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>
                                    @if($warehouse->subdivision)
                                        {{ $warehouse->subdivision->name }} — {{ $warehouse->name }}
                                    @else
                                        {{ $warehouse->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0 sm:w-56">
                        <label for="movement_type_filter_journal" class="app-form-label">Тип операции</label>
                        <select id="movement_type_filter_journal" name="movement_type_id" class="app-select w-full min-w-0 sm:max-w-xs">
                            <option value="">Все типы</option>
                            @foreach($movementTypes as $movementType)
                                <option value="{{ $movementType->id }}" @selected($selectedMovementTypeId === (int) $movementType->id)>{{ $movementType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0 sm:w-44">
                        <label for="journal_per_page" class="app-form-label">На странице</label>
                        <select id="journal_per_page" name="per_page" class="app-select w-full min-w-0 sm:max-w-xs">
                            @foreach($allowedPerPage as $size)
                                <option value="{{ $size }}" @selected((int) ($perPage ?? 0) === (int) $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

          

            @if($movements->isEmpty())
                <p class="mt-4 rounded-xl border border-dashed border-stone-300 px-4 py-6 text-center text-sm text-black/70 dark:border-stone-600 dark:text-white/70">
                    Операций пока нет.
                </p>
            @else
                <div class="mt-4 md:hidden app-card-list">
                    @foreach($movements as $movement)
                        @php
                            $signed = $movement->signedQuantity();
                            $utc = (string) ($movement->equipment?->measurementUnit?->unitType?->code ?? '');
                            $qtyLabel = match ($utc) {
                                'clothing_size' => 'Размер',
                                'length' => 'Длина',
                                'mass' => 'Масса',
                                'piece' => 'Штуки',
                                default => 'Кол-во',
                            };
                            $signedClass = $signed < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-300';
                            $deliveryReceipt = $journalSubdivisionScoped
                                && ($movement->movementType?->name ?? '') === \App\Models\MaterialStockMovementType::NAME_RECEIPT
                                && str_contains((string) ($movement->comment ?? ''), ':DELIVERY-RCPT:');
                            $sourceWarehouseHint = $deliveryReceipt && $mainWhName
                                ? 'Поступление с основного склада «'.$mainWhName.'»'
                                : ($deliveryReceipt ? 'Поступление с основного склада' : null);
                            $commentDisplay = \App\Models\MaterialStockMovement::commentBodyForDisplay($movement->comment);
                            $eq = $movement->equipment;
                            $hideUnitInEquipment = $eq
                                && ($eq->measurementUnit?->unitType?->code ?? '') === 'clothing_size'
                                && trim((string) ($eq->value ?? '')) !== '';
                        @endphp
                        <article class="app-card-list__item">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-medium text-black dark:text-white app-equipment-line">
                                    {{ $eq?->display_name ?? '—' }}
                                    @if($eq && ! $hideUnitInEquipment)
                                        <span class="text-black/55 dark:text-white/55">({{ $eq->stockQuantityUnitLabel() }})</span>
                                    @endif
                                </p>
                                <div class="text-right">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-black/50 dark:text-white/50">{{ $qtyLabel }}</p>
                                    @php $qtySuffix = $movement->quantityDisplaySuffix(); @endphp
                                    <p class="text-sm font-semibold {{ $signedClass }}">
                                        @include('materials.partials.balance-quantity-cell', ['quantity' => $signed, 'unitCode' => $qtySuffix, 'measurementTypeCode' => $utc, 'class' => ''])
                                    </p>
                                </div>
                            </div>
                            <p class="text-xs text-black/70 dark:text-white/65">
                                {{ $movement->created_at?->format('d.m.Y H:i') }} ·
                                @if($journalSubdivisionScoped && $movement->warehouse?->subdivision)
                                    {{ $movement->warehouse->subdivision->name }} — {{ $movement->warehouse->name }}
                                @else
                                    {{ $movement->warehouse?->name ?? '—' }}
                                @endif
                            </p>
                            @if($sourceWarehouseHint)
                                <p class="text-xs text-black/75 dark:text-white/75">{{ $sourceWarehouseHint }}</p>
                            @endif
                            <p class="text-xs text-black/80 dark:text-white/80">
                                <span class="font-medium">{{ $movement->movementType?->name ?? '—' }}</span>
                                @if($movement->counterparty)
                                    · {{ $movement->counterparty }}
                                @endif
                            </p>
                            <p class="text-xs text-black/70 dark:text-white/65">
                                Автор: {{ $movement->performerDisplayName() }}
                            </p>
                            @if($commentDisplay)
                                <p class="text-xs text-black/70 dark:text-white/65 break-words">{{ \Illuminate\Support\Str::limit($commentDisplay, 160) }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
                <div class="mt-4 hidden md:block app-table-shell">
                    <table class="text-sm text-black dark:text-white">
                        <thead>
                            <tr class="border-b border-stone-200 dark:border-stone-700">
                                <th class="text-left py-2 pr-3">Дата</th>
                                <th class="text-left py-2 pr-3">Оборудование</th>
                                <th class="text-left py-2 pr-3">Склад</th>
                                @if($journalSubdivisionScoped)
                                    <th class="text-left py-2 pr-3">Откуда</th>
                                @endif
                                <th class="text-left py-2 pr-3">Тип</th>
                                <th class="text-left py-2 pr-3">Автор</th>
                                <th class="text-left py-2 pr-3">Заявка</th>
                                <th class="text-right py-2 pr-3">{{ $quantityColumnHeader }}</th>
                                <th class="text-left py-2 max-w-[18rem]">Комментарий</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movements as $movement)
                                @php
                                    $signed = $movement->signedQuantity();
                                    $utc = (string) ($movement->equipment?->measurementUnit?->unitType?->code ?? '');
                                    $qtyLabel = match ($utc) {
                                        'clothing_size' => 'Размер',
                                        'length' => 'Длина',
                                        'mass' => 'Масса',
                                        'piece' => 'Штуки',
                                        default => 'Кол-во',
                                    };
                                    $deliveryReceiptRow = $journalSubdivisionScoped
                                        && ($movement->movementType?->name ?? '') === \App\Models\MaterialStockMovementType::NAME_RECEIPT
                                        && str_contains((string) ($movement->comment ?? ''), ':DELIVERY-RCPT:');
                                    $sourceCell = $deliveryReceiptRow && $mainWhName
                                        ? 'Основной: «'.$mainWhName.'»'
                                        : ($deliveryReceiptRow ? 'Основной склад' : '—');
                                    $commentDisplay = \App\Models\MaterialStockMovement::commentBodyForDisplay($movement->comment);
                                    $eq = $movement->equipment;
                                    $hideUnitInEquipment = $eq
                                        && ($eq->measurementUnit?->unitType?->code ?? '') === 'clothing_size'
                                        && trim((string) ($eq->value ?? '')) !== '';
                                @endphp
                                <tr class="border-b border-stone-100 dark:border-stone-700/60">
                                    <td class="py-2 pr-3">{{ $movement->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="py-2 pr-3">
                                        {{ $eq?->display_name ?? '—' }}
                                        @if($eq && ! $hideUnitInEquipment)
                                            ({{ $eq->stockQuantityUnitLabel() }})
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3">
                                        @if($journalSubdivisionScoped && $movement->warehouse?->subdivision)
                                            {{ $movement->warehouse->subdivision->name }} — {{ $movement->warehouse->name }}
                                        @else
                                            {{ $movement->warehouse?->name }}
                                        @endif
                                    </td>
                                    @if($journalSubdivisionScoped)
                                        <td class="py-2 pr-3 text-xs text-black/80 dark:text-white/80">{{ $sourceCell }}</td>
                                    @endif
                                    <td class="py-2 pr-3">{{ $movement->movementType?->name ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-xs text-black/80 dark:text-white/80">{{ $movement->performerDisplayName() }}</td>
                                    <td class="py-2 pr-3 text-xs text-black/80 dark:text-white/80">{{ $movement->counterparty ?: '—' }}</td>
                                    <td class="py-2 pr-3 text-right {{ $signed < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400' }}">
                                        <span class="block text-[10px] font-medium uppercase tracking-wide text-black/50 dark:text-white/50 sm:inline sm:mr-1">{{ $qtyLabel }}</span>
                                        @php $qtySuffix = $movement->quantityDisplaySuffix(); @endphp
                                        @include('materials.partials.balance-quantity-cell', ['quantity' => $signed, 'unitCode' => $qtySuffix, 'measurementTypeCode' => $utc])
                                    </td>
                                    <td class="py-2 max-w-[18rem] text-xs text-black/80 dark:text-white/80 break-words">{{ $commentDisplay ? \Illuminate\Support\Str::limit($commentDisplay, 160) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-4">
                {{ $movements->links() }}
            </div>
        </div>
    </div>
    @include('partials.js-filterable-select', [
        'searchInputId' => 'warehouse_filter_journal_search',
        'selectInputId' => 'warehouse_filter_journal',
        'preserveSelection' => false,
    ])
</x-app-layout>
