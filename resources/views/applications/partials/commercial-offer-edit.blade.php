@php
    $commercialOfferDraftReady = $commercialOfferDraftReady ?? false;
    $hasReplacement = $commercialOfferDraftReady
        || $errors->has('commercial_offer')
        || old('replace_commercial_offer') === '1';
@endphp
<section class="space-y-3 border-t border-stone-100 pt-8 dark:border-stone-800" aria-labelledby="edit-section-commercial-offer">
    <h3 id="edit-section-commercial-offer" class="app-section-title">Коммерческое предложение</h3>

    <div class="rounded-xl border border-stone-200/80 bg-stone-50/50 px-4 py-3 dark:border-stone-700/80 dark:bg-stone-900/30">
        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400">Текущий файл</p>
        <p class="mt-1 text-sm font-medium text-stone-900 dark:text-stone-100 break-all">
            {{ basename($application->commercial_offer) }}
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
            <a
                href="{{ route('applications.commercial-offer.view', $application) }}"
                target="_blank"
                rel="noopener"
                class="ui-btn ui-btn--secondary ui-btn--sm"
            >
                Открыть
            </a>
            <a
                href="{{ route('applications.commercial-offer.download', $application) }}"
                class="ui-btn ui-btn--secondary ui-btn--sm"
            >
                Скачать
            </a>
        </div>
    </div>

    <input type="hidden" name="replace_commercial_offer" id="replace-commercial-offer-input" value="{{ $hasReplacement ? '1' : '0' }}">
    <input type="hidden" name="use_commercial_offer_draft" id="use-commercial-offer-draft-input" value="{{ ($commercialOfferDraftReady && ! old('commercial_offer')) ? '1' : '0' }}">

    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap {{ $hasReplacement ? 'hidden' : '' }}" id="commercial-offer-replace-actions">
        <button
            type="button"
            id="replace-commercial-offer-file-btn"
            class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto"
        >
            Заменить файлом
        </button>
        <a
            href="{{ $commercialProposalFillUrl }}"
            id="fill-commercial-proposal-edit-btn"
            class="ui-btn ui-btn--secondary ui-btn--sm w-full sm:w-auto text-center"
        >
            Заполнить
        </a>
    </div>

    <div id="commercial-offer-replace-block" class="{{ $hasReplacement ? '' : 'hidden' }} space-y-3 rounded-xl border border-dashed border-orange-300/80 bg-orange-50/40 p-4 dark:border-orange-800/50 dark:bg-orange-950/20">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <label for="commercial_offer" class="app-form-label !mb-0">Новый файл</label>
            <button type="button" id="cancel-commercial-offer-replace-btn" class="min-h-10 shrink-0 rounded-lg px-2 text-xs font-medium text-stone-500 underline decoration-stone-300 hover:bg-stone-100 hover:text-stone-800 dark:text-stone-400 dark:hover:bg-stone-800/60 dark:hover:text-stone-200">
                Отменить замену
            </button>
        </div>
        @if ($commercialOfferDraftReady && ! $errors->has('commercial_offer'))
            <div class="rounded-lg border border-emerald-200/80 bg-emerald-50/70 px-3 py-2 text-sm text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/25 dark:text-emerald-100">
                PDF сформирован и заменит текущее КП после сохранения заявки. Чтобы прикрепить другой файл, выберите документ ниже.
            </div>
        @endif
        <input
            id="commercial_offer"
            type="file"
            name="commercial_offer"
            accept=".pdf,.docx"
            class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-orange-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-stone-800 hover:file:bg-orange-200/90 dark:text-stone-300 dark:file:bg-orange-950/50 dark:file:text-orange-100 dark:hover:file:bg-orange-900/60"
        />
        <p class="text-xs text-stone-500 dark:text-stone-400">
            Только PDF или DOCX · до 10 МБ. Если файл не выбран, будет использован сформированный PDF.
        </p>
        <x-input-error :messages="$errors->get('commercial_offer')" class="mt-1" />
    </div>
</section>
