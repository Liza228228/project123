@php // шаблон страницы
@endphp
@props([
    'name',
    'title',
    'confirmLabel' => 'Подтвердить',
    'cancelLabel' => 'Отмена',
    'variant' => 'primary',
    'formId' => null,
    'linkId' => null,
    'confirmHandler' => null,
])

@php
    $confirmBtnClass = $variant === 'danger'
        ? 'ui-btn ui-btn--danger'
        : 'ui-btn ui-btn--primary';
@endphp

<x-modal :name="$name" :show="false" maxWidth="md" focusable>
    <div class="p-5 sm:p-6 space-y-4">
        <h3 class="text-base sm:text-lg font-semibold text-black dark:text-white">
            {{ $title }}
        </h3>
        <div class="text-sm text-stone-700 dark:text-stone-300 leading-relaxed">
            {{ $slot }}
        </div>
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
            <button
                type="button"
                x-on:click="$dispatch('close-modal', '{{ $name }}')"
                class="ui-btn ui-btn--secondary w-full sm:w-auto"
            >
                {{ $cancelLabel }}
            </button>
            @if ($formId)
                <button
                    type="submit"
                    form="{{ $formId }}"
                    class="{{ $confirmBtnClass }} w-full sm:w-auto"
                >
                    {{ $confirmLabel }}
                </button>
            @elseif ($linkId)
                <button
                    type="button"
                    x-on:click="document.getElementById({{ Js::from($linkId) }})?.click(); $dispatch('close-modal', '{{ $name }}')"
                    class="{{ $confirmBtnClass }} w-full sm:w-auto"
                >
                    {{ $confirmLabel }}
                </button>
            @else
                <button
                    type="button"
                    x-on:click="
                        @if ($confirmHandler) {!! $confirmHandler !!} @endif
                        $dispatch('close-modal', '{{ $name }}');
                    "
                    class="{{ $confirmBtnClass }} w-full sm:w-auto"
                >
                    {{ $confirmLabel }}
                </button>
            @endif
        </div>
    </div>
</x-modal>
