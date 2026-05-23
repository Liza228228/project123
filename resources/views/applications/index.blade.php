@php
    $isAccountant = Auth::user()?->hasRoleId(\App\Models\User::ACCOUNTANT_ROLE_ID) ?? false;
    $isAdministratorViewer = $isAdministratorViewer ?? (Auth::user()?->hasRoleId(\App\Models\User::ADMINISTRATOR_ROLE_ID) ?? false);
    $canForceArchiveApplications = $canForceArchiveApplications ?? false;
    $archiveFilterValue = $archiveFilter ?? (($isAccountant || $isAdministratorViewer) ? 'all' : 'active');
@endphp
<x-app-layout :wide="true">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <div class="min-w-0 space-y-1">
                <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                    @if($archiveFilterValue === 'archived')
                        Архив выполненных заявок
                    @else
                        Заявки
                    @endif
                </h2>
                @if($archiveFilterValue === 'archived')
                   
                @endif
            </div>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto sm:shrink-0">
                @if($isAccountant || $isAdministratorViewer)
                    @if($archiveFilterValue !== 'all')
                        <a href="{{ route('applications.index', array_merge(request()->except('page', 'archive'), ['archive' => 'all'])) }}" class="ui-btn ui-btn--secondary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                            Все заявки
                        </a>
                    @endif
                    @if($archiveFilterValue !== 'active')
                        <a href="{{ route('applications.index', array_merge(request()->except('page', 'archive'), ['archive' => 'active'])) }}" class="ui-btn ui-btn--secondary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                            Только активные
                        </a>
                    @endif
                    @if($archiveFilterValue !== 'archived')
                        <a href="{{ route('applications.archive', request()->except('page', 'archive')) }}" class="ui-btn ui-btn--secondary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                            Только архив
                        </a>
                    @endif
                @elseif($archiveFilterValue === 'archived')
                    <a href="{{ route('applications.index', request()->except('page', 'archive')) }}" class="ui-btn ui-btn--secondary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                        К активным заявкам
                    </a>
                @else
                    <a href="{{ route('applications.archive', request()->except('page', 'archive')) }}" class="ui-btn ui-btn--secondary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                        Архив выполненных
                    </a>
                @endif
                @if (Auth::user()->hasAnyRoleId(\App\Models\User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS) && ($customEquipmentPendingOrderCount ?? 0) > 0)
                    <a href="{{ route('applications.custom-equipment-to-order') }}" class="ui-btn ui-btn--secondary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                        Своё оборудование к заказу ({{ (int) $customEquipmentPendingOrderCount }})
                    </a>
                @elseif(Auth::user()->hasAnyRoleId(\App\Models\User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS))
                    <a href="{{ route('applications.custom-equipment-to-order') }}" class="ui-btn ui-btn--secondary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                        Своё оборудование к заказу
                    </a>
                @endif
                @if (Auth::user()->hasAnyRoleId([1, 6, 2, 4, 7]) && ! $isAdministratorViewer)
                    <a href="{{ route('applications.create') }}" class="ui-btn ui-btn--primary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                        Создать заявку
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="w-full max-w-[min(100%,1920px)] mx-auto px-0 py-2 max-sm:-mx-4 sm:px-4 lg:px-6 xl:px-8">
            <div class="app-form-card">
                <div class="px-4 py-5 sm:p-6 xl:p-8 space-y-5 sm:space-y-6">
                    <form method="get" action="{{ route('applications.index') }}" class="flex flex-col gap-4" data-auto-submit="filter">
                        <input type="hidden" name="archive" value="{{ $archiveFilterValue }}">
                        <div class="app-filter-panel">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-6 lg:items-end">
                            <div class="min-w-0 lg:col-span-2">
                                <label for="applications-q" class="app-form-label">Поиск</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    </span>
                                    <input type="search" name="q" id="applications-q" value="{{ $search }}"
                                        placeholder="Подразделение, автор, согласовавший, оборудование…"
                                        class="app-input app-input--with-icon">
                                </div>
                            </div>
                            <div class="min-w-0">
                                <label for="applications-approval-filter" class="app-form-label">Согласование</label>
                                <select name="approval_filter" id="applications-approval-filter"
                                    class="app-select">
                                    @foreach($approvalFilterOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($approvalFilter === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-0">
                                <label for="applications-commercial-offer-filter" class="app-form-label">Коммерческое предложение</label>
                                <select name="commercial_offer_filter" id="applications-commercial-offer-filter"
                                    class="app-select">
                                    @foreach($commercialOfferFilterOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($commercialOfferFilter === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @unless($isSiteForeman || ($isBoilerChief ?? false))
                                <div class="min-w-0">
                                    <label for="applications-foreman-filter" class="app-form-label">Мастер участка</label>
                                    <select name="foreman_user_id" id="applications-foreman-filter"
                                        class="app-select">
                                        <option value="">Все мастера участка</option>
                                        @foreach($foremen as $foreman)
                                            <option value="{{ $foreman->id }}" @selected($selectedForemanId === (int) $foreman->id)>
                                                {{ trim($foreman->surname.' '.$foreman->name.' '.$foreman->patronymic) }}{{ ($foreman->is_blocked ?? false) ? ' (заблокирован)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endunless
                            <div class="min-w-0">
                                <label for="applications-per-page" class="app-form-label">На странице</label>
                                <select name="per_page" id="applications-per-page"
                                    class="app-select">
                                    @foreach($allowedPerPage as $size)
                                        <option value="{{ $size }}" @selected((int) $perPage === (int) $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-0 lg:col-span-6">
                                <p class="app-form-label">Сортировка</p>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <select name="sort_primary_field"
                                            class="app-select">
                                            <option value="created_at" @selected(($sortState['primary_field'] ?? '') === 'created_at')>Дата создания</option>
                                            <option value="desired_delivery_date" @selected(($sortState['primary_field'] ?? '') === 'desired_delivery_date')>Желаемая дата поставки</option>
                                            <option value="subdivision" @selected(($sortState['primary_field'] ?? '') === 'subdivision')>Подразделение</option>
                                            <option value="responsible" @selected(($sortState['primary_field'] ?? '') === 'responsible')>Ответственный</option>
                                            <option value="author" @selected(($sortState['primary_field'] ?? '') === 'author')>Автор заявки</option>
                                            <option value="approved_by" @selected(($sortState['primary_field'] ?? '') === 'approved_by')>Согласовавший</option>
                                        </select>
                                        <select name="sort_primary_direction"
                                            class="app-select">
                                            <option value="asc" @selected(($sortState['primary_direction'] ?? '') === 'asc')>По возрастанию</option>
                                            <option value="desc" @selected(($sortState['primary_direction'] ?? '') === 'desc')>По убыванию</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <select name="sort_secondary_field"
                                            class="app-select">
                                            <option value="" @selected(empty($sortState['secondary_field']))>Без второго поля</option>
                                            <option value="created_at" @selected(($sortState['secondary_field'] ?? '') === 'created_at')>Дата создания</option>
                                            <option value="desired_delivery_date" @selected(($sortState['secondary_field'] ?? '') === 'desired_delivery_date')>Желаемая дата поставки</option>
                                            <option value="subdivision" @selected(($sortState['secondary_field'] ?? '') === 'subdivision')>Подразделение</option>
                                            <option value="responsible" @selected(($sortState['secondary_field'] ?? '') === 'responsible')>Ответственный</option>
                                            <option value="author" @selected(($sortState['secondary_field'] ?? '') === 'author')>Автор заявки</option>
                                            <option value="approved_by" @selected(($sortState['secondary_field'] ?? '') === 'approved_by')>Согласовавший</option>
                                        </select>
                                        <select name="sort_secondary_direction"
                                            class="app-select">
                                            <option value="asc" @selected(($sortState['secondary_direction'] ?? '') === 'asc')>По возрастанию</option>
                                            <option value="desc" @selected(($sortState['secondary_direction'] ?? '') === 'desc')>По убыванию</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2 pt-1 sm:justify-end">
                            @php
                                $archiveResetQuery = ($archiveFilter ?? 'active') === 'archived' ? ['archive' => 'archived'] : [];
                            @endphp
                            @if(
                                $search !== ''
                                || $approvalFilter !== 'all'
                                || ($commercialOfferFilter ?? 'all') !== 'all'
                                || (($archiveFilter ?? 'active') !== 'active')
                                || $selectedForemanId !== null
                                || (($sortState['primary_field'] ?? 'created_at') !== 'created_at')
                                || (($sortState['primary_direction'] ?? 'desc') !== 'desc')
                                || !empty($sortState['secondary_field'])
                                || (($sortState['secondary_direction'] ?? 'asc') !== 'asc')
                            )
                                <a href="{{ route('applications.index', $archiveResetQuery) }}" class="ui-btn ui-btn--secondary w-full shrink-0 whitespace-nowrap sm:w-auto [touch-action:manipulation]">
                                    Сбросить
                                </a>
                            @endif
                        </div>
                    </form>

                    @if($applications->isEmpty())
                        <p class="md:hidden py-6 text-center text-sm text-black dark:text-white">
                            @if($search !== '' || $approvalFilter !== 'all' || ($commercialOfferFilter ?? 'all') !== 'all' || (($archiveFilter ?? 'active') !== 'active') || $selectedForemanId !== null)
                                По заданным условиям заявок не найдено.
                            @else
                                Заявок пока нет.
                            @endif
                        </p>
                    @else
                        <div class="md:hidden mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-orange-200/80 bg-orange-50/50 px-3 py-2.5 dark:border-orange-900/45 dark:bg-orange-950/25">
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-black/70 dark:text-white/60">Оборудование</span>
                            <div class="applications-equipment-bulk-host flex flex-wrap justify-end gap-1.5">
                                <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm whitespace-nowrap !px-2.5 !py-1.5 text-[11px]" data-action="collapse-all">Свернуть все</button>
                                <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm whitespace-nowrap !px-2.5 !py-1.5 text-[11px]" data-action="expand-all">Развернуть все</button>
                            </div>
                        </div>
                        <div class="md:hidden space-y-4">
                            @foreach($applications as $application)
                                @php
                                    $needsIndexSubmit = (bool) ($application->index_needs_submit ?? (Auth::check() && $application->needsSubmitToApprovalBy(Auth::user())));
                                    $needsCustomOrder = (bool) ($application->index_needs_custom_order ?? $application->needsCustomEquipmentOrder());
                                @endphp
                                <article class="rounded-xl border p-5 space-y-4 shadow-sm {{ $needsCustomOrder ? 'border-amber-300/90 bg-amber-50/40 dark:border-amber-800/60 dark:bg-amber-950/25' : ($needsIndexSubmit ? 'border-orange-300/90 bg-orange-50/40 dark:border-orange-800/60 dark:bg-orange-950/25' : 'border-orange-100/90 dark:border-orange-900/40 bg-orange-50/20 dark:bg-stone-900/20') }}">
                                    <p class="text-sm font-semibold tabular-nums text-black/80 dark:text-white/80">Заявка № {{ $application->id }}</p>
                                    <div class="flex justify-between gap-3 items-start">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Подразделение</p>
                                            <p class="text-sm font-medium text-black dark:text-white break-words">{{ $application->subdivision->name }}</p>
                                            @if($needsCustomOrder)
                                                <span class="mt-1 inline-flex w-fit items-center rounded-full border border-amber-400/80 bg-amber-100/90 px-2 py-0.5 text-[11px] font-medium text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100">
                                                    Нужно заказать своё оборудование
                                                </span>
                                            @endif
                                            @if($application->source_application_id)
                                                <span class="mt-1 inline-flex w-fit items-center rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-stone-900/40 dark:text-white">
                                                    Повторная к №{{ $application->source_application_id }}
                                                </span>
                                            @endif
                                            @if($application->isAdminArchived())
                                                @include('applications.partials.admin-archived-badge', ['class' => 'mt-1'])
                                            @elseif($application->archived_at)
                                                <span class="mt-1 inline-flex w-fit items-center rounded-full border border-emerald-300/90 bg-emerald-50/90 px-2 py-0.5 text-[11px] font-medium text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                                                    В архиве (выполнена)
                                                </span>
                                            @endif
                                        </div>
                                        <div class="shrink-0 text-end">
                                            @include('applications.partials.index-approval-status-badge', ['application' => $application, 'compact' => true])
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 text-base text-black dark:text-white">
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Ответственный</p>
                                            <p>{{ $application->responsibleUser ? $application->responsibleUser->surname.' '.$application->responsibleUser->name : '—' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Оборудование</p>
                                            @include('applications.partials.index-equipment-collapsible', ['application' => $application])
                                        </div>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                                            <div>
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Дата поставки</p>
                                                <p>{{ $application->desired_delivery_date->format('d.m.Y') }}</p>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Транспорт</p>
                                                <p class="break-words">{{ $application->transportAndVehicleLine() ?? '—' }}</p>
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
                                                    @if($application->approvedBy->role?->name)
                                                        <span class="block text-xs opacity-75 mt-0.5">{{ $application->approvedBy->role->name }}</span>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    @if($isAdministratorViewer)
                                        <div class="flex flex-col gap-2">
                                            <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary flex w-full min-h-[44px] py-3 sm:min-h-0 sm:py-2 [touch-action:manipulation]">
                                                Просмотр
                                            </a>
                                            @include('applications.partials.force-archive-actions', [
                                                'application' => $application,
                                                'stacked' => true,
                                            ])
                                        </div>
                                    @elseif($isAccountant)
                                        @php
                                            $hasInstallationDocs = filled(trim((string) ($application->act_of_installation ?? '')))
                                                || (int) ($application->installation_act_photos_count ?? 0) > 0;
                                        @endphp
                                        <div class="flex flex-col gap-2">
                                            <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary flex w-full min-h-[44px] py-3 sm:min-h-0 sm:py-2 [touch-action:manipulation]">
                                                Просмотр
                                            </a>
                                            @if($hasInstallationDocs)
                                                <a href="{{ route('applications.installation-act.browse', ['application_id' => $application->id]) }}" class="ui-btn ui-btn--secondary flex w-full min-h-[44px] py-3 sm:min-h-0 sm:py-2 [touch-action:manipulation]">
                                                    Акт и фото
                                                </a>
                                            @endif
                                        </div>
                                    @elseif(Auth::user()->hasAnyRoleId([4, 7]))
                                        <div class="flex flex-col gap-2">
                                            @include('applications.partials.index-row-actions', ['application' => $application, 'stacked' => true])
                                            @if($canForceArchiveApplications)
                                                @include('applications.partials.force-archive-actions', [
                                                    'application' => $application,
                                                    'stacked' => true,
                                                ])
                                            @endif
                                        </div>
                                    @else
                                        <div class="flex flex-col gap-2">
                                            <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary flex w-full min-h-[44px] py-3 sm:min-h-0 sm:py-2 [touch-action:manipulation]">
                                                Просмотр
                                            </a>
                                            @if($canForceArchiveApplications)
                                                @include('applications.partials.force-archive-actions', [
                                                    'application' => $application,
                                                    'stacked' => true,
                                                ])
                                            @endif
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif

                    <div class="hidden md:block app-table-shell applications-index-table-shell">
                        <table class="applications-index-table">
                            <colgroup>
                                <col style="width: 3%">
                                <col style="width: 14%">
                                <col style="width: 10%">
                                <col style="width: 11%">
                                <col style="width: 11%">
                                <col style="width: 17%">
                                <col style="width: 8%">
                                <col style="width: 7%">
                                <col style="width: 9%">
                                <col style="width: 10%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="text-left text-black dark:text-white uppercase">№</th>
                                    <th class="text-left text-black dark:text-white uppercase">Подразделение</th>
                                    <th class="text-left text-black dark:text-white uppercase">Ответственный</th>
                                    <th class="text-left text-black dark:text-white uppercase">Создал(а)</th>
                                    <th class="text-left text-black dark:text-white uppercase">Согласовал(а)</th>
                                    <th class="text-left text-black dark:text-white uppercase align-bottom">
                                        <div class="flex flex-col gap-1 min-w-0">
                                            <span>Оборудование</span>
                                            <div class="applications-equipment-bulk-host flex flex-wrap gap-1 normal-case">
                                                <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm !px-2 !py-1 text-[10px] font-semibold" data-action="collapse-all">Свернуть</button>
                                                <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm !px-2 !py-1 text-[10px] font-semibold" data-action="expand-all">Развернуть</button>
                                            </div>
                                        </div>
                                    </th>
                                    <th class="text-left text-black dark:text-white uppercase">Транспорт</th>
                                    <th class="text-left text-black dark:text-white uppercase">Дата</th>
                                    <th class="text-left text-black dark:text-white uppercase">Статус</th>
                                    <th class="text-right text-black dark:text-white uppercase">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applications as $application)
                                    @php
                                        $needsIndexSubmit = (bool) ($application->index_needs_submit ?? (Auth::check() && $application->needsSubmitToApprovalBy(Auth::user())));
                                        $needsCustomOrder = (bool) ($application->index_needs_custom_order ?? $application->needsCustomEquipmentOrder());
                                    @endphp
                                    <tr class="align-top {{ $needsCustomOrder ? 'bg-amber-50/50 dark:bg-amber-950/20 border-l-4 border-l-amber-400 dark:border-l-amber-600' : ($needsIndexSubmit ? 'bg-orange-50/45 dark:bg-orange-950/25 border-l-4 border-l-orange-400 dark:border-l-orange-600' : '') }}">
                                        <td class="font-semibold tabular-nums text-black dark:text-white" title="Номер заявки">№ {{ $application->id }}</td>
                                        <td class="text-black dark:text-white">
                                            <div class="flex flex-col gap-1 min-w-0">
                                                <span class="break-words">{{ $application->subdivision->name }}</span>
                                                @if($needsCustomOrder)
                                                    <span class="inline-flex w-fit items-center rounded-full border border-amber-400/80 bg-amber-100/90 px-2 py-0.5 text-[11px] font-medium text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100">
                                                        К заказу
                                                    </span>
                                                @endif
                                                @if($application->source_application_id)
                                                    <span class="inline-flex w-fit items-center rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-stone-900/40 dark:text-white">
                                                        Повторная к заявке №{{ $application->source_application_id }}
                                                    </span>
                                                @endif
                                                @if($application->isAdminArchived())
                                                    @include('applications.partials.admin-archived-badge')
                                                @elseif($application->archived_at)
                                                    <span class="inline-flex w-fit items-center rounded-full border border-emerald-300/90 bg-emerald-50/90 px-2 py-0.5 text-[11px] font-medium text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                                                        В архиве (выполнена)
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-black dark:text-white break-words">
                                            @if($application->responsibleUser)
                                                {{ $application->responsibleUser->surname }} {{ $application->responsibleUser->name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-black dark:text-white min-w-0">
                                            @if($application->user)
                                                <span class="font-medium break-words">{{ $application->user->surname }} {{ $application->user->name }}</span>
                                                <span class="block text-xs opacity-75 mt-0.5">{{ $application->created_at->format('d.m.Y H:i') }}</span>
                                            @else
                                                <span class="opacity-50">—</span>
                                            @endif
                                        </td>
                                        <td class="text-black dark:text-white min-w-0">
                                            @if($application->approvedBy)
                                                <span class="font-medium break-words">{{ $application->approvedBy->surname }} {{ $application->approvedBy->name }}</span>
                                                @if($application->approvedBy->role?->name)
                                                    <span class="block text-xs opacity-75 mt-0.5 leading-snug break-words">{{ $application->approvedBy->role->name }}</span>
                                                @endif
                                            @else
                                                <span class="opacity-50">—</span>
                                            @endif
                                        </td>
                                        <td class="text-black dark:text-white min-w-0">
                                            @include('applications.partials.index-equipment-collapsible', ['application' => $application])
                                        </td>
                                        <td class="text-black dark:text-white min-w-0">
                                            @if($line = $application->transportAndVehicleLine())
                                                <span class="line-clamp-2 break-words" title="{{ $line }}">{{ $line }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-black dark:text-white tabular-nums">{{ $application->desired_delivery_date->format('d.m.Y') }}</td>
                                        <td class="align-top min-w-0">
                                            @include('applications.partials.index-approval-status-badge', ['application' => $application])
                                        </td>
                                        <td class="text-right align-top">
                                            @if($isAdministratorViewer)
                                                <div class="applications-index-actions ms-auto">
                                                    <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary">
                                                        Просмотр
                                                    </a>
                                                    @include('applications.partials.force-archive-actions', [
                                                        'application' => $application,
                                                        'inline' => true,
                                                    ])
                                                </div>
                                            @elseif($isAccountant)
                                                @php
                                                    $hasInstallationDocs = filled(trim((string) ($application->act_of_installation ?? '')))
                                                        || (int) ($application->installation_act_photos_count ?? 0) > 0;
                                                @endphp
                                                <div class="applications-index-actions ms-auto">
                                                    <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary">
                                                        Просмотр
                                                    </a>
                                                    @if($hasInstallationDocs)
                                                        <a href="{{ route('applications.installation-act.browse', ['application_id' => $application->id]) }}" class="ui-btn ui-btn--secondary">
                                                            Акт и фото
                                                        </a>
                                                    @endif
                                                </div>
                                            @elseif(Auth::user()->hasAnyRoleId([4, 7]))
                                                <div class="applications-index-actions ms-auto">
                                                    @include('applications.partials.index-row-actions', ['application' => $application, 'tableCompact' => true])
                                                    @if($canForceArchiveApplications)
                                                        @include('applications.partials.force-archive-actions', [
                                                            'application' => $application,
                                                            'inline' => true,
                                                        ])
                                                    @endif
                                                </div>
                                            @else
                                                <div class="applications-index-actions ms-auto">
                                                    <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary">
                                                        Просмотр
                                                    </a>
                                                    @if($canForceArchiveApplications)
                                                        @include('applications.partials.force-archive-actions', [
                                                            'application' => $application,
                                                            'inline' => true,
                                                        ])
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-6 text-center text-sm text-black dark:text-white">
                                            @if($search !== '' || $approvalFilter !== 'all' || ($commercialOfferFilter ?? 'all') !== 'all' || (($archiveFilter ?? 'active') !== 'active') || $selectedForemanId !== null)
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

                    @if($applications->hasPages())
                        <div class="pt-2">
                            {{ $applications->links() }}
                        </div>
                    @endif
                </div>
            </div>
    </div>
    @unless($applications->isEmpty())
        <script>
            (function () {
                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('.applications-equipment-bulk-host button[data-action]');
                    if (!btn) {
                        return;
                    }
                    var open = btn.getAttribute('data-action') === 'expand-all';
                    document.querySelectorAll('details.application-index-equipment-details').forEach(function (d) {
                        d.open = open;
                    });
                });
            })();
        </script>
    @endunless
</x-app-layout>
