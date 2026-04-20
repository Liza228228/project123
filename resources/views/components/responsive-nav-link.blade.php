@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex min-h-[3rem] w-full items-center border-l-4 border-orange-700 ps-3 pe-4 py-2 text-start text-base font-medium text-stone-900 dark:border-orange-300 dark:text-stone-100 bg-orange-100/95 dark:bg-orange-950/50 focus:outline-none focus:text-stone-900 dark:focus:text-stone-100 focus:bg-orange-200/70 dark:focus:bg-orange-950/60 active:bg-orange-200/80 dark:active:bg-orange-950/70 transition duration-150 ease-in-out'
            : 'flex min-h-[3rem] w-full items-center border-l-4 border-transparent ps-3 pe-4 py-2 text-start text-base font-medium text-stone-800 dark:text-stone-200 opacity-90 hover:border-orange-300 hover:bg-orange-100/60 hover:opacity-100 dark:hover:border-orange-700 dark:hover:bg-stone-800 focus:outline-none focus:border-orange-300 focus:bg-orange-100/60 focus:opacity-100 dark:focus:border-orange-700 dark:focus:bg-stone-800 active:bg-orange-100/80 dark:active:bg-stone-800/90 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
