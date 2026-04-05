@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-orange-500 dark:border-orange-300 text-start text-base font-medium text-orange-800 dark:text-orange-100 bg-orange-100 dark:bg-orange-800/70 focus:outline-none focus:text-orange-900 dark:focus:text-white focus:bg-orange-200 dark:focus:bg-orange-800 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-orange-600 dark:text-orange-400 hover:text-orange-800 dark:hover:text-orange-200 hover:bg-orange-50 dark:hover:bg-orange-800 hover:border-orange-300 dark:hover:border-orange-600 focus:outline-none focus:text-orange-800 dark:focus:text-orange-200 focus:bg-orange-50 dark:focus:bg-orange-800 focus:border-orange-300 dark:focus:border-orange-600 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
