<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Удаление аккаунта
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            После удаления аккаунта все связанные данные будут удалены безвозвратно. Перед удалением сохраните нужную информацию.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="rounded-lg px-5 py-2.5"
    >Удалить аккаунт</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Вы уверены, что хотите удалить аккаунт?
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                После удаления аккаунта все данные будут удалены безвозвратно. Введите пароль, чтобы подтвердить удаление.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Пароль" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Пароль"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Отмена
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    Удалить аккаунт
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
