<section>
    <header>
        <h2 class="text-lg font-medium text-black dark:text-white">
            Изменение пароля
        </h2>

    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div class="rounded-xl border border-orange-200/70 bg-white/80 px-4 py-3 text-sm text-stone-700 shadow-sm dark:border-stone-700 dark:bg-stone-900/40 dark:text-stone-200">
            Для подтверждения смены пароля сначала укажите текущий пароль, затем задайте новый.
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="update_password_current_password" value="Текущий пароль" />
                <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full rounded-xl border-orange-200 bg-white/95 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-orange-400 focus:ring-orange-400/30" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password" value="Новый пароль" />
                <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full rounded-xl border-orange-200 bg-white/95 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-orange-400 focus:ring-orange-400/30" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" value="Подтверждение пароля" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full rounded-xl border-orange-200 bg-white/95 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-orange-400 focus:ring-orange-400/30" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:gap-4">
            <button type="submit" class="ui-btn ui-btn--primary w-full justify-center sm:w-auto">
                Сохранить
            </button>

            @if (session('status') === 'password-updated')
                <x-app-alert
                    type="success"
                    class="w-full sm:flex-1"
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 4000)"
                >
                    Пароль обновлён.
                </x-app-alert>
            @endif
        </div>
    </form>
</section>
