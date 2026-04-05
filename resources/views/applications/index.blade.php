<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-orange-950 dark:text-orange-50 leading-tight">
                Заявки
            </h2>
            @if (in_array((int) Auth::user()->role_id, [\App\Models\Role::ID_DIRECTOR, \App\Models\Role::ID_SITE_FOREMAN, \App\Models\Role::ID_SUPPLY_DEPARTMENT_HEAD], true))
                <a href="{{ route('applications.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                    Создать заявку
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-orange-100 dark:bg-orange-900/40 text-orange-900 dark:text-orange-100 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm sm:rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-orange-200 dark:divide-orange-800">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Подразделение</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Ответственный</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Оборудование</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Транспорт</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Желаемая дата поставки</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-orange-800 dark:text-orange-300 uppercase">Согласование</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-orange-800 dark:text-orange-300 uppercase"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orange-200 dark:divide-orange-800">
                                @forelse($applications as $application)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-orange-950 dark:text-orange-50">
                                            <div class="flex flex-col gap-1">
                                                <span>{{ $application->subdivision->name }}</span>
                                                @if($application->source_application_id)
                                                    <span class="inline-flex w-fit items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">
                                                        Повторная к заявке №{{ $application->source_application_id }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-orange-900 dark:text-orange-200">
                                            @if($application->responsibleUser)
                                                {{ $application->responsibleUser->surname }} {{ $application->responsibleUser->name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-orange-900 dark:text-orange-200">{{ $application->equipment_summary }}</td>
                                        <td class="px-4 py-3 text-sm text-orange-900 dark:text-orange-200">
                                            @if($application->transportOption)
                                                <span class="line-clamp-2" title="{{ $application->transportOption->name }}">{{ $application->transportOption->name }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-orange-900 dark:text-orange-200">{{ $application->desired_delivery_date->format('d.m.Y') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($application->items->isEmpty())
                                                <span class="text-orange-400 dark:text-orange-500">—</span>
                                            @elseif($application->is_fully_approved)
                                                <span class="inline-flex items-center rounded-full bg-orange-200 px-2.5 py-0.5 text-xs font-medium text-orange-900 dark:bg-orange-900/60 dark:text-orange-100">
                                                    Согласовано
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-orange-900 dark:bg-orange-900/50 dark:text-orange-200">
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
                                        <td colspan="7" class="px-4 py-6 text-center text-sm text-orange-700 dark:text-orange-300">Заявок пока нет.</td>
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
