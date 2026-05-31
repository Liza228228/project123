@php // шаблон страницы
@endphp
@props(['block' => 'subdivision'])
@if($block === 'subdivision')
    @php
        $subVis = old('field_change_reasons.subdivision_id') || $errors->has('field_change_reasons.subdivision_id');
    @endphp
    <div id="field-reason-subdivision-wrap" class="sm:col-span-2 space-y-1 {{ $subVis ? '' : 'hidden' }}">
        <label for="field_change_reasons_subdivision_id" class="app-form-label">Комментарий: смена подразделения</label>
        <textarea
            id="field_change_reasons_subdivision_id"
            name="field_change_reasons[subdivision_id]"
            rows="2"
            maxlength="500"
            class="app-input min-h-[4rem] text-sm"
        >{{ old('field_change_reasons.subdivision_id') }}</textarea>
        <x-input-error :messages="$errors->get('field_change_reasons.subdivision_id')" class="mt-1.5" />
    </div>
@elseif($block === 'delivery')
    @php
        $delVis = old('field_change_reasons.desired_delivery_date') || $errors->has('field_change_reasons.desired_delivery_date');
    @endphp
    <div id="field-reason-delivery-wrap" class="w-full max-w-full sm:max-w-xs space-y-1 {{ $delVis ? '' : 'hidden' }}">
        <label for="field_change_reasons_desired_delivery_date" class="app-form-label">Комментарий: изменение даты поставки</label>
        <textarea
            id="field_change_reasons_desired_delivery_date"
            name="field_change_reasons[desired_delivery_date]"
            rows="2"
            maxlength="500"
            class="app-input min-h-[4rem] text-sm"
        >{{ old('field_change_reasons.desired_delivery_date') }}</textarea>
        <x-input-error :messages="$errors->get('field_change_reasons.desired_delivery_date')" class="mt-1.5" />
    </div>
@endif
