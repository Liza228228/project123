@props(['idx'])
@php
    $i = (int) $idx;
    $visible = old('items.'.$i.'.addition_reason') || $errors->has('items.'.$i.'.addition_reason');
@endphp
<div
    class="new-line-field-change-reasons mt-3 space-y-1 border-t border-stone-200/80 pt-3 dark:border-stone-600/80 {{ $visible ? '' : 'hidden' }}"
    data-new-line-change-reasons="{{ $i }}"
    data-server-error="{{ $errors->has('items.'.$i.'.addition_reason') ? '1' : '0' }}"
>
    <label class="app-form-label !text-xs" for="new_line_cr_{{ $i }}">Комментарий: почему добавляете позицию</label>
    <p class="text-xs text-stone-600 dark:text-stone-400">Увидит мастер участка при просмотре заявки.</p>
    <textarea
        id="new_line_cr_{{ $i }}"
        name="items[{{ $i }}][addition_reason]"
        rows="2"
        maxlength="500"
        class="app-input min-h-[4rem] text-sm"
    >{{ old('items.'.$i.'.addition_reason') }}</textarea>
    <x-input-error :messages="$errors->get('items.'.$i.'.addition_reason')" class="mt-1" />
</div>
