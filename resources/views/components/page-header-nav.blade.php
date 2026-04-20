@props(['href'])
<nav {{ $attributes->class(['page-header-nav']) }} aria-label="Навигация к разделу">
    <a
        href="{{ $href }}"
        class="ui-btn ui-btn--secondary ui-btn--sm w-fit max-w-full shrink-0 [touch-action:manipulation]"
    >
        <span class="shrink-0 font-normal" aria-hidden="true">←</span>
        <span class="min-w-0 truncate text-left">{{ $slot }}</span>
    </a>
</nav>
