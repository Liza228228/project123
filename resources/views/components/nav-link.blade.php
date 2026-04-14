@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-orange-600 dark:border-orange-400 text-sm font-medium leading-5 text-stone-900 dark:text-stone-100 focus:outline-none focus:border-orange-700 dark:focus:border-orange-300 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-stone-800 dark:text-stone-200 opacity-80 hover:opacity-100 hover:border-orange-200 dark:hover:border-orange-800 focus:outline-none focus:opacity-100 focus:border-orange-200 dark:focus:border-orange-800 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
