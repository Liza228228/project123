<x-guest-layout>
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-orange-900 dark:text-orange-100">Авторизация</h1>
       
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Почта" class="text-orange-700 dark:text-orange-200" />
            <x-text-input id="email" class="block mt-1 w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-100 focus:border-orange-500 focus:ring-orange-500" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Пароль" class="text-orange-700 dark:text-orange-200" />
            <x-text-input id="password" class="block mt-1 w-full rounded-lg border-orange-300 dark:border-orange-600 dark:bg-orange-900 dark:text-orange-100 focus:border-orange-500 focus:ring-orange-500" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-orange-300 dark:border-orange-600 dark:bg-orange-900 text-orange-700 shadow-sm focus:ring-orange-500 dark:focus:ring-orange-400" name="remember">
                <span class="ms-2 text-sm text-orange-600 dark:text-orange-300">Запомнить меня</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-orange-600 dark:text-orange-300 hover:text-orange-900 dark:hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 dark:focus:ring-offset-orange-800" href="{{ route('password.request') }}">
                    Забыли пароль?
                </a>
            @endif

            <button type="submit" class="ms-3 inline-flex items-center rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:bg-orange-500 dark:text-white dark:hover:bg-orange-400 dark:focus:ring-offset-orange-950">
                Войти
            </button>
        </div>
    </form>
</x-guest-layout>