<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\User;
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

        $applicationAnalytics = $this->buildApplicationAnalytics($user);
        $userDirectoryStats = $this->buildUserDirectoryStats($user);

        return view('dashboard', [
            'applicationAnalytics' => $applicationAnalytics,
            'userDirectoryStats' => $userDirectoryStats,
        ]);
    }

    /**
     * @return array{total_users: int, blocked_users: int}|null
     */
    private function buildUserDirectoryStats(?Authenticatable $user): ?array
    {
        if (! $user instanceof User || ! $user->hasRoleId(User::ADMINISTRATOR_ROLE_ID)) {
            return null;
        }

        return [
            'total_users' => User::query()->count(),
            'blocked_users' => User::query()->where('is_blocked', true)->count(),
        ];
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
}
