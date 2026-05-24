@props([
    'balances',
    'heading' => null,
    'intro' => null,
    'emptyText' => 'Нет позиций.',
    'equipmentSearch' => '',
])

@php
    $isPaginator = $balances instanceof \Illuminate\Contracts\Pagination\Paginator;
@endphp

<div class="space-y-4">
    @if(filled($heading) || filled($intro))
        <div>
            @if(filled($heading))
                <h4 class="text-sm font-semibold tracking-tight text-black dark:text-white">{{ $heading }}</h4>
            @endif
            @if(filled($intro))
                <p @class(['text-xs leading-snug text-black/65 dark:text-white/65', 'mt-1' => filled($heading)])>{{ $intro }}</p>
            @endif
        </div>
    @endif

    <div class="md:hidden space-y-3">
        @forelse($balances as $row)
            @php
                $qtyIn = (float) ($row->qty_in ?? 0);
                $qtyOut = (float) ($row->qty_out ?? 0);
                $balance = (float) ($row->balance ?? 0);
                $unitCode = trim((string) ($row->unit_code ?? '')) ?: 'шт';
                $measurementTypeCode = trim((string) ($row->measurement_type_code ?? ''));
            @endphp
            <article class="rounded-xl border border-stone-200/90 bg-stone-50/40 px-4 py-3 dark:border-stone-600 dark:bg-stone-900/40">
                <p class="text-sm font-medium text-black dark:text-white leading-snug">
                    @include('materials.partials.balance-equipment-title', [
                        'equipmentName' => $row->equipment_name,
                        'unitCode' => $unitCode,
                        'measurementTypeCode' => $measurementTypeCode,
                    ])
                </p>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-lg bg-white/90 px-2 py-2 dark:bg-stone-800/80">
                        <dt class="font-medium uppercase tracking-wide text-black/50 dark:text-white/50">Приход</dt>
                        <dd class="mt-1 text-sm font-medium text-black dark:text-white">
                            @include('materials.partials.balance-quantity-cell', ['quantity' => $qtyIn, 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                        </dd>
                    </div>
                    <div class="rounded-lg bg-emerald-50/90 px-2 py-2 dark:bg-emerald-950/35">
                        <dt class="font-medium uppercase tracking-wide text-emerald-800/80 dark:text-emerald-200/70">Остаток</dt>
                        <dd class="mt-1 text-sm font-semibold @if(abs($balance) < 0.0005 && $qtyOut > 0.0005) text-black/50 dark:text-white/50 @else text-emerald-900 dark:text-emerald-100 @endif">
                            @include('materials.partials.balance-quantity-cell', ['quantity' => $balance, 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/90 px-2 py-2 dark:bg-stone-800/80">
                        <dt class="font-medium uppercase tracking-wide text-black/50 dark:text-white/50">Списано</dt>
                        <dd class="mt-1 text-sm font-medium text-red-700 dark:text-red-300/90">
                            @include('materials.partials.balance-quantity-cell', ['quantity' => $qtyOut, 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                        </dd>
                    </div>
                </dl>
            </article>
        @empty
            <p class="text-sm text-black/65 dark:text-white/65 py-2">
                @if(filled($equipmentSearch))
                    По запросу «{{ $equipmentSearch }}» ничего не найдено.
                @else
                    {{ $emptyText }}
                @endif
            </p>
        @endforelse
    </div>

    <div class="hidden md:block app-table-shell overflow-x-auto">
        <table class="min-w-full text-sm text-black dark:text-white">
            <thead>
                <tr>
                    <th class="text-left py-3 px-4">Оборудование</th>
                    <th class="text-right py-3 px-4">Приход</th>
                    <th class="text-right py-3 px-4">Остаток</th>
                    <th class="text-right py-3 px-4">Списано</th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances as $row)
                    @php
                        $qtyIn = (float) ($row->qty_in ?? 0);
                        $qtyOut = (float) ($row->qty_out ?? 0);
                        $balance = (float) ($row->balance ?? 0);
                        $unitCode = trim((string) ($row->unit_code ?? '')) ?: 'шт';
                        $measurementTypeCode = trim((string) ($row->measurement_type_code ?? ''));
                    @endphp
                    <tr>
                        <td class="py-3 px-4">
                            @include('materials.partials.balance-equipment-title', [
                                'equipmentName' => $row->equipment_name,
                                'unitCode' => $unitCode,
                                'measurementTypeCode' => $measurementTypeCode,
                            ])
                        </td>
                        <td class="py-3 px-4 text-right">
                            @include('materials.partials.balance-quantity-cell', ['quantity' => $qtyIn, 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                        </td>
                        <td class="py-3 px-4 text-right font-semibold @if(abs($balance) < 0.0005 && $qtyOut > 0.0005) text-black/55 dark:text-white/55 @endif">
                            @include('materials.partials.balance-quantity-cell', ['quantity' => $balance, 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                        </td>
                        <td class="py-3 px-4 text-right text-red-700 dark:text-red-300/90">
                            @include('materials.partials.balance-quantity-cell', ['quantity' => $qtyOut, 'unitCode' => $unitCode, 'measurementTypeCode' => $measurementTypeCode])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 px-4 text-sm text-black/65 dark:text-white/65">
                            @if(filled($equipmentSearch))
                                По запросу «{{ $equipmentSearch }}» ничего не найдено.
                            @else
                                {{ $emptyText }}
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($isPaginator && $balances->hasPages())
        <div class="border-t border-stone-200/80 pt-4 dark:border-stone-600/80">
            {{ $balances->links() }}
        </div>
    @endif
</div>
