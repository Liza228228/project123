<section>
    <header>
        <h2 class="text-lg font-medium text-black dark:text-white">
            Данные профиля
        </h2>

        <p class="mt-1 text-sm text-black dark:text-white">
            Обновите фамилию, имя, отчество и адрес электронной почты (не более 45 символов в каждом поле ФИО, до 50 — в почте).
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="surname" value="Фамилия" />
            <x-text-input id="surname" name="surname" type="text" maxlength="45" class="mt-1 block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-stone-500 focus:ring-stone-500" :value="old('surname', $user->surname)" required autofocus autocomplete="family-name" />
            <x-input-error class="mt-2" :messages="$errors->get('surname')" />
        </div>

        <div>
            <x-input-label for="name" value="Имя" />
            <x-text-input id="name" name="name" type="text" maxlength="45" class="mt-1 block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-stone-500 focus:ring-stone-500" :value="old('name', $user->name)" required autocomplete="given-name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="patronymic" value="Отчество" />
            <x-text-input id="patronymic" name="patronymic" type="text" maxlength="45" class="mt-1 block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-stone-500 focus:ring-stone-500" :value="old('patronymic', $user->patronymic)" required autocomplete="additional-name" />
            <x-input-error class="mt-2" :messages="$errors->get('patronymic')" />
        </div>

        <div>
            <x-input-label for="email" value="Почта" />
            <x-text-input id="email" name="email" type="email" maxlength="50" class="mt-1 block w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-stone-500 focus:ring-stone-500" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="ui-btn ui-btn--primary">
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
