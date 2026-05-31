@php
    // шаблон страницы
    $needsSubmitToApproval = Auth::check() && $application->needsSubmitToApprovalBy(Auth::user());
    $submitRoute = null;
    if ($needsSubmitToApproval && Auth::user()->hasRoleId(4)) {
        $submitRoute = route('applications.submit-to-boiler-chief', $application);
    } elseif ($needsSubmitToApproval && Auth::user()->hasRoleId(7)) {
        $submitRoute = route('applications.submit-for-management', $application);
    }
    $isStacked = (bool) ($stacked ?? false);
    $btnStackClass = $isStacked
        ? 'applications-index-actions flex w-full flex-col gap-2'
        : 'applications-index-actions applications-index-actions--desktop';
    $btnClass = 'ui-btn w-full';
@endphp
<div class="{{ $btnStackClass }}">
    <a href="{{ route('applications.show', $application) }}" class="{{ $btnClass }} ui-btn--primary">
        Просмотр
    </a>
    @if($submitRoute)
        <form
            method="POST"
            action="{{ $submitRoute }}"
            class="w-full"
            data-app-confirm="После отправки редактирование заявки будет недоступно."
            data-app-confirm-title="Отправить заявку на согласование?"
            data-app-confirm-label="Да, отправить"
        >
            @csrf
            <input type="hidden" name="_return_url" value="{{ request()->fullUrl() }}">
            <button type="submit" class="{{ $btnClass }} ui-btn--secondary font-semibold">
                {{ ($tableCompact ?? false) ? 'На согласование' : 'Отправить на согласование' }}
            </button>
        </form>
    @endif
    @isset($extraActions)
        {{ $extraActions }}
    @endisset
</div>
