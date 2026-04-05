<section>
    <header>
        <h2 class="text-lg font-medium text-orange-950 dark:text-orange-50">
            Изменение пароля
        </h2>

        <p class="mt-1 text-sm text-orange-800 dark:text-orange-300">
            Используйте надежный пароль для безопасности учетной записи.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Текущий пароль" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-100 focus:border-orange-500 focus:ring-orange-500" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Новый пароль" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-100 focus:border-orange-500 focus:ring-orange-500" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Подтверждение пароля" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-100 focus:border-orange-500 focus:ring-orange-500" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex items-center rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:bg-orange-600 dark:text-white dark:hover:bg-orange-500 dark:focus:ring-offset-orange-950">
                Сохранить
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-orange-800 dark:text-orange-300"
                >Сохранено.</p>
            @endif
        </div>
    </form>
</section>
