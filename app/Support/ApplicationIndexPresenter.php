<?php

namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ApplicationIndexPresenter
{
    /**
     * @param  LengthAwarePaginator<int, Application>  $applications
     */
    public static function prepare(LengthAwarePaginator $applications, ?User $viewer): void
    {
        $draftStatusId = ApplicationStatus::idForDraft();
        $completedStatusName = ApplicationStatus::NAME_COMPLETED;

        foreach ($applications->items() as $application) {
            $application->setAttribute(
                'index_list_status',
                self::resolveStatusKey($application, $viewer, $draftStatusId, $completedStatusName)
            );
            $application->setAttribute(
                'index_needs_custom_order',
                self::needsCustomEquipmentOrder($application)
            );
            $application->setAttribute(
                'index_needs_submit',
                self::needsSubmitToApproval($application, $viewer, $draftStatusId)
            );
        }
    }

    private static function resolveStatusKey(
        Application $application,
        ?User $viewer,
        int $draftStatusId,
        string $completedStatusName
    ): string {
        if ($application->isAdminArchived()) {
            return 'archived_admin';
        }

        if ((int) $application->application_status_id === $draftStatusId) {
            return 'draft';
        }

        if ($application->items->isEmpty()) {
            return 'empty';
        }

        if (self::isLifecycleCompleted($application, $completedStatusName)) {
            return 'completed';
        }

        if (self::isApprovedDeliveryFullyInTransit($application)) {
            return 'in_transit';
        }

        if (self::needsBoilerChiefReviewBeforeManagement($application)) {
            return 'boiler';
        }

        if ($application->isPendingManagementReview()) {
            return 'management';
        }

        $resolvedStatus = self::resolvedStatusName($application);

        if ($resolvedStatus === ApplicationStatus::NAME_APPROVED) {
            return 'approved';
        }

        if ($resolvedStatus === ApplicationStatus::NAME_PARTIAL) {
            return 'partial';
        }

        if ($application->isWorkflowDraftForDisplay()) {
            return 'draft';
        }

        if ($resolvedStatus === ApplicationStatus::NAME_REJECTED) {
            return 'rejected';
        }

        return 'pending';
    }

    private static function needsCustomEquipmentOrder(Application $application): bool
    {
        foreach ($application->items as $item) {
            if ($item->canMarkCustomSupplyOrdered()) {
                return true;
            }
        }

        return false;
    }

    private static function needsSubmitToApproval(Application $application, ?User $viewer, int $draftStatusId): bool
    {
        if ($viewer === null || $application->isArchived() || $application->isStatusRejected()) {
            return false;
        }

        if ($viewer->hasRoleId(4)) {
            return $application->needsSubmitToApprovalBy($viewer);
        }

        if ($viewer->hasRoleId(7)) {
            return self::boilerChiefCanSubmitToManagement($application, $draftStatusId);
        }

        return false;
    }

    private static function isForemanDraftBeforeBoilerChief(Application $application, int $draftStatusId): bool
    {
        if ((int) ($application->user?->role_id ?? 0) !== 4) {
            return false;
        }

        if (! Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)) {
            return false;
        }

        return (int) $application->application_status_id === $draftStatusId;
    }

    private static function boilerChiefCanSubmitToManagement(Application $application, int $draftStatusId): bool
    {
        return $application->boilerChiefCanSubmitToManagement();
    }

    private static function isCreatorDraftApplication(Application $application, int $draftStatusId): bool
    {
        return self::isForemanDraftBeforeBoilerChief($application, $draftStatusId)
            || ((int) ($application->user?->role_id ?? 0) === 7
                && (int) $application->application_status_id === $draftStatusId);
    }

    private static function isLifecycleCompleted(Application $application, string $completedStatusName): bool
    {
        if ($application->isAdminArchived()) {
            return false;
        }

        if ($application->isArchived()) {
            return true;
        }

        return $application->applicationStatus?->name === $completedStatusName;
    }

    private static function isApprovedDeliveryFullyInTransit(Application $application): bool
    {
        if ($application->isArchived() || $application->approved_by_user_id === null) {
            return false;
        }

        if (Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)
            && ! self::needsBoilerChiefReviewBeforeManagement($application)
            && $application->management_supply_items_saved_at === null) {
            return false;
        }

        $checked = $application->items->where('is_checked', true);
        if ($checked->isEmpty()) {
            return false;
        }

        foreach ($checked as $item) {
            if (! $item->isInShipmentTransitState()) {
                return false;
            }
        }

        return true;
    }

    private static function needsBoilerChiefReviewBeforeManagement(Application $application): bool
    {
        if ($application->isWorkflowDraftForDisplay()) {
            return false;
        }

        if ((int) ($application->user?->role_id ?? 0) === 7) {
            return false;
        }

        if (in_array((int) ($application->user?->role_id ?? 0), User::MANAGEMENT_EDITOR_ROLE_IDS, true)) {
            return false;
        }

        if (! Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)) {
            return false;
        }

        if ($application->items->isEmpty()) {
            return true;
        }

        foreach ($application->items as $item) {
            if (! (bool) $item->is_checked && trim((string) ($item->reason_not_selected ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    private static function awaitsManagementEquipmentApproval(Application $application): bool
    {
        if (! Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)) {
            return false;
        }

        if (self::needsBoilerChiefReviewBeforeManagement($application)) {
            return false;
        }

        if ($application->approved_by_user_id === null) {
            return false;
        }

        if ($application->items->isEmpty()) {
            return false;
        }

        if ($application->items->where('is_checked', true)->isNotEmpty()) {
            return false;
        }

        foreach ($application->items as $item) {
            if (trim((string) ($item->reason_not_selected ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function resolvedStatusName(Application $application): string
    {
        if ($application->items->isEmpty()) {
            return ApplicationStatus::NAME_PENDING;
        }

        if (self::needsBoilerChiefReviewBeforeManagement($application)) {
            return ApplicationStatus::NAME_PENDING;
        }

        $checkedCount = $application->items->where('is_checked', true)->count();
        $totalCount = $application->items->count();
        $rejectedWithReasonCount = $application->items->filter(
            fn (ApplicationItem $item) => ! (bool) $item->is_checked
                && trim((string) ($item->reason_not_selected ?? '')) !== ''
        )->count();
        $resolvedCount = $checkedCount + $rejectedWithReasonCount;

        if ($resolvedCount === $totalCount) {
            return Application::resolvedEquipmentLinesStatusWhenAllResolved($checkedCount, $totalCount);
        }

        if ($checkedCount === 0) {
            if (Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)
                && ! self::needsBoilerChiefReviewBeforeManagement($application)) {
                foreach ($application->items as $item) {
                    if (trim((string) ($item->reason_not_selected ?? '')) !== '') {
                        return ApplicationStatus::NAME_REJECTED;
                    }
                }

                return ApplicationStatus::NAME_PENDING;
            }

            return ApplicationStatus::NAME_REJECTED;
        }

        return ApplicationStatus::NAME_PARTIAL;
    }
}
