@php // шаблон страницы
@endphp
<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div>
            <x-input-label for="surname" value="Фамилия" />
            <x-text-input id="surname" class="block mt-1 w-full" type="text" name="surname" :value="old('surname')" required autofocus autocomplete="family-name" />
            <x-input-error :messages="$errors->get('surname')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="name" value="Имя" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autocomplete="given-name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="patronymic" value="Отчество" />
            <x-text-input id="patronymic" class="block mt-1 w-full" type="text" name="patronymic" :value="old('patronymic')" required autocomplete="additional-name" />
            <x-input-error :messages="$errors->get('patronymic')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" value="Почта" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="role_id" value="Роль" />
            <select id="role_id" name="role_id" class="block mt-1 w-full rounded-md border-stone-200 dark:border-stone-800 dark:bg-stone-950 dark:text-white shadow-sm focus:ring-stone-500 dark:focus:ring-stone-400 dark:focus:ring-offset-stone-950" required>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Пароль" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Подтверждение пароля" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-black dark:text-white hover:text-black dark:hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stone-500 dark:focus:ring-offset-stone-950" href="{{ route('login') }}">
                Уже зарегистрированы?
            </a>

            <x-primary-button class="ms-4">
                Зарегистрироваться
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
