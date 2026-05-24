<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.commercial-offer-procurement')">Закупка по КП</x-page-header-nav>
            <p class="text-sm">
                <a href="{{ route('applications.show', $application) }}" class="font-medium text-stone-700 underline decoration-orange-400/60 underline-offset-2 hover:text-orange-950 dark:text-stone-300 dark:hover:text-orange-100">
                    Заявка №{{ $application->id }}
                </a>
            </p>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Закупка по КП: заявка №{{ $application->id }}
            </h2>
            <p class="text-sm text-stone-600 dark:text-stone-400 max-w-2xl">
                {{ $application->subdivision->name ?? '—' }}
                @if($application->responsibleUser)
                    · ответственный: {{ $application->responsibleUser->surname }} {{ $application->responsibleUser->name }}
                @endif
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8 space-y-4">
        @error('custom_supply')
            <x-app-alert type="error" class="mb-4">{{ $message }}</x-app-alert>
        @enderror
        @error('item_ids')
            <x-app-alert type="error" class="mb-4">{{ $message }}</x-app-alert>
        @enderror

        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-8">
                <div class="rounded-xl border border-stone-200/90 bg-stone-50/80 p-4 dark:border-stone-600 dark:bg-stone-800/35 space-y-3">
                    <p class="text-sm text-black dark:text-white">
                        <span class="font-medium">Статус:</span> {{ $application->commercialOfferProcurementStatusLabel() }}
                    </p>
                    @if($application->desired_delivery_date)
                        <p class="text-sm text-black dark:text-white">
                            <span class="font-medium">Желаемая дата поставки:</span>
                            {{ $application->desired_delivery_date->format('d.m.Y') }}
                        </p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('applications.commercial-offer.download', $application) }}" class="ui-btn ui-btn--primary ui-btn--sm" target="_blank" rel="noopener">
                            Открыть КП (PDF)
                        </a>
                        <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--secondary ui-btn--sm">
                            Карточка заявки
                        </a>
                    </div>
                </div>

                @if($coLines !== [])
                    <section class="space-y-3" aria-labelledby="co-proc-table">
                        <h3 id="co-proc-table" class="app-section-title">Оборудование по коммерческому предложению</h3>
                        <p class="text-xs text-black dark:text-white">
                            Данные из таблицы КП: наименование, количество и единица измерения. Ниже отметьте заказ и приход на склад по каждой позиции.
                        </p>
                        <div class="overflow-x-auto rounded-xl border border-stone-200/90 dark:border-stone-600">
                            <table class="min-w-full text-sm text-left">
                                <thead class="bg-stone-100/90 dark:bg-stone-800/80 text-black dark:text-white">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">Наименование</th>
                                        <th class="px-3 py-2 font-medium whitespace-nowrap">Кол-во</th>
                                        <th class="px-3 py-2 font-medium whitespace-nowrap">Ед. изм.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-200 dark:divide-stone-700 bg-white dark:bg-stone-950/60">
                                    @foreach($coLines as $line)
                                        <tr>
                                            <td class="px-3 py-2 text-black dark:text-white app-equipment-line">{{ $line['equipment_name'] }}</td>
                                            <td class="px-3 py-2 text-black dark:text-white whitespace-nowrap">{{ $line['quantity'] }}</td>
                                            <td class="px-3 py-2 text-black dark:text-white whitespace-nowrap">{{ $line['quantity_unit'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @else
                    <p class="text-sm text-stone-600 dark:text-stone-400">
                        Таблица оборудования из макета КП не сохранена (например, загружен только PDF). Закупку ведите по файлу КП или заново заполните КП через макет в карточке заявки.
                    </p>
                @endif

                @if($toOrder->isEmpty() && $toWarehouse->isEmpty())
                    @if($coLines !== [])
                        <p class="text-sm text-stone-600 dark:text-stone-400">
                            Все позиции из таблицы КП уже оприходованы или ожидают следующего этапа вне этой формы.
                        </p>
                    @endif
                @else
                    @if($toOrder->isNotEmpty())
                        <section class="space-y-4" aria-labelledby="co-proc-order">
                            <h3 id="co-proc-order" class="app-section-title">Отметить заказанное оборудование </h3>
                            <form method="POST" action="{{ route('applications.custom-equipment-order.ordered', $application) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="return_to" value="commercial_offer_procurement" />
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm text-black dark:text-white cursor-pointer">
                                        <input type="checkbox" id="cop-select-all-order" class="rounded border-stone-300 dark:border-stone-600" />
                                        <span>Выбрать все</span>
                                    </label>
                                </div>
                                <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600 overflow-hidden">
                                    @foreach($toOrder as $item)
                                        <li class="flex flex-wrap items-start gap-3 bg-white dark:bg-stone-950/60 px-3 py-2">
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="cop-cb-order mt-0.5 h-5 w-5 shrink-0 rounded border-stone-300 dark:border-stone-600" />
                                            <span class="text-sm text-black dark:text-white flex-1 min-w-0 app-equipment-line">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="flex sm:justify-end">
                                    <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto">Заказано</button>
                                </div>
                            </form>
                            <script>
                                (function () {
                                    var master = document.getElementById('cop-select-all-order');
                                    if (!master) return;
                                    master.addEventListener('change', function () {
                                        document.querySelectorAll('.cop-cb-order').forEach(function (cb) { cb.checked = master.checked; });
                                    });
                                })();
                            </script>
                        </section>
                    @endif

                    @if($toWarehouse->isNotEmpty())
                        <section class="space-y-4 border-t border-stone-200 pt-8 dark:border-stone-700" aria-labelledby="co-proc-wh">
                            <h3 id="co-proc-wh" class="app-section-title">Приход на основной склад</h3>
                            <form method="POST" action="{{ route('applications.custom-equipment-order.on-warehouse', $application) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="return_to" value="commercial_offer_procurement" />
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm text-black dark:text-white cursor-pointer">
                                        <input type="checkbox" id="cop-select-all-wh" class="rounded border-stone-300 dark:border-stone-600" />
                                        <span>Выбрать все</span>
                                    </label>
                                </div>
                                <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600 overflow-hidden">
                                    @foreach($toWarehouse as $item)
                                        <li class="flex flex-wrap items-start gap-3 bg-white dark:bg-stone-950/60 px-3 py-2">
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="cop-cb-wh mt-0.5 h-5 w-5 shrink-0 rounded border-stone-300 dark:border-stone-600" />
                                            <span class="text-sm text-black dark:text-white flex-1 min-w-0 app-equipment-line">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="flex sm:justify-end">
                                    <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto">На складе</button>
                                </div>
                            </form>
                            <script>
                                (function () {
                                    var master = document.getElementById('cop-select-all-wh');
                                    if (!master) return;
                                    master.addEventListener('change', function () {
                                        document.querySelectorAll('.cop-cb-wh').forEach(function (cb) { cb.checked = master.checked; });
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
