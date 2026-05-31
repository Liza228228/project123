@php // шаблон страницы
@endphp
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-btn ui-btn--danger']) }}>
    {{ $slot }}
</button>
