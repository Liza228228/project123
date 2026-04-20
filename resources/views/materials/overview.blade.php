<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
            Остатки по складам
        </h2>
    </x-slot>

    @php
        $usingDefaultMainWarehouse = (bool) ($usingDefaultMainWarehouse ?? false);
        $hasFilters = ! $usingDefaultMainWarehouse
            && ((int) ($selectedSubdivision?->id ?? 0) > 0 || (int) ($selectedWarehouse?->id ?? 0) > 0);
    @endphp

    <div class="py-2 sm:py-8 md:py-10 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-black/75 dark:text-white/75">
            Выберите подразделение и склад — ниже отобразятся приход, расход и остаток по каждой позиции оборудования, по которой на этом складе были движения.
        </p>

        <div class="rounded-2xl border border-stone-200/90 bg-white shadow-sm dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6 space-y-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-base font-semibold text-black dark:text-white">Просмотр склада</h3>
                @if($hasFilters)
                    <a
                        href="{{ route('materials.overview') }}"
                        class="text-sm font-medium text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white shrink-0"
                    >
                        Сбросить выбор
                    </a>
                @endif
            </div>

            @if($usingDefaultMainWarehouse && $selectedWarehouse)
                <p class="text-xs text-emerald-700 dark:text-emerald-200">
                    По умолчанию показан основной склад «{{ $selectedWarehouse->name }}» (Администрация).
                </p>
            @endif

            @if($subdivisions->isEmpty())
                <p class="text-sm text-black/70 dark:text-white/70">Подразделения для вашей учётной записи не найдены.</p>
            @else
            <form
                id="materials-overview-filters"
                method="get"
                action="{{ route('materials.overview') }}"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5"
            >
                <div>
                    <label for="overview_subdivision_id" class="app-form-label">Подразделение</label>
                    <select
                        id="overview_subdivision_id"
                        name="subdivision_id"
                        class="app-select mt-1.5 w-full"
                        autocomplete="organization"
                    >
                        <option value="">Выберите подразделение…</option>
                        @foreach($subdivisions as $subdivision)
                            <option value="{{ $subdivision->id }}" @selected((int) ($selectedSubdivision?->id ?? 0) === (int) $subdivision->id)>
                                {{ $subdivision->name }}@if((int) $subdivision->warehouses_count > 0) ({{ (int) $subdivision->warehouses_count }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="overview_warehouse_id" class="app-form-label">Склад</label>
                    <select
                        id="overview_warehouse_id"
                        name="warehouse_id"
                        class="app-select mt-1.5 w-full @if(!$selectedSubdivision || $warehouses->isEmpty()) opacity-60 @endif"
                        @if(!$selectedSubdivision || $warehouses->isEmpty()) disabled @endif
                    >
                        @if(!$selectedSubdivision)
                            <option value="">Сначала выберите подразделение</option>
                        @elseif($warehouses->isEmpty())
                            <option value="">В подразделении нет складов</option>
                        @else
                            <option value="">Выберите склад…</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((int) ($selectedWarehouse?->id ?? 0) === (int) $warehouse->id)>
                                    @if(filled(trim((string) ($warehouse->code ?? ''))))
                                        {{ $warehouse->code }} —
                                    @endif
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </form>
            @endif
        </div>

        @if($selectedWarehouse)
            <div class="rounded-2xl border border-stone-200/90 bg-white shadow-sm dark:border-stone-700 dark:bg-stone-800/90 p-5 sm:p-6 space-y-4">
                <div class="flex flex-col gap-1 border-b border-stone-200/80 pb-4 dark:border-stone-600/80">
                    <p class="text-xs font-semibold uppercase tracking-wide text-black/50 dark:text-white/50">Остатки</p>
                    <h3 class="text-lg font-semibold text-black dark:text-white leading-snug">
                        {{ $selectedSubdivision?->name }}
                        <span class="font-normal text-black/45 dark:text-white/45">·</span>
                        {{ $selectedWarehouse->name }}
                    </h3>
                    <p class="text-xs text-black/65 dark:text-white/65">
                        Учитываются все операции на этом складе; полностью списанные позиции тоже видны в таблице (остаток 0).
                    </p>
                </div>

                <div class="md:hidden space-y-3">
                    @forelse($equipmentBalances as $row)
                        @php
                            $qtyIn = (float) ($row->qty_in ?? 0);
                            $qtyOut = (float) ($row->qty_out ?? 0);
                            $balance = (float) ($row->balance ?? 0);
                        @endphp
                        <article class="rounded-xl border border-stone-200/90 bg-stone-50/40 px-4 py-3 dark:border-stone-600 dark:bg-stone-900/40">
                            <p class="text-sm font-medium text-black dark:text-white leading-snug">
                                {{ $row->equipment_name }}
                                <span class="text-xs font-normal text-black/55 dark:text-white/55">({{ $row->unit_code }})</span>
                            </p>
                            <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded-lg bg-white/90 px-2 py-2 dark:bg-stone-800/80">
                                    <dt class="font-medium uppercase tracking-wide text-black/50 dark:text-white/50">Приход</dt>
                                    <dd class="mt-1 tabular-nums text-sm font-medium text-black dark:text-white">{{ number_format($qtyIn, 3, '.', ' ') }}</dd>
                                </div>
                                <div class="rounded-lg bg-white/90 px-2 py-2 dark:bg-stone-800/80">
                                    <dt class="font-medium uppercase tracking-wide text-black/50 dark:text-white/50">Списано</dt>
                                    <dd class="mt-1 tabular-nums text-sm font-medium text-red-700 dark:text-red-300/90">{{ number_format($qtyOut, 3, '.', ' ') }}</dd>
                                </div>
                                <div class="rounded-lg bg-emerald-50/90 px-2 py-2 dark:bg-emerald-950/35">
                                    <dt class="font-medium uppercase tracking-wide text-emerald-800/80 dark:text-emerald-200/70">Остаток</dt>
                                    <dd class="mt-1 tabular-nums text-sm font-semibold @if(abs($balance) < 0.0005 && $qtyOut > 0.0005) text-black/50 dark:text-white/50 @else text-emerald-900 dark:text-emerald-100 @endif">{{ number_format($balance, 3, '.', ' ') }}</dd>
                                </div>
                            </dl>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-stone-300 px-4 py-8 text-center text-sm text-black/65 dark:border-stone-600 dark:text-white/65">
                            По этому складу ещё не было движений оборудования.
                        </p>
                    @endforelse
                </div>

                <div class="hidden md:block app-table-shell">
                    <table class="min-w-full text-sm text-black dark:text-white">
                        <thead>
                            <tr>
                                <th class="text-left py-3 px-4">Оборудование</th>
                                <th class="text-right py-3 px-4">Приход</th>
                                <th class="text-right py-3 px-4">Списано</th>
                                <th class="text-right py-3 px-4">Остаток</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($equipmentBalances as $row)
                                @php
                                    $qtyIn = (float) ($row->qty_in ?? 0);
                                    $qtyOut = (float) ($row->qty_out ?? 0);
                                    $balance = (float) ($row->balance ?? 0);
                                @endphp
                                <tr>
                                    <td class="py-3 px-4">
                                        {{ $row->equipment_name }}
                                        <span class="text-black/55 dark:text-white/55">({{ $row->unit_code }})</span>
                                    </td>
                                    <td class="py-3 px-4 text-right tabular-nums">{{ number_format($qtyIn, 3, '.', ' ') }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-red-700 dark:text-red-300/90">{{ number_format($qtyOut, 3, '.', ' ') }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums font-semibold @if(abs($balance) < 0.0005 && $qtyOut > 0.0005) text-black/55 dark:text-white/55 @endif">{{ number_format($balance, 3, '.', ' ') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 px-4 text-center text-black/65 dark:text-white/65">
                                        По этому складу ещё не было движений оборудования.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif($selectedSubdivision && $warehouses->isNotEmpty())
            <div class="rounded-xl border border-dashed border-stone-300 bg-stone-50/50 px-4 py-6 text-center text-sm text-black/70 dark:border-stone-600 dark:bg-stone-900/30 dark:text-white/70">
                Выберите склад в списке выше — здесь появятся остатки.
            </div>
        @endif
    </div>

    @once
        <script>
            (function () {
                var form = document.getElementById('materials-overview-filters');
                var sub = document.getElementById('overview_subdivision_id');
                var wh = document.getElementById('overview_warehouse_id');
                if (!form || !sub || !wh) {
                    return;
                }
                sub.addEventListener('change', function () {
                    wh.value = '';
                    form.submit();
                });
                wh.addEventListener('change', function () {
                    if (wh.disabled) {
                        return;
                    }
                    form.submit();
                });
            })();
        </script>
    @endonce
</x-app-layout>
