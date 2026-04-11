<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
                Заявки
            </h2>
            @if (Auth::user()->hasAnyRoleId([1, 6, 2, 4]))
                <a href="{{ route('applications.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                    Создать заявку
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-orange-100 dark:bg-orange-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm sm:rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-orange-200 dark:divide-orange-800">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Подразделение</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Ответственный</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase min-w-[14rem] max-w-md">Оборудование</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Транспорт</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Желаемая дата поставки</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Согласование</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-black dark:text-white uppercase"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orange-200 dark:divide-orange-800">
                                @forelse($applications as $application)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white">
                                            <div class="flex flex-col gap-1">
                                                <span>{{ $application->subdivision->name }}</span>
                                                @if($application->source_application_id)
                                                    <span class="inline-flex w-fit items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-orange-900/40 dark:text-white">
                                                        Повторная к заявке №{{ $application->source_application_id }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white">
                                            @if($application->responsibleUser)
                                                {{ $application->responsibleUser->surname }} {{ $application->responsibleUser->name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top min-w-[14rem] max-w-md">
                                            @if($application->items->isEmpty())
                                                <span class="opacity-50">—</span>
                                            @else
                                                @php
                                                    $idxUnchecked = $application->items->where('is_checked', false)->sortBy('id');
                                                    $idxChecked = $application->items->where('is_checked', true)->sortBy('id');
                                                @endphp
                                                @if($idxUnchecked->isNotEmpty())
                                                    <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Не одобрено</h4>
                                                    <ul class="divide-y divide-orange-200 dark:divide-orange-800 rounded-lg border border-orange-300 dark:border-orange-700 overflow-hidden mb-6">
                                                        @foreach($idxUnchecked as $item)
                                                            <li class="px-4 py-3 bg-orange-50/80 dark:bg-orange-900/25">
                                                                <span class="text-sm font-medium text-black dark:text-white">
                                                                    {{ $item->equipment_display_name }} × {{ $item->quantity }}
                                                                </span>
                                                                @if($item->reason_not_selected)
                                                                    <p class="mt-1 text-sm text-black dark:text-white"><span class="font-medium text-black dark:text-white">Причина:</span> {{ $item->reason_not_selected }}</p>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                @if($idxChecked->isNotEmpty())
                                                    <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Одобрено</h4>
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
                                        <td class="px-4 py-3 text-sm text-black dark:text-white">
                                            @if($application->transportOption)
                                                <span class="line-clamp-2" title="{{ $application->transportOption->name }}">{{ $application->transportOption->name }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white">{{ $application->desired_delivery_date->format('d.m.Y') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($application->items->isEmpty())
                                                <span class="text-black dark:text-white opacity-50">—</span>
                                            @elseif($application->is_fully_approved)
                                                <span class="inline-flex items-center rounded-full bg-orange-200 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-orange-900/60 dark:text-white">
                                                    Согласовано
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-orange-900/50 dark:text-white">
                                                    На согласовании
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('applications.show', $application) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                                                Просмотр
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-sm text-black dark:text-white">Заявок пока нет.</td>
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
