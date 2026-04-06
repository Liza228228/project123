@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-orange-500 dark:border-orange-300 text-sm font-medium leading-5 text-black dark:text-white focus:outline-none focus:border-orange-600 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-black dark:text-white opacity-70 hover:opacity-100 hover:border-orange-300 dark:hover:border-orange-600 focus:outline-none focus:opacity-100 focus:border-orange-300 dark:focus:border-orange-600 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
