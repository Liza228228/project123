<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white dark:bg-orange-950 border border-orange-200 dark:border-orange-600 rounded-md font-semibold text-xs text-orange-900 dark:text-orange-100 uppercase tracking-widest shadow-sm hover:bg-orange-50 dark:hover:bg-orange-900/50 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-orange-950 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
