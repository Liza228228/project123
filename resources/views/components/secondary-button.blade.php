<button {{ $attributes->merge(['type' => 'button', 'class' => 'ui-btn ui-btn--secondary']) }}>
    {{ $slot }}
</button>
