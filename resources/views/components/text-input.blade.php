@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-stone-200 bg-white dark:border-stone-600 dark:bg-stone-900/40 text-stone-900 dark:text-stone-100 placeholder:text-stone-400 dark:placeholder:text-stone-500 focus:border-orange-400/90 focus:ring-2 focus:ring-orange-500/25 dark:focus:border-orange-600/70 dark:focus:ring-orange-500/20 shadow-sm']) }}>
