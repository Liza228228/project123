@php
    $needsSubmitToApproval = Auth::check() && $application->needsSubmitToApprovalBy(Auth::user());
    $badgeClass = 'applications-index-status-badge';
    $badgeClassMobile = 'inline-flex items-center rounded-full px-2.5 py-1 text-sm font-medium leading-snug';
@endphp
@if($application->items->isEmpty())
    <span class="text-black dark:text-white opacity-50">—</span>
@elseif($needsSubmitToApproval)
    <span
        class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} applications-index-status-badge--submit"
        title="Отправьте заявку на следующий этап согласования"
    >
        Нужна отправка
    </span>
@elseif($application->isLifecycleCompleted())
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} border border-emerald-300/90 bg-emerald-50/90 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
        Выполнена
    </span>
@elseif($application->isApprovedDeliveryFullyInTransit())
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} border border-orange-300/90 bg-orange-50/90 text-orange-950 dark:border-orange-800 dark:bg-orange-950/40 dark:text-orange-100">
        В пути
    </span>
@elseif($application->isStatusApproved())
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} bg-stone-200 text-black dark:bg-stone-900/60 dark:text-white">
        Согласована
    </span>
@elseif($application->isStatusPartial())
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} bg-stone-200/90 text-black dark:bg-stone-900/50 dark:text-white">
        Частично
    </span>
@elseif($application->isCreatorDraftApplication())
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} bg-stone-200/90 text-stone-800 dark:bg-stone-800/60 dark:text-stone-200">
        Черновик
    </span>
@elseif($application->needsBoilerChiefReviewBeforeManagement())
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
        У котельной
    </span>
@elseif($application->awaitsManagementEquipmentApproval())
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} bg-sky-100 text-sky-950 dark:bg-sky-950/40 dark:text-sky-100">
        У руководства
    </span>
@elseif($application->isStatusRejected())
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} bg-stone-300/90 text-black dark:bg-stone-900/70 dark:text-white">
        Не согласована
    </span>
@else
    <span class="{{ ($compact ?? false) ? $badgeClassMobile : $badgeClass }} bg-stone-100 text-black dark:bg-stone-900/50 dark:text-white">
        На согласовании
    </span>
@endif
