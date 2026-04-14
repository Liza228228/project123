<nav x-data="{ open: false }" class="sticky top-0 z-50 bg-orange-200/95 dark:bg-orange-950/75 backdrop-blur-md border-b border-orange-400/70 dark:border-orange-800/60 shadow-sm shadow-orange-900/[0.08] dark:shadow-black/30 text-stone-900 dark:text-stone-100 pt-[max(0px,env(safe-area-inset-top))]">
    <div class="h-px w-full bg-gradient-to-r from-transparent via-orange-400/35 to-transparent dark:via-orange-700/25" aria-hidden="true"></div>
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center gap-3">
                    <a href="{{ route('dashboard') }}" class="group flex items-center gap-2 rounded-xl px-1 py-1 -ms-1 ring-1 ring-transparent hover:ring-orange-300/45 dark:hover:ring-orange-800/35 transition-shadow">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-orange-600 to-orange-800 text-white shadow-sm shadow-orange-700/20 group-hover:shadow-md group-hover:shadow-orange-700/25 transition-shadow">
                            <svg class="h-5 w-5 opacity-95" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </span>
                        <x-application-logo class="hidden sm:block h-8 w-auto fill-current text-black dark:text-white opacity-90" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Панель управления
                    </x-nav-link>
                </div>
            </div>

            <!-- Theme + user -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                <div class="flex flex-col items-end gap-1">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-black/40 dark:text-white/40 leading-none">Тема</span>
                    <x-theme-toggle />
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="ui-btn ui-btn--secondary px-3 py-2 gap-2">
                            <div class="text-end">
                                <div>{{ Auth::user()->name }}</div>
                                <div class="text-xs font-normal text-black/60 dark:text-white/60 max-w-[14rem] truncate" title="{{ Auth::user()->role?->name ?? '' }}">
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
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-black dark:text-white hover:opacity-80 hover:bg-stone-100 dark:hover:bg-stone-800 focus:outline-none focus:bg-stone-100 dark:focus:bg-stone-800 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Панель управления
            </x-responsive-nav-link>
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
