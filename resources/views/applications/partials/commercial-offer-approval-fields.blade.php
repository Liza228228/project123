@props([
    'application',
    'checkboxName',
    'reasonName',
    'showViewLink' => false,
])

@php
    $oldChecked = old($checkboxName, '1');
    $isCheckedOld = (string) $oldChecked === '1';
    $existingReason = $reasonName === 'commercial_offer_chief_reason_not_selected'
        ? ($application->commercial_offer_chief_reason_not_selected ?? '')
        : ($application->commercial_offer_management_reason_not_selected ?? '');
@endphp

<div class="rounded-xl border border-stone-200/90 bg-white px-4 py-3 dark:border-stone-600 dark:bg-stone-900/40">
    @if($showViewLink)
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-stone-900 dark:text-white">Коммерческое предложение</span>
            <a
                href="{{ route('applications.commercial-offer.view', $application) }}"
                target="_blank"
                rel="noopener"
                class="ui-btn ui-btn--secondary ui-btn--sm"
            >
                Открыть КП
            </a>
        </div>
    @endif
    <div class="flex items-start gap-3">
        <input type="hidden" name="{{ $checkboxName }}" value="0">
        <input
            type="checkbox"
            id="{{ $checkboxName }}"
            name="{{ $checkboxName }}"
            value="1"
            class="co-approval-checkbox mt-0.5 h-5 w-5 shrink-0 rounded border-stone-200 text-black shadow-sm focus:ring-stone-500 dark:border-stone-700 dark:bg-stone-900 dark:checked:bg-stone-700"
            @checked($isCheckedOld)
        />
        <label for="{{ $checkboxName }}" class="text-sm font-medium text-stone-900 dark:text-white cursor-pointer">
            Согласовать коммерческое предложение
        </label>
    </div>
    <div class="co-approval-reason-block mt-3 pl-8 {{ $isCheckedOld ? 'hidden' : '' }}">
        <label for="{{ $reasonName }}" class="block text-xs text-stone-700 dark:text-stone-300 mb-0.5">
            Причина не согласования
        </label>
        <input
            type="text"
            id="{{ $reasonName }}"
            name="{{ $reasonName }}"
            value="{{ $isCheckedOld ? '' : old($reasonName, $existingReason) }}"
            maxlength="500"
            placeholder="Обязательно, если КП не согласовано"
            class="co-approval-reason-input app-input text-sm w-full @error($reasonName) !border-red-500 dark:!border-red-400 @enderror"
        />
        @error($reasonName)
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>
</div>
