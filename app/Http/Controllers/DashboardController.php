<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Те же роли, что в {@see \App\Http\Middleware\EnsureUserCanAccessApplications}. */
    private const APPLICATION_ACCESS_ROLE_IDS = [1, 6, 4, 2, 3, 7];

    private const BOILER_CHIEF_ROLE_ID = 7;

    public function index(): View
    {
        $user = auth()->user();

        $mainWarehouse = $this->resolveMainWarehouse();
        $mainWarehouseId = $mainWarehouse?->id;

        $mainWarehouseBalances = collect();
        $deficitPositions = collect();

        if ($mainWarehouseId) {
            $mainWarehouseBalances = $this->baseBalanceQuery($mainWarehouseId)
                ->havingRaw('ABS(balance) >= 0.0005')
                ->orderByDesc('balance')
                ->limit(10)
                ->get();

            $deficitPositions = $this->baseBalanceQuery($mainWarehouseId)
                ->havingRaw('balance <= 0.0005')
                ->havingRaw('qty_out > 0.0005')
                ->orderBy('balance')
                ->limit(10)
                ->get();
        }

        $latestOperations = MaterialStockMovement::query()
            ->with(['equipment:id,name,measurement_unit_id', 'equipment.measurementUnit:id,code', 'warehouse:id,name', 'movementType:id,name'])
            ->when($mainWarehouseId, fn ($query) => $query->where('warehouse_id', $mainWarehouseId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $applicationAnalytics = $this->buildApplicationAnalytics($user);
        $canAccessMaterialsJournal = $user instanceof User && $user->hasAnyRoleId([1, 6, 2, 3]);

        return view('dashboard', [
            'mainWarehouse' => $mainWarehouse,
            'mainWarehouseBalances' => $mainWarehouseBalances,
            'deficitPositions' => $deficitPositions,
            'latestOperations' => $latestOperations,
            'applicationAnalytics' => $applicationAnalytics,
            'canAccessMaterialsJournal' => $canAccessMaterialsJournal,
        ]);
    }

    /**
     * @return array{
     *   total_active: int,
     *   pending: int,
     *   approved: int,
     *   partial: int,
     *   rejected: int,
     *   archived: int,
     *   custom_equipment_pending: int,
     * }|null
     */
    private function buildApplicationAnalytics(?Authenticatable $user): ?array
    {
        if (! $user instanceof User || ! $user->hasAnyRoleId(self::APPLICATION_ACCESS_ROLE_IDS)) {
            return null;
        }

        $activeBase = $this->dashboardApplicationsListingQuery($user, 'active');
        $archivedBase = $this->dashboardApplicationsListingQuery($user, 'archived');

        $pendingId = ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
        $approvedId = ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED);
        $rejectedId = ApplicationStatus::idFor(ApplicationStatus::NAME_REJECTED);
        $partialId = ApplicationStatus::idFor(ApplicationStatus::NAME_PARTIAL);

        $customEquipmentPending = 0;
        if ($user->hasAnyRoleId(User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS)) {
            $customEquipmentPending = ApplicationItem::queryPendingCustomEquipmentOrder()->count();
        }

        return [
            'total_active' => (clone $activeBase)->count(),
            'pending' => (clone $activeBase)->where(function (Builder $q) use ($pendingId): void {
                $q->where('application_status_id', $pendingId)
                    ->orWhereNull('application_status_id');
            })->count(),
            'approved' => (clone $activeBase)->where('application_status_id', $approvedId)->count(),
            'partial' => (clone $activeBase)->where('application_status_id', $partialId)->count(),
            'rejected' => (clone $activeBase)->where('application_status_id', $rejectedId)->count(),
            'archived' => (clone $archivedBase)->count(),
            'custom_equipment_pending' => $customEquipmentPending,
        ];
    }

    private function dashboardApplicationsListingQuery(User $user, string $archive): Builder
    {
        $request = Request::create('/applications', 'GET', ['archive' => $archive]);
        $applicationsQuery = Application::listingQuery($request);
        $this->applyApplicationIndexScope($applicationsQuery, $user);

        return $applicationsQuery;
    }

    /** Область видимости как в {@see ApplicationController::index}. */
    private function applyApplicationIndexScope(Builder $applicationsQuery, User $user): void
    {
        if ($user->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)) {
            $applicationsQuery->where(function ($outer): void {
                $outer->whereDoesntHave('user', function ($q): void {
                    $q->where('role_id', 4);
                })->orWhere(function ($q): void {
                    $q->whereHas('items')
                        ->whereDoesntHave('items', function ($itemQuery): void {
                            $itemQuery
                                ->where('is_checked', false)
                                ->whereRaw("TRIM(COALESCE(reason_not_selected, '')) = ''");
                        });
                });
            });
        }

        if ($user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            $chiefSubIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $applicationsQuery->whereIn('subdivision_id', $chiefSubIds);
        }

        if ($user->hasRoleId(4)) {
            $assignedSubdivisionIds = $user->assignedSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id): int => (int) $id);
            $applicationsQuery->whereIn('subdivision_id', $assignedSubdivisionIds);
        }
    }

    private function baseBalanceQuery(int $warehouseId)
    {
        $r = MaterialStockMovementType::NAME_RECEIPT;
        $i = MaterialStockMovementType::NAME_ISSUE;
        $a = MaterialStockMovementType::NAME_ADJUSTMENT;

        return MaterialStockMovement::query()
            ->join('equipment', 'equipment.id', '=', 'material_stock_movements.equipment_id')
            ->join('material_stock_movement_types as msm_types', 'msm_types.id', '=', 'material_stock_movements.material_stock_movement_type_id')
            ->leftJoin('measurement_units', 'measurement_units.id', '=', 'equipment.measurement_unit_id')
            ->where('material_stock_movements.warehouse_id', $warehouseId)
            ->groupBy('equipment.id', 'equipment.name', 'measurement_units.code')
            ->selectRaw('equipment.id as equipment_id')
            ->selectRaw('equipment.name as equipment_name')
            ->selectRaw("COALESCE(measurement_units.code, 'шт') as unit_code")
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN material_stock_movements.quantity WHEN msm_types.name = ? AND material_stock_movements.quantity > 0 THEN material_stock_movements.quantity ELSE 0 END) as qty_in', [$r, $a])
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN material_stock_movements.quantity WHEN msm_types.name = ? AND material_stock_movements.quantity < 0 THEN -material_stock_movements.quantity ELSE 0 END) as qty_out', [$i, $a])
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN -material_stock_movements.quantity ELSE material_stock_movements.quantity END) as balance', [$i]);
    }

    private function resolveMainWarehouse(): ?Warehouse
    {
        return Warehouse::query()
            ->where('is_primary', true)
            ->orderBy('id')
            ->first();
    }
}
