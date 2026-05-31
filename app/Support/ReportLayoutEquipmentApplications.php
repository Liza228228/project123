<?php

// вспомогательная логика
namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ReportLayoutEquipmentApplications
{
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
    public static function collectionForUser(?User $user): Collection
    {
        return self::queryForUser($user)->get();
    }
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
    public static function clientOptionsForUser(?User $user): array
    {
        return self::clientOptionsFromCollection(self::collectionForUser($user));
    }
    public static function clientOptionsForInstallationActUser(?User $user): array
    {
        return self::clientOptionsFromCollection(
            self::collectionForInstallationActUser($user),
            forInstallationAct: true,
        );
    }
    private static function clientOptionsFromCollection(Collection $applications, bool $forInstallationAct = false): array
    {
        return $applications
            ->map(fn (Application $application): array => self::clientOptionFromApplication($application, $forInstallationAct))
            ->filter(fn (array $row): bool => $row['equipment'] !== [])
            ->values()
            ->all();
    }
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
    private static function clientOptionFromApplication(Application $application, bool $forInstallationAct = false): array
    {
        $lineItems = $application->items
            ->filter(fn (ApplicationItem $item): bool => $item->hasArrivedAtWarehouseForReport())
            ->map(function (ApplicationItem $item) use ($application, $forInstallationAct): ?array {
                $quantityLabel = $forInstallationAct
                    ? self::installationActQuantityLabelForItem($application, $item)
                    : (string) $item->quantity_with_unit;

                if ($forInstallationAct && trim($quantityLabel) === '') {
                    return null;
                }

                $line = trim($item->equipment_display_name.' x '.$quantityLabel);
                if ($line === '' || str_ends_with($line, ' x ')) {
                    return null;
                }

                return [
                    'name' => (string) $item->equipment_display_name,
                    'quantity' => $quantityLabel,
                    'line' => $line,
                ];
            })
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->all();

        return [
            'id' => (int) $application->id,
            'label' => '#'.$application->id.' - '.($application->subdivision?->name ?? 'Без подразделения'),
            'subdivision_name' => (string) ($application->subdivision?->name ?? ''),
            'equipment' => $lineItems,
            'foreman_user_id' => (int) ($application->user_id ?? 0),
            'subdivision_id' => (int) ($application->subdivision_id ?? 0),
        ];
    }

    private static function installationActQuantityLabelForItem(Application $application, ApplicationItem $item): string
    {
        $quantity = WarehouseStockBucket::installationActReportQuantityForApplicationItem(
            (float) $item->quantity,
            (int) $application->id,
            (int) $item->id,
        );

        if ($quantity < 0.0005) {
            return '';
        }

        if (($item->measurement_type ?? '') === 'clothing_size') {
            $size = trim((string) ($item->size_value ?? ''));
            if ($size !== '') {
                $rounded = (int) round($quantity);

                return $rounded === 1 ? $size : $rounded.'×'.$size;
            }
        }

        $unit = trim((string) ($item->quantity_unit ?? '')) ?: 'шт';
        if (abs($quantity - round($quantity)) < 0.0005) {
            return ((int) round($quantity)).' '.$unit;
        }

        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.').' '.$unit;
    }
}
