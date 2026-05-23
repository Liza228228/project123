<?php

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

    public const KEY_NEEDS_CUSTOM_ORDER = 'needs_custom_equipment_order';

    public const KEY_COMPLETED = 'completed';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::KEY_ALL => 'Все заявки',
            self::KEY_DRAFT => ApplicationStatus::NAME_DRAFT,
            self::KEY_AT_BOILER_CHIEF => 'У котельной',
            self::KEY_AT_MANAGEMENT => 'У руководства',
            self::KEY_PENDING => ApplicationStatus::NAME_PENDING,
            self::KEY_PARTIAL => 'Частично',
            self::KEY_APPROVED => ApplicationStatus::NAME_APPROVED,
            self::KEY_REJECTED => ApplicationStatus::NAME_REJECTED,
            self::KEY_IN_TRANSIT => 'В пути',
            self::KEY_NEEDS_CUSTOM_ORDER => 'Нужно заказать своё оборудование',
            self::KEY_COMPLETED => ApplicationStatus::NAME_COMPLETED,
        ];
    }

    public static function normalize(mixed $value): string
    {
        $value = trim((string) $value);
        $allowed = array_keys(self::options());

        if ($value === self::KEY_NEEDS_SUBMIT) {
            return self::KEY_DRAFT;
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
            default => self::KEY_ALL,
        };
    }

    /**
     * @param  Builder<Application>  $query
     */
    public static function apply(Builder $query, string $filter): void
    {
        $filter = self::normalize($filter);
        if ($filter === self::KEY_ALL) {
            return;
        }

        match ($filter) {
            self::KEY_DRAFT => self::applyDraft($query),
            self::KEY_AT_BOILER_CHIEF => self::applyAtBoilerChief($query),
            self::KEY_AT_MANAGEMENT => self::applyAtManagement($query),
            self::KEY_PENDING => self::applyPending($query),
            self::KEY_PARTIAL => self::applyStatus($query, ApplicationStatus::NAME_PARTIAL),
            self::KEY_APPROVED => self::applyApproved($query),
            self::KEY_REJECTED => self::applyStatus($query, ApplicationStatus::NAME_REJECTED),
            self::KEY_IN_TRANSIT => self::applyInTransit($query),
            self::KEY_NEEDS_CUSTOM_ORDER => self::applyNeedsCustomOrder($query),
            self::KEY_COMPLETED => self::applyCompleted($query),
            default => null,
        };
    }

    /**
     * @param  Builder<Application>  $query
     */
    private static function applyDraft(Builder $query): void
    {
        self::applyStatus($query, ApplicationStatus::NAME_DRAFT);
        $query->notArchived();
    }

    /**
     * @param  Builder<Application>  $query
     */
    private static function applyAtBoilerChief(Builder $query): void
    {
        $draftId = ApplicationStatus::idForDraft();
        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->whereExists(self::boilerChiefSubdivisionExistsSubquery())
            ->whereDoesntHave('user', function (Builder $userQuery): void {
                $userQuery->whereIn('role_id', User::MANAGEMENT_EDITOR_ROLE_IDS);
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

    /**
     * @param  Builder<Application>  $query
     */
    private static function applyAtManagement(Builder $query): void
    {
        $draftId = ApplicationStatus::idForDraft();
        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->whereExists(self::boilerChiefSubdivisionExistsSubquery())
            ->whereHas('items')
            ->whereDoesntHave('items', fn (Builder $itemQuery) => $itemQuery->where('is_checked', true))
            ->whereDoesntHave('items', function (Builder $itemQuery): void {
                $itemQuery->whereRaw("TRIM(COALESCE(reason_not_selected, '')) <> ''");
            })
            ->where(function (Builder $released): void {
                $released
                    ->whereNotNull('approved_by_user_id')
                    ->orWhereHas('user', function (Builder $userQuery): void {
                        $userQuery->whereIn('role_id', User::MANAGEMENT_EDITOR_ROLE_IDS);
                    });
            });
    }

    /**
     * @param  Builder<Application>  $query
     */
    private static function applyPending(Builder $query): void
    {
        $pendingId = ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
        $draftId = ApplicationStatus::idForDraft();
        $query->notArchived()
            ->where('application_status_id', '!=', $draftId)
            ->where(function (Builder $statusQuery) use ($pendingId): void {
                $statusQuery
                    ->where('application_status_id', $pendingId)
                    ->orWhereNull('application_status_id');
            });
    }

    /**
     * @param  Builder<Application>  $query
     */
    private static function applyStatus(Builder $query, string $statusName): void
    {
        $statusId = ApplicationStatus::idFor($statusName);
        $query->where('application_status_id', $statusId);
    }

    /**
     * @param  Builder<Application>  $query
     */
    private static function applyApproved(Builder $query): void
    {
        self::applyStatus($query, ApplicationStatus::NAME_APPROVED);
        $query->notArchived()
            ->whereNotNull('approved_by_user_id');
        self::excludeInTransit($query);
    }

    /**
     * @param  Builder<Application>  $query
     */
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

    /**
     * @param  Builder<Application>  $query
     */
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

    /**
     * @param  Builder<Application>  $query
     */
    private static function applyCompleted(Builder $query): void
    {
        $completedId = ApplicationStatus::idFor(ApplicationStatus::NAME_COMPLETED);
        $query->where(function (Builder $w) use ($completedId): void {
            $w->archived()
                ->orWhere('application_status_id', $completedId);
        });
    }

    /**
     * @param  Builder<Application>  $query
     */
    private static function excludeInTransit(Builder $query): void
    {
        $clone = Application::query()->select('applications.id');
        self::applyInTransit($clone);
        $query->whereNotIn('applications.id', $clone);
    }

    /**
     * @param  Builder<Application>  $query
     */
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
}
