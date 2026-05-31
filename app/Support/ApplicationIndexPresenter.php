<?php

// готовим бейджи для списка заявок
namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

final class ApplicationIndexPresenter
{
    private static ?bool $transportOptionsHavePlateColumn = null;

    public static function prepare(LengthAwarePaginator $applications, ?User $viewer): void
    {
        $items = $applications->items();
        if ($items === []) {
            return;
        }

        self::warmBoilerChiefCache($items);

        $draftStatusId = ApplicationStatus::idForDraft();
        $completedStatusName = ApplicationStatus::NAME_COMPLETED;
        $viewerCanCustomOrderFilter = ApplicationApprovalListingFilter::canViewCustomEquipmentOrderFilter($viewer);
        $viewerCanSupplyWorkflow = $viewer !== null && $viewer->hasApplicationSupplyWorkflowRole();

        foreach ($items as $application) {
            self::prepareApplication(
                $application,
                $draftStatusId,
                $completedStatusName,
                $viewer,
                $viewerCanCustomOrderFilter,
                $viewerCanSupplyWorkflow,
            );
        }
    }

    /**
     * @param  array<int, Application>  $applications
     */
    private static function warmBoilerChiefCache(array $applications): void
    {
        foreach ($applications as $application) {
            Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id);
        }
    }

    private static function prepareApplication(
        Application $application,
        int $draftStatusId,
        string $completedStatusName,
        ?User $viewer,
        bool $viewerCanCustomOrderFilter,
        bool $viewerCanSupplyWorkflow,
    ): void {
        $flags = self::computeFlags($application, $completedStatusName);

        $application->setAttribute(
            'index_draft_key',
            self::resolveDraftKey($application, $draftStatusId, $flags)
        );
        $application->setAttribute(
            'index_stage_key',
            self::resolveStageKey($application, $draftStatusId, $flags)
        );
        $application->setAttribute(
            'index_approval_key',
            self::resolveApprovalKey($application, $draftStatusId, $flags)
        );
        $application->setAttribute(
            'index_fulfillment_key',
            self::resolveFulfillmentKey($flags, $viewerCanCustomOrderFilter, $viewerCanSupplyWorkflow)
        );
        $application->setAttribute(
            'index_list_status',
            self::resolveStatusKey($application, $draftStatusId, $flags)
        );
        $application->setAttribute(
            'index_needs_custom_order',
            $viewerCanCustomOrderFilter && $flags['needsCustomEquipmentOrder']
        );
        $application->setAttribute(
            'index_needs_submit',
            self::needsSubmitToApproval($application, $viewer, $draftStatusId)
        );
        $application->setAttribute(
            'index_needs_delivery_in_transit',
            $viewerCanSupplyWorkflow && $flags['needsCatalogDeliveryInTransit']
        );
        $application->setAttribute(
            'index_transport_line',
            self::transportLine($application)
        );
        $application->setAttribute(
            'index_expected_arrival_line',
            self::expectedArrivalLine($application)
        );
        $application->setAttribute(
            'index_row_table_class',
            self::resolveRowTableClass($application)
        );
        $application->setAttribute(
            'index_row_card_class',
            self::resolveRowCardClass($application)
        );
    }

    /**
     * @return array{
     *     isAdminArchived: bool,
     *     isArchived: bool,
     *     itemsEmpty: bool,
     *     hasCheckedItem: bool,
     *     hasUncheckedItem: bool,
     *     checkedCount: int,
     *     totalCount: int,
     *     rejectedWithReasonCount: int,
     *     needsBoilerChiefReview: bool,
     *     isWorkflowDraft: bool,
     *     isPendingManagement: bool,
     *     managementHasSavedApproval: bool,
     *     isSupplyApprovedForCustom: bool,
     *     isLifecycleCompleted: bool,
     *     isApprovedFullyInTransit: bool,
     *     needsCustomEquipmentOrder: bool,
     *     needsCatalogDeliveryInTransit: bool,
     *     resolvedStatusName: string,
     *     hasMixedItemApproval: bool,
     * }
     */
    private static function computeFlags(Application $application, string $completedStatusName): array
    {
        $items = $application->items;
        $totalCount = $items->count();
        $checkedCount = 0;
        $rejectedWithReasonCount = 0;
        $hasCheckedItem = false;
        $hasUncheckedItem = false;
        $needsCustomEquipmentOrder = false;
        $needsCatalogDeliveryInTransit = false;

        foreach ($items as $item) {
            $isChecked = (bool) $item->is_checked;
            if ($isChecked) {
                $checkedCount++;
                $hasCheckedItem = true;
            } else {
                $hasUncheckedItem = true;
            }

            $reason = trim((string) ($item->reason_not_selected ?? ''));
            if (! $isChecked && $reason !== '') {
                $rejectedWithReasonCount++;
            }

            if (! $needsCustomEquipmentOrder && $item->canMarkCustomSupplyOrdered()) {
                $needsCustomEquipmentOrder = true;
            }
        }

        $needsBoilerChiefReview = $application->needsBoilerChiefReviewBeforeManagement();
        $isWorkflowDraft = $application->isWorkflowDraftForDisplay();
        $managementHasSavedApproval = $application->managementHasSavedApproval();
        $isSupplyApprovedForCustom = $application->isSupplyApprovedForCustomEquipmentWorkflow();

        $hasBoilerChief = Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id);
        $isPendingManagement = ! $isWorkflowDraft
            && ! $needsBoilerChiefReview
            && $hasBoilerChief
            && $application->boilerChiefReleasedToManagement()
            && ! $managementHasSavedApproval;

        if (
            $application->archived_at === null
            && ! $application->isAdminArchived()
            && $managementHasSavedApproval
        ) {
            foreach ($items as $item) {
                if ($item->is_checked && $item->equipment_id !== null && $item->canMarkDeliveryInTransit()) {
                    $needsCatalogDeliveryInTransit = true;
                    break;
                }
            }
        }

        $isApprovedFullyInTransit = self::computeIsApprovedFullyInTransit(
            $application,
            $items,
            $hasBoilerChief,
            $needsBoilerChiefReview,
            $isSupplyApprovedForCustom,
            $checkedCount
        );

        $resolvedStatusName = self::computeResolvedStatusName(
            $application,
            $items,
            $totalCount,
            $checkedCount,
            $rejectedWithReasonCount,
            $needsBoilerChiefReview,
            $hasBoilerChief
        );

        return [
            'isAdminArchived' => $application->isAdminArchived(),
            'isArchived' => $application->isArchived(),
            'itemsEmpty' => $totalCount === 0,
            'hasCheckedItem' => $hasCheckedItem,
            'hasUncheckedItem' => $hasUncheckedItem,
            'checkedCount' => $checkedCount,
            'totalCount' => $totalCount,
            'rejectedWithReasonCount' => $rejectedWithReasonCount,
            'needsBoilerChiefReview' => $needsBoilerChiefReview,
            'isWorkflowDraft' => $isWorkflowDraft,
            'isPendingManagement' => $isPendingManagement,
            'managementHasSavedApproval' => $managementHasSavedApproval,
            'isSupplyApprovedForCustom' => $isSupplyApprovedForCustom,
            'isLifecycleCompleted' => self::computeIsLifecycleCompleted($application, $completedStatusName),
            'isApprovedFullyInTransit' => $isApprovedFullyInTransit,
            'needsCustomEquipmentOrder' => $needsCustomEquipmentOrder,
            'needsCatalogDeliveryInTransit' => $needsCatalogDeliveryInTransit,
            'resolvedStatusName' => $resolvedStatusName,
            'hasMixedItemApproval' => $hasCheckedItem && $hasUncheckedItem,
        ];
    }

    private static function computeIsLifecycleCompleted(Application $application, string $completedStatusName): bool
    {
        if ($application->isAdminArchived()) {
            return false;
        }

        if ($application->isArchived()) {
            return true;
        }

        return $application->applicationStatus?->name === $completedStatusName;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ApplicationItem>  $checkedItems
     */
    private static function computeIsApprovedFullyInTransit(
        Application $application,
        $items,
        bool $hasBoilerChief,
        bool $needsBoilerChiefReview,
        bool $isSupplyApprovedForCustom,
        int $checkedCount,
    ): bool {
        if ($application->isArchived() || $application->approved_by_user_id === null || $checkedCount === 0) {
            return false;
        }

        if ($hasBoilerChief && ! $needsBoilerChiefReview && ! $isSupplyApprovedForCustom) {
            return false;
        }

        foreach ($items as $item) {
            if (! (bool) $item->is_checked) {
                continue;
            }

            if (! $item->isInShipmentTransitState()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ApplicationItem>  $items
     */
    private static function computeResolvedStatusName(
        Application $application,
        $items,
        int $totalCount,
        int $checkedCount,
        int $rejectedWithReasonCount,
        bool $needsBoilerChiefReview,
        bool $hasBoilerChief,
    ): string {
        if ($totalCount === 0) {
            return ApplicationStatus::NAME_PENDING;
        }

        if ($needsBoilerChiefReview) {
            return ApplicationStatus::NAME_PENDING;
        }

        $resolvedCount = $checkedCount + $rejectedWithReasonCount;

        if ($resolvedCount === $totalCount) {
            return Application::resolvedEquipmentLinesStatusWhenAllResolved($checkedCount, $totalCount);
        }

        if ($checkedCount === 0) {
            if ($hasBoilerChief && ! $needsBoilerChiefReview) {
                foreach ($items as $item) {
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

    private static function transportLine(Application $application): ?string
    {
        $opt = $application->transportOption;
        if (! $opt) {
            return null;
        }

        $name = trim((string) ($opt->name ?? ''));
        $plate = self::transportOptionsHavePlateColumn()
            ? trim((string) ($opt->plate ?? ''))
            : '';

        if ($name === '' && $plate === '') {
            return null;
        }

        if ($name !== '' && $plate !== '') {
            return $name.' — '.$plate;
        }

        return $name !== '' ? $name : $plate;
    }

    private static function expectedArrivalLine(Application $application): ?string
    {
        $times = $application->items
            ->filter(fn (ApplicationItem $item) => (int) ($item->delivery_status_id ?? 0) === ApplicationItem::DELIVERY_IN_TRANSIT_ID)
            ->map(fn (ApplicationItem $item) => $item->expected_arrival_at)
            ->filter()
            ->sort()
            ->values();

        if ($times->isEmpty()) {
            return null;
        }

        if ($times->count() === 1) {
            return $times->first()->format('d.m.Y');
        }

        return $times->first()->format('d.m.Y').' — '.$times->last()->format('d.m.Y');
    }

    private static function transportOptionsHavePlateColumn(): bool
    {
        if (self::$transportOptionsHavePlateColumn === null) {
            self::$transportOptionsHavePlateColumn = Schema::hasColumn('transport_options', 'plate');
        }

        return self::$transportOptionsHavePlateColumn;
    }

    private static function resolveRowTableClass(Application $application): string
    {
        if ((bool) $application->index_needs_submit) {
            return 'bg-orange-50/45 dark:bg-orange-950/25 border-l-4 border-l-orange-400 dark:border-l-orange-600';
        }

        if ((bool) $application->index_needs_delivery_in_transit) {
            return 'applications-index-row--needs-transit';
        }

        if ((bool) $application->index_needs_custom_order) {
            return 'bg-amber-50/50 dark:bg-amber-950/20 border-l-4 border-l-amber-400 dark:border-l-amber-600';
        }

        return '';
    }

    private static function resolveRowCardClass(Application $application): string
    {
        if ((bool) $application->index_needs_submit) {
            return 'border-orange-300/90 bg-orange-50/40 dark:border-orange-800/60 dark:bg-orange-950/25';
        }

        if ((bool) $application->index_needs_delivery_in_transit) {
            return 'applications-index-card--needs-transit';
        }

        if ((bool) $application->index_needs_custom_order) {
            return 'border-amber-300/90 bg-amber-50/40 dark:border-amber-800/60 dark:bg-amber-950/25';
        }

        return 'border-orange-100/90 dark:border-orange-900/40 bg-orange-50/20 dark:bg-stone-900/20';
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private static function resolveDraftKey(Application $application, int $draftStatusId, array $flags): ?string
    {
        if ($flags['isAdminArchived'] || $flags['itemsEmpty']) {
            return null;
        }

        if ((int) $application->application_status_id === $draftStatusId || $flags['isWorkflowDraft']) {
            return 'draft';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private static function resolveStageKey(Application $application, int $draftStatusId, array $flags): ?string
    {
        if ($flags['isAdminArchived'] || $flags['itemsEmpty'] || $flags['isLifecycleCompleted']) {
            return null;
        }

        if ((int) $application->application_status_id === $draftStatusId || $flags['isWorkflowDraft']) {
            return null;
        }

        if ($flags['needsBoilerChiefReview']) {
            return 'boiler';
        }

        if ($flags['isPendingManagement']) {
            return 'management';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private static function resolveApprovalKey(Application $application, int $draftStatusId, array $flags): ?string
    {
        if ($flags['isAdminArchived'] || $flags['itemsEmpty']) {
            return null;
        }

        if ((int) $application->application_status_id === $draftStatusId || $flags['isWorkflowDraft']) {
            return null;
        }

        if ($flags['needsBoilerChiefReview']) {
            return null;
        }

        if ($flags['isPendingManagement']) {
            return $flags['hasMixedItemApproval'] ? 'partial' : 'pending';
        }

        return match ($flags['resolvedStatusName']) {
            ApplicationStatus::NAME_APPROVED => 'approved',
            ApplicationStatus::NAME_PARTIAL => 'partial',
            ApplicationStatus::NAME_REJECTED => 'rejected',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private static function resolveFulfillmentKey(
        array $flags,
        bool $viewerCanCustomOrderFilter,
        bool $viewerCanSupplyWorkflow,
    ): ?string {
        if ($flags['isAdminArchived']) {
            return null;
        }

        if ($flags['isLifecycleCompleted']) {
            return 'completed';
        }

        if ($flags['isApprovedFullyInTransit']) {
            return 'in_transit';
        }

        if ($viewerCanSupplyWorkflow && $flags['needsCatalogDeliveryInTransit']) {
            return 'needs_delivery_in_transit';
        }

        if ($flags['needsBoilerChiefReview'] || $flags['isPendingManagement']) {
            return null;
        }

        if (! $viewerCanCustomOrderFilter) {
            return null;
        }

        if (! $flags['isSupplyApprovedForCustom']) {
            return null;
        }

        if ($flags['needsCustomEquipmentOrder']) {
            return 'needs_custom_order';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private static function resolveStatusKey(Application $application, int $draftStatusId, array $flags): string
    {
        if ($flags['isAdminArchived']) {
            return 'archived_admin';
        }

        if ((int) $application->application_status_id === $draftStatusId) {
            return 'draft';
        }

        if ($flags['itemsEmpty']) {
            return 'empty';
        }

        if ($flags['isLifecycleCompleted']) {
            return 'completed';
        }

        if ($flags['isApprovedFullyInTransit']) {
            return 'in_transit';
        }

        if ($flags['needsBoilerChiefReview']) {
            return 'boiler';
        }

        if ($flags['isPendingManagement']) {
            return 'management';
        }

        $resolvedStatus = $flags['resolvedStatusName'];

        if ($resolvedStatus === ApplicationStatus::NAME_APPROVED) {
            return 'approved';
        }

        if ($resolvedStatus === ApplicationStatus::NAME_PARTIAL) {
            return 'partial';
        }

        if ($flags['isWorkflowDraft']) {
            return 'draft';
        }

        if ($resolvedStatus === ApplicationStatus::NAME_REJECTED) {
            return 'rejected';
        }

        return 'pending';
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
            return $application->boilerChiefCanSubmitToManagement();
        }

        return false;
    }
}
