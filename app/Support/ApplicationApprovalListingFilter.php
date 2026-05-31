<?php

// фильтры статусов в списке заявок
namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class ApplicationApprovalListingFilter
{
    public const KEY_ALL = 'all';

    public const KEY_NEEDS_SUBMIT = 'needs_submit';

    public const KEY_DRAFT = 'draft';

    public const KEY_AT_BOILER_CHIEF = 'at_boiler_chief';

    public const KEY_AT_MANAGEMENT = 'at_management';

    public const KEY_PENDING = 'pending';

    public const KEY_PARTIAL = 'partial';

    public const KEY_APPROVED = 'approved';

    public const KEY_REJECTED = 'rejected';

    public const KEY_IN_TRANSIT = 'in_transit';

    public const KEY_NEEDS_DELIVERY_IN_TRANSIT = 'needs_delivery_in_transit';

    public const KEY_NEEDS_CUSTOM_ORDER = 'needs_custom_equipment_order';

    public const KEY_COMPLETED = 'completed';
    public static function options(): array
    {
        return self::optionsForUser(null);
    }
    public static function optionsForUser(?User $user): array
    {
        $options = [];
        foreach (self::optionGroupsForUser($user) as $groupOptions) {
            $options += $groupOptions;
        }

        return $options;
    }
    public static function optionGroups(): array
    {
        return self::optionGroupsForUser(null);
    }
    public static function optionGroupsForUser(?User $user): array
    {
        $groups = [
            'Общее' => [
                self::KEY_ALL => 'Все заявки',
            ],
            'Черновик' => [
                self::KEY_DRAFT => ApplicationStatus::NAME_DRAFT,
            ],
            'Этап согласования' => [
                self::KEY_AT_BOILER_CHIEF => 'У котельной',
                self::KEY_AT_MANAGEMENT => 'У руководства',
            ],
            'Решение по позициям' => [
                self::KEY_PENDING => ApplicationStatus::NAME_PENDING,
                self::KEY_PARTIAL => 'Частично согласована',
                self::KEY_APPROVED => ApplicationStatus::NAME_APPROVED,
                self::KEY_REJECTED => ApplicationStatus::NAME_REJECTED,
            ],
            'Исполнение' => [
                self::KEY_IN_TRANSIT => 'В пути',
            ],
        ];

        if (self::canViewNeedsDeliveryInTransitFilter($user)) {
            $groups['Исполнение'][self::KEY_NEEDS_DELIVERY_IN_TRANSIT] = 'Отправить в путь';
        }

        if (self::canViewCustomEquipmentOrderFilter($user)) {
            $groups['Исполнение'][self::KEY_NEEDS_CUSTOM_ORDER] = 'Своё оборудование к заказу';
        }

        return $groups;
    }

    public static function canViewCustomEquipmentOrderFilter(?User $user): bool
    {
        return $user !== null
            && $user->hasAnyRoleId(User::CUSTOM_EQUIPMENT_ORDER_FILTER_ROLE_IDS);
    }

    public static function canViewNeedsDeliveryInTransitFilter(?User $user): bool
    {
        return $user !== null
            && $user->hasApplicationSupplyWorkflowRole();
    }

    public static function normalize(mixed $value): string
    {
        return self::normalizeForUser($value, null);
    }

    public static function normalizeForUser(mixed $value, ?User $user): string
    {
        $value = trim((string) $value);
        $allowed = array_keys(self::optionsForUser($user));

        if ($value === self::KEY_NEEDS_SUBMIT) {
            return self::KEY_DRAFT;
        }

        if ($value === self::KEY_COMPLETED) {
            return self::KEY_ALL;
        }

        if ($value === self::KEY_NEEDS_CUSTOM_ORDER && ! self::canViewCustomEquipmentOrderFilter($user)) {
            return self::KEY_ALL;
        }

        if ($value === self::KEY_NEEDS_DELIVERY_IN_TRANSIT && ! self::canViewNeedsDeliveryInTransitFilter($user)) {
            return self::KEY_ALL;
        }

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return match ($value) {
            'has_approved' => self::KEY_PARTIAL,
            'has_not_approved' => self::KEY_PENDING,
            'fully_approved' => self::KEY_APPROVED,
            'on_approval' => self::KEY_PENDING,
            'needs_custom_equipment_order' => self::KEY_NEEDS_CUSTOM_ORDER,
            'needs_delivery_in_transit' => self::KEY_NEEDS_DELIVERY_IN_TRANSIT,
            default => self::KEY_ALL,
        };
    }
    public static function apply(Builder $query, string $filter, ?User $user = null): void
    {
        $filter = self::normalizeForUser($filter, $user);
        if ($filter === self::KEY_ALL) {
            return;
        }

        match ($filter) {
            self::KEY_DRAFT => self::applyDraft($query),
            self::KEY_AT_BOILER_CHIEF => self::applyAtBoilerChief($query),
            self::KEY_AT_MANAGEMENT => self::applyAtManagement($query),
            self::KEY_PENDING => self::applyPending($query),
            self::KEY_PARTIAL => self::applyPartial($query),
            self::KEY_APPROVED => self::applyApproved($query),
            self::KEY_REJECTED => self::applyRejected($query),
            self::KEY_IN_TRANSIT => self::applyInTransit($query),
            self::KEY_NEEDS_DELIVERY_IN_TRANSIT => self::applyNeedsDeliveryInTransit($query),
            self::KEY_NEEDS_CUSTOM_ORDER => self::applyNeedsCustomOrder($query),
            self::KEY_COMPLETED => self::applyCompleted($query),
            default => null,
        };
    }
    private static function applyDraft(Builder $query): void
    {
        self::applyStatus($query, ApplicationStatus::NAME_DRAFT);
        $query->notArchived();
    }
    private static function applyAtBoilerChief(Builder $query): void
    {
        $draftId = ApplicationStatus::idForDraft();
        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->whereExists(self::boilerChiefSubdivisionExistsSubquery())
            ->whereDoesntHave('user', function (Builder $userQuery): void {
                $userQuery->whereIn('role_id', User::MANAGEMENT_EDITOR_ROLE_IDS);
            })
            ->whereDoesntHave('user', function (Builder $userQuery): void {
                $userQuery->where('role_id', 7);
            })
            ->where(function (Builder $notReleasedToManagement): void {
                $notReleasedToManagement
                    ->whereDoesntHave('user', fn (Builder $userQuery) => $userQuery->where('role_id', 4))
                    ->orWhereNull('approved_by_user_id');
            })
            ->where(function (Builder $outer): void {
                $outer->whereDoesntHave('items')
                    ->orWhereHas('items', function (Builder $itemQuery): void {
                        $itemQuery
                            ->where('is_checked', false)
                            ->whereRaw("TRIM(COALESCE(reason_not_selected, '')) = ''");
                    });
            });
    }
    private static function applyAtManagement(Builder $query): void
    {
        $draftId = ApplicationStatus::idForDraft();
        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->whereExists(self::boilerChiefSubdivisionExistsSubquery())
            ->whereHas('items')
            ->where(function (Builder $released): void {
                $released
                    ->whereDoesntHave('user', fn (Builder $userQuery) => $userQuery->where('role_id', 4))
                    ->orWhereNotNull('approved_by_user_id');
            })
            ->whereNot(function (Builder $savedByManagement): void {
                self::applyManagementSavedApprovalScope($savedByManagement);
            });
    }
    private static function applyPending(Builder $query): void
    {
        $draftId = ApplicationStatus::idForDraft();
        $pendingId = ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);

        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->whereHas('items');

        self::excludeAtBoilerChiefStage($query);

        $query->where(function (Builder $pending) use ($pendingId): void {
            $atManagement = Application::query()->select('applications.id');
            self::applyAtManagement($atManagement);

            $partialAtManagement = Application::query()->select('applications.id');
            self::applyAtManagement($partialAtManagement);
            self::applyPartialPositionApprovalScope($partialAtManagement);

            $pending
                ->where(function (Builder $managementPending) use ($atManagement, $partialAtManagement): void {
                    $managementPending
                        ->whereIn('applications.id', $atManagement)
                        ->whereNotIn('applications.id', $partialAtManagement);
                })
                ->orWhere('application_status_id', $pendingId);
        });
    }
    private static function applyPartial(Builder $query): void
    {
        $draftId = ApplicationStatus::idForDraft();
        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->whereHas('items');

        self::excludeAtBoilerChiefStage($query);
        self::applyPartialPositionApprovalScope($query);
    }
    public static function hasMixedItemApproval(Application $application): bool
    {
        $application->loadMissing('items');

        return $application->items->contains(fn (ApplicationItem $item) => (bool) $item->is_checked)
            && $application->items->contains(fn (ApplicationItem $item) => ! (bool) $item->is_checked);
    }
    private static function applyPartialPositionApprovalScope(Builder $query): void
    {
        $query
            ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('is_checked', true))
            ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('is_checked', false));
    }
    private static function applyRejected(Builder $query): void
    {
        $draftId = ApplicationStatus::idForDraft();
        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->whereHas('items');

        self::excludeWorkflowStagesFromPositionApproval($query);
        self::applyStatus($query, ApplicationStatus::NAME_REJECTED);
    }
    private static function excludeAtBoilerChiefStage(Builder $query): void
    {
        $atBoilerChief = Application::query()->select('applications.id');
        self::applyAtBoilerChief($atBoilerChief);
        $query->whereNotIn('applications.id', $atBoilerChief);
    }
    private static function excludeWorkflowStagesFromPositionApproval(Builder $query): void
    {
        self::excludeAtBoilerChiefStage($query);

        $atManagement = Application::query()->select('applications.id');
        self::applyAtManagement($atManagement);
        $query->whereNotIn('applications.id', $atManagement);
    }
    private static function applyStatus(Builder $query, string $statusName): void
    {
        $statusId = ApplicationStatus::idFor($statusName);
        $query->where('application_status_id', $statusId);
    }
    private static function applyApproved(Builder $query): void
    {
        $draftId = ApplicationStatus::idForDraft();
        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->whereHas('items');

        self::excludeWorkflowStagesFromPositionApproval($query);
        self::applyStatus($query, ApplicationStatus::NAME_APPROVED);
        $query->whereNotNull('approved_by_user_id');
        self::excludeInTransit($query);
    }
    private static function applyInTransit(Builder $query): void
    {
        $query->notArchived()
            ->whereNotNull('approved_by_user_id')
            ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('is_checked', true));

        self::applyPassedBoilerChiefSupplyGate($query);

        $query->whereDoesntHave('items', function (Builder $itemQuery): void {
            $itemQuery->where('is_checked', true)->where(function (Builder $w): void {
                $w->where(function (Builder $custom): void {
                    $custom->whereNull('equipment_id')
                        ->where(function (Builder $status): void {
                            $status->where('custom_equipment_supply_status_id', '!=', ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT_ID)
                                ->orWhereNull('custom_equipment_supply_status_id');
                        });
                })->orWhere(function (Builder $catalog): void {
                    $catalog->whereNotNull('equipment_id')
                        ->where(function (Builder $delivery): void {
                            $delivery->where('delivery_status_id', '!=', ApplicationItem::DELIVERY_IN_TRANSIT_ID)
                                ->orWhereNull('delivery_status_id');
                        });
                });
            });
        });
    }
    private static function applyNeedsCustomOrder(Builder $query): void
    {
        $query->notArchived();
        $query->whereSupplyApprovedForCustomEquipmentWorkflow();
        $query->whereHas('items', function (Builder $itemQuery): void {
            $itemQuery
                ->whereNull('equipment_id')
                ->where('is_checked', true)
                ->where(function (Builder $w): void {
                    $w->where('custom_equipment_supply_status_id', ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID)
                        ->orWhereNull('custom_equipment_supply_status_id');
                });
        });
    }

    private static function applyNeedsDeliveryInTransit(Builder $query): void
    {
        $query->notArchived();
        self::applyManagementSavedApprovalScope($query);
        $query->whereHas('items', function (Builder $itemQuery): void {
            $itemQuery
                ->where('is_checked', true)
                ->whereNotNull('equipment_id')
                ->whereNull('delivery_status_id');
        });
    }
    private static function applyCompleted(Builder $query): void
    {
        $completedId = ApplicationStatus::idFor(ApplicationStatus::NAME_COMPLETED);
        $query->where(function (Builder $w) use ($completedId): void {
            $w->archived()
                ->orWhere('application_status_id', $completedId);
        });
    }
    private static function excludeInTransit(Builder $query): void
    {
        $clone = Application::query()->select('applications.id');
        self::applyInTransit($clone);
        $query->whereNotIn('applications.id', $clone);
    }
    private static function applyPassedBoilerChiefSupplyGate(Builder $query): void
    {
        if (! Schema::hasTable('boiler_chief_subdivision_user')) {
            return;
        }

        $query->where(function (Builder $w): void {
            $w->whereNotExists(self::boilerChiefSubdivisionExistsSubquery());
            if (Schema::hasColumn('applications', 'management_supply_items_saved_at')) {
                $w->orWhereNotNull('management_supply_items_saved_at');
            }
            $w->orWhereHas('items', function (Builder $itemQuery): void {
                $itemQuery
                    ->where('is_checked', false)
                    ->whereRaw("TRIM(COALESCE(reason_not_selected, '')) = ''");
            });
        });
    }

    private static function boilerChiefSubdivisionExistsSubquery(): \Closure
    {
        return function ($sub) {
            $sub->selectRaw('1')
                ->from('boiler_chief_subdivision_user')
                ->whereColumn('boiler_chief_subdivision_user.subdivision_id', 'applications.subdivision_id');
        };
    }
    private static function managementDecisionStatusIds(): array
    {
        return [
            ApplicationStatus::idFor(ApplicationStatus::NAME_PARTIAL),
            ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
            ApplicationStatus::idFor(ApplicationStatus::NAME_REJECTED),
        ];
    }
    private static function applyManagementSavedApprovalScope(Builder $query): void
    {
        $query
            ->whereNotNull('approved_by_user_id')
            ->whereHas('approvedBy', fn (Builder $userQuery) => $userQuery->whereIn('role_id', User::MANAGEMENT_EDITOR_ROLE_IDS))
            ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('is_checked', true))
            ->where(function (Builder $saved): void {
                if (Schema::hasColumn('applications', 'management_supply_items_saved_at')) {
                    $saved
                        ->whereNotNull('management_supply_items_saved_at')
                        ->orWhereIn('application_status_id', self::managementDecisionStatusIds());
                } else {
                    $saved->whereIn('application_status_id', self::managementDecisionStatusIds());
                }
            });
    }
}
