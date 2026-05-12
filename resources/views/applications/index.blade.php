<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 w-full min-w-0">
            <div class="min-w-0 space-y-1">
                <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                    @if(($archiveFilter ?? 'active') === 'archived')
                        Архив выполненных заявок
                    @else
                        Заявки
                    @endif
                </h2>
                @if(($archiveFilter ?? 'active') === 'archived')
                   
                @endif
            </div>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto sm:shrink-0">
                @if(($archiveFilter ?? 'active') === 'archived')
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
                @if (Auth::user()->hasAnyRoleId([1, 6, 2, 4, 7]))
                    <a href="{{ route('applications.create') }}" class="ui-btn ui-btn--primary gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
                        Создать заявку
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[96rem] px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-stone-200/80 bg-stone-50/80 px-4 py-3 text-sm text-stone-900 dark:border-stone-700 dark:bg-stone-900/40 dark:text-stone-100">
                    {{ session('status') }}
                </div>
            @endif

            <div class="app-form-card">
                <div class="px-4 py-5 sm:p-8 space-y-5 sm:space-y-6">
                    <form method="get" action="{{ route('applications.index') }}" class="flex flex-col gap-4" data-auto-submit="filter">
                        <input type="hidden" name="archive" value="{{ ($archiveFilter ?? 'active') === 'archived' ? 'archived' : 'active' }}">
                        <div class="app-filter-panel">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 lg:items-end">
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
                                <label for="applications-equipment-filter" class="app-form-label">Статус согласования</label>
                                <select name="equipment_filter" id="applications-equipment-filter"
                                    class="app-select">
                                    <option value="all" @selected($equipmentFilter === 'all')>Все заявки</option>
                                    <option value="has_approved" @selected($equipmentFilter === 'has_approved')>Есть согласованные позиции</option>
                                    <option value="has_not_approved" @selected($equipmentFilter === 'has_not_approved')>Есть несогласованные позиции</option>
                                    <option value="fully_approved" @selected($equipmentFilter === 'fully_approved')>Все позиции согласованы</option>
                                    <option value="on_approval" @selected($equipmentFilter === 'on_approval')>Заявка на согласовании</option>
                                    <option value="needs_custom_equipment_order" @selected($equipmentFilter === 'needs_custom_equipment_order')>Нужно заказать своё оборудование</option>
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
                                    @foreach([10, 25, 50] as $size)
                                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-0 lg:col-span-4">
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
                                            <option value="status" @selected(($sortState['primary_field'] ?? '') === 'status')>Статус</option>
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
                                            <option value="status" @selected(($sortState['secondary_field'] ?? '') === 'status')>Статус</option>
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
                                || $equipmentFilter !== 'all'
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
                            @if($search !== '' || $equipmentFilter !== 'all' || (($archiveFilter ?? 'active') !== 'active') || $selectedForemanId !== null)
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
                                <article class="rounded-xl border p-4 space-y-3 shadow-sm {{ $application->needsCustomEquipmentOrder() ? 'border-amber-300/90 bg-amber-50/40 dark:border-amber-800/60 dark:bg-amber-950/25' : 'border-stone-200 dark:border-stone-800 bg-stone-50/30 dark:bg-stone-900/20' }}">
                                    <div class="flex justify-between gap-3 items-start">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-black/55 dark:text-white/50">Подразделение</p>
                                            <p class="text-sm font-medium text-black dark:text-white break-words">{{ $application->subdivision->name }}</p>
                                            @if($application->needsCustomEquipmentOrder())
                                                <span class="mt-1 inline-flex w-fit items-center rounded-full border border-amber-400/80 bg-amber-100/90 px-2 py-0.5 text-[11px] font-medium text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100">
                                                    Нужно заказать своё оборудование
                                                </span>
                                            @endif
                                            @if($application->source_application_id)
                                                <span class="mt-1 inline-flex w-fit items-center rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-stone-900/40 dark:text-white">
                                                    Повторная к №{{ $application->source_application_id }}
                                                </span>
                                            @endif
                                            @if($application->archived_at)
                                                <span class="mt-1 inline-flex w-fit items-center rounded-full border border-emerald-300/90 bg-emerald-50/90 px-2 py-0.5 text-[11px] font-medium text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                                                    В архиве (выполнена)
                                                </span>
                                            @endif
                                        </div>
                                        <div class="shrink-0 text-end">
                                            @if($application->items->isEmpty())
                                                <span class="text-xs text-black/50 dark:text-white/50">—</span>
                                            @elseif($application->isLifecycleCompleted())
                                                <span class="inline-flex items-center rounded-full border border-emerald-300/90 bg-emerald-50/90 px-2 py-0.5 text-[11px] font-medium text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">Выполнена</span>
                                            @elseif($application->isStatusApproved())
                                                <span class="inline-flex items-center rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-black dark:bg-stone-900/60 dark:text-white">Согласована</span>
                                            @elseif($application->isStatusPartial())
                                                <span class="inline-flex items-center rounded-full bg-stone-200/90 px-2 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white">Частично</span>
                                            @elseif($application->needsBoilerChiefReviewBeforeManagement())
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">У котельной</span>
                                            @elseif($application->awaitsManagementEquipmentApproval())
                                                <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-950 dark:bg-sky-950/40 dark:text-sky-100">У руководства</span>
                                            @elseif($application->isStatusRejected())
                                                <span class="inline-flex items-center rounded-full bg-stone-300/90 px-2 py-0.5 text-xs font-medium text-black dark:bg-stone-900/70 dark:text-white">Не согласована</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white">На согласовании</span>
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
                                            @include('applications.partials.index-equipment-collapsible', ['application' => $application])
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
                                    @if(Auth::user()->hasRoleId(3))
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
                                    @else
                                        <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary flex w-full min-h-[44px] py-3 sm:min-h-0 sm:py-2 [touch-action:manipulation]">
                                            Просмотр
                                        </a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif

                    <div class="hidden md:block app-table-shell">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Подразделение</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Ответственный</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase min-w-[9rem]">Создал(а)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase min-w-[9rem]">Согласовал(а)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase min-w-[14rem] max-w-md align-bottom">
                                        <div class="flex flex-col gap-2 min-w-0">
                                            <span class="tracking-wide">Оборудование</span>
                                            <div class="applications-equipment-bulk-host flex flex-wrap gap-1.5 normal-case">
                                                <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm whitespace-nowrap !px-2.5 !py-1.5 text-[10px] font-semibold" data-action="collapse-all">Свернуть все</button>
                                                <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm whitespace-nowrap !px-2.5 !py-1.5 text-[10px] font-semibold" data-action="expand-all">Развернуть все</button>
                                            </div>
                                        </div>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Транспорт</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Желаемая дата поставки</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-black dark:text-white uppercase">Согласование</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-black dark:text-white uppercase w-[1%] whitespace-nowrap"><span class="sr-only">Действия</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applications as $application)
                                    <tr class="align-top {{ $application->needsCustomEquipmentOrder() ? 'bg-amber-50/50 dark:bg-amber-950/20 border-l-4 border-l-amber-400 dark:border-l-amber-600' : '' }}">
                                        <td class="px-4 py-3 text-sm text-black dark:text-white align-top">
                                            <div class="flex flex-col gap-1">
                                                <span>{{ $application->subdivision->name }}</span>
                                                @if($application->needsCustomEquipmentOrder())
                                                    <span class="inline-flex w-fit items-center rounded-full border border-amber-400/80 bg-amber-100/90 px-2 py-0.5 text-[11px] font-medium text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100">
                                                        К заказу
                                                    </span>
                                                @endif
                                                @if($application->source_application_id)
                                                    <span class="inline-flex w-fit items-center rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-black dark:bg-stone-900/40 dark:text-white">
                                                        Повторная к заявке №{{ $application->source_application_id }}
                                                    </span>
                                                @endif
                                                @if($application->archived_at)
                                                    <span class="inline-flex w-fit items-center rounded-full border border-emerald-300/90 bg-emerald-50/90 px-2 py-0.5 text-[11px] font-medium text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                                                        В архиве
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
                                            @include('applications.partials.index-equipment-collapsible', ['application' => $application])
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
                                            @elseif($application->isLifecycleCompleted())
                                                <span class="inline-flex items-center rounded-full border border-emerald-300/90 bg-emerald-50/90 px-2.5 py-0.5 text-xs font-medium text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                                                    Выполнена
                                                </span>
                                            @elseif($application->isStatusApproved())
                                                <span class="inline-flex items-center rounded-full bg-stone-200 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/60 dark:text-white">
                                                    Согласована
                                                </span>
                                            @elseif($application->isStatusPartial())
                                                <span class="inline-flex items-center rounded-full bg-stone-200/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white">
                                                    Частично
                                                </span>
                                            @elseif($application->needsBoilerChiefReviewBeforeManagement())
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                                                    У котельной
                                                </span>
                                            @elseif($application->awaitsManagementEquipmentApproval())
                                                <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-950 dark:bg-sky-950/40 dark:text-sky-100">
                                                    У руководства
                                                </span>
                                            @elseif($application->isStatusRejected())
                                                <span class="inline-flex items-center rounded-full bg-stone-300/90 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/70 dark:text-white">
                                                    Не согласована
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-black dark:bg-stone-900/50 dark:text-white">
                                                    На согласовании
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right align-top w-[1%]">
                                            @if(Auth::user()->hasRoleId(3))
                                                @php
                                                    $hasInstallationDocs = filled(trim((string) ($application->act_of_installation ?? '')))
                                                        || (int) ($application->installation_act_photos_count ?? 0) > 0;
                                                @endphp
                                                <div class="inline-flex flex-col gap-2 items-end">
                                                    <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary whitespace-nowrap">
                                                        Просмотр
                                                    </a>
                                                    @if($hasInstallationDocs)
                                                        <a href="{{ route('applications.installation-act.browse', ['application_id' => $application->id]) }}" class="ui-btn ui-btn--secondary whitespace-nowrap">
                                                            Акт и фото
                                                        </a>
                                                    @endif
                                                </div>
                                            @else
                                                <a href="{{ route('applications.show', $application) }}" class="ui-btn ui-btn--primary whitespace-nowrap">
                                                    Просмотр
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-6 text-center text-sm text-black dark:text-white">
                                            @if($search !== '' || $equipmentFilter !== 'all' || (($archiveFilter ?? 'active') !== 'active') || $selectedForemanId !== null)
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
