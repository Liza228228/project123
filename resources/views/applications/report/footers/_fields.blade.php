<div class="space-y-5">
    <div>
        <label for="ftr-name" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Название шаблона</label>
        <input type="text" name="name" id="ftr-name" value="{{ old('name', $nameValue ?? '') }}" required maxlength="255"
            class="w-full max-w-xl rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500"
            placeholder="Например: Подписи комиссии">
    </div>
    <div>
        <label for="ftr-font-size" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Размер шрифта (pt)</label>
        <input type="number" name="font_size" id="ftr-font-size" min="8" max="36" value="{{ old('font_size', $fontSize ?? 14) }}"
            class="w-full max-w-xs rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
    </div>
</div>
