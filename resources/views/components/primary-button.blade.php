<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-btn ui-btn--primary']) }}>
    {{ $slot }}
</button>
