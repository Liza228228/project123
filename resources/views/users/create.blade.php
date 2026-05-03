<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 min-w-0 w-full">
            <x-page-header-nav :href="route('users.index')">Управление пользователями</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Новый пользователь
            </h2>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-orange-200/85 dark:border-stone-800 bg-orange-50/35 dark:bg-stone-950 shadow-sm ring-1 ring-orange-100/70">
                <div class="border-b border-orange-200/75 dark:border-stone-800 bg-gradient-to-r from-orange-50/80 via-orange-50/35 to-amber-50/25 dark:from-stone-900/40 dark:via-stone-950 dark:to-stone-950 px-4 py-4 sm:px-8 sm:py-5">
                    <h3 class="text-lg font-semibold text-black dark:text-white">Создание учётной записи</h3>
                    <p class="mt-1.5 text-sm text-black/70 dark:text-white/70 max-w-2xl">
                        Заполните ФИО, укажите почту и роль. Пароль нужен для первого входа в систему.
                    </p>
                </div>

                <form method="POST" action="{{ route('users.store') }}" class="p-6 sm:p-8 space-y-8">
                    @csrf

                    <section aria-labelledby="create-section-personal">
                        <h3 id="create-section-personal" class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-stone-700 dark:text-stone-300 mb-4">
                            <span class="h-px w-10 shrink-0 bg-stone-300 dark:bg-stone-600 rounded-full" aria-hidden="true"></span>
                            Личные данные
                            <span class="h-px flex-1 bg-stone-200 dark:bg-stone-800 rounded-full" aria-hidden="true"></span>
                        </h3>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-1">
                                <x-input-label for="surname" value="Фамилия" />
                                <x-text-input id="surname" class="block mt-1.5 w-full" type="text" name="surname" :value="old('surname')" required autofocus />
                                <x-input-error :messages="$errors->get('surname')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-1">
                                <x-input-label for="name" value="Имя" />
                                <x-text-input id="name" class="block mt-1.5 w-full" type="text" name="name" :value="old('name')" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-1">
                                <x-input-label for="patronymic" value="Отчество" />
                                <x-text-input id="patronymic" class="block mt-1.5 w-full" type="text" name="patronymic" :value="old('patronymic')" required />
                                <x-input-error :messages="$errors->get('patronymic')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="pt-2 border-t border-stone-200 dark:border-stone-800" aria-labelledby="create-section-account">
                        <h3 id="create-section-account" class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-stone-700 dark:text-stone-300 mb-4">
                            <span class="h-px w-10 shrink-0 bg-stone-300 dark:bg-stone-600 rounded-full" aria-hidden="true"></span>
                            Контакты и роль
                            <span class="h-px flex-1 bg-stone-200 dark:bg-stone-800 rounded-full" aria-hidden="true"></span>
                        </h3>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <x-input-label for="email" value="Электронная почта" />
                                <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="role_id" value="Роль в системе" />
                                <div class="mt-1.5 rounded-2xl border border-orange-200/75 dark:border-stone-700 bg-gradient-to-br from-orange-50/85 via-white/80 to-amber-50/40 dark:bg-stone-900/35 px-4 py-4 shadow-sm space-y-4">
                                    <div class="flex flex-col gap-1">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-orange-900/70 dark:text-orange-200/75">Выбор роли</p>
                                        <p class="text-sm text-stone-700 dark:text-stone-300">Роль определяет, какие разделы и действия будут доступны новому пользователю.</p>
                                    </div>
                                    <select
                                        id="role_id"
                                        name="role_id"
                                        class="block w-full rounded-xl border-orange-200 dark:border-stone-800 bg-white/95 dark:bg-stone-950/50 py-2.5 px-3 text-sm text-black dark:text-white shadow-sm focus:border-orange-400 focus:ring-2 focus:ring-orange-400/30 dark:focus:border-orange-500"
                                        required
                                    >
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($roles as $role)
                                            <span class="inline-flex items-center rounded-full border border-orange-200/80 bg-white/85 px-3 py-1 text-xs font-medium text-stone-700 dark:border-stone-700 dark:bg-stone-900/45 dark:text-stone-200">
                                                {{ $role->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="pt-2 border-t border-stone-200 dark:border-stone-800" aria-labelledby="create-section-security">
                        <h3 id="create-section-security" class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-stone-700 dark:text-stone-300 mb-4">
                            <span class="h-px w-10 shrink-0 bg-stone-300 dark:bg-stone-600 rounded-full" aria-hidden="true"></span>
                            Пароль
                            <span class="h-px flex-1 bg-stone-200 dark:bg-stone-800 rounded-full" aria-hidden="true"></span>
                        </h3>
                        <div class="rounded-lg border border-stone-200 dark:border-stone-800 bg-stone-50/50 dark:bg-stone-900/20 p-4 sm:p-5 space-y-4">
                            <p class="text-xs text-black/70 dark:text-white/70">Минимальная длина и сложность пароля задаются правилами валидации приложения.</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="password" value="Пароль" />
                                    <x-text-input id="password" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="new-password" />
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="password_confirmation" value="Подтверждение" />
                                    <x-text-input id="password_confirmation" class="block mt-1.5 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-stone-200 dark:border-stone-800">
                        <a
                            href="{{ route('users.index') }}"
                            class="ui-btn ui-btn--secondary w-full justify-center sm:w-auto"
                        >
                            Отмена
                        </a>
                        <x-primary-button class="min-w-[200px]">
                            Создать пользователя
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
