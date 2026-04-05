<section>
    <header>
        <h2 class="text-lg font-medium text-orange-950 dark:text-orange-50">
            Данные профиля
        </h2>

        <p class="mt-1 text-sm text-orange-800 dark:text-orange-300">
            Обновите данные профиля и адрес электронной почты.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Имя" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-100 focus:border-orange-500 focus:ring-orange-500" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Почта" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-100 focus:border-orange-500 focus:ring-orange-500" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-orange-950 dark:text-orange-100">
                        Адрес электронной почты не подтвержден.

                        <button form="send-verification" class="underline text-sm text-orange-600 dark:text-orange-300 hover:text-orange-900 dark:hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 dark:focus:ring-offset-orange-800">
                            Нажмите здесь, чтобы отправить письмо для подтверждения повторно.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-orange-800 dark:text-orange-300">
                            Новая ссылка для подтверждения отправлена на вашу почту.
                        </p>
                    @endif
                </div>
            @endif
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
                    class="text-sm text-orange-800 dark:text-orange-300"
                >Сохранено.</p>
            @endif
        </div>
    </form>
</section>
