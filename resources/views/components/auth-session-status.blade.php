@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-black dark:text-white']) }}>
        {{ $status }}
    </div>
@endif
