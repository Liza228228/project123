@php // шаблон страницы
@endphp
@props([
    'itemId' => null,
    'dbItem' => null,
    'locked' => false,
    'rowMode' => 'list',
])
@if($itemId && $dbItem && ! $locked)
    @php
        $id = (int) $itemId;
    @endphp
    <div
        class="item-field-change-reasons mt-3 space-y-1 border-t border-stone-200/80 pt-3 dark:border-stone-600/80 {{ $errors->has('item_change_reasons.'.$id) ? '' : 'hidden' }}"
        data-item-field-change-reasons="{{ $id }}"
        data-row-mode="{{ $rowMode }}"
        data-initial-equipment-id="{{ (string) ($dbItem->equipment_id ?? '') }}"
        data-initial-equipment-name="{{ e(trim((string) ($dbItem->equipment_name ?? ''))) }}"
        data-initial-quantity="{{ (int) $dbItem->quantity }}"
        data-initial-measurement-type="{{ e((string) ($dbItem->measurement_type ?? 'piece')) }}"
        data-initial-quantity-unit="{{ e((string) ($dbItem->quantity_unit ?? 'шт')) }}"
        data-initial-size-value="{{ e(trim((string) ($dbItem->size_value ?? ''))) }}"
        data-server-error="{{ $errors->has('item_change_reasons.'.$id) ? '1' : '0' }}"
    >
        <label class="app-form-label !text-xs" for="item_cr_{{ $id }}">Комментарий: почему изменили позицию</label>
        <p class="text-xs text-stone-600 dark:text-stone-400">Увидит мастер участка при просмотре заявки.</p>
        <textarea
            id="item_cr_{{ $id }}"
            name="item_change_reasons[{{ $id }}]"
            rows="2"
            maxlength="500"
            class="app-input min-h-[4rem] text-sm"
        >{{ old('item_change_reasons.'.$id) }}</textarea>
        <x-input-error :messages="$errors->get('item_change_reasons.'.$id)" class="mt-1" />
    </div>
@endif
