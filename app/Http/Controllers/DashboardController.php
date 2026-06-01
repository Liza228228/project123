<?php

// главная страница после входа
namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\User;
use App\Support\ApplicationApprovalListingFilter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const APPLICATION_ACCESS_ROLE_IDS = User::APPLICATION_LISTING_ROLE_IDS;

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
    private function buildApplicationAnalytics(?Authenticatable $user): ?array
    {
        if (! $user instanceof User || ! $user->hasAnyRoleId(self::APPLICATION_ACCESS_ROLE_IDS)) {
            return null;
        }

        $activeBase = $this->dashboardApplicationsListingQuery($user, 'active');
        $archivedBase = $this->dashboardApplicationsListingQuery($user, 'archived');

        $customEquipmentPending = 0;
        if ($user->hasAnyRoleId(User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS)) {
            $customEquipmentPending = ApplicationItem::queryPendingCustomEquipmentOrder()->count();
        }

        return [
            'total_active' => (clone $activeBase)->count(),
            'pending' => ApplicationApprovalListingFilter::countWithFilter(
                $activeBase,
                ApplicationApprovalListingFilter::KEY_PENDING,
                $user
            ),
            'approved' => ApplicationApprovalListingFilter::countWithFilter(
                $activeBase,
                ApplicationApprovalListingFilter::KEY_APPROVED,
                $user
            ),
            'partial' => ApplicationApprovalListingFilter::countWithFilter(
                $activeBase,
                ApplicationApprovalListingFilter::KEY_PARTIAL,
                $user
            ),
            'rejected' => ApplicationApprovalListingFilter::countWithFilter(
                $activeBase,
                ApplicationApprovalListingFilter::KEY_REJECTED,
                $user
            ),
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
    private function applyApplicationIndexScope(Builder $applicationsQuery, User $user): void
    {
        if ($user->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)) {
            $draftStatusId = ApplicationStatus::idForDraft();
            $applicationsQuery
                ->where('application_status_id', '!=', $draftStatusId)
                ->visibleToManagementEditors();
        }

        if ($user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            $chiefSubIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $applicationsQuery
                ->whereIn('subdivision_id', $chiefSubIds)
                ->visibleToBoilerChiefInListing();
        }

        if ($user->hasRoleId(4)) {
            $applicationsQuery->forSiteForemanAccess($user);
        }
    }
}
