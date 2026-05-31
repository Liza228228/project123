@php // шаблон страницы
@endphp
@props([
    'type' => 'success',
    'title' => null,
    'dismissible' => false,
])

@php
    $type = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'success';
    $typeClasses = match ($type) {
        'error' => 'app-alert--error',
        'warning' => 'app-alert--warning',
        'info' => 'app-alert--info',
        default => 'app-alert--success',
    };
    $iconPaths = match ($type) {
        'error' => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
        'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 9.75h.007v.008H12V9.75z',
        'info' => 'M11.25 11.25l.041 3.008M12 6.75h.008v.008H12V6.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z',
        default => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    };
@endphp

<div
    {{ $attributes->class(['app-alert', $typeClasses]) }}
    role="alert"
    @if ($dismissible) x-data="{ open: true }" x-show="open" x-transition.opacity @endif
>
    <span class="app-alert__icon" aria-hidden="true">
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPaths }}" />
        </svg>
    </span>
    <div class="app-alert__body min-w-0 flex-1">
        @if ($title)
            <p class="app-alert__title">{{ $title }}</p>
        @endif
        <div class="app-alert__message">{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button
            type="button"
            class="app-alert__dismiss"
            @click="open = false"
            aria-label="Закрыть уведомление"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
