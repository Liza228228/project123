<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Заявки
            </h2>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto sm:shrink-0">
                @if (Auth::user()->hasRoleId(2))
                    <a href="{{ route('applications.report.index') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-orange-300 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/30 transition hover:bg-orange-100 dark:hover:bg-orange-900/50 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 whitespace-nowrap w-full sm:w-auto">
                        Отчёт по заявкам
                    </a>
                @endif
                @if (Auth::user()->hasAnyRoleId([1, 6, 2, 4]))
                    <a href="{{ route('applications.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 whitespace-nowrap w-full sm:w-auto">
                        Создать заявку
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-orange-100 dark:bg-orange-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <form method="get" action="{{ route('applications.index') }}" class="flex flex-col gap-4">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(14rem,20rem)] lg:items-end">
                            <div class="min-w-0">
                                <label for="applications-q" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Поиск</label>
                                <input type="search" name="q" id="applications-q" value="{{ $search }}"
                                    placeholder="Подразделение, автор, согласовавший, ответственный, оборудование…"
                                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                            </div>
                            <div class="min-w-0">
                                <label for="applications-equipment-filter" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Статус согласования</label>
                                <select name="equipment_filter" id="applications-equipment-filter"
                                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <option value="all" @selected($equipmentFilter === 'all')>Все заявки</option>
                                    <option value="has_approved" @selected($equipmentFilter === 'has_approved')>Есть согласованные позиции</option>
                                    <option value="has_not_approved" @selected($equipmentFilter === 'has_not_approved')>Есть несогласованные позиции</option>
                                    <option value="fully_approved" @selected($equipmentFilter === 'fully_approved')>Все позиции согласованы</option>
                                    <option value="on_approval" @selected($equipmentFilter === 'on_approval')>Заявка на согласовании</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2 pt-1 sm:justify-end">
                            <button type="submit" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-3 sm:py-2.5 text-sm font-semibold text-white rounded-lg bg-orange-600 shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 whitespace-nowrap shrink-0 [touch-action:manipulation]">
                                Применить
                            </button>
                            @if($search !== '' || $equipmentFilter !== 'all')
                                <a href="{{ route('applications.index') }}" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-3 sm:py-2.5 text-sm font-medium text-black dark:text-white rounded-lg border border-orange-300 dark:border-orange-600 bg-orange-50/80 dark:bg-orange-900/30 hover:bg-orange-100 dark:hover:bg-orange-900/50 whitespace-nowrap shrink-0 [touch-action:manipulation]">
                                    Сбросить
                                </a>
                            @endif
                        </div>
                    </form>

                    @if($applications->isEmpty())
                        <p class="md:hidden py-6 text-center text-sm text-black dark:text-white">
                            @if($search !== '' || $equipmentFilter !== 'all')
                                По заданным условиям заявок не найдено.
                            @else
                                Заявок пока нет.
                            @endif
                        </p>
                    @else
                        <div class="md:hidden space-y-4">
                            @foreach($applications as $application)
                                <article class="rounded-xl border border-orange-200 dark:border-orange-800 bg-orange-50/30 dark:bg-orange-900/20 p-4 space-y-3 shadow-sm">
                                    <div class="flex justify-between gap-3 items-start">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Подразделение</p>
                                            <p class="text-sm font-medium text-black dark:text-white break-words">{{ $application->subdivision->name }}</p>
                                            @if($application->source_application_id)
                                                <span class="mt-1 inline-flex w-fit items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-orange-900/40 dark:text-white">
                                                    Повторная к №{{ $application->source_application_id }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="shrink-0 text-end">
                                            @if($application->items->isEmpty())
                                                <span class="text-xs text-black/50 dark:text-white/50">—</span>
                                            @elseif($application->isStatusApproved())
                                                <span class="inline-flex items-center rounded-full bg-orange-200 px-2 py-0.5 text-xs font-medium text-black dark:bg-orange-900/60 dark:text-white">Согласована</span>
                                            @elseif($application->isStatusPartial())
                                                <span class="inline-flex items-center rounded-full bg-amber-200/90 px-2 py-0.5 text-xs font-medium text-black dark:bg-amber-900/50 dark:text-white">Частично</span>
                                            @elseif($application->isStatusRejected())
                                                <span class="inline-flex items-center rounded-full bg-orange-300/90 px-2 py-0.5 text-xs font-medium text-black dark:bg-orange-900/70 dark:text-white">Не согласована</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-orange-900/50 dark:text-white">На согласовании</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 text-sm text-black dark:text-white">
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Ответственный</p>
                                            <p>{{ $application->responsibleUser ? $application->responsibleUser->surname.' '.$application->responsibleUser->name : '—' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Оборудование</p>
                                            <p class="text-sm break-words line-clamp-4" title="{{ $application->equipment_summary }}">{{ $application->equipment_summary }}</p>
                                        </div>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                                            <div>
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Дата поставки</p>
                                                <p>{{ $application->desired_delivery_date->format('d.m.Y') }}</p>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Транспорт</p>
                                                <p class="break-words">{{ $application->transportOption?->name ?? '—' }}</p>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Создал(а)</p>
                                            <p class="break-words">
                                                @if($application->user)
                                                    {{ $application->user->surname }} {{ $application->user->name }}
                                                    <span class="block text-xs opacity-75">{{ $application->created_at->format('d.m.Y H:i') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Согласование</p>
                                            <p class="break-words">
                                                @if($application->approvedBy)
                                                    {{ $application->approvedBy->surname }} {{ $application->approvedBy->name }}
                                                @else
                                                    —
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <a href="{{ route('applications.show', $application) }}" class="flex w-full items-center justify-center px-4 py-3 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 [touch-action:manipulation]">
                                        Просмотр
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-orange-200 dark:divide-orange-800">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Подразделение</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Ответственный</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase min-w-[9rem]">Создал(а)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase min-w-[9rem]">Согласовал(а)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase min-w-[14rem] max-w-md">Оборудование</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Транспорт</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Желаемая дата поставки</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Согласование</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-black dark:text-white uppercase w-[1%] whitespace-nowrap"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orange-200 dark:divide-orange-800">
                                @forelse($applications as $application)
                                    <tr class="align-top">
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            <div class="flex flex-col gap-1">
                                                <span>{{ $application->subdivision->name }}</span>
                                                @if($application->source_application_id)
                                                    <span class="inline-flex w-fit items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-orange-900/40 dark:text-white">
                                                        Повторная к заявке №{{ $application->source_application_id }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            @if($application->responsibleUser)
                                                {{ $application->responsibleUser->surname }} {{ $application->responsibleUser->name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-black dark:text-white align-top max-w-[11rem]">
                                            @if($application->user)
                                                <span class="font-medium text-sm">{{ $application->user->surname }} {{ $application->user->name }}</span>
                                                <span class="block text-[11px] opacity-75 mt-0.5 whitespace-nowrap">{{ $application->created_at->format('d.m.Y H:i') }}</span>
                                            @else
                                                <span class="opacity-50">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-black dark:text-white align-top max-w-[11rem]">
                                            @if($application->approvedBy)
                                                <span class="font-medium text-sm">{{ $application->approvedBy->surname }} {{ $application->approvedBy->name }}</span>
                                            @else
                                                <span class="opacity-50">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top min-w-[14rem] max-w-md">
                                            @if($application->items->isEmpty())
                                                <span class="opacity-50">—</span>
                                            @else
                                                @php
                                                    $idxUnchecked = $application->items->filter(fn ($i) => ! $application->itemLineIsApproved($i->id))->sortBy('id');
                                                    $idxChecked = $application->items->filter(fn ($i) => $application->itemLineIsApproved($i->id))->sortBy('id');
                                                @endphp
                                                @if($idxUnchecked->isNotEmpty())
                                                    <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Не согласовано</h4>
                                                    <ul class="divide-y divide-orange-200 dark:divide-orange-800 rounded-lg border border-orange-300 dark:border-orange-700 overflow-hidden mb-6">
                                                        @foreach($idxUnchecked as $item)
                                                            <li class="px-4 py-3 bg-orange-50/80 dark:bg-orange-900/25">
                                                                <span class="text-sm font-medium text-black dark:text-white">
                                                                    {{ $item->equipment_display_name }} × {{ $item->quantity }}
                                                                </span>
                                                                @if($application->itemLineRejectionReason($item->id))
                                                                    <p class="mt-1 text-sm text-black dark:text-white"><span class="font-medium text-black dark:text-white">Причина:</span> {{ $application->itemLineRejectionReason($item->id) }}</p>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                @if($idxChecked->isNotEmpty())
                                                    <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Согласовано</h4>
                                                    <ul class="divide-y divide-orange-200 dark:divide-orange-800 rounded-lg border border-orange-300 dark:border-orange-700 overflow-hidden">
                                                        @foreach($idxChecked as $item)
                                                            <li class="px-4 py-3 bg-orange-100/60 dark:bg-orange-900/30">
                                                                <span class="text-sm font-medium text-black dark:text-white">
                                                                    {{ $item->equipment_display_name }} × {{ $item->quantity }}
                                                                </span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            @if($application->transportOption)
                                                <span class="line-clamp-2" title="{{ $application->transportOption->name }}">{{ $application->transportOption->name }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top whitespace-nowrap">{{ $application->desired_delivery_date->format('d.m.Y') }}</td>
                                        <td class="px-4 py-3 text-sm align-top">
                                            @if($application->items->isEmpty())
                                                <span class="text-black dark:text-white opacity-50">—</span>
                                            @elseif($application->isStatusApproved())
                                                <span class="inline-flex items-center rounded-full bg-orange-200 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-orange-900/60 dark:text-white">
                                                    Согласована
                                                </span>
                                            @elseif($application->isStatusPartial())
                                                <span class="inline-flex items-center rounded-full bg-amber-200/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-amber-900/50 dark:text-white">
                                                    Частично
                                                </span>
                                            @elseif($application->isStatusRejected())
                                                <span class="inline-flex items-center rounded-full bg-orange-300/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-orange-900/70 dark:text-white">
                                                    Не согласована
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-orange-900/50 dark:text-white">
                                                    На согласовании
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right align-top whitespace-nowrap w-[1%]">
                                            <a href="{{ route('applications.show', $application) }}" class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                                                Просмотр
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-6 text-center text-sm text-black dark:text-white">
                                            @if($search !== '' || $equipmentFilter !== 'all')
                                                По заданным условиям заявок не найдено.
                                            @else
                                                Заявок пока нет.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
