@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-orange-200 dark:border-orange-800 dark:bg-orange-950/50 text-black dark:text-white placeholder:text-zinc-500 dark:placeholder:text-zinc-400 focus:border-orange-500 dark:focus:border-orange-400 focus:ring-orange-500 dark:focus:ring-orange-400 rounded-md shadow-sm']) }}>
