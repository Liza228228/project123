@php // шаблон страницы
@endphp
@props(['title' => 'Исправьте ошибки в форме'])

@if ($errors->any())
    <x-app-alert type="error" :title="$title" {{ $attributes }}>
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-app-alert>
@endif
