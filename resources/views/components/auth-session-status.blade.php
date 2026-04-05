@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-orange-800 dark:text-orange-300']) }}>
        {{ $status }}
    </div>
@endif
