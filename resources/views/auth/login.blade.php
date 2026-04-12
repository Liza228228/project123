<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-black dark:text-white">Авторизация</h1>
        <p class="mt-2 text-sm text-black/65 dark:text-white/60 leading-relaxed">Введите почту и пароль для доступа к заявкам и материалам.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Почта" class="text-black dark:text-white" />
            <x-text-input id="email" class="block mt-1 w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white focus:border-orange-500 focus:ring-orange-500" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Пароль" class="text-black dark:text-white" />
            <x-text-input id="password" class="block mt-1 w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-white focus:border-orange-500 focus:ring-orange-500" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-black dark:text-white hover:text-black dark:hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 dark:focus:ring-offset-orange-800" href="{{ route('password.request') }}">
                    Забыли пароль?
                </a>
            @endif

            <button type="submit" class="ms-3 inline-flex items-center rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:bg-orange-500 dark:text-white dark:hover:bg-orange-400 dark:focus:ring-offset-orange-950">
                Войти
            </button>
        </div>
    </form>
</x-guest-layout>