@php // шаблон страницы
@endphp
<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-black dark:text-white">Авторизация</h1>
        <p class="mt-2 text-sm text-black/65 dark:text-white/60 leading-relaxed">Введите почту и пароль для доступа к заявкам и материалам.</p>
    </div>

    <x-flash-messages class="mb-4" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Почта" class="text-black dark:text-white" />
            <x-text-input id="email" class="block mt-1 w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-stone-500 focus:ring-stone-500" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Пароль" class="text-black dark:text-white" />
            <x-text-input id="password" class="block mt-1 w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-white focus:border-stone-500 focus:ring-stone-500" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            @if (Route::has('password.request'))
              
            @endif

            <button type="submit" class="ui-btn ui-btn--primary w-full justify-center sm:ms-3 sm:w-auto">
                Войти
            </button>
        </div>
    </form>
</x-guest-layout>