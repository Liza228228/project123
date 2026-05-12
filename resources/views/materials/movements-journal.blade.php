@php
    $quantityColumnHeader = 'Количество / маркировка';
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
                @if(auth()->user()?->hasAnyRoleId([1, 6, 2, 3]))
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
                        <select id="warehouse_filter_journal" name="warehouse_id" class="app-select w-full min-w-0 sm:max-w-xs">
                            <option value="">Все склады</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
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

            <p class="mt-2 text-xs text-black/70 dark:text-white/70">
                В колонке «{{ $quantityColumnHeader }}» для строки показан тип учёта (штуки, масса, длина, размер) и числовое значение; для спецодежды в скобках — размер прихода.
            </p>

            @if($movements->isEmpty())
                <p class="mt-4 rounded-xl border border-dashed border-stone-300 px-4 py-6 text-center text-sm text-black/70 dark:border-stone-600 dark:text-white/70">
                    Операций пока нет.
                </p>
            @else
                <div class="mt-4 md:hidden app-card-list">
                    @foreach($movements as $movement)
                        @php
                            $signed = $movement->signedQuantity();
                            $unitCode = $movement->equipment?->measurementUnit?->code ?? 'шт';
                            $utc = (string) ($movement->equipment?->measurementUnit?->unitType?->code ?? '');
                            $qtyLabel = match ($utc) {
                                'clothing_size' => 'Размер',
                                'length' => 'Длина',
                                'mass' => 'Масса',
                                'piece' => 'Штуки',
                                default => 'Кол-во',
                            };
                            $signedClass = $signed < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-300';
                        @endphp
                        <article class="app-card-list__item">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-medium text-black dark:text-white app-equipment-line">{{ $movement->equipment?->name ?? '—' }}</p>
                                <div class="text-right">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-black/50 dark:text-white/50">{{ $qtyLabel }}</p>
                                    <p class="tabular-nums text-sm font-semibold {{ $signedClass }}">{{ number_format($signed, 3, '.', ' ') }} {{ $unitCode }}@if($movement->receipt_variant) <span class="font-normal text-black/65 dark:text-white/60">({{ $movement->receipt_variant }})</span>@endif</p>
                                </div>
                            </div>
                            <p class="text-xs text-black/70 dark:text-white/65">
                                {{ $movement->created_at?->format('d.m.Y H:i') }} · {{ $movement->warehouse?->name ?? '—' }}
                            </p>
                            <p class="text-xs text-black/80 dark:text-white/80">
                                <span class="font-medium">{{ $movement->movementType?->name ?? '—' }}</span>
                                @if($movement->counterparty)
                                    · {{ $movement->counterparty }}
                                @endif
                            </p>
                            @if($movement->comment)
                                <p class="text-xs text-black/70 dark:text-white/65 break-words">{{ \Illuminate\Support\Str::limit($movement->comment, 160) }}</p>
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
                                <th class="text-left py-2 pr-3">Тип</th>
                                <th class="text-left py-2 pr-3">Контрагент</th>
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
                                @endphp
                                <tr class="border-b border-stone-100 dark:border-stone-700/60">
                                    <td class="py-2 pr-3">{{ $movement->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="py-2 pr-3">{{ $movement->equipment?->name ?? '—' }} @if($movement->equipment) ({{ $movement->equipment->measurementUnit?->code ?? 'шт' }}) @endif</td>
                                    <td class="py-2 pr-3">{{ $movement->warehouse?->name }}</td>
                                    <td class="py-2 pr-3">{{ $movement->movementType?->name ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-xs text-black/80 dark:text-white/80">{{ $movement->counterparty ?: '—' }}</td>
                                    <td class="py-2 pr-3 text-right {{ $signed < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400' }}">
                                        <span class="block text-[10px] font-medium uppercase tracking-wide text-black/50 dark:text-white/50 sm:inline sm:mr-1">{{ $qtyLabel }}</span>
                                        <span class="tabular-nums">{{ number_format($signed, 3, '.', ' ') }}@if($movement->receipt_variant) <span class="text-black/70 dark:text-white/65">({{ $movement->receipt_variant }})</span>@endif</span>
                                    </td>
                                    <td class="py-2 max-w-[18rem] text-xs text-black/80 dark:text-white/80 break-words">{{ $movement->comment ? \Illuminate\Support\Str::limit($movement->comment, 160) : '—' }}</td>
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
</x-app-layout>
