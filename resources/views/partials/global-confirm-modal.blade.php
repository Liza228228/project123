@php // шаблон страницы
@endphp
<x-modal name="app-confirm" :show="false" maxWidth="md" focusable>
    <div class="p-5 sm:p-6 space-y-4">
        <h3 id="app-confirm-title" class="text-base sm:text-lg font-semibold text-black dark:text-white">
            Подтверждение действия
        </h3>
        <p id="app-confirm-message" class="text-sm text-stone-700 dark:text-stone-300 leading-relaxed"></p>
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
            <button
                type="button"
                id="app-confirm-cancel"
                class="ui-btn ui-btn--secondary w-full sm:w-auto"
            >
                Отмена
            </button>
            <button
                type="button"
                id="app-confirm-ok"
                class="ui-btn ui-btn--primary w-full sm:w-auto"
            >
                Подтвердить
            </button>
        </div>
    </div>
</x-modal>
