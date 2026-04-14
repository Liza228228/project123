<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center gap-x-3 gap-y-2 min-w-0 w-full">
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-black dark:text-white hover:text-stone-700 dark:hover:text-stone-300 transition shrink-0 whitespace-nowrap">
                <span aria-hidden="true">←</span> Управление пользователями
            </a>
            <span class="hidden sm:inline text-stone-300 dark:text-stone-700" aria-hidden="true">|</span>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight min-w-0 break-words">
                Новый пользователь
            </h2>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8 md:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl border border-stone-200 dark:border-stone-800 bg-white dark:bg-stone-950 shadow-sm">
                <div class="border-b border-stone-200 dark:border-stone-800 bg-gradient-to-r from-stone-50 via-stone-50/80 to-white dark:from-stone-900/40 dark:via-stone-950 dark:to-stone-950 px-4 py-4 sm:px-8 sm:py-5">
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
                                <select
                                    id="role_id"
                                    name="role_id"
                                    class="mt-1.5 block w-full rounded-lg border-stone-200 dark:border-stone-800 bg-white dark:bg-stone-950/50 py-2.5 px-3 text-sm text-black dark:text-white shadow-sm focus:border-stone-500 focus:ring-2 focus:ring-stone-500 dark:focus:border-stone-400 dark:focus:ring-stone-400"
                                    required
                                >
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
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
                            class="inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium rounded-lg border border-stone-300 dark:border-stone-600 text-black dark:text-white bg-white dark:bg-stone-900/30 hover:bg-stone-50 dark:hover:bg-stone-900/50 focus:outline-none focus:ring-2 focus:ring-stone-500 focus:ring-offset-2 dark:focus:ring-offset-stone-950 transition"
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
