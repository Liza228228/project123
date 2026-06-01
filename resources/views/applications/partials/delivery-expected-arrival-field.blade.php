@php
    // шаблон страницы
    $uid = (string) ($fieldUid ?? 'delivery-arrival');
    $nameExpectedArrival = $nameExpectedArrival ?? null;
    $expectedArrivalValue = trim((string) ($expectedArrivalValue ?? ''));
    $fieldsRequired = ! ($optional ?? false) && filled($nameExpectedArrival);
@endphp
<div class="w-full" data-delivery-expected-arrival-field>
    <label for="{{ $uid }}-expected-arrival" class="app-form-label !normal-case">Дата прибытия</label>
    <input
        id="{{ $uid }}-expected-arrival"
        type="date"
        @if($nameExpectedArrival) name="{{ $nameExpectedArrival }}" @endif
        value="{{ $expectedArrivalValue }}"
        min="{{ now()->format('Y-m-d') }}"
        class="app-input text-sm w-full sm:max-w-md delivery-expected-arrival-input"
        data-delivery-expected-arrival
        @if($fieldsRequired) required @endif
    />
</div>
