@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-rose-700 dark:text-rose-300 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li class="flex items-start gap-1.5">
                <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-rose-500 dark:bg-rose-400" aria-hidden="true"></span>
                <span>{{ $message }}</span>
            </li>
        @endforeach
    </ul>
@endif
