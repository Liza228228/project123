@php
    $statusKey = $application->index_list_status ?? null;
    if ($statusKey === null) {
        $statusKey = match (true) {
            $application->isAdminArchived() => 'archived_admin',
            $application->isCreatorDraftApplication() => 'draft',
            $application->items->isEmpty() => 'empty',
            $application->isLifecycleCompleted() => 'completed',
            $application->isApprovedDeliveryFullyInTransit() => 'in_transit',
            $application->isStatusApproved() => 'approved',
            $application->isStatusPartial() => 'partial',
            $application->needsBoilerChiefReviewBeforeManagement() => 'boiler',
            $application->awaitsManagementEquipmentApproval() => 'management',
            $application->isStatusRejected() => 'rejected',
            default => 'pending',
        };
    }

    $badgeClass = 'applications-index-status-badge';
    $badgeClassMobile = 'inline-flex max-w-full items-center rounded-full px-2.5 py-1 text-sm font-medium leading-snug ring-1 ring-inset';
    $badge = ($compact ?? false) ? $badgeClassMobile : $badgeClass;
@endphp
@if($statusKey === 'empty')
    <span class="text-black dark:text-white opacity-50">—</span>
@elseif($statusKey === 'archived_admin')
    <span
        class="{{ $badge }} applications-index-status-badge--rejected"
        title="Заявка перенесена в архив. Изменения недоступны."
    >
        В архиве
    </span>
@elseif($statusKey === 'needs_submit')
    <span
        class="{{ $badge }} applications-index-status-badge--draft"
        title="Заявка ещё не отправлена на согласование"
    >
        Черновик
    </span>
@elseif($statusKey === 'completed')
    <span class="{{ $badge }} applications-index-status-badge--completed">
        Выполнена
    </span>
@elseif($statusKey === 'in_transit')
    <span class="{{ $badge }} applications-index-status-badge--transit">
        В пути
    </span>
@elseif($statusKey === 'approved')
    <span class="{{ $badge }} applications-index-status-badge--approved">
        Согласована
    </span>
@elseif($statusKey === 'partial')
    <span class="{{ $badge }} applications-index-status-badge--partial">
        Частично
    </span>
@elseif($statusKey === 'draft')
    <span class="{{ $badge }} applications-index-status-badge--draft">
        Черновик
    </span>
@elseif($statusKey === 'boiler')
    <span class="{{ $badge }} applications-index-status-badge--boiler">
        У котельной
    </span>
@elseif($statusKey === 'management')
    <span class="{{ $badge }} applications-index-status-badge--management">
        У руководства
    </span>
@elseif($statusKey === 'rejected')
    <span class="{{ $badge }} applications-index-status-badge--rejected">
        Не согласована
    </span>
@else
    <span class="{{ $badge }} applications-index-status-badge--pending">
        На согласовании
    </span>
@endif
