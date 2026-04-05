@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-orange-900 dark:text-orange-200']) }}>
    {{ $value ?? $slot }}
</label>
