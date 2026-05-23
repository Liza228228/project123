@php
    $hasCommercialOffer = filled(trim((string) ($application->commercial_offer ?? '')));
@endphp
@if($hasCommercialOffer)
    <a
        href="{{ route('applications.commercial-offer.view', $application) }}"
        target="_blank"
        rel="noopener"
        class="ui-btn ui-btn--secondary ui-btn--sm inline-flex w-fit max-w-full"
        title="Открыть коммерческое предложение"
    >
        Просмотр КП
    </a>
@endif
