@props([
    'application',
    'variant' => 'rejected',
    'showOrderButton' => false,
])

@php
    $liClass = match ($variant) {
        'approved' => 'px-4 py-3 bg-stone-100/60 dark:bg-stone-900/30 space-y-1',
        'pending' => 'px-4 py-3 bg-amber-50/50 dark:bg-amber-950/20 space-y-1',
        default => 'px-4 py-3 bg-stone-50/80 dark:bg-stone-900/25 space-y-1',
    };
@endphp

<li class="{{ $liClass }}">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span class="text-sm font-medium text-black dark:text-white">Коммерческое предложение</span>
        <span class="inline-flex shrink-0 items-center rounded-full border border-orange-300/90 bg-orange-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-900 dark:border-orange-700/60 dark:bg-orange-950/40 dark:text-orange-100">
            КП
        </span>
        <a
            href="{{ route('applications.commercial-offer.view', $application) }}"
            target="_blank"
            rel="noopener"
            class="ui-btn ui-btn--secondary ui-btn--sm"
        >
            Открыть КП
        </a>
        @if($showOrderButton && $variant === 'approved')
            <button
                type="button"
                class="ui-btn ui-btn--primary ui-btn--sm"
                data-app-open-modal="commercial-offer-order-lines"
            >
                Как заказать
            </button>
        @endif
    </div>
    @if($variant === 'pending')
        <p class="mt-1 text-xs text-black/80 dark:text-white/75">
            @if($application->commercialOfferChiefReviewPending())
                Ожидается согласование начальником котельной.
            @else
                На согласовании у руководства и снабжения.
            @endif
        </p>
    @endif
    @if($variant === 'approved')
        @php
            $coOrderedCount = $application->items->filter(fn ($i) => $i->isOrderedFromCommercialOffer())->count();
        @endphp
        @if($coOrderedCount > 0)
            <p class="mt-1 text-xs text-black/80 dark:text-white/75">
                Заказано по КП: {{ $coOrderedCount }} {{ $coOrderedCount === 1 ? 'позиция' : ($coOrderedCount < 5 ? 'позиции' : 'позиций') }}.
            </p>
        @endif
    @endif
    @if($application->commercialOfferDisplayRejectionReason())
        <p class="mt-1 text-sm text-black dark:text-white">
            <span class="font-medium text-black dark:text-white">Причина:</span>
            {{ $application->commercialOfferDisplayRejectionReason() }}
        </p>
    @endif
</li>
