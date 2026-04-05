@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-orange-200 dark:border-orange-800 dark:bg-orange-950/50 dark:text-orange-100 focus:border-orange-500 dark:focus:border-orange-400 focus:ring-orange-500 dark:focus:ring-orange-400 rounded-md shadow-sm']) }}>
