<section>
    <header>
        <h2 class="text-lg font-medium text-black dark:text-white">
            Данные профиля
        </h2>

        <p class="mt-1 text-sm text-black dark:text-white">
            Обновите данные профиля и адрес электронной почты.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Имя" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white focus:border-orange-500 focus:ring-orange-500" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Почта" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white focus:border-orange-500 focus:ring-orange-500" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex items-center rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:bg-orange-600 dark:text-white dark:hover:bg-orange-500 dark:focus:ring-offset-orange-950">
                Сохранить
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-black dark:text-white"
                >Сохранено.</p>
            @endif
        </div>
    </form>
</section>
