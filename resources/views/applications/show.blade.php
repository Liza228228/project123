<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 w-full min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 min-w-0">
                <a href="{{ route('applications.index') }}" class="shrink-0 text-black dark:text-white hover:text-black dark:hover:text-white text-sm whitespace-nowrap">← Заявки</a>
                <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                    Просмотр заявки
                </h2>
                @if($application->items->isEmpty())
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900 dark:text-white shrink-0">Нет позиций</span>
                @elseif($application->isStatusApproved())
                    <span class="inline-flex items-center rounded-full bg-stone-200 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/60 dark:text-white shrink-0">Согласована</span>
                @elseif($application->isStatusPartial())
                    <span class="inline-flex items-center rounded-full bg-stone-200/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white shrink-0">Частично согласована</span>
                @elseif($application->isStatusRejected())
                    <span class="inline-flex items-center rounded-full bg-stone-300/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/70 dark:text-white shrink-0">Не согласована</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white shrink-0">На согласовании</span>
                @endif
            </div>
            <div class="flex flex-col sm:flex-row flex-wrap gap-2">
                @if (Auth::user()->hasAnyRoleId([1, 6, 2, 4]))
                    <a href="{{ route('applications.edit', $application) }}" class="ui-btn ui-btn--primary whitespace-nowrap shrink-0 w-full sm:w-auto">
                        Изменить
                    </a>
                @endif
                @if (Auth::user()->hasRoleId(4))
                    <a href="{{ route('applications.repeat', $application) }}" class="ui-btn ui-btn--primary whitespace-nowrap shrink-0 w-full sm:w-auto">
                        Создать повторную
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 rounded-lg bg-stone-100 dark:bg-stone-900/40 text-black dark:text-white text-sm">
                    {{ session('status') }}
                </div>
            @endif
            <div class="bg-white dark:bg-stone-950 overflow-hidden shadow-sm rounded-lg border border-stone-200 dark:border-stone-800">
                <div class="p-4 sm:p-6 space-y-5 sm:space-y-6">
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
                                        <ul class="rounded-lg border border-stone-200 dark:border-stone-700 divide-y divide-stone-200 dark:divide-stone-800 max-h-52 overflow-y-auto">
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
                            <div>
                                <dt class="text-xs text-black dark:text-white">Заявку создал(а)</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->user)
                                        {{ $application->user->surname }} {{ $application->user->name }}
                                        <span class="block text-xs font-normal opacity-80 mt-0.5">{{ $application->created_at->format('d.m.Y H:i') }}</span>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-black dark:text-white">Согласование сохранено</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->approvedBy)
                                        {{ $application->approvedBy->surname }} {{ $application->approvedBy->name }}
                                    @else
                                        <span class="font-normal opacity-70">Ещё не сохраняли через «Сохранить согласование»</span>
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
                                                class="ui-btn ui-btn--primary ui-btn--sm"
                                            >
                                                Открыть файл
                                            </a>
                                            <a
                                                href="{{ route('applications.commercial-offer.download', $application) }}"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-black dark:text-white rounded-lg border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900/30 transition hover:bg-stone-50 dark:hover:bg-stone-900/50"
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

                    @if(Auth::user()->hasRoleId(4) && $application->latestEditHistory)
                        @php
                            $hist = $application->latestEditHistory;
                            $lastEditor = $hist->user;
                            $editorRoleLabel = $lastEditor?->role?->name;
                        @endphp
                        <div class="rounded-lg border border-stone-300 dark:border-stone-700 bg-stone-50/80 dark:bg-stone-900/25 p-4 space-y-2">
                            <p class="text-sm text-black dark:text-white">
                                <span class="font-medium">В заявку внесены изменения</span>
                                @if($lastEditor)
                                    — {{ $lastEditor->surname }} {{ $lastEditor->name }}
                                    @if($editorRoleLabel)
                                        <span class="text-xs font-normal opacity-80">({{ $editorRoleLabel }})</span>
                                    @endif
                                @endif
                                <span class="text-xs font-normal opacity-80"> — {{ $hist->edited_at->format('d.m.Y H:i') }}</span>
                            </p>
                            @if(filled($hist->equipment_change))
                                <div class="text-sm text-black dark:text-white">
                                    <span class="font-medium">Оборудование:</span>
                                    <p class="mt-1 whitespace-pre-line">{{ $hist->equipment_change }}</p>
                                </div>
                            @endif
                            @if(filled($hist->change_reason))
                                <div class="text-sm text-black dark:text-white">
                                    <span class="font-medium">Причина:</span>
                                    <p class="mt-1 whitespace-pre-line">{{ $hist->change_reason }}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    @php
                        $canManageApproval = Auth::user()->hasAnyRoleId([1, 6, 2]);
                        $uncheckedItems = $application->items->filter(fn ($i) => ! $application->itemLineIsApproved($i->id));
                        $checkedItems = $application->items->filter(fn ($i) => $application->itemLineIsApproved($i->id));
                    @endphp
                    <div>
                        <h3 class="text-sm font-medium text-black dark:text-white mb-3">Оборудование</h3>

                        @if($application->items->isEmpty())
                            <p class="text-sm text-black dark:text-white py-3">Позиций нет.</p>
                        @elseif($canManageApproval)
                            <form method="POST" action="{{ route('applications.approval', $application) }}" id="approval-form" class="space-y-4">
                                @csrf

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" id="approval-check-all" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 text-black dark:text-white hover:bg-stone-50 dark:hover:bg-stone-800">
                                        Согласовать все
                                    </button>
                                    <button type="button" id="approval-uncheck-all" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 text-black dark:text-white hover:bg-stone-50 dark:hover:bg-stone-800">
                                        Снять со всех
                                    </button>
                                </div>
                                <div class="rounded-lg border border-stone-200 dark:border-stone-700 bg-stone-50/70 dark:bg-stone-900/25 p-3 space-y-2">
                                    <label for="bulk-unchecked-reason" class="block text-xs font-medium text-black dark:text-white">
                                        Общая причина для несогласованных позиций
                                    </label>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start">
                                        <input
                                            id="bulk-unchecked-reason"
                                            type="text"
                                            maxlength="500"
                                            placeholder="Например: нет на складе поставщика"
                                            class="w-full min-w-0 sm:min-w-[200px] sm:flex-1 rounded-lg border-stone-200 dark:border-stone-700 dark:bg-stone-950 dark:text-white text-sm shadow-sm focus:ring-stone-500 focus:border-stone-500"
                                        />
                                        <button type="button" id="apply-bulk-unchecked-reason" class="inline-flex w-full shrink-0 items-center justify-center px-3 py-2 text-sm font-medium rounded-lg border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 text-black dark:text-white hover:bg-stone-50 dark:hover:bg-stone-800 sm:w-auto">
                                            Применить к несогласованным
                                        </button>
                                    </div>
                                </div>
                                <ul class="divide-y divide-stone-200 dark:divide-stone-800 rounded-lg border border-stone-200 dark:border-stone-700 overflow-hidden">
                                    @foreach($application->items->sortBy('id') as $item)
                                        @php
                                            $oldChecked = old("items.{$item->id}.is_checked", $application->itemLineIsApproved($item->id) ? '1' : '0');
                                            $isCheckedOld = (string) $oldChecked === '1';
                                        @endphp
                                        <li class="approval-row px-4 py-3 bg-white dark:bg-stone-950/80 space-y-2">
                                            <div class="flex items-start gap-3">
                                                <input type="hidden" name="items[{{ $item->id }}][is_checked]" value="0">
                                                <input type="checkbox"
                                                    name="items[{{ $item->id }}][is_checked]"
                                                    value="1"
                                                    class="approval-item-checkbox mt-0.5 h-5 w-5 shrink-0 rounded border-stone-200 text-black shadow-sm focus:ring-stone-500 dark:border-stone-700 dark:bg-stone-900 dark:checked:bg-stone-700"
                                                    @checked($isCheckedOld)
                                                />
                                                <div class="min-w-0 flex-1">
                                                    <span class="text-sm font-medium text-black dark:text-white">
                                                        {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="approval-reason-block pl-8 sm:pl-9 {{ $isCheckedOld ? 'hidden' : '' }}">
                                                <label class="block text-xs text-black dark:text-white mb-0.5" for="reason-{{ $item->id }}">Причина не согласования</label>
                                                <input type="text"
                                                    id="reason-{{ $item->id }}"
                                                    name="items[{{ $item->id }}][reason_not_selected]"
                                                    value="{{ $isCheckedOld ? '' : old("items.{$item->id}.reason_not_selected", $application->itemLineRejectionReason($item->id) ?? '') }}"
                                                    placeholder="Обязательно, если позиция не согласована"
                                                    maxlength="500"
                                                    class="approval-reason-input block w-full rounded-lg border-stone-200 dark:border-stone-700 dark:bg-stone-950 dark:text-white shadow-sm text-sm focus:ring-stone-500 focus:border-stone-500 @error('items.'.$item->id.'.reason_not_selected') border-red-500 dark:border-red-400 @enderror"
                                                />
                                                @error('items.'.$item->id.'.reason_not_selected')
                                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                <div>
                                    <button type="submit" class="ui-btn ui-btn--primary">
                                        Сохранить согласование
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
                                <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Не согласовано</h4>
                                <ul class="divide-y divide-stone-200 dark:divide-stone-800 rounded-lg border border-stone-300 dark:border-stone-700 overflow-hidden mb-6">
                                    @foreach($uncheckedItems->sortBy('id') as $item)
                                        <li class="px-4 py-3 bg-stone-50/80 dark:bg-stone-900/25">
                                            <span class="text-sm font-medium text-black dark:text-white">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @if($application->itemLineRejectionReason($item->id))
                                                <p class="mt-1 text-sm text-black dark:text-white"><span class="font-medium text-black dark:text-white">Причина:</span> {{ $application->itemLineRejectionReason($item->id) }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if($checkedItems->isNotEmpty())
                                <h4 class="text-xs font-medium text-black dark:text-white uppercase tracking-wide mb-2">Согласовано</h4>
                                <ul class="divide-y divide-stone-200 dark:divide-stone-800 rounded-lg border border-stone-300 dark:border-stone-700 overflow-hidden">
                                    @foreach($checkedItems->sortBy('id') as $item)
                                        <li class="px-4 py-3 bg-stone-100/60 dark:bg-stone-900/30">
                                            <span class="text-sm font-medium text-black dark:text-white">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>

                    @if(Auth::user()->hasAnyRoleId([1, 2]))
                        @php
                            $approvedForIssue = $application->items->filter(fn ($item) => $item->is_checked && $item->equipment_id);
                        @endphp
                        <div class="pt-2 border-t border-stone-200 dark:border-stone-800">
                            <h3 class="text-sm font-medium text-black dark:text-white mb-3">Списание со склада по заявке</h3>

                            @if(!$mainWarehouse)
                                <p class="text-sm text-red-700 dark:text-red-400">
                                    Не найден основной склад «Администрация». Назначьте основной склад, чтобы списывать оборудование по заявкам.
                                </p>
                            @elseif($approvedForIssue->isEmpty())
                                <p class="text-sm text-black dark:text-white opacity-80">
                                    Нет согласованных позиций из справочника оборудования для списания.
                                </p>
                            @else
                                @error('stock')
                                    <div class="mb-3 text-sm text-red-700 dark:text-red-400">{{ $message }}</div>
                                @enderror

                                <p class="text-xs text-black dark:text-white opacity-80 mb-2">
                                    Склад списания: <span class="font-medium">{{ $mainWarehouse->name }}</span>
                                </p>

                                <form method="POST" action="{{ route('applications.issue-stock', $application) }}" class="space-y-3">
                                    @csrf
                                    <div class="rounded-lg border border-stone-200 dark:border-stone-700 overflow-hidden">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-stone-50 dark:bg-stone-900/40">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-black dark:text-white">Позиция</th>
                                                    <th class="px-3 py-2 text-right text-black dark:text-white">Согласовано</th>
                                                    <th class="px-3 py-2 text-right text-black dark:text-white">Списано</th>
                                                    <th class="px-3 py-2 text-right text-black dark:text-white">Осталось</th>
                                                    <th class="px-3 py-2 text-right text-black dark:text-white">Списать сейчас</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                                                @foreach($approvedForIssue->sortBy('id') as $item)
                                                    @php
                                                        $issued = (float) ($issuedByItemId[$item->id] ?? 0);
                                                        $remaining = (float) ($remainingByItemId[$item->id] ?? max(0, (float) $item->quantity - $issued));
                                                    @endphp
                                                    <tr class="bg-white dark:bg-stone-950/70">
                                                        <td class="px-3 py-2 text-black dark:text-white">{{ $item->equipment_display_name }}</td>
                                                        <td class="px-3 py-2 text-right text-black dark:text-white">{{ number_format((float) $item->quantity, 3, '.', ' ') }}</td>
                                                        <td class="px-3 py-2 text-right text-black dark:text-white">{{ number_format($issued, 3, '.', ' ') }}</td>
                                                        <td class="px-3 py-2 text-right text-black dark:text-white font-medium">{{ number_format($remaining, 3, '.', ' ') }}</td>
                                                        <td class="px-3 py-2 text-right">
                                                            <input
                                                                type="number"
                                                                step="0.001"
                                                                min="0"
                                                                max="{{ number_format($remaining, 3, '.', '') }}"
                                                                name="items[{{ $item->id }}][quantity]"
                                                                value="0"
                                                                class="w-28 rounded-lg border-stone-300 dark:border-stone-700 dark:bg-stone-900 dark:text-white text-sm"
                                                            />
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div>
                                        <label for="issue-comment" class="block text-xs text-black dark:text-white mb-1">Комментарий к списанию (необязательно)</label>
                                        <textarea id="issue-comment" name="comment" rows="2" class="block w-full rounded-lg border-stone-300 dark:border-stone-700 dark:bg-stone-900 dark:text-white text-sm"></textarea>
                                    </div>

                                    <button type="submit" class="ui-btn ui-btn--primary">
                                        Списать со склада по заявке
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
