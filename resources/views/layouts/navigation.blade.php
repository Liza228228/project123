@php // шаблон страницы
@endphp
﻿<nav x-data="{ open: false }" class="sticky top-0 z-50 bg-orange-200/95 dark:bg-orange-950/75 backdrop-blur-md border-b border-orange-400/70 dark:border-orange-800/60 shadow-sm shadow-orange-900/[0.08] dark:shadow-black/30 text-stone-900 dark:text-stone-100 pt-[max(0px,env(safe-area-inset-top))]">
    @php
        $user = Auth::user();
        $topNavBtnClass = 'ui-btn ui-btn--secondary px-3 py-2 whitespace-nowrap text-[13px] sm:text-sm';
        $topNavUserBtnClass = 'ui-btn ui-btn--secondary px-3 py-2 gap-2 max-w-[12rem] lg:max-w-[14rem] text-[13px] sm:text-sm';
        $canManageBoilerChiefAssignments = $user->hasAnyRoleId(\App\Models\User::SUBDIVISION_ASSIGNMENT_MANAGER_ROLE_IDS);
        $canManageForemanAssignments = $canManageBoilerChiefAssignments;
        $canManageMaterials = $user->hasAnyRoleId(\App\Models\User::MATERIALS_CATALOG_RECEIPT_ROLE_IDS);
        $canViewWarehouseBalances = $user->hasAnyRoleId(\App\Models\User::MATERIALS_WAREHOUSE_NAV_ROLE_IDS);
        $canOpenMaterialsWarehouseNav = $canManageMaterials || $canViewWarehouseBalances;
        $canManageReportLayoutTemplates = $user->hasAnyRoleId(\App\Models\User::REPORT_LAYOUT_DESIGNER_ROLE_IDS);
        $hasReportGeneratorFullMenu = $user->hasAnyRoleId(\App\Models\User::REPORT_GENERATOR_FULL_MENU_ROLE_IDS)
            || $user->hasRoleId(\App\Models\User::ADMINISTRATOR_ROLE_ID);
        $canLayoutApplicationReports = $user->hasAnyRoleId(\App\Models\User::LAYOUT_APPLICATION_REPORT_ROLE_IDS);
        $canLayoutApplicationsOnly = $user->hasRoleId(3);
        $foremanLayoutReportsGeneratorOnly = $user->hasRoleId(4) && $canLayoutApplicationReports && ! $hasReportGeneratorFullMenu;
        $boilerChiefLayoutReportsGeneratorOnly = $user->hasRoleId(7) && $canLayoutApplicationReports && ! $hasReportGeneratorFullMenu;
    @endphp
    <div class="h-px w-full bg-gradient-to-r from-transparent via-orange-400/35 to-transparent dark:via-orange-700/25" aria-hidden="true"></div>
    <!-- Primary Navigation Menu -->
    <div class="top-nav-shell">
        <div class="top-nav-row">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <!-- Logo -->
                <div class="top-nav-brand">
                    <a href="{{ route('dashboard') }}" class="group flex items-center gap-2 rounded-xl px-1 py-1 -ms-1 ring-1 ring-transparent hover:ring-orange-300/45 dark:hover:ring-orange-800/35 transition-shadow">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-orange-600 to-orange-800 text-white shadow-sm shadow-orange-700/20 group-hover:shadow-md group-hover:shadow-orange-700/25 transition-shadow">
                            <svg class="h-5 w-5 opacity-95" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </span>
                        <x-application-logo class="hidden lg:block h-8 w-auto fill-current text-black dark:text-white opacity-90" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="top-nav-menu">
                    @if ((int) Auth::user()->role_id === 5)
                        <a href="{{ route('users.index') }}"
                           class="{{ $topNavBtnClass }}"
                           @if(request()->routeIs('users.*')) aria-current="page" @endif>
                            Пользователи
                        </a>
                    @endif

                    @if (Auth::user()->hasAnyRoleId(\App\Models\User::APPLICATION_LISTING_ROLE_IDS))
                        <a href="{{ route('applications.index') }}"
                           class="{{ $topNavBtnClass }}"
                           @if(request()->routeIs('applications.*') && ! request()->routeIs('applications.installation-act.upload', 'applications.installation-act.upload.store', 'applications.installation-act.browse', 'applications.custom-equipment-to-order', 'applications.custom-equipment-order', 'applications.custom-equipment-order.ordered', 'applications.custom-equipment-order.on-warehouse')) aria-current="page" @endif>
                            Заявки
                        </a>
                    @endif

                    @if (Auth::user()->hasAnyRoleId(\App\Models\User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS))
                        <a href="{{ route('applications.custom-equipment-to-order') }}"
                           class="{{ $topNavBtnClass }}"
                           @if(request()->routeIs('applications.custom-equipment-to-order', 'applications.custom-equipment-order', 'applications.custom-equipment-order.ordered', 'applications.custom-equipment-order.on-warehouse')) aria-current="page" @endif>
                            Оборудование к заказу
                        </a>
                    @endif

                    @if (Auth::user()->hasRoleId(3))
                        <a href="{{ route('applications.installation-act.browse') }}"
                           class="{{ $topNavBtnClass }}"
                           @if(request()->routeIs('applications.installation-act.browse')) aria-current="page" @endif>
                            Акты по заявкам
                        </a>
                    @endif

                    @if (Auth::user()->hasAnyRoleId(\App\Models\User::APPLICATION_INSTALLATION_ACT_ROLE_IDS))
                        <a href="{{ route('applications.installation-act.upload') }}"
                           class="{{ $topNavBtnClass }}"
                           @if(request()->routeIs('applications.installation-act.upload', 'applications.installation-act.upload.store')) aria-current="page" @endif>
                            Акт установки
                        </a>
                    @endif

                    @if ($hasReportGeneratorFullMenu)
                        <x-dropdown align="left" width="64">
                            <x-slot name="trigger">
                                <button type="button" class="{{ $topNavBtnClass }} gap-2"
                                    @if(request()->routeIs('boiler-chief.document-header-layouts.*') || request()->routeIs('boiler-chief.request-layouts.*') || request()->routeIs('boiler-chief.layout-applications.*') || request()->routeIs('applications.installation-act.layout-fill.*')) aria-current="page" @endif>
                                    Генератор отчётов
                                    <svg class="h-4 w-4 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('boiler-chief.document-header-layouts.index')">Макеты шапок</x-dropdown-link>
                                <x-dropdown-link :href="route('boiler-chief.request-layouts.index')">Макеты отчетов (PDF)</x-dropdown-link>
                                @if ($canLayoutApplicationReports)
                                    <x-dropdown-link :href="route('boiler-chief.layout-applications.index')">Отчеты по макетам</x-dropdown-link>
                                @endif
                            </x-slot>
                        </x-dropdown>
                    @elseif ($canLayoutApplicationReports)
                        @if ($canLayoutApplicationsOnly)
                            <a href="{{ route('boiler-chief.layout-applications.index') }}"
                               class="{{ $topNavBtnClass }}"
                               @if(request()->routeIs('boiler-chief.layout-applications.*')) aria-current="page" @endif>
                                Отчеты по макетам
                            </a>
                        @elseif ($foremanLayoutReportsGeneratorOnly || $boilerChiefLayoutReportsGeneratorOnly)
                            <a href="{{ route('boiler-chief.layout-applications.index') }}"
                               class="{{ $topNavBtnClass }}"
                               @if(request()->routeIs('boiler-chief.layout-applications.*')) aria-current="page" @endif>
                                Отчеты по макетам
                            </a>
                        @else
                            <x-dropdown align="left" width="64">
                                <x-slot name="trigger">
                                    <button type="button" class="{{ $topNavBtnClass }} gap-2"
                                        @if(request()->routeIs('boiler-chief.request-layouts.*') || request()->routeIs('boiler-chief.layout-applications.*') || request()->routeIs('applications.installation-act.layout-fill.*')) aria-current="page" @endif>
                                        Генератор отчётов
                                        <svg class="h-4 w-4 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('boiler-chief.request-layouts.index')">Макеты отчетов (заполнение)</x-dropdown-link>
                                    <x-dropdown-link :href="route('boiler-chief.layout-applications.index')">Отчеты по макетам</x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        @endif
                    @endif

                    @if ($canOpenMaterialsWarehouseNav)
                        <x-dropdown align="left" width="64">
                            <x-slot name="trigger">
                                <button type="button" class="{{ $topNavBtnClass }} gap-2"
                                    @if(request()->routeIs('materials.*')) aria-current="page" @endif>
                                    Склады и оборудование
                                    <svg class="h-4 w-4 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                @if ($canManageMaterials)
                                    <x-dropdown-link :href="route('materials.index')">Учёт оборудования</x-dropdown-link>
                                @endif
                                @if ($canViewWarehouseBalances)
                                    <x-dropdown-link :href="route('materials.overview')">Остатки складов</x-dropdown-link>
                                @endif
                                <x-dropdown-link :href="route('materials.movements')">Журнал операций</x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    @if (Auth::user()->hasAnyRoleId(\App\Models\User::SUBDIVISION_DIRECTORY_TOP_NAV_ROLE_IDS))
                        <a href="{{ route('foreman-subdivisions.index') }}"
                           class="{{ $topNavBtnClass }}"
                           @if(request()->routeIs('foreman-subdivisions.index')) aria-current="page" @endif>
                            Подразделения
                        </a>
                    @endif

                    @if ($canManageBoilerChiefAssignments)
                        <x-dropdown align="left" width="72">
                            <x-slot name="trigger">
                                <button type="button" class="{{ $topNavBtnClass }} gap-2"
                                    @if(request()->routeIs('foreman-subdivisions.assignments') || request()->routeIs('foreman-subdivisions.edit') || request()->routeIs('boiler-chief-subdivisions.*')) aria-current="page" @endif>
                                    Назначения
                                    <svg class="h-4 w-4 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('foreman-subdivisions.assignments')">Назначения мастерам</x-dropdown-link>
                                <x-dropdown-link :href="route('boiler-chief-subdivisions.assignments')">Назначения котельной</x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @elseif ($canManageForemanAssignments)
                        <a href="{{ route('foreman-subdivisions.assignments') }}"
                           class="{{ $topNavBtnClass }}"
                           @if(request()->routeIs('foreman-subdivisions.assignments') || request()->routeIs('foreman-subdivisions.edit')) aria-current="page" @endif>
                            Назначения мастерам
                        </a>
                    @endif
                </div>
            </div>

            <!-- Theme + user -->
            <div class="top-nav-utilities">
                <div class="flex flex-col items-center gap-1">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-black/40 dark:text-white/40 leading-none">Тема</span>
                    <x-theme-toggle />
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="{{ $topNavUserBtnClass }}">
                            <div class="min-w-0 text-end">
                                <div class="truncate">{{ Auth::user()->name }}</div>
                                <div class="text-xs font-normal text-black/60 dark:text-white/60 truncate" title="{{ Auth::user()->role?->name ?? '' }}">
                                    {{ Auth::user()->role?->name ?? 'Роль не назначена' }}
                                </div>
                            </div>

                            <div class="ms-1 shrink-0">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Профиль
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                Выйти
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-1 flex items-center sm:hidden">
                <button type="button" @click="open = ! open" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl p-2 text-black dark:text-white hover:bg-stone-100/90 active:scale-95 dark:hover:bg-stone-800/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/50 transition duration-150 ease-in-out" :aria-expanded="open" aria-controls="mobile-nav-panel">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div id="mobile-nav-panel" :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-orange-400/50 dark:border-orange-800/50">
        <div class="max-h-[min(72dvh,calc(100dvh-4.5rem))] space-y-2 overflow-y-auto overscroll-y-contain px-3 pt-3 pb-3 [-webkit-overflow-scrolling:touch]">
            @if ((int) Auth::user()->role_id === 5)
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    Пользователи
                </x-responsive-nav-link>
            @endif

            @if (Auth::user()->hasAnyRoleId(\App\Models\User::APPLICATION_LISTING_ROLE_IDS))
                <x-responsive-nav-link :href="route('applications.index')" :active="request()->routeIs('applications.*') && ! request()->routeIs('applications.installation-act.upload', 'applications.installation-act.upload.store', 'applications.installation-act.browse', 'applications.custom-equipment-to-order', 'applications.custom-equipment-order', 'applications.custom-equipment-order.ordered', 'applications.custom-equipment-order.on-warehouse')">
                    Заявки
                </x-responsive-nav-link>
            @endif

            @if (Auth::user()->hasAnyRoleId(\App\Models\User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS))
                <x-responsive-nav-link :href="route('applications.custom-equipment-to-order')" :active="request()->routeIs('applications.custom-equipment-to-order', 'applications.custom-equipment-order', 'applications.custom-equipment-order.ordered', 'applications.custom-equipment-order.on-warehouse')">
                    Оборудование к заказу
                </x-responsive-nav-link>
            @endif

            @if (Auth::user()->hasRoleId(3))
                <x-responsive-nav-link :href="route('applications.installation-act.browse')" :active="request()->routeIs('applications.installation-act.browse')">
                    Акты по заявкам
                </x-responsive-nav-link>
            @endif

            @if (Auth::user()->hasAnyRoleId(\App\Models\User::APPLICATION_INSTALLATION_ACT_ROLE_IDS))
                <x-responsive-nav-link :href="route('applications.installation-act.upload')" :active="request()->routeIs('applications.installation-act.upload', 'applications.installation-act.upload.store')">
                    Акт установки
                </x-responsive-nav-link>
            @endif

            @if ($hasReportGeneratorFullMenu)
                <div class="px-3 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-black/45 dark:text-white/45">
                    Генератор отчётов
                </div>
                <x-responsive-nav-link :href="route('boiler-chief.document-header-layouts.index')" :active="request()->routeIs('boiler-chief.document-header-layouts.*')">
                    Макеты шапок
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('boiler-chief.request-layouts.index')" :active="request()->routeIs('boiler-chief.request-layouts.*')">
                    Макеты отчетов (PDF)
                </x-responsive-nav-link>
                @if ($canLayoutApplicationReports)
                    <x-responsive-nav-link :href="route('boiler-chief.layout-applications.index')" :active="request()->routeIs('boiler-chief.layout-applications.*')">
                        Отчеты по макетам
                    </x-responsive-nav-link>
                @endif
            @elseif ($canLayoutApplicationReports)
                @if ($canLayoutApplicationsOnly)
                    <x-responsive-nav-link :href="route('boiler-chief.layout-applications.index')" :active="request()->routeIs('boiler-chief.layout-applications.*')">
                        Отчеты по макетам
                    </x-responsive-nav-link>
                @elseif ($foremanLayoutReportsGeneratorOnly || $boilerChiefLayoutReportsGeneratorOnly)
                    <x-responsive-nav-link :href="route('boiler-chief.layout-applications.index')" :active="request()->routeIs('boiler-chief.layout-applications.*')">
                        Отчеты по макетам
                    </x-responsive-nav-link>
                @else
                    <div class="px-3 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-black/45 dark:text-white/45">
                        Генератор отчётов
                    </div>
                    <x-responsive-nav-link :href="route('boiler-chief.request-layouts.index')" :active="request()->routeIs('boiler-chief.request-layouts.*')">
                        Макеты отчетов (заполнение)
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('boiler-chief.layout-applications.index')" :active="request()->routeIs('boiler-chief.layout-applications.*')">
                        Отчеты по макетам
                    </x-responsive-nav-link>
                @endif
            @endif

            @if ($canOpenMaterialsWarehouseNav)
                <div class="px-3 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-black/45 dark:text-white/45">
                    Склады и оборудование
                </div>
                @if ($canManageMaterials)
                    <x-responsive-nav-link :href="route('materials.index')" :active="request()->routeIs('materials.index') || request()->routeIs('materials.store-*')">
                        Учёт оборудования
                    </x-responsive-nav-link>
                @endif
                @if ($canViewWarehouseBalances)
                    <x-responsive-nav-link :href="route('materials.overview')" :active="request()->routeIs('materials.overview')">
                        Остатки складов
                    </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('materials.movements')" :active="request()->routeIs('materials.movements')">
                    Журнал операций
                </x-responsive-nav-link>
            @endif

            @if (Auth::user()->hasAnyRoleId(\App\Models\User::SUBDIVISION_DIRECTORY_TOP_NAV_ROLE_IDS))
                <x-responsive-nav-link :href="route('foreman-subdivisions.index')" :active="request()->routeIs('foreman-subdivisions.index')">
                    Подразделения
                </x-responsive-nav-link>
            @endif

            @if ($canManageBoilerChiefAssignments)
                <div class="px-3 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-black/45 dark:text-white/45">
                    Назначения
                </div>
                <x-responsive-nav-link :href="route('foreman-subdivisions.assignments')" :active="request()->routeIs('foreman-subdivisions.assignments') || request()->routeIs('foreman-subdivisions.edit')">
                    Назначения мастерам
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('boiler-chief-subdivisions.assignments')" :active="request()->routeIs('boiler-chief-subdivisions.*')">
                    Назначения котельной
                </x-responsive-nav-link>
            @elseif ($canManageForemanAssignments)
                <x-responsive-nav-link :href="route('foreman-subdivisions.assignments')" :active="request()->routeIs('foreman-subdivisions.assignments') || request()->routeIs('foreman-subdivisions.edit')">
                    Назначения мастерам
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-stone-200 dark:border-stone-700">
            <div class="px-4 py-3 flex items-center justify-between gap-3 border-b border-stone-100 dark:border-stone-800/80">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-black/50 dark:text-white/50">Оформление</p>
                    <p class="mt-0.5 text-xs text-black/60 dark:text-white/60">Светлая или тёмная тема</p>
                </div>
                <x-theme-toggle />
            </div>
            <div class="px-4 pt-3">
                <div class="font-medium text-base text-black dark:text-white">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-black dark:text-white opacity-80">{{ Auth::user()->email }}</div>
                <div class="mt-1 text-xs text-orange-900 dark:text-orange-200/90 font-medium">
                    Роль: {{ Auth::user()->role?->name ?? 'не назначена' }}
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Профиль
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        Выйти
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
