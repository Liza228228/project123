@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-orange-700 dark:border-orange-300 text-start text-base font-medium text-stone-900 dark:text-stone-100 bg-orange-100/95 dark:bg-orange-950/50 focus:outline-none focus:text-stone-900 dark:focus:text-stone-100 focus:bg-orange-200/70 dark:focus:bg-orange-950/60 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-stone-800 dark:text-stone-200 opacity-90 hover:opacity-100 hover:bg-orange-100/60 dark:hover:bg-stone-800 hover:border-orange-300 dark:hover:border-orange-700 focus:outline-none focus:opacity-100 focus:bg-orange-100/60 dark:focus:bg-stone-800 focus:border-orange-300 dark:focus:border-orange-700 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
