<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.index')">Заявки</x-page-header-nav>
            <div class="min-w-0 space-y-1">
                <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                    Своё оборудование к заказу
                </h2>
                <p class="text-sm text-stone-600 dark:text-stone-400 max-w-2xl">
                    Выберите заявку — откроется форма со всеми позициями со своим названием по этой заявке: что отметить как заказанное и что оприходовать на основной склад.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[96rem] px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8 space-y-4">
                @if($applications->isEmpty())
                    <p class="text-sm text-stone-600 dark:text-stone-400">
                        Нет заявок с незавершённым своим оборудованием (все позиции уже оприходованы или нет согласованных строк без справочника).
                    </p>
                @else
                    <form method="GET" action="{{ route('applications.custom-equipment-to-order') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div class="w-full sm:w-auto">
                            <label for="sort_date" class="app-form-label">Сортировка по дате</label>
                            <select id="sort_date" name="sort_date" class="app-select min-w-[16rem]" onchange="this.form.submit()">
                                <option value="desc" @selected(($sortDate ?? 'desc') === 'desc')>Сначала новые заявки</option>
                                <option value="asc" @selected(($sortDate ?? 'desc') === 'asc')>Сначала старые заявки</option>
                            </select>
                        </div>
                    </form>
                    <div class="overflow-x-auto rounded-xl border border-stone-200/90 dark:border-stone-600">
                        <table class="min-w-full text-sm">
                            <thead class="bg-stone-50 dark:bg-stone-900/40">
                                <tr>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Заявка</th>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Дата</th>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Подразделение</th>
                                    <th class="px-3 py-2 text-left text-black dark:text-white">Автор</th>
                                    <th class="px-3 py-2 text-right text-black dark:text-white">Форма</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                                @foreach($applications as $appRow)
                                    <tr class="bg-white dark:bg-stone-950/70">
                                        <td class="px-3 py-2 align-top text-black dark:text-white font-medium">
                                            №{{ $appRow->id }}
                                        </td>
                                        <td class="px-3 py-2 align-top text-black dark:text-white whitespace-nowrap">
                                            {{ $appRow->created_at?->format('d.m.Y H:i') ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 align-top text-black dark:text-white">
                                            {{ $appRow->subdivision->name ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 align-top text-black dark:text-white">
                                            @if($appRow->user)
                                                {{ $appRow->user->surname }} {{ $appRow->user->name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-top text-right">
                                            <a href="{{ route('applications.custom-equipment-order', $appRow) }}" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap">
                                                Открыть форму
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
