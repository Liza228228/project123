@php // шаблон страницы
@endphp
<x-guest-layout>
    <div class="mb-4 text-sm text-black dark:text-white">
        Забыли пароль? Укажите почту — мы отправим ссылку для сброса пароля.
    </div>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Почта" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
            <button type="submit" class="ui-btn ui-btn--primary w-full justify-center sm:w-auto">
                Отправить ссылку для сброса пароля
            </button>
        </div>
    </form>
</x-guest-layout>
