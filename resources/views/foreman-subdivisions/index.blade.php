<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
                Назначение подразделений 
            </h2>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-md bg-orange-100 dark:bg-orange-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm sm:rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-4 sm:p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-orange-200 dark:divide-orange-800">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Мастер участка</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Почта</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Назначенные подразделения</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-black dark:text-white uppercase"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orange-200 dark:divide-orange-800">
                                @forelse($foremen as $foreman)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            <div class="font-medium">{{ $foreman->surname }} {{ $foreman->name }} {{ $foreman->patronymic }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            <span class="opacity-85">{{ $foreman->email }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            @if($foreman->assignedSubdivisions->isEmpty())
                                                —
                                            @else
                                                <ul class="space-y-1">
                                                    @foreach($foreman->assignedSubdivisions as $subdivision)
                                                        <li class="text-sm text-black dark:text-white">
                                                            {{ $subdivision->name }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right align-top">
                                            <a href="{{ route('foreman-subdivisions.edit', $foreman) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                                                Назначить подразделение
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-black dark:text-white">Мастера участка не найдены.</td>
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
