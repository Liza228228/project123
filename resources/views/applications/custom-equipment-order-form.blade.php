<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.custom-equipment-to-order')">Заявки к заказу</x-page-header-nav>
            <p class="text-sm">
                <a href="{{ route('applications.show', $application) }}" class="font-medium text-stone-700 underline decoration-orange-400/60 underline-offset-2 hover:text-orange-950 dark:text-stone-300 dark:hover:text-orange-100">
                    Заявка №{{ $application->id }}
                </a>
            </p>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Своё оборудование: заявка №{{ $application->id }}
            </h2>
            <p class="text-sm text-stone-600 dark:text-stone-400 max-w-2xl">
                Отметьте галочками позиции и нажмите «Заказано». Когда товар пришёл на основной склад — выберите строки во втором блоке и нажмите «На складе» (приход в «Материалы» и привязка к справочнику).
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-xl border border-stone-200/80 bg-stone-50/80 px-4 py-3 text-sm text-stone-900 dark:border-stone-700 dark:bg-stone-900/40 dark:text-stone-100">
                {{ session('status') }}
            </div>
        @endif
        @error('custom_supply')
            <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                {{ $message }}
            </div>
        @enderror
        @error('item_ids')
            <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                {{ $message }}
            </div>
        @enderror

        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-8">
                @if($toOrder->isEmpty() && $toWarehouse->isEmpty())
                    <p class="text-sm text-stone-600 dark:text-stone-400">
                        По этой заявке нет позиций со своим названием, требующих действий в этой форме (всё уже оприходовано или строки не согласованы).
                    </p>
                @else
                    @if($toOrder->isNotEmpty())
                        <section class="space-y-4" aria-labelledby="sec-order">
                            <h3 id="sec-order" class="app-section-title">1. Отметить как заказано у поставщика</h3>
                            <p class="text-xs text-black dark:text-white">
                                Выберите позиции (или «Выбрать все») и нажмите кнопку.
                            </p>
                            <form method="POST" action="{{ route('applications.custom-equipment-order.ordered', $application) }}" class="space-y-3">
                                @csrf
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm text-black dark:text-white cursor-pointer">
                                        <input type="checkbox" id="co-select-all-order" class="rounded border-stone-300 dark:border-stone-600" />
                                        <span>Выбрать все</span>
                                    </label>
                                </div>
                                <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600 overflow-hidden">
                                    @foreach($toOrder as $item)
                                        <li class="flex flex-wrap items-center gap-3 bg-white dark:bg-stone-950/60 px-3 py-2">
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="co-cb-order rounded border-stone-300 dark:border-stone-600" />
                                            <span class="text-sm text-black dark:text-white flex-1 min-w-0">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="flex justify-end">
                                    <button type="submit" class="ui-btn ui-btn--primary">Заказано</button>
                                </div>
                            </form>
                            <script>
                                (function () {
                                    var master = document.getElementById('co-select-all-order');
                                    if (!master) return;
                                    master.addEventListener('change', function () {
                                        document.querySelectorAll('.co-cb-order').forEach(function (cb) { cb.checked = master.checked; });
                                    });
                                })();
                            </script>
                        </section>
                    @endif

                    @if($toWarehouse->isNotEmpty())
                        <section class="space-y-4 border-t border-stone-200 pt-8 dark:border-stone-700" aria-labelledby="sec-wh">
                            <h3 id="sec-wh" class="app-section-title">2. Приход на основной склад</h3>
                            <p class="text-xs text-black dark:text-white">
                                Для позиций уже в статусе «Заказано» (или «В пути»): отметьте строки и нажмите — будет создан приход на основной склад организации.
                            </p>
                            <form method="POST" action="{{ route('applications.custom-equipment-order.on-warehouse', $application) }}" class="space-y-3">
                                @csrf
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm text-black dark:text-white cursor-pointer">
                                        <input type="checkbox" id="co-select-all-wh" class="rounded border-stone-300 dark:border-stone-600" />
                                        <span>Выбрать все</span>
                                    </label>
                                </div>
                                <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600 overflow-hidden">
                                    @foreach($toWarehouse as $item)
                                        <li class="flex flex-wrap items-center gap-3 bg-white dark:bg-stone-950/60 px-3 py-2">
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="co-cb-wh rounded border-stone-300 dark:border-stone-600" />
                                            <span class="text-sm text-black dark:text-white flex-1 min-w-0">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="flex justify-end">
                                    <button type="submit" class="ui-btn ui-btn--primary">На складе</button>
                                </div>
                            </form>
                            <script>
                                (function () {
                                    var master = document.getElementById('co-select-all-wh');
                                    if (!master) return;
                                    master.addEventListener('change', function () {
                                        document.querySelectorAll('.co-cb-wh').forEach(function (cb) { cb.checked = master.checked; });
                                    });
                                })();
                            </script>
                        </section>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
