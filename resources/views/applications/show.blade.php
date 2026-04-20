<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('applications.index')">Заявки</x-page-header-nav>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center gap-x-4 gap-y-2 min-w-0">
                <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                    Просмотр заявки
                </h2>
                @if($application->items->isEmpty())
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900 dark:text-white shrink-0">Нет позиций</span>
                @elseif($application->isLifecycleCompleted())
                    <span class="inline-flex items-center rounded-full border border-emerald-300/90 bg-emerald-50/90 px-2.5 py-0.5 text-xs font-medium text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100 shrink-0" title="Акт и фото загружены, оборудование списано со складов">
                        Выполнена
                    </span>
                @elseif($application->isStatusApproved())
                    <span class="inline-flex items-center rounded-full bg-stone-200 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/60 dark:text-white shrink-0">Согласована</span>
                @elseif($application->isStatusPartial())
                    <span class="inline-flex items-center rounded-full bg-stone-200/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white shrink-0">Частично согласована</span>
                @elseif($application->needsBoilerChiefReviewBeforeManagement())
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-950 dark:bg-amber-950/40 dark:text-amber-100 shrink-0">У начальника котельной</span>
                @elseif($application->awaitsManagementEquipmentApproval())
                    <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-950 dark:bg-sky-950/40 dark:text-sky-100 shrink-0">У руководства / снабжения</span>
                @elseif($application->isStatusRejected())
                    <span class="inline-flex items-center rounded-full bg-stone-300/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/70 dark:text-white shrink-0">Не согласована</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white shrink-0">На согласовании</span>
                @endif
            </div>
            <div class="flex flex-col sm:flex-row flex-wrap gap-2">
                @if (! $application->archived_at
                    && Auth::user()->hasAnyRoleId([1, 6, 2, 4, 7])
                    && ! $application->items->contains(fn ($i) => in_array($i->resolvedDeliveryStatus(), [\App\Models\ApplicationItem::DELIVERY_IN_TRANSIT, \App\Models\ApplicationItem::DELIVERY_DELIVERED], true)))
                    <a href="{{ route('applications.edit', $application) }}" class="ui-btn ui-btn--primary whitespace-nowrap shrink-0 w-full sm:w-auto">
                        Изменить
                    </a>
                @endif
                @if (((! $application->archived_at && Auth::user()->hasAnyRoleId([1, 6, 2, 7])) || Auth::user()->hasRoleId(4)) && $application->canUploadInstallationActAndPhotos())
                    <a href="{{ route('applications.installation-act.upload', ['application_id' => $application->id]) }}" class="ui-btn ui-btn--secondary whitespace-nowrap shrink-0 w-full sm:w-auto">
                        Акт установки
                    </a>
                @endif
                @if (Auth::user()->hasRoleId(4))
                    <a href="{{ route('applications.repeat', $application) }}"
                       class="ui-btn ui-btn--primary whitespace-nowrap shrink-0 w-full sm:w-auto"
                       onclick="return window.confirm('Вы уверены, что хотите создать повторную заявку?');">
                        Создать повторную
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-stone-200/80 bg-stone-50/80 px-4 py-3 text-sm text-stone-900 dark:border-stone-700 dark:bg-stone-900/40 dark:text-stone-100">
                    {{ session('status') }}
                </div>
            @endif
            @if($application->archived_at)
                <div class="mb-4 rounded-xl border border-emerald-200/90 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100 space-y-2">
                    <p>Заявка находится в архиве выполненных (актуальные документы и списания по складу зафиксированы). Дата архивации: {{ $application->archived_at->format('d.m.Y H:i') }}.</p>
                    @if(Auth::user()->hasRoleId(4))
                        <p class="text-emerald-900/95 dark:text-emerald-100/95">Новую заявку с теми же позициями можно оформить кнопкой «Создать повторную» выше.</p>
                    @endif
                </div>
            @endif
            @error('edit')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('custom_supply')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('custom_target')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('delivery')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('delivery_subdivision_id')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('delivery_warehouse_id')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('transport_option_id')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('delivered_stock')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('boiler_chief')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('approval')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('stock')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            <div class="app-form-card">
                <div class="px-4 py-5 sm:p-8 space-y-8 sm:space-y-10">
                    <section class="space-y-4" aria-labelledby="show-section-main">
                        <h3 id="show-section-main" class="app-section-title">Данные заявки</h3>
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="app-form-label !normal-case">Подразделение</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">{{ $application->subdivision->name }}</dd>
                            </div>
                            @if($application->subdivision && $application->subdivision->warehouses->isNotEmpty())
                                <div class="sm:col-span-2">
                                    <dt class="app-form-label !normal-case">Склады подразделения</dt>
                                    <dd class="mt-1">
                                        <ul class="max-h-52 divide-y divide-stone-200 overflow-y-auto rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
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
                                <dt class="app-form-label !normal-case">Ответственный</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->responsibleUser)
                                        {{ $application->responsibleUser->surname }} {{ $application->responsibleUser->name }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="app-form-label !normal-case">Желаемая дата поставки</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">{{ $application->desired_delivery_date->format('d.m.Y') }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="app-form-label !normal-case">Транспорт / доставка</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->transportOption)
                                        {{ $application->transportOption->name }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="app-form-label !normal-case">Заявку создал(а)</dt>
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
                                <dt class="app-form-label !normal-case">Согласование сохранено</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->approvedBy)
                                        {{ $application->approvedBy->surname }} {{ $application->approvedBy->name }}
                            
                                    @endif
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="app-form-label !normal-case">Коммерческое предложение</dt>
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
                                                class="ui-btn ui-btn--secondary ui-btn--sm"
                                            >
                                                Скачать
                                            </a>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="app-form-label !normal-case">Акт установки</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->installation_act_path)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ basename($application->installation_act_path) }}</span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <a
                                                href="{{ route('applications.installation-act.view', $application) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="ui-btn ui-btn--primary ui-btn--sm"
                                            >
                                                Открыть файл
                                            </a>
                                            <a
                                                href="{{ route('applications.installation-act.download', $application) }}"
                                                class="ui-btn ui-btn--secondary ui-btn--sm"
                                            >
                                                Скачать
                                            </a>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            @if($application->installationActPhotos->isNotEmpty())
                                <div class="sm:col-span-2">
                                    <dt class="app-form-label !normal-case">Фото к акту установки</dt>
                                    <dd class="mt-2">
                                        <x-installation-act-photo-gallery :application="$application" />
                                    </dd>
                                </div>
                            @endif
                            @if($application->source_application_id)
                                <div class="sm:col-span-2">
                                    <dt class="app-form-label !normal-case">Тип заявки</dt>
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
                    </section>

                    @if(Auth::user()->hasRoleId(4) && $application->latestEditHistory)
                        @php
                            $hist = $application->latestEditHistory;
                            $lastEditor = $hist->user;
                            $editorRoleLabel = $lastEditor?->role?->name;
                        @endphp
                        <div class="rounded-xl border border-stone-200/90 bg-stone-50/80 p-4 space-y-2 dark:border-stone-600 dark:bg-stone-800/35">
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
                        $approvalLockedAfterTransit = $application->items->contains(
                            fn ($i) => in_array($i->resolvedDeliveryStatus(), [\App\Models\ApplicationItem::DELIVERY_IN_TRANSIT, \App\Models\ApplicationItem::DELIVERY_DELIVERED], true)
                        );
                        $canManagementApprove = Auth::user()->hasAnyRoleId([1, 6, 2]) && ! $application->needsBoilerChiefReviewBeforeManagement();
                        if ($approvalLockedAfterTransit) {
                            $canManagementApprove = false;
                        }
                        $canBoilerChiefApprove = Auth::user()->hasRoleId(7) && $application->needsBoilerChiefReviewBeforeManagement();
                        $uncheckedItems = $application->items->filter(fn ($i) => ! $application->itemLineIsApproved($i->id));
                        $checkedItems = $application->items->filter(fn ($i) => $application->itemLineIsApproved($i->id));
                        $boilerUncheckedItems = $application->items->filter(fn ($i) => ! $i->boiler_chief_checked);
                        $canManageDeliveryTransit = Auth::user()->hasAnyRoleId([1, 6, 2]);
                        $inTransitCandidates = $application->items->filter(fn ($i) => $i->canMarkDeliveryInTransit());
                        $chiefCanMarkDelivered = Auth::user()->hasAnyRoleId([7, 4]);
                        $chiefDeliveryCandidates = $application->items->filter(fn ($i) => $i->canMarkDeliveryDeliveredByBoilerChief());
                    @endphp
                    <section class="space-y-4" aria-labelledby="show-section-equipment">
                        <h3 id="show-section-equipment" class="app-section-title">Оборудование</h3>
                        @if(Auth::user()->hasAnyRoleId([1, 6, 2]) && $approvalLockedAfterTransit)
                            <p class="rounded-xl border border-amber-200/80 bg-amber-50/60 px-4 py-3 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100">
                                Согласование заблокировано: по заявке уже есть позиции со статусом «В пути» или «Доставлено».
                            </p>
                        @endif

                        @if($application->items->isEmpty())
                            <p class="text-sm text-stone-600 dark:text-stone-400 py-2">Позиций нет.</p>
                        @elseif($canBoilerChiefApprove)
                            <p class="mb-3 rounded-xl border border-amber-200/80 bg-amber-50/60 px-4 py-3 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100">
                                Согласуйте позиции по подразделению. После сохранения заявка станет видна директору, техническому директору и начальнику отдела снабжения для согласования и заказа оборудования. Заказывать оборудование и отмечать поставки может только снабжение / руководство.
                            </p>
                            <form method="POST" action="{{ route('applications.boiler-chief-approval', $application) }}" id="boiler-chief-approval-form" class="space-y-4">
                                @csrf
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" id="bc-approval-check-all" class="ui-btn ui-btn--secondary ui-btn--sm">
                                        Согласовать все
                                    </button>
                                    <button type="button" id="bc-approval-uncheck-all" class="ui-btn ui-btn--secondary ui-btn--sm">
                                        Снять со всех
                                    </button>
                                </div>
                                <div class="space-y-2 rounded-xl border border-stone-200/90 bg-stone-50/80 p-4 dark:border-stone-600 dark:bg-stone-800/35">
                                    <label for="bc-bulk-unchecked-reason" class="app-form-label !normal-case">
                                        Общая причина для несогласованных позиций
                                    </label>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start">
                                        <input
                                            id="bc-bulk-unchecked-reason"
                                            type="text"
                                            name="boiler_bulk_unchecked_reason"
                                            value="{{ old('boiler_bulk_unchecked_reason') }}"
                                            maxlength="500"
                                            placeholder="Например: не соответствует требованиям"
                                            class="app-input min-w-0 w-full text-sm sm:min-w-[200px] sm:flex-1 @error('boiler_bulk_unchecked_reason') !border-red-500 dark:!border-red-400 @enderror"
                                        />
                                        <button type="button" id="bc-apply-bulk-unchecked-reason" class="ui-btn ui-btn--secondary ui-btn--sm w-full shrink-0 sm:w-auto">
                                            Применить к несогласованным
                                        </button>
                                    </div>
                                    @error('boiler_bulk_unchecked_reason')
                                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <ul class="divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
                                    @foreach($application->items->sortBy('id') as $item)
                                        @php
                                            $oldBc = old("boiler_items.{$item->id}.boiler_chief_checked", $item->boiler_chief_checked ? '1' : '0');
                                            $isBcCheckedOld = (string) $oldBc === '1';
                                        @endphp
                                        <li class="bc-approval-row space-y-2 bg-white px-4 py-3 dark:bg-stone-900/40">
                                            <div class="flex items-start gap-3">
                                                <input type="hidden" name="boiler_items[{{ $item->id }}][boiler_chief_checked]" value="0">
                                                <input type="checkbox"
                                                    name="boiler_items[{{ $item->id }}][boiler_chief_checked]"
                                                    value="1"
                                                    class="bc-approval-item-checkbox mt-0.5 h-5 w-5 shrink-0 rounded border-stone-200 text-black shadow-sm focus:ring-stone-500 dark:border-stone-700 dark:bg-stone-900 dark:checked:bg-stone-700"
                                                    @checked($isBcCheckedOld)
                                                />
                                                <div class="min-w-0 flex-1 space-y-1">
                                                    <span class="text-sm font-medium text-black dark:text-white">
                                                        {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="bc-approval-reason-block pl-8 sm:pl-9 {{ $isBcCheckedOld ? 'hidden' : '' }}">
                                                <label class="block text-xs text-black dark:text-white mb-0.5" for="bc-reason-{{ $item->id }}">Причина не согласования</label>
                                                <input type="text"
                                                    id="bc-reason-{{ $item->id }}"
                                                    name="boiler_items[{{ $item->id }}][reason_boiler_chief_not_selected]"
                                                    value="{{ $isBcCheckedOld ? '' : old("boiler_items.{$item->id}.reason_boiler_chief_not_selected", $application->itemLineBoilerChiefRejectionReason($item->id) ?? '') }}"
                                                    placeholder="Обязательно, если позиция не согласована"
                                                    maxlength="500"
                                                    class="bc-approval-reason-input app-input text-sm @error('boiler_items.'.$item->id.'.reason_boiler_chief_not_selected') !border-red-500 dark:!border-red-400 @enderror"
                                                />
                                                @error('boiler_items.'.$item->id.'.reason_boiler_chief_not_selected')
                                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                <div>
                                    <button type="submit" class="ui-btn ui-btn--primary">
                                        Сохранить согласование начальника котельной
                                    </button>
                                </div>
                            </form>
                            <script>
                                (function () {
                                    var form = document.getElementById('boiler-chief-approval-form');
                                    if (!form) return;
                                    function syncRow(row) {
                                        var cb = row.querySelector('.bc-approval-item-checkbox');
                                        var block = row.querySelector('.bc-approval-reason-block');
                                        var reason = row.querySelector('.bc-approval-reason-input');
                                        if (!cb || !block || !reason) return;
                                        if (cb.checked) {
                                            block.classList.add('hidden');
                                            reason.value = '';
                                        } else {
                                            block.classList.remove('hidden');
                                        }
                                    }
                                    function syncAll() {
                                        form.querySelectorAll('.bc-approval-row').forEach(syncRow);
                                    }
                                    form.querySelectorAll('.bc-approval-item-checkbox').forEach(function (cb) {
                                        cb.addEventListener('change', function () {
                                            syncRow(cb.closest('.bc-approval-row'));
                                        });
                                    });
                                    document.getElementById('bc-approval-check-all')?.addEventListener('click', function () {
                                        form.querySelectorAll('.bc-approval-item-checkbox').forEach(function (cb) { cb.checked = true; });
                                        syncAll();
                                    });
                                    document.getElementById('bc-approval-uncheck-all')?.addEventListener('click', function () {
                                        form.querySelectorAll('.bc-approval-item-checkbox').forEach(function (cb) { cb.checked = false; });
                                        syncAll();
                                    });
                                    document.getElementById('bc-apply-bulk-unchecked-reason')?.addEventListener('click', function () {
                                        var bulkInput = document.getElementById('bc-bulk-unchecked-reason');
                                        if (!bulkInput) return;
                                        var value = (bulkInput.value || '').trim();
                                        if (value === '') return;
                                        form.querySelectorAll('.bc-approval-row').forEach(function (row) {
                                            var cb = row.querySelector('.bc-approval-item-checkbox');
                                            var reason = row.querySelector('.bc-approval-reason-input');
                                            if (!cb || !reason) return;
                                            if (!cb.checked) {
                                                reason.value = value;
                                            }
                                        });
                                    });
                                    syncAll();
                                })();
                            </script>
                        @elseif($canManagementApprove)
                            <form method="POST" action="{{ route('applications.approval', $application) }}" id="approval-form" class="space-y-4">
                                @csrf

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" id="approval-check-all" class="ui-btn ui-btn--secondary ui-btn--sm">
                                        Согласовать все
                                    </button>
                                    <button type="button" id="approval-uncheck-all" class="ui-btn ui-btn--secondary ui-btn--sm">
                                        Снять со всех
                                    </button>
                                </div>
                                <div class="space-y-2 rounded-xl border border-stone-200/90 bg-stone-50/80 p-4 dark:border-stone-600 dark:bg-stone-800/35">
                                    <label for="bulk-unchecked-reason" class="app-form-label !normal-case">
                                        Общая причина для несогласованных позиций
                                    </label>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start">
                                        <input
                                            id="bulk-unchecked-reason"
                                            type="text"
                                            maxlength="500"
                                            placeholder="Например: нет на складе поставщика"
                                            class="app-input min-w-0 w-full text-sm sm:min-w-[200px] sm:flex-1"
                                        />
                                        <button type="button" id="apply-bulk-unchecked-reason" class="ui-btn ui-btn--secondary ui-btn--sm w-full shrink-0 sm:w-auto">
                                            Применить к несогласованным
                                        </button>
                                    </div>
                                </div>
                                <ul class="divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
                                    @foreach($application->items->sortBy('id') as $item)
                                        @php
                                            $oldChecked = old("items.{$item->id}.is_checked", $application->itemLineIsApproved($item->id) ? '1' : '0');
                                            $isCheckedOld = (string) $oldChecked === '1';
                                        @endphp
                                        <li class="approval-row space-y-2 bg-white px-4 py-3 dark:bg-stone-900/40">
                                            <div class="flex items-start gap-3">
                                                <input type="hidden" name="items[{{ $item->id }}][is_checked]" value="0">
                                                <input type="checkbox"
                                                    name="items[{{ $item->id }}][is_checked]"
                                                    value="1"
                                                    class="approval-item-checkbox mt-0.5 h-5 w-5 shrink-0 rounded border-stone-200 text-black shadow-sm focus:ring-stone-500 dark:border-stone-700 dark:bg-stone-900 dark:checked:bg-stone-700"
                                                    @checked($isCheckedOld)
                                                />
                                                <div class="min-w-0 flex-1 space-y-1">
                                                    <span class="text-sm font-medium text-black dark:text-white">
                                                        {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                                    </span>
                                                    @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
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
                                                    class="approval-reason-input app-input text-sm @error('items.'.$item->id.'.reason_not_selected') !border-red-500 dark:!border-red-400 @enderror"
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
                            @php
                                $hasCustomEquipmentOrderForm = $application->items->contains(function ($i) {
                                    return $i->usesFreeTextEquipment() && $i->is_checked && $i->equipment_id === null;
                                });
                            @endphp
                            @if($hasCustomEquipmentOrderForm && Auth::user()->hasAnyRoleId(\App\Models\User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS))
                                <div class="space-y-2 rounded-xl border border-stone-200/90 bg-stone-50/80 p-4 dark:border-stone-600 dark:bg-stone-800/35">
                                    <p class="text-xs text-black dark:text-white">
                                        Позиции со <span class="font-medium">своим названием</span>: заказ и приход на основной склад оформляются в одной форме — выбор строк и кнопки «Заказано» / «На складе».
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('applications.custom-equipment-order', $application) }}" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap">
                                            Форма: своё оборудование к заказу
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @if($canManageDeliveryTransit && $inTransitCandidates->isNotEmpty())
                                <div class="space-y-3 rounded-xl border border-orange-200/80 bg-orange-50/60 p-4 dark:border-orange-800/50 dark:bg-orange-950/25">
                                    <p class="text-xs text-black dark:text-white">
                                        После подготовки отгрузки отметьте <span class="font-medium">«В пути»</span> — статус сразу проставится для всех подходящих позиций этой заявки. После фактической доставки начальник котельной отметит подразделение и склад поступления.
                                    </p>
                                    <form method="POST" action="{{ route('applications.delivery-in-transit', $application) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end">
                                        @csrf
                                        <div class="w-full sm:w-auto sm:min-w-[17rem]">
                                            <label for="delivery-transport-option-id" class="app-form-label !normal-case">Способ доставки</label>
                                            <select id="delivery-transport-option-id" name="transport_option_id" class="app-select text-sm" required>
                                                <option value="" disabled @selected(! old('transport_option_id', $application->transport_option_id))>Выберите способ доставки</option>
                                                @foreach(($transportOptions ?? collect()) as $transportOption)
                                                    <option value="{{ $transportOption->id }}" @selected((string) old('transport_option_id', $application->transport_option_id) === (string) $transportOption->id)>
                                                        {{ $transportOption->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap">
                                            Отметить всё как «В пути»
                                        </button>
                                    </form>
                                    <ul class="space-y-2">
                                        @foreach($inTransitCandidates->sortBy('id') as $deliveryItem)
                                            <li class="flex flex-col gap-2 rounded-md border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-950/60 px-3 py-2">
                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                                    <span class="text-sm text-black dark:text-white">
                                                        {{ $deliveryItem->equipment_display_name }} × {{ $deliveryItem->quantity_with_unit }}
                                                    </span>
                                                    @include('applications.partials.custom-equipment-supply-badge', ['item' => $deliveryItem])
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @else
                            @if($boilerUncheckedItems->isNotEmpty() && $application->needsBoilerChiefReviewBeforeManagement())
                                <h4 class="app-form-label !normal-case !mb-2">Не согласовано начальником котельной</h4>
                                <ul class="mb-6 divide-y divide-stone-200 overflow-hidden rounded-xl border border-amber-200/80 dark:divide-stone-700 dark:border-amber-800/50">
                                    @foreach($boilerUncheckedItems->sortBy('id') as $item)
                                        <li class="px-4 py-3 bg-amber-50/50 dark:bg-amber-950/20 space-y-1">
                                            <span class="text-sm font-medium text-black dark:text-white">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @if($application->itemLineBoilerChiefRejectionReason($item->id))
                                                <p class="mt-1 text-sm text-black dark:text-white"><span class="font-medium">Причина:</span> {{ $application->itemLineBoilerChiefRejectionReason($item->id) }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($uncheckedItems->isNotEmpty())
                                <h4 class="app-form-label !normal-case !mb-2">Не согласовано</h4>
                                <ul class="mb-6 divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
                                    @foreach($uncheckedItems->sortBy('id') as $item)
                                        <li class="px-4 py-3 bg-stone-50/80 dark:bg-stone-900/25 space-y-1">
                                            <span class="text-sm font-medium text-black dark:text-white">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                                            @if($application->itemLineRejectionReason($item->id))
                                                <p class="mt-1 text-sm text-black dark:text-white"><span class="font-medium text-black dark:text-white">Причина:</span> {{ $application->itemLineRejectionReason($item->id) }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if($checkedItems->isNotEmpty())
                                <h4 class="app-form-label !normal-case !mb-2">Согласовано</h4>
                                <ul class="divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
                                    @foreach($checkedItems->sortBy('id') as $item)
                                        <li class="px-4 py-3 bg-stone-100/60 dark:bg-stone-900/30 space-y-1">
                                            <span class="text-sm font-medium text-black dark:text-white">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @include('applications.partials.custom-equipment-supply-badge', ['item' => $item])
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if($chiefCanMarkDelivered && $chiefDeliveryCandidates->isNotEmpty())
                                <div class="mt-4 space-y-3 rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-4 dark:border-emerald-800/50 dark:bg-emerald-950/25">
                                    <p class="text-xs text-black dark:text-white">
                                        Для позиций <span class="font-medium">«В пути»</span> подразделение получения уже задано заявкой и мастером участка — выберите только <span class="font-medium">склад</span> этого подразделения, на который фактически поступило оборудование.
                                    </p>
                                    <ul class="space-y-2">
                                        @foreach($chiefDeliveryCandidates->sortBy('id') as $deliveryItem)
                                            @php
                                                $targetSubId = $deliveryItem->resolvedDeliveryTargetSubdivisionId();
                                                $subForChief = ($boilerChiefDeliverySubdivisions ?? collect())->firstWhere('id', $targetSubId);
                                            @endphp
                                            <li class="rounded-md border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-950/60 px-3 py-2 space-y-2">
                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                                    <span class="text-sm text-black dark:text-white">
                                                        {{ $deliveryItem->equipment_display_name }} × {{ $deliveryItem->quantity_with_unit }}
                                                    </span>
                                                    @include('applications.partials.custom-equipment-supply-badge', ['item' => $deliveryItem])
                                                </div>
                                                @if($subForChief)
                                                    <form method="POST" action="{{ route('applications.delivery-delivered', [$application, $deliveryItem]) }}" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                                                        @csrf
                                                        <div class="min-w-0 flex-1 space-y-1">
                                                            <p class="text-xs text-black dark:text-white">
                                                                <span class="font-medium">Подразделение:</span> {{ $subForChief->name }}
                                                            </p>
                                                            @if($deliveryItem->custom_target_warehouse_id && $deliveryItem->customTargetWarehouse)
                                                                <p class="text-[11px] text-stone-600 dark:text-stone-400">
                                                                    Мастер указал склад: {{ $deliveryItem->customTargetWarehouse->name }}
                                                                </p>
                                                            @endif
                                                            <label class="app-form-label !normal-case text-xs" for="delivery-wh-{{ $deliveryItem->id }}">Склад поступления</label>
                                                            <select name="delivery_warehouse_id" id="delivery-wh-{{ $deliveryItem->id }}" class="app-select text-sm w-full max-w-md" required>
                                                                <option value="" disabled @selected(! old('delivery_warehouse_id'))>Выберите склад</option>
                                                                @foreach($subForChief->warehouses->sortBy('name') as $warehouse)
                                                                    <option
                                                                        value="{{ $warehouse->id }}"
                                                                        @selected((string) old('delivery_warehouse_id', (string) $deliveryItem->custom_target_warehouse_id) === (string) $warehouse->id)
                                                                    >
                                                                        {{ $warehouse->code }} — {{ $warehouse->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('delivery_warehouse_id')
                                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                                            @enderror
                                                        </div>
                                                        <div class="shrink-0">
                                                            <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap w-full sm:w-auto">
                                                                Доставлено
                                                            </button>
                                                        </div>
                                                    </form>
                                                @else
                                                    <p class="text-xs text-amber-800 dark:text-amber-100">
                                                        Подразделение заявки не совпадает с вашей зоной ответственности — отметку доставки оформить нельзя.
                                                    </p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif

                        @if(Auth::user()->hasRoleId(4))
                            @php
                                $foremanCustomItems = $application->items->filter(function ($i) {
                                    if (! $i->usesFreeTextEquipment() || ! $i->is_checked || $i->equipment_id !== null) {
                                        return false;
                                    }

                                    return $i->canSaveCustomTargetWarehouseForForeman()
                                        || $i->canMarkCustomForemanInTransitToTarget()
                                        || $i->custom_foreman_in_transit;
                                });
                            @endphp
                            @if($foremanCustomItems->isNotEmpty())
                                <div class="mt-4 space-y-3 rounded-xl border border-sky-200/80 bg-sky-50/50 p-4 dark:border-sky-800/50 dark:bg-sky-950/25">
                                    <h4 class="app-section-title text-xs sm:text-sm">Своё оборудование: склад получения</h4>
                                    <p class="text-xs text-black dark:text-white">
                                        Укажите склад подразделения, на который заказана поставка. После того как снабжение отметит заказ, нажмите <span class="font-medium">«В пути на выбранный склад»</span>, чтобы зафиксировать движение к вашему складу.
                                    </p>
                                    @if($application->subdivision && $application->subdivision->warehouses->isEmpty())
                                        <p class="text-xs text-amber-800 dark:text-amber-100">
                                            У подразделения нет складов в справочнике — добавьте склад в настройках подразделений, чтобы выбрать получателя.
                                        </p>
                                    @endif
                                    <ul class="space-y-3">
                                        @foreach($foremanCustomItems->sortBy('id') as $fItem)
                                            <li class="rounded-md border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-950/60 px-3 py-2 space-y-2">
                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                                    <span class="text-sm font-medium text-black dark:text-white">
                                                        {{ $fItem->equipment_display_name }} × {{ $fItem->quantity_with_unit }}
                                                    </span>
                                                    @include('applications.partials.custom-equipment-supply-badge', ['item' => $fItem])
                                                </div>
                                                @if($fItem->custom_foreman_in_transit && $fItem->customForemanTransitSummary())
                                                    <p class="text-xs font-medium text-sky-900 dark:text-sky-100">
                                                        {{ $fItem->customForemanTransitSummary() }}
                                                    </p>
                                                @endif
                                                @if($fItem->canSaveCustomTargetWarehouseForForeman())
                                                    <form method="POST" action="{{ route('applications.custom-target-warehouse', [$application, $fItem]) }}" class="flex flex-col sm:flex-row gap-2 sm:items-end">
                                                        @csrf
                                                        <div class="min-w-0 flex-1">
                                                            <label class="app-form-label !normal-case text-xs" for="custom-target-wh-{{ $fItem->id }}">Склад получения</label>
                                                            <select name="custom_target_warehouse_id" id="custom-target-wh-{{ $fItem->id }}" class="app-select text-sm w-full" required>
                                                                <option value="" disabled @selected(! $fItem->custom_target_warehouse_id)>Выберите склад</option>
                                                                @foreach($application->subdivision->warehouses->sortBy('name') as $wh)
                                                                    <option value="{{ $wh->id }}" @selected((int) $fItem->custom_target_warehouse_id === (int) $wh->id)>
                                                                        {{ $wh->code }} — {{ $wh->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap shrink-0">
                                                            Сохранить склад
                                                        </button>
                                                    </form>
                                                @elseif($fItem->customTargetWarehouse)
                                                    <p class="text-xs text-black dark:text-white">
                                                        Склад получения: <span class="font-medium">{{ $fItem->customTargetWarehouse->name }}</span>
                                                    </p>
                                                @endif
                                                @if($fItem->canMarkCustomForemanInTransitToTarget())
                                                    <form method="POST" action="{{ route('applications.custom-foreman-in-transit', [$application, $fItem]) }}" class="flex justify-end">
                                                        @csrf
                                                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap">
                                                            В пути на выбранный склад
                                                        </button>
                                                    </form>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif

                    </section>

                </div>
            </div>
    </div>
</x-app-layout>
