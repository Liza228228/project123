@php // шаблон страницы
@endphp
@props(['status'])

@if ($status)
    <x-app-alert type="info" {{ $attributes }}>
        {{ $status }}
    </x-app-alert>
@endif
