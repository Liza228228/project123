{{-- Выбор склада → values[подразделение] и адрес объекта. --}}
<div class="rounded-xl border border-orange-200/75 bg-white p-4 dark:border-orange-900/40 dark:bg-stone-900/40 space-y-3">
    <label class="block text-sm font-medium text-stone-800 dark:text-stone-200 mb-1"
           x-text="field.label || 'Склад'"></label>
    <select class="app-select"
            x-model="subdivisionWarehouseRef"
            @change="applySubdivisionWarehouseSelection()">
        <option value="">— Выберите склад —</option>
        <template x-for="opt in subdivisionWarehouseWarehouseOptions()" :key="opt.value">
            <option :value="opt.value" x-text="opt.label"></option>
        </template>
    </select>
    <input type="hidden" name="values[_подразделение_ref]" x-model="subdivisionWarehouseRef"/>
    <div class="overflow-hidden rounded-xl border-2 border-orange-400/90 shadow-sm dark:border-orange-600/70">
        @include('boiler-chief.layout-applications._rich-field-toolbar')
        <div contenteditable="true" spellcheck="false"
             class="layout-field-editor min-h-[4rem] w-full bg-white px-3 py-2.5 text-sm text-stone-900 outline-none focus:ring-2 focus:ring-orange-400/30 focus:ring-inset dark:bg-stone-950 dark:text-stone-100"
             :id="'editor-' + field.slug"
             @focus="setActiveEditorField(field.key)"
             @input="syncRich(field.key, $event.target)"></div>
        <input type="hidden" :name="'values[' + field.key + ']'" :id="'hidden-' + field.slug"/>
    </div>
    <p class="text-xs text-stone-500 dark:text-stone-400">Адрес объекта заполнится автоматически по выбранному складу.</p>
</div>
