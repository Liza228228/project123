@props([
    'variant' => 'default', // default | compact
])

@php
    $base = 'group relative shrink-0 rounded-full border shadow-sm transition focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950';
    $sizes = $variant === 'compact'
        ? 'h-8 w-14'
        : 'h-9 w-16';
@endphp

<button
    type="button"
    data-theme-toggle
    {{ $attributes->merge(['class' => $base.' '.$sizes.' border-orange-300 bg-gradient-to-b from-white to-orange-50 hover:border-orange-400 dark:border-orange-600 dark:from-orange-900 dark:to-orange-950 dark:hover:border-orange-500']) }}
    title="Переключить светлую и тёмную тему"
>
    <span class="sr-only">Переключить тему оформления</span>
    <span
        class="pointer-events-none absolute top-1 left-1 flex h-7 w-7 items-center justify-center rounded-full bg-white text-amber-500 shadow-md ring-1 ring-orange-200/80 transition-transform duration-200 ease-out dark:translate-x-7 dark:bg-orange-100 dark:text-orange-900 dark:ring-orange-400/50"
    >
        {{-- Светлая тема активна: показываем солнце --}}
        <svg class="h-4 w-4 dark:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
        </svg>
        {{-- Тёмная тема активна: показываем луну --}}
        <svg class="hidden h-4 w-4 dark:inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
        </svg>
    </span>
</button>
