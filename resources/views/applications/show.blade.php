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
                @elseif($application->isApprovedDeliveryFullyInTransit())
                    <span class="inline-flex items-center rounded-full border border-orange-300/90 bg-orange-50/90 px-2.5 py-0.5 text-xs font-medium text-orange-950 dark:border-orange-800 dark:bg-orange-950/40 dark:text-orange-100 shrink-0" title="Все согласованные позиции в пути">
                        В пути
                    </span>
                @elseif($application->isStatusApproved())
                    <span class="inline-flex items-center rounded-full bg-stone-200 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/60 dark:text-white shrink-0">Согласована</span>
                @elseif($application->isStatusPartial())
                    <span class="inline-flex items-center rounded-full bg-stone-200/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white shrink-0">Частично согласована</span>
                @elseif($application->isCreatorDraftApplication())
                    <span class="inline-flex items-center rounded-full bg-stone-200/90 px-2.5 py-0.5 text-xs font-medium text-stone-800 dark:bg-stone-800/60 dark:text-stone-200 shrink-0">Черновик</span>
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
            <div class="app-actions-row">
                @if (! $application->archived_at
                    && ! $application->managementHasSavedApproval()
                    && Auth::user()->hasAnyRoleId([1, 6, 2, 4, 7])
                    && ! $application->items->contains(fn ($i) => in_array($i->resolvedDeliveryStatus(), [\App\Models\ApplicationItem::DELIVERY_IN_TRANSIT, \App\Models\ApplicationItem::DELIVERY_DELIVERED], true))
                    && (! Auth::user()->hasRoleId(4) || $application->foremanCanEditApplication())
                    && (! Auth::user()->hasRoleId(7) || $application->boilerChiefCanEditApplication()))
                    <a href="{{ route('applications.edit', $application) }}" class="ui-btn ui-btn--primary whitespace-nowrap shrink-0">
                        Изменить
                    </a>
                @endif
                @if ($canForemanSubmitToBoilerChief ?? false)
                    <button
                        type="button"
                        class="ui-btn ui-btn--primary whitespace-nowrap shrink-0"
                        data-app-open-modal="confirm-submit-boiler-chief"
                    >
                        Отправить на согласование
                    </button>
                @endif
                @if ($canBoilerChiefSubmitForManagement ?? false)
                    <button
                        type="button"
                        class="ui-btn ui-btn--primary whitespace-nowrap shrink-0"
                        data-app-open-modal="confirm-submit-for-management"
                    >
                        Отправить на согласование
                    </button>
                @endif
                @if($canChangeApplicationResponsible ?? false)
                    <a href="{{ route('applications.responsible.edit', $application) }}" class="ui-btn ui-btn--secondary whitespace-nowrap shrink-0">
                        Изменить ответственного
                    </a>
                @endif
                @if (((! $application->archived_at && Auth::user()->hasAnyRoleId([1, 6, 2, 7])) || Auth::user()->hasRoleId(4)) && $application->canUploadInstallationActAndPhotos())
                    <a href="{{ route('applications.installation-act.upload', ['application_id' => $application->id]) }}" class="ui-btn ui-btn--secondary whitespace-nowrap shrink-0">
                        Акт установки
                    </a>
                @endif
                @if (Auth::user()->hasAnyRoleId([4, 7]))
                    <a
                        id="application-repeat-link"
                        href="{{ route('applications.repeat', $application) }}"
                        class="sr-only"
                        tabindex="-1"
                        aria-hidden="true"
                    >Создать повторную</a>
                    <button
                        type="button"
                        class="ui-btn ui-btn--primary whitespace-nowrap shrink-0"
                        data-app-open-modal="confirm-repeat-application"
                    >
                        Создать повторную
                    </button>
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
            @error('submit')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @if($application->archived_at)
                <div class="mb-4 rounded-xl border border-emerald-200/90 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100 space-y-2">
                    <p>Заявка находится в архиве выполненных (актуальные документы и списания по складу зафиксированы). Дата архивации: {{ $application->archived_at->format('d.m.Y H:i') }}.</p>
                    @if(Auth::user()->hasAnyRoleId([4, 7]))
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
            @error('delivery')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('delivery_warehouse_id')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('delivery_bulk_warehouse_id')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('transport_option_id')
                <div class="mb-4 rounded-xl border border-red-200/80 bg-red-50/80 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $message }}
                </div>
            @enderror
            @error('vehicle_plate')
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
            @if(Auth::user()->hasRoleId(7) && $application->user && $application->user->hasRoleId(4) && $application->user->is_blocked)
                <div class="mb-4 rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                    <p class="font-medium">Автор заявки (мастер участка) заблокирован</p>
                    <p class="mt-1">Заявка доступна вам для ведения и согласования. Чтобы передать её другому мастеру <strong>этого же подразделения</strong> (как у заблокированного автора по этой заявке), откройте «Изменить» и в блоке «Автор заявки» выберите активного мастера из списка закрепления за подразделением.</p>
                </div>
            @endif
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
                                        @if($application->user->hasRoleId(4) && $application->user->is_blocked)
                                            <span class="ml-1 inline-flex items-center rounded-full bg-amber-200/90 px-2 py-0.5 text-[11px] font-semibold text-amber-950 dark:bg-amber-900/50 dark:text-amber-100">заблокирован</span>
                                        @endif
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
                                        @if($application->approvedBy->role?->name)
                                            <span class="block text-xs font-normal opacity-80 mt-0.5">{{ $application->approvedBy->role->name }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="app-form-label !normal-case">Коммерческое предложение</dt>
                                <dd class="mt-0.5 text-sm font-medium text-black dark:text-white">
                                    @if($application->commercial_offer)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ basename($application->commercial_offer) }}</span>
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
                                    @if($application->act_of_installation)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ basename($application->act_of_installation) }}</span>
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

                    @php
                        $approvalLockedAfterTransit = $application->items->contains(
                            fn ($i) => in_array($i->resolvedDeliveryStatus(), [\App\Models\ApplicationItem::DELIVERY_IN_TRANSIT, \App\Models\ApplicationItem::DELIVERY_DELIVERED], true)
                        );
                        $canManagementApprove = Auth::user()->hasAnyRoleId([1, 6, 2])
                            && ! $application->needsBoilerChiefReviewBeforeManagement()
                            && $application->managementMayReviewAfterBoilerChief()
                            && ! $application->managementHasSavedApproval();
                        if ($approvalLockedAfterTransit) {
                            $canManagementApprove = false;
                        }
                        $canBoilerChiefApprove = Auth::user()->hasRoleId(7)
                            && $application->needsBoilerChiefReviewBeforeManagement()
                            && ! $application->boilerChiefReleasedToManagement();
                        $uncheckedItems = $application->items->filter(fn ($i) => ! $application->itemLineIsApproved($i->id));
                        $checkedItems = $application->items->filter(fn ($i) => $application->itemLineIsApproved($i->id));
                        $boilerUncheckedItems = $application->items->filter(fn ($i) => ! $i->is_checked);
                        $subdivisionHasBoilerChiefForReadonly = \App\Models\Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id);
                        $postBoilerChiefFrozenUncheckedReadonly = $subdivisionHasBoilerChiefForReadonly
                            && ! $application->needsBoilerChiefReviewBeforeManagement();
                        $itemsRejectedByBoilerChiefReadonly = $postBoilerChiefFrozenUncheckedReadonly
                            ? $boilerUncheckedItems
                            : collect();
                        $rejectedByBoilerChiefReadonlyIds = $itemsRejectedByBoilerChiefReadonly->pluck('id')->all();
                        $uncheckedItemsForDisplay = $rejectedByBoilerChiefReadonlyIds === []
                            ? $uncheckedItems
                            : $uncheckedItems->reject(fn ($i) => in_array($i->id, $rejectedByBoilerChiefReadonlyIds, true));
                        $awaitingBoilerChiefApprovalList = $application->needsBoilerChiefReviewBeforeManagement()
                            && $boilerUncheckedItems->isNotEmpty();
                        if ($awaitingBoilerChiefApprovalList) {
                            $boilerPendingItemIds = $boilerUncheckedItems->pluck('id')->all();
                            $uncheckedItemsForDisplay = $uncheckedItemsForDisplay->reject(
                                fn ($i) => in_array($i->id, $boilerPendingItemIds, true)
                            );
                        }
                        $canManageDeliveryTransit = Auth::user()->hasAnyRoleId([1, 6, 2]);
                        $inTransitCandidates = $application->items->filter(fn ($i) => $i->canMarkDeliveryInTransit());
                        $showCatalogDeliveryInTransitForm = $canManageDeliveryTransit
                            && $application->managementHasSavedApproval()
                            && $inTransitCandidates->isNotEmpty();
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
                                            $oldBc = old("boiler_items.{$item->id}.is_checked", $application->itemLineIsApproved($item->id) ? '1' : '0');
                                            $isBcCheckedOld = (string) $oldBc === '1';
                                        @endphp
                                        <li class="bc-approval-row space-y-2 bg-white px-4 py-3 dark:bg-stone-900/40">
                                            <div class="flex items-start gap-3">
                                                <input type="hidden" name="boiler_items[{{ $item->id }}][is_checked]" value="0">
                                                <input type="checkbox"
                                                    name="boiler_items[{{ $item->id }}][is_checked]"
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
                                                    name="boiler_items[{{ $item->id }}][reason_not_selected]"
                                                    value="{{ $isBcCheckedOld ? '' : old("boiler_items.{$item->id}.reason_not_selected", $application->itemLineRejectionReason($item->id) ?? '') }}"
                                                    placeholder="Обязательно, если позиция не согласована"
                                                    maxlength="500"
                                                    class="bc-approval-reason-input app-input text-sm @error('boiler_items.'.$item->id.'.reason_not_selected') !border-red-500 dark:!border-red-400 @enderror"
                                                />
                                                @error('boiler_items.'.$item->id.'.reason_not_selected')
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
                            @php
                                $subdivisionHasBoilerChief = \App\Models\Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id);
                                $supplyManagementItems = $application->items;
                                $stockByItem = $catalogStockOnMainWarehouseByItemId ?? [];
                                $supplyAwaitingPostBoilerManagementSave = $subdivisionHasBoilerChief
                                    && ! $application->needsBoilerChiefReviewBeforeManagement()
                                    && $application->management_supply_items_saved_at === null;
                                $splitSupplyFormForBoilerFrozen = $subdivisionHasBoilerChief
                                    && ! $application->needsBoilerChiefReviewBeforeManagement();
                                if ($splitSupplyFormForBoilerFrozen) {
                                    $itemsFrozenAsBoilerRejectedForSupply = $supplyManagementItems->filter(
                                        fn ($i) => ! (bool) $i->is_checked
                                    );
                                    $supplyManagementItemsInteractive = $supplyManagementItems->filter(
                                        fn ($i) => (bool) $i->is_checked
                                    );
                                } else {
                                    $itemsFrozenAsBoilerRejectedForSupply = collect();
                                    $supplyManagementItemsInteractive = $supplyManagementItems;
                                }
                            @endphp
                            @if($supplyAwaitingPostBoilerManagementSave)
                                
                            @elseif(! $application->approved_by_user_id)
                                <div class="mb-4 rounded-xl border border-stone-200/90 bg-stone-50/80 p-3 text-xs text-black dark:border-stone-600 dark:bg-stone-800/35 dark:text-white">
                                    Сохраните согласование по позициям — после этого станут доступны доставка («В пути») и форма заказа своего оборудования.
                                </div>
                            @endif
                            <form method="POST" action="{{ route('applications.approval', $application) }}" id="approval-form" class="space-y-4">
                                @csrf

                                @if($itemsFrozenAsBoilerRejectedForSupply->isNotEmpty())
                                    <div class="space-y-2 rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 dark:border-amber-800/50 dark:bg-amber-950/20">
                                        <h4 class="app-form-label !normal-case !mb-0">Не согласовано</h4>
                                        <p class="text-xs text-black/80 dark:text-white/75">
                                            Эти позиции уже не в согласовании снабжения на этом шаге — только просмотр. Ниже отмечайте и сохраняйте согласование только по остальным позициям.
                                        </p>
                                        <ul class="divide-y divide-amber-200/80 overflow-hidden rounded-lg border border-amber-200/70 dark:divide-amber-800/40 dark:border-amber-800/40">
                                            @foreach($itemsFrozenAsBoilerRejectedForSupply->sortBy('id') as $item)
                                                <li class="space-y-1 bg-white/90 px-3 py-2.5 dark:bg-stone-900/50">
                                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                        <span class="text-sm font-medium text-black dark:text-white">
                                                            {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                                        </span>
                                                        <span class="text-[11px] font-medium uppercase tracking-wide text-amber-900/90 dark:text-amber-100/90">не в согласовании снабжения</span>
                                                    </div>
                                                    @if($application->itemLineRejectionReason($item->id))
                                                        <p class="text-xs text-black dark:text-white">
                                                            <span class="font-medium">Причина:</span> {{ $application->itemLineRejectionReason($item->id) }}
                                                        </p>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                        @foreach($itemsFrozenAsBoilerRejectedForSupply->sortBy('id') as $item)
                                            <input type="hidden" name="items[{{ $item->id }}][is_checked]" value="0">
                                            <input type="hidden" name="items[{{ $item->id }}][reason_not_selected]" value="{{ old('items.'.$item->id.'.reason_not_selected', $item->reason_not_selected ?? '') }}">
                                        @endforeach
                                    </div>
                                @endif

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
                                @if($supplyManagementItemsInteractive->isEmpty())
                                    <p class="rounded-lg border border-stone-200/90 bg-stone-50/80 px-3 py-2 text-xs text-black dark:border-stone-600 dark:bg-stone-800/35 dark:text-white">
                                        Нет позиций для согласования снабжением на этом шаге — все строки уже не согласованы. Нажмите «Сохранить согласование», чтобы зафиксировать состояние заявки.
                                    </p>
                                @else
                                <ul class="divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
                                    @foreach($supplyManagementItemsInteractive->sortBy('id') as $item)
                                        @php
                                            $legacyDefaultChecked = $application->itemLineIsApproved($item->id) ? '1' : '0';
                                            $defaultChecked = ($supplyAwaitingPostBoilerManagementSave && $application->itemLineIsApproved($item->id)) ? '0' : $legacyDefaultChecked;
                                            $oldChecked = old("items.{$item->id}.is_checked", $defaultChecked);
                                            $isCheckedOld = (string) $oldChecked === '1';
                                            $stockMain = $stockByItem[(int) $item->id] ?? null;
                                            $unitLabel = $item->quantityUnitLabelForDisplay();
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
                                                    @if($stockMain !== null && ($mainWarehouse ?? null))
                                                        @php
                                                            $reqQty = (float) $item->quantity;
                                                            $toOrder = max(0.0, $reqQty - (float) $stockMain);
                                                            $fmt = static function (float $v): string {
                                                                if (abs($v - round($v)) < 0.0005) {
                                                                    return (string) (int) round($v);
                                                                }

                                                                return rtrim(rtrim(number_format($v, 3, ',', ' '), '0'), ',');
                                                            };
                                                        @endphp
                                                        <p class="text-xs text-black/80 dark:text-white/75">
                                                            Доступно на основном складе «{{ $mainWarehouse->name }}» (с учётом резерва других заявок): {{ $fmt((float) $stockMain) }} {{ $unitLabel }}.
                                                            К дозаказу: {{ $fmt($toOrder) }} {{ $unitLabel }} (по заявке {{ $fmt($reqQty) }} {{ $unitLabel }}).
                                                        </p>
                                                    @endif
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
                                @endif
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
                            @if($hasCustomEquipmentOrderForm && $application->approved_by_user_id && ! $supplyAwaitingPostBoilerManagementSave && Auth::user()->hasAnyRoleId(\App\Models\User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS))
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
                        @else
                            @if($awaitingBoilerChiefApprovalList)
                                <h4 class="app-form-label !normal-case !mb-2">Не согласовано</h4>
                                <p class="mb-2 text-xs text-black/80 dark:text-white/75">
                                    Ожидается согласование начальником котельной.
                                </p>
                                <ul class="mb-6 divide-y divide-stone-200 overflow-hidden rounded-xl border border-amber-200/80 dark:divide-stone-700 dark:border-amber-800/50">
                                    @foreach($boilerUncheckedItems->sortBy('id') as $item)
                                        <li class="px-4 py-3 bg-amber-50/50 dark:bg-amber-950/20 space-y-1">
                                            <span class="text-sm font-medium text-black dark:text-white">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @if($application->itemLineRejectionReason($item->id))
                                                <p class="mt-1 text-sm text-black dark:text-white"><span class="font-medium">Причина:</span> {{ $application->itemLineRejectionReason($item->id) }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif($itemsRejectedByBoilerChiefReadonly->isNotEmpty())
                                <h4 class="app-form-label !normal-case !mb-2">Не согласовано</h4>
                                <p class="mb-2 text-xs text-black/80 dark:text-white/75">
                                    @if(Auth::user()->hasRoleId(7))
                                        Эти позиции уже зафиксированы как не согласованные; изменить отметку на этом этапе нельзя. Дальнейшее согласование по остальным строкам — у директора, технического директора и снабжения (кнопка «Сохранить согласование»).
                                    @else
                                        По этим позициям согласование не оформлено; в списке для снабжения они не участвуют. Остальные строки согласуют директор, технический директор и снабжение после «Сохранить согласование».
                                    @endif
                                </p>
                                <ul class="mb-6 divide-y divide-stone-200 overflow-hidden rounded-xl border border-amber-200/80 dark:divide-stone-700 dark:border-amber-800/50">
                                    @foreach($itemsRejectedByBoilerChiefReadonly->sortBy('id') as $item)
                                        <li class="px-4 py-3 bg-amber-50/50 dark:bg-amber-950/20 space-y-1">
                                            <span class="text-sm font-medium text-black dark:text-white">
                                                {{ $item->equipment_display_name }} × {{ $item->quantity_with_unit }}
                                            </span>
                                            @if($application->itemLineRejectionReason($item->id))
                                                <p class="mt-1 text-sm text-black dark:text-white"><span class="font-medium">Причина:</span> {{ $application->itemLineRejectionReason($item->id) }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($uncheckedItemsForDisplay->isNotEmpty())
                                <h4 class="app-form-label !normal-case !mb-2">Не согласовано</h4>
                                <ul class="mb-6 divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200/90 dark:divide-stone-700 dark:border-stone-600">
                                    @foreach($uncheckedItemsForDisplay->sortBy('id') as $item)
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
                                   
                                    @php
                                        $deliveryGroups = $chiefDeliveryCandidates
                                            ->sortBy('id')
                                            ->groupBy(fn ($deliveryItem) => (string) ($deliveryItem->resolvedDeliveryTargetSubdivisionId() ?? 0));
                                    @endphp
                                    @foreach($deliveryGroups as $targetSubId => $groupItems)
                                        @php
                                            $targetSubIdInt = (int) $targetSubId;
                                            $groupSubdivision = ($boilerChiefDeliverySubdivisions ?? collect())->firstWhere('id', $targetSubIdInt);
                                        @endphp
                                        @if($groupSubdivision)
                                            <form method="POST"
                                                  action="{{ route('applications.delivery-delivered.bulk', $application) }}"
                                                  class="space-y-3 rounded-lg border border-emerald-200/80 bg-white/80 p-3 dark:border-emerald-900/50 dark:bg-stone-950/45">
                                                @csrf
                                                <p class="text-xs text-black dark:text-white app-equipment-line">
                                                    <span class="font-medium">Отметить вместе для подразделения:</span> {{ $groupSubdivision->name }}
                                                </p>
                                                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                                                    @foreach($groupItems as $groupItem)
                                                        <input type="hidden" name="item_ids[]" value="{{ $groupItem->id }}">
                                                    @endforeach
                                                    <div class="min-w-0 flex-1">
                                                        <label class="app-form-label !normal-case text-xs" for="delivery-wh-bulk-{{ $targetSubIdInt }}">Склад поступления (для всех позиций блока)</label>
                                                        <select name="delivery_bulk_warehouse_id" id="delivery-wh-bulk-{{ $targetSubIdInt }}" class="app-select text-sm w-full sm:max-w-md" required>
                                                            <option value="" disabled @selected(! old('delivery_bulk_warehouse_id'))>Выберите склад</option>
                                                            @foreach($groupSubdivision->warehouses->sortBy('name') as $warehouse)
                                                                <option value="{{ $warehouse->id }}" @selected((string) old('delivery_bulk_warehouse_id') === (string) $warehouse->id)>
                                                                    {{ $warehouse->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="w-full shrink-0 sm:w-auto">
                                                        <button type="submit" class="ui-btn ui-btn--secondary ui-btn--sm w-full whitespace-normal sm:w-auto sm:whitespace-nowrap">
                                                            Доставлено для всех в блоке
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        @endif
                                    @endforeach
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
                                                            <p class="text-xs text-black dark:text-white app-equipment-line">
                                                                <span class="font-medium">Подразделение:</span> {{ $subForChief->name }}
                                                            </p>
                                                            <label class="app-form-label !normal-case text-xs" for="delivery-wh-{{ $deliveryItem->id }}">Склад поступления</label>
                                                            <select name="delivery_warehouse_id" id="delivery-wh-{{ $deliveryItem->id }}" class="app-select text-sm w-full sm:max-w-md" required>
                                                                <option value="" disabled @selected(! old('delivery_warehouse_id'))>Выберите склад</option>
                                                                @foreach($subForChief->warehouses->sortBy('name') as $warehouse)
                                                                    <option
                                                                        value="{{ $warehouse->id }}"
                                                                        @selected((string) old('delivery_warehouse_id', (string) $deliveryItem->delivery_warehouse_id) === (string) $warehouse->id)
                                                                    >
                                                                        {{ $warehouse->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('delivery_warehouse_id')
                                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                                            @enderror
                                                        </div>
                                                        <div class="w-full shrink-0 sm:w-auto">
                                                            <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm w-full whitespace-nowrap sm:w-auto">
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

                        @if($showCatalogDeliveryInTransitForm)
                            <div class="mt-4 space-y-3 rounded-xl border border-orange-200/80 bg-orange-50/60 p-4 dark:border-orange-800/50 dark:bg-orange-950/25">
                                @php
                                    $bulkMethodId = $application->transportMethodOptionIdForDeliveryForm();
                                    $bulkPlate = trim((string) ($application->transportOption?->plate ?? ''));
                                @endphp
                                <form method="POST" action="{{ route('applications.delivery-in-transit', $application) }}" id="delivery-in-transit-form" class="flex flex-col gap-4">
                                    @csrf

                                    @if($inTransitCandidates->count() > 1)
                                        <div class="rounded-xl border border-stone-200/90 bg-white/90 p-3 dark:border-stone-600 dark:bg-stone-950/50 space-y-3" data-delivery-transport-block id="delivery-bulk-block">
                                            <p class="text-xs font-medium text-black dark:text-white">Для всех позиций сразу</p>
                                            @include('applications.partials.delivery-transport-fields', [
                                                'fieldUid' => 'delivery-bulk',
                                                'selectedMethodId' => $bulkMethodId,
                                                'vehiclePlateValue' => $bulkPlate,
                                                'transportOptions' => $transportOptions,
                                                'serviceVehiclePlateOptions' => $serviceVehiclePlateOptions,
                                            ])
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" id="delivery-apply-bulk" class="ui-btn ui-btn--secondary ui-btn--sm">
                                                    Применить ко всем позициям
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-3">
                                        @if($inTransitCandidates->count() > 1)
                                            <label class="inline-flex items-center gap-2 text-sm text-black dark:text-white cursor-pointer">
                                                <input type="checkbox" id="delivery-select-all" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500" checked>
                                                Выбрать все позиции
                                            </label>
                                        @endif
                                    </div>

                                    <ul class="space-y-3">
                                        @foreach($inTransitCandidates->sortBy('id') as $deliveryItem)
                                            @php
                                                $itemId = (int) $deliveryItem->id;
                                                $oldRow = old("items.{$itemId}", []);
                                                $itemMarked = array_key_exists('mark', $oldRow) ? filled($oldRow['mark']) : true;
                                                $itemMethodId = $oldRow['transport_option_id'] ?? $deliveryItem->transportMethodOptionIdForDeliveryForm() ?? $bulkMethodId;
                                                $itemPlate = $oldRow['vehicle_plate'] ?? trim((string) ($deliveryItem->transportOption?->plate ?? $bulkPlate));
                                            @endphp
                                            <li class="delivery-in-transit-row rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-950/60 p-3 space-y-3" data-delivery-transport-block data-item-id="{{ $itemId }}">
                                                @if($inTransitCandidates->count() === 1)
                                                    <input type="hidden" name="items[{{ $itemId }}][mark]" value="1">
                                                    <p class="text-sm font-medium text-black dark:text-white">
                                                        {{ $deliveryItem->equipment_display_name }} × {{ $deliveryItem->quantity_with_unit }}
                                                    </p>
                                                @else
                                                    <label class="flex items-start gap-2 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            class="delivery-item-mark mt-0.5 rounded border-stone-300 text-orange-600 focus:ring-orange-500"
                                                            name="items[{{ $itemId }}][mark]"
                                                            value="1"
                                                            @checked($itemMarked)
                                                        >
                                                        <span class="text-sm font-medium text-black dark:text-white">
                                                            {{ $deliveryItem->equipment_display_name }} × {{ $deliveryItem->quantity_with_unit }}
                                                        </span>
                                                    </label>
                                                @endif
                                                @include('applications.partials.delivery-transport-fields', [
                                                    'fieldUid' => 'delivery-item-'.$itemId,
                                                    'nameTransport' => "items[{$itemId}][transport_option_id]",
                                                    'namePlate' => "items[{$itemId}][vehicle_plate]",
                                                    'selectedMethodId' => $itemMethodId,
                                                    'vehiclePlateValue' => $itemPlate,
                                                    'transportOptions' => $transportOptions,
                                                    'serviceVehiclePlateOptions' => $serviceVehiclePlateOptions,
                                                ])
                                                @if($errors->has("items.{$itemId}.transport_option_id") || $errors->has("items.{$itemId}.vehicle_plate"))
                                                    <ul class="text-xs text-red-700 dark:text-red-300 space-y-0.5">
                                                        @foreach($errors->get("items.{$itemId}.transport_option_id") as $message)
                                                            <li>{{ $message }}</li>
                                                        @endforeach
                                                        @foreach($errors->get("items.{$itemId}.vehicle_plate") as $message)
                                                            <li>{{ $message }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>

                                    <p class="text-xs text-black/70 dark:text-white/70">
                                        Для каждой позиции можно указать свой способ доставки и машину. Госномер вводится с пробелами, например <span class="font-medium">А 123 ВС 77</span>.
                                    </p>

                                    <div class="flex flex-wrap justify-end">
                                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm whitespace-nowrap">
                                            @if($inTransitCandidates->count() > 1)
                                                Отметить выбранные как «В пути»
                                            @else
                                                Отметить как «В пути»
                                            @endif
                                        </button>
                                    </div>
                                </form>

                                <script>
                                    (function () {
                                        const form = document.getElementById('delivery-in-transit-form');
                                        if (!form) {
                                            return;
                                        }
                                        const selfPickup = @json(\App\Models\TransportOption::NAME_SELF_PICKUP);
                                        const serviceVehicle = @json(\App\Models\TransportOption::NAME_SERVICE_VEHICLE);
                                        const plateLetters = @json(\App\Support\RussianVehiclePlate::CYRILLIC_LETTERS);
                                        const latinToCyr = { A: 'А', B: 'В', E: 'Е', K: 'К', M: 'М', H: 'Н', O: 'О', P: 'Р', C: 'С', T: 'Т', Y: 'У', X: 'Х' };

                                        function normalizePlateLetter(ch) {
                                            const upper = String(ch ?? '').toUpperCase();
                                            return latinToCyr[upper] ?? upper;
                                        }

                                        function filterRussianPlateInput(raw) {
                                            let out = '';
                                            const s = String(raw ?? '').toUpperCase().replace(/[\s\-]+/g, '');
                                            for (const ch of s) {
                                                const c = normalizePlateLetter(ch);
                                                const len = out.length;
                                                if (len === 0) {
                                                    if (plateLetters.includes(c)) {
                                                        out += c;
                                                    }
                                                } else if (len <= 3) {
                                                    if (/\d/.test(c)) {
                                                        out += c;
                                                    }
                                                } else if (len <= 5) {
                                                    if (plateLetters.includes(c)) {
                                                        out += c;
                                                    }
                                                } else if (len <= 8) {
                                                    if (/\d/.test(c)) {
                                                        out += c;
                                                    }
                                                }
                                                if (out.length >= 9) {
                                                    break;
                                                }
                                            }
                                            return out;
                                        }

                                        function formatPlateWithSpaces(compact) {
                                            const p = filterRussianPlateInput(compact);
                                            if (!p) {
                                                return '';
                                            }
                                            return [p.slice(0, 1), p.slice(1, 4), p.slice(4, 6), p.slice(6)].filter(Boolean).join(' ');
                                        }

                                        function initTransportBlock(block) {
                                            const methodSelect = block.querySelector('[data-delivery-method]');
                                            const plateField = block.querySelector('[data-delivery-plate-field]');
                                            const plateText = block.querySelector('[data-delivery-plate-text]');
                                            const plateSelect = block.querySelector('[data-delivery-plate-select]');
                                            if (!methodSelect) {
                                                return;
                                            }

                                            function syncPlateField() {
                                                if (!plateField || !plateText || !plateSelect) {
                                                    return;
                                                }
                                                const name = methodSelect.selectedOptions[0]?.dataset?.transportName?.trim() ?? '';
                                                const plateName = plateText.getAttribute('name') || plateSelect.getAttribute('name');
                                                if (name === selfPickup) {
                                                    plateField.classList.add('hidden');
                                                    plateText.required = false;
                                                    plateText.disabled = true;
                                                    plateText.removeAttribute('name');
                                                    plateSelect.required = false;
                                                    plateSelect.disabled = true;
                                                    plateSelect.removeAttribute('name');
                                                    return;
                                                }
                                                plateField.classList.remove('hidden');
                                                if (name === serviceVehicle) {
                                                    plateText.classList.add('hidden');
                                                    plateText.required = false;
                                                    plateText.disabled = true;
                                                    plateText.removeAttribute('name');
                                                    plateSelect.classList.remove('hidden');
                                                    if (plateName) {
                                                        plateSelect.setAttribute('name', plateName);
                                                    }
                                                    plateSelect.required = true;
                                                    plateSelect.disabled = false;
                                                    return;
                                                }
                                                plateSelect.classList.add('hidden');
                                                plateSelect.required = false;
                                                plateSelect.disabled = true;
                                                plateSelect.removeAttribute('name');
                                                plateText.classList.remove('hidden');
                                                if (plateName) {
                                                    plateText.setAttribute('name', plateName);
                                                }
                                                plateText.required = true;
                                                plateText.disabled = false;
                                                plateText.value = formatPlateWithSpaces(plateText.value);
                                            }

                                            methodSelect.addEventListener('change', syncPlateField);
                                            if (plateText) {
                                                plateText.addEventListener('input', function () {
                                                    plateText.value = formatPlateWithSpaces(plateText.value);
                                                });
                                                plateText.addEventListener('paste', function (event) {
                                                    event.preventDefault();
                                                    const pasted = (event.clipboardData || window.clipboardData)?.getData('text') ?? '';
                                                    plateText.value = formatPlateWithSpaces(plateText.value + pasted);
                                                });
                                            }
                                            syncPlateField();
                                        }

                                        form.querySelectorAll('[data-delivery-transport-block]').forEach(initTransportBlock);

                                        const selectAll = document.getElementById('delivery-select-all');
                                        if (selectAll) {
                                            selectAll.addEventListener('change', function () {
                                                form.querySelectorAll('.delivery-item-mark').forEach(function (cb) {
                                                    cb.checked = selectAll.checked;
                                                });
                                            });
                                        }

                                        const applyBulk = document.getElementById('delivery-apply-bulk');
                                        const bulkBlock = document.getElementById('delivery-bulk-block');
                                        if (applyBulk && bulkBlock) {
                                            applyBulk.addEventListener('click', function () {
                                                const bulkMethod = bulkBlock.querySelector('[data-delivery-method]');
                                                const bulkPlateText = bulkBlock.querySelector('[data-delivery-plate-text]');
                                                const bulkPlateSelect = bulkBlock.querySelector('[data-delivery-plate-select]');
                                                form.querySelectorAll('.delivery-in-transit-row').forEach(function (row) {
                                                    const method = row.querySelector('[data-delivery-method]');
                                                    const plateText = row.querySelector('[data-delivery-plate-text]');
                                                    const plateSelect = row.querySelector('[data-delivery-plate-select]');
                                                    if (method && bulkMethod) {
                                                        method.value = bulkMethod.value;
                                                        method.dispatchEvent(new Event('change', { bubbles: true }));
                                                    }
                                                    if (bulkPlateSelect && !bulkPlateSelect.classList.contains('hidden') && plateSelect) {
                                                        plateSelect.value = bulkPlateSelect.value;
                                                    } else if (bulkPlateText && plateText) {
                                                        plateText.value = bulkPlateText.value;
                                                        plateText.dispatchEvent(new Event('input', { bubbles: true }));
                                                    }
                                                });
                                            });
                                        }
                                    })();
                                </script>
                            </div>
                        @endif

                    </section>

                </div>
            </div>
    </div>

    @if ($canForemanSubmitToBoilerChief ?? false)
        <form id="submit-to-boiler-chief-form" method="POST" action="{{ route('applications.submit-to-boiler-chief', $application) }}" class="hidden" aria-hidden="true">
            @csrf
            <button type="submit" tabindex="-1">Отправить на согласование</button>
        </form>
        <x-confirm-action-modal
            name="confirm-submit-boiler-chief"
            title="Отправить на согласование?"
            confirm-label="Да, отправить"
            form-id="submit-to-boiler-chief-form"
        >
            После отправки редактирование заявки будет недоступно. Продолжить?
        </x-confirm-action-modal>
    @endif

    @if ($canBoilerChiefSubmitForManagement ?? false)
        <form id="submit-for-management-form" method="POST" action="{{ route('applications.submit-for-management', $application) }}" class="hidden" aria-hidden="true">
            @csrf
            <button type="submit" tabindex="-1">Отправить на согласование</button>
        </form>
        <x-confirm-action-modal
            name="confirm-submit-for-management"
            title="Отправить на согласование?"
            confirm-label="Да, отправить"
            form-id="submit-for-management-form"
        >
            Заявка будет отправлена руководству и снабжению. После отправки редактирование будет недоступно. Продолжить?
        </x-confirm-action-modal>
    @endif

    @if (Auth::user()->hasAnyRoleId([4, 7]))
        <x-confirm-action-modal
            name="confirm-repeat-application"
            title="Создать повторную заявку?"
            confirm-label="Да, создать"
            link-id="application-repeat-link"
        >
            Будет открыта форма новой заявки с копией позиций из текущей.
        </x-confirm-action-modal>
    @endif
</x-app-layout>
