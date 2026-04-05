<x-guest-layout>
    <div class="mb-4 text-sm text-orange-900 dark:text-orange-200">
        Спасибо за регистрацию. Подтвердите почту, перейдя по ссылке из письма. Если письмо не пришло, мы можем отправить его снова.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-orange-800 dark:text-orange-300">
            Новая ссылка для подтверждения отправлена на указанную почту.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Отправить письмо повторно
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-orange-800 dark:text-orange-300 hover:text-orange-950 dark:hover:text-orange-50 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 dark:focus:ring-offset-orange-950">
                Выйти
            </button>
        </form>
    </div>
</x-guest-layout>
