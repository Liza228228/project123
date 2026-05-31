@php // шаблон страницы
@endphp
@props(['href'])
<nav {{ $attributes->class(['page-header-nav']) }} aria-label="Навигация к разделу">
    <a
        href="{{ $href }}"
        class="ui-btn ui-btn--secondary ui-btn--sm w-fit max-w-full shrink-0 items-start text-left [touch-action:manipulation]"
    >
        <span class="mt-px shrink-0 font-normal" aria-hidden="true">←</span>
        <span class="min-w-0 break-words whitespace-normal text-left">{{ $slot }}</span>
    </a>
</nav>
