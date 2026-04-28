<x-app-layout>
    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4 sm:space-y-6">
            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-8">
                    <h2 class="text-xl sm:text-2xl font-semibold text-black dark:text-white mb-3">
                        Централизованный контроль заявок и материалов
                    </h2>
                    <p class="text-black dark:text-white max-w-3xl opacity-90">
                        КТ-Ресурс помогает подразделениям создавать и согласовывать заявки, контролировать статус позиций
                        и вести прозрачный процесс закупки оборудования в едином интерфейсе.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <h3 class="font-semibold text-black dark:text-white">Управление заявками</h3>
                        <p class="mt-2 text-sm text-black dark:text-white opacity-90">Создание, редактирование и контроль повторных заявок по подразделениям.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <h3 class="font-semibold text-black dark:text-white">Согласование и контроль</h3>
                        <p class="mt-2 text-sm text-black dark:text-white opacity-90">Отметки одобрения, причины отклонений и прозрачный процесс принятия решений.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <h3 class="font-semibold text-black dark:text-white">Ролевой доступ</h3>
                        <p class="mt-2 text-sm text-black dark:text-white opacity-90">Разделение прав по ролям: администратор, директор, мастер участка и другие.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <h3 class="font-semibold text-black dark:text-white">Остатки основного склада</h3>
                            <a href="{{ route('materials.overview') }}" class="text-xs text-stone-500 hover:text-stone-800 dark:text-stone-400 dark:hover:text-white">Все остатки</a>
                        </div>
                        @if($mainWarehouse)
                            <p class="text-xs opacity-70 mb-3">{{ $mainWarehouse->name }}</p>
                            <div class="space-y-2">
                                @forelse($mainWarehouseBalances as $row)
                                    <div class="flex items-start justify-between gap-3 text-sm">
                                        <div class="min-w-0">
                                            <p class="truncate">{{ $row->equipment_name }}</p>
                                            <p class="text-xs opacity-70">приход: {{ number_format((float) $row->qty_in, 3, '.', ' ') }} · расход: {{ number_format((float) $row->qty_out, 3, '.', ' ') }}</p>
                                        </div>
                                        <p class="tabular-nums whitespace-nowrap font-semibold">{{ number_format((float) $row->balance, 3, '.', ' ') }} {{ $row->unit_code }}</p>
                                    </div>
                                @empty
                                    <p class="text-sm opacity-80">По основному складу пока нет движений.</p>
                                @endforelse
                            </div>
                        @else
                            <p class="text-sm opacity-80">Основной склад не назначен.</p>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <h3 class="font-semibold text-black dark:text-white mb-3">Дефицитные позиции</h3>
                        <div class="space-y-2">
                            @forelse($deficitPositions as $row)
                                <div class="flex items-start justify-between gap-3 text-sm">
                                    <div class="min-w-0">
                                        <p class="truncate">{{ $row->equipment_name }}</p>
                                        <p class="text-xs text-red-700 dark:text-red-300">расход: {{ number_format((float) $row->qty_out, 3, '.', ' ') }}</p>
                                    </div>
                                    <p class="tabular-nums whitespace-nowrap font-semibold text-red-700 dark:text-red-300">{{ number_format((float) $row->balance, 3, '.', ' ') }} {{ $row->unit_code }}</p>
                                </div>
                            @empty
                                <p class="text-sm opacity-80">Дефицитных позиций нет.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm border border-stone-200 dark:border-stone-800 rounded-lg">
                    <div class="p-4 sm:p-6 text-black dark:text-white">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <h3 class="font-semibold text-black dark:text-white">Последние операции</h3>
                            <a href="{{ route('materials.index') }}" class="text-xs text-stone-500 hover:text-stone-800 dark:text-stone-400 dark:hover:text-white">Журнал</a>
                        </div>
                        <div class="space-y-2">
                            @forelse($latestOperations as $movement)
                                @php
                                    $signed = $movement->signedQuantity();
                                    $signedClass = $signed < 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300';
                                @endphp
                                <div class="flex items-start justify-between gap-3 text-sm">
                                    <div class="min-w-0">
                                        <p class="truncate">{{ $movement->equipment?->name ?? '—' }}</p>
                                        <p class="text-xs opacity-70">{{ $movement->created_at?->format('d.m.Y H:i') }} · {{ $movement->warehouse?->name ?? '—' }}</p>
                                    </div>
                                    <p class="tabular-nums whitespace-nowrap font-semibold {{ $signedClass }}">
                                        {{ number_format($signed, 3, '.', ' ') }} {{ $movement->equipment?->measurementUnit?->code ?? 'шт' }}
                                    </p>
                                </div>
                            @empty
                                <p class="text-sm opacity-80">Операций пока нет.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
