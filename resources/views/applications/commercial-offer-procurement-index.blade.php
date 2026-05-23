<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.index')">Заявки</x-page-header-nav>
            <div class="min-w-0 space-y-1">
                <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                    Закупка по КП
                </h2>
                <p class="text-sm text-stone-600 dark:text-stone-400 max-w-2xl">
                    Заявки с согласованным коммерческим предложением. Откройте форму, оформите заказ у поставщика и отметьте приход на склад.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[96rem] px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-4">
                @if($errors->has('commercial_offer_procurement'))
                    <x-app-alert type="error">{{ $errors->first('commercial_offer_procurement') }}</x-app-alert>
                @endif
                @if($applications->isEmpty())
                    <p class="text-sm text-stone-600 dark:text-stone-400">
                        Нет заявок с согласованным коммерческим предложением, ожидающих закупки.
                    </p>
                @else
                    <form method="GET" action="{{ route('applications.commercial-offer-procurement') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between" data-auto-submit="filter">
                        <div class="w-full sm:w-auto">
                            <label for="sort_date" class="app-form-label">Сортировка по дате</label>
                            <select id="sort_date" name="sort_date" class="app-select min-w-[16rem]">
                                <option value="desc" @selected(($sortDate ?? 'desc') === 'desc')>Сначала новые заявки</option>
                                <option value="asc" @selected(($sortDate ?? 'desc') === 'asc')>Сначала старые заявки</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="co-procurement-per-page" class="app-form-label">На странице</label>
                            <select id="co-procurement-per-page" name="per_page" class="app-select min-w-[10rem]">
                                @foreach($allowedPerPage as $size)
                                    <option value="{{ $size }}" @selected((int) ($perPage ?? 0) === (int) $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                    <div class="md:hidden app-card-list">
                        @foreach($applications as $appRow)
                            <article class="app-card-list__item">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-sm font-semibold text-black dark:text-white">№{{ $appRow->id }}</p>
                                    <p class="text-xs text-black/65 dark:text-white/65">{{ $appRow->created_at?->format('d.m.Y H:i') ?? '—' }}</p>
                                </div>
                                <div class="grid grid-cols-1 gap-1 text-sm text-black dark:text-white">
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Подразделение</p>
                                        <p class="break-words">{{ $appRow->subdivision->name ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Статус закупки</p>
                                        <p>{{ $appRow->commercialOfferProcurementStatusLabel() }}</p>
                                    </div>
                                </div>
                                <a href="{{ route('applications.commercial-offer-procurement.show', $appRow) }}" class="ui-btn ui-btn--primary w-full justify-center">
                                    Закупка по КП
                                </a>
                            </article>
                        @endforeach
                    </div>
                    <div class="hidden md:block app-table-shell">
                        <table>
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Заявка</th>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Дата</th>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Подразделение</th>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Ответственный</th>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Закупка</th>
                                    <th class="px-3 py-2 text-right text-black dark:text-white"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($applications as $appRow)
                                    <tr>
                                        <td class="px-3 py-2 align-top text-black dark:text-white font-medium">№{{ $appRow->id }}</td>
                                        <td class="px-3 py-2 align-top text-black dark:text-white whitespace-nowrap">
                                            {{ $appRow->created_at?->format('d.m.Y H:i') ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 align-top text-black dark:text-white">
                                            {{ $appRow->subdivision->name ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 align-top text-black dark:text-white">
                                            @if($appRow->responsibleUser)
                                                {{ $appRow->responsibleUser->surname }} {{ $appRow->responsibleUser->name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-top text-black dark:text-white">
                                            {{ $appRow->commercialOfferProcurementStatusLabel() }}
                                        </td>
                                        <td class="px-3 py-2 align-top text-right">
                                            <a href="{{ route('applications.commercial-offer-procurement.show', $appRow) }}" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap">
                                                Открыть
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pt-2">
                        {{ $applications->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
