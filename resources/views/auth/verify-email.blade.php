<x-guest-layout>
    <div class="mb-4 text-sm text-black dark:text-white">
        Спасибо за регистрацию. Подтвердите почту, перейдя по ссылке из письма. Если письмо не пришло, мы можем отправить его снова.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-black dark:text-white">
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

            <button type="submit" class="underline text-sm text-black dark:text-white hover:text-black dark:hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 dark:focus:ring-offset-orange-950">
                Выйти
            </button>
        </form>
    </div>
</x-guest-layout>
