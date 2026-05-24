<?php

namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ReportLayoutEquipmentApplications
{
    /**
     * @return Builder<Application>
     */
    public static function queryForUser(?User $user): Builder
    {
        return self::applyUserAccessScope(
            Application::query()
                ->with(['subdivision:id,name', 'items'])
                ->eligibleForReportEquipmentInsertion()
                ->orderByDesc('id')
                ->limit(300),
            $user
        );
    }

    /**
     * Заявки для подстановки оборудования в акт установки: доставлено, можно прикрепить акт,
     * не в архиве, акт и фото ещё не загружены.
     *
     * @return Builder<Application>
     */
    public static function queryForInstallationActUser(?User $user): Builder
    {
        return self::applyUserAccessScope(
            Application::query()
                ->with(['subdivision:id,name', 'items', 'installationActPhotos'])
                ->notArchived()
                ->orderByDesc('id')
                ->limit(500),
            $user
        );
    }

    /**
     * @return Collection<int, Application>
     */
    public static function collectionForUser(?User $user): Collection
    {
        return self::queryForUser($user)->get();
    }

    /**
     * @return Collection<int, Application>
     */
    public static function collectionForInstallationActUser(?User $user): Collection
    {
        return self::queryForInstallationActUser($user)
            ->get()
            ->filter(
                fn (Application $application): bool => $application->canUploadInstallationActAndPhotos()
                    && ! $application->hasInstallationActEvidence()
            )
            ->values();
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     equipment: list<array{name: string, quantity: string, line: string}>,
     *     foreman_user_id: int,
     *     subdivision_id: int
     * }>
     */
    public static function clientOptionsForUser(?User $user): array
    {
        return self::clientOptionsFromCollection(self::collectionForUser($user));
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     equipment: list<array{name: string, quantity: string, line: string}>,
     *     foreman_user_id: int,
     *     subdivision_id: int
     * }>
     */
    public static function clientOptionsForInstallationActUser(?User $user): array
    {
        return self::clientOptionsFromCollection(self::collectionForInstallationActUser($user));
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return list<array{
     *     id: int,
     *     label: string,
     *     equipment: list<array{name: string, quantity: string, line: string}>,
     *     foreman_user_id: int,
     *     subdivision_id: int
     * }>
     */
    private static function clientOptionsFromCollection(Collection $applications): array
    {
        return $applications
            ->map(fn (Application $application): array => self::clientOptionFromApplication($application))
            ->filter(fn (array $row): bool => $row['equipment'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    private static function applyUserAccessScope(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRoleId(4)) {
            return $query->forSiteForemanAccess($user);
        }

        if ($user->hasRoleId(User::BOILER_CHIEF_ROLE_ID)) {
            $subdivisionIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            if ($subdivisionIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn('subdivision_id', $subdivisionIds);
        }

        if ($user->hasRoleId(User::ACCOUNTANT_ROLE_ID)
            || $user->hasRoleId(User::ADMINISTRATOR_ROLE_ID)
            || $user->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return array{
     *     id: int,
     *     label: string,
     *     equipment: list<array{name: string, quantity: string, line: string}>,
     *     foreman_user_id: int,
     *     subdivision_id: int
     * }
     */
    private static function clientOptionFromApplication(Application $application): array
    {
        $lineItems = $application->items
            ->filter(fn (ApplicationItem $item): bool => $item->hasArrivedAtWarehouseForReport())
            ->map(function (ApplicationItem $item): array {
                $line = trim($item->equipment_display_name.' x '.$item->quantity_with_unit);

                return [
                    'name' => (string) $item->equipment_display_name,
                    'quantity' => (string) $item->quantity_with_unit,
                    'line' => $line,
                ];
            })
            ->filter(fn (array $row): bool => $row['line'] !== '')
            ->values()
            ->all();

        return [
            'id' => (int) $application->id,
            'label' => '#'.$application->id.' - '.($application->subdivision?->name ?? 'Без подразделения'),
            'equipment' => $lineItems,
            'foreman_user_id' => (int) ($application->user_id ?? 0),
            'subdivision_id' => (int) ($application->subdivision_id ?? 0),
        ];
    }
}
