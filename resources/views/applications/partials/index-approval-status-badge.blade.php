@php
    // шаблон страницы
    $draftKey = $application->index_draft_key ?? null;
    $stageKey = $application->index_stage_key ?? null;
    $approvalKey = $application->index_approval_key ?? null;
    $fulfillmentKey = $application->index_fulfillment_key ?? null;

    $badgeClass = 'applications-index-status-badge';
    $badgeClassMobile = 'inline-flex max-w-full items-center rounded-full px-2.5 py-1 text-sm font-medium leading-snug ring-1 ring-inset';
    $badge = ($compact ?? false) ? $badgeClassMobile : $badgeClass;
    $badgeCompact = ($compact ?? false) ? '' : ' applications-index-status-badge--compact';

    $hasLayeredStatus = $draftKey !== null
        || $stageKey !== null
        || $approvalKey !== null
        || $fulfillmentKey !== null;

    if (! $hasLayeredStatus) {
        $statusKey = $application->index_list_status ?? null;
        if ($statusKey === null) {
            $statusKey = match (true) {
                $application->isAdminArchived() => 'archived_admin',
                $application->isWorkflowDraftForDisplay() => 'draft',
                $application->items->isEmpty() => 'empty',
                $application->isLifecycleCompleted() => 'completed',
                $application->isApprovedDeliveryFullyInTransit() => 'in_transit',
                $application->needsBoilerChiefReviewBeforeManagement() => 'boiler',
                $application->isPendingManagementReview() => 'management',
                $application->isStatusApproved() => 'approved',
                $application->isStatusPartial() => 'partial',
                $application->isStatusRejected() => 'rejected',
                default => 'pending',
            };
        }
    }
@endphp

@if(($statusKey ?? null) === 'empty')
    <span class="text-black dark:text-white opacity-50">—</span>
@elseif(($statusKey ?? null) === 'archived_admin')
    <span
        class="{{ $badge }} applications-index-status-badge--rejected"
        title="Заявка перенесена в архив. Изменения недоступны."
    >
        В архиве
    </span>
@elseif($hasLayeredStatus)
    <div class="flex flex-col items-start gap-1 min-w-0 max-w-full">
        @if($draftKey === 'draft')
            <span
                class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--draft"
                title="Заявка ещё не отправлена на согласование"
            >
                Черновик
            </span>
        @endif

        @if($stageKey === 'boiler')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--boiler">
                У котельной
            </span>
        @elseif($stageKey === 'management')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--management">
                У руководства
            </span>
        @endif

        @if($approvalKey === 'approved')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--approved">
                Согласована
            </span>
        @elseif($approvalKey === 'partial')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--partial">
                Частично
            </span>
        @elseif($approvalKey === 'rejected')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--rejected">
                Не согласована
            </span>
        @elseif($approvalKey === 'pending')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--pending">
                На согласовании
            </span>
        @endif

        @if($fulfillmentKey === 'completed')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--completed">
                Выполнена
            </span>
        @elseif($fulfillmentKey === 'in_transit')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--transit">
                В пути
            </span>
        @elseif($fulfillmentKey === 'needs_delivery_in_transit')
            <span
                class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--needs-transit"
                title="Согласованное каталожное оборудование нужно отметить как «В пути»"
            >
                Отправить в путь
            </span>
        @elseif($fulfillmentKey === 'needs_custom_order')
            <span class="{{ $badge }}{{ $badgeCompact }} applications-index-status-badge--partial">
                Своё оборудование к заказу
            </span>
        @endif
    </div>
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
