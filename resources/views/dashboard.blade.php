<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight">
            Аналитика
        </h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-0 py-2 sm:px-6 sm:py-6 lg:px-8">
        @if($applicationAnalytics !== null)
            <section aria-labelledby="dash-applications-heading" class="rounded-2xl border border-orange-200/90 bg-gradient-to-br from-orange-50/95 via-white to-amber-50/40 p-5 shadow-md shadow-orange-950/[0.06] ring-1 ring-orange-100/80 dark:border-orange-900/55 dark:from-orange-950/50 dark:via-stone-950 dark:to-stone-950 dark:shadow-black/30 dark:ring-orange-950/40 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <h2 id="dash-applications-heading" class="text-lg font-semibold text-stone-900 dark:text-white sm:text-xl">
                            Заявки
                        </h2>
                        <p class="max-w-2xl text-sm text-stone-600 dark:text-stone-300">
                            Активные заявки в вашей зоне видимости по статусу в системе. Нажмите на блок или кнопку, чтобы открыть список с фильтром.
                        </p>
                    </div>
                    <div class="flex flex-shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                        <a href="{{ route('applications.index') }}" class="ui-btn ui-btn--primary ui-btn--sm justify-center sm:justify-center">
                            Все заявки
                        </a>
                        <a href="{{ route('applications.archive') }}" class="ui-btn ui-btn--secondary ui-btn--sm justify-center">
                            Архив выполненных
                        </a>
                    </div>
                </div>

                @if(($applicationAnalytics['custom_equipment_pending'] ?? 0) > 0 && Auth::user()?->hasAnyRoleId(\App\Models\User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS))
                    <div class="mt-4 rounded-xl border border-amber-300/70 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/35 dark:text-amber-100">
                        <span class="font-medium">Своё оборудование к заказу:</span>
                        {{ (int) $applicationAnalytics['custom_equipment_pending'] }} поз.
                        <a href="{{ route('applications.custom-equipment-to-order') }}" class="ms-1 font-medium text-orange-800 underline decoration-orange-400/80 underline-offset-2 hover:text-orange-950 dark:text-orange-200 dark:hover:text-white">Перейти</a>
                    </div>
                @endif

                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <a href="{{ route('applications.index', ['archive' => 'active']) }}" class="group flex flex-col rounded-xl border border-stone-200/90 bg-white/90 p-4 shadow-sm transition hover:border-orange-300/90 hover:shadow-md dark:border-stone-700 dark:bg-stone-900/60 dark:hover:border-orange-700/70">
                        <span class="text-2xl font-bold tabular-nums text-stone-900 dark:text-white sm:text-3xl">{{ (int) $applicationAnalytics['total_active'] }}</span>
                        <span class="mt-1 text-xs font-medium text-stone-600 dark:text-stone-300">Всего активных</span>
                        <span class="mt-auto pt-2 text-[11px] font-medium text-orange-700 opacity-0 transition group-hover:opacity-100 dark:text-orange-300">Открыть →</span>
                    </a>
                    <a href="{{ route('applications.index', ['equipment_filter' => 'on_approval', 'archive' => 'active']) }}" class="group flex flex-col rounded-xl border border-sky-200/90 bg-sky-50/80 p-4 shadow-sm transition hover:border-sky-400/80 hover:shadow-md dark:border-sky-900/50 dark:bg-sky-950/40 dark:hover:border-sky-600/60">
                        <span class="text-2xl font-bold tabular-nums text-sky-950 dark:text-sky-100 sm:text-3xl">{{ (int) $applicationAnalytics['pending'] }}</span>
                        <span class="mt-1 text-xs font-medium text-sky-800/90 dark:text-sky-200/90">На согласовании</span>
                        <span class="mt-auto pt-2 text-[11px] font-medium text-sky-800 opacity-0 transition group-hover:opacity-100 dark:text-sky-200">Фильтр →</span>
                    </a>
                    <a href="{{ route('applications.index', ['equipment_filter' => 'fully_approved', 'archive' => 'active']) }}" class="group flex flex-col rounded-xl border border-emerald-200/90 bg-emerald-50/80 p-4 shadow-sm transition hover:border-emerald-400/80 hover:shadow-md dark:border-emerald-900/45 dark:bg-emerald-950/35 dark:hover:border-emerald-600/55">
                        <span class="text-2xl font-bold tabular-nums text-emerald-950 dark:text-emerald-100 sm:text-3xl">{{ (int) $applicationAnalytics['approved'] }}</span>
                        <span class="mt-1 text-xs font-medium text-emerald-900/90 dark:text-emerald-200/90">Согласованы</span>
                        <span class="mt-auto pt-2 text-[11px] font-medium text-emerald-800 opacity-0 transition group-hover:opacity-100 dark:text-emerald-200">Фильтр →</span>
                    </a>
                    <a href="{{ route('applications.index', ['archive' => 'active']) }}" class="group flex flex-col rounded-xl border border-amber-200/90 bg-amber-50/80 p-4 shadow-sm transition hover:border-amber-400/80 hover:shadow-md dark:border-amber-900/45 dark:bg-amber-950/35 dark:hover:border-amber-600/55">
                        <span class="text-2xl font-bold tabular-nums text-amber-950 dark:text-amber-100 sm:text-3xl">{{ (int) $applicationAnalytics['partial'] }}</span>
                        <span class="mt-1 text-xs font-medium text-amber-900/90 dark:text-amber-200/90">Частично согласованы</span>
                        <span class="mt-auto pt-2 text-[11px] font-medium text-amber-900 opacity-0 transition group-hover:opacity-100 dark:text-amber-200">К списку →</span>
                    </a>
                    <a href="{{ route('applications.index', ['equipment_filter' => 'has_not_approved', 'archive' => 'active']) }}" class="group flex flex-col rounded-xl border border-rose-200/90 bg-rose-50/80 p-4 shadow-sm transition hover:border-rose-400/80 hover:shadow-md dark:border-rose-900/45 dark:bg-rose-950/35 dark:hover:border-rose-600/55">
                        <span class="text-2xl font-bold tabular-nums text-rose-950 dark:text-rose-100 sm:text-3xl">{{ (int) $applicationAnalytics['rejected'] }}</span>
                        <span class="mt-1 text-xs font-medium text-rose-900/90 dark:text-rose-200/90">Не согласованы</span>
                        <span class="mt-auto pt-2 text-[11px] font-medium text-rose-800 opacity-0 transition group-hover:opacity-100 dark:text-rose-200">Фильтр →</span>
                    </a>
                    <a href="{{ route('applications.archive') }}" class="group flex flex-col rounded-xl border border-stone-200/90 bg-stone-50/90 p-4 shadow-sm transition hover:border-stone-400/80 hover:shadow-md dark:border-stone-600 dark:bg-stone-900/70 dark:hover:border-stone-500">
                        <span class="text-2xl font-bold tabular-nums text-stone-800 dark:text-stone-100 sm:text-3xl">{{ (int) $applicationAnalytics['archived'] }}</span>
                        <span class="mt-1 text-xs font-medium text-stone-600 dark:text-stone-300">В архиве</span>
                        <span class="mt-auto pt-2 text-[11px] font-medium text-stone-600 opacity-0 transition group-hover:opacity-100 dark:text-stone-300">Архив →</span>
                    </a>
                </div>
            </section>
        @endif

        <section aria-labelledby="dash-warehouse-heading">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="dash-warehouse-heading" class="text-lg font-semibold text-stone-900 dark:text-white sm:text-xl">
                        Склад и движения
                    </h2>
                    <p class="text-sm text-stone-600 dark:text-stone-300">Основной склад и последние операции по учёту.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('materials.overview') }}" class="ui-btn ui-btn--secondary ui-btn--sm">
                        Все остатки
                    </a>
                    @if($canAccessMaterialsJournal ?? false)
                        <a href="{{ route('materials.index') }}" class="ui-btn ui-btn--secondary ui-btn--sm">
                            Журнал операций
                        </a>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-orange-200/85 bg-orange-50/50 p-5 shadow-sm dark:border-orange-900/50 dark:bg-stone-950/80 sm:p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-stone-700 dark:text-stone-200">Остатки основного склада</h3>
                    @if($mainWarehouse)
                        <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">{{ $mainWarehouse->name }}</p>
                        <div class="mt-4 space-y-2.5">
                            @forelse($mainWarehouseBalances as $row)
                                <div class="flex items-start justify-between gap-3 rounded-lg border border-orange-100/80 bg-white/70 px-3 py-2 text-sm dark:border-orange-900/40 dark:bg-stone-900/50">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-stone-900 dark:text-stone-100">{{ $row->equipment_name }}</p>
                                        <p class="text-xs text-stone-500 dark:text-stone-400">приход: {{ number_format((float) $row->qty_in, 3, '.', ' ') }} · расход: {{ number_format((float) $row->qty_out, 3, '.', ' ') }}</p>
                                    </div>
                                    <p class="tabular-nums text-sm font-semibold text-stone-900 dark:text-white">{{ number_format((float) $row->balance, 3, '.', ' ') }} {{ $row->unit_code }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-stone-600 dark:text-stone-400">По основному складу пока нет движений.</p>
                            @endforelse
                        </div>
                    @else
                        <p class="mt-3 text-sm text-stone-600 dark:text-stone-400">Основной склад не назначен.</p>
                    @endif
                </div>

                <div class="rounded-xl border border-rose-200/70 bg-rose-50/40 p-5 shadow-sm dark:border-rose-900/40 dark:bg-rose-950/25 sm:p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-rose-900 dark:text-rose-200">Дефицитные позиции</h3>
                    <div class="mt-4 space-y-2.5">
                        @forelse($deficitPositions as $row)
                            <div class="flex items-start justify-between gap-3 rounded-lg border border-rose-100/90 bg-white/80 px-3 py-2 text-sm dark:border-rose-900/35 dark:bg-stone-900/50">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-stone-900 dark:text-stone-100">{{ $row->equipment_name }}</p>
                                    <p class="text-xs text-rose-700 dark:text-rose-300">расход: {{ number_format((float) $row->qty_out, 3, '.', ' ') }}</p>
                                </div>
                                <p class="tabular-nums text-sm font-semibold text-rose-800 dark:text-rose-200">{{ number_format((float) $row->balance, 3, '.', ' ') }} {{ $row->unit_code }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-stone-600 dark:text-stone-400">Дефицитных позиций нет.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-orange-200/85 bg-orange-50/35 p-5 shadow-sm dark:border-orange-900/50 dark:bg-stone-950/80 sm:p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-stone-700 dark:text-stone-200">Последние операции</h3>
                    <div class="mt-4 space-y-2.5">
                        @forelse($latestOperations as $movement)
                            @php
                                $signed = $movement->signedQuantity();
                                $signedClass = $signed < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300';
                            @endphp
                            <div class="flex items-start justify-between gap-3 rounded-lg border border-orange-100/80 bg-white/70 px-3 py-2 text-sm dark:border-orange-900/40 dark:bg-stone-900/50">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-stone-900 dark:text-stone-100">{{ $movement->equipment?->name ?? '—' }}</p>
                                    <p class="text-xs text-stone-500 dark:text-stone-400">{{ $movement->created_at?->format('d.m.Y H:i') }} · {{ $movement->warehouse?->name ?? '—' }}</p>
                                </div>
                                <p class="tabular-nums text-sm font-semibold {{ $signedClass }}">
                                    {{ number_format($signed, 3, '.', ' ') }} {{ $movement->equipment?->measurementUnit?->code ?? 'шт' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-stone-600 dark:text-stone-400">Операций пока нет.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
