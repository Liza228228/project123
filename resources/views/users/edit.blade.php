<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('users.index') }}" class="text-orange-800 dark:text-orange-300 hover:text-orange-950 dark:hover:text-orange-50 text-sm">← Управление пользователями</a>
            <h2 class="font-semibold text-xl text-orange-950 dark:text-orange-50 leading-tight">
                Изменить данные пользователя
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-orange-950 overflow-hidden shadow-sm sm:rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="p-6 max-w-md">
                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="surname" value="Фамилия" />
                            <x-text-input id="surname" class="block mt-1 w-full" type="text" name="surname" :value="old('surname', $user->surname)" required autofocus />
                            <x-input-error :messages="$errors->get('surname')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="name" value="Имя" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $user->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="patronymic" value="Отчество" />
                            <x-text-input id="patronymic" class="block mt-1 w-full" type="text" name="patronymic" :value="old('patronymic', $user->patronymic)" required />
                            <x-input-error :messages="$errors->get('patronymic')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="email" value="Почта" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $user->email)" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="role_id" value="Роль" />
                            <select id="role_id" name="role_id" class="block mt-1 w-full rounded-md border-orange-200 dark:border-orange-800 dark:bg-orange-950 dark:text-orange-100 shadow-sm focus:ring-orange-500 dark:focus:ring-orange-400 dark:focus:ring-offset-orange-950" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="password" value="Новый пароль" />
                            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="password_confirmation" value="Подтверждение пароля" />
                            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4 mt-6">
                            <x-primary-button>Сохранить изменения</x-primary-button>
                            <a href="{{ route('users.index') }}" class="text-sm text-orange-800 dark:text-orange-300 hover:text-orange-950 dark:hover:text-orange-50">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
