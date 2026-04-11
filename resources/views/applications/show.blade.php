<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('applications.index') }}" class="text-black dark:text-white hover:text-black dark:hover:text-white text-sm">← Заявки</a>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight">
                Просмотр заявки
            </h2>
            @if($application->items->isEmpty())
                <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-orange-900 dark:text-white">Нет позиций</span>
            @elseif($application->is_fully_approved)
                <span class="inline-flex items-center rounded-full bg-orange-200 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-orange-900/60 dark:text-white">Согласовано</span>
            @else
                <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-orange-900/50 dark:text-white">На согласовании</span>
            @endif
            @if (Auth::user()->hasAnyRoleId([1, 6, 2, 4]))
                <a href="{{ route('applications.edit', $application) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                    Изменить
                </a>
            @endif
            @if (Auth::user()->hasRoleId(4))
                <a href="{{ route('applications.repeat', $application) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white rounded-lg bg-orange-700 shadow-sm transition hover:bg-orange-800 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                    Создать повторную
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-lg bg-orange-100 dark:bg-orange-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif
            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm sm:rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-6 space-y-6">
                    <div>
                        <h3 class="text-sm font-medium text-black dark:text-white">Данные заявки</h3>
                        <dl class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-black dark:text-white">Подразделение</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">{{ $application->subdivision->name }}</dd>
                            </div>
                            @if($application->subdivision && $application->subdivision->warehouses->isNotEmpty())
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-black dark:text-white">Склады подразделения</dt>
                                    <dd class="mt-1">
                                        <ul class="rounded-lg border border-orange-200 dark:border-orange-700 divide-y divide-orange-200 dark:divide-orange-800 max-h-52 overflow-y-auto">
                                            @foreach($application->subdivision->warehouses as $wh)
                                                <li class="px-3 py-2 text-sm text-black dark:text-white">
                                                    <span class="font-mono text-xs opacity-80">{{ $wh->code }}</span>
                                                    — {{ $wh->name }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </dd>
                                </div>
                            @endif
                            <div>
                                <dt class="text-xs text-black dark:text-white">Ответственный</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->responsibleUser)
                                        {{ $application->responsibleUser->surname }} {{ $application->responsibleUser->name }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-black dark:text-white">Желаемая дата поставки</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">{{ $application->desired_delivery_date->format('d.m.Y') }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-black dark:text-white">Транспорт / доставка</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->transportOption)
                                        {{ $application->transportOption->name }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-black dark:text-white">Коммерческое предложение</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->commercial_offer_path)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ basename($application->commercial_offer_path) }}</span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <a
                                                href="{{ route('applications.commercial-offer.view', $application) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white rounded-lg bg-orange-600 shadow-sm transition hover:bg-orange-700"
                                            >
                                                Открыть файл
                                            </a>
                                            <a
                                                href="{{ route('applications.commercial-offer.download', $application) }}"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-black dark:text-white rounded-lg border border-orange-300 dark:border-orange-700 bg-white dark:bg-orange-900/30 transition hover:bg-orange-50 dark:hover:bg-orange-900/50"
                                            >
                                                Скачать
                                            </a>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            @if($application->source_application_id)
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-black dark:text-white">Тип заявки</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                        Повторная заявка
                                        @if($application->sourceApplication)
                                            — на основе
                                            <a href="{{ route('applications.show', $application->sourceApplication) }}" class="underline hover:no-underline">
                                                заявки №{{ $application->source_application_id }}
                                            </a>
                                        @else
                                            — к заявке №{{ $application->source_application_id }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    @if(Auth::user()->hasRoleId(4) && $application->director_last_edited_at)
                        @php
                            $directorEditLines = $application->directorLastEditDetailLines();
                            $mgmtEditor = $application->directorLastEditedBy;
                            $mgmtRoleLabel = $mgmtEditor?->role?->name;
                        @endphp
                        <div class="rounded-lg border border-orange-300 dark:border-orange-700 bg-orange-50/80 dark:bg-orange-900/25 p-4 space-y-2">
                            <p class="text-sm text-black dark:text-white">
                                <span class="font-medium">В заявку внесены изменения</span>
                                @if($mgmtEditor)
                                    — {{ $mgmtEditor->surname }} {{ $mgmtEditor->name }}
                                    @if($mgmtRoleLabel)
                                        <span class="text-xs font-normal opacity-80">({{ $mgmtRoleLabel }})</span>
                                    @endif
                                @endif
                                <span class="text-xs font-normal opacity-80"> — {{ $application->director_last_edited_at->format('d.m.Y H:i') }}</span>
                            </p>
                            @if(count($directorEditLines) > 0)
 
                                <ul class="list-disc list-inside text-sm text-black dark:text-white space-y-1">
                                    @foreach($directorEditLines as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    @php
                        $canManageApproval = Auth::user()->hasAnyRoleId([1, 6, 2]);
                        $uncheckedItems = $application->items->where('is_checked', false);
                        $checkedItems = $application->items->where('is_checked', true);
                    @endphp
                    <div>
                        <h3 class="text-sm font-medium text-black dark:text-white mb-3">Оборудование</h3>

                        @if($application->items->isEmpty())
                            <p class="text-sm text-black dark:text-white py-3">Позиций нет.</p>
                        @elseif($canManageApproval)
                            <form method="POST" action="{{ route('applications.approval', $application) }}" id="approval-form" class="space-y-4">
                                @csrf

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" id="approval-check-all" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-orange-200 dark:border-orange-700 bg-white dark:bg-orange-900 text-black dark:text-white hover:bg-orange-50 dark:hover:bg-orange-800">
                                        Одобрить все
                                    </button>
                                    <button type="button" id="approval-uncheck-all" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-orange-200 dark:border-orange-700 bg-white dark:bg-orange-900 text-black dark:text-white hover:bg-orange-50 dark:hover:bg-orange-800">
                                        Снять со всех
                                    </button>
                                </div>
                                @if(Auth::user()->hasAnyRoleId([1, 6, 2]))
                                    <div class="rounded-lg border border-orange-200 dark:border-orange-700 bg-orange-50/70 dark:bg-orange-900/25 p-3 space-y-2">
                                        <label for="bulk-unchecked-reason" class="block text-xs font-medium text-black dark:text-white">
                                            Общая причина для неодобренного оборудования
                                        </label>
                                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start">
                                            <input
                                                id="bulk-unchecked-reason"
                                                type="text"
                                                maxlength="500"
                                                placeholder="Например: нет на складе поставщика"
                                                class="w-full min-w-0 sm:min-w-[200px] sm:flex-1 rounded-lg border-orange-200 dark:border-orange-700 dark:bg-orange-950 dark:text-white text-sm shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                            />
                                            <button type="button" id="apply-bulk-unchecked-reason" class="inline-flex w-full shrink-0 items-center justify-center px-3 py-2 text-sm font-medium rounded-lg border border-orange-200 dark:border-orange-700 bg-white dark:bg-orange-900 text-black dark:text-white hover:bg-orange-50 dark:hover:bg-orange-800 sm:w-auto">
                                                Применить к несогласованному оборудованию
                                            </button>
                                        </div>
                                      
                                    </div>
                                @endif
                                <ul class="divide-y divide-orange-200 dark:divide-orange-800 rounded-lg border border-orange-200 dark:border-orange-700 overflow-hidden">
                                    @foreach($application->items->sortBy('id') as $item)
                                        @php
                                            $oldChecked = old("items.{$item->id}.is_checked", $item->is_checked ? '1' : '0');
                                            $isCheckedOld = (string) $oldChecked === '1';
                                        @endphp
                                        <li class="approval-row px-4 py-3 bg-white dark:bg-orange-950/80 space-y-2">
                                            <div class="flex items-start gap-3">
                                                <input type="hidden" name="items[{{ $item->id }}][is_checked]" value="0">
                                                <input type="checkbox"
                                                    name="items[{{ $item->id }}][is_checked]"
                                                    value="1"
                                                    class="approval-item-checkbox mt-0.5 h-5 w-5 shrink-0 rounded border-orange-200 text-black shadow-sm focus:ring-orange-500 dark:border-orange-700 dark:bg-orange-900 dark:checked:bg-orange-600"
                                                    @checked($isCheckedOld)
                                                />
                                                <div class="min-w-0 flex-1">
                                                    <span class="text-sm font-medium text-black dark:text-white">
                                                        {{ $item->equipment_display_name }} × {{ $item->quantity }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="approval-reason-block pl-8 sm:pl-9 {{ $isCheckedOld ? 'hidden' : '' }}">
                                                <label class="block text-xs text-black dark:text-white mb-0.5" for="reason-{{ $item->id }}">Причина неодобрения</label>
                                                <input type="text"
                                                    id="reason-{{ $item->id }}"
                                                    name="items[{{ $item->id }}][reason_not_selected]"
                                                    value="{{ $isCheckedOld ? '' : old("items.{$item->id}.reason_not_selected", $item->reason_not_selected) }}"
                                                    placeholder="Обязательно, пока нет галочки"
                                                    maxlength="500"
                                                    class="approval-reason-input block w-full rounded-lg border-orange-200 dark:border-orange-700 dark:bg-orange-950 dark:text-white shadow-sm text-sm focus:ring-orange-500 focus:border-orange-500 @error('items.'.$item->id.'.reason_not_selected') border-red-500 dark:border-red-400 @enderror"
                                                />
                                                @error('items.'.$item->id.'.reason_not_selected')
                                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                <div>
                                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white rounded-lg bg-orange-600 shadow-sm hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950">
                                        Сохранить согласованное оборудование
                                    </button>
                                </div>
                            </form>
                            <script>
                                (function () {
                                    var form = document.getElementById('approval-form');
                                    if (!form) return;

                                    function syncRow(row) {
                                        var cb = row.querySelector('.approval-item-checkbox');
                                        var block = row.querySelector('.approval-reason-block');
                                        var reason = row.querySelector('.approval-reason-input');
                                        if (!cb || !block || !reason) return;
                                        if (cb.checked) {
                                            block.classList.add('hidden');
                                            reason.value = '';
                                        } else {
                                            block.classList.remove('hidden');
                                        }
                                    }

                                    function syncAll() {
                                        form.querySelectorAll('.approval-row').forEach(syncRow);
                                    }

                                    form.querySelectorAll('.approval-item-checkbox').forEach(function (cb) {
                                        cb.addEventListener('change', function () {
                                            syncRow(cb.closest('.approval-row'));
                                        });
                                    });

                                    document.getElementById('approval-check-all')?.addEventListener('click', function () {
                                        form.querySelectorAll('.approval-item-checkbox').forEach(function (cb) { cb.checked = true; });
                                        syncAll();
                                    });
                                    document.getElementById('approval-uncheck-all')?.addEventListener('click', function () {
                                        form.querySelectorAll('.approval-item-checkbox').forEach(function (cb) { cb.checked = false; });
                                        syncAll();
                                    });

                                    document.getElementById('apply-bulk-unchecked-reason')?.addEventListener('click', function () {
                                        var value = (document.getElementById('bulk-unchecked-reason')?.value || '').trim();
                                        if (value === '') {
                                            return;
                                        }
                                        form.querySelectorAll('.approval-row').forEach(function (row) {
                                            var cb = row.querySelector('.approval-item-checkbox');
                                            var reason = row.querySelector('.approval-reason-input');
                                            if (!cb || !reason) return;
                                            if (!cb.checked) {
                                                reason.value = value;
                                            }
                                        });
                                    });

                                    syncAll();
                                })();
                            </script>
                        @else
                            @if($uncheckedItems->isNotEmpty())
                                <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Не одобрено</h4>
                                <ul class="divide-y divide-orange-200 dark:divide-orange-800 rounded-lg border border-orange-300 dark:border-orange-700 overflow-hidden mb-6">
                                    @foreach($uncheckedItems as $item)
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

                            @if($checkedItems->isNotEmpty())
                                <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Одобрено</h4>
                                <ul class="divide-y divide-orange-200 dark:divide-orange-800 rounded-lg border border-orange-300 dark:border-orange-700 overflow-hidden">
                                    @foreach($checkedItems as $item)
                                        <li class="px-4 py-3 bg-orange-100/60 dark:bg-orange-900/30">
                                            <span class="text-sm font-medium text-black dark:text-white">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
