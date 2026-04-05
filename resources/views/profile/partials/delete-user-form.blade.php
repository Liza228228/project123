<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-orange-950 dark:text-orange-50">
            Удаление аккаунта
        </h2>

        <p class="mt-1 text-sm text-orange-800 dark:text-orange-300">
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

            <h2 class="text-lg font-medium text-orange-950 dark:text-orange-50">
                Вы уверены, что хотите удалить аккаунт?
            </h2>

            <p class="mt-1 text-sm text-orange-800 dark:text-orange-300">
                После удаления аккаунта все данные будут удалены безвозвратно. Введите пароль, чтобы подтвердить удаление.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Пароль" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4 rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-100 focus:border-orange-500 focus:ring-orange-500"
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
