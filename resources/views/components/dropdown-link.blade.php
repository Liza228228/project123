@php // шаблон страницы
@endphp
<a {{ $attributes->merge(['class' => 'block w-full px-4 py-2 text-start text-sm leading-5 text-stone-900 dark:text-stone-100 hover:bg-orange-50/80 dark:hover:bg-orange-600/25 focus:outline-none focus:bg-orange-50/80 dark:focus:bg-orange-950/25 transition duration-150 ease-in-out']) }}>{{ $slot }}</a>
